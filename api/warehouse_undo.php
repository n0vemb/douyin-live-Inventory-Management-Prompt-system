<?php
/**
 * warehouse_undo.php — 撤销已处理（点错可撤回）
 * POST { id }
 * 权限：超管/店管/仓库；运营不可用
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireWarehouseAccess();
$storeId = getStoreId();

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if ($id <= 0) error('缺少任务ID');

$sql = "UPDATE warehouse_task SET status = 'pending', done_at = NULL, done_by = NULL WHERE id = ? AND status = 'done'";
$params = [$id];
if ($storeId) {
    $sql .= " AND store_id = ?";
    $params[] = $storeId;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
if ($stmt->rowCount() === 0) error('任务不存在或未处理');

success(['message' => '已撤销']);
