<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/condition_common.php';

$input = json_decode(file_get_contents('php://input'), true);
$keyword = trim($input['keyword'] ?? '');
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($keyword)) {
    error('请提供搜索关键词');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

// 存量数据用的是每个字拼音的首字母格式（失衡→sh），输入什么就搜什么

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 品相中文名：统一来源（店铺配置 → 全局配置 → 默认兜底）
$conditionMap = conditionNames($pdo, $storeId);
if (empty($conditionMap)) {
    $conditionMap = [
        'sealed' => '原盒未拆',
        'opened' => '拆盒无瑕',
        'boxless' => '无盒无瑕',
        'flawed' => '微瑕'
    ];
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
    WHERE live_session_id = ? AND product_id IN ({$placeholders})" . ($storeId ? " AND store_id = ?" : "") . "
");
$params = array_merge([$liveSessionId], $productIds);
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
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
    WHERE product_id IN ({$placeholders}) AND remaining_qty > 0 AND purchase_price > 0" . ($storeId ? " AND store_id = ?" : "") . "
    GROUP BY product_id, condition_type
");
$params = $productIds;
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
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
