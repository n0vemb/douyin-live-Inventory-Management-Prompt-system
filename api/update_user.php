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

$userId   = (int)($input['user_id'] ?? 0);
$isActive = isset($input['is_active']) ? (int)$input['is_active'] : null;
$role     = $input['role'] ?? null;
$storeId  = isset($input['store_id']) && $input['store_id'] !== '' ? (int)$input['store_id'] : null;
$shopId   = isset($input['shop_id']) && $input['shop_id'] !== '' ? (int)$input['shop_id'] : null;
$username    = array_key_exists('username', $input) ? trim((string)$input['username']) : null;
$displayName = array_key_exists('display_name', $input) ? trim((string)$input['display_name']) : null;

if ($userId <= 0) {
    error('请提供用户ID');
}

$pdo = getDB();

// 校验目标用户存在
$stmt = $pdo->prepare('SELECT id, username, role, store_id, shop_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch();
if (!$target) {
    error('用户不存在');
}

// 目标必须落在当前账号的管理范围（平台/集团/店）
if (!targetInManageScope($scope, $target)) {
    error('无权管理该用户（不在你的管理范围内）');
}

$updates = [];
$params = [];

if ($isActive !== null) {
    $updates[] = 'is_active = ?';
    $params[] = $isActive;
}

// 用户名 / 显示名称（修复：此前接口忽略这两个字段，前端提示成功但实际未保存）
if ($username !== null) {
    if ($username === '') {
        error('请输入用户名');
    }
    if ($username !== $target['username']) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            error('用户名已存在');
        }
    }
    $updates[] = 'username = ?';
    $params[] = $username;
}

if ($displayName !== null) {
    $updates[] = 'display_name = ?';
    $params[] = $displayName === '' ? null : $displayName;
}

if (!empty($input['password'])) {
    $updates[] = 'password_hash = ?';
    $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
}

// 角色/店铺更新（修复：原实现只更新密码/启用，角色和店铺不生效）
if ($role !== null) {
    if (!in_array($role, $scope['roles'], true)) {
        error('无权将该账号设为该角色，或该角色不在你的管理范围内');
    }

    if ($scope['scope'] === 'group') {
        $storeId = $scope['store_id'];
    } elseif ($scope['scope'] === 'shop') {
        $storeId = $scope['store_id'];
        $shopId = $scope['shop_id'];
    }

    // 平台超管：不绑集团/店
    if ($role === 'super_admin') {
        $storeId = null;
        $shopId = null;
    }

    // 集团管理员：绑集团、不绑店
    if ($role === 'group_admin') {
        if (empty($storeId)) {
            error('集团管理员必须指定所属集团');
        }
        $shopId = null;
    }

    // 店级角色（店管/副店长/运营）：必须绑集团+店
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

    // 仓库：集团级，不绑店
    if ($role === 'warehouse') {
        if (empty($storeId)) {
            error('仓库账号必须指定所属集团');
        }
        $shopId = null;
    }

    // 除平台超管外都必须有集团
    if ($role !== 'super_admin' && empty($storeId)) {
        error('该角色必须指定所属集团');
    }

    // 防止把目标移到自己的管理范围之外（平台超管不受限）
    if ($scope['scope'] !== 'platform') {
        $finalTarget = ['role' => $role, 'store_id' => $storeId, 'shop_id' => $shopId];
        if (!targetInManageScope($scope, $finalTarget)) {
            error('不能把该账号移到你的管理范围之外');
        }
    }

    $updates[] = 'role = ?';
    $params[] = $role;
    $updates[] = 'store_id = ?';
    $params[] = $storeId;
    $updates[] = 'shop_id = ?';
    $params[] = $shopId;
}

if (empty($updates)) {
    error('没有需要更新的字段');
}

$params[] = $userId;
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

// 编辑的是自己时同步会话，让顶栏/当前会话立即生效（避免显示旧名称/用户名）
if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
    if ($username !== null) {
        $_SESSION['username'] = $username;
    }
    if ($displayName !== null) {
        $_SESSION['display_name'] = $displayName === '' ? null : $displayName;
    }
    if ($role !== null) {
        $_SESSION['role'] = $role;
        $_SESSION['store_id'] = $storeId;
        $_SESSION['store_name'] = '';
        if ($storeId) {
            $st = getDB()->prepare('SELECT name FROM stores WHERE id = ?');
            $st->execute([$storeId]);
            $sn = $st->fetchColumn();
            if ($sn) $_SESSION['store_name'] = $sn;
        }
        $_SESSION['shop_id'] = $shopId;
        $_SESSION['shop_name'] = '';
        if ($shopId) {
            $st = getDB()->prepare('SELECT name FROM shops WHERE id = ?');
            $st->execute([$shopId]);
            $sh = $st->fetchColumn();
            if ($sh) $_SESSION['shop_name'] = $sh;
        }
        unset($_SESSION['view_store_id'], $_SESSION['view_store_name'], $_SESSION['view_shop_id'], $_SESSION['view_shop_name']);
    }
}

success(['message' => '用户已更新']);
