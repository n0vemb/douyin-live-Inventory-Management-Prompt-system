<?php
/**
 * delete_shop.php — 删除空店（删除前校验无账号/无业务数据）
 * POST { shop_id }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$isSuper = isSuperAdmin();
$isGroup = isGroupAdmin();
if (!$isSuper && !$isGroup) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => '权限不足']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$shopId = (int)($input['shop_id'] ?? 0);
if ($shopId <= 0) {
    error('请提供店铺ID');
}

$stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ?');
$stmt->execute([$shopId]);
$shop = $stmt->fetch();
if (!$shop) {
    error('店铺不存在');
}
if (!$isSuper && (int)$shop['store_id'] !== (int)getStoreId()) {
    error('无权删除该集团下的店铺');
}

// 逐个校验店级数据（有任一项即拒绝，提示先迁移/删除数据）
$storeId = (int)$shop['store_id'];
$checks = [
    '账号'          => ['users', 'shop_id'],
    '直播场次'       => ['live_sessions', 'shop_id'],
    '直播台账场次'    => ['live_ledger_session', 'shop_id'],
    '销售记录'       => ['sales_log', 'shop_id'],
    '出库明细'       => ['outbound_log', 'shop_id'],
    '出库财务'       => ['outbound_finance', 'shop_id'],
    '仓库任务'       => ['warehouse_task', 'shop_id'],
    '待办'          => ['todo_items', 'shop_id'],
];
foreach ($checks as $label => [$table, $col]) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = ?");
    $stmt->execute([$shopId]);
    if ((int)$stmt->fetchColumn() > 0) {
        error("店铺「{$shop['name']}」下仍有{$label}数据，不能删除；请先迁移或清理");
    }
}

$stmt = $pdo->prepare('DELETE FROM shops WHERE id = ?');
$stmt->execute([$shopId]);

success(['message' => "店铺「{$shop['name']}」已删除"]);
