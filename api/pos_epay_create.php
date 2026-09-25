<?php
/**
 * pos_epay_create.php — 收银台「易支付」动态收款码（店铺 pos_pay_mode=epay 时使用）
 *
 * POST { order_id, method: 'wechat'|'alipay' }
 *
 * 收银台已经落单（pos_orders.pay_status=pending），这里只是向易支付要一个收款码：
 *   out_trade_no 直接用 pos_orders.order_no（唯一，天然就是幂等键）
 *
 * 幂等：同一订单重复调用直接返回已生成的支付串，不再向平台下单。
 *   平台下单接口偶发 15~30s 无响应，而「超时」不代表平台没建单；
 *   若超时后换新订单号重开，晚到的回调就会对不上本地订单，所以必须沿用同一个单号。
 *
 * ⚠ 本接口只负责「出码」，绝不置已收款。收款一律以平台异步回调（epay_notify.php）为准。
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/epay_lib.php';

$storeId = requirePosStore();
$shopId = posShopId();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId = intval($input['order_id'] ?? 0);
if (!$orderId) error('缺少订单ID');

$payType = epayPayType(strtolower(trim((string)($input['method'] ?? ''))));
if ($payType === '') error('支付方式无效');

$pdo = getDB();
$stmt = $pdo->prepare(
    'SELECT * FROM pos_orders WHERE id = ? AND store_id = ?' . ($shopId ? ' AND shop_id = ?' : '')
);
$stmt->execute($shopId ? [$orderId, $storeId, $shopId] : [$orderId, $storeId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) error('订单不存在');
if ($order['pay_status'] === 'paid') error('订单已收款');
if ($order['outbound_status'] !== 'pending') error('订单已超时释放或已取消，请重新下单');

// 已出过码：直接复用（刷新页面/重复点击不重复下单）
$existingQr = trim((string)($order['epay_qr'] ?? ''));
if ($existingQr !== '') {
    success([
        'qrcode'    => $existingQr,
        'qr_image'  => epayQrImageUrl($existingQr),
        'trade_no'  => (string)($order['pay_trade_no'] ?? ''),
        'channel'   => (string)($order['pay_channel'] ?? ''),
        'reused'    => true,
    ]);
}

$cfg = epayStoreConfig($pdo, $storeId);
if ($cfg['mode'] !== 'epay') {
    // 收款方式在收银台页面加载时就写进了 HTML，后台切回「静态收款码」后旧页面仍会来要易支付码。
    // 这里把静态码一起回传（mode=static），收银台据此就地切成静态码流程，而不是让顾客卡在「平台未响应」。
    $qr = epayStaticQrUrls($pdo, $storeId, $shopId);
    jsonResponse([
        'success' => false,
        'error'   => '本店收款方式为静态收款码，未启用易支付',
        'mode'    => 'static',
        'qr_wx'   => $qr['wx'],
        'qr_ali'  => $qr['ali'],
    ]);
}
if (!$cfg['ready']) error('本店已启用易支付但未配置商户ID/密钥，请联系管理员');

$outTradeNo = (string)$order['order_no'];
$money = number_format((float)$order['payable'], 2, '.', '');
if ((float)$money <= 0) error('订单金额异常，无法发起支付');

$name = trim((string)$cfg['store_name']);
if ($name === '') $name = '门店收款';
$name = mb_substr($name, 0, 40);

$params = [
    'pid'          => (string)$cfg['pid'],
    'type'         => $payType,
    'out_trade_no' => $outTradeNo,
    'notify_url'   => epaySelfUrl('api/epay_notify.php'),
    'return_url'   => epaySelfUrl('api/epay_return.php'),
    'name'         => $name,
    'money'        => epayMoney($money),
];
$params['sign'] = epaySign($params, $cfg['mch_key']);
$params['sign_type'] = 'MD5';

$endpoint = $cfg['api_url'] . '/mapi.php';
// 平台下单偶发长时间无响应，给足超时；失败由收银台点「重试」（同一个单号，幂等）
set_time_limit(60);
list($httpCode, $body, $curlErr) = epayHttpPost($endpoint, $params, 25);
$json = json_decode((string)$body, true);

if ($curlErr !== '' || !epayRespOk($json)) {
    $note = $curlErr !== '' ? ('请求失败：' . $curlErr) : ('平台返回：' . mb_substr((string)$body, 0, 200));
    epayLog($pdo, $storeId, $outTradeNo, 'out', $endpoint, $httpCode, 0, $note, ['request' => $params, 'response' => $body]);
    error('收款码生成失败：' . ($curlErr !== '' ? '平台无响应，请点重试' : (string)($json['msg'] ?? '平台繁忙，请点重试')));
}

$qrcode = trim((string)($json['qrcode'] ?? ''));
$codeUrl = trim((string)($json['code_url'] ?? ''));
if ($qrcode === '' && $codeUrl === '') {
    epayLog($pdo, $storeId, $outTradeNo, 'out', $endpoint, $httpCode, 0, '平台未返回二维码', $body);
    error('平台未返回收款码，请重试');
}

$tradeNo = mb_substr(trim((string)($json['trade_no'] ?? '')), 0, 64);
$storedQr = $qrcode !== '' ? $qrcode : $codeUrl;
// 只在该单仍待收款时写入，避免与超时释放/取消竞争
$upd = $pdo->prepare(
    "UPDATE pos_orders SET pay_channel = ?, pay_trade_no = ?, epay_qr = ?
      WHERE id = ? AND store_id = ? AND pay_status = 'pending' AND outbound_status = 'pending'"
);
$upd->execute([$payType === 'alipay' ? 'alipay' : 'wechat', $tradeNo !== '' ? $tradeNo : null, mb_substr($storedQr, 0, 500), $orderId, $storeId]);
if ($upd->rowCount() === 0) {
    epayLog($pdo, $storeId, $outTradeNo, 'out', $endpoint, $httpCode, 0, '收款码生成后订单状态已变更（释放/取消），未写入', $body);
    error('订单已超时释放或已取消，请重新下单');
}

epayLog($pdo, $storeId, $outTradeNo, 'out', $endpoint, $httpCode, 1, '获取成功', $body);
    success([
        'qrcode'   => $qrcode,
        'code_url' => $codeUrl,
        // 微信通道只有 wxp:// 码串：服务端落一张真图，微信长按才有「识别图中二维码」
        'qr_image' => epayQrImageUrl($storedQr),
        'trade_no' => $tradeNo,
        'channel'  => $payType === 'alipay' ? 'alipay' : 'wechat',
    ]);
