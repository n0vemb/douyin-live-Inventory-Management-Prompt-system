<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 运营角色不可导出库存（导出含进价等成本数据）
requireNonOperator();

// 获取状态类型
$conditionTypes = [
    ['name' => '原盒未拆', 'key' => 'sealed'],
    ['name' => '拆盒无瑕', 'key' => 'opened'],
    ['name' => '无盒无瑕', 'key' => 'boxless'],
    ['name' => '微瑕', 'key' => 'flawed']
];
try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        if ($row && $row['condition_types']) {
            $types = json_decode($row['condition_types'], true);
            if ($types && is_array($types)) $conditionTypes = $types;
        }
    } else {
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $row = $stmt->fetch();
        if ($row && $row['setting_value']) {
            $types = json_decode($row['setting_value'], true);
            if ($types && is_array($types)) $conditionTypes = $types;
        }
    }
} catch (Exception $e) {}

// 只查有库存的商品
$productSql = "SELECT DISTINCT p.* FROM products p JOIN inventory_batches ib ON ib.product_id = p.id AND ib.remaining_qty > 0";
$productParams = [];
if ($storeId) {
    $productSql .= " WHERE p.store_id = ?";
    $productParams[] = $storeId;
}
$productSql .= " ORDER BY p.updated_at DESC";
$stmt = $pdo->prepare($productSql);
$stmt->execute($productParams);
$products = $stmt->fetchAll();

if (empty($products)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_export.csv"');
    echo '';
    exit;
}

$productIds = array_column($products, 'id');
$placeholders = implode(',', array_fill(0, count($productIds), '?'));

// 查询库存
$inventorySql = "SELECT product_id, condition_type,
    SUM(remaining_qty) as total_stock,
    MAX(purchase_price) as purchase_price,
    MAX(suggested_price) as suggested_price
    FROM inventory_batches
    WHERE product_id IN ({$placeholders})
    GROUP BY product_id, condition_type";
$stmt = $pdo->prepare($inventorySql);
$stmt->execute($productIds);
$inventoryRows = $stmt->fetchAll();

// 构建 product_id => condition_type => data 映射
$inventory = [];
foreach ($inventoryRows as $row) {
    $pid = $row['product_id'];
    if (!isset($inventory[$pid])) $inventory[$pid] = [];
    $inventory[$pid][$row['condition_type']] = $row;
}

// 构建表头
$headers = ['商品名称', '常用名称', '系列', '品牌', '条码', '参考价', '发售时间', '产品介绍', '图片链接'];
foreach ($conditionTypes as $ct) {
    $headers[] = $ct['name'] . '数量';
    $headers[] = $ct['name'] . '进价';
    $headers[] = $ct['name'] . '售价';
}
$headers[] = '供应商';
$headers[] = '备注';

// 生成 CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory_export_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

fputcsv($output, $headers, ',', '"', '\\');

foreach ($products as $p) {
    $row = [
        $p['name'] ?? '',
        $p['common_name'] ?? '',
        $p['series'] ?? '',
        $p['brand'] ?? '',
        $p['barcode'] ?? '',
        $p['qiandao_price'] ?? '',
        $p['release_date'] ?? '',
        $p['product_description'] ?? '',
        $p['image_url'] ?? ''
    ];

    $inv = $inventory[$p['id']] ?? [];
    foreach ($conditionTypes as $ct) {
        $data = $inv[$ct['key']] ?? null;
        $row[] = $data ? intval($data['total_stock']) : 0;         // 数量
        $row[] = $data ? floatval($data['purchase_price']) : '';   // 进价
        $row[] = $data ? floatval($data['suggested_price']) : '';  // 售价
    }

    $row[] = ''; // 供应商
    $row[] = ''; // 备注

    fputcsv($output, $row, ',', '"', '\\');
}

fclose($output);
exit;
