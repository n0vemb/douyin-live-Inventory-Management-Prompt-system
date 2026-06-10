<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 加载状态配置
$conditionTypes = [
    ['key' => 'sealed', 'name' => '原盒未拆'],
    ['key' => 'opened', 'name' => '拆盒无瑕'],
    ['key' => 'boxless', 'name' => '无盒无瑕'],
    ['key' => 'flawed', 'name' => '微瑕'],
];
try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $result = $stmt->fetch();
        if ($result && $result['condition_types']) {
            $types = json_decode($result['condition_types'], true);
            if ($types && is_array($types)) {
                $conditionTypes = $types;
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && $result['setting_value']) {
            $types = json_decode($result['setting_value'], true);
            if ($types && is_array($types)) {
                $conditionTypes = $types;
            }
        }
    }
} catch (Exception $e) {}

// 查询所有有库存的商品
$productsSql = "
    SELECT p.id, p.name, p.common_name, p.barcode, p.series
    FROM products p
    WHERE EXISTS (SELECT 1 FROM inventory_batches ib WHERE ib.product_id = p.id AND ib.remaining_qty > 0";
if ($storeId) {
    $productsSql .= " AND ib.store_id = ?";
}
$productsSql .= ")
    ORDER BY p.name";
$stmt = $pdo->prepare($productsSql);
$stmt->execute($storeId ? [$storeId] : []);
$products = $stmt->fetchAll();

if (empty($products)) {
    success(['data' => ['products' => [], 'condition_types' => $conditionTypes]]);
}

// 查各商品各状态的库存汇总
$productIds = array_column($products, 'id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$stmt = $pdo->prepare("
    SELECT product_id, condition_type,
           SUM(remaining_qty) as total_qty,
           MIN(purchase_price) as purchase_price,
           MIN(suggested_price) as suggested_price
    FROM inventory_batches
    WHERE product_id IN ({$placeholders}) AND remaining_qty > 0" . ($storeId ? " AND store_id = ?" : "") . "
    GROUP BY product_id, condition_type
");
$params = $productIds;
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
$batchData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$invMap = [];
foreach ($batchData as $row) {
    $invMap[$row['product_id'] . '_' . $row['condition_type']] = $row;
}

$result = [];
foreach ($products as $p) {
    $conditions = [];
    foreach ($conditionTypes as $ct) {
        $key = $p['id'] . '_' . $ct['key'];
        if (isset($invMap[$key])) {
            $conditions[$ct['key']] = [
                'qty' => (int)$invMap[$key]['total_qty'],
                'purchase_price' => $invMap[$key]['purchase_price'] ? floatval($invMap[$key]['purchase_price']) : null,
                'suggested_price' => $invMap[$key]['suggested_price'] ? floatval($invMap[$key]['suggested_price']) : null,
            ];
        } else {
            $conditions[$ct['key']] = [
                'qty' => 0,
                'purchase_price' => null,
                'suggested_price' => null,
            ];
        }
    }
    $result[] = [
        'product_id' => $p['id'],
        'product_name' => $p['common_name'] ?: $p['name'],
        'official_name' => $p['name'],
        'barcode' => $p['barcode'],
        'series' => $p['series'],
        'conditions' => $conditions,
    ];
}

success(['data' => ['products' => $result, 'condition_types' => $conditionTypes]]);
