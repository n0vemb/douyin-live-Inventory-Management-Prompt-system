<?php
/**
 * warehouse_undo.php — 撤销已处理（点错可撤回）
 * POST { id }
 * 权限：超管/店管/仓库；运营不可用
 *
 * 拆件模式：撤销恢复 1 件（qty + 1，状态回 pending；若此前已 done 则整单恢复 pending）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireWarehouseAccess();
$storeId = getStoreId();

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
if ($id <= 0) error('缺少任务ID');

$pdo->beginTransaction();
try {
    $sql = "UPDATE warehouse_task SET qty = qty + 1, status = 'pending', done_at = NULL, done_by = NULL WHERE id = ? AND status = 'done'";
    $params = [$id];
    if ($storeId) {
        $sql .= " AND store_id = ?";
        $params[] = $storeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        // 不是 done 状态：可能该单尚未全部处理完（仍是 pending），也允许撤销已处理的部分？不允许，保持原语义
        $pdo->rollBack();
        error('任务不存在或未处理');
    }

    $stmt = $pdo->prepare("SELECT qty FROM warehouse_task WHERE id = ?");
    $stmt->execute([$id]);
    $qty = (int)$stmt->fetchColumn();

    $pdo->commit();
    success(['message' => '已撤销 1 件', 'remaining' => $qty]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('撤销失败: ' . $e->getMessage());
}
