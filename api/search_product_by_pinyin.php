<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$rawKeyword = trim($input['keyword'] ?? '');
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($rawKeyword)) {
    error('请提供搜索关键词');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

// 将 zh/ch/sh 简化为 z/c/s，方便拼音不好时也能搜到
$keyword = str_replace(['zh', 'ch', 'sh'], ['z', 'c', 's'], $rawKeyword);

$pdo = getDB();

// 条件类型映射
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

// 搜索商品（匹配拼音首字母或名称本身），限定在本场直播库存中
$stmt = $pdo->prepare('
    SELECT DISTINCT p.id, p.name, p.common_name, p.series, p.barcode, p.qiandao_price, p.image_url, p.product_description, p.pinyin_initials
    FROM products p
    INNER JOIN live_inventory li ON li.product_id = p.id AND li.live_session_id = ?
    WHERE p.pinyin_initials LIKE ? OR p.name LIKE ?
    ORDER BY
        CASE WHEN p.pinyin_initials = ? THEN 0
             WHEN p.pinyin_initials LIKE ? THEN 1
             WHEN p.name LIKE ? THEN 2
             ELSE 3 END,
        p.id DESC
    LIMIT 20
');
$likeKeyword = "%{$keyword}%";
$stmt->execute([$liveSessionId, $likeKeyword, $likeKeyword, $keyword, $likeKeyword, $likeKeyword]);
$products = $stmt->fetchAll();

if (empty($products)) {
    success(['data' => [], 'message' => '未找到匹配商品']);
}

$productIds = array_column($products, 'id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

// 查直播库存详情
$stmt = $pdo->prepare("
    SELECT * FROM live_inventory
    WHERE live_session_id = ? AND product_id IN ({$placeholders})
");
$stmt->execute(array_merge([$liveSessionId], $productIds));
$liveInventoryRows = $stmt->fetchAll();

// 按 product_id 分组
$liveByProduct = [];
foreach ($liveInventoryRows as $row) {
    $liveByProduct[$row['product_id']][] = $row;
}

// 查各状态的进货价
$stmt = $pdo->prepare("
    SELECT product_id, condition_type, GROUP_CONCAT(DISTINCT purchase_price ORDER BY purchase_price SEPARATOR '/') as prices
    FROM inventory_batches
    WHERE product_id IN ({$placeholders}) AND remaining_qty > 0 AND purchase_price > 0
    GROUP BY product_id, condition_type
");
$stmt->execute($productIds);
$purchaseRows = $stmt->fetchAll();
$purchaseByProduct = [];
foreach ($purchaseRows as $pr) {
    $purchaseByProduct[$pr['product_id']][$pr['condition_type']] = $pr['prices'];
}

$result = [];
foreach ($products as $product) {
    $inventoryData = [];
    if (isset($liveByProduct[$product['id']])) {
        foreach ($liveByProduct[$product['id']] as $inv) {
            $conditionName = $conditionMap[$inv['condition_type']] ?? $inv['condition_type'];
            $inventoryData[$conditionName] = [
                'stock' => $inv['current_stock'],
                'initial_stock' => $inv['initial_stock'],
                'suggested_price' => $inv['suggested_price'],
                'live_price' => $inv['live_price'],
                'purchase_price' => $purchaseByProduct[$product['id']][$inv['condition_type']] ?? null
            ];
        }
    }

    $result[] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'common_name' => $product['common_name'],
        'series' => $product['series'],
        'barcode' => $product['barcode'],
        'qiandao_price' => $product['qiandao_price'],
        'image_url' => $product['image_url'],
        'product_description' => $product['product_description'],
        'pinyin_initials' => $product['pinyin_initials'],
        'inventory' => $inventoryData
    ];
}

success(['data' => $result]);
