<?php
/**
 * todo_add.php — 新增待办
 * POST { content, priority, assignee_ids[] }
 * 服务端自动注入：store_id（当前店铺，超管需先选店）、creator_id、created_at
 * assignees：前端 @ 选人得到的 user_id 数组，校验归属当前店铺
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$content  = trim($input['content'] ?? '');
$priority = $input['priority'] ?? 'normal';
$assigneeIds = $input['assignee_ids'] ?? [];

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

$pdo = getDB();

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

$creatorId = (int)($_SESSION['user_id'] ?? 0);
if ($creatorId <= 0) {
    error('登录状态异常');
}

$assigneesJson = count($validAssignees) > 0 ? json_encode($validAssignees, JSON_UNESCAPED_UNICODE) : null;

$stmt = $pdo->prepare("INSERT INTO todo_items (store_id, content, priority, creator_id, assignees) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$storeId, $content, $priority, $creatorId, $assigneesJson]);

success(['id' => (int)$pdo->lastInsertId(), 'message' => '已添加']);
