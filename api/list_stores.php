<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$pdo = getDB();
$stmt = $pdo->query('SELECT id, name, barcode_prefix, created_at FROM stores ORDER BY id');
$stores = $stmt->fetchAll();

success(['data' => ['stores' => $stores]]);
