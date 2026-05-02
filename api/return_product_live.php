<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? 0;
$conditionType = $input['condition_type'] ?? '';
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($productId) || empty($conditionType)) {
    error('请提供商品ID和状态类型');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

$pdo = getDB();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('
        SELECT * FROM live_inventory
        WHERE live_session_id = ? AND product_id = ? AND condition_type = ?
        FOR UPDATE
    ');
    $stmt->execute([$liveSessionId, $productId, $conditionType]);
    $liveInv = $stmt->fetch();

    if (!$liveInv) {
        throw new Exception('直播库存记录不存在');
    }

    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(qty), 0) as total_sold
        FROM sales_log
        WHERE product_id = ? AND condition_type = ? AND live_session_id = ?
    ');
    $stmt->execute([$productId, $conditionType, $liveSessionId]);
    $totalSold = $stmt->fetch()['total_sold'];

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(qty_change), 0) as total_returned
        FROM inventory_log
        WHERE product_id = ? AND condition_type = ? AND live_session_id = ? AND change_type = 'return'
    ");
    $stmt->execute([$productId, $conditionType, $liveSessionId]);
    $totalReturned = $stmt->fetch()['total_returned'];

    $maxReturnable = $totalSold - $totalReturned;

    if ($maxReturnable <= 0) {
        throw new Exception('没有可退还的数量');
    }

    $beforeQty = $liveInv['current_stock'];
    $afterQty = $beforeQty + 1;

    if ($afterQty > $liveInv['initial_stock']) {
        throw new Exception('退还数量不能超过初始库存');
    }

    $stmt = $pdo->prepare('UPDATE live_inventory SET current_stock = ? WHERE id = ?');
    $stmt->execute([$afterQty, $liveInv['id']]);

    $stmt = $pdo->prepare('
        INSERT INTO inventory_log
        (product_id, condition_type, change_type, qty_change, before_qty, after_qty, live_session_id, remark)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$productId, $conditionType, 'return', 1, $beforeQty, $afterQty, $liveSessionId, '直播退还']);

    $pdo->commit();

    success([
        'data' => [
            'before_qty' => $beforeQty,
            'after_qty' => $afterQty,
            'message' => '退还成功'
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
