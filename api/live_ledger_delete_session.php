<?php
/**
 * live_ledger_delete_session.php — 删除场次（级联删除该场次的全部记账数据）
 * POST { session_id }
 *
 * 注意：只删除 live_ledger_* 记账数据；
 * 已结束场次产生的 outbound_log / sales_log 属于真实出库记录，不随场次删除。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireNonOperator(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
if ($sessionId <= 0) error('缺少场次ID');

// 校验场次存在且属于本店铺
$stmt = $pdo->prepare("SELECT id, session_name FROM live_ledger_session WHERE id = ? AND store_id = ?");
$stmt->execute([$sessionId, $storeId]);
$session = $stmt->fetch();
if (!$session) error('场次不存在');

$pdo->beginTransaction();
try {
    // 级联删除：先子表后父表
    $stmt = $pdo->prepare("DELETE FROM live_ledger_item WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $stmt = $pdo->prepare("DELETE FROM live_ledger_gift WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $stmt = $pdo->prepare("DELETE FROM live_ledger_customer WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $stmt = $pdo->prepare("DELETE FROM live_ledger_outbound WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $stmt = $pdo->prepare("DELETE FROM live_ledger_session WHERE id = ?");
    $stmt->execute([$sessionId]);

    $pdo->commit();
    success(['message' => '场次已删除', 'data' => ['session_id' => $sessionId]]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('删除失败: ' . $e->getMessage());
}
