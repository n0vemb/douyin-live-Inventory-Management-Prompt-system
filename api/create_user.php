<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);

$username    = trim($input['username'] ?? '');
$password    = $input['password'] ?? '';
$displayName = trim($input['display_name'] ?? $username);
$role        = $input['role'] ?? 'store_admin';
$storeId     = $input['store_id'] ?? null;

if (empty($username)) {
    error('请输入用户名');
}
if (strlen($password) < 6) {
    error('密码至少6位');
}
if (!in_array($role, ['super_admin', 'store_admin'])) {
    error('无效的角色');
}

$pdo = getDB();

// 检查用户名
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    error('用户名已存在');
}

// 店铺管理员必须有店铺
if ($role === 'store_admin' && empty($storeId)) {
    error('店铺管理员必须指定所属店铺');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, display_name, role, store_id, is_active)
     VALUES (?, ?, ?, ?, ?, 1)'
);
$stmt->execute([$username, $hash, $displayName, $role, $storeId ? (int)$storeId : null]);

success(['message' => '用户创建成功', 'data' => ['user_id' => (int)$pdo->lastInsertId()]]);
