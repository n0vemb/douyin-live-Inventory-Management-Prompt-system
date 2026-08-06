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
$userId = (int)($input['user_id'] ?? 0);

if ($userId <= 0) {
    error('请提供用户ID');
}

// 不允许删除自己
if ($userId === (int)$currentUser['id']) {
    error('不能删除自己的账号');
}

$pdo = getDB();

// 检查用户是否存在
$stmt = $pdo->prepare('SELECT id, username, role, store_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    error('用户不存在');
}

// 店铺管理员只能删除自己店铺的运营账号
if ($isStoreAdmin) {
    if ($user['role'] !== 'operator' || (int)$user['store_id'] !== (int)$currentUser['store_id']) {
        error('只能删除自己店铺的运营账号');
    }
}

// 检查是否还有超管（如果要删除的是超管）
if ($user['username'] === 'admin') {
    error('不能删除系统默认管理员账号');
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$userId]);

success(['message' => '用户已删除']);
