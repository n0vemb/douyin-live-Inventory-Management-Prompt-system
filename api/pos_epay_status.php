<?php
/**
 * pos_epay_status.php — 收银台轮询「这笔单收款了没」（易支付模式）
 * GET ?order_id=
 *
 * 只读：真正确认收款的是平台异步回调（epay_notify.php），本接口只给前端轮询用。
 */
require_once __DIR__ . '/pos_auth.php';

$storeId = requirePosStore();
$shopId = posShopId();
$orderId = intval($_GET['order_id'] ?? 0);
if (!$orderId) error('缺少订单ID');

$pdo = getDB();
$stmt = $pdo->prepare(
    'SELECT pay_status, paid_at, pay_trade_no, pay_channel, outbound_status, payable
     FROM pos_orders WHERE id = ? AND store_id = ?' . ($shopId ? ' AND shop_id = ?' : '')
);
$stmt->execute($shopId ? [$orderId, $storeId, $shopId] : [$orderId, $storeId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) error('订单不存在');

success([
    'paid'      => $order['pay_status'] === 'paid',
    'released'  => $order['outbound_status'] !== 'pending',
    'trade_no'  => (string)($order['pay_trade_no'] ?? ''),
    'channel'   => (string)($order['pay_channel'] ?? ''),
    'paid_at'   => $order['paid_at'] ?? null,
]);
