<?php
// 确保会话已启动（部分页面先 require auth.php 再 require config.php，
// 未启动会话时 $_SESSION 为空导致 requireAuth 误判未登录而 302）
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
/**
 * 认证中间件
 * 使用方式：所有受保护的页面/API 在顶部 require_once __DIR__ . '/auth.php';
 *
 * 角色体系（2026-08-06 扩展）：
 *   super_admin  — 超级管理员（全平台，可看成本利润）
 *   store_admin  — 店铺管理员（本店铺，可看成本利润）
 *   operator     — 运营（本店铺，可看销售额，但成本/毛利/毛利率全隐藏）
 *   warehouse    — 仓库（2026-08-21 新增：本店铺，登录后只能进仓库出库台，看不到价格成本）
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

    // 仓库角色：只能访问仓库出库台页面 + 仓库API，其余一律拦截
    if (($_SESSION['role'] ?? '') === 'warehouse') {
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $allowed = (
            strpos($scriptPath, '/admin/warehouse.php') !== false
            || strpos($scriptPath, '/api/warehouse_') !== false
            || strpos($scriptPath, '/login.php') !== false
            || strpos($scriptPath, '/logout') !== false
        );
        if (!$allowed) {
            $isApi = (strpos($scriptPath, '/api/') !== false);
            if ($isApi) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => '权限不足：仓库账号仅可访问仓库出库台']);
                exit;
            }
            header('Location: /admin/warehouse.php');
            exit;
        }
    }

    return $_SESSION['store_id'] ?? null;
}

/**
 * 是否为仓库角色
 */
function isWarehouse(): bool {
    return ($_SESSION['role'] ?? '') === 'warehouse';
}

/**
 * 仓库功能访问权限：超管/店管/仓库可用，运营不可用（运营不可操作仓库）
 */
function requireWarehouseAccess(): void {
    requireAuth();
    if (isOperator()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '权限不足：运营账号无仓库操作权限']);
        exit;
    }
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
 * 是否为运营角色
 */
function isOperator(): bool {
    return ($_SESSION['role'] ?? '') === 'operator';
}

/**
 * 当前用户能否查看成本/利润数据
 * 运营（operator）不可看，其余角色可看
 */
function canSeeProfit(): bool {
    return !isOperator();
}

/**
 * 拒绝运营访问（用于财务、用户管理等页面/API）
 * 运营访问 → 403
 */
function requireNonOperator(): void {
    requireAuth();
    if (isOperator()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '权限不足：运营账号无此权限']);
        exit;
    }
}

/**
 * 获取有效店铺ID（用于数据筛选）
 * 超管未选店铺 → null（看全平台）
 * 超管选了店铺 → 该店铺ID
 * 店铺管理员/运营 → 自己的店铺ID
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
        'can_see_profit'  => canSeeProfit(),
    ];
}
