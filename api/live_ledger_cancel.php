<?php
/**
 * live_ledger_cancel.php — 已结束场次：撤单 / 退货
 * POST { session_id, customer_id, item_id? }
 *   - 有 item_id → 退货单个商品（该商品回库存，费用重算）
 *   - 无 item_id  → 撤单整单（全部商品回库存，客户删除，费用重算）
 *
 * 规则：
 * - 商品回退原批次（退回时剩余库存 += qty）
 * - 若退货后客户无任何商品 → 整单取消（运费/平台扣点/包装成本按单费用全部归零）
 * - 场次汇总（total_* 字段 + snapshot_json）全量重算
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/live_ledger_common.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$customerId = isset($input['customer_id']) ? (int)$input['customer_id'] : 0;
$itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;
if ($sessionId <= 0 || $customerId <= 0) error('缺少参数');

// 校验场次已结束
$stmt = $pdo->prepare("SELECT * FROM live_ledger_session WHERE id = ? AND store_id = ?");
$stmt->execute([$sessionId, $storeId]);
$session = $stmt->fetch();
if (!$session) error('场次不存在');
if ($session['status'] !== 'ended') error('只有已结束的场次才能撤单/退货');

// 校验客户属于该场次
$stmt = $pdo->prepare("SELECT * FROM live_ledger_customer WHERE id = ? AND session_id = ?");
$stmt->execute([$customerId, $sessionId]);
$customer = $stmt->fetch();
if (!$customer) error('客户不存在');

$pdo->beginTransaction();
try {
    // ===== 1. 确定要删除的商品 =====
    if ($itemId > 0) {
        // 退货单个商品
        $stmt = $pdo->prepare("SELECT * FROM live_ledger_item WHERE id = ? AND customer_id = ?");
        $stmt->execute([$itemId, $customerId]);
        $itemsToDelete = $stmt->fetchAll();
        if (empty($itemsToDelete)) error('商品不存在');
    } else {
        // 撤单整单
        $stmt = $pdo->prepare("SELECT * FROM live_ledger_item WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $itemsToDelete = $stmt->fetchAll();
    }

    // ===== 2. 逐商品：回补批次库存 + 删出库记录 + 删销售记录 =====
    $returnedQty = 0;
    foreach ($itemsToDelete as $item) {
        // 该商品对应的出库记录（按 item_id 精确匹配；历史数据可能按 product+qty 匹配）
        $stmt = $pdo->prepare("SELECT lo.*, ib.condition_type, ib.purchase_price AS batch_purchase_price FROM live_ledger_outbound lo LEFT JOIN inventory_batches ib ON lo.batch_id = ib.id WHERE lo.session_id = ? AND lo.customer_id = ? AND lo.item_id = ?");
        $stmt->execute([$sessionId, $customerId, $item['id']]);
        $outbounds = $stmt->fetchAll();

        // 历史数据 item_id 可能没回填好，用 product_id + qty 兜底匹配
        if (empty($outbounds)) {
            $stmt = $pdo->prepare("SELECT lo.*, ib.condition_type, ib.purchase_price AS batch_purchase_price FROM live_ledger_outbound lo LEFT JOIN inventory_batches ib ON lo.batch_id = ib.id WHERE lo.session_id = ? AND lo.customer_id = ? AND lo.product_id = ?");
            $stmt->execute([$sessionId, $customerId, $item['product_id']]);
            $outbounds = $stmt->fetchAll();
        }

        // 兜底：历史数据 outbound 关联缺失（商品已出库但记录归到别的客户），
        // 用该商品的 sales_log 回补库存（不依赖 outbound 记录）
        $fallbackSalesLog = null;
        if (empty($outbounds)) {
            $stmt = $pdo->prepare("SELECT id, product_id, batch_id, qty FROM sales_log WHERE product_id = ? AND qty >= ? AND batch_id IS NOT NULL ORDER BY id LIMIT 1");
            $stmt->execute([$item['product_id'], (int)$item['qty']]);
            $fallbackSalesLog = $stmt->fetch();
        }

        // 需处理总量 = item 的 qty（防护：历史聚合的 outbound qty 可能大于该客户实际购买量）
        $needQty = (int)$item['qty'];
        foreach ($outbounds as $ob) {
            if ($needQty <= 0) break;
            $obQty = (int)$ob['qty'];
            $processQty = min($needQty, $obQty);
            $needQty -= $processQty;

            // 回补批次库存（只回补本次处理的数量）
            $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = remaining_qty + ? WHERE id = ?" . ($storeId ? " AND store_id = ?" : ""));
            $params = [$processQty, (int)$ob['batch_id']];
            if ($storeId) $params[] = $storeId;
            $stmt->execute($params);

            // 删销售记录（按商品+成色+数量匹配，删对应数量）
            // 注：历史 outbound 回填的 batch_id 可能错位，不依赖 batch_id 匹配
            $stmt = $pdo->prepare("SELECT id FROM sales_log WHERE product_id = ? AND condition_type = ? AND qty >= ? AND purchase_cost = ? ORDER BY id LIMIT 1");
            $stmt->execute([$ob['product_id'], $ob['condition_type'], $processQty, $ob['batch_purchase_price']]);
            $sl = $stmt->fetch();
            if (!$sl) {
                // 兜底：忽略成色/进价再匹配一次
                $stmt = $pdo->prepare("SELECT id FROM sales_log WHERE product_id = ? AND qty >= ? ORDER BY id LIMIT 1");
                $stmt->execute([$ob['product_id'], $processQty]);
                $sl = $stmt->fetch();
            }
            if ($sl) {
                $stmt = $pdo->prepare("DELETE FROM sales_log WHERE id = ?");
                $stmt->execute([$sl['id']]);
            }

            // 出库记录：处理量 >= 记录量 → 整条删；否则减量（保留剩余）
            if ($processQty >= $obQty) {
                if (!empty($ob['outbound_log_id'])) {
                    $stmt = $pdo->prepare("DELETE FROM outbound_log WHERE id = ?");
                    $stmt->execute([$ob['outbound_log_id']]);
                }
                $stmt = $pdo->prepare("DELETE FROM live_ledger_outbound WHERE id = ?");
                $stmt->execute([$ob['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE live_ledger_outbound SET qty = qty - ? WHERE id = ?");
                $stmt->execute([$processQty, $ob['id']]);
            }

            $returnedQty += $processQty;
        }

        // 兜底：无 outbound 关联时，用 sales_log 回补库存（该商品实际已出库）
        if ($needQty > 0 && $fallbackSalesLog) {
            $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = remaining_qty + ? WHERE id = ?" . ($storeId ? " AND store_id = ?" : ""));
            $params = [$needQty, (int)$fallbackSalesLog['batch_id']];
            if ($storeId) $params[] = $storeId;
            $stmt->execute($params);

            $stmt = $pdo->prepare("DELETE FROM sales_log WHERE id = ?");
            $stmt->execute([$fallbackSalesLog['id']]);

            $returnedQty += $needQty;
            $needQty = 0;
        }

        // 删商品明细
        $stmt = $pdo->prepare("DELETE FROM live_ledger_item WHERE id = ?");
        $stmt->execute([$item['id']]);
    }

    // ===== 3. 客户是否还有商品？没有则整单取消（删客户+赠品）=====
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_ledger_item WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $remainingItems = (int)$stmt->fetchColumn();

    $customerDeleted = false;
    if ($remainingItems <= 0) {
        // 删赠品
        $stmt = $pdo->prepare("DELETE FROM live_ledger_gift WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        // 删客户
        $stmt = $pdo->prepare("DELETE FROM live_ledger_customer WHERE id = ?");
        $stmt->execute([$customerId]);
        $customerDeleted = true;
    }

    // ===== 4. 全量重算场次汇总 + 快照 =====
    $data = ledgerLoadSession($pdo, $sessionId);
    $settings = $data['settings'];
    $customers = $data['customers'];

    $totalQty = 0; $totalGmv = 0.0; $totalCost = 0.0; $totalShipping = 0.0;
    $totalPlatformFee = 0.0; $totalPacking = 0.0; $totalProfitBase = 0.0;
    $totalGiftCost = 0.0; $totalProfitWithGift = 0.0;
    $totalReduceAmount = 0.0; $totalProfitWithReduce = 0.0; $totalProfitBoth = 0.0;

    $snapshotCustomers = [];
    foreach ($customers as $c) {
        $m = ledgerCalcCustomer($c, $settings);
        $totalQty += $m['total_qty'];
        $totalGmv += $m['gmv'];
        $totalCost += $m['cost'];
        $totalShipping += $m['shipping'];
        $totalPlatformFee += $m['platform_fee'];
        $totalPacking += $m['packing'];
        $totalProfitBase += $m['profit_base'];
        $totalGiftCost += $m['gift_cost'];
        $totalProfitWithGift += $m['profit_with_gift'];
        $totalReduceAmount += $m['reduce_amount'];
        $totalProfitWithReduce += $m['profit_with_reduce'];
        $totalProfitBoth += $m['profit_both'];

        $snapshotCustomers[] = [
            'nickname' => $c['nickname'],
            'vip_no' => $c['vip_no'],
            'metrics' => $m,
            'items' => array_map(function($i) {
                return [
                    'product_name' => $i['product_name'],
                    'qty' => $i['qty'],
                    'sell_price' => $i['sell_price'],
                    'purchase_cost' => $i['purchase_cost'],
                    'is_gift' => !empty($i['is_gift']),
                ];
            }, $c['items']),
            'gifts' => array_map(function($g) {
                return ['cost' => $g['cost'], 'description' => $g['description']];
            }, $c['gifts']),
        ];
    }

    $snapshot = [
        'settings' => $settings,
        'outbound_batch_no' => $session['outbound_batch_no'],
        'customers' => $snapshotCustomers,
        'totals' => [
            'customers' => count($customers),
            'qty' => $totalQty,
            'gmv' => round($totalGmv, 2),
            'cost' => round($totalCost, 2),
            'shipping' => round($totalShipping, 2),
            'platform_fee' => round($totalPlatformFee, 2),
            'packing' => round($totalPacking, 2),
            'profit_base' => round($totalProfitBase, 2),
            'gift_cost' => round($totalGiftCost, 2),
            'profit_with_gift' => round($totalProfitWithGift, 2),
            'reduce_amount' => round($totalReduceAmount, 2),
            'profit_with_reduce' => round($totalProfitWithReduce, 2),
            'profit_both' => round($totalProfitBoth, 2),
        ]
    ];

    $stmt = $pdo->prepare("UPDATE live_ledger_session SET
        total_customers = ?, total_qty = ?, total_gmv = ?, total_cost = ?,
        total_shipping = ?, total_platform_fee = ?, total_packing = ?,
        total_profit_base = ?, total_gift_cost = ?, total_profit_with_gift = ?,
        total_reduce_amount = ?, total_profit_with_reduce = ?, total_profit_both = ?,
        snapshot_json = ?
        WHERE id = ?");
    $stmt->execute([
        count($customers), $totalQty, round($totalGmv, 2), round($totalCost, 2),
        round($totalShipping, 2), round($totalPlatformFee, 2), round($totalPacking, 2),
        round($totalProfitBase, 2), round($totalGiftCost, 2), round($totalProfitWithGift, 2),
        round($totalReduceAmount, 2), round($totalProfitWithReduce, 2), round($totalProfitBoth, 2),
        json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        $sessionId
    ]);

    $pdo->commit();

    success([
        'message' => $itemId > 0 ? '退货成功' : '撤单成功',
        'data' => [
            'returned_qty' => $returnedQty,
            'customer_deleted' => $customerDeleted,
            'totals' => $snapshot['totals'],
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('操作失败: ' . $e->getMessage());
}
