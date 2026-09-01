<?php
// 修复 inventory_audit.php：价格从 MIN(最低价) 改为取最新批次的价格
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

// 查询本店全部商品（含无库存，盘点需覆盖"线上0但现场有货"的商品）
$productsSql = "SELECT p.id, p.name, p.common_name, p.barcode, p.series, p.brand FROM products p";
if ($storeId) {
    $productsSql .= " WHERE p.store_id = ?";
}
$productsSql .= " ORDER BY p.name";
$stmt = $pdo->prepare($productsSql);
$stmt->execute($storeId ? [$storeId] : []);
$products = $stmt->fetchAll();

if (empty($products)) {
    success(['data' => ['products' => [], 'condition_types' => $conditionTypes]]);
}

// 查各商品各状态的库存汇总
// 修复：价格不再用 MIN()（会取到历史最低价），而是关联每个商品-状态的最新批次取价格，
// 与 export_inventory.php 的展示保持一致（export 用 MAX，实际最新批次价格在 MAX 与 MIN 之间，
// 最准确的做法是直接取最新批次。这里用 LEFT JOIN 取最新批次的价格）。
$productIds = array_column($products, 'id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

$params = $productIds;
if ($storeId) $params[] = $storeId;

$stmt = $pdo->prepare("
    SELECT ib.product_id, ib.condition_type,
           SUM(ib.remaining_qty) as total_qty
    FROM inventory_batches ib
    WHERE ib.product_id IN ({$placeholders}) AND ib.remaining_qty > 0" . ($storeId ? " AND ib.store_id = ?" : "") . "
    GROUP BY ib.product_id, ib.condition_type
");
$stmt->execute($params);
$batchData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$invMap = [];
foreach ($batchData as $row) {
    $invMap[$row['product_id'] . '_' . $row['condition_type']] = $row;
}

// 查每个 商品-状态 的最新批次（用于取价格，与盘点保存逻辑 ORDER BY purchased_at DESC LIMIT 1 一致）
$latestBatchMap = [];
$stmt = $pdo->prepare("
    SELECT ib.product_id, ib.condition_type, ib.purchase_price, ib.suggested_price
    FROM inventory_batches ib
    WHERE ib.product_id IN ({$placeholders}) AND ib.remaining_qty > 0" . ($storeId ? " AND ib.store_id = ?" : "") . "
    ORDER BY ib.purchased_at DESC, ib.id DESC
");
$stmt->execute($params);
$allBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($allBatches as $b) {
    $key = $b['product_id'] . '_' . $b['condition_type'];
    if (!isset($latestBatchMap[$key])) {
        $latestBatchMap[$key] = $b;
    }
}

$result = [];
// 盘点界面价格（进价/售价）仅超管可见；店管/运营打开盘点不显示价格（2026-08-21 需求）
$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';
foreach ($products as $p) {
    $conditions = [];
    foreach ($conditionTypes as $ct) {
        $key = $p['id'] . '_' . $ct['key'];
        if (isset($invMap[$key])) {
            $latest = $latestBatchMap[$key] ?? null;
            $conditions[$ct['key']] = [
                'qty' => (int)$invMap[$key]['total_qty'],
                'purchase_price' => $isSuperAdmin && $latest ? floatval($latest['purchase_price']) : null,
                'suggested_price' => $isSuperAdmin && $latest ? floatval($latest['suggested_price']) : null,
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
        'brand' => $p['brand'],
        'conditions' => $conditions,
    ];
}

success(['data' => ['products' => $result, 'condition_types' => $conditionTypes]]);
