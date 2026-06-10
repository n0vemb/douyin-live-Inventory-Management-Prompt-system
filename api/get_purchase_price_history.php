<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? 0;
$startDate = $input['start_date'] ?? '';
$endDate = $input['end_date'] ?? '';

if (empty($productId)) {
    error('请提供商品ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$sql = "
    SELECT purchase_price, qty, condition_type, purchased_at
    FROM purchase_log
    WHERE product_id = ? AND purchase_price > 0
";
$params = [$productId];
if ($storeId) {
    $sql .= " AND store_id = ?";
    $params[] = $storeId;
}

if ($startDate) {
    $sql .= " AND purchased_at >= ?";
    $params[] = $startDate . ' 00:00:00';
}
if ($endDate) {
    $sql .= " AND purchased_at <= ?";
    $params[] = $endDate . ' 23:59:59';
}

$sql .= " ORDER BY purchased_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 获取该商品所有进货记录的最早和最晚时间（用于日期筛选器默认范围）
$rangeSql = "SELECT MIN(purchased_at) AS min_date, MAX(purchased_at) AS max_date FROM purchase_log WHERE product_id = ? AND purchase_price > 0";
$rangeParams = [$productId];
if ($storeId) {
    $rangeSql .= " AND store_id = ?";
    $rangeParams[] = $storeId;
}
$rangeStmt = $pdo->prepare($rangeSql);
$rangeStmt->execute($rangeParams);
$dateRange = $rangeStmt->fetch();

success([
    'data' => [
        'records' => $records,
        'date_range' => $dateRange
    ]
]);
