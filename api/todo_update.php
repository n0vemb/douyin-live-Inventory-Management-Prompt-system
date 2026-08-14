<?php
/**
 * todo_update.php — 追加更新进展记录（不改状态）
 * POST { id, content, assignee_ids[] }
 * 服务端自动注入：updated_by（当前用户）、created_at（now）
 * 校验：该 todo 属于当前店铺；assignee 校验归属店铺
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id     = (int)($input['id'] ?? 0);
$content = trim($input['content'] ?? '');
$assigneeIds = $input['assignee_ids'] ?? [];

if ($id <= 0) {
    error('参数错误');
}
if ($content === '') {
    error('请填写更新说明');
}

requireAuth();
$storeId = getStoreId();
if ($storeId === null) {
    error('请先选择店铺再操作');
}

$pdo = getDB();

// 校验归属店铺
$stmt = $pdo->prepare("SELECT id FROM todo_items WHERE id = ? AND store_id = ?");
$stmt->execute([$id, $storeId]);
if (!$stmt->fetch()) {
    error('未找到该待办');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    error('登录状态异常');
}

// 校验执行人归属当前店铺，过滤非法 id
$validAssignees = [];
if (is_array($assigneeIds) && count($assigneeIds) > 0) {
    $ids = array_unique(array_map('intval', $assigneeIds));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE store_id = ? AND id IN ($ph)");
        $stmt->execute(array_merge([$storeId], $ids));
        $validAssignees = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

$assigneesJson = count($validAssignees) > 0 ? json_encode($validAssignees, JSON_UNESCAPED_UNICODE) : null;

$stmt = $pdo->prepare("INSERT INTO todo_updates (todo_id, content, assignees, updated_by) VALUES (?, ?, ?, ?)");
$stmt->execute([$id, $content, $assigneesJson, $userId]);

success(['id' => (int)$pdo->lastInsertId(), 'message' => '已记录']);
