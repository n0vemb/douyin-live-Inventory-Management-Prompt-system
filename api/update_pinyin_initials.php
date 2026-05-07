<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? null;
$pinyinInitials = $input['pinyin_initials'] ?? '';

if (empty($productId)) {
    error('请提供商品ID');
}

$pdo = getDB();
$stmt = $pdo->prepare('UPDATE products SET pinyin_initials = ? WHERE id = ?');
$stmt->execute([$pinyinInitials, $productId]);

if ($stmt->rowCount() > 0) {
    success(['message' => '更新成功']);
} else {
    // 可能 ID 不存在
    $check = $pdo->prepare('SELECT id FROM products WHERE id = ?');
    $check->execute([$productId]);
    if (!$check->fetch()) {
        error('商品不存在');
    }
    // ID 存在但内容没变，也算成功
    success(['message' => '更新成功']);
}
