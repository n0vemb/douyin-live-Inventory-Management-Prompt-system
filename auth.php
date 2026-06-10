<?php
/**
 * 认证中间件
 * 使用方式：所有受保护的页面/API 在顶部 require_once __DIR__ . '/auth.php';
 */

/**
 * 检查登录状态，未登录时返回 401 或重定向
 * @return int|null 店铺ID（超管返回 null）
 */
function requireAuth(): ?int {
    if (empty($_SESSION['user_id'])) {
        // API 请求检测（根据路径或 Accept 头）
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $isApi = (strpos($scriptPath, '/api/') !== false);

        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '请先登录']);
            exit;
        }

        // 页面请求：重定向到登录页
        header('Location: /login.php');
        exit;
    }

    return $_SESSION['store_id'] ?? null;
}

/**
 * 检查是否为超级管理员
 */
function requireSuperAdmin(): void {
    requireAuth();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '权限不足']);
        exit;
    }
}

/**
 * 是否为超级管理员
 */
function isSuperAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'super_admin';
}

/**
 * 获取有效店铺ID（用于数据筛选）
 * 超管未选店铺 → null（看全平台）
 * 超管选了店铺 → 该店铺ID
 * 普通用户 → 自己的店铺ID
 */
function getStoreId(): ?int {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'super_admin') {
        // view_store_id: null=全平台, >0=特定店铺
        return isset($_SESSION['view_store_id']) ? $_SESSION['view_store_id'] : null;
    }
    return $_SESSION['store_id'] ?? null;
}

/**
 * 获取当前用户信息
 * @return array
 */
function getCurrentUser(): array {
    $viewStoreName = '';
    if (($_SESSION['role'] ?? '') === 'super_admin' && !empty($_SESSION['view_store_id'])) {
        $viewStoreName = $_SESSION['view_store_name'] ?? '';
    }
    return [
        'id'           => $_SESSION['user_id'] ?? null,
        'username'     => $_SESSION['username'] ?? null,
        'display_name' => $_SESSION['display_name'] ?? null,
        'role'         => $_SESSION['role'] ?? null,
        'store_id'     => $_SESSION['store_id'] ?? null,
        'store_name'   => $_SESSION['store_name'] ?? null,
        'view_store_id'   => $_SESSION['view_store_id'] ?? null,
        'view_store_name' => $viewStoreName,
    ];
}
