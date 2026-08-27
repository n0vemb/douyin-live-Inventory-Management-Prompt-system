<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/condition_common.php';

$input = json_decode(file_get_contents('php://input'), true);
$barcode = trim($input['barcode'] ?? '');
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($barcode)) {
    error('请提供条码');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$stmt = $pdo->prepare('SELECT * FROM products WHERE barcode = ?' . ($storeId ? ' AND store_id = ?' : ''));
$stmt->execute($storeId ? [$barcode, $storeId] : [$barcode]);
$product = $stmt->fetch();

if (!$product) {
    error('商品未找到，请先入库');
}

$stmt = $pdo->prepare('
    SELECT * FROM live_inventory
    WHERE live_session_id = ? AND product_id = ? AND store_id = ?
');
$stmt->execute([$liveSessionId, $product['id'], $storeId]);
$liveInventory = $stmt->fetchAll();

if (empty($liveInventory)) {
    error('该商品未在本场直播库存中');
}

$inventoryData = [];
$purchasePriceData = [];
// 品相中文名：统一来源（店铺配置 → 全局配置 → 默认兜底）
$conditionMap = conditionNames($pdo, $storeId);

// 查询各状态的进货价（去重）
try {
    $stmt = $pdo->prepare('
        SELECT condition_type, GROUP_CONCAT(DISTINCT purchase_price ORDER BY purchase_price SEPARATOR "/") as prices
        FROM inventory_batches
        WHERE product_id = ? AND remaining_qty - locked_qty > 0 AND purchase_price > 0 AND store_id = ?
        GROUP BY condition_type
    ');
    $stmt->execute([$product['id'], $storeId]);
    $purchaseRows = $stmt->fetchAll();
    foreach ($purchaseRows as $pr) {
        $conditionName = $conditionMap[$pr['condition_type']] ?? $pr['condition_type'];
        $purchasePriceData[$conditionName] = $pr['prices'];
        $purchasePriceData[$pr['condition_type']] = $pr['prices'];
    }
} catch (Exception $e) {}

foreach ($liveInventory as $inv) {
    $conditionName = $conditionMap[$inv['condition_type']] ?? $inv['condition_type'];
    $inventoryData[$conditionName] = [
        'stock' => $inv['current_stock'],
        'initial_stock' => $inv['initial_stock'],
        'suggested_price' => $inv['suggested_price'],
        'live_price' => $inv['live_price'],
        'purchase_price' => $purchasePriceData[$inv['condition_type']] ?? null
    ];
    $inventoryData[$inv['condition_type']] = $inventoryData[$conditionName];
}

// 聚合所有状态的去重进价
$allPrices = [];
foreach ($purchasePriceData as $pricesStr) {
    foreach (explode('/', $pricesStr) as $p) {
        $fp = floatval($p);
        if ($fp > 0) $allPrices[(string)$fp] = $fp;
    }
}
ksort($allPrices);
$overallPurchasePrices = !empty($allPrices) ? implode('/', array_map(function($v) {
    return number_format($v, 2, '.', '');
}, $allPrices)) : null;

$result = [
    'id' => $product['id'],
    'name' => $product['name'],
    'common_name' => $product['common_name'] ?? null,
    'product_description' => $product['product_description'] ?? null,
    'series' => $product['series'],
    'barcode' => $product['barcode'],
    'qiandao_price' => $product['qiandao_price'],
    'image_url' => $product['image_url'],
    'purchase_prices' => $overallPurchasePrices,
    'inventory' => $inventoryData
];

success(['data' => $result]);
