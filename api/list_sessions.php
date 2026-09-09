<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$shopId = getShopId();

$stmt = $pdo->prepare('
    SELECT
        ls.*,
        ls.session_name AS name,
        (SELECT SUM(sale_price * (qty - returned_qty)) FROM sales_log WHERE live_session_id = ls.id) AS total_sales
    FROM live_sessions ls
    WHERE 1=1' . ($storeId ? ' AND ls.store_id = ?' : '') . ($shopId ? ' AND ls.shop_id = ?' : '') . '
    ORDER BY ls.started_at DESC
    LIMIT 50
');
$params = [];
if ($storeId) $params[] = $storeId;
if ($shopId) $params[] = $shopId;
$stmt->execute($params);
$sessions = $stmt->fetchAll();

success(['data' => $sessions]);
