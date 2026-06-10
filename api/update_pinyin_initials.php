<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? null;
$pinyinInitials = $input['pinyin_initials'] ?? '';

if (empty($productId)) {
    error('请提供商品ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$stmt = $pdo->prepare('UPDATE products SET pinyin_initials = ? WHERE id = ? AND store_id = ?');
$stmt->execute([$pinyinInitials, $productId, $storeId]);

if ($stmt->rowCount() > 0) {
    success(['message' => '更新成功']);
} else {
    // 可能 ID 不存在
    $check = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
    $check->execute([$productId, $storeId]);
    if (!$check->fetch()) {
        error('商品不存在');
    }
    // ID 存在但内容没变，也算成功
    success(['message' => '更新成功']);
}
