<?php
/**
 * save_offline_price.php — 保存商品品相(SKU)线下售价
 * POST { product_id, condition_type, offline_price }（offline_price 空 = 删除配置，恢复自动）
 * 仅店长/超管可用；运营(operator)拒绝
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) error('请先选择店铺后再操作');

// 运营不可见/不可改线下售价
if (isOperator()) error('运营角色无权设置线下售价');

$input = json_decode(file_get_contents('php://input'), true);
$productId = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$conditionType = trim($input['condition_type'] ?? '');
$offlinePrice = $input['offline_price'] ?? null;

if ($productId <= 0 || $conditionType === '') error('参数不完整');

// 校验商品归属本店
$stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
$stmt->execute([$productId, $storeId]);
if (!$stmt->fetch()) error('商品不存在或不属于本店');

// 校验品相是本店配置之一
$condStmt = $pdo->prepare('SELECT condition_types FROM stores WHERE id = ?');
$condStmt->execute([$storeId]);
$condRow = $condStmt->fetch();
$valid = false;
if ($condRow && $condRow['condition_types']) {
    $types = json_decode($condRow['condition_types'], true);
    if (is_array($types)) {
        foreach ($types as $t) {
            if (($t['key'] ?? '') === $conditionType) { $valid = true; break; }
        }
    }
}
if (!$valid) error('品相不存在于本店配置');

// offline_price 为空或 <=0 → 删除配置（恢复自动 进价×比例）
if ($offlinePrice === null || $offlinePrice === '' || floatval($offlinePrice) <= 0) {
    $del = $pdo->prepare('DELETE FROM product_offline_prices WHERE product_id = ? AND condition_type = ?');
    $del->execute([$productId, $conditionType]);
    success(['message' => '已恢复自动定价', 'configured' => false]);
}

$price = round(floatval($offlinePrice), 2);
if ($price <= 0) error('售价无效');

$upsert = $pdo->prepare(
    'INSERT INTO product_offline_prices (product_id, condition_type, offline_price) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE offline_price = VALUES(offline_price)'
);
$upsert->execute([$productId, $conditionType, $price]);
success(['message' => '线下售价已保存', 'configured' => true, 'offline_price' => $price]);
