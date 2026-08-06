<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$productId = $_GET['product_id'] ?? 0;
$liveSessionId = $_GET['live_session_id'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$canSeeProfit = !isOperator();

$sql = 'SELECT s.*,
            (s.qty - s.returned_qty) as qty,
            p.name as product_name, p.barcode, p.series,
            (SELECT MIN(ib.purchase_price) FROM inventory_batches ib
             WHERE ib.product_id = s.product_id AND ib.condition_type = s.condition_type
             AND ib.remaining_qty > 0 AND ib.purchase_price > 0 AND ib.store_id = s.store_id LIMIT 1) as batch_purchase_price
        FROM sales_log s
        LEFT JOIN products p ON s.product_id = p.id
        WHERE s.qty > s.returned_qty';
$params = [];

if ($storeId) {
    $sql .= ' AND s.store_id = ?';
    $params[] = $storeId;
}

if (!empty($productId)) {
    $sql .= ' AND s.product_id = ?';
    $params[] = $productId;
}

if (!empty($liveSessionId)) {
    $sql .= ' AND s.live_session_id = ?';
    $params[] = $liveSessionId;
}

if (!empty($startDate)) {
    $sql .= ' AND s.sold_at >= ?';
    $params[] = $startDate . ' 00:00:00';
}

if (!empty($endDate)) {
    $sql .= ' AND s.sold_at <= ?';
    $params[] = $endDate . ' 23:59:59';
}

$sql .= ' ORDER BY s.sold_at DESC LIMIT ?';

$stmt = $pdo->prepare($sql);
$paramIndex = 1;
foreach ($params as $value) {
    $stmt->bindValue($paramIndex++, $value);
}
$stmt->bindValue($paramIndex, max(1, $limit), PDO::PARAM_INT);
$stmt->execute();
$sales = $stmt->fetchAll();

$summarySql = 'SELECT SUM(s.sale_price * (s.qty - s.returned_qty)) as total_amount,
            SUM(s.qty - s.returned_qty) as total_qty,
            SUM((s.sale_price - COALESCE((SELECT MIN(ib.purchase_price) FROM inventory_batches ib WHERE ib.product_id = s.product_id AND ib.condition_type = s.condition_type AND ib.remaining_qty > 0 AND ib.purchase_price > 0 AND ib.store_id = s.store_id LIMIT 1), 0)) * (s.qty - s.returned_qty)) as total_profit
        FROM sales_log s
        WHERE 1=1';
$summaryParams = [];
if ($storeId) {
    $summarySql .= ' AND s.store_id = ?';
    $summaryParams[] = $storeId;
}
if (!empty($productId)) {
    $summarySql .= ' AND product_id = ?';
    $summaryParams[] = $productId;
}
if (!empty($liveSessionId)) {
    $summarySql .= ' AND live_session_id = ?';
    $summaryParams[] = $liveSessionId;
}
if (!empty($startDate)) {
    $summarySql .= ' AND sold_at >= ?';
    $summaryParams[] = $startDate . ' 00:00:00';
}
if (!empty($endDate)) {
    $summarySql .= ' AND sold_at <= ?';
    $summaryParams[] = $endDate . ' 23:59:59';
}
$stmt = $pdo->prepare($summarySql);
$stmt->execute($summaryParams);
$summary = $stmt->fetch();

// 运营不可见进价/盈利
if (!$canSeeProfit) {
    foreach ($sales as &$s) {
        $s['batch_purchase_price'] = null;
    }
    unset($s);
    if (isset($summary['total_profit'])) {
        $summary['total_profit'] = null;
    }
}

success(['data' => ['sales' => $sales, 'summary' => $summary]]);