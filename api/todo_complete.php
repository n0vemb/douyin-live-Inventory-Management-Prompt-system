<?php
/**
 * todo_complete.php — 标记完成
 * POST { id, completion_detail }
 * 服务端自动注入：completed_by（当前用户）、completed_at（now）、status → done
 * 校验：该 todo 属于当前店铺
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id    = (int)($input['id'] ?? 0);
$detail = trim($input['completion_detail'] ?? '');

if ($id <= 0) {
    error('参数错误');
}
if ($detail === '') {
    error('请填写完成详情');
}

requireAuth();
$storeId = getStoreId();
if ($storeId === null) {
    error('请先选择店铺再操作');
}

$pdo = getDB();

// 校验归属店铺
$stmt = $pdo->prepare("SELECT id, status FROM todo_items WHERE id = ? AND store_id = ?");
$stmt->execute([$id, $storeId]);
$todo = $stmt->fetch();
if (!$todo) {
    error('未找到该待办');
}
if ($todo['status'] === 'done') {
    error('该待办已完成');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    error('登录状态异常');
}

$stmt = $pdo->prepare("UPDATE todo_items SET status = 'done', completed_by = ?, completion_detail = ?, completed_at = NOW() WHERE id = ? AND store_id = ?");
$stmt->execute([$userId, $detail, $id, $storeId]);

success(['message' => '已完成']);
