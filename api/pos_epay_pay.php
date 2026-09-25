<?php
/**
 * pos_epay_pay.php — 收银台「易支付」手机去付款（页面跳转支付）
 *
 * GET ?order_id=..&method=wechat|alipay
 *
 * 顾客用手机打开收银台时，没法扫自己屏幕上的二维码，所以走平台的「页面跳转支付」：
 * 本页把已签名的参数自动 POST 到 {api_url}/submit.php，浏览器随即落到平台收银台，
 * 顾客在那里点「启动微信/支付宝」完成付款；平台收银台自己会轮询订单状态。
 *
 * 与 pos_epay_create.php 的关系：
 *   同一个 out_trade_no（= pos_orders.order_no），所以无论走哪条路，回调都能对上。
 *   ⚠ 本页同样只负责「把顾客送出去」，置已收款只认平台异步回调（epay_notify.php）。
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/epay_lib.php';

$storeId = requirePosStore();
$shopId = posShopId();
$orderId = intval($_GET['order_id'] ?? 0);
$payType = epayPayType(strtolower(trim((string)($_GET['method'] ?? ''))));
if (!$orderId) error('缺少订单ID');
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

$cfg = epayStoreConfig($pdo, $storeId);
if ($cfg['mode'] !== 'epay') {
    // 收款方式在收银台页面加载时就写死了：后台切回静态收款码后，顾客手上这个旧页面还可能点「去付款」。
    // 这里给一句人话（不是 JSON），让他回收银台扫静态码。
    http_response_code(409);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>收款方式已变更</title></head>'
       . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;'
       . 'font-family:-apple-system,BlinkMacSystemFont,\'PingFang SC\',sans-serif;background:#f5f6f8;color:#1c2230">'
       . '<div style="width:100%;max-width:380px;text-align:center;background:#fff;border-radius:16px;padding:26px 22px;'
       . 'box-shadow:0 8px 28px rgba(15,20,40,.08)">'
       . '<div style="font-size:17px;font-weight:800;margin-bottom:8px">本店已改为静态收款码</div>'
       . '<div style="font-size:13.5px;color:#64748b;line-height:1.7">请返回收银台，用微信/支付宝「扫一扫」'
       . '扫描屏幕上的收款码付款。</div></div></body></html>';
    exit;
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

$submitUrl = $cfg['api_url'] . '/submit.php';
epayLog($pdo, $storeId, $outTradeNo, 'out', $submitUrl, null, 1, '页面跳转支付：已生成提交表单', $params);

$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$chanName = $payType === 'alipay' ? '支付宝' : '微信';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>正在前往<?= $esc($chanName) ?>支付…</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
       font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f6f8;color:#1c2230}
  .wrap{width:100%;max-width:380px;background:#fff;border-radius:16px;padding:26px 22px;text-align:center;
        box-shadow:0 8px 28px rgba(15,20,40,.08)}
  .amt{font-size:30px;font-weight:800;color:#e6021f;margin-bottom:6px}
  .tip{font-size:13.5px;color:#64748b;line-height:1.7;margin-bottom:18px}
  .btn{width:100%;border:none;border-radius:12px;padding:15px;font-size:16px;font-weight:800;color:#fff;cursor:pointer;
       background:<?= $payType === 'alipay' ? '#1677FF' : '#07C160' ?>}
  .no{font-size:12px;color:#94a3b8;margin-top:14px;word-break:break-all}
</style>
</head>
<body>
<div class="wrap">
  <div class="amt">¥<?= $esc($money) ?></div>
  <div class="tip">正在打开<?= $esc($chanName) ?>收银台…<br>若没有自动跳转，请点下面的按钮</div>
  <form id="payForm" method="post" action="<?= $esc($submitUrl) ?>">
    <?php foreach ($params as $k => $v): ?>
    <input type="hidden" name="<?= $esc($k) ?>" value="<?= $esc($v) ?>">
    <?php endforeach; ?>
    <button type="submit" class="btn">继续支付 ¥<?= $esc($money) ?></button>
  </form>
  <div class="no">订单号 <?= $esc($outTradeNo) ?></div>
</div>
<script>document.getElementById('payForm').submit();</script>
</body>
</html>
