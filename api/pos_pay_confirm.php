<?php
/**
 * pos_pay_confirm.php — 扫码收款确认（免登录，纯自助）
 * 顾客扫码付款后点「已付款」→ pay_status=paid（一期自助模式，无需店员模式）
 */
require_once __DIR__ . '/pos_auth.php';
$storeId = requirePosStore();
$input = json_decode(file_get_contents('php://input'), true);
$orderId = intval($input['order_id'] ?? 0);
if (!$orderId) error('缺少订单ID');
$pdo = getDB();

// 与 15 分钟自动释放 / 取消互斥，防止“付款”与“释放/作废”竞争
$lockName = 'pp_pos_auto_release_' . (int)$storeId;
$pdo->query('SELECT GET_LOCK(' . $pdo->quote($lockName) . ', 5)');

try {
    $stmt = $pdo->prepare('SELECT * FROM pos_orders WHERE id = ? AND store_id = ?');
    $stmt->execute([$orderId, $storeId]);
    $order = $stmt->fetch();
    if (!$order) error('订单不存在');
    if ($order['outbound_status'] !== 'pending') error('仅待出库订单可确认收款');
    if ($order['pay_status'] === 'paid') error('订单已收款');

    // 条件更新：若订单已被 15 分钟自动释放/店员取消/作废，则不应再标记收款
    $upd = $pdo->prepare(
        "UPDATE pos_orders SET pay_status = 'paid', paid_at = NOW()
         WHERE id = ? AND store_id = ? AND outbound_status = 'pending' AND pay_status = 'pending'"
    );
    $upd->execute([$orderId, $storeId]);
    if ($upd->rowCount() === 0) error('订单已超时释放或已取消，请重新下单');
    $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')');
    success(['pay_status' => 'paid']);
} catch (Exception $e) {
    try { $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')'); } catch (Exception $ignore) {}
    logError($e->getMessage(), 'pos_pay_confirm');
    error('确认收款失败: ' . $e->getMessage(), 500);
}
