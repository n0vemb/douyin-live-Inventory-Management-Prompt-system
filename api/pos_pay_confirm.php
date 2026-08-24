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

try {
    $stmt = $pdo->prepare('SELECT * FROM pos_orders WHERE id = ? AND store_id = ?');
    $stmt->execute([$orderId, $storeId]);
    $order = $stmt->fetch();
    if (!$order) error('订单不存在');
    if ($order['outbound_status'] !== 'pending') error('仅待出库订单可确认收款');
    if ($order['pay_status'] === 'paid') error('订单已收款');

    $pdo->prepare("UPDATE pos_orders SET pay_status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$orderId]);
    success(['pay_status' => 'paid']);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_pay_confirm');
    error('确认收款失败: ' . $e->getMessage(), 500);
}
