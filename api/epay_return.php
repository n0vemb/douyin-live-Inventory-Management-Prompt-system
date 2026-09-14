<?php
/**
 * epay_return.php — 易支付同步跳转回调（收银台 + 联调沙箱共用）
 *
 * 顾客在平台收银台付完款，浏览器会被带回本地址（GET，参数同异步通知）。
 * 这里只做验签 + 展示结果，真正的订单状态一律以 notify 为准。
 *   1) 收银台订单 → 只给顾客一句「付款完成，请回收银台」，不暴露内部报文
 *   2) 联调沙箱订单 → 保留原来的调试详情页
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/epay_lib.php';

$params = array_merge($_GET, $_POST);
$pdo = getDB();
$outTradeNo = (string)($params['out_trade_no'] ?? '');
$isSuccess = (($params['trade_status'] ?? '') === 'TRADE_SUCCESS');

// ========== 1) 收银台订单：顾客侧提示页 ==========
$posOrder = null;
if ($outTradeNo !== '') {
    $stmt = $pdo->prepare('SELECT id, store_id, order_no, payable FROM pos_orders WHERE order_no = ? LIMIT 1');
    $stmt->execute([$outTradeNo]);
    $posOrder = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($posOrder) {
    $st = $pdo->prepare('SELECT epay_mch_key FROM stores WHERE id = ?');
    $st->execute([(int)$posOrder['store_id']]);
    $key = trim((string)($st->fetchColumn() ?: ''));
    $ok = ($key !== '' && epayVerify($params, $key));
    epayLog(
        $pdo,
        (int)$posOrder['store_id'],
        $outTradeNo,
        'in',
        'return',
        200,
        $ok ? 1 : 0,
        $ok ? ('同步跳转（' . ($params['trade_status'] ?? '-') . '）') : '同步跳转验签失败',
        $params
    );
    epayReturnPosPage($posOrder, $isSuccess, $ok);
    exit;
}

// ========== 2) 联调沙箱 ==========
$cfg = ['mch_key' => '', 'pid' => ''];
try {
    $cfg = epayTestConfig($pdo);
} catch (Exception $e) {
    // 生产库没有沙箱表，忽略
}
$verified = ($cfg['mch_key'] !== '' && epayVerify($params, $cfg['mch_key']));

$order = null;
if ($outTradeNo !== '') {
    try {
        $stmt = $pdo->prepare('SELECT * FROM epay_test_orders WHERE out_trade_no = ?');
        $stmt->execute([$outTradeNo]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $order = null;
    }
}
epayTestLog($pdo, $outTradeNo, 'in', 'return', 200, $verified ? 1 : 0, $verified ? '同步跳转（' . ($params['trade_status'] ?? '-') . '）' : '同步跳转验签失败', $params);

$esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>支付结果 - 易支付联调</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{margin:0;padding:28px 16px;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f6f8;color:#1c2230}
  .wrap{max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:22px 20px;box-shadow:0 6px 24px rgba(15,20,40,.08)}
  h1{font-size:19px;margin:0 0 6px}
  .ok{color:#2fa375}.bad{color:#dc2626}
  .sub{font-size:13px;color:#64748b;margin-bottom:16px;line-height:1.6}
  table{width:100%;border-collapse:collapse;font-size:13px;margin-top:6px}
  td{padding:7px 0;border-bottom:1px solid #eef1f5;vertical-align:top}
  td.k{color:#64748b;width:130px;white-space:nowrap}
  .raw{margin-top:14px;background:#f7f8fa;border-radius:8px;padding:10px 12px;font-size:12px;line-height:1.6;color:#475569;word-break:break-all;white-space:pre-wrap}
  .btn{display:inline-block;margin-top:16px;padding:10px 18px;border-radius:9px;background:#e6021f;color:#fff;text-decoration:none;font-size:14px;font-weight:700}
  .note{margin-top:14px;font-size:12px;color:#94a3b8;line-height:1.6}
</style>
</head>
<body>
<div class="wrap">
  <h1 class="<?= $isSuccess ? 'ok' : 'bad' ?>"><?= $isSuccess ? '支付成功' : '支付未完成或状态异常' ?></h1>
  <div class="sub">同步跳转结果仅供参考，订单状态以服务器异步通知（notify）为准。</div>
  <table>
    <tr><td class="k">验签</td><td><?= $verified ? '<span class="ok">通过</span>' : '<span class="bad">失败</span>' ?></td></tr>
    <tr><td class="k">trade_status</td><td><?= $esc($params['trade_status'] ?? '-') ?></td></tr>
    <tr><td class="k">商户订单号</td><td><?= $esc($outTradeNo ?: '-') ?></td></tr>
    <tr><td class="k">平台订单号</td><td><?= $esc($params['trade_no'] ?? '-') ?></td></tr>
    <tr><td class="k">金额</td><td><?= $esc($params['money'] ?? '-') ?></td></tr>
    <tr><td class="k">本地订单状态</td><td><?= $order ? $esc($order['status']) . '（回调 ' . (int)$order['notify_count'] . ' 次）' : '本地未找到该订单' ?></td></tr>
  </table>
  <div class="raw"><?= $esc(json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) ?></div>
  <a class="btn" href="//<?= $esc($_SERVER['HTTP_HOST'] ?? '') ?>/admin/epay_test.php">返回联调页</a>
  <div class="note">若这里显示「已支付」但联调页仍是待支付，说明异步通知没进来，回去看联调页的回调日志。</div>
</div>
</body>
</html>

<?php
/**
 * 收银台订单的顾客侧提示页：只说结论，不暴露报文与订单内部字段。
 */
function epayReturnPosPage(array $order, $isSuccess, $verified) {
    $esc = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $title = $isSuccess ? '付款完成' : '付款未完成';
    $tip = $isSuccess
        ? '请返回收银台，把订单号告诉工作人员即可配货。'
        : '这笔付款还没完成，请返回收银台重新扫码。';
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title><?= $esc($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;
       font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f5f6f8;color:#1c2230}
  .wrap{width:100%;max-width:420px;background:#fff;border-radius:16px;padding:26px 22px;text-align:center;
        box-shadow:0 8px 28px rgba(15,20,40,.08)}
  .ico{width:60px;height:60px;border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;
       font-size:30px;color:#fff;background:<?= $isSuccess ? '#2fa375' : '#dc2626' ?>}
  h1{font-size:20px;margin:0 0 8px}
  .tip{font-size:14px;color:#64748b;line-height:1.7;margin-bottom:16px}
  .amt{font-size:26px;font-weight:800;color:#e6021f;margin:6px 0 2px}
  .no{font-size:13px;color:#94a3b8;word-break:break-all}
  <?php if (!$verified): ?>
  .warn{margin-top:14px;font-size:12px;color:#b45309;background:#fffbeb;border-radius:8px;padding:9px 11px;line-height:1.6}
  <?php endif; ?>
</style>
</head>
<body>
<div class="wrap">
  <div class="ico"><?= $isSuccess ? '&#10003;' : '!' ?></div>
  <h1><?= $esc($title) ?></h1>
  <div class="tip"><?= $esc($tip) ?></div>
  <div class="amt">¥<?= $esc(number_format((float)$order['payable'], 2, '.', '')) ?></div>
  <div class="no">订单号 <?= $esc($order['order_no']) ?></div>
  <?php if (!$verified): ?>
  <div class="warn">本页未能校验平台签名，最终以收银台显示的收款状态为准。</div>
  <?php endif; ?>
</div>
</body>
</html>
    <?php
}
