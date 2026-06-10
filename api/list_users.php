<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$pdo = getDB();
$stmt = $pdo->query('
    SELECT u.id, u.username, u.display_name, u.role, u.store_id, u.is_active, u.last_login_at, u.created_at,
           s.name AS store_name
    FROM users u
    LEFT JOIN stores s ON u.store_id = s.id
    ORDER BY u.id
');
$users = $stmt->fetchAll();

// 确保 is_active 为整数
foreach ($users as &$u) {
    $u['is_active'] = (int)$u['is_active'];
}

success(['data' => ['users' => $users]]);
