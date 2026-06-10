<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$userId = (int)($input['user_id'] ?? 0);

if ($userId <= 0) {
    error('请提供用户ID');
}

// 不允许禁用/删除自己
$currentUser = getCurrentUser();
if ($userId === (int)$currentUser['id']) {
    error('不能对自己的账号执行此操作');
}

$pdo = getDB();

// 检查用户是否存在
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$userId]);
if (!$stmt->fetch()) {
    error('用户不存在');
}

$updates = [];
$params = [];

// 更新显示名
if (isset($input['display_name'])) {
    $updates[] = 'display_name = ?';
    $params[] = trim($input['display_name']) ?: null;
}

// 更新角色
if (isset($input['role'])) {
    if (!in_array($input['role'], ['super_admin', 'store_admin'])) {
        error('无效的角色');
    }
    $updates[] = 'role = ?';
    $params[] = $input['role'];
}

// 更新店铺
if (array_key_exists('store_id', $input)) {
    $storeId = $input['store_id'] ? (int)$input['store_id'] : null;
    $updates[] = 'store_id = ?';
    $params[] = $storeId;
}

// 更新用户名
if (isset($input['username'])) {
    $username = trim($input['username']);
    if (empty($username)) {
        error('用户名不能为空');
    }
    // 检查用户名唯一（排除自己）
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        error('用户名已被使用');
    }
    $updates[] = 'username = ?';
    $params[] = $username;
}

// 更新密码
if (!empty($input['password'])) {
    if (strlen($input['password']) < 6) {
        error('密码至少6位');
    }
    $updates[] = 'password_hash = ?';
    $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
}

// 启用/禁用
if (isset($input['is_active'])) {
    $updates[] = 'is_active = ?';
    $params[] = (int)$input['is_active'];
}

if (empty($updates)) {
    error('没有需要更新的字段');
}

$params[] = $userId;
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

success(['message' => '用户已更新']);
