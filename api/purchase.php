<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = (int)($input['product_id'] ?? 0);
$conditionType = trim((string)($input['condition_type'] ?? ''));
$purchasePrice = (float)($input['purchase_price'] ?? 0);
$suggestedPrice = (float)($input['suggested_price'] ?? 0);
$remark = $input['remark'] ?? '手动价格调整';

if ($productId <= 0 || $conditionType === '') {
    error('请提供有效的商品ID和状态类型');
}

if ($suggestedPrice <= 0) {
    error('请提供有效的建议售价');
}

$pdo = getDB();
$pdo->beginTransaction();

try {
    $allowedTypes = ['sealed', 'opened', 'boxless', 'flawed'];
    if (!in_array($conditionType, $allowedTypes, true)) {
        throw new RuntimeException('无效的状态类型');
    }

    $stmt = $pdo->prepare('
        SELECT id, purchase_price
        FROM inventory_batches
        WHERE product_id = ? AND condition_type = ? AND remaining_qty > 0
    ');
    $stmt->execute([$productId, $conditionType]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batches)) {
        throw new RuntimeException('当前状态没有可调整价格的库存');
    }

    $newPurchasePrice = $purchasePrice > 0 ? $purchasePrice : null;
    $updateSql = $newPurchasePrice === null
        ? 'UPDATE inventory_batches SET suggested_price = ?, remark = CONCAT(IFNULL(remark, ""), IF(IFNULL(remark, "") = "", "", " | "), ?) WHERE id = ?'
        : 'UPDATE inventory_batches SET purchase_price = ?, suggested_price = ?, remark = CONCAT(IFNULL(remark, ""), IF(IFNULL(remark, "") = "", "", " | "), ?) WHERE id = ?';

    $updateStmt = $pdo->prepare($updateSql);
    foreach ($batches as $batch) {
        if ($newPurchasePrice === null) {
            $updateStmt->execute([$suggestedPrice, $remark, (int)$batch['id']]);
        } else {
            $updateStmt->execute([$newPurchasePrice, $suggestedPrice, $remark, (int)$batch['id']]);
        }
    }

    $pdo->commit();
    success([
        'message' => '价格更新成功',
        'data' => [
            'product_id' => $productId,
            'condition_type' => $conditionType,
            'updated_batches' => count($batches),
            'purchase_price' => $newPurchasePrice,
            'suggested_price' => $suggestedPrice
        ]
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
