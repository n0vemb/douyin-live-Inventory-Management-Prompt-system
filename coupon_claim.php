<?php
/**
 * coupon_claim.php — 优惠券活动页（公开访问）
 * 用法：/coupon_claim.php?token=xxxxxx
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/coupon_lib.php';
$token = trim((string)($_GET['token'] ?? ''));
$pdo = getDB();
$camp = null;
$campError = '';
if ($token === '') {
    $campError = '缺少活动参数';
} else {
    try {
        $stmt = $pdo->prepare('SELECT * FROM coupon_campaigns WHERE claim_token = ?');
        $stmt->execute([$token]);
        $camp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$camp) $campError = '活动不存在或链接已失效';
    } catch (Exception $e) {
        $campError = '活动加载失败';
    }
}
$active = $camp && couponCampaignActive($camp);
$issued = $camp ? couponIssuedCount($pdo, (int)$camp['id']) : 0;
$remain = $camp && (int)$camp['total_count'] > 0 ? max(0, (int)$camp['total_count'] - $issued) : null;
$done = (int)($_GET['ok'] ?? 0);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?= $camp ? htmlspecialchars($camp['name']) : '优惠券活动' ?></title>
<style>
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;
       background:linear-gradient(160deg,#ffe3ec,#fff7f0 45%,#fff);color:#2b2230;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:18px}
  .card{width:min(420px,100%);background:#fff;border-radius:22px;box-shadow:0 18px 44px rgba(230,2,31,.12);padding:30px 24px;text-align:center}
  .ticket{background:linear-gradient(135deg,#ff5f8f,#ff8a6b);color:#fff;border-radius:16px;padding:22px 12px;margin-bottom:18px}
  .ticket .amt{font-size:44px;font-weight:900;line-height:1}
  .ticket .amt small{font-size:20px;font-weight:700}
  .ticket .nm{font-size:16px;font-weight:800;margin-top:8px}
  .ticket .cn{font-size:13px;opacity:.9;margin-top:6px}
  h2{margin:0 0 6px}
  .tip{font-size:13px;color:#7a6b75;line-height:1.7;margin-bottom:16px}
  input{width:100%;padding:14px;border:1px solid #f0d3da;border-radius:12px;font-size:17px;letter-spacing:1px;text-align:center;outline:none;background:#fff7f9}
  input:focus{border-color:#e6021f}
  button{width:100%;margin-top:14px;border:none;background:#e6021f;color:#fff;font-size:17px;font-weight:800;padding:15px;border-radius:12px;cursor:pointer}
  button:disabled{opacity:.5}
  .err{color:#d32f2f;font-size:13px;margin-top:10px;display:none}
  .ok{color:#1a9c6b;font-size:14px;font-weight:700;margin-top:12px}
  .meta{font-size:12px;color:#b6a7b1;margin-top:14px;line-height:1.6}
</style>
</head>
<body>
<div class="card">
  <?php if (!$camp): ?>
    <h2>优惠券活动</h2>
    <div class="tip" style="color:#d32f2f"><?= htmlspecialchars($campError) ?></div>
  <?php elseif ($done): ?>
    <h2>🎉 领取成功</h2>
    <div class="ok">到店结账时，收银台输入手机号即可使用</div>
    <div class="meta">本活动券请留意有效期，过期自动失效。</div>
  <?php elseif (!$active): ?>
    <h2>活动未开始或已结束</h2>
    <div class="meta">本券有效期至 <?= htmlspecialchars($camp['end_at'] ?: '长期') ?></div>
  <?php else: ?>
    <div class="ticket">
      <div class="amt">¥<?= number_format((float)$camp['amount'], 2) ?></div>
      <div class="nm"><?= htmlspecialchars($camp['name']) ?></div>
      <div class="cn"><?= $camp['coupon_type'] === 'threshold' && (float)$camp['threshold'] > 0
        ? '满 ¥' . number_format((float)$camp['threshold'], 2) . ' 可用'
        : '无门槛使用' ?> · 有效期至 <?= htmlspecialchars($camp['end_at'] ?: '长期') ?></div>
    </div>
    <h2>领取优惠券</h2>
    <div class="tip">请输入领券手机号，结账时报同一手机号即可核销。</div>
    <input type="tel" id="phone" maxlength="11" placeholder="请输入 11 位手机号" autocomplete="tel">
    <div class="err" id="err"></div>
    <button id="btn" onclick="doClaim()">立即领取</button>
    <div class="meta"><?= $remain !== null ? '剩余 ' . $remain . ' 张' : '数量充足' ?> · 每号限领 <?= (int)$camp['per_user'] ?> 张</div>
  <?php endif; ?>
</div>
<script>
const TOKEN = <?= json_encode($token) ?>;
async function doClaim(){
  const phone=document.getElementById('phone').value.trim();
  const err=document.getElementById('err'); err.style.display='none';
  if(!/^1[3-9]\d{9}$/.test(phone)){ err.textContent='请输入正确的 11 位手机号'; err.style.display='block'; return; }
  const btn=document.getElementById('btn'); btn.disabled=true; btn.textContent='领取中…';
  try{
    const res=await fetch('api/coupon_claim.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:TOKEN,phone})});
    const d=await res.json();
    if(!d.success){ err.textContent=d.error||'领取失败'; err.style.display='block'; btn.disabled=false; btn.textContent='立即领取'; return; }
    location.href='coupon_claim.php?token='+encodeURIComponent(TOKEN)+'&ok=1';
  }catch(e){ err.textContent='网络异常，请重试'; err.style.display='block'; btn.disabled=false; btn.textContent='立即领取'; }
}
</script>
</body>
</html>
