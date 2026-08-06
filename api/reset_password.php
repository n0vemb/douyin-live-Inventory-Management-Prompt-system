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

$pdo = getDB();

$stmt = $pdo->prepare('SELECT id, username, role, store_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    error('用户不存在');
}

// 不允许重置自己的密码（防止误操作）
if ($userId === (int)$currentUser['id']) {
    error('不能重置自己的密码');
}

// 店铺管理员只能重置自己店铺运营账号的密码
if ($isStoreAdmin) {
    if ($user['role'] !== 'operator' || (int)$user['store_id'] !== (int)$currentUser['store_id']) {
        error('只能重置自己店铺的运营账号密码');
    }
}

$defaultPassword = '123456';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hash, $userId]);

success([
    'message' => "用户「{$user['username']}」的密码已重置为 {$defaultPassword}"
]);
