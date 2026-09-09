<?php
/**
 * pos_checkout.php — 结算下单（免登录，店铺隔离）
 * 1. 服务端重算单价：unit_price = MAX(在库批次进价) × 店铺比例；店员模式可改价
 * 2. 事务内 FIFO 锁定库存（batches.locked_qty + pos_order_locks），不足整单回滚
 * 3. 写 pos_orders(outbound_status=pending) + pos_order_items（cost 出库时回填）
 * 4. cash → pay_status=paid；scan → pending（店员确认收款后置 paid）
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/coupon_lib.php';
$storeId = requirePosStore();
$shopId = requirePosShop();
$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$couponIds = array_values(array_unique(array_filter(array_map('intval', $input['coupon_ids'] ?? []))));
$payMethod = $input['pay_method'] ?? 'cash';
$cashierName = trim($input['cashier_name'] ?? '');
$customerPhone = trim($input['customer_phone'] ?? '');
if ($customerPhone !== '' && !preg_match('/^1[3-9]\d{9}$/', $customerPhone)) {
    $customerPhone = '';
}
$discount = isset($input['staff_discount']) ? floatval($input['staff_discount']) : 1;

if (empty($items)) error('购物清单为空');
if (!in_array($payMethod, ['cash', 'scan'], true)) $payMethod = 'cash';
$pdo = getDB();
$shortages = []; // 库存不足明细（一次性回给收银台，便于展示并自动调整清单）

try {
    $stmt = $pdo->prepare('SELECT name, offline_price_ratio FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    $ratio = decimal($store['offline_price_ratio'] ?? 1.80);
    if ($ratio <= 0) $ratio = 1.80;

    $isStaff = posStaffActive();
    // 店员态才接受折扣/改价；折扣限 [0.5, 1]
    $staffDiscount = 1;
    if ($isStaff && $discount >= 0.5 && $discount <= 1) $staffDiscount = $discount;

    // 预校验商品归属本店 + 品相存在
    $prodStmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND store_id = ?');

    // ========== 事务 ==========
    $pdo->beginTransaction();

    // 1) 生成订单主表（先占 id）
    $orderNo = posOrderNo();
    $insertOrder = $pdo->prepare(
        "INSERT INTO pos_orders (store_id, shop_id, order_no, cashier_name, staff_mode, customer_phone, staff_discount, subtotal, discount_amount, payable, pay_method, pay_status, paid_at, outbound_status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, 'pending')"
    );
    $payStatus = $payMethod === 'cash' ? 'paid' : 'pending';
    $paidAt = $payMethod === 'cash' ? date('Y-m-d H:i:s') : null;
    $insertOrder->execute([$storeId, $shopId, $orderNo, $cashierName ?: null, $isStaff ? 1 : 0, $customerPhone !== '' ? $customerPhone : null, $isStaff ? $staffDiscount : null, $payMethod, $payStatus, $paidAt]);
    $orderId = (int)$pdo->lastInsertId();

    // 2) 逐 item：算价 + FIFO 锁批次
    $batchStmt = $pdo->prepare(
        "SELECT id, purchase_price, remaining_qty, locked_qty
         FROM inventory_batches
         WHERE store_id = ? AND product_id = ? AND condition_type = ?
           AND remaining_qty - locked_qty > 0
         ORDER BY purchased_at ASC, id ASC
         FOR UPDATE"
    );
    $lockUpdate = $pdo->prepare('UPDATE inventory_batches SET locked_qty = locked_qty + ? WHERE id = ?');
    $insertLock = $pdo->prepare('INSERT INTO pos_order_locks (order_id, order_item_id, batch_id, qty) VALUES (?, ?, ?, ?)');
    $insertItem = $pdo->prepare(
        'INSERT INTO pos_order_items (order_id, store_id, shop_id, product_id, condition_type, qty, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    // 直播占用：所有未结束(active)记账场次已录数量（POS 不可卖）
    $liveOccStmt = $pdo->prepare("SELECT COALESCE(SUM(li.qty),0)
        FROM live_ledger_item li
        JOIN live_ledger_session s ON s.id = li.session_id
        WHERE s.store_id = ? AND s.status = 'active'
          AND li.product_id = ? AND li.condition_type = ? AND li.is_gift = 0 AND li.is_temp = 0");

    $subtotal = 0;
    $resultItems = [];
    foreach ($items as $it) {
        $productId = intval($it['product_id'] ?? 0);
        $cond = $it['condition_type'] ?? '';
        $qty = intval($it['qty'] ?? 0);
        if ($productId <= 0 || $cond === '' || $qty <= 0) {
            throw new Exception('商品参数无效');
        }
        // 商品归属校验
        $prodStmt->execute([$productId, $storeId]);
        $prod = $prodStmt->fetch();
        if (!$prod) throw new Exception('商品不存在或不属于本店');

        // 定价：该品相所有在库批次的最大进价
        $priceStmt = $pdo->prepare(
            "SELECT MAX(purchase_price) AS max_cost
             FROM inventory_batches
             WHERE store_id = ? AND product_id = ? AND condition_type = ? AND remaining_qty - locked_qty > 0"
        );
        $priceStmt->execute([$storeId, $productId, $cond]);
        $maxCost = $priceStmt->fetchColumn();
        if ($maxCost === null || $maxCost === false) {
            // 已无可售批次（含全部被直播占用/锁定）
            $shortages[] = [
                'product_id' => $productId,
                'name' => $prod['name'],
                'condition_type' => $cond,
                'requested' => $qty,
                'available' => 0,
                'occupied_live' => 0
            ];
            continue;
        }

        // 店员改价（仅店员态接受，合理范围校验）
        $unitPrice = round($maxCost * $ratio, 2);
        if ($isStaff && isset($it['price'])) {
            $custom = floatval($it['price']);
            if ($custom > 0 && $custom <= 99999) $unitPrice = round($custom, 2);
        }

        // FIFO 锁批次
        $batchStmt->execute([$storeId, $productId, $cond]);
        $batches = $batchStmt->fetchAll();
        $liveOccStmt->execute([$storeId, $productId, $cond]);
        $occ = (int)$liveOccStmt->fetchColumn();
        $totalAvail = 0;
        foreach ($batches as $b) {
            $totalAvail += max(0, (int)$b['remaining_qty'] - (int)$b['locked_qty']);
        }
        $sellable = max(0, $totalAvail - $occ);
        if ($sellable < $qty) {
            $shortages[] = [
                'product_id' => $productId,
                'name' => $prod['name'],
                'condition_type' => $cond,
                'requested' => $qty,
                'available' => max(0, $sellable),
                'occupied_live' => $occ
            ];
            continue;
        }
        $need = $qty;
        $lockedBatches = [];
        foreach ($batches as $b) {
            if ($need <= 0) break;
            $avail = (int)$b['remaining_qty'] - (int)$b['locked_qty'];
            if ($avail <= 0) continue;
            $take = min($need, $avail);
            $lockedBatches[] = ['batch_id' => (int)$b['id'], 'take' => $take];
            $need -= $take;
        }
        if ($need > 0) {
            throw new Exception($prod['name'] . ' 该品相库存不足（可售 ' . ($qty - $need) . ' 件）');
        }

        // 插明细 + 锁
        $lineTotal = round($unitPrice * $qty, 2);
        $insertItem->execute([$orderId, $storeId, $shopId, $productId, $cond, $qty, $unitPrice, $lineTotal]);
        $itemId = (int)$pdo->lastInsertId();
        foreach ($lockedBatches as $lb) {
            $lockUpdate->execute([$lb['take'], $lb['batch_id']]);
            $insertLock->execute([$orderId, $itemId, $lb['batch_id'], $lb['take']]);
        }
        $subtotal += $lineTotal;
        $resultItems[] = [
            'product_id' => $productId,
            'name' => $prod['name'],
            'condition_type' => $cond,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal
        ];
    }

    if ($shortages) {
        throw new Exception('部分商品库存不足，请调整清单');
    }

    // 3) 金额结算 + 优惠券
    $subtotal = round($subtotal, 2);
    $discountAmount = $staffDiscount < 1 ? round($subtotal * (1 - $staffDiscount), 2) : 0;
    $couponAmount = 0.0;
    if ($couponIds) {
        if ($customerPhone === '') {
            throw new Exception('使用优惠券请先填写手机号');
        }
        // 校验可领可用：本店、该手机号、unused、活动有效、满减门槛
        $inList = implode(',', array_fill(0, count($couponIds), '?'));
        $q = $pdo->prepare(
            "SELECT cc.id AS claim_id, cc.campaign_id, cc.phone, cc.status,
                    cp.name, cp.coupon_type, cp.threshold, cp.amount, cp.stackable,
                    cp.start_at, cp.end_at, cp.status AS camp_status
             FROM coupon_claims cc
             JOIN coupon_campaigns cp ON cp.id = cc.campaign_id
             WHERE cc.id IN ($inList) AND cc.store_id = ? AND cc.phone = ? AND cc.status = 'unused'
               AND cp.shop_id = ?
             FOR UPDATE"
        );
        $params = array_merge($couponIds, [$storeId, $customerPhone, $shopId]);
        $q->execute($params);
        $claims = $q->fetchAll(PDO::FETCH_ASSOC);
        if (count($claims) !== count($couponIds)) {
            throw new Exception('部分优惠券不可用（可能已使用或不属于该手机号）');
        }
        $now = couponNow();
        $sawCampaign = [];
        foreach ($claims as $cl) {
            if ($cl['camp_status'] !== 'active') throw new Exception('优惠券活动已结束');
            if (!empty($cl['start_at']) && $cl['start_at'] > $now) throw new Exception('优惠券活动未开始');
            if (!empty($cl['end_at']) && $cl['end_at'] < $now) throw new Exception('优惠券已过期');
            if ((float)$cl['threshold'] > 0 && $subtotal < (float)$cl['threshold'] - 0.001) {
                throw new Exception('未达满减门槛（满 ¥' . round((float)$cl['threshold'], 2) . ' 可用）');
            }
            if (isset($sawCampaign[$cl['campaign_id']])) throw new Exception('同一活动每单最多使用 1 张');
            $sawCampaign[$cl['campaign_id']] = 1;
        }
        // 多张券叠加：仅当所选券的活动都允许叠加
        if (count($claims) > 1) {
            foreach ($claims as $cl) {
                if (!(int)$cl['stackable']) throw new Exception('所选券不可叠加，请只选 1 张');
            }
        }
        $lockClaim = $pdo->prepare(
            "UPDATE coupon_claims SET status = 'locked', order_id = ?, order_no = ?
             WHERE id = ? AND store_id = ? AND shop_id = ? AND phone = ? AND status = 'unused'"
        );
        $insOrderCoupon = $pdo->prepare(
            'INSERT INTO pos_order_coupons (order_id, claim_id, campaign_id, store_id, shop_id, phone, amount_off)
             VALUES (?,?,?,?,?,?,?)'
        );
        foreach ($claims as $cl) {
            $lockClaim->execute([$orderId, $orderNo, $cl['claim_id'], $storeId, $shopId, $customerPhone]);
            if ($lockClaim->rowCount() !== 1) throw new Exception('优惠券已被其他订单占用，请刷新重选');
            $couponAmount += (float)$cl['amount'];
            $insOrderCoupon->execute([$orderId, $cl['claim_id'], $cl['campaign_id'], $storeId, $shopId, $customerPhone, (float)$cl['amount']]);
        }
    }
    // 券后最低支付 0.01（个人收款码不能收 0 元）
    $couponAmount = round(min($couponAmount, max(0, $subtotal - $discountAmount - 0.01)), 2);
    $payable = round($subtotal - $discountAmount - $couponAmount, 2);

    $updateOrder = $pdo->prepare('UPDATE pos_orders SET subtotal = ?, discount_amount = ?, coupon_amount = ?, payable = ? WHERE id = ?');
    $updateOrder->execute([$subtotal, $discountAmount, $couponAmount, $payable, $orderId]);

    // 现金单（店员模式）当场核销；扫码单等顾客点「已付款」再核销
    if ($couponAmount > 0 && $payMethod === 'cash') {
        couponSetClaimsByOrder($pdo, $orderId, 'used');
    }

    $pdo->commit();

    success([
        'order_no' => $orderNo,
        'order_id' => $orderId,
        'payable' => $payable,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'coupon_amount' => $couponAmount,
        'pay_status' => $payStatus,
        'pay_method' => $payMethod,
        'items' => $resultItems
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logError($e->getMessage(), 'pos_checkout', ['store_id' => $storeId]);
    if ($shortages) {
        http_response_code(409);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => '部分商品库存不足，请调整清单',
            'fail_type' => 'stock',
            'shortages' => $shortages
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    error($e->getMessage(), 400);
}
