<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? 0;
$conditionType = $input['condition_type'] ?? '';
$purchasePrice = $input['purchase_price'] ?? 0;
$suggestedPrice = $input['suggested_price'] ?? 0;
$qty = $input['qty'] ?? 1;
$supplier = $input['supplier'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($productId) || empty($conditionType)) {
    error('请提供商品ID和状态类型');
}

if ($purchasePrice <= 0) {
    error('请提供有效进价');
}

if ($qty <= 0) {
    error('请提供有效数量');
}

$pdo = getDB();

$batchNo = 'B' . date('YmdHis') . sprintf('%04d', rand(0, 9999));

$stmt = $pdo->prepare('
    INSERT INTO inventory_batches 
    (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$stmt->execute([
    $productId,
    $conditionType,
    $batchNo,
    $purchasePrice,
    $suggestedPrice ?: $purchasePrice,
    $qty,
    $qty,
    $supplier,
    $remark
]);

$batchId = $pdo->lastInsertId();

$stmt = $pdo->prepare('
    INSERT INTO purchase_log 
    (product_id, condition_type, purchase_price, qty, supplier, remark)
    VALUES (?, ?, ?, ?, ?, ?)
');
$stmt->execute([$productId, $conditionType, $purchasePrice, $qty, $supplier, $remark]);

success([
    'message' => '入库成功',
    'data' => [
        'batch_id' => $batchId,
        'batch_no' => $batchNo,
        'product_id' => $productId,
        'condition_type' => $conditionType,
        'purchase_price' => $purchasePrice,
        'suggested_price' => $suggestedPrice,
        'qty' => $qty
    ]
]);
