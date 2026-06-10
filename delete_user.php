<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$userId = (int)($input['user_id'] ?? 0);

if ($userId <= 0) {
    error('请提供用户ID');
}

// 不允许删除自己
$currentUser = getCurrentUser();
if ($userId === (int)$currentUser['id']) {
    error('不能删除自己的账号');
}

$pdo = getDB();

// 检查用户是否存在
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    error('用户不存在');
}

// 检查是否还有超管（如果要删除的是超管）
if ($user['username'] === 'admin') {
    error('不能删除系统默认管理员账号');
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$userId]);

success(['message' => '用户已删除']);
