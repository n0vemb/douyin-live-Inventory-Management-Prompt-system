<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? 0;

if (empty($productId)) {
    error('请提供商品ID');
}

$pdo = getDB();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('DELETE FROM sales_log WHERE product_id = ?');
    $stmt->execute([$productId]);

    $stmt = $pdo->prepare('DELETE FROM purchase_log WHERE product_id = ?');
    $stmt->execute([$productId]);

    $stmt = $pdo->prepare('DELETE FROM inventory_log WHERE product_id = ?');
    $stmt->execute([$productId]);

    $stmt = $pdo->prepare('DELETE FROM inventory WHERE product_id = ?');
    $stmt->execute([$productId]);

    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$productId]);

    $pdo->commit();
    success();

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}