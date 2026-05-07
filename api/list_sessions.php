<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

$stmt = $pdo->query('
    SELECT
        ls.*,
        ls.session_name AS name,
        (SELECT SUM(sale_price * (qty - returned_qty)) FROM sales_log WHERE live_session_id = ls.id) AS total_sales
    FROM live_sessions ls
    ORDER BY ls.started_at DESC
    LIMIT 50
');
$sessions = $stmt->fetchAll();

success(['data' => $sessions]);