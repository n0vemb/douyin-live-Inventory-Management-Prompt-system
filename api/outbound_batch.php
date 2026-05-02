<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$items = $input['items'] ?? [];
$orderNo = $input['order_no'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($items)) {
    error('请选择要出库的商品');
}

$pdo = getDB();

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
            continue;
        }

        $stmt = $pdo->prepare('SELECT * FROM inventory_batches WHERE id = ? AND remaining_qty >= ? FOR UPDATE');
        $stmt->execute([$batchId, $qty]);
        $batch = $stmt->fetch();

        if (!$batch) {
            throw new Exception("批次 #{$batchId} 库存不足");
        }

        $newRemaining = $batch['remaining_qty'] - $qty;
        $stmt = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = ? WHERE id = ?');
        $stmt->execute([$newRemaining, $batchId]);

        $stmt = $pdo->prepare('
            INSERT INTO outbound_log
            (batch_id, product_id, condition_type, qty, outbound_price, order_no, outbound_batch_no, remark)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$batchId, $productId, $conditionType, $qty, $price, $orderNo, $outboundBatchNo, $remark]);

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
