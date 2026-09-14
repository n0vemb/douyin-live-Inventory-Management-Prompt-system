<?php
/**
 * epay_notify.php — 易支付异步回调（收银台正式功能 + 联调沙箱共用）
 *
 * 平台以 GET 方式回调本地址；验签通过且 trade_status=TRADE_SUCCESS 才置为已支付。
 * 按 out_trade_no 自动分流，两者互不影响：
 *   1) 收银台订单 pos_orders.order_no  → 置「已收款」+ 核销占用中的券，日志写 epay_logs
 *   2) 联调沙箱订单 epay_test_orders    → 沿用原联调逻辑，日志写 epay_test_logs
 * 幂等：以 out_trade_no 为键，重复回调只累加次数、不回退状态。
 * 必须输出纯文本 success，否则平台会重复通知。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/epay_lib.php';

header('Content-Type: text/plain; charset=utf-8');

$params = array_merge($_GET, $_POST);
$pdo = getDB();
$outTradeNo = (string)($params['out_trade_no'] ?? '');
$tradeStatus = (string)($params['trade_status'] ?? '');
$money = (string)($params['money'] ?? '');

// ========== 1) 收银台订单（正式功能）==========
$posOrder = null;
if ($outTradeNo !== '') {
    $stmt = $pdo->prepare('SELECT * FROM pos_orders WHERE order_no = ? LIMIT 1');
    $stmt->execute([$outTradeNo]);
    $posOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($posOrder) {
    echo epayHandlePosNotify($pdo, $posOrder, $params, $tradeStatus, $money, $outTradeNo);
    exit;
}

// ========== 2) 联调沙箱（仅测试站有 epay_test_* 表）==========
if (!epaySandboxReady($pdo)) {
    epayLog($pdo, null, $outTradeNo, 'in', 'notify', 200, 0, '本地无此订单号（无沙箱表）', $params);
    echo 'success';
    exit;
}

$cfg = epayTestConfig($pdo);
if (!$cfg['mch_key'] || !$cfg['pid']) {
    epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 0, '未配置商户信息，忽略回调', $params);
    echo 'error';
    exit;
}
// 1) 验签
if (!epayVerify($params, $cfg['mch_key'])) {
    epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 0, '验签失败', $params);
    error_log('[epay] notify 验签失败: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
    echo 'error';
    exit;
}
// 2) 商户号必须一致
if ((string)($params['pid'] ?? '') !== (string)$cfg['pid']) {
    epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 0, 'pid 不匹配', $params);
    echo 'error';
    exit;
}
// 3) 找单
$stmt = $pdo->prepare('SELECT * FROM epay_test_orders WHERE out_trade_no = ?');
$stmt->execute([$outTradeNo]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 0, '本地无此订单号', $params);
    echo 'error';
    exit;
}

$note = '';
$ok = true;
if ($tradeStatus === 'TRADE_SUCCESS') {
    $mismatch = abs((float)$money - (float)$order['money']) > 0.001;
    if ($mismatch) {
        // 平台对极小金额会做微调，这里不拦单，但记下来备查
        $note = '金额不一致：下单 ¥' . $order['money'] . ' 实付 ¥' . $money;
    }
    if ($order['status'] === 'paid') {
        $pdo->prepare('UPDATE epay_test_orders SET notify_count = notify_count + 1 WHERE id = ?')->execute([(int)$order['id']]);
        epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 1, '重复回调（已支付，忽略）' . ($note ? '；' . $note : ''), $params);
        echo 'success';
        exit;
    }
    $upd = $pdo->prepare(
        "UPDATE epay_test_orders
            SET status = 'paid', trade_no = ?, trade_status = ?, notify_money = ?, paid_at = NOW(),
                notify_count = notify_count + 1, remark = ?
          WHERE id = ? AND status <> 'paid'"
    );
    $upd->execute([
        substr((string)($params['trade_no'] ?? ''), 0, 64),
        substr($tradeStatus, 0, 32),
        $money !== '' ? round((float)$money, 2) : null,
        $note !== '' ? substr($note, 0, 255) : null,
        (int)$order['id'],
    ]);
    epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 1, '支付成功' . ($note ? '；' . $note : ''), $params);
} else {
    $ok = false;
    $pdo->prepare('UPDATE epay_test_orders SET notify_count = notify_count + 1, trade_status = ? WHERE id = ?')
        ->execute([substr($tradeStatus, 0, 32), (int)$order['id']]);
    epayTestLog($pdo, $outTradeNo, 'in', 'notify', 200, 0, '非成功状态：' . $tradeStatus, $params);
}

// 幂等/非成功都要回 success，避免平台无限重推；真正的异常靠日志排查
echo $tradeStatus === 'TRADE_SUCCESS' || !$ok ? 'success' : 'error';

/**
 * 收银台订单回调：验签 → 幂等置已收款 → 核销券。返回给平台的纯文本。
 */
function epayHandlePosNotify(PDO $pdo, array $order, array $params, $tradeStatus, $money, $outTradeNo) {
    $storeId = (int)$order['store_id'];
    $orderId = (int)$order['id'];
    $stmt = $pdo->prepare('SELECT epay_pid, epay_mch_key FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $key = trim((string)($store['epay_mch_key'] ?? ''));

    if ($key === '') {
        epayLog($pdo, $storeId, $outTradeNo, 'in', 'notify', 200, 0, '本店未配置易支付密钥，忽略回调', $params);
        return 'error';
    }
    if ((string)($params['pid'] ?? '') !== (string)($store['epay_pid'] ?? '')) {
        epayLog($pdo, $storeId, $outTradeNo, 'in', 'notify', 200, 0, 'pid 不匹配', $params);
        return 'error';
    }
    if (!epayVerify($params, $key)) {
        epayLog($pdo, $storeId, $outTradeNo, 'in', 'notify', 200, 0, '验签失败', $params);
        error_log('[epay] 收银台回调验签失败: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        return 'error';
    }
    if ($tradeStatus !== 'TRADE_SUCCESS') {
        epayLog($pdo, $storeId, $outTradeNo, 'in', 'notify', 200, 0, '非成功状态：' . $tradeStatus, $params);
        return 'success';
    }
    if ($order['pay_status'] === 'paid') {
        epayLog($pdo, $storeId, $outTradeNo, 'in', 'notify', 200, 1, '重复回调（已收款，忽略）', $params);
        return 'success';
    }

    $note = '';
    if (abs((float)$money - (float)$order['payable']) > 0.01) {
        $note = '；金额不一致：应付 ¥' . $order['payable'] . ' 实付 ¥' . $money;
    }
    $tradeNo = mb_substr(trim((string)($params['trade_no'] ?? '')), 0, 64);
    $payType = epayPayType((string)($params['type'] ?? ''));
    $channel = $payType === 'alipay' ? 'alipay' : ($payType === 'wxpay' ? 'wechat' : null);

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare(
            "UPDATE pos_orders
                SET pay_status = 'paid', paid_at = NOW(),
                    pay_trade_no = COALESCE(NULLIF(?, ''), pay_trade_no),
                    pay_channel  = COALESCE(NULLIF(?, ''), pay_channel)
              WHERE id = ? AND store_id = ? AND pay_status = 'pending'"
        );
        $upd->execute([$tradeNo, $channel, $orderId, $storeId]);
        $changed = $upd->rowCount() > 0;
        if ($changed) {
            // 该单已超时释放但钱到了：标记出来等人工处理（仍是 voided，不会进待出库）
            if ($order['outbound_status'] !== 'pending') {
                $pdo->prepare("UPDATE pos_orders SET void_reason = CONCAT('【已付款待人工处理】', COALESCE(void_reason, '')) WHERE id = ?")
                    ->execute([$orderId]);
            }
            require_once __DIR__ . '/coupon_lib.php';
            couponSetClaimsByOrder($pdo, $orderId, 'used');
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        epayLog($pdo, $storeId, $outTradeNo, 'in', 'notify', 200, 0, '置已收款失败：' . $e->getMessage(), $params);
        return 'error';
    }

    epayLog(
        $pdo,
        $storeId,
        $outTradeNo,
        'in',
        'notify',
        200,
        $changed ? 1 : 0,
        ($changed ? '收款成功' : '状态未变更（订单已非待收款）') . $note,
        $params
    );
    return 'success';
}

/** 测试站是否装了联调沙箱表（生产没有这两张表） */
function epaySandboxReady(PDO $pdo) {
    try {
        $pdo->query('SELECT 1 FROM epay_test_orders LIMIT 1');
        return true;
    } catch (Exception $e) {
        return false;
    }
}
