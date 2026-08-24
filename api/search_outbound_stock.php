<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$keyword = $_GET['keyword'] ?? '';

if (empty($keyword)) {
    error('请提供搜索关键词');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 条件类型映射
$conditionNames = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
];
try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $result = $stmt->fetch();
        if ($result && $result['condition_types']) {
            $types = json_decode($result['condition_types'], true);
            if ($types && is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) {
                    $conditionNames[$t['key']] = $t['name'];
                }
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && $result['setting_value']) {
            $types = json_decode($result['setting_value'], true);
            if ($types && is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) {
                    $conditionNames[$t['key']] = $t['name'];
                }
            }
        }
    }
} catch (Exception $e) {}

// 搜索商品（匹配拼音首字母或名称本身）
$stmt = $pdo->prepare('
    SELECT DISTINCT p.id as product_id, p.name as product_name, p.common_name, p.series, p.barcode, p.pinyin_initials
    FROM products p
    WHERE (p.pinyin_initials LIKE ? OR p.name LIKE ?)' . ($storeId ? ' AND p.store_id = ?' : '') . '
    ORDER BY
        CASE WHEN p.pinyin_initials = ? THEN 0
             WHEN p.pinyin_initials LIKE ? THEN 1
             WHEN p.name LIKE ? THEN 2
             ELSE 3 END,
        p.id DESC
    LIMIT 10
');
$likeKeyword = "%{$keyword}%";
$params = [$likeKeyword, $likeKeyword];
if ($storeId) $params[] = $storeId;
$params = array_merge($params, [$keyword, $likeKeyword, $likeKeyword]);
$stmt->execute($params);
$products = $stmt->fetchAll();

if (empty($products)) {
    success(['data' => []]);
}

// 查这些商品的所有有库存批次
$productIds = array_column($products, 'product_id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$stmt = $pdo->prepare("
    SELECT
        p.id as product_id,
        p.name as product_name,
        p.common_name,
        p.series,
        p.barcode,
        ib.id as batch_id,
        ib.condition_type,
        ib.batch_no,
        ib.purchase_price,
        ib.suggested_price,
        ib.remaining_qty,
        ib.locked_qty,
        (ib.remaining_qty - ib.locked_qty) AS available_qty,
        ib.purchased_at
    FROM inventory_batches ib
    JOIN products p ON ib.product_id = p.id
    WHERE p.id IN ({$placeholders}) AND ib.remaining_qty - ib.locked_qty > 0" . ($storeId ? " AND ib.store_id = ?" : "") . "
    ORDER BY p.id, ib.condition_type, ib.purchased_at ASC
");
$params = $productIds;
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($batches as &$batch) {
    $batch['condition_name'] = $conditionNames[$batch['condition_type']] ?? $batch['condition_type'];
}

success(['data' => $batches]);
