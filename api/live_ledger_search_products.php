<?php
/**
 * live_ledger_search_products.php — 商品搜索（条码/名称/拼音）
 * GET ?q=关键词
 * 返回有库存的匹配商品，带最新批次进价/售价（运营隐藏进价）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/permission_helper.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$q = trim($_GET['q'] ?? '');
if ($q === '') error('请输入搜索关键词');
$limit = isset($_GET['limit']) ? min(50, (int)$_GET['limit']) : 20;

// 搜索：名称 / 常用名 / 条码 / 拼音首字母
$sql = "SELECT DISTINCT p.id, p.name, p.common_name, p.barcode, p.series,
            (SELECT ib.purchase_price FROM inventory_batches ib
             WHERE ib.product_id = p.id AND ib.remaining_qty > 0 AND ib.purchase_price > 0
             ORDER BY ib.purchased_at DESC, ib.id DESC LIMIT 1) as latest_cost,
            (SELECT ib.suggested_price FROM inventory_batches ib
             WHERE ib.product_id = p.id AND ib.remaining_qty > 0 AND ib.suggested_price > 0
             ORDER BY ib.purchased_at DESC, ib.id DESC LIMIT 1) as latest_price
        FROM products p
        WHERE 1=1";
$params = [];
if ($storeId) {
    $sql .= " AND p.store_id = ?";
    $params[] = $storeId;
}
$sql .= " AND (p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ? OR p.pinyin_initials LIKE ?)";
$like = '%' . $q . '%';
$params = array_merge($params, [$like, $like, $like, $like]);
$sql .= " ORDER BY p.name LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as &$p) {
    $p['purchase_price'] = $p['latest_cost'] !== null ? floatval($p['latest_cost']) : null;
    $p['suggested_price'] = $p['latest_price'] !== null ? floatval($p['latest_price']) : null;
    unset($p['latest_cost'], $p['latest_price']);
}

// 运营角色：隐藏进价
if (shouldMaskProfit()) {
    foreach ($products as &$p) {
        $p['purchase_price'] = null;
    }
}

success(['data' => ['products' => $products]]);
