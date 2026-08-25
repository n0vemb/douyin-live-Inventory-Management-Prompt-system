<?php
/**
 * admin/pos.php — 线下收银台（免登录，URL 带店铺 token）
 * 访问：/admin/pos.php?t={pos_token}（店铺设置中查看/复制）
 * 顾客自助看图下单：品牌/IP → 系列 → 商品 → 品相 → 购物车
 * 结算：选微信/支付宝 → 弹店铺收款码 → 顾客扫码付款 → 点「已付款」→ 订单进后台待出库
 * 一期纯自助：无店员模式、无折扣、无改价
 */
require_once __DIR__ . '/../api/pos_auth.php';
$storeId = posStoreId();
if (!$storeId) {
    http_response_code(401);
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>收银台</title></head>
<body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#fdf3f6;color:#7a6b75">
<div style="text-align:center"><h2 style="color:#2b2230">收银台链接无效</h2>
<p>请从后台「店铺设置 → 线下收银台」获取正确的访问链接</p></div></body></html>';
    exit;
}
$pdo = getDB();
$stmt = $pdo->prepare('SELECT name, offline_pay_qr_wx, offline_pay_qr_ali FROM stores WHERE id = ?');
$stmt->execute([$storeId]);
$storeRow = $stmt->fetch();
$storeName = $storeRow['name'] ?? '线下收银台';
$qrWx = $storeRow['offline_pay_qr_wx'] ?? '';
$qrAli = $storeRow['offline_pay_qr_ali'] ?? '';
// 补全相对路径为完整 URL（库中存 uploads/... 相对路径；页面在 admin/ 下需 ../ 前缀）
function posAssetUrl($path) {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
$qrWx = posAssetUrl($qrWx);
$qrAli = posAssetUrl($qrAli);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?= htmlspecialchars($storeName) ?> · 收银台</title>
<style>
  :root{
    --bg:#fdf5f5; --surface:#ffffff; --surface-2:#fff0f1; --border:#f5d9dc;
    --text:#2b2230; --text-2:#7a6b75; --text-3:#b6a7b1;
    --primary:#e6021f; --primary-d:#c40119; --primary-soft:#fde3e6;
    --ok:#22b07d; --warn:#ff9f43; --danger:#ff5a5f;
    --shadow:0 8px 24px rgba(255,92,138,.14);
  }
  *{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
  html,body{margin:0;height:100%}
  body{font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--text);font-size:15px;overflow-y:auto}
  .topbar{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;padding:12px 18px;background:var(--primary);color:#fff;box-shadow:var(--shadow);z-index:20}
  .topbar .store{font-size:17px;font-weight:800;letter-spacing:.5px;white-space:nowrap;justify-self:start}
  .search-wrap{display:flex;align-items:center;gap:7px;background:#fff;border-radius:22px;padding:8px 15px;width:min(380px,60vw);justify-self:center;box-shadow:0 1px 5px rgba(0,0,0,.12)}
  .search-wrap .si{font-size:14px;opacity:.55}
  .search-wrap .search{border:none;outline:none;background:transparent;font-size:14.5px;width:100%;color:#1c2230}
  .fullscreen-btn{border:1px solid rgba(255,255,255,.5);background:rgba(255,255,255,.14);color:#fff;font-size:13px;font-weight:600;padding:7px 14px;border-radius:8px;cursor:pointer;flex-shrink:0;min-height:38px}
  .fullscreen-btn:hover{background:rgba(255,255,255,.25)}
  .fullscreen-btn:active{background:rgba(255,255,255,.32)}
  .kiosk{display:flex;min-height:calc(100vh - 60px);height:auto}
  .menu{flex:1;display:flex;flex-direction:column;min-width:0}
  .cats{display:flex;gap:4px 18px;padding:14px 18px 6px;flex-wrap:wrap;flex-shrink:0;max-height:200px;overflow-y:auto}
  .brand-item{font-size:15px;font-weight:600;color:var(--text-2);padding:8px 4px;cursor:pointer;white-space:nowrap;border-bottom:3px solid transparent;transition:.15s;line-height:1.2}
  .brand-item:hover{color:var(--text)}
  .brand-item.on{color:var(--primary);border-bottom-color:var(--primary);font-weight:800}
  .cat{padding:9px 16px;border-radius:22px;background:var(--surface);border:1px solid var(--border);font-size:14px;font-weight:600;color:var(--text-2);white-space:nowrap;cursor:pointer;min-height:40px}
  .cat.on{background:var(--primary);color:#fff;border-color:var(--primary)}
  .series-bar{display:flex;gap:8px;padding:6px 18px 0;flex-wrap:wrap;flex-shrink:0;max-height:180px;overflow-y:auto}
  .series-bar .cat{background:var(--surface-2);border-color:var(--border);font-size:13px;padding:7px 14px;min-height:34px}
  .series-bar .cat.on{background:var(--primary-soft);color:var(--primary-d);border-color:var(--primary);font-weight:700}
  .grid{flex:1;overflow:visible;padding:12px 18px 24px;display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));grid-auto-rows:max-content;gap:14px;align-content:start}
  .pcard{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;cursor:pointer;transition:.15s;box-shadow:var(--shadow);display:flex;flex-direction:column;height:max-content;min-height:0}
  .pcard:active{transform:scale(.97)}
  .pcard .img{aspect-ratio:4/5;width:100%;flex:none;display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:800;color:#fff;position:relative;overflow:hidden}
  .pcard .img img{width:100%;height:100%;object-fit:contain;position:absolute;inset:0;background:#fff}
  .pcard .series{position:absolute;top:8px;left:8px;background:rgba(0,0,0,.45);color:#fff;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:10px;z-index:2}
  .pcard .body{padding:9px 11px 12px}
  .pcard .pn{font-size:14px;font-weight:700;line-height:1.25}
  .pcard .pb{font-size:11.5px;color:var(--text-3);margin-top:2px}
  .pcard .from{font-size:11.5px;color:var(--text-2);margin-top:6px}
  .pcard .from b{color:var(--primary);font-size:15px}
  .pcard .sku-n{font-size:10.5px;color:var(--text-3);margin-top:2px}
  .cart{position:fixed;right:0;top:60px;bottom:0;width:min(340px,88vw);flex-shrink:0;background:var(--surface);border-left:1px solid var(--border);display:none;flex-direction:column;box-shadow:-8px 0 24px rgba(30,40,80,.18);z-index:40}
  .cart.open{display:flex}
  .cart-head{padding:14px 16px;border-bottom:1px solid var(--border);font-weight:800;font-size:16px;display:flex;align-items:center;gap:8px}
  .cart-head .cnt{background:var(--primary);color:#fff;font-size:12px;padding:1px 9px;border-radius:12px}
  .cart-head .collapse{margin-left:auto;border:none;background:var(--surface-2);width:34px;height:34px;border-radius:10px;font-size:20px;color:var(--text-2);cursor:pointer}
  .cart-list{flex:1;overflow-y:auto;padding:10px 14px}
  .empty{text-align:center;color:var(--text-3);padding:50px 20px;font-size:14px}
  .empty .big{font-size:46px;margin-bottom:10px}
  .citem{display:flex;gap:10px;padding:10px;background:var(--surface-2);border-radius:12px;margin-bottom:9px;border:1px solid var(--border)}
  .citem .ci{width:50px;height:50px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#fff;overflow:hidden;position:relative}
  .citem .ci img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
  .citem .cm{flex:1;min-width:0}
  .citem .cn{font-size:13.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .citem .cs{font-size:11.5px;color:var(--text-3)}
  .citem .cp{font-size:12px;color:var(--text-2);margin-top:2px}
  .citem .cp b{color:var(--primary)}
  .citem .cr{color:var(--danger);font-size:11px;cursor:pointer;font-weight:700}
  .stepper{display:inline-flex;align-items:center;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-top:6px}
  .stepper button{width:30px;height:30px;border:none;background:var(--surface);color:var(--text-2);font-size:17px;font-weight:700;cursor:pointer}
  .stepper button:active{background:var(--primary-soft)}
  .stepper span{width:38px;text-align:center;font-weight:800;font-size:14px}
  .citem .line{font-weight:800;font-size:14px;white-space:nowrap;align-self:center;margin-left:auto}
  .summary{border-top:1px solid var(--border);padding:12px 16px;background:var(--surface-2)}
  .srow{display:flex;justify-content:space-between;font-size:13.5px;padding:3px 0;color:var(--text-2)}
  .srow.total{font-size:17px;font-weight:800;color:var(--text);padding-top:8px;margin-top:4px;border-top:1px dashed var(--border)}
  .srow.total b{color:var(--primary);font-size:21px}
  .cart-actions{padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:10px}
  .btn{flex:1;border:none;border-radius:12px;padding:15px;font-size:15.5px;font-weight:800;cursor:pointer;min-height:52px}
  .btn:active{transform:scale(.98)}
  .btn-primary{background:var(--primary);color:#fff}
  .btn-ghost{background:var(--surface);border:1px solid var(--border);color:var(--text-2);flex:0 0 auto;width:120px}
  .cart-fab{position:fixed;right:0;top:50%;transform:translateY(-50%);z-index:30;display:none;flex-direction:column;align-items:stretch;gap:8px}
  .fab-btn{border:none;cursor:pointer;box-shadow:var(--shadow);display:flex;flex-direction:column;align-items:center;gap:5px;font-size:12.5px;font-weight:700;color:#fff;padding:14px 11px;min-height:64px;justify-content:center}
  .fab-btn:active{transform:scale(.97)}
  .fab-cart{background:var(--primary);border-radius:0}
  .fab-refresh{background:#f5b400;border-radius:0}
  .cart-fab .n{background:#fff;color:var(--primary);border-radius:12px;padding:0 8px;font-size:12px;font-weight:800}
  .fab-refresh .ri{font-size:20px;line-height:1}

  /* ===== 竖屏适配（平板竖放 / 窄屏）===== */
  @media (max-width: 820px) {
    .kiosk{min-height:calc(100vh - 56px);height:auto}
    .topbar{grid-template-columns:auto minmax(0,1fr) auto;padding:10px 14px;gap:8px}
    .topbar .store{font-size:14px;max-width:96px;overflow:hidden;text-overflow:ellipsis}
    .search-wrap{max-width:none;width:100%;min-width:0;padding:7px 12px}
    .cats{padding:10px 12px 2px}
    .series-bar{padding:4px 12px 0}
    .grid{padding:10px 12px 18px;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px}
    .pcard .img{aspect-ratio:4/5}
    .pcard .img{font-size:34px}
    .pcard .body{padding:7px 9px 10px}
    .pcard .pn{font-size:13px}
    /* 竖屏默认收起购物车，用悬浮按钮 */
    .cart-fab{display:flex}
    .cart{top:56px}
    .sheet{max-width:100%;max-height:86vh}
    .sheet-body .sku-opt{width:min(220px,46vw)}
  }
  .mask{position:fixed;inset:0;background:rgba(15,20,40,.5);display:none;align-items:flex-end;justify-content:center;z-index:50}
  .mask.show{display:flex}
  .sheet{background:var(--surface);width:min(420px,94vw);border-radius:20px;max-height:88vh;display:flex;flex-direction:column;animation:pop .25s ease;box-shadow:0 18px 50px rgba(15,20,40,.35)}
  @keyframes pop{from{transform:scale(.96) translateY(14px);opacity:.5}to{transform:none;opacity:1}}
  @keyframes up{from{transform:translateY(40px);opacity:.6}to{transform:none;opacity:1}}
  .sheet-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
  .sheet-head .st{font-size:16px;font-weight:800}
  .sheet-head .x{margin-left:auto;font-size:26px;color:var(--text-3);cursor:pointer;line-height:1}
  .sku-img-wrap{background:var(--surface-2);padding:10px 10px 0}
  .sku-img-wrap img{width:100%;aspect-ratio:4/5;object-fit:contain;background:var(--surface-2);border-radius:10px}
  .sheet-body{padding:12px 18px 22px;overflow-y:auto;display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-content:flex-start}
  .sheet-body .sku-opt{width:100%;flex:none}
  .sku-opt{border:1px solid var(--border);border-radius:14px;padding:13px 14px;cursor:pointer;transition:.15s;background:var(--surface-2)}
  .sku-opt:active{transform:scale(.98)}
  .sku-opt.sold{opacity:.45;cursor:not-allowed;background:#f4eef1}
  .sku-opt .lab{font-weight:700;font-size:14.5px;display:flex;align-items:center;gap:7px}
  .sku-opt .cond{font-size:11px;font-weight:700;padding:1px 8px;border-radius:9px}
  .sku-opt .calc{font-size:12px;color:var(--text-2);margin-top:8px}
  .sku-opt .calc b{color:var(--primary);font-size:18px}
  .sku-opt .row{display:flex;align-items:center;justify-content:space-between;margin-top:8px;gap:8px}
  .sku-opt .add-btn{width:34px;height:34px;border-radius:50%;border:none;background:var(--primary);color:#fff;font-size:22px;font-weight:800;line-height:1;cursor:pointer;flex-shrink:0;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(230,2,31,.35)}
  .sku-opt .add-btn:hover{background:var(--primary-d)}
  .sku-opt .stk{font-size:11.5px;color:var(--text-2);margin-top:3px}
  .sku-opt .stk.low{color:var(--warn);font-weight:700}
  .sku-opt .stk.out{color:var(--danger);font-weight:700}
  .wish-btn{width:100%;margin-top:10px;border:none;background:var(--primary);color:#fff;border-radius:10px;padding:8px;font-size:13px;font-weight:700;cursor:pointer}
  .wish-btn:hover{background:var(--primary-d)}
  .sku-opt .wish-done{width:100%;margin-top:10px;text-align:center;font-size:13px;font-weight:700;color:var(--ok);padding:8px}
  .sku-empty{width:100%;text-align:center;padding:30px 10px}
  .sku-empty-txt{font-size:14px;color:var(--text-2);margin-bottom:14px}
  .wish-btn-lg{width:auto;min-width:160px;padding:12px 30px;font-size:15px;border-radius:12px}
  .cond-sealed{background:#e8f0ff;color:#2f6fed}.cond-opened{background:#eafaf0;color:#16a34a}.cond-boxless{background:#fff4e0;color:#b45309}.cond-flawed{background:#fdecec;color:#dc2626}
  .modal{background:var(--surface);border-radius:18px;width:min(480px,94vw);padding:22px;animation:up .2s ease}
  .modal h3{margin:0 0 14px;font-size:18px}
  .pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:12px 0 4px}
  .pay-opt{border:2px solid var(--border);border-radius:14px;padding:18px;text-align:center;font-weight:800;font-size:15px;cursor:pointer;background:var(--surface-2);transition:.15s}
  .pay-opt:active{transform:scale(.97)}
  .pay-opt .pi{font-size:30px;display:block;margin-bottom:6px}
  .pay-opt:hover{border-color:var(--primary)}
  .success{text-align:center;padding:10px}
  .success .ok{width:72px;height:72px;border-radius:50%;background:var(--ok);color:#fff;font-size:40px;display:flex;align-items:center;justify-content:center;margin:6px auto 14px}
  .success .ot{font-size:13px;color:var(--text-2);margin:3px 0}
  .success .ot b{color:var(--text)}
  .success .ot.note{color:var(--text-3);font-size:12px;margin-top:8px}
  .toast{position:fixed;bottom:26px;left:50%;transform:translateX(-50%) translateY(20px);background:#1c2230;color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;opacity:0;transition:.25s;z-index:90;pointer-events:none}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
  .toast.err{background:#b3261e}
  .scan-hint{font-size:11.5px;color:var(--text-3);text-align:center;padding:0 20px 12px}
  .qr-box{width:210px;height:210px;margin:6px auto 4px;background:#fff;border:1px solid var(--border);border-radius:14px;display:flex;align-items:center;justify-content:center;overflow:hidden}
  .qr-box img{max-width:100%;max-height:100%}
  .qr-missing{color:var(--text-3);font-size:13px;padding:20px;text-align:center}
  .qr-tip{font-size:13.5px;color:var(--text-2);margin:8px 0 2px;font-weight:600}
  .qr-amt{font-size:24px;font-weight:800;color:var(--primary);margin-bottom:6px}
</style>
</head>
<body>

<!-- 收银台未开启遮罩 -->
<div id="posDisabledMask" style="position:fixed;inset:0;background:#f5f6f8;z-index:10000;display:none;align-items:center;justify-content:center;flex-direction:column;gap:16px;">
  <div style="font-size:64px;">🚫</div>
  <div style="font-size:22px;font-weight:800;color:#2b2230;">线下收银台暂未开启</div>
  <div style="font-size:14px;color:#7a6b75;">请联系管理员在「店铺设置 → 线下收银台」中开启</div>
</div>

<!-- 屏保覆盖层 -->
<div id="screensaver" style="position:fixed;inset:0;z-index:9999;display:none;cursor:pointer;background:#000;">
  <img id="ssImg" src="" alt="" style="width:100%;height:100%;" onclick="exitScreensaver()">
</div>
<style>
#screensaver img{object-fit:contain}
@media (orientation:portrait){#screensaver img{object-fit:cover}}
</style>

<div class="topbar">
  <div class="store" id="storeName"><?= htmlspecialchars($storeName) ?></div>
  <div class="search-wrap">
    <span class="si">🔍</span>
    <input class="search" id="searchInput" placeholder="搜索商品名称" oninput="onSearch(this.value)">
  </div>
  <div style="justify-self:end;display:flex;align-items:center;gap:10px">
    <button class="fullscreen-btn" onclick="toggleFullscreen()">⛶ 全屏</button>
  </div>
</div>

<div class="kiosk" id="kiosk">
  <div class="menu">
    <div class="cats" id="brands"></div>
    <div class="series-bar" id="seriesBar"></div>
    <div class="grid" id="grid"></div>
  </div>
  <div class="cart" id="cart">
    <div class="cart-head">
      <span>购物车</span><span class="cnt" id="cartCnt">0</span>
      <button class="collapse" onclick="collapseCart()" title="收起购物车">›</button>
    </div>
    <div class="cart-list" id="cartList"></div>
    <div class="summary" id="summary"></div>
    <div class="cart-actions">
      <button class="btn-ghost" onclick="clearCart()">清空</button>
      <button class="btn btn-primary" onclick="openCheckout()">去结算</button>
    </div>
  </div>
</div>
<div class="cart-fab" id="cartFab">
  <button class="fab-btn fab-cart" onclick="expandCart()"><span class="n" id="fabCnt">0</span>购物车</button>
  <button class="fab-btn fab-refresh" onclick="refreshWithFullscreen()" title="刷新页面（自动进入全屏）"><span class="ri">⟳</span>刷新</button>
</div>

<!-- 品相选择 -->
<div class="mask" id="skuMask" style="align-items:center;padding-top:4vh" onclick="if(event.target===this)closeSku()">
  <div class="sheet">
    <div class="sheet-head">
      <span class="st" id="skuTitle">选择品相</span>
      <span class="x" onclick="closeSku()">×</span>
    </div>
    <div class="sku-img-wrap" id="skuImgWrap" style="display:none;">
      <img id="skuImg" src="" alt="" style="width:100%;object-fit:contain;background:var(--surface-2);">
    </div>
    <div class="scan-hint" id="skuHint">点选品相即可加入购物清单</div>
    <div class="sheet-body" id="skuBody"></div>
  </div>
</div>

<!-- 结算：选择支付方式 -->
<div class="mask" id="checkoutMask" onclick="if(event.target===this)closeCheckout()">
  <div class="modal">
    <div class="sheet-head" style="border:0;padding:0 0 12px">
      <span class="st">确认订单</span>
      <span class="x" onclick="closeCheckout()">×</span>
    </div>
    <div id="checkoutBody"></div>
    <div class="pay-grid">
      <div class="pay-opt" onclick="startPay('wechat')"><span class="pi">
        <svg viewBox="0 0 1024 1024" width="34" height="34" aria-label="微信支付"><path d="M404.511405 600.865957c-4.042059 2.043542-8.602935 3.223415-13.447267 3.223415-11.197016 0-20.934798-6.169513-26.045189-15.278985l-1.959631-4.296863-81.56569-178.973184c-0.880043-1.954515-1.430582-4.14746-1.430582-6.285147 0-8.251941 6.686283-14.944364 14.938224-14.944364 3.351328 0 6.441713 1.108241 8.94165 2.966565l96.242971 68.521606c7.037277 4.609994 15.433504 7.305383 24.464181 7.305383 5.40101 0 10.533914-1.00284 15.328104-2.75167l452.645171-201.459315C811.496653 163.274644 677.866167 100.777241 526.648117 100.777241c-247.448742 0-448.035176 167.158091-448.035176 373.361453 0 112.511493 60.353576 213.775828 154.808832 282.214547 7.582699 5.405103 12.537548 14.292518 12.537548 24.325012 0 3.312442-0.712221 6.358825-1.569752 9.515724-7.544837 28.15013-19.62599 73.202209-20.188808 75.314313-0.940418 3.529383-2.416026 7.220449-2.416026 10.917654 0 8.245801 6.692423 14.933107 14.944364 14.933107 3.251044 0 5.89015-1.202385 8.629541-2.7793l98.085946-56.621579c7.377014-4.266164 15.188934-6.89913 23.790846-6.89913 4.577249 0 9.003048 0.703011 13.174044 1.978051 45.75509 13.159718 95.123474 20.476357 146.239666 20.476357 247.438509 0 448.042339-167.162184 448.042339-373.372709 0-62.451354-18.502399-121.275087-51.033303-173.009356L407.778822 598.977957 404.511405 600.865957z" fill="#00C800"/></svg>
      </span>微信支付</div>
      <div class="pay-opt" onclick="startPay('alipay')"><span class="pi">
        <svg viewBox="0 0 1024 1024" width="34" height="34" aria-label="支付宝"><path d="M230.771014 576.556522c-12.614493 9.646377-25.228986 23.744928-28.93913 42.295652-5.194203 24.486957-0.742029 55.652174 22.26087 80.13913 28.93913 28.93913 72.718841 37.101449 92.011594 38.585508 51.2 3.710145 106.110145-22.26087 147.663768-50.457971 16.324638-11.130435 43.77971-34.133333 70.492754-69.750725-59.362319-30.423188-133.565217-64.556522-212.22029-61.588406-41.553623 1.484058-70.492754 9.646377-91.269566 20.776812zM983.188406 712.347826c25.971014-61.588406 40.811594-129.113043 40.811594-200.347826 0-281.971014-230.028986-512-512-512S0 230.028986 0 512s230.028986 512 512 512c170.666667 0 321.298551-83.849275 414.794203-212.22029C838.492754 768.742029 693.797101 696.023188 604.011594 652.985507c-42.295652 48.973913-105.368116 97.205797-176.602898 117.982609-44.521739 13.356522-85.333333 18.550725-126.886957 9.646377-42.295652-8.904348-72.718841-28.197101-90.527536-47.489855-8.904348-10.388406-19.292754-22.26087-27.455073-37.843479 0.742029 0.742029 0.742029 2.226087 0.742029 2.968116 0 0-4.452174-7.42029-7.420289-19.292753-1.484058-5.936232-2.968116-11.872464-3.710145-17.808696-0.742029-4.452174-0.742029-8.904348 0-12.614493-0.742029-7.42029 0-15.582609 1.484058-23.744927 4.452174-20.776812 12.614493-43.77971 35.617391-65.298551 48.973913-48.231884 115.014493-50.457971 149.147826-50.457971 50.457971 0.742029 138.017391 22.26087 212.22029 48.973913 20.776812-43.77971 34.133333-89.785507 42.295652-121.692754H304.973913v-33.391304h158.052174v-66.782609H272.324638v-34.133333h190.701449v-66.782609c0-8.904348 2.226087-16.324638 16.324638-16.324637h74.944927v83.107246h207.026087v33.391304H554.295652v66.782609H719.768116S702.701449 494.933333 651.501449 586.202899c115.014493 40.811594 277.518841 104.626087 331.686957 126.144927z" fill="#06B4FD"/></svg>
      </span>支付宝</div>
    </div>
  </div>
</div>

<!-- 店铺收款码 -->
<div class="mask" id="qrMask" onclick="if(event.target===this)cancelQr()">
  <div class="modal" style="width:min(360px,92vw);text-align:center">
    <div class="sheet-head" style="border:0;justify-content:center;position:relative">
      <span class="st" id="qrTitle">扫码收款</span>
      <span class="x" onclick="cancelQr()" style="position:absolute;right:0">×</span>
    </div>
    <div class="qr-badge" id="qrBadge" style="display:inline-block;padding:6px 16px;border-radius:999px;color:#fff;font-size:15px;font-weight:600;background:#07C160;margin:6px 0 10px">微信收款码</div>
    <div class="qr-amt" id="qrAmt">¥0.00</div>
    <div class="qr-box" id="qrBox"></div>
    <div class="qr-tip">请使用微信/支付宝扫码付款</div>
    <div class="scan-hint" id="qrHint">付款完成后请找工作人员配货</div>
    <button class="btn btn-primary" style="width:100%;margin-top:12px" onclick="onPaid()">已付款</button>
    <button class="btn btn-ghost" style="width:100%;margin-top:8px" onclick="cancelQr()">取消</button>
  </div>
</div>

<!-- 下单成功 -->
<div class="mask" id="successMask">
  <div class="modal">
    <div class="success">
      <div class="ok">✓</div>
      <h3 style="margin:0 0 6px">下单成功</h3>
      <div class="ot" id="sOrderNo">订单号 —</div>
      <div class="ot" id="sItems">—</div>
      <div class="ot" id="sPay">应付 —</div>
      <div class="ot" id="sMethod">支付方式 —</div>
      <div class="ot note" id="sNote">订单已提交，请凭订单号找工作人员配货</div>
      <button class="btn btn-primary" style="width:100%;margin-top:16px" onclick="backHome()">返回</button>
    </div>
  </div>
</div>

<div class="mask" id="fsPwdMask" style="z-index:9999" onclick="if(event.target===this)fsPwdCancel()">
  <div class="modal" style="width:min(360px,92vw);text-align:center">
    <div class="sheet-head" style="border:0;justify-content:center;position:relative">
      <span class="st">退出全屏</span>
    </div>
    <div style="font-size:13px;color:var(--text-2);margin:4px 0 14px">请输入密码后退出全屏</div>
    <input id="fsPwdInput" type="password" placeholder="请输入退出密码" style="width:100%;padding:12px 13px;border:1px solid var(--border);border-radius:10px;font-size:15px;margin-bottom:12px" onkeydown="if(event.key==='Enter')fsPwdConfirm()">
    <button class="btn btn-primary" style="width:100%" onclick="fsPwdConfirm()">退出全屏</button>
    <button class="btn btn-ghost" style="width:100%;margin-top:8px" onclick="fsPwdCancel()">留在全屏</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ===== 常量（PHP 注入）=====
const STORE = {
  name: <?= json_encode($storeName) ?>,
  qrWx: <?= json_encode($qrWx) ?>,
  qrAli: <?= json_encode($qrAli) ?>
};
const API = '../api/';

// ===== 状态 =====
let CATALOG = { store_name: '', products: [] };
let cart = [];          // {pid, key, name, series, cond, condName, unit, qty, imgUrl}
let curBrand = '';
let curSeries = '';
let kw = '';
let payMethod = 'wechat';
let curOrder = null;    // 当前待确认收款的订单 {order_id, order_no}

// ===== 加载目录 =====
async function loadCatalog(keepFilter) {
  try {
    const res = await fetch(API + 'pos_catalog.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || '加载失败');
    const prevBrand = curBrand, prevSeries = curSeries, prevKw = kw;
    CATALOG = data;
    $('storeName').textContent = CATALOG.store_name || STORE.name;
    const brands = listBrands();
    if (keepFilter && brands.includes(prevBrand)) {
      curBrand = prevBrand;
      curSeries = brands.includes(prevBrand) && listSeries(curBrand).includes(prevSeries) ? prevSeries : firstSeries(curBrand);
      kw = prevKw;
    } else {
      curBrand = brands[0] || '';
      curSeries = firstSeries(curBrand);
      kw = '';
    }
    renderBrands(); renderSeries(); renderGrid(); renderCart();
    handlePosState();
  } catch (e) {
    toast(e.message, true);
  }
}

// ===== 开关 + 屏保 =====
let ssTimer = null;
function handlePosState() {
  // 开关检查
  if (CATALOG.pos_enabled === 0) {
    document.getElementById('posDisabledMask').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    return;
  }
  document.getElementById('posDisabledMask').style.display = 'none';
  document.body.style.overflow = '';
  // 屏保：设置了图片 + 时长>0 才启用
  const ssImg = CATALOG.screensaver_img || '';
  const ssSec = (CATALOG.screensaver_sec || 0);
  if (ssImg && ssSec > 0) {
    document.getElementById('ssImg').src = ssImg;
    resetSsTimer(ssSec);
    document.addEventListener('pointerdown', () => resetSsTimer(ssSec), true);
    document.addEventListener('keydown', () => resetSsTimer(ssSec), true);
    // 首次加载直接进入屏保（需求：设置有图片时进入页面自动屏保状态）
    showScreensaver();
  } else {
    clearTimeout(ssTimer);
  }
}
function resetSsTimer(sec) {
  clearTimeout(ssTimer);
  ssTimer = setTimeout(showScreensaver, sec * 1000);
}
function showScreensaver() {
  const ss = document.getElementById('screensaver');
  if (ss && document.getElementById('ssImg').src) {
    ss.style.display = 'flex';
  }
}
function exitScreensaver() {
  document.getElementById('screensaver').style.display = 'none';
  // 退出屏保后重新计时
  const ssSec = (CATALOG.screensaver_sec || 0);
  if (ssSec > 0) resetSsTimer(ssSec);
}

// ===== 品牌 / 系列 =====
function totalStock(p) { return p.skus ? p.skus.reduce((a, s) => a + (s.stock > 0 ? s.stock : 0), 0) : 0; }
function hasStock(p) { return totalStock(p) > 0; }
function productsOf(brand) {
  if (brand === '其他') return CATALOG.products.filter(p => !p.brand);
  return CATALOG.products.filter(p => p.brand === brand);
}
function listBrands() {
  const hasOther = CATALOG.products.some(p => !p.brand);
  const brands = [...new Set(CATALOG.products.map(p => p.brand).filter(Boolean))];
  if (hasOther) brands.push('其他');
  return brands;
}
function listSeries(brand) {
  // 空系列归为「未分类」，保证所有商品可达
  return [...new Set(productsOf(brand).map(p => p.series || '未分类'))];
}
function firstSeries(brand) {
  const s = listSeries(brand);
  return s[0] || '';
}
function setBrand(b) {
  curBrand = b;
  curSeries = firstSeries(b);
  renderBrands(); renderSeries(); renderGrid();
}
function setSeries(s) {
  curSeries = s;
  renderSeries(); renderGrid();
}
function renderBrands() {
  const brands = listBrands();
  $('brands').innerHTML = brands.map(b => `<div class="brand-item ${b === curBrand ? 'on' : ''}" onclick="setBrand('${b.replace(/'/g, "\\\\'")}')">${b}</div>`).join('');
}
function renderSeries() {
  const series = listSeries(curBrand);
  if (series.length <= 1) { $('seriesBar').innerHTML = ''; return; }
  $('seriesBar').innerHTML = series.map(s => `<div class="cat ${s === curSeries ? 'on' : ''}" onclick="setSeries('${s.replace(/'/g, "\\'")}')">${s}</div>`).join('');
}
function onSearch(v) {
  kw = (v || '').trim().toLowerCase();
  renderGrid();
}

// ===== 渲染 =====
function grad(series) {
  const map = {'小野':'linear-gradient(135deg,#ffd1e8,#ff9ec7)','嘎子姐':'linear-gradient(135deg,#ffe0c2,#ffb088)','Dimoo':'linear-gradient(135deg,#c2e9ff,#9ed2ff)','Molly':'linear-gradient(135deg,#fff0b3,#ffd97a)','其他':'linear-gradient(135deg,#e0d4ff,#c7b3ff)'};
  return map[series] || map['其他'];
}
function renderGrid() {
  let list;
  if (kw) {
    list = CATALOG.products.filter(p => p.name.toLowerCase().includes(kw));
  } else {
    list = productsOf(curBrand);
    if (curSeries) {
      list = list.filter(p => (curSeries === '未分类') ? !p.series : p.series === curSeries);
    }
  }
  // 全部显示，按库存排序（库存多的靠前，售罄靠后）
  list = [...list].sort((a, b) => totalStock(b) - totalStock(a));
  if (!list.length) {
    $('grid').innerHTML = `<div class="empty" style="grid-column:1/-1"><div class="big">🔍</div>未找到相关商品</div>`;
    return;
  }
  $('grid').innerHTML = list.map((p) => {
    const avail = p.skus.filter(s => s.stock > 0);
    const minPrice = avail.length ? Math.min(...avail.map(s => s.price)) : null;
    const img = p.image_url ? `<img src="${p.image_url}" loading="lazy" onerror="this.remove()">` : '';
    return `<div class="pcard" onclick="openSku(${p.id})">
      <div class="img" style="background:${grad(p.series)}">${img}${img ? '' : (p.name[0] || '')}</div>
      <div class="body">
        <div class="pn">${p.name}</div>
        <div class="from">${minPrice != null ? `<b>¥${minPrice.toFixed(2)}</b> 起` : '暂时缺货'}</div>
        <div class="sku-n">${p.skus.length} 个品相可选</div>
      </div>
    </div>`;
  }).join('');
}

// ===== 品相弹层 =====
function openSku(pid) {
  const p = CATALOG.products.find(x => x.id === pid);
  if (!p) return;
  $('skuTitle').textContent = p.name;
  const requested = wishRequested(pid);
  $('skuHint').textContent = requested ? '你已为这款商品求过补货' : '点选品相即可加入购物清单';
  // 商品图
  const imgWrap = $('skuImgWrap'), imgEl = $('skuImg');
  if (p.image_url) { imgEl.src = p.image_url; imgWrap.style.display = 'block'; }
  else { imgWrap.style.display = 'none'; }
  $('skuBody').innerHTML = p.skus.length === 0
    ? `<div class="sku-empty">
         <div class="sku-empty-txt">该商品暂未入库，暂无品相可选</div>
         ${requested
           ? `<div class="wish-done">✓ 已求补货</div>`
           : `<button class="wish-btn wish-btn-lg" onclick="requestRestock(${p.id}, '')">求补货</button>`}
       </div>`
    : p.skus.map(s => {
    const sold = s.stock <= 0;
    const low = !sold && s.stock <= 5;
    const stkCls = sold ? 'out' : (low ? 'low' : '');
    const stkTxt = sold ? '已售罄' : (low ? `仅剩 ${s.stock} 件` : `库存 ${s.stock}`);
    const wishBtn = sold ? (requested
      ? `<div class="wish-done">✓ 已求补货</div>`
      : `<button class="wish-btn" onclick="event.stopPropagation();requestRestock(${p.id}, '${s.condition_type}')">求补货</button>`)
      : '';
    return `<div class="sku-opt ${sold ? 'sold' : ''}" ${sold ? '' : `onclick="addToCart(${p.id}, '${s.condition_type}')"`}>
      <div class="lab"><span class="cond cond-${s.condition_type}">${s.cond_name}</span></div>
      <div class="row">
        <div class="calc">售价 <b>¥${s.price.toFixed(2)}</b></div>
        ${sold ? '' : `<span class="add-btn" title="加入购物车">+</span>`}
      </div>
      <div class="stk ${stkCls}">${stkTxt}</div>
      ${wishBtn}
    </div>`;
  }).join('');
  show('skuMask');
}
// 求补货（每客户每商品一次，localStorage 记录 + 后端统计）
function clientKey() {
  let k = localStorage.getItem('wish_client');
  if (!k) { k = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); localStorage.setItem('wish_client', k); }
  return k;
}
function wishKey(pid) { return 'wish_' + pid; }
function wishRequested(pid) { return localStorage.getItem(wishKey(pid)) === '1'; }
function requestRestock(pid, cond) {
  if (wishRequested(pid)) { toast('你已经求过补货了'); return; }
  localStorage.setItem(wishKey(pid), '1');
  // 记录到后端（统计用）
  fetch(API + 'pos_wish.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ product_id: pid, condition_type: cond, client_key: clientKey() })
  }).catch(() => {});
  renderCart();
  toast('已记录，补货后我们会尽快上架');
  closeSku();
  setTimeout(() => openSku(pid), 50);
}
function closeSku() { hide('skuMask'); }

// ===== 购物车 =====
function addToCart(pid, cond) {
  const p = CATALOG.products.find(x => x.id === pid);
  if (!p) return;
  const sk = p.skus.find(s => s.condition_type === cond);
  if (!sk) return;
  const key = pid + '_' + cond;
  const ex = cart.find(c => c.key === key);
  if (ex) {
    if (ex.qty >= sk.stock) { toast('已达该品相库存上限'); return; }
    ex.qty++;
  }
  else {
    if (sk.stock <= 0) return;
    cart.push({ key, pid, name: p.name, series: p.series, cond, condName: sk.cond_name, unit: sk.price, qty: 1, stock: sk.stock, imgUrl: p.image_url || '' });
  }
  closeSku(); // 点击 SKU 加入后关闭品相弹层
  // 若清单被收起，加入商品时自动弹出
  if (!$('cart').classList.contains('open')) expandCart();
  renderCart();
  toast('已加入 ' + p.name + ' · ' + sk.cond_name);
}
function chgQty(key, d) {
  const it = cart.find(c => c.key === key); if (!it) return;
  if (d > 0 && it.qty >= it.stock) { toast('已达库存上限'); return; }
  it.qty += d;
  if (it.qty <= 0) cart = cart.filter(c => c.key !== key);
  renderCart();
}
function rmItem(key) { cart = cart.filter(c => c.key !== key); renderCart(); }
function clearCart() {
  if (!cart.length) return;
  cart = []; renderCart(); toast('已清空');
}
function collapseCart() { $('cart').classList.remove('open'); $('cartFab').style.display = 'flex'; }
function expandCart() { $('cart').classList.add('open'); $('cartFab').style.display = 'none'; }
function renderCart() {
  const list = $('cartList');
  if (!cart.length) {
    list.innerHTML = `<div class="empty"><div class="big">🛒</div>点击左侧商品开始点单<br>选品相后自动加入清单</div>`;
  } else {
    list.innerHTML = cart.map(it => `
      <div class="citem">
        <div class="ci" style="background:${grad(it.series)}">${it.imgUrl ? `<img src="${it.imgUrl}" onerror="this.remove()">` : (it.name[0] || '')}</div>
        <div class="cm">
          <div class="cn">${it.name}</div>
          <div class="cs">${it.condName}</div>
          <div class="cp">单价 <b>¥${it.unit.toFixed(2)}</b></div>
          <div class="stepper"><button onclick="chgQty('${it.key}',-1)">−</button><span>${it.qty}</span><button onclick="chgQty('${it.key}',1)">＋</button></div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
          <span class="cr" onclick="rmItem('${it.key}')">删除</span>
          <span class="line">¥${(it.unit * it.qty).toFixed(2)}</span>
        </div>
      </div>`).join('');
  }
  const cnt = cart.reduce((a, b) => a + b.qty, 0);
  $('cartCnt').textContent = cnt;
  $('fabCnt').textContent = cnt;
  renderSummary();
}
function calc() {
  const subtotal = cart.reduce((a, b) => a + b.unit * b.qty, 0);
  return { subtotal, payable: +subtotal.toFixed(2) };
}
function renderSummary() {
  const c = calc();
  let html = `<div class="srow"><span>商品小计（${cart.reduce((a, b) => a + b.qty, 0)}件）</span><span>¥${c.subtotal.toFixed(2)}</span></div>`;
  html += `<div class="srow total"><span>应付合计</span><b>¥${c.payable.toFixed(2)}</b></div>`;
  $('summary').innerHTML = html;
}

// ===== 结算 =====
function openCheckout() {
  if (!cart.length) { toast('清单为空'); return; }
  const c = calc();
  let html = `<div style="max-height:34vh;overflow:auto;margin-bottom:10px">`;
  cart.forEach(it => {
    html += `<div style="display:flex;justify-content:space-between;font-size:13.5px;padding:5px 0;border-bottom:1px dashed var(--border)">
      <span>${it.name} · ${it.condName} ×${it.qty}</span><span>¥${(it.unit * it.qty).toFixed(2)}</span></div>`;
  });
  html += `</div>`;
  html += `<div class="srow"><span>小计</span><span>¥${c.subtotal.toFixed(2)}</span></div>`;
  html += `<div class="srow total"><span>应付</span><b>¥${c.payable.toFixed(2)}</b></div>`;
  html += `<div style="font-size:12.5px;color:var(--text-3);text-align:center;margin-top:8px">请选择支付方式，扫码完成付款</div>`;
  $('checkoutBody').innerHTML = html;
  show('checkoutMask');
}
function closeCheckout() { hide('checkoutMask'); }

// 选支付方式 → 立即落单(pending) + 弹收款码
async function startPay(method) {
  payMethod = method;
  const qrUrl = method === 'wechat' ? STORE.qrWx : STORE.qrAli;
  if (!qrUrl) { toast('本店未配置收款码，请联系店员', true); return; }
  try {
    const order = await doCheckout();
    openQr(order, qrUrl);
  } catch (e) {
    toast(e.message, true);
  }
}

// 落单（服务端重算价+锁库存，pay_method=scan）
async function doCheckout() {
  const items = cart.map(it => ({ product_id: it.pid, condition_type: it.cond, qty: it.qty }));
  const res = await fetch(API + 'pos_checkout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ items, pay_method: 'scan' })
  });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || '下单失败');
  return data;
}

// 收款码弹窗
function openQr(order, qrUrl) {
  curOrder = order;
  const isWx = payMethod === 'wechat';
  const name = isWx ? '微信收款码' : '支付宝收款码';
  const color = isWx ? '#07C160' : '#1677FF';
  $('qrTitle').textContent = name;
  $('qrBadge').textContent = name;
  $('qrBadge').style.background = color;
  $('qrAmt').textContent = '¥' + order.payable.toFixed(2);
  $('qrBox').innerHTML = qrUrl
    ? `<img src="${qrUrl}" alt="收款码">`
    : `<div class="qr-missing">未配置收款码</div>`;
  $('qrHint').textContent = '付款完成后请找工作人员配货';
  closeCheckout();
  show('qrMask');
}
// 纯关闭收款码弹窗（已付款成功路径）
function closeQr() { hide('qrMask'); }

// 取消订单：释放锁定 + 删除订单（不进入门店待出库）
async function cancelQr() {
  if (!curOrder) { closeQr(); return; }
  const orderId = curOrder.order_id;
  try {
    const res = await fetch(API + 'pos_cancel.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: orderId })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || '取消失败');
    curOrder = null;
    closeQr();
    toast('订单已取消，未进入待出库');
  } catch (e) {
    toast(e.message, true);
  }
}

// 顾客点「已付款」
async function onPaid() {
  if (!curOrder) { closeQr(); return; }
  try {
    const res = await fetch(API + 'pos_pay_confirm.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_id: curOrder.order_id })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || '确认失败');
    closeQr();
    showSuccess(curOrder);
  } catch (e) {
    toast(e.message, true);
  }
}

// 成功页
function showSuccess(order) {
  const mName = { wechat: '微信扫码', alipay: '支付宝' }[payMethod] || '扫码支付';
  $('sOrderNo').textContent = '订单号 ' + order.order_no;
  $('sItems').textContent = '共 ' + cart.reduce((a, b) => a + b.qty, 0) + ' 件';
  $('sPay').textContent = '应付 ¥' + order.payable.toFixed(2);
  $('sMethod').textContent = '支付方式 ' + mName;
  show('successMask');
}
function backHome() {
  cart = []; curOrder = null; payMethod = 'wechat';
  renderCart(); hide('successMask'); expandCart();
  toast('已返回');
}

// ===== 工具 =====
function $(id) { return document.getElementById(id); }
function show(id) { $(id).classList.add('show'); }
function hide(id) { $(id).classList.remove('show'); }

// 全屏切换（同仓库出库台）+ 强制全屏：退出需输入密码 888888
const FULLSCREEN_PWD = '888888';
let fsForce = false; // 是否处于"强制全屏"模式（自动全屏/刷新后）
function enterFullscreen() {
  if (document.fullscreenElement) return;
  const el = document.documentElement;
  const req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
  if (req) req.call(el).catch(() => {});
}
function toggleFullscreen() {
  if (!document.fullscreenElement) {
    fsForce = true;
    enterFullscreen();
  } else {
    // 手动退出：走密码流程
    askFsPwd();
  }
}
// 监听全屏状态变化：退出全屏时若处于强制模式 → 立即重进全屏 + 弹密码框
document.addEventListener('fullscreenchange', function () {
  if (!document.fullscreenElement) {
    if (window.__fsPwdOk) { window.__fsPwdOk = false; fsForce = false; return; } // 密码正确，允许退出
    enterFullscreen();          // 先重进全屏（安卓触摸屏：防止系统 UI 覆盖密码框）
    setTimeout(askFsPwd, 200);  // 重进后再弹密码框，确保在全屏层内可见
  }
});
function askFsPwd() {
  $('fsPwdInput').value = '';
  show('fsPwdMask');
  setTimeout(() => { const inp = $('fsPwdInput'); if (inp && inp.focus) inp.focus(); }, 120);
}
function fsPwdConfirm() {
  const pwd = $('fsPwdInput').value;
  if (pwd === FULLSCREEN_PWD) {
    window.__fsPwdOk = true;
    hide('fsPwdMask');
    // 密码正确：主动退出全屏
    if (document.fullscreenElement) {
      const ex = document.exitFullscreen || document.webkitExitFullscreen;
      if (ex) ex.call(document).catch(() => {});
    }
  } else {
    toast('密码错误，重新进入全屏');
    $('fsPwdInput').value = '';
    hide('fsPwdMask');
    // 密码错误：强制回到全屏
    enterFullscreen();
  }
}
function fsPwdCancel() {
  hide('fsPwdMask');
  // 取消=留在全屏：强制回全屏
  enterFullscreen();
}
let toastT;
function toast(msg, isError) {
  const t = $('toast'); t.textContent = msg; t.className = 'toast' + (isError ? ' err' : '');
  requestAnimationFrame(() => t.classList.add('show'));
  clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('show'), isError ? 10000 : 1800);
}

// ===== init =====
loadCatalog();
renderCart();
collapseCart();

// 刷新后自动强制全屏（浏览器要求用户手势，首次点击/触摸时触发）
function enterFullscreen() {
  if (document.fullscreenElement) return;
  const el = document.documentElement;
  const req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
  if (req) req.call(el).catch(() => {});
}
// 刷新按钮：进入全屏 + 重新加载目录数据（不整页 reload，保留全屏状态）
function refreshWithFullscreen() {
  if (!document.fullscreenElement) {
    const el = document.documentElement;
    const req = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
    if (req) req.call(el).catch(() => {});
  }
  // 重新拉取目录数据（no-store）并重渲染，保留当前品牌/系列/搜索
  loadCatalog(true).then(() => {
    toast('已刷新');
  }).catch(() => { toast('刷新失败', true); });
}
try {
  enterFullscreen();
  // 若自动进入失败（需手势），在首次用户交互时补触发
  const tryOnce = () => { enterFullscreen(); document.removeEventListener('pointerdown', tryOnce); document.removeEventListener('keydown', tryOnce); };
  document.addEventListener('pointerdown', tryOnce);
  document.addEventListener('keydown', tryOnce);
} catch (e) {}
</script>
</body>
</html>
