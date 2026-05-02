<?php
require_once __DIR__ . '/../config.php';

$productId = $_GET['product_id'] ?? 0;
$liveSessionId = $_GET['live_session_id'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$pdo = getDB();

$sql = 'SELECT s.*, p.name as product_name, p.barcode, p.series FROM sales_log s LEFT JOIN products p ON s.product_id = p.id WHERE 1=1';
$params = [];

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

$summarySql = 'SELECT SUM(sale_price * qty) as total_amount, SUM(qty) as total_qty FROM sales_log WHERE 1=1';
$summaryParams = [];
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

success(['data' => ['sales' => $sales, 'summary' => $summary]]);