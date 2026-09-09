<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

$currentUser = getCurrentUser();
$scope = userManageScope();
if (!$scope) {
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

$stmt = $pdo->prepare('SELECT id, username, role, store_id, shop_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    error('用户不存在');
}

// 不允许重置自己的密码（防止误操作）
if ($userId === (int)$currentUser['id']) {
    error('不能重置自己的密码');
}

// 目标必须落在当前账号的管理范围
if (!targetInManageScope($scope, $user)) {
    error('无权重置该用户的密码（不在你的管理范围内）');
}

$defaultPassword = '123456';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hash, $userId]);

success([
    'message' => "用户「{$user['username']}」的密码已重置为 {$defaultPassword}"
]);
