<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();

$stmt = $pdo->prepare('
    SELECT p.id, p.product_id, p.condition_type, p.qty, p.price, p.created_at,
           pr.common_name, pr.name as product_name
    FROM pending_outbound p
    LEFT JOIN products pr ON p.product_id = pr.id
    WHERE 1=1' . ($storeId ? ' AND p.store_id = ?' : '') . '
    ORDER BY p.created_at ASC
');
$stmt->execute($storeId ? [$storeId] : []);
$items = $stmt->fetchAll();

success(['data' => ['items' => $items]]);
