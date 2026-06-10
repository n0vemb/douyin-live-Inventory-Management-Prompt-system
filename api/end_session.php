<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? 0;

if (empty($sessionId)) {
    error('请提供场次ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$stmt = $pdo->prepare('UPDATE live_sessions SET status = ?, ended_at = NOW() WHERE id = ? AND store_id = ?');
$stmt->execute(['ended', $sessionId, $storeId]);

success(['message' => '直播已结束']);
