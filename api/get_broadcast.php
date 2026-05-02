<?php
require_once __DIR__ . '/../config.php';

$sessionId = $_GET['session_id'] ?? 0;
$lastId = $_GET['last_id'] ?? 0;

if (empty($sessionId)) {
    error('请提供场次ID');
}

$pdo = getDB();

try {
    $stmt = $pdo->prepare('
        SELECT id, message, msg_type, created_at
        FROM broadcast_messages
        WHERE session_id = ? AND id > ?
        ORDER BY id DESC
        LIMIT 10
    ');
    $stmt->execute([$sessionId, $lastId]);
    $messages = $stmt->fetchAll();

    success(['data' => ['messages' => $messages]]);
} catch (Exception $e) {
    error($e->getMessage());
}
