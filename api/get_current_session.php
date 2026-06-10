<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$stmt = $pdo->prepare("SELECT * FROM live_sessions WHERE status = 'active'" . ($storeId ? " AND store_id = ?" : "") . " ORDER BY started_at DESC LIMIT 1");
$stmt->execute($storeId ? [$storeId] : []);
$session = $stmt->fetch();

if ($session) {
    success(['data' => $session]);
} else {
    success(['data' => null]);
}