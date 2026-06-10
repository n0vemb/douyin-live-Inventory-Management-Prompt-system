<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);

$userId   = (int)($input['user_id'] ?? 0);
$isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;

if ($userId <= 0) {
    error('请提供用户ID');
}

$pdo = getDB();

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

if (empty($updates)) {
    error('没有需要更新的字段');
}

$params[] = $userId;
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

success(['message' => '用户已更新']);
