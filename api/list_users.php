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

$pdo = getDB();

if ($isSuperAdmin) {
    $stmt = $pdo->query('
        SELECT u.id, u.username, u.display_name, u.role, u.store_id, u.is_active, u.last_login_at, u.created_at,
               s.name AS store_name
        FROM users u
        LEFT JOIN stores s ON u.store_id = s.id
        ORDER BY u.id
    ');
} else {
    // 店铺管理员只能看自己店铺的运营账号
    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.display_name, u.role, u.store_id, u.is_active, u.last_login_at, u.created_at,
               s.name AS store_name
        FROM users u
        LEFT JOIN stores s ON u.store_id = s.id
        WHERE u.role = ? AND u.store_id = ?
        ORDER BY u.id
    ');
    $stmt->execute(['operator', $currentUser['store_id']]);
}
$users = $stmt->fetchAll();

// 确保 is_active 为整数
foreach ($users as &$u) {
    $u['is_active'] = (int)$u['is_active'];
}

success(['data' => ['users' => $users]]);
