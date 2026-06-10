<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$batchId = (int)($input['batch_id'] ?? 0);
$productId = (int)($input['product_id'] ?? 0);
$conditionType = trim((string)($input['condition_type'] ?? ''));
$qty = (int)($input['qty'] ?? 0);
$purchasePrice = (float)($input['purchase_price'] ?? 0);
$suggestedPrice = (float)($input['suggested_price'] ?? 0);
$remark = $input['remark'] ?? '';

if ($batchId <= 0 || $productId <= 0 || $conditionType === '') {
    error('请提供有效的参数');
}

if ($qty < 0) {
    error('库存数量不能为负数');
}

if ($purchasePrice <= 0 || $suggestedPrice <= 0) {
    error('请提供有效的价格');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$pdo->beginTransaction();

try {
    // 查找批次
    $stmt = $pdo->prepare('SELECT * FROM inventory_batches WHERE id = ? AND store_id = ?');
    $stmt->execute([$batchId, $storeId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$batch) {
        throw new RuntimeException('批次不存在');
    }
    
    if ((int)$batch['product_id'] !== $productId) {
        throw new RuntimeException('批次与商品不匹配');
    }
    
    // 更新批次信息
    $updateRemark = trim($remark);
    if ($updateRemark !== '') {
        $updateRemark = $batch['remark'] ? $batch['remark'] . ' | ' . $updateRemark : $updateRemark;
    }
    
    $stmt = $pdo->prepare('
        UPDATE inventory_batches
        SET remaining_qty = ?,
            purchase_price = ?,
            suggested_price = ?,
            remark = ?
        WHERE id = ? AND store_id = ?
    ');
    $stmt->execute([
        $qty,
        $purchasePrice,
        $suggestedPrice,
        $updateRemark ?: $batch['remark'],
        $batchId,
        $storeId
    ]);
    
    // 同时更新live_inventory（如果存在） - 通过 product_id 和 condition_type 匹配
    $stmt = $pdo->prepare('
        UPDATE live_inventory
        SET current_stock = ?,
            live_price = ?,
            suggested_price = ?
        WHERE product_id = ? AND condition_type = ? AND store_id = ?
    ');
    $stmt->execute([$qty, $suggestedPrice, $suggestedPrice, $productId, $conditionType, $storeId]);
    
    $pdo->commit();
    success([
        'message' => '批次更新成功',
        'data' => [
            'batch_id' => $batchId,
            'product_id' => $productId,
            'condition_type' => $conditionType,
            'qty' => $qty,
            'purchase_price' => $purchasePrice,
            'suggested_price' => $suggestedPrice
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
