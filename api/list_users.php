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

$pdo = getDB();

$sql = '
    SELECT u.id, u.username, u.display_name, u.role, u.store_id, u.shop_id, u.is_active,
           u.last_login_at, u.created_at,
           s.name AS store_name, sh.name AS shop_name
    FROM users u
    LEFT JOIN stores s ON u.store_id = s.id
    LEFT JOIN shops sh ON u.shop_id = sh.id
    WHERE 1=1';
$params = [];

if ($scope['scope'] === 'group') {
    $sql .= ' AND u.store_id = ?';
    $params[] = $scope['store_id'];
} elseif ($scope['scope'] === 'shop') {
    $sql .= ' AND u.store_id = ? AND u.shop_id = ?';
    $params[] = $scope['store_id'];
    $params[] = $scope['shop_id'];
}

// 排序：超管 → 集团 → 店管 → 副店长 → 运营 → 仓库；同角色按集团/店/ID
$sql .= " ORDER BY FIELD(u.role, 'super_admin', 'group_admin', 'store_admin', 'deputy_store_admin', 'operator', 'warehouse'),
          u.store_id, u.shop_id, u.id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 确保 is_active 为整数
foreach ($users as &$u) {
    $u['is_active'] = (int)$u['is_active'];
}

success(['data' => ['users' => $users]]);
