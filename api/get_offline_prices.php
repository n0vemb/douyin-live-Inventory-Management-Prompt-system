<?php
/**
 * get_offline_prices.php — 读取某商品所有品相的线下售价配置
 * GET ?product_id=xxx
 * 运营(operator)不返回线下售价（返回空配置）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) error('请先选择店铺后再操作');

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) error('缺少商品ID');

// 校验商品归属
$stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
$stmt->execute([$productId, $storeId]);
if (!$stmt->fetch()) error('商品不存在或不属于本店');

// 运营不可见线下售价
if (isOperator()) {
    success(['configured' => []]);
}

$stmt = $pdo->prepare('SELECT condition_type, offline_price FROM product_offline_prices WHERE product_id = ?');
$stmt->execute([$productId]);
$configured = [];
foreach ($stmt->fetchAll() as $row) {
    $configured[$row['condition_type']] = round(floatval($row['offline_price']), 2);
}
success(['configured' => $configured]);
