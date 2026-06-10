<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$items = $input['items'] ?? [];
$orderNo = $input['order_no'] ?? null;
$remark = $input['remark'] ?? null;
$platform = $input['platform'] ?? null;
$account = $input['account'] ?? null;
$financeGmv = isset($input['gmv']) ? floatval($input['gmv']) : null;
$financeOrders = isset($input['order_count']) ? intval($input['order_count']) : null;
$financeAdSpend = isset($input['ad_spend']) ? floatval($input['ad_spend']) : null;

if (empty($items)) {
    error('请选择要出库的商品');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$pdo->beginTransaction();

try {
    $outboundBatchNo = date('YmdHis');

    $totalItems = 0;
    $totalAmount = 0;
    $outboundRecords = [];

    foreach ($items as $item) {
        $batchId = $item['batch_id'] ?? 0;
        $productId = $item['product_id'] ?? 0;
        $conditionType = $item['condition_type'] ?? '';
        $qty = intval($item['qty'] ?? 0);
        $price = floatval($item['price'] ?? 0);

        if ($qty <= 0 || $price <= 0) {
            throw new Exception("商品 #{$productId} 的数量或价格无效");
        }

        // 与读 API 保持一致：store_id 存在则过滤，全平台视角则不限制
        if ($storeId) {
            $stmt = $pdo->prepare('SELECT * FROM inventory_batches WHERE id = ? AND remaining_qty >= ? AND store_id = ? FOR UPDATE');
            $stmt->execute([$batchId, $qty, $storeId]);
        } else {
            $stmt = $pdo->prepare('SELECT * FROM inventory_batches WHERE id = ? AND remaining_qty >= ? FOR UPDATE');
            $stmt->execute([$batchId, $qty]);
        }
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new Exception("批次 #{$batchId} 库存不足");
        }

        $newRemaining = $batch['remaining_qty'] - $qty;
        if ($storeId) {
            $stmt = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = ? WHERE id = ? AND store_id = ?');
            $stmt->execute([$newRemaining, $batchId, $storeId]);
        } else {
            $stmt = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = ? WHERE id = ?');
            $stmt->execute([$newRemaining, $batchId]);
        }

        $outboundStoreId = $storeId ?? $batch['store_id'];

        $stmt = $pdo->prepare('
            INSERT INTO outbound_log
            (batch_id, product_id, condition_type, qty, outbound_price, order_no, outbound_batch_no, remark, platform, account, store_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$batchId, $productId, $conditionType, $qty, $price, $orderNo, $outboundBatchNo, $remark, $platform, $account, $outboundStoreId]);

        $outboundRecords[] = [
            'batch_id' => $batchId,
            'product_id' => $productId,
            'condition_type' => $conditionType,
            'qty' => $qty,
            'price' => $price,
            'total' => $qty * $price
        ];

        $totalItems += $qty;
        $totalAmount += $qty * $price;
    }

    if ($totalItems === 0) {
        throw new Exception('没有有效的出库商品');
    }

    // 保存财务数据（选填）
    if (isset($outboundStoreId) && ($financeGmv !== null || $financeOrders !== null || $financeAdSpend !== null)) {
        $stmt = $pdo->prepare('
            INSERT INTO outbound_finance (store_id, outbound_batch_no, gmv, order_count, ad_spend)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$outboundStoreId, $outboundBatchNo, $financeGmv, $financeOrders, $financeAdSpend]);
    }

    $pdo->commit();

    success([
        'message' => '出库成功',
        'data' => [
            'batch_no' => $outboundBatchNo,
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
            'records' => $outboundRecords
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
