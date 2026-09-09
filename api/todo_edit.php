<?php
/**
 * todo_edit.php — 编辑待办（仅发起人本人 + 待完成状态）
 * POST { id, content, priority, assignee_ids[] }
 * 校验：属于当前店铺；status = pending；creator_id = 当前用户
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id       = (int)($input['id'] ?? 0);
$content  = trim($input['content'] ?? '');
$priority = $input['priority'] ?? 'normal';
$assigneeIds = $input['assignee_ids'] ?? [];

if ($id <= 0) {
    error('参数错误');
}
if ($content === '') {
    error('请填写事项内容');
}
if (!in_array($priority, ['normal', 'urgent'], true)) {
    $priority = 'normal';
}

requireAuth();
$storeId = getStoreId();
if ($storeId === null) {
    error('请先选择店铺再操作');
}
$shopId = getShopId();
if ($shopId === null) {
    error('待办归属具体店铺：集团管理员只能跨店查看，不能编辑');
}

$pdo = getDB();

// 校验归属店铺 + 状态 + 发起人
$stmt = $pdo->prepare("SELECT id, status, creator_id FROM todo_items WHERE id = ? AND store_id = ? AND shop_id = ?");
$stmt->execute([$id, $storeId, $shopId]);
$todo = $stmt->fetch();
if (!$todo) {
    error('未找到该待办');
}
if ($todo['status'] === 'done') {
    error('已完成待办不可修改');
}
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    error('登录状态异常');
}
if ((int)$todo['creator_id'] !== $userId) {
    error('仅发起人可编辑该待办');
}

// 校验执行人归属当前店铺，过滤非法 id
$validAssignees = [];
if (is_array($assigneeIds) && count($assigneeIds) > 0) {
    $ids = array_unique(array_map('intval', $assigneeIds));
    if (count($ids) > 0) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE store_id = ? AND shop_id = ? AND id IN ($ph)");
        $stmt->execute(array_merge([$storeId, $shopId], $ids));
        $validAssignees = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

$assigneesJson = count($validAssignees) > 0 ? json_encode($validAssignees, JSON_UNESCAPED_UNICODE) : null;

$stmt = $pdo->prepare("UPDATE todo_items SET content = ?, priority = ?, assignees = ? WHERE id = ? AND store_id = ? AND shop_id = ?");
$stmt->execute([$content, $priority, $assigneesJson, $id, $storeId, $shopId]);

success(['message' => '已保存']);
