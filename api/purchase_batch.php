<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

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

if ($qty <= 0) {
    error('请提供有效数量');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

// 幂等防重：网络延迟/连点导致同一提交重复请求时，5秒内相同特征直接返回成功（不重复入库）
$sig = implode('|', [$storeId, $productId, $conditionType, $qty, $purchasePrice, $suggestedPrice, $supplier, $remark]);
$now = time();
if (isset($_SESSION['purchase_last_sig']) && $_SESSION['purchase_last_sig'] === $sig
    && isset($_SESSION['purchase_last_time']) && ($now - $_SESSION['purchase_last_time']) < 5) {
    success([
        'message' => '入库成功',
        'data' => ['deduped' => true, 'batch_id' => $_SESSION['purchase_last_batch_id'] ?? null]
    ]);
}
$_SESSION['purchase_last_sig'] = $sig;
$_SESSION['purchase_last_time'] = $now;

$batchNo = 'B' . date('YmdHis') . sprintf('%04d', rand(0, 9999));

$stmt = $pdo->prepare('
    INSERT INTO inventory_batches
    (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark, store_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
');

$stmt->execute([
    $productId,
    $conditionType,
    $batchNo,
    $purchasePrice ?: null,
    $suggestedPrice ?: null,
    $qty,
    $qty,
    $supplier,
    $remark,
    $storeId
]);

$batchId = $pdo->lastInsertId();
$_SESSION['purchase_last_batch_id'] = $batchId;

$stmt = $pdo->prepare('
    INSERT INTO purchase_log
    (product_id, condition_type, purchase_price, qty, supplier, remark, store_id)
    VALUES (?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([$productId, $conditionType, $purchasePrice ?: null, $qty, $supplier, $remark, $storeId]);

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
