<?php
require_once __DIR__ . '/../config.php';

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

$stmt = $pdo->prepare('SELECT * FROM products WHERE barcode = ?');
$stmt->execute([$barcode]);
$product = $stmt->fetch();

if (!$product) {
    error('商品未找到，请先入库');
}

$stmt = $pdo->prepare('
    SELECT * FROM live_inventory 
    WHERE live_session_id = ? AND product_id = ?
');
$stmt->execute([$liveSessionId, $product['id']]);
$liveInventory = $stmt->fetchAll();

if (empty($liveInventory)) {
    error('该商品未在本场直播库存中');
}

$inventoryData = [];
$conditionMap = [];
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result && $result['setting_value']) {
        $conditionTypes = json_decode($result['setting_value'], true);
        if ($conditionTypes && is_array($conditionTypes)) {
            foreach ($conditionTypes as $ct) {
                $conditionMap[$ct['key']] = $ct['name'];
            }
        }
    }
} catch (Exception $e) {}

if (empty($conditionMap)) {
    $conditionMap = CONDITION_TYPES;
}

foreach ($liveInventory as $inv) {
    $conditionName = $conditionMap[$inv['condition_type']] ?? $inv['condition_type'];
    $inventoryData[$conditionName] = [
        'stock' => $inv['current_stock'],
        'initial_stock' => $inv['initial_stock'],
        'suggested_price' => $inv['suggested_price'],
        'live_price' => $inv['live_price']
    ];
    $inventoryData[$inv['condition_type']] = $inventoryData[$conditionName];
}

$result = [
    'id' => $product['id'],
    'name' => $product['name'],
    'common_name' => $product['common_name'] ?? null,
    'product_description' => $product['product_description'] ?? null,
    'series' => $product['series'],
    'barcode' => $product['barcode'],
    'qiandao_price' => $product['qiandao_price'],
    'image_url' => $product['image_url'],
    'inventory' => $inventoryData
];

success(['data' => $result]);
