<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? 0;

if (empty($sessionId)) {
    error('请提供场次ID');
}

$pdo = getDB();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare('SELECT status FROM live_sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new Exception('场次不存在');
    }

    if ($session['status'] === 'active') {
        throw new Exception('无法删除进行中的场次，请先结束直播');
    }

    $stmt = $pdo->prepare('DELETE FROM live_inventory WHERE live_session_id = ?');
    $stmt->execute([$sessionId]);

    $stmt = $pdo->prepare('DELETE FROM sales_log WHERE live_session_id = ?');
    $stmt->execute([$sessionId]);

    $stmt = $pdo->prepare('DELETE FROM inventory_log WHERE live_session_id = ?');
    $stmt->execute([$sessionId]);

    $stmt = $pdo->prepare('DELETE FROM broadcast_messages WHERE session_id = ?');
    $stmt->execute([$sessionId]);

    $stmt = $pdo->prepare('DELETE FROM live_sessions WHERE id = ?');
    $stmt->execute([$sessionId]);

    $pdo->commit();
    success(['message' => '删除成功']);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
