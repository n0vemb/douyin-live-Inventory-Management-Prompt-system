<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$sessionId = $input['session_id'] ?? 0;
$message = trim((string)($input['message'] ?? ''));
$type = $input['type'] ?? 'announcement';

if (empty($sessionId) || empty($message)) {
    error('请提供场次ID和消息内容');
}
if (mb_strlen($message, 'UTF-8') > 200) {
    error('消息内容过长（最多200字）');
}

$pdo = getDB();

try {
    $stmt = $pdo->prepare('
        INSERT INTO broadcast_messages (session_id, message, msg_type, created_at)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->execute([$sessionId, $message, $type]);

    $id = $pdo->lastInsertId();

    $pdo->query("DELETE FROM broadcast_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

    success(['data' => ['id' => $id, 'message' => $message]]);
} catch (Exception $e) {
    error($e->getMessage());
}
