<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM live_sessions WHERE status = 'active' ORDER BY started_at DESC LIMIT 1");
$stmt->execute();
$session = $stmt->fetch();

if ($session) {
    success(['data' => $session]);
} else {
    success(['data' => null]);
}