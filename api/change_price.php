<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? 0;
$conditionType = $input['condition_type'] ?? '';
$newPrice = $input['new_price'] ?? 0;
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($productId) || empty($conditionType)) {
    error('请提供商品ID和状态类型');
}

if ($newPrice <= 0) {
    error('请提供有效价格');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM live_inventory WHERE live_session_id = ? AND product_id = ? AND condition_type = ?');
$stmt->execute([$liveSessionId, $productId, $conditionType]);
$inventory = $stmt->fetch();

if (!$inventory) {
    error('库存记录不存在');
}

$stmt = $pdo->prepare('UPDATE live_inventory SET live_price = ? WHERE id = ?');
$stmt->execute([$newPrice, $inventory['id']]);

success(['data' => ['new_price' => $newPrice]]);