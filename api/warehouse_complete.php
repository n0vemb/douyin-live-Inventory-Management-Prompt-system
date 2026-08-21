<?php
/**
 * warehouse_complete.php — 仓库处理完成（点"已出库/已回库"）
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

$sql = "UPDATE warehouse_task SET status = 'done', done_at = NOW(), done_by = ? WHERE id = ? AND status = 'pending'";
$params = [$_SESSION['user_id'], $id];
if ($storeId) {
    $sql .= " AND store_id = ?";
    $params[] = $storeId;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
if ($stmt->rowCount() === 0) error('任务不存在或已处理');

success(['message' => '已标记完成']);
