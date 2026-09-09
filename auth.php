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
 *   group_admin  — 集团管理员（本集团/租户，跨店看汇总报表；待办跨店只读不派）
 *   store_admin  — 店铺管理员/店管（本店，可看成本利润；店间互不可见）
 *   operator     — 运营（本店，可看销售额，但成本/毛利/毛利率全隐藏）
 *   deputy_store_admin — 副店长（本店，能力等同运营 + 可进行库存盘点）
 *   warehouse    — 仓库（2026-08-21 新增：集团级，登录后只能进仓库出库台，看不到价格成本）
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

    // 仓库角色：只能访问仓库出库台页面 + 仓库API + 货架分布查询（拣货位置），其余一律拦截
    if (($_SESSION['role'] ?? '') === 'warehouse') {
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $allowed = (
            strpos($scriptPath, '/admin/warehouse.php') !== false
            || strpos($scriptPath, '/api/warehouse_') !== false
            || strpos($scriptPath, '/api/get_racks.php') !== false
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
 * 是否为集团管理员（本集团/租户，跨店汇总）
 */
function isGroupAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'group_admin';
}

/**
 * 是否为店管（本店管理员）
 */
function isStoreAdmin(): bool {
    return ($_SESSION['role'] ?? '') === 'store_admin';
}

/**
 * 是否为运营角色
 */
function isOperator(): bool {
    return in_array($_SESSION['role'] ?? '', ['operator', 'deputy_store_admin'], true);
}

/**
 * 是否允许库存盘点（副店长及以上；运营已取消盘点权限）
 */
function canAuditInventory(): bool {
    return canPerm('audit.inventory');
}

/**
 * 盘点权限校验
 */
function requireInventoryAudit(): void {
    requireAuth();
    if (!canAuditInventory()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '权限不足：运营账号无库存盘点权限']);
        exit;
    }
}

/**
 * 细粒度权限默认值（角色+权限点；role_permissions 表可覆盖）
 * perm 说明见 admin/roles_permissions.php
 */
function defaultPermMap(): array {
    return [
        'audit.inventory'   => ['super_admin', 'group_admin', 'store_admin', 'deputy_store_admin'],
        'audit.rack'        => ['super_admin', 'group_admin', 'store_admin', 'deputy_store_admin'],
        'product.export'    => ['super_admin', 'group_admin', 'store_admin'],
        'product.delete'    => ['super_admin', 'group_admin', 'store_admin'],
        'product.offline_price' => ['super_admin', 'group_admin', 'store_admin'],
        'product.batch_edit'=> ['super_admin', 'group_admin', 'store_admin'],
        'live.session_meta' => ['super_admin', 'group_admin', 'store_admin'],
        'pos.delete_order'  => ['super_admin', 'group_admin', 'store_admin'],
        'finance.view_cost' => ['super_admin', 'group_admin', 'store_admin'],
        'finance.report'    => ['super_admin', 'group_admin', 'store_admin'],
        'user.manage'       => ['super_admin', 'group_admin', 'store_admin'],
        'todo.cross_shop_view' => ['super_admin', 'group_admin'],
        'coupon.issue'      => ['super_admin', 'group_admin', 'store_admin', 'deputy_store_admin'],
        'compensate.shipping' => ['super_admin', 'group_admin', 'store_admin', 'deputy_store_admin', 'operator'],
    ];
}

/** 读取权限覆盖值；没有覆盖返回 null */
function permOverride($role, $perm) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $pdo = getDB();
            foreach ($pdo->query('SELECT role, perm, allowed FROM role_permissions')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $cache[$r['role'] . '|' . $r['perm']] = (int)$r['allowed'] === 1;
            }
        } catch (Exception $e) { $cache = []; }
    }
    return array_key_exists($role . '|' . $perm, $cache) ? $cache[$role . '|' . $perm] : null;
}

/** 当前用户是否拥有某权限（默认值可被 role_permissions 覆盖） */
function canPerm($perm) {
    $role = $_SESSION['role'] ?? '';
    $over = permOverride($role, $perm);
    if ($over !== null) return $over;
    $defs = defaultPermMap();
    if (!isset($defs[$perm])) return true; // 未纳入细粒度控制的权限点默认放行（沿用原角色判断）
    return in_array($role, $defs[$perm], true);
}

function requirePerm($perm) {
    requireAuth();
    if (!canPerm($perm)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '权限不足：无权执行该操作']);
        exit;
    }
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
 * 获取当前生效店ID（业务数据按店隔离时的过滤维度）
 * - super_admin：view_shop_id（可空；空=看当前集团/全平台汇总）
 * - group_admin / warehouse：NULL（集团级：跨店汇总 / 仓库共用）
 * - store_admin / deputy_store_admin / operator：本人所属店
 */
function getShopId(): ?int {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'super_admin') {
        return isset($_SESSION['view_shop_id']) && $_SESSION['view_shop_id'] !== '' ? (int)$_SESSION['view_shop_id'] : null;
    }
    return isset($_SESSION['shop_id']) && $_SESSION['shop_id'] !== '' ? (int)$_SESSION['shop_id'] : null;
}

/**
 * 获取当前生效店名（页面角标/筛选用）
 */
function getShopName(): string {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'super_admin') {
        return (string)($_SESSION['view_shop_name'] ?? '');
    }
    return (string)($_SESSION['shop_name'] ?? '');
}

/**
 * 幂等创建集团“默认店”（新注册集团或迁移未跑时兜底）
 * @return int|null 店ID；表不存在/失败时返回 null（老版本降级）
 */
function ensureDefaultShop(int $storeId): ?int {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM shops WHERE store_id = ? AND name = '默认店' ORDER BY id LIMIT 1");
        $stmt->execute([$storeId]);
        $shopId = $stmt->fetchColumn();
        if ($shopId) {
            ensureShopPosCode($pdo, (int)$shopId);
            return (int)$shopId;
        }

        $stmt = $pdo->prepare('INSERT INTO shops (store_id, name, remark) VALUES (?, ?, ?)');
        $stmt->execute([$storeId, '默认店', '注册/迁移自动生成']);
        $newId = (int)$pdo->lastInsertId();
        ensureShopPosCode($pdo, $newId);
        return $newId;
    } catch (Exception $e) {
        return null; // shops 表尚未迁移：降级为老版本行为
    }
}

/**
 * 当前账号可管理的用户范围（null=无用户管理权）
 * - super_admin：全平台
 * - group_admin：本集团（可管 店管/副店长/运营/仓库，不可管超管与其他集团管理员）
 * - store_admin：仅本店（副店长/运营）
 */
function userManageScope(): ?array {
    $role = $_SESSION['role'] ?? '';
    $storeId = isset($_SESSION['store_id']) && $_SESSION['store_id'] !== '' ? (int)$_SESSION['store_id'] : null;
    if ($role === 'super_admin') {
        return [
            'scope' => 'platform',
            'store_id' => null,
            'shop_id' => null,
            'roles' => ['super_admin', 'group_admin', 'store_admin', 'operator', 'deputy_store_admin', 'warehouse'],
        ];
    }
    if ($role === 'group_admin' && $storeId) {
        return [
            'scope' => 'group',
            'store_id' => $storeId,
            'shop_id' => null,
            'roles' => ['store_admin', 'operator', 'deputy_store_admin', 'warehouse'],
        ];
    }
    if ($role === 'store_admin' && $storeId) {
        return [
            'scope' => 'shop',
            'store_id' => $storeId,
            'shop_id' => getShopId(),
            'roles' => ['operator', 'deputy_store_admin'],
        ];
    }
    return null;
}

/** 目标用户是否在当前管理范围内（scope 来自 userManageScope） */
function targetInManageScope(array $scope, array $target): bool {
    if (!in_array($target['role'], $scope['roles'], true)) {
        return false;
    }
    if ($scope['scope'] === 'platform') {
        return true;
    }
    if ($scope['scope'] === 'group') {
        return (int)$target['store_id'] === $scope['store_id'];
    }
    return (int)$target['store_id'] === $scope['store_id']
        && (int)($target['shop_id'] ?? 0) === $scope['shop_id'];
}

/**
 * 读取台账场次（live_ledger_session）并按当前作用域校验：
 * - 店级角色（店管/副店长/运营）只能取本店场次
 * - 集团管理员/仓库取本集团任意场次；超管按 view_store/view_shop
 */
function requireLedgerSessionRow(PDO $pdo, int $sessionId): array {
    $storeId = getStoreId();
    $shopId = getShopId();
    $sql = 'SELECT * FROM live_ledger_session WHERE id = ?';
    $params = [$sessionId];
    if ($storeId) {
        $sql .= ' AND store_id = ?';
        $params[] = $storeId;
    }
    if ($shopId) {
        $sql .= ' AND shop_id = ?';
        $params[] = $shopId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        error('场次不存在或无权访问');
    }
    return $row;
}

/** 同上，用于旧直播链路 live_sessions */
function requireLiveSessionRow(PDO $pdo, int $sessionId): array {
    $storeId = getStoreId();
    $shopId = getShopId();
    $sql = 'SELECT * FROM live_sessions WHERE id = ?';
    $params = [$sessionId];
    if ($storeId) {
        $sql .= ' AND store_id = ?';
        $params[] = $storeId;
    }
    if ($shopId) {
        $sql .= ' AND shop_id = ?';
        $params[] = $shopId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        error('场次不存在或无权访问');
    }
    return $row;
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
    $viewShopName = '';
    if (($_SESSION['role'] ?? '') === 'super_admin' && !empty($_SESSION['view_shop_id'])) {
        $viewShopName = $_SESSION['view_shop_name'] ?? '';
    }
    return [
        'id'           => $_SESSION['user_id'] ?? null,
        'username'     => $_SESSION['username'] ?? null,
        'display_name' => $_SESSION['display_name'] ?? null,
        'role'         => $_SESSION['role'] ?? null,
        'store_id'     => $_SESSION['store_id'] ?? null,
        'store_name'   => $_SESSION['store_name'] ?? null,
        'shop_id'      => $_SESSION['shop_id'] ?? null,
        'shop_name'    => $_SESSION['shop_name'] ?? null,
        'view_store_id'   => $_SESSION['view_store_id'] ?? null,
        'view_store_name' => $viewStoreName,
        'view_shop_id'    => $_SESSION['view_shop_id'] ?? null,
        'view_shop_name'  => $viewShopName,
        'can_see_profit'  => canSeeProfit(),
    ];
}
