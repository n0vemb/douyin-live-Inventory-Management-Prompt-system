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

$username    = trim($input['username'] ?? '');
$password    = $input['password'] ?? '';
$displayName = trim($input['display_name'] ?? $username);
$role        = $input['role'] ?? 'operator';
$storeId     = $input['store_id'] ?? null;
$shopId      = isset($input['shop_id']) && $input['shop_id'] !== '' ? (int)$input['shop_id'] : null;
$pdo = getDB();

if (empty($username)) {
    error('请输入用户名');
}
if (strlen($password) < 6) {
    error('密码至少6位');
}

// 角色权限：按 平台/集团/店 作用域收口
if (!in_array($role, $scope['roles'], true)) {
    error('无权创建该角色，或当前角色不属于你的管理范围');
}

if ($scope['scope'] === 'group') {
    $storeId = $scope['store_id'];
} elseif ($scope['scope'] === 'shop') {
    $storeId = $scope['store_id'];
    $shopId = $scope['shop_id'];
}

// 集团管理员/超管账号：必须属于某个集团（store_id 必填，shop_id 置空）
if ($role === 'group_admin') {
    if (empty($storeId)) {
        error('集团管理员必须指定所属集团');
    }
    $shopId = null;
}

// 平台超管账号：不绑集团/店
if ($role === 'super_admin') {
    $storeId = null;
    $shopId = null;
}

// 店级角色（店管/副店长/运营）：必须指定集团+店
if (in_array($role, ['store_admin', 'operator', 'deputy_store_admin'], true)) {
    if (empty($storeId) || !$shopId) {
        error('店管、副店长和运营账号必须指定所属集团和店铺');
    }
    $stmt = $pdo->prepare('SELECT id FROM shops WHERE id = ? AND store_id = ?');
    $stmt->execute([$shopId, $storeId]);
    if (!$stmt->fetch()) {
        error('所选店铺不属于该集团');
    }
}

// 仓库账号：集团级，不绑具体店
if ($role === 'warehouse') {
    if (empty($storeId)) {
        error('仓库账号必须指定所属集团');
    }
    $shopId = null;
}

// 除平台超管外，其余角色必须有集团
if ($role !== 'super_admin' && empty($storeId)) {
    error('该角色必须指定所属集团');
}

// 检查用户名
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    error('用户名已存在');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO users (username, password_hash, display_name, role, store_id, shop_id, is_active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
);
$stmt->execute([$username, $hash, $displayName, $role, $storeId ? (int)$storeId : null, $shopId]);

success(['message' => '用户创建成功', 'data' => ['user_id' => (int)$pdo->lastInsertId()]]);
