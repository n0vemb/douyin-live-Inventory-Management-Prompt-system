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

$userId   = (int)($input['user_id'] ?? 0);
$isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;
$role     = $input['role'] ?? null;
$storeId  = isset($input['store_id']) && $input['store_id'] !== '' ? (int)$input['store_id'] : null;

if ($userId <= 0) {
    error('请提供用户ID');
}

$pdo = getDB();

// 校验目标用户存在
$stmt = $pdo->prepare('SELECT id, role, store_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch();
if (!$target) {
    error('用户不存在');
}

// 店铺管理员只能操作自己店铺的运营/仓库账号
if ($isStoreAdmin) {
    if (!in_array($target['role'], ['operator', 'warehouse']) || (int)$target['store_id'] !== (int)$currentUser['store_id']) {
        error('只能管理自己店铺的运营和仓库账号');
    }
    // 强制：运营/仓库角色 + 自己店铺
    $role = $target['role'];
    $storeId = (int)$currentUser['store_id'];
}

$updates = [];
$params = [];

if ($isActive !== null) {
    $updates[] = 'is_active = ?';
    $params[] = $isActive;
}

if (!empty($input['password'])) {
    $updates[] = 'password_hash = ?';
    $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
}

// 角色/店铺更新（修复：原实现只更新密码/启用，角色和店铺不生效）
if ($role !== null) {
    if (!in_array($role, ['super_admin', 'store_admin', 'operator', 'warehouse'])) {
        error('无效的角色');
    }
    // 店铺管理员/运营/仓库必须有店铺；超管不能绑店铺
    if (in_array($role, ['store_admin', 'operator', 'warehouse'])) {
        if (!$storeId) {
            error('店铺管理员、运营和仓库账号必须指定所属店铺');
        }
    } else {
        $storeId = null;
    }
    $updates[] = 'role = ?';
    $params[] = $role;
    $updates[] = 'store_id = ?';
    $params[] = $storeId;
}

if (empty($updates)) {
    error('没有需要更新的字段');
}

$params[] = $userId;
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

success(['message' => '用户已更新']);
