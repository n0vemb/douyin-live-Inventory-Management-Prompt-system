<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? 0;
$conditionType = $input['condition_type'] ?? '';
$salePrice = $input['sale_price'] ?? 0;
$qty = $input['qty'] ?? 1;
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($productId) || empty($conditionType)) {
    error('请提供商品ID和状态类型');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

if ($salePrice <= 0) {
    error('请提供有效售价');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('
        SELECT * FROM live_inventory
        WHERE live_session_id = ? AND product_id = ? AND condition_type = ? AND store_id = ?
        FOR UPDATE
    ');
    $stmt->execute([$liveSessionId, $productId, $conditionType, $storeId]);
    $liveInv = $stmt->fetch();

    if (!$liveInv) {
        throw new Exception('直播库存记录不存在');
    }

    if ($liveInv['current_stock'] < $qty) {
        throw new Exception('直播库存不足，当前库存: ' . $liveInv['current_stock']);
    }

    $beforeQty = $liveInv['current_stock'];
    $afterQty = $beforeQty - $qty;

    $stmt = $pdo->prepare('
        UPDATE live_inventory 
        SET current_stock = ?, live_price = ?
        WHERE id = ?
    ');
    $stmt->execute([$afterQty, $salePrice, $liveInv['id']]);

    $stmt = $pdo->prepare('
        INSERT INTO sales_log
        (product_id, condition_type, sale_price, qty, live_session_id, store_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$productId, $conditionType, $salePrice, $qty, $liveSessionId, $storeId]);

    $pdo->commit();

    success([
        'data' => [
            'before_qty' => $beforeQty,
            'after_qty' => $afterQty,
            'sale_price' => $salePrice
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
