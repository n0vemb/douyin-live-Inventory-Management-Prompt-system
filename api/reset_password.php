<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$userId = (int)($input['user_id'] ?? 0);

if ($userId <= 0) {
    error('请提供用户ID');
}

$pdo = getDB();

$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    error('用户不存在');
}

// 不允许重置自己的密码（防止误操作）
$currentUser = getCurrentUser();
if ($userId === (int)$currentUser['id']) {
    error('不能重置自己的密码');
}

$defaultPassword = '123456';
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hash, $userId]);

success([
    'message' => "用户「{$user['username']}」的密码已重置为 {$defaultPassword}"
]);
