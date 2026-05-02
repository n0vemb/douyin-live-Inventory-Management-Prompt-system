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
    // 删除没有外键约束的日志表数据
    $stmt = $pdo->prepare('DELETE FROM purchase_log WHERE product_id = ?');
    $stmt->execute([$productId]);

    $stmt = $pdo->prepare('DELETE FROM inventory_log WHERE product_id = ?');
    $stmt->execute([$productId]);

    // 删除库存批次（会通过外键约束自动删除 live_inventory 和 outbound_log 相关数据）
    $stmt = $pdo->prepare('DELETE FROM inventory_batches WHERE product_id = ?');
    $stmt->execute([$productId]);

    // 删除商品（会通过外键约束自动删除 sales_log 相关数据）
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([$productId]);

    $pdo->commit();
    success();

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}