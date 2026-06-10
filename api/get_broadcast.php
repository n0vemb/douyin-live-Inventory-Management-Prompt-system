<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$sessionId = $_GET['session_id'] ?? 0;
$lastId = $_GET['last_id'] ?? 0;

if (empty($sessionId)) {
    error('请提供场次ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

try {
    $stmt = $pdo->prepare('
        SELECT id, message, msg_type, created_at
        FROM broadcast_messages
        WHERE session_id = ? AND id > ?' . ($storeId ? ' AND store_id = ?' : '') . '
        ORDER BY id DESC
        LIMIT 10
    ');
    $stmt->execute($storeId ? [$sessionId, $lastId, $storeId] : [$sessionId, $lastId]);
    $messages = $stmt->fetchAll();

    success(['data' => ['messages' => $messages]]);
} catch (Exception $e) {
    error($e->getMessage());
}
