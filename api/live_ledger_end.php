<?php
/**
 * live_ledger_end.php — 结束直播并出库
 * POST { session_id }
 *
 * 流程：
 * 1. 校验场次 active
 * 2. 汇总所有客户非赠品购买 → 按 product 聚合
 * 3. 自动从库存批次扣减（先进先出 FIFO：按 purchased_at 升序扣）
 * 4. 写 outbound_log（复用现有出库表）+ sales_log（关联场次）
 * 5. 生成快照写入 live_ledger_session（snapshot_json + 汇总字段）
 * 6. 场次状态 → ended
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
if ($sessionId <= 0) error('缺少场次ID');

// 校验场次
$stmt = $pdo->prepare("SELECT * FROM live_ledger_session WHERE id = ? AND store_id = ?");
$stmt->execute([$sessionId, $storeId]);
$session = $stmt->fetch();
if (!$session) error('场次不存在');
if ($session['status'] === 'ended') error('场次已结束');
if ($session['status'] !== 'active') error('场次状态异常');

$data = ledgerLoadSession($pdo, $sessionId);
$customers = $data['customers'];
$settings = $data['settings'];

$pdo->beginTransaction();
try {
    // ===== 汇总购买（按 product_id 聚合，排除赠品）=====
    // 同时保留 客户→商品 明细，供出库时按客户逐一 FIFO（确保 outbound/sales_log 可关联客户，支持撤单/退货）
    $purchaseMap = [];   // product_id => { qty, sell_total }
    $customerItems = []; // customer_id => [ {product_id, qty, price, item_id} ]
    foreach ($customers as $c) {
        $cid = (int)$c['id'];
        foreach ($c['items'] as $item) {
            if (!empty($item['is_gift'])) continue;
            $pid = (int)$item['product_id'];
            $qty = (int)$item['qty'];
            $price = floatval($item['sell_price']);
            if (!isset($purchaseMap[$pid])) {
                $purchaseMap[$pid] = ['qty' => 0, 'sell_total' => 0.0];
            }
            $purchaseMap[$pid]['qty'] += $qty;
            $purchaseMap[$pid]['sell_total'] += $price * $qty;
            $customerItems[$cid][] = ['product_id' => $pid, 'qty' => $qty, 'price' => $price, 'item_id' => (int)$item['id']];
        }
    }

    if (empty($purchaseMap)) {
        throw new Exception('没有购买商品，无法出库');
    }

    $outboundBatchNo = 'LL' . date('YmdHis');
    $totalQty = 0;
    $totalGmv = 0.0;

    // ===== 出库扣库存（FIFO，按客户逐一扣，保证可追溯客户）=====
    // 每个客户的每件商品独立 FIFO：从最早批次扣，记录 customer_id + item_id
    // 预取所有商品批次（有库存的）
    $allBatches = [];
    foreach ($purchaseMap as $pid => $need) {
        $stmt = $pdo->prepare("SELECT * FROM inventory_batches WHERE product_id = ? AND remaining_qty > 0" . ($storeId ? " AND store_id = ?" : "") . " ORDER BY purchased_at ASC, id ASC FOR UPDATE");
        $params = [$pid];
        if ($storeId) $params[] = $storeId;
        $stmt->execute($params);
        $allBatches[$pid] = $stmt->fetchAll();
    }

    // 客户顺序：按 customer id（保持与明细添加顺序一致）
    foreach ($customerItems as $cid => $items) {
        foreach ($items as $item) {
            $pid = $item['product_id'];
            $needQty = $item['qty'];
            $price = $item['price'];
            $totalQty += $needQty;
            $totalGmv += $price * $needQty;

            $remaining = $needQty;
            foreach ($allBatches[$pid] as &$batch) {
                if ($remaining <= 0) break;
                if ($batch['remaining_qty'] <= 0) continue;
                $take = min($remaining, (int)$batch['remaining_qty']);
                $batch['remaining_qty'] -= $take;
                $newRemaining = $batch['remaining_qty'];
                $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = ? WHERE id = ?" . ($storeId ? " AND store_id = ?" : ""));
                $upParams = [$newRemaining, $batch['id']];
                if ($storeId) $upParams[] = $storeId;
                $stmt->execute($upParams);

                // 写 outbound_log（带场次 id + 场次账号，便于商品流水追溯"哪场直播/哪个运营"）
                $stmt = $pdo->prepare("INSERT INTO outbound_log (batch_id, product_id, condition_type, qty, outbound_price, order_no, outbound_batch_no, remark, platform, account, live_session_id, store_id, shipping_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$batch['id'], $pid, $batch['condition_type'], $take, $price, null, $outboundBatchNo, '直播出库(' . $session['session_name'] . ')', 'live', $session['account'] ?? '', (int)$session['id'], $storeId, null]);

                // 写 live_ledger_outbound 关联（带 customer_id + item_id）
                $outboundLogId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO live_ledger_outbound (session_id, customer_id, item_id, product_id, qty, batch_id, outbound_log_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$sessionId, $cid, $item['item_id'], $pid, $take, $batch['id'], $outboundLogId]);

                // 写 sales_log（live_session_id 置 NULL：sales_log 外键指向旧 live_sessions 表，
                // 直播记账有独立历史体系，不关联旧场次，避免外键冲突）
                // 记录当时批次 batch_id + 固化进价 purchase_cost，销售记录页可精确追溯
                $stmt = $pdo->prepare("INSERT INTO sales_log (store_id, product_id, condition_type, sale_price, purchase_cost, batch_id, qty, live_session_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)");
                $stmt->execute([$storeId, $pid, $batch['condition_type'], $price, $batch['purchase_price'], $batch['id'], $take]);

                $remaining -= $take;
            }
            unset($batch);

            if ($remaining > 0) {
                throw new Exception("商品 #$pid 库存不足，还差 $remaining 件");
            }
        }
    }

    // ===== 计算汇总快照 =====
    $totalCost = 0.0;
    $totalShipping = 0.0;
    $totalPlatformFee = 0.0;
    $totalPacking = 0.0;
    $totalProfitBase = 0.0;
    $totalGiftCost = 0.0;
    $totalProfitWithGift = 0.0;
    $totalReduceAmount = 0.0;
    $totalProfitWithReduce = 0.0;
    $totalProfitBoth = 0.0;

    $snapshotCustomers = [];
    foreach ($customers as $c) {
        $m = ledgerCalcCustomer($c, $settings);
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
        'outbound_batch_no' => $outboundBatchNo,
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

    // ===== 更新场次（状态 ended + 汇总 + 快照）=====
    $stmt = $pdo->prepare("UPDATE live_ledger_session SET
        status = 'ended', ended_at = NOW(),
        total_customers = ?, total_qty = ?, total_gmv = ?, total_cost = ?,
        total_shipping = ?, total_platform_fee = ?, total_packing = ?,
        total_profit_base = ?, total_gift_cost = ?, total_profit_with_gift = ?,
        total_reduce_amount = ?, total_profit_with_reduce = ?, total_profit_both = ?,
        snapshot_json = ?, outbound_batch_no = ?
        WHERE id = ?");
    $stmt->execute([
        count($customers), $totalQty, round($totalGmv, 2), round($totalCost, 2),
        round($totalShipping, 2), round($totalPlatformFee, 2), round($totalPacking, 2),
        round($totalProfitBase, 2), round($totalGiftCost, 2), round($totalProfitWithGift, 2),
        round($totalReduceAmount, 2), round($totalProfitWithReduce, 2), round($totalProfitBoth, 2),
        json_encode($snapshot, JSON_UNESCAPED_UNICODE), $outboundBatchNo,
        $sessionId
    ]);

    $pdo->commit();

    success([
        'message' => '直播已结束，出库完成',
        'data' => [
            'outbound_batch_no' => $outboundBatchNo,
            'total_qty' => $totalQty,
            'total_gmv' => round($totalGmv, 2),
            'totals' => $snapshot['totals'],
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('结束直播失败: ' . $e->getMessage());
}
