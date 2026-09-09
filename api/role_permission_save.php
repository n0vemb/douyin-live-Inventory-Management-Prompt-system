<?php
/**
 * role_permission_save.php — 细粒度权限配置保存
 * POST { items:[{role, perm, allowed, default?}] }
 *   allowed===default → 删除覆盖，恢复默认；否则写入覆盖
 * 权限：店管/集团管理员/超管
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'group_admin', 'super_admin'], true)) error('无权限', 403);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$items = $input['items'] ?? [];
if (!is_array($items)) error('参数错误');
$allowedRoles = ['super_admin', 'group_admin', 'store_admin', 'deputy_store_admin', 'operator', 'warehouse'];
$defaults = defaultPermMap();

$pdo->beginTransaction();
try {
    $ins = $pdo->prepare('INSERT INTO role_permissions (role, perm, allowed, updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed), updated_by=VALUES(updated_by)');
    $del = $pdo->prepare('DELETE FROM role_permissions WHERE role=? AND perm=?');
    $n = 0;
    foreach ($items as $it) {
        $role = $it['role'] ?? '';
        $perm = $it['perm'] ?? '';
        if (!in_array($role, $allowedRoles, true) || !isset($defaults[$perm])) continue;
        $allowed = !empty($it['allowed']) ? 1 : 0;
        $defaultAllowed = in_array($role, $defaults[$perm], true) ? 1 : 0;
        if ($allowed === $defaultAllowed) {
            $del->execute([$role, $perm]);
        } else {
            $ins->execute([$role, $perm, $allowed, $_SESSION['username'] ?? null]);
        }
        $n++;
    }
    $pdo->commit();
    success(['message' => '已保存 ' . $n . ' 项权限覆盖']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logError($e->getMessage(), 'role_permission_save');
    error('保存失败: ' . $e->getMessage(), 500);
}
