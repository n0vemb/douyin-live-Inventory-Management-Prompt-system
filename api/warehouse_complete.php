<?php
/**
 * warehouse_complete.php — 仓库处理完成（点"已出库/已回库"）
 * POST { id }
 * 权限：超管/店管/仓库；运营不可用
 *
 * 拆件模式：qty>1 的任务在仓库台拆成多张卡片，每点一次处理 1 件
 * （qty 减 1，减到 0 才置为 done）
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
    // 扣减 1 件（仅 pending 且有剩余）
    $sql = "UPDATE warehouse_task SET qty = qty - 1 WHERE id = ? AND status = 'pending' AND qty > 0";
    $params = [$id];
    if ($storeId) {
        $sql .= " AND store_id = ?";
        $params[] = $storeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        error('任务不存在或已处理完');
    }

    // 剩余 0 件 → 整单完成
    $stmt = $pdo->prepare("SELECT qty FROM warehouse_task WHERE id = ?");
    $stmt->execute([$id]);
    $qty = (int)$stmt->fetchColumn();
    if ($qty <= 0) {
        $sql = "UPDATE warehouse_task SET status = 'done', done_at = NOW(), done_by = ? WHERE id = ?";
        $params = [$_SESSION['user_id'], $id];
        if ($storeId) {
            $sql .= " AND store_id = ?";
            $params[] = $storeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    $pdo->commit();
    success(['message' => '已处理 1 件', 'remaining' => $qty]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('处理失败: ' . $e->getMessage());
}
