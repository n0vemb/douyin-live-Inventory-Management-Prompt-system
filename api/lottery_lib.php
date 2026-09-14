<?php
/**
 * lottery_lib.php — 收银台抽奖共享逻辑（后台配置 / 收银台抽奖 / 门店待出库联动）
 *
 * 规则：
 *   单笔实付满 threshold 即可抽 1 次；中「再来一次」可追加（spin_again_limit 上限）
 *   权重法：奖品权重之和不足 100 时，剩余权重视为「未中奖」
 *   保底：活动配置 guarantee_prize_id 后，抽到「未中奖」自动改发保底奖品
 *   奖品落地：
 *     coupon  → 发放 coupon_claims（issue_type=lottery），需手机号
 *     product → 生成 0 元出库单并 FIFO 占用库存（随门店待出库正常出库扣库存）
 *     custom  → 生成 0 元出库单但不占库存（仅登记，仓库核对后发放）
 */
require_once __DIR__ . '/coupon_lib.php';

/** 活动是否在有效时间窗内 */
function lotteryCampaignInWindow($camp, $now = null) {
    if (empty($camp) || !is_array($camp)) return false;
    $now = $now ?: date('Y-m-d H:i:s');
    if (!empty($camp['start_at']) && $camp['start_at'] > $now) return false;
    if (!empty($camp['end_at']) && $camp['end_at'] < $now) return false;
    return true;
}

/** 当前门店生效的抽奖活动（门店专属优先，其次集团通用）
 *  时间窗在 SQL 里过滤，避免「已过期活动占满 LIMIT 导致有效活动查不到」 */
function lotteryActiveCampaign(PDO $pdo, $storeId, $shopId = 0) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "SELECT * FROM lottery_campaigns
         WHERE store_id = ? AND status = 'active' AND (shop_id = ? OR shop_id IS NULL)
           AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)
         ORDER BY (shop_id IS NULL) ASC, id DESC LIMIT 1"
    );
    $stmt->execute([(int)$storeId, (int)$shopId, $now, $now]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** 取单个奖品 */
function lotteryPrizeById(PDO $pdo, $prizeId) {
    $stmt = $pdo->prepare('SELECT * FROM lottery_prizes WHERE id = ?');
    $stmt->execute([(int)$prizeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** 商品某品相可售数量（扣除直播占用，与收银台可售口径一致） */
function lotteryProductStock(PDO $pdo, $storeId, $productId, $condition) {
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(remaining_qty - locked_qty), 0) FROM inventory_batches
         WHERE store_id = ? AND product_id = ? AND condition_type = ? AND remaining_qty - locked_qty > 0'
    );
    $stmt->execute([(int)$storeId, (int)$productId, (string)$condition]);
    $stock = (int)$stmt->fetchColumn();
    if ($stock > 0) {
        $occ = $pdo->prepare(
            "SELECT COALESCE(SUM(li.qty), 0) FROM live_ledger_item li
             JOIN live_ledger_session s ON s.id = li.session_id
             WHERE s.store_id = ? AND s.status = 'active' AND li.is_gift = 0 AND li.is_temp = 0
               AND li.product_id = ? AND li.condition_type = ?"
        );
        $occ->execute([(int)$storeId, (int)$productId, (string)$condition]);
        $stock = max(0, $stock - (int)$occ->fetchColumn());
    }
    return $stock;
}

/**
 * 奖品当前是否可参与抽奖
 * @param bool $isLastSpin 本单最后一次机会（此时排除「再来一次」，避免空转）
 * @param string $why 不可参与原因（出参，后台预览用）
 */
function lotteryPrizeAvailability(PDO $pdo, array $prize, $storeId, $campaign, $isLastSpin = false, &$why = null) {
    $why = '';
    $type = $prize['prize_type'] ?? 'none';
    $quota = (int)($prize['quota'] ?? 0);
    if ($quota > 0 && (int)($prize['won'] ?? 0) >= $quota) { $why = '已中完'; return false; }

    if ($type === 'spin_again') {
        if ($isLastSpin) { $why = '已是最后一次机会'; return false; }
        return true;
    }
    if ($type === 'coupon') {
        $cid = (int)($prize['coupon_campaign_id'] ?? 0);
        if ($cid <= 0) { $why = '未绑定券活动'; return false; }
        $stmt = $pdo->prepare('SELECT * FROM coupon_campaigns WHERE id = ?');
        $stmt->execute([$cid]);
        $camp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$camp || !couponCampaignActive($camp)) { $why = '券活动已结束'; return false; }
        if ((int)$camp['store_id'] !== (int)$storeId) { $why = '券活动不属于本店'; return false; }
        $total = (int)$camp['total_count'];
        if ($total > 0 && couponIssuedCount($pdo, $cid) >= $total) { $why = '券已发完'; return false; }
        return true;
    }
    if ($type === 'product') {
        $pid = (int)($prize['product_id'] ?? 0);
        $cond = (string)($prize['condition_type'] ?? '');
        if ($pid <= 0 || $cond === '') { $why = '未选择商品/品相'; return false; }
        $need = max(1, (int)($prize['qty'] ?? 1));
        if (lotteryProductStock($pdo, $storeId, $pid, $cond) < $need) { $why = '库存不足'; return false; }
        return true;
    }
    // custom / none
    return true;
}

/**
 * 转盘扇区（前端展示与后端抽取共用同一份）
 * - 「未中奖」权重 = 显式未中奖奖品权重 + 权重不足 100 的剩余部分
 * - 活动配置了保底奖品时，上述「未中奖」权重整体改由保底奖品承接（每单必中）
 */
function lotteryBuildSegments(PDO $pdo, array $campaign, $storeId, $isLastSpin = false) {
    $stmt = $pdo->prepare('SELECT * FROM lottery_prizes WHERE campaign_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC');
    $stmt->execute([(int)$campaign['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $segments = [];
    $sum = 0;
    $noneWeight = 0;
    $guaranteeOwn = 0;
    foreach ($rows as $r) {
        $w = (int)($r['weight'] ?? 0);
        if ($w <= 0) continue;
        // 「未中奖」先只累计权重，最后由保底奖品或默认「谢谢参与」承接
        if (($r['prize_type'] ?? '') === 'none') { $noneWeight += $w; continue; }
        // 保底奖品统一由下方「未中奖承接」逻辑生成，避免转盘出现重复扇区
        if (!empty($campaign['guarantee_prize_id']) && (int)$r['id'] === (int)$campaign['guarantee_prize_id']) {
            $sum += $w;          // 自身权重照常占用概率
            $guaranteeOwn += $w; // 与「未中奖」权重合并到保底扇区
            continue;
        }
        $why = '';
        if (!lotteryPrizeAvailability($pdo, $r, $storeId, $campaign, $isLastSpin, $why)) continue;
        $segments[] = [
            'id' => (int)$r['id'],
            'name' => (string)$r['name'],
            'type' => (string)$r['prize_type'],
            'weight' => $w,
            'qty' => max(1, (int)($r['qty'] ?? 1)),
            'note' => (string)($r['custom_note'] ?? ''),
            'guarantee' => 0,
        ];
        $sum += $w;
    }
    if ($sum < 100) $noneWeight += 100 - $sum;

    if ($noneWeight + $guaranteeOwn > 0) {
        $gp = null;
        if (!empty($campaign['guarantee_prize_id'])) {
            $cand = lotteryPrizeById($pdo, (int)$campaign['guarantee_prize_id']);
            $why = '';
            if ($cand && (int)$cand['campaign_id'] === (int)$campaign['id'] && (int)$cand['status'] === 1
                && lotteryPrizeAvailability($pdo, $cand, $storeId, $campaign, $isLastSpin, $why)) {
                $gp = $cand;
            }
        }
        if ($gp) {
            $segments[] = [
                'id' => (int)$gp['id'],
                'name' => (string)$gp['name'],
                'type' => (string)$gp['prize_type'],
                'weight' => $noneWeight + $guaranteeOwn,
                'qty' => max(1, (int)($gp['qty'] ?? 1)),
                'note' => (string)($gp['custom_note'] ?? ''),
                'guarantee' => 1,
            ];
        } else {
            $segments[] = ['id' => 0, 'name' => '谢谢参与', 'type' => 'none', 'weight' => $noneWeight, 'qty' => 0, 'note' => '', 'guarantee' => 0];
        }
    }
    return $segments;
}

/** 权重法抽一个（返回扇区数组；超出权重之和即「未中奖」） */
/**
 * 从给定扇区里按权重必中其一（用于「最后一把」剔除「再来一次」后的重抽，
 * 把它的权重按比例分给其余扇区，避免出现转盘上不存在的「谢谢参与」落点）
 */
function lotteryPickFromPool(array $pool) {
    $total = 0;
    foreach ($pool as $s) $total += max(0, (int)($s['weight'] ?? 0));
    if ($total <= 0) {
        return ['id' => 0, 'name' => '谢谢参与', 'type' => 'none', 'weight' => 0, 'qty' => 0, 'note' => '', 'guarantee' => 0];
    }
    $roll = random_int(1, $total);
    $acc = 0;
    foreach ($pool as $s) {
        $acc += max(0, (int)($s['weight'] ?? 0));
        if ($roll <= $acc) return $s;
    }
    return $pool[count($pool) - 1];
}

function lotteryPickSegment(array $segments) {
    $sum = 0;
    foreach ($segments as $s) $sum += (int)$s['weight'];
    if ($sum <= 0) return ['id' => 0, 'name' => '谢谢参与', 'type' => 'none', 'weight' => 0, 'qty' => 0, 'note' => ''];
    $roll = random_int(1, max($sum, 100));
    $acc = 0;
    foreach ($segments as $s) {
        $acc += (int)$s['weight'];
        if ($roll <= $acc) return $s;
    }
    return ['id' => 0, 'name' => '谢谢参与', 'type' => 'none', 'weight' => 0, 'qty' => 0, 'note' => ''];
}

/** 释放订单占用的锁定库存（奖品单作废用） */
function lotteryReleaseOrderLocks(PDO $pdo, $orderId) {
    $locks = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
    $locks->execute([(int)$orderId, 'locked']);
    $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
    $relLock = $pdo->prepare("UPDATE pos_order_locks SET status = 'released' WHERE id = ?");
    foreach ($locks->fetchAll(PDO::FETCH_ASSOC) as $lk) {
        $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
        $relLock->execute([(int)$lk['id']]);
    }
}

/**
 * 生成奖品出库单（0 元单，进「门店待出库」）
 * 在库商品：FIFO 占用批次；自定义/无库存：登记不占库存（inventory_tracked=0）
 * @return array ['order_id'=>int,'order_no'=>string,'tracked'=>bool]
 */
function lotteryCreatePrizeOrder(PDO $pdo, array $order, array $prize, $phone = '') {
    $storeId = (int)$order['store_id'];
    $shopId = $order['shop_id'] !== null ? (int)$order['shop_id'] : null;
    $type = $prize['prize_type'] ?? 'custom';
    $qty = max(1, (int)($prize['qty'] ?? 1));
    $productId = (int)($prize['product_id'] ?? 0);
    $cond = (string)($prize['condition_type'] ?? '');
    $note = trim((string)($prize['custom_note'] ?? ''));
    $itemName = (string)$prize['name'];
    if ($note !== '' && mb_strpos($itemName, $note) === false) $itemName .= '（' . $note . '）';

    // 商品奖：判断是否真的有库存可扣
    $tracked = false;
    if ($type === 'product' && $productId > 0 && $cond !== '') {
        $tracked = lotteryProductStock($pdo, $storeId, $productId, $cond) >= $qty;
    }

    $orderNo = posOrderNo();
    $ins = $pdo->prepare(
        "INSERT INTO pos_orders (store_id, shop_id, source, order_no, cashier_name, staff_mode, customer_phone,
                                 subtotal, discount_amount, coupon_amount, payable, pay_method, pay_status, paid_at, outbound_status)
         VALUES (?, ?, 'lottery', ?, '抽奖奖品', 0, ?, 0, 0, 0, 0, 'cash', 'paid', NOW(), 'pending')"
    );
    $ins->execute([$storeId, $shopId, $orderNo, $phone !== '' ? $phone : null]);
    $prizeOrderId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare(
        'INSERT INTO pos_order_items (order_id, store_id, shop_id, product_id, condition_type, item_name, qty,
                                      unit_price, line_total, is_prize, inventory_tracked)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, ?)'
    );
    $insItem->execute([
        $prizeOrderId, $storeId, $shopId,
        $productId > 0 ? $productId : 0,
        $cond !== '' ? $cond : 'prize',
        mb_substr($itemName, 0, 250),
        $qty,
        $tracked ? 1 : 0
    ]);
    $itemId = (int)$pdo->lastInsertId();

    if ($tracked) {
        // FIFO 占用批次（与收银台下单同一套锁定机制，出库时真正扣减）
        $batchStmt = $pdo->prepare(
            "SELECT id, remaining_qty, locked_qty FROM inventory_batches
             WHERE store_id = ? AND product_id = ? AND condition_type = ? AND remaining_qty - locked_qty > 0
             ORDER BY purchased_at ASC, id ASC FOR UPDATE"
        );
        $batchStmt->execute([$storeId, $productId, $cond]);
        $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
        $need = $qty;
        $lockUpd = $pdo->prepare('UPDATE inventory_batches SET locked_qty = locked_qty + ? WHERE id = ?');
        $lockIns = $pdo->prepare('INSERT INTO pos_order_locks (order_id, order_item_id, batch_id, qty) VALUES (?, ?, ?, ?)');
        foreach ($batches as $b) {
            if ($need <= 0) break;
            $take = min($need, (int)$b['remaining_qty'] - (int)$b['locked_qty']);
            if ($take <= 0) continue;
            $lockUpd->execute([$take, (int)$b['id']]);
            $lockIns->execute([$prizeOrderId, $itemId, (int)$b['id'], $take]);
            $need -= $take;
        }
        if ($need > 0) {
            // 并发兜底：库存被别人抢走一部分 → 退化为仅登记，不占用已锁的
            $tracked = false;
            lotteryReleaseOrderLocks($pdo, $prizeOrderId);
            $pdo->prepare('UPDATE pos_order_items SET inventory_tracked = 0 WHERE id = ?')->execute([$itemId]);
        }
    }

    return ['order_id' => $prizeOrderId, 'order_no' => $orderNo, 'tracked' => $tracked];
}

/**
 * 来源订单作废/删除时，同步回收抽奖奖励（券退回、奖品单作废）
 */
function lotteryVoidBySourceOrder(PDO $pdo, $orderId, $reason = '来源订单已作废') {
    $stmt = $pdo->prepare("SELECT * FROM lottery_draws WHERE order_id = ? AND claim_status <> 'voided'");
    $stmt->execute([(int)$orderId]);
    $draws = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$draws) return 0;
    $cnt = 0;
    foreach ($draws as $d) {
        $drawId = (int)$d['id'];
        // 已发出的券：未使用的收回作废
        if ((int)$d['coupon_claim_id'] > 0) {
            $pdo->prepare("UPDATE coupon_claims SET status = 'expired', released_at = NOW()
                           WHERE id = ? AND status = 'unused'")->execute([(int)$d['coupon_claim_id']]);
        }
        // 奖品出库单：仍待出库则释放库存并作废
        if ((int)$d['prize_order_id'] > 0) {
            $po = $pdo->prepare('SELECT id, outbound_status FROM pos_orders WHERE id = ? FOR UPDATE');
            $po->execute([(int)$d['prize_order_id']]);
            $poRow = $po->fetch(PDO::FETCH_ASSOC);
            if ($poRow && $poRow['outbound_status'] === 'pending') {
                lotteryReleaseOrderLocks($pdo, (int)$poRow['id']);
                $pdo->prepare("UPDATE pos_orders SET outbound_status = 'voided', void_reason = ?, completed_at = NOW() WHERE id = ?")
                    ->execute([mb_substr('来源订单作废：' . $reason, 0, 250), (int)$poRow['id']]);
            }
        }
        // 已中出的奖品数量回退，避免占用配额
        if ((int)$d['prize_id'] > 0 && $d['prize_type'] !== 'spin_again') {
            $pdo->prepare('UPDATE lottery_prizes SET won = GREATEST(won - 1, 0) WHERE id = ?')->execute([(int)$d['prize_id']]);
        }
        $pdo->prepare("UPDATE lottery_draws SET claim_status = 'voided' WHERE id = ?")->execute([$drawId]);
        $cnt++;
    }
    return $cnt;
}
