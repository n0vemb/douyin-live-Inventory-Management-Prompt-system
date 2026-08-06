<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

$currentUser = getCurrentUser();
$isSuperAdmin = ($currentUser['role'] === 'super_admin');
$isStoreAdmin = ($currentUser['role'] === 'store_admin');
if (!$isSuperAdmin && !$isStoreAdmin) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => '权限不足']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$username    = trim($input['username'] ?? '');
$password    = $input['password'] ?? '';
$displayName = trim($input['display_name'] ?? $username);
$role        = $input['role'] ?? 'operator';
$storeId     = $input['store_id'] ?? null;

if (empty($username)) {
    error('请输入用户名');
}
if (strlen($password) < 6) {
    error('密码至少6位');
}

// 角色权限：超管可创建任意角色；店铺管理员只能创建运营
if ($isSuperAdmin) {
    if (!in_array($role, ['super_admin', 'store_admin', 'operator'])) {
        error('无效的角色');
    }
} else {
    if ($role !== 'operator') {
        error('店铺管理员只能创建运营账号');
    }
    $storeId = $currentUser['store_id'];
}

$pdo = getDB();

// 检查用户名
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    error('用户名已存在');
}

// 店铺管理员/运营必须有店铺
if (($role === 'store_admin' || $role === 'operator') && empty($storeId)) {
    error('店铺管理员和运营必须指定所属店铺');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, display_name, role, store_id, is_active)
     VALUES (?, ?, ?, ?, ?, 1)'
);
$stmt->execute([$username, $hash, $displayName, $role, $storeId ? (int)$storeId : null]);

success(['message' => '用户创建成功', 'data' => ['user_id' => (int)$pdo->lastInsertId()]]);
