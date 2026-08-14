<?php
/**
 * todo_update_edit.php — 编辑更新记录（仅更新人本人 + 关联待办待完成）
 * POST { id, content, assignee_ids[] }
 * 校验：更新记录属于当前店铺（join todo_items）；updated_by = 当前用户；关联待办 pending
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id       = (int)($input['id'] ?? 0);
$content  = trim($input['content'] ?? '');
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

// 校验更新记录归属店铺 + 关联待办状态 + 更新人
$stmt = $pdo->prepare("SELECT u.id, u.updated_by, t.status AS todo_status
                       FROM todo_updates u
                       JOIN todo_items t ON t.id = u.todo_id
                       WHERE u.id = ? AND t.store_id = ?");
$stmt->execute([$id, $storeId]);
$upd = $stmt->fetch();
if (!$upd) {
    error('未找到该更新记录');
}
if ($upd['todo_status'] === 'done') {
    error('已完成待办的记录不可修改');
}
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    error('登录状态异常');
}
if ((int)$upd['updated_by'] !== $userId) {
    error('仅更新人本人可修改该记录');
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

$stmt = $pdo->prepare("UPDATE todo_updates SET content = ?, assignees = ? WHERE id = ?");
$stmt->execute([$content, $assigneesJson, $id]);

success(['message' => '已保存']);
