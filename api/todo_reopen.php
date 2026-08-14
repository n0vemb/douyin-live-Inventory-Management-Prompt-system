<?php
/**
 * todo_reopen.php — 重新打开（仅已完成项）
 * POST { id }
 * status → pending；清空 completed_by / completed_at / completion_detail
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id    = (int)($input['id'] ?? 0);

if ($id <= 0) {
    error('参数错误');
}

requireAuth();
$storeId = getStoreId();
if ($storeId === null) {
    error('请先选择店铺再操作');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id, status FROM todo_items WHERE id = ? AND store_id = ?");
$stmt->execute([$id, $storeId]);
$todo = $stmt->fetch();
if (!$todo) {
    error('未找到该待办');
}
if ($todo['status'] !== 'done') {
    error('仅已完成项可重新打开');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    error('登录状态异常');
}

$stmt = $pdo->prepare("UPDATE todo_items SET status = 'pending', completed_by = NULL, completion_detail = NULL, completed_at = NULL WHERE id = ? AND store_id = ?");
$stmt->execute([$id, $storeId]);

// 重新打开时写入一条更新记录（系统记录）
$stmt = $pdo->prepare("INSERT INTO todo_updates (todo_id, content, updated_by) VALUES (?, ?, ?)");
$stmt->execute([$id, '重新打开', $userId]);

success(['message' => '已重新打开']);
