<?php
/**
 * 加入待出库清单
 * POST: { product_id, condition_type, qty, price }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    jsonResponse(['success' => false, 'error' => '请使用POST方法']);
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = intval($input['product_id'] ?? 0);
$conditionType = $input['condition_type'] ?? '';
$qty = intval($input['qty'] ?? 1);
$price = $input['price'] ?? null;

if (!$productId || !$conditionType || $qty <= 0) {
    error('参数不完整');
}

$pdo = getDB();
requireAuth();
$storeId = getStoreId();

// 检查库存是否够
$stmt = $pdo->prepare('SELECT SUM(remaining_qty) as stock FROM inventory_batches WHERE product_id = ? AND condition_type = ?' . ($storeId ? ' AND store_id = ?' : ''));
$params = [$productId, $conditionType];
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
$row = $stmt->fetch();
$available = intval($row['stock'] ?? 0);

// 已占用的待出库
$stmt = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) as reserved FROM pending_outbound WHERE product_id = ? AND condition_type = ?' . ($storeId ? ' AND store_id = ?' : ''));
$params = [$productId, $conditionType];
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
$reserved = intval($stmt->fetch()['reserved'] ?? 0);

$remain = $available - $reserved;
if ($qty > $remain) {
    error("库存不足：需要 {$qty} 件，可用 {$remain} 件（库存 {$available} - 已占用 {$reserved}）");
}

// 如果已存在同 product+condition 的记录，合并数量
$stmt = $pdo->prepare("SELECT id, qty FROM pending_outbound WHERE product_id = ? AND condition_type = ?" . ($storeId ? " AND store_id = ?" : ""));
$params = [$productId, $conditionType];
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
$existing = $stmt->fetch();

if ($existing) {
    $newQty = intval($existing['qty']) + $qty;
    $stmt = $pdo->prepare('UPDATE pending_outbound SET qty = ?, price = COALESCE(?, price) WHERE id = ?');
    $stmt->execute([$newQty, $price, $existing['id']]);
} else {
    $stmt = $pdo->prepare('INSERT INTO pending_outbound (store_id, product_id, condition_type, qty, price) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$storeId ?: 1, $productId, $conditionType, $qty, $price]);
}

// 获取商品名
$stmt = $pdo->prepare('SELECT common_name, name FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

success([
    'message' => "已添加到待出库：{$product['common_name']} × {$qty}",
    'data' => [
        'product_name' => $product['common_name'] ?: $product['name'],
        'qty' => $qty,
        'condition_type' => $conditionType
    ]
]);
