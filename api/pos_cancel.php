<?php
/**
 * pos_cancel.php — 收银台取消未付款订单（免登录，纯自助）
 * 顾客结算弹收款码后中途取消 → 释放锁定库存 + 删除订单（不进入门店待出库）
 * 仅允许取消：本店 + pay_status=pending + outbound_status=pending
 */
require_once __DIR__ . '/pos_auth.php';
$storeId = requirePosStore();
$input = json_decode(file_get_contents('php://input'), true);
$orderId = intval($input['order_id'] ?? 0);
if (!$orderId) error('缺少订单ID');
$pdo = getDB();

try {
    $orderStmt = $pdo->prepare('SELECT * FROM pos_orders WHERE id = ?');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order) error('订单不存在');
    if ((int)$order['store_id'] !== $storeId) error('订单不属于当前店铺', 403);
    if ($order['pay_status'] !== 'pending') error('订单已收款，不可取消');
    if ($order['outbound_status'] !== 'pending') error('订单状态不允许取消');

    $pdo->beginTransaction();
    try {
        // 释放锁定库存
        $lockStmt = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
        $lockStmt->execute([$orderId, 'locked']);
        $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
        foreach ($lockStmt->fetchAll() as $lk) {
            $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
        }
        // 删除订单（明细/locks 级联）
        $pdo->prepare('DELETE FROM pos_order_items WHERE order_id = ?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM pos_order_locks WHERE order_id = ?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM pos_orders WHERE id = ?')->execute([$orderId]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    success(['cancelled' => true, 'order_id' => $orderId]);
} catch (Exception $e) {
    error($e->getMessage());
}
