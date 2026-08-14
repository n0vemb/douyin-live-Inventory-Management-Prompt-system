<?php
/**
 * todo_list.php — 待办事项列表
 * GET { filter: all|pending|done, q: 内容模糊搜索 }
 * 返回 { items, members }
 * - 店铺管理员/运营：只看本店
 * - 超管未选店(null)：看全部店铺（item 带 store_name），members 为全部用户
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

requireAuth();
$storeId = getStoreId();

$filter = $_GET['filter'] ?? 'all';
$q      = trim($_GET['q'] ?? '');

if (!in_array($filter, ['all', 'pending', 'done'], true)) {
    $filter = 'all';
}

$pdo = getDB();

$sql = "SELECT t.*, s.name AS store_name,
               u1.display_name AS creator_name, u2.display_name AS completor_name
        FROM todo_items t
        JOIN stores s ON s.id = t.store_id
        LEFT JOIN users u1 ON u1.id = t.creator_id
        LEFT JOIN users u2 ON u2.id = t.completed_by
        WHERE 1=1";
$params = [];

if ($storeId !== null) {
    $sql .= " AND t.store_id = ?";
    $params[] = $storeId;
}
if ($filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $filter;
}
if ($q !== '') {
    $sql .= " AND t.content LIKE ?";
    $params[] = '%' . $q . '%';
}
// 待完成在前，各自按时间倒序
$sql .= " ORDER BY (t.status = 'done') ASC, COALESCE(t.completed_at, t.created_at) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = array_map(function ($r) {
    $assignees = json_decode($r['assignees'] ?? 'null', true);
    return [
        'id'               => (int)$r['id'],
        'store_id'         => (int)$r['store_id'],
        'store_name'       => $r['store_name'] ?? '',
        'content'          => $r['content'],
        'priority'         => $r['priority'],
        'status'           => $r['status'],
        'creator_id'       => (int)$r['creator_id'],
        'creator_name'     => $r['creator_name'] ?: '',
        'assignees'        => is_array($assignees) ? array_map('intval', $assignees) : [],
        'completed_by'     => $r['completed_by'] !== null ? (int)$r['completed_by'] : null,
        'completor_name'   => $r['completor_name'] ?: '',
        'completion_detail'=> $r['completion_detail'] ?: '',
        'created_at'       => $r['created_at'],
        'completed_at'     => $r['completed_at'] ?: null,
    ];
}, $rows);

// 批量查所有待办的更新记录（避免 N+1），按时间正序
$ids = array_column($items, 'id');
$updatesMap = [];
if (count($ids) > 0) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $uStmt = $pdo->prepare("SELECT u.id, u.todo_id, u.content, u.assignees, u.updated_by, u.created_at, us.display_name AS updater_name
                            FROM todo_updates u
                            LEFT JOIN users us ON us.id = u.updated_by
                            WHERE u.todo_id IN ($ph)
                            ORDER BY u.created_at ASC, u.id ASC");
    $uStmt->execute($ids);
    foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $updAssignees = json_decode($u['assignees'] ?? 'null', true);
        $updatesMap[$u['todo_id']][] = [
            'id'           => (int)$u['id'],
            'content'      => $u['content'],
            'assignees'    => is_array($updAssignees) ? array_map('intval', $updAssignees) : [],
            'updated_by'   => (int)$u['updated_by'],
            'updater_name' => $u['updater_name'] ?: '',
            'created_at'   => $u['created_at'],
        ];
    }
}
foreach ($items as &$it) {
    $it['updates'] = $updatesMap[$it['id']] ?? [];
}
unset($it);

// 本店成员（用于 @ 指定执行人 / 渲染姓名）
if ($storeId !== null) {
    $mStmt = $pdo->prepare("SELECT id, username, display_name FROM users WHERE store_id = ? AND is_active = 1 ORDER BY display_name, username");
    $mStmt->execute([$storeId]);
} else {
    $mStmt = $pdo->query("SELECT id, username, display_name FROM users WHERE is_active = 1 ORDER BY display_name, username");
}
$members = array_map(function ($u) {
    return [
        'id'   => (int)$u['id'],
        'name' => ($u['display_name'] ?: $u['username']) ?: ('用户' . $u['id']),
    ];
}, $mStmt->fetchAll(PDO::FETCH_ASSOC));

// 当前有效店铺信息（超管未选店 → null，前端提示先切店）
$current = null;
if ($storeId !== null) {
    $sStmt = $pdo->prepare("SELECT id, name FROM stores WHERE id = ?");
    $sStmt->execute([$storeId]);
    $s = $sStmt->fetch();
    if ($s) {
        $current = ['store_id' => (int)$s['id'], 'store_name' => $s['name']];
    }
}

// 当前登录用户（前端据此判断可编辑项）
$cuId = (int)($_SESSION['user_id'] ?? 0);
$cuName = '';
if ($cuId > 0) {
    $cuStmt = $pdo->prepare("SELECT display_name, username FROM users WHERE id = ?");
    $cuStmt->execute([$cuId]);
    $cu = $cuStmt->fetch();
    if ($cu) {
        $cuName = ($cu['display_name'] ?: $cu['username']) ?: ('用户' . $cuId);
    }
}
$currentUser = ['id' => $cuId, 'name' => $cuName];

success(['items' => $items, 'members' => $members, 'current_store' => $current, 'current_user' => $currentUser]);
