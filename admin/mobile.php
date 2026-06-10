<?php
$pageTitle = '超级管理 - 移动端';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireAuth();

if (!isSuperAdmin()) {
    header('Location: ../mobile_outbound.php');
    exit;
}

$currentUser = getCurrentUser();
$currentViewStoreId = $_SESSION['view_store_id'] ?? null;

$allStores = [];
try {
    $allStores = getDB()->query("SELECT id, name FROM stores ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$conditionTypes = [];
try {
    $stmt = getDB()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
    $row = $stmt->fetch();
    if ($row && $row['setting_value']) {
        $decoded = json_decode($row['setting_value'], true);
        if (is_array($decoded)) $conditionTypes = $decoded;
    }
} catch (Exception $e) {}
if (empty($conditionTypes)) {
    $conditionTypes = [
        ['key' => 'sealed', 'name' => '原盒未拆', 'color' => '#10b981'],
        ['key' => 'opened', 'name' => '拆盒无瑕', 'color' => '#3b82f6'],
        ['key' => 'boxless', 'name' => '无盒无瑕', 'color' => '#f59e0b'],
        ['key' => 'flawed', 'name' => '微瑕', 'color' => '#ef4444'],
    ];
}
$conditionTypesJson = json_encode($conditionTypes, JSON_UNESCAPED_UNICODE);
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>超级管理 - 移动端</title>
<script src="../assets/js/html5-qrcode.min.js"></script>
<style>
:root {
    --bg: #0a0a0f; --bg-card: #12121a; --bg-elevated: #1a1a26;
    --border: #2a2a3a; --text: #e8e8ed; --text-secondary: #9d9daf;
    --text-tertiary: #6b6b80; --primary: #5e5ce6; --success: #34d399;
    --danger: #f87171; --warning: #fbbf24; --radius: 10px;
}
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg); color: var(--text); min-height: 100dvh;
}

/* Header */
.header {
    background: var(--bg-elevated); border-bottom: 1px solid var(--border);
    padding: 10px 14px; display: flex; align-items: center; gap: 8px;
    position: sticky; top: 0; z-index: 100;
}
.header .title { font-size: 16px; font-weight: 700; white-space: nowrap; }
.store-select {
    flex: 1; padding: 8px 10px; border: 1px solid var(--border);
    border-radius: 8px; background: var(--bg-card); color: var(--text);
    font-size: 13px; max-width: 180px;
}
.header .logout { color: var(--text-tertiary); text-decoration: none; font-size: 13px; margin-left: auto; }

/* Tab bar */
.tab-bar {
    display: flex; background: var(--bg-elevated);
    border-bottom: 1px solid var(--border);
    position: sticky; top: 48px; z-index: 99;
}
.tab-btn {
    flex: 1; padding: 12px; background: transparent; border: none;
    color: var(--text-secondary); font-size: 15px; font-weight: 600;
    cursor: pointer; border-bottom: 2px solid transparent; transition: 0.15s;
}
.tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

.tab-content { display: none; flex-direction: column; height: calc(100dvh - 96px); }
.tab-content.active { display: flex; }

/* ── Live Tab ── */
.session-banner {
    padding: 8px 14px; background: var(--bg-elevated);
    border-bottom: 1px solid var(--border); font-size: 13px;
    text-align: center; flex-shrink: 0;
}
.session-banner.live { color: #34d399; }
.session-banner.none { color: var(--text-tertiary); }

.live-grid-wrap {
    flex: 1; overflow-y: auto; padding: 8px; padding-bottom: 60px;
}
.live-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;
}
@media (min-width: 600px) {
    .live-grid { grid-template-columns: repeat(3, 1fr); }
}
.product-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius); padding: 10px 12px; min-width: 0;
}
.product-card.sold-out {
    opacity: 0.35; background: rgba(26,26,38,0.4);
    order: 999;
}
.card-name {
    font-size: 14px; font-weight: 700; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-bottom: 6px; line-height: 1.2;
}
.sku-list { display: flex; flex-direction: column; gap: 2px; }
.sku-row {
    display: flex; align-items: center; gap: 6px;
    padding: 4px 0; border-top: 1px solid rgba(42,42,58,0.3);
    cursor: pointer; user-select: none;
}
.sku-row:first-child { border-top: none; }
.sku-badge {
    font-size: 11px; padding: 3px 7px; border-radius: 4px;
    font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.sku-price {
    font-size: 15px; font-weight: 700; color: var(--success);
    margin-left: auto; white-space: nowrap; flex-shrink: 0;
    padding: 3px 5px; border-radius: 4px;
}
.sku-price:active { background: rgba(52,211,153,0.15); }
.sku-stock {
    font-size: 17px; font-weight: 800; min-width: 26px; text-align: right;
    white-space: nowrap; flex-shrink: 0; padding: 3px 5px; border-radius: 4px;
}
.sku-stock:active { background: rgba(94,92,230,0.15); }
.sku-stock.low { color: var(--warning); }

.live-search-bar {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-elevated); border-top: 1px solid var(--border);
    padding: 10px 14px; z-index: 50;
}
.live-search-bar input {
    width: 100%; padding: 10px 14px; border: 1px solid var(--border);
    border-radius: 10px; background: var(--bg-card); color: var(--text);
    font-size: 15px; outline: none;
}
.live-search-bar input:focus { border-color: var(--primary); }

/* ── Outbound Tab ── */
#scanner-section {
    position: relative; width: 100%; background: #000;
    overflow: hidden; flex-shrink: 0;
}
#camera-view {
    width: 100%; aspect-ratio: 4/3; background: #111; position: relative;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
}
#camera-view .placeholder {
    color: #666; font-size: 15px; text-align: center; padding: 20px;
}
#camera-view .placeholder .icon { font-size: 48px; display: block; margin-bottom: 12px; }

.scan-overlay {
    position: absolute; top:0;left:0;right:0;bottom:0; pointer-events:none;
    display: flex; align-items: center; justify-content: center;
}
.scan-frame {
    width: 70%; max-width: 280px; aspect-ratio: 1;
    border: 2px solid rgba(102,126,234,0.7); border-radius: 12px;
    box-shadow: 0 0 0 9999px rgba(0,0,0,0.4);
}
.scan-frame .corner {
    position: absolute; width: 20px; height: 20px; border-color: #667eea; border-style: solid;
}
.scan-frame .corner.tl { top:-2px;left:-2px;border-width:3px 0 0 3px;border-radius:4px 0 0 0; }
.scan-frame .corner.tr { top:-2px;right:-2px;border-width:3px 3px 0 0;border-radius:0 4px 0 0; }
.scan-frame .corner.bl { bottom:-2px;left:-2px;border-width:0 0 3px 3px;border-radius:0 0 0 4px; }
.scan-frame .corner.br { bottom:-2px;right:-2px;border-width:0 3px 3px 0;border-radius:0 0 4px 0; }
.scanner-hint {
    position: absolute; bottom: 10px; left:0;right:0; text-align: center;
    color: rgba(255,255,255,0.5); font-size: 13px; pointer-events: none;
}

.outbound-manual {
    padding: 10px 14px; background: var(--bg-elevated);
    border-bottom: 1px solid var(--border); display: flex; gap: 8px; flex-shrink: 0;
}
.outbound-manual input {
    flex: 1; padding: 10px 14px; border: 1px solid var(--border);
    border-radius: 10px; background: var(--bg-card); color: var(--text);
    font-size: 15px; outline: none;
}
.outbound-manual input:focus { border-color: var(--primary); }
.outbound-manual button {
    padding: 10px 16px; border: none; border-radius: 10px;
    color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.btn-camera {
    padding: 10px 14px; border: none; border-radius: 10px;
    color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
}

#pinyinDropdown {
    position: fixed; top: 50%; left: 10px; right: 10px; max-height: 50vh;
    overflow-y: auto; background: var(--bg-elevated); border: 1px solid var(--border);
    border-radius: 12px; z-index: 200; display: none; box-shadow: 0 8px 32px rgba(0,0,0,0.6);
}
#pinyinDropdown.show { display: block; }
.pinyin-item {
    padding: 12px 14px; border-bottom: 1px solid rgba(42,42,58,0.5);
    cursor: pointer; display: flex; align-items: center; gap: 10px;
}
.pinyin-item:last-child { border-bottom: none; }
.pinyin-item:active { background: rgba(94,92,230,0.15); }
.pinyin-item .pi-name { font-size: 15px; font-weight: 600; flex: 1; }
.pinyin-item .pi-stock { font-size: 13px; color: var(--text-secondary); }
.pinyin-item .pi-add {
    padding: 6px 14px; background: var(--primary); color: #fff;
    border-radius: 6px; font-size: 13px; font-weight: 600;
}

#scanResult {
    background: var(--bg-elevated); padding: 12px 14px;
    border-bottom: 1px solid var(--border); display: none; flex-shrink: 0;
}
#scanResult .sr-name { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
#scanResult .sr-meta { font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px; }
#scanResult .sr-form { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
#scanResult select, #scanResult input {
    padding: 8px 10px; border: 1px solid var(--border);
    border-radius: 8px; background: var(--bg-card); color: var(--text);
    font-size: 14px; outline: none;
}
#scanResult select { flex: 1; min-width: 80px; }
#scanResult input[type="number"] { width: 60px; text-align: center; }
#scanResult input[type="number"].price-inp { width: 90px; }
#scanResult .btn-add {
    padding: 8px 18px; border: none; border-radius: 8px;
    background: var(--primary); color: #fff; font-size: 14px; font-weight: 600;
    cursor: pointer; white-space: nowrap;
}
#scanResult .btn-add:active { opacity: 0.8; }

.outbound-cart {
    flex: 1; display: flex; flex-direction: column; min-height: 0;
}
.cart-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 14px; background: var(--bg-elevated); border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.cart-header .title { font-size: 15px; font-weight: 600; }
.cart-header .count { font-size: 13px; color: var(--text-secondary); }
.cart-items { flex: 1; overflow-y: auto; padding: 8px 14px; }
.cart-empty {
    text-align: center; padding: 40px 20px; color: var(--text-tertiary); font-size: 15px;
}
.cart-item {
    background: var(--bg-card); border-radius: 10px; padding: 10px 12px;
    margin-bottom: 8px; display: flex; align-items: center; gap: 8px;
    border: 1px solid var(--border);
}
.cart-item .ci-info { flex: 1; min-width: 0; }
.cart-item .ci-info .ci-name { font-size: 14px; font-weight: 500; }
.cart-item .ci-info .ci-sub { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }
.cart-item .ci-qty { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.cart-item .ci-qty button {
    width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--border);
    background: var(--bg-card); color: var(--text); font-size: 16px;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.cart-item .ci-qty .ci-num { min-width: 24px; text-align: center; font-size: 15px; font-weight: 600; }
.cart-item .ci-price { font-size: 15px; font-weight: 600; color: var(--success); min-width:65px; text-align:right; }
.cart-item .ci-del {
    padding: 4px 8px; border: none; background: transparent;
    color: var(--danger); font-size: 13px; cursor: pointer;
}
.cart-footer {
    background: var(--bg-elevated); padding: 12px 14px;
    border-top: 1px solid var(--border); flex-shrink: 0;
}
.cart-footer .cf-total {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px;
}
.cart-footer .cf-label { font-size: 13px; color: var(--text-secondary); }
.cart-footer .cf-amount { font-size: 20px; font-weight: 700; color: var(--success); }
.cart-footer .btn-confirm {
    width: 100%; padding: 14px; border: none; border-radius: 12px;
    background: linear-gradient(135deg, #34d399, #10b981);
    color: #fff; font-size: 16px; font-weight: 600; cursor: pointer;
}
.cart-footer .btn-confirm:disabled { opacity: 0.3; }
.cart-footer .btn-confirm:active:not(:disabled) { transform: scale(0.98); }

/* Toast */
.toast {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%);
    background: rgba(0,0,0,0.85); color: #fff; padding: 16px 24px;
    border-radius: 12px; font-size: 15px; z-index: 999;
    text-align: center; max-width: 80%;
    opacity: 0; transition: opacity 0.2s; pointer-events: none;
}
.toast.show { opacity: 1; }

.hidden { display: none !important; }
</style>
</head>
<body>

<div class="header">
    <span class="title">超级管理</span>
    <select class="store-select" id="storeSelect" onchange="switchStore(this.value)">
        <option value="">全平台</option>
        <?php foreach ($allStores as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $currentViewStoreId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <a class="logout" href="#" onclick="event.preventDefault();logout()">退出</a>
</div>

<div class="tab-bar">
    <button class="tab-btn active" id="tabLiveBtn" onclick="switchTab('live')">直播</button>
    <button class="tab-btn" id="tabOutboundBtn" onclick="switchTab('outbound')">出库</button>
</div>

<!-- ═══════ Tab 1: 直播 ═══════ -->
<div class="tab-content active" id="tabLive">
    <div class="session-banner" id="sessionBanner">
        <span id="sessionStatus">检查中...</span>
        <span style="margin-left:8px;font-size:12px;color:var(--primary);cursor:pointer;" onclick="loadLiveData()" title="刷新库存">↻ 刷新</span>
    </div>
    <div class="live-grid-wrap" id="liveGridWrap">
        <div class="live-grid" id="liveGrid"></div>
        <div style="text-align:center;padding:40px;color:var(--text-tertiary);" id="liveEmpty">加载中...</div>
    </div>
    <div class="live-search-bar">
        <input type="text" id="liveSearch" placeholder="过滤商品..." oninput="filterLiveGrid()" autocomplete="off">
    </div>
</div>

<!-- ═══════ Tab 2: 出库 ═══════ -->
<div class="tab-content" id="tabOutbound">
    <div id="scanner-section">
        <div id="camera-view">
            <video id="video" playsinline autoplay muted style="width:100%;height:100%;object-fit:cover;display:none;"></video>
            <div class="placeholder" id="cameraPlaceholder">
                <span class="icon" id="camIcon">📷</span>
                <span id="cameraStatus">相机未启动</span>
            </div>
        </div>
        <div class="scan-overlay" id="scanOverlay" style="display:none;">
            <div class="scan-frame">
                <div class="corner tl"></div><div class="corner tr"></div>
                <div class="corner bl"></div><div class="corner br"></div>
            </div>
        </div>
        <div class="scanner-hint">将条形码对准框内自动扫描</div>
    </div>
    <div id="scanStatusBar" style="padding:6px 14px;background:rgba(52,211,153,0.1);border-bottom:1px solid var(--border);font-size:12px;color:var(--success);text-align:center;display:none;">自动扫描中...</div>
    <div class="outbound-manual">
        <input type="text" id="obSearch" placeholder="拼音搜索或扫码..." autocomplete="off">
        <button onclick="obManualSearch()" style="background:var(--primary);">查询</button>
        <button id="cameraToggleBtn" class="btn-camera" onclick="toggleObCamera()" style="background:#34d399;">📷 打开相机</button>
    </div>
    <div id="pinyinDropdown"></div>
    <div id="scanResult">
        <div class="sr-name" id="srName"></div>
        <div class="sr-meta"><span id="srBarcode"></span> <span id="srSeries"></span></div>
        <div class="sr-form">
            <select id="srCondition"></select>
            <input type="number" id="srQty" value="1" min="1">
            <input type="number" id="srPrice" class="price-inp" step="0.01" placeholder="售价">
            <button class="btn-add" id="btnAddCart" onclick="obAddToCart()">+ 添加</button>
        </div>
        <div style="font-size:11px;color:var(--text-tertiary);margin-top:6px;" id="srStockInfo"></div>
    </div>
    <div class="outbound-cart">
        <div class="cart-header">
            <span class="title">待出库</span>
            <span class="count" id="cartCount">0 件</span>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="cart-empty">扫码或拼音搜索添加出库商品</div>
        </div>
        <div class="cart-footer">
            <div class="cf-total">
                <span class="cf-label">合计</span>
                <span class="cf-amount" id="cartTotal">¥0.00</span>
            </div>
            <button class="btn-confirm" id="confirmBtn" disabled onclick="obConfirm()">确认出库</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
var CONDITION_TYPES = <?= $conditionTypesJson ?>;
var CONDITION_KEYS = {}; CONDITION_TYPES.forEach(function(c,i) { CONDITION_KEYS[c.key] = c.name; });

// ── Common ──
var currentTab = 'live';

function switchTab(tab) {
    currentTab = tab;
    document.getElementById('tabLive').classList.toggle('active', tab === 'live');
    document.getElementById('tabOutbound').classList.toggle('active', tab === 'outbound');
    document.getElementById('tabLiveBtn').classList.toggle('active', tab === 'live');
    document.getElementById('tabOutboundBtn').classList.toggle('active', tab === 'outbound');
    if (tab === 'live') { loadLiveData(); }
    if (tab === 'outbound') { initObTab(); }
}

async function logout() {
    try { await fetch('../api/logout.php'); } catch(e) {}
    window.location.href = '../login.php';
}

async function switchStore(storeId) {
    try {
        var res = await fetch('../api/switch_store.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({store_id: storeId ? parseInt(storeId) : null})
        });
        var data = await res.json();
        if (data.success) { window.location.reload(); }
        else { showToast('切换失败: ' + (data.error || '未知错误')); }
    } catch(e) { showToast('切换失败'); }
}

function showToast(msg) {
    var el = document.getElementById('toast');
    el.textContent = msg; el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(function() { el.classList.remove('show'); }, 2000);
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function getCtColor(key) {
    for (var i = 0; i < CONDITION_TYPES.length; i++) {
        if (CONDITION_TYPES[i].key === key) return CONDITION_TYPES[i].color;
    }
    return '#667eea';
}

/* ══════════════════════ Live Tab ══════════════════════ */
var liveSessionId = null;
var liveProducts = [];
var liveFiltered = [];

async function loadLiveData() {
    if (currentTab !== 'live') return;
    try {
        var r1 = await fetch('../api/list_live_inventory.php');
        var d1 = await r1.json();
        var banner = document.getElementById('sessionBanner');
        if (d1.success && d1.data && d1.data.session) {
            liveSessionId = d1.data.session.id;
            document.getElementById('sessionStatus').textContent = '直播中: ' + (d1.data.session.session_name || '');
            banner.className = 'session-banner live';
        } else {
            liveSessionId = null;
            document.getElementById('sessionStatus').textContent = '无活跃直播场次，无法售出/退还';
            banner.className = 'session-banner none';
        }
        liveProducts = (d1.success && d1.data && d1.data.products) ? d1.data.products : [];
    } catch(e) { liveSessionId = null; }

    renderLiveGrid();
}

function renderLiveGrid() {
    var products = liveFiltered.length ? liveFiltered : liveProducts;
    var grid = document.getElementById('liveGrid');
    var empty = document.getElementById('liveEmpty');

    if (!products.length) {
        empty.style.display = 'block';
        empty.textContent = liveSessionId ? '暂无有库存商品' : '请选择店铺并确保有活跃直播场次';
        grid.innerHTML = '';
        return;
    }
    empty.style.display = 'none';

    products.sort(function(a,b) {
        var aHas = hasAnyStock(a), bHas = hasAnyStock(b);
        if (aHas && !bHas) return -1;
        if (!aHas && bHas) return 1;
        return (a.common_name||a.name||'').length - (b.common_name||b.name||'').length;
    });

    grid.innerHTML = products.map(function(p) {
        var inv = p.inventory_summary || {};
        var rows = '';
        var hasStock = false;
        CONDITION_TYPES.forEach(function(c) {
            var info = inv[c.key];
            if (!info || parseInt(info.total_stock) <= 0) return;
            hasStock = true;
            var stock = parseInt(info.total_stock);
            rows += '<div class="sku-row" data-pid="' + p.id + '" data-cond="' + c.key + '" data-price="' + (info.suggested_price||0) + '">' +
                '<span class="sku-badge" style="background:' + getCtColor(c.key) + '22;color:' + getCtColor(c.key) + '">' + c.name + '</span>' +
                '<span class="sku-price">¥' + parseFloat(info.suggested_price||0).toFixed(0) + '</span>' +
                '<span class="sku-stock' + (stock <= 2 ? ' low' : '') + '">' + stock + '</span>' +
                '</div>';
        });
        var soldOut = hasStock ? '' : ' sold-out';
        return '<div class="product-card' + soldOut + '" data-pid="' + p.id + '">' +
            '<div class="card-name" title="' + escapeHtml(p.common_name||p.name) + '">' + escapeHtml(p.common_name||p.name) + '</div>' +
            '<div class="sku-list">' + (rows || '<div style="font-size:12px;color:var(--text-tertiary);padding:8px 0;">已售罄</div>') + '</div>' +
            '</div>';
    }).join('');

    // Click handlers
    grid.querySelectorAll('.sku-stock, .sku-badge').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            var row = this.closest('.sku-row');
            liveSell(row.dataset.pid, row.dataset.cond, parseFloat(row.dataset.price));
        });
    });
    grid.querySelectorAll('.sku-price').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            var row = this.closest('.sku-row');
            liveReturn(row.dataset.pid, row.dataset.cond);
        });
    });
}

function filterLiveGrid() {
    var kw = document.getElementById('liveSearch').value.trim().toLowerCase();
    if (!kw) { liveFiltered = []; renderLiveGrid(); return; }
    liveFiltered = liveProducts.filter(function(p) {
        return (p.name||'').toLowerCase().indexOf(kw) >= 0 ||
               (p.common_name||'').toLowerCase().indexOf(kw) >= 0 ||
               (p.barcode||'').indexOf(kw) >= 0;
    });
    renderLiveGrid();
}

async function liveSell(pid, cond, price) {
    if (!liveSessionId) { showToast('无活跃直播场次'); return; }
    showToast('售出中...');
    try {
        var res = await fetch('../api/sell_product_live.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_id: parseInt(pid), condition_type: cond, sale_price: price, qty: 1, live_session_id: liveSessionId})
        });
        var d = await res.json();
        if (d.success) {
            updateLocalStock(pid, cond, -1);
            showToast('售出 -1');
        } else {
            showToast(d.error || '售出失败');
        }
    } catch(e) { showToast('网络错误'); }
}

async function liveReturn(pid, cond) {
    if (!liveSessionId) { showToast('无活跃直播场次'); return; }
    showToast('退还中...');
    try {
        var res = await fetch('../api/return_product_live.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product_id: parseInt(pid), condition_type: cond, live_session_id: liveSessionId})
        });
        var d = await res.json();
        if (d.success) {
            updateLocalStock(pid, cond, 1);
            showToast('退还 +1');
        } else {
            showToast(d.error || '退还失败');
        }
    } catch(e) { showToast('网络错误'); }
}

function updateLocalStock(pid, cond, delta) {
    // 更新数据
    var p = liveProducts.find(function(x) { return x.id == pid; });
    if (!p) return;
    var summary = p.inventory_summary = p.inventory_summary || {};
    var info = summary[cond];
    if (info) {
        info.total_stock = Math.max(0, (parseInt(info.total_stock)||0) + delta);
    } else if (delta > 0) {
        summary[cond] = {total_stock: delta, suggested_price: 0};
        info = summary[cond];
    }

    // 直接更新 DOM，避免全量重绘导致卡片闪烁
    var row = document.querySelector('.sku-row[data-pid="' + pid + '"][data-cond="' + cond + '"]');
    var stock = parseInt(info ? info.total_stock : 0);

    if (stock <= 0) {
        if (row) {
            var list = row.parentElement;
            row.remove();
            if (list && !list.querySelector('.sku-row')) {
                var card = list.closest('.product-card');
                if (card) { card.classList.add('sold-out'); }
            }
        }
        return;
    }

    // 库存 > 0，但 DOM 行不存在（退还后恢复）→ 局部重绘该卡片
    if (!row) {
        var oldCard = document.querySelector('.product-card[data-pid="' + pid + '"]');
        if (oldCard) {
            oldCard.outerHTML = buildCardHTML(p);
            rebindCard(document.querySelector('.product-card[data-pid="' + pid + '"]'));
        }
        return;
    }

    var stockEl = row.querySelector('.sku-stock');
    stockEl.textContent = stock;
    stockEl.className = 'sku-stock' + (stock <= 2 ? ' low' : '');
}

function hasAnyStock(p) {
    var inv = p.inventory_summary || {};
    return Object.values(inv).some(function(v) { return parseInt(v.total_stock) > 0; });
}

function buildCardHTML(p) {
    var inv = p.inventory_summary || {};
    var rows = '';
    CONDITION_TYPES.forEach(function(c) {
        var info = inv[c.key];
        if (!info || parseInt(info.total_stock) <= 0) return;
        var stock = parseInt(info.total_stock);
        rows += '<div class="sku-row" data-pid="' + p.id + '" data-cond="' + c.key + '" data-price="' + (info.suggested_price||0) + '">' +
            '<span class="sku-badge" style="background:' + getCtColor(c.key) + '22;color:' + getCtColor(c.key) + '">' + c.name + '</span>' +
            '<span class="sku-price">¥' + parseFloat(info.suggested_price||0).toFixed(0) + '</span>' +
            '<span class="sku-stock' + (stock <= 2 ? ' low' : '') + '">' + stock + '</span>' +
            '</div>';
    });
    if (!rows) return '';
    return '<div class="product-card" data-pid="' + p.id + '">' +
        '<div class="card-name" title="' + escapeHtml(p.common_name||p.name) + '">' + escapeHtml(p.common_name||p.name) + '</div>' +
        '<div class="sku-list">' + rows + '</div>' +
        '</div>';
}

function rebindCard(cardEl) {
    if (!cardEl) return;
    cardEl.querySelectorAll('.sku-stock, .sku-badge').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            var row = this.closest('.sku-row');
            liveSell(row.dataset.pid, row.dataset.cond, parseFloat(row.dataset.price));
        });
    });
    cardEl.querySelectorAll('.sku-price').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.stopPropagation();
            var row = this.closest('.sku-row');
            liveReturn(row.dataset.pid, row.dataset.cond);
        });
    });
}

/* ══════════════════════ Outbound Tab ══════════════════════ */
var obBatches = [];
var obCart = [];
var obScannerOn = false;
var obVideoStream = null;
var obScanInterval = null;
var obScanCooldown = false;
var obScanMethod = null;
var obLibScanner = null;
var obSearchTimer = null;

function initObTab() {
    if (currentTab !== 'outbound') return;
    var inp = document.getElementById('obSearch');
    inp.addEventListener('input', obHandleInput);
    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var val = this.value.trim();
            if (val) { obHandleBarcode(val); this.value = ''; }
            document.getElementById('pinyinDropdown').classList.remove('show');
        }
    });
    document.getElementById('srCondition').addEventListener('change', obUpdatePrice);
}

// ── Camera scanner ──
function updateObCameraBtn() {
    var btn = document.getElementById('cameraToggleBtn');
    if (obScannerOn) {
        btn.textContent = '📷 关闭相机';
        btn.style.background = '#34d399';
    } else {
        btn.textContent = '📷 打开相机';
        btn.style.background = '#667eea';
    }
}

function toggleObCamera() {
    if (obScannerOn) {
        obStopScanner();
        obScannerOn = false;
        updateObCameraBtn();
        document.getElementById('scanOverlay').style.display = 'none';
        document.getElementById('scanStatusBar').style.display = 'none';
        document.getElementById('cameraPlaceholder').style.display = 'flex';
        document.getElementById('camIcon').textContent = '⏸';
        document.getElementById('cameraStatus').textContent = '相机已关闭';
    } else {
        obStartScanner();
    }
}

async function obCheckBarcodeDetector() {
    try {
        if (window.BarcodeDetector) {
            var formats = await BarcodeDetector.getSupportedFormats();
            if (formats && formats.length > 0) return true;
        }
    } catch(e) {}
    return false;
}

async function obStartScanner() {
    document.getElementById('cameraStatus').textContent = '启动中...';
    obStopScanner();
    var video = document.getElementById('video');
    video.style.display = 'none';
    document.getElementById('cameraPlaceholder').style.display = 'flex';

    try {
        obVideoStream = await navigator.mediaDevices.getUserMedia({
            video: {facingMode: "environment", width: {ideal: 640}, height: {ideal: 480}}
        });
        video.srcObject = obVideoStream;
        await video.play();
        video.style.display = 'block';
        document.getElementById('cameraPlaceholder').style.display = 'none';

        var hasNative = await obCheckBarcodeDetector();
        var hasLib = (typeof Html5Qrcode !== 'undefined');

        if (hasNative) {
            obScanMethod = 'native';
            document.getElementById('scanOverlay').style.display = 'flex';
            document.getElementById('cameraStatus').textContent = '扫描就绪';
            obStartNative(video);
        } else if (hasLib) {
            obScanMethod = 'library';
            document.getElementById('scanOverlay').style.display = 'flex';
            document.getElementById('cameraStatus').textContent = '扫描就绪';
            obStartLibrary(video);
        } else {
            document.getElementById('cameraStatus').textContent = '不支持自动扫码';
            document.getElementById('scanOverlay').style.display = 'none';
            showToast('请使用拼音搜索或手动输入');
        }
        obScannerOn = true;
        updateObCameraBtn();
    } catch(e) {
        var msg = e.message || '';
        if (msg.indexOf('NotAllowed')>=0) msg = '相机权限被拒绝';
        else if (msg.indexOf('NotFound')>=0) msg = '未找到摄像头';
        document.getElementById('cameraStatus').textContent = msg;
        obScannerOn = false;
        updateObCameraBtn();
    }
}

function obStartNative(video) {
    var bar = document.getElementById('scanStatusBar');
    bar.style.display = 'block'; bar.textContent = '自动扫描中...';
    var detector = new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','upc_a','upc_e','code_93','itf']});
    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d');

    obScanInterval = setInterval(async function() {
        if (obScanCooldown) return;
        try {
            var vw = video.videoWidth, vh = video.videoHeight;
            if (!vw || !vh) return;
            var s = Math.min(1, 480/Math.max(vw,vh));
            canvas.width = Math.round(vw*s); canvas.height = Math.round(vh*s);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            var codes = await detector.detect(canvas);
            if (codes && codes.length) {
                obScanCooldown = true;
                setTimeout(function(){obScanCooldown=false;}, 2000);
                bar.textContent = '已识别: ' + codes[0].rawValue;
                obHandleBarcode(codes[0].rawValue.trim());
            }
        } catch(e) {}
    }, 200);
}

function obStartLibrary(video) {
    var bar = document.getElementById('scanStatusBar');
    bar.style.display = 'block'; bar.textContent = '启动扫码引擎...';
    if (obVideoStream) { obVideoStream.getTracks().forEach(function(t){t.stop();}); obVideoStream = null; }
    video.srcObject = null; video.style.display = 'none';

    obLibScanner = new Html5Qrcode("camera-view");
    obLibScanner.start(
        {facingMode:"environment"}, {fps:10, qrbox:{width:280,height:280}},
        function(text) {
            if (obScanCooldown) return;
            obScanCooldown = true;
            setTimeout(function(){obScanCooldown=false;}, 2000);
            bar.textContent = '已识别: ' + text;
            obHandleBarcode(text.trim());
        },
        function(){}
    ).then(function() {
        bar.textContent = '自动扫描中...';
        document.getElementById('scanOverlay').style.display = 'flex';
        document.getElementById('cameraPlaceholder').style.display = 'none';
    }).catch(function(e) {
        bar.textContent = '引擎启动失败';
    });
}

function obStopScanner() {
    if (obLibScanner) { try{obLibScanner.stop();}catch(e){} try{obLibScanner.clear();}catch(e){} obLibScanner = null; }
    if (obScanInterval) { clearInterval(obScanInterval); obScanInterval = null; }
    if (obVideoStream) { obVideoStream.getTracks().forEach(function(t){t.stop();}); obVideoStream = null; }
}

// ── Pinyin search ──
function obHandleInput(e) {
    clearTimeout(obSearchTimer);
    var val = e.target.value.trim();
    if (!val) { document.getElementById('pinyinDropdown').classList.remove('show'); return; }
    if (/^\d+$/.test(val)) {
        document.getElementById('pinyinDropdown').classList.remove('show');
        if (val.length >= 5) { obHandleBarcode(val); e.target.value = ''; }
        return;
    }
    if (!/[a-zA-Z]/.test(val)) { document.getElementById('pinyinDropdown').classList.remove('show'); return; }
    obSearchTimer = setTimeout(function() { obPinyinSearch(val); }, 250);
}

async function obPinyinSearch(kw) {
    try {
        var res = await fetch('../api/search_outbound_stock.php?keyword=' + encodeURIComponent(kw));
        var d = await res.json();
        if (!d.success) return;
        var items = d.data || [];

        // Merge by product_id + condition_type
        var merged = {};
        items.forEach(function(b) {
            var key = b.product_id + '_' + b.condition_type;
            if (!merged[key]) {
                merged[key] = {
                    product_id: b.product_id, product_name: b.common_name || b.product_name,
                    barcode: b.barcode, series: b.series, condition_type: b.condition_type,
                    condition_name: CONDITION_KEYS[b.condition_type] || b.condition_type,
                    total_stock: 0, suggested_price: 0, batches: []
                };
            }
            merged[key].total_stock += parseInt(b.remaining_qty);
            merged[key].suggested_price = Math.max(merged[key].suggested_price, parseFloat(b.suggested_price||0));
            merged[key].batches.push(b);
        });
        // Sort batches by purchased_at
        Object.values(merged).forEach(function(m) {
            m.batches.sort(function(a,b) { return (a.purchased_at||'').localeCompare(b.purchased_at||''); });
        });

        var results = Object.values(merged);
        window._lastPinyinResults = results;
        var dd = document.getElementById('pinyinDropdown');
        if (!results.length) {
            dd.innerHTML = '<div style="padding:16px;text-align:center;color:var(--text-tertiary);">未找到匹配商品</div>';
            dd.classList.add('show');
            return;
        }
        dd.innerHTML = results.map(function(r) {
            return '<div class="pinyin-item" onclick="obAddMerged(\'' + r.product_id + '\',\'' + r.condition_type + '\')">' +
                '<div style="flex:1;"><div class="pi-name">' + escapeHtml(r.product_name) + '</div>' +
                '<div style="font-size:11px;color:var(--text-tertiary);">' + escapeHtml(r.barcode||'') + ' · ' + escapeHtml(r.condition_name) + '</div></div>' +
                '<div class="pi-stock">库存 ' + r.total_stock + '</div>' +
                '<div class="pi-add">添加</div></div>';
        }).join('');
        dd.classList.add('show');
    } catch(e) {}
}

function obAddMerged(pid, cond) {
    document.getElementById('pinyinDropdown').classList.remove('show');
    document.getElementById('obSearch').value = '';
    if (!window._lastPinyinResults) return;
    var found = window._lastPinyinResults.find(function(r) { return r.product_id == pid && r.condition_type == cond; });
    if (found) {
        obUpsertMerged(found, 1, found.suggested_price, found.product_name);
        obRenderCart();
        showToast('已添加');
    }
}

// ── Barcode handling ──
async function obHandleBarcode(barcode) {
    if (!barcode) return;
    showToast('查询: ' + barcode);
    try {
        var res = await fetch('../api/search_stock.php?barcode=' + encodeURIComponent(barcode));
        var d = await res.json();
        if (d.success && d.data && d.data.length) {
            obBatches = d.data;
            obShowScanResult(d.data);
        } else {
            showToast('未找到库存');
        }
    } catch(e) { showToast('查询失败'); }
}

function obShowScanResult(batches) {
    var first = batches[0];
    document.getElementById('srName').textContent = first.common_name || first.product_name;
    document.getElementById('srBarcode').textContent = first.barcode || '';
    document.getElementById('srSeries').textContent = first.series ? '· ' + first.series : '';

    // Merge by condition_type
    var merged = {}; var ctList = [];
    batches.forEach(function(b) {
        if (!merged[b.condition_type]) {
            merged[b.condition_type] = {key: b.condition_type, name: CONDITION_KEYS[b.condition_type]||b.condition_type, stock: 0, price: 0, batches: []};
            ctList.push(merged[b.condition_type]);
        }
        merged[b.condition_type].stock += b.remaining_qty;
        merged[b.condition_type].price = Math.max(merged[b.condition_type].price, parseFloat(b.suggested_price||0));
        merged[b.condition_type].batches.push(b);
    });
    ctList.forEach(function(m) {
        m.batches.sort(function(a,b) { return (a.purchased_at||'').localeCompare(b.purchased_at||''); });
    });
    window._obMerged = ctList;

    var sel = document.getElementById('srCondition');
    sel.innerHTML = '';
    ctList.forEach(function(m, i) {
        var opt = document.createElement('option');
        opt.value = i; opt.textContent = m.name + ' (库存' + m.stock + ')';
        sel.appendChild(opt);
    });
    obUpdatePrice();
    document.getElementById('srQty').value = 1;
    document.getElementById('srStockInfo').textContent = '库存总量: ' + batches.reduce(function(s,b){return s+b.remaining_qty;}, 0);
    document.getElementById('scanResult').style.display = 'block';
    document.getElementById('srPrice').focus();
}

function obUpdatePrice() {
    var sel = document.getElementById('srCondition');
    var idx = parseInt(sel.value);
    if (window._obMerged && window._obMerged[idx]) {
        document.getElementById('srPrice').value = window._obMerged[idx].price.toFixed(2);
    }
}

function obManualSearch() {
    var val = document.getElementById('obSearch').value.trim();
    if (val) obHandleBarcode(val);
}

function obAddToCart() {
    var idx = parseInt(document.getElementById('srCondition').value);
    if (!window._obMerged || !window._obMerged[idx]) { showToast('请先扫码'); return; }
    var m = window._obMerged[idx];
    var qty = parseInt(document.getElementById('srQty').value) || 1;
    var price = parseFloat(document.getElementById('srPrice').value);
    if (!price || price <= 0) { showToast('请输入有效售价'); return; }
    if (qty > m.stock) { showToast('库存不足 (剩余' + m.stock + ')'); return; }

    obUpsertMerged(m, qty, price);
    obRenderCart();
    showToast('已添加');
}

function obUpsertMerged(m, qty, price, productName) {
    if (!qty) qty = 1;
    if (!price) price = m.price;
    if (!productName) productName = document.getElementById('srName').textContent || m.product_name || '';
    var key = m.condition_type || m.key;
    var name = m.condition_name || m.name;
    var existing = obCart.find(function(item) { return item.cond_key === key; });
    if (existing) {
        existing.qty = Math.min(existing.qty + qty, existing.stock);
        existing.price = price;
    } else {
        obCart.push({
            cond_key: key,
            cond_name: name,
            product_name: productName,
            batches: m.batches,
            stock: m.stock,
            price: price,
            qty: qty
        });
    }
}

// ── Cart ──
function obRenderCart() {
    var container = document.getElementById('cartItems');
    var countEl = document.getElementById('cartCount');
    var totalEl = document.getElementById('cartTotal');
    var btn = document.getElementById('confirmBtn');

    if (!obCart.length) {
        container.innerHTML = '<div class="cart-empty">扫码或拼音搜索添加出库商品</div>';
        countEl.textContent = '0 件';
        totalEl.textContent = '¥0.00';
        btn.disabled = true;
        return;
    }

    var totalQty = obCart.reduce(function(s,i){return s+i.qty;}, 0);
    var totalAmt = obCart.reduce(function(s,i){return s+i.price*i.qty;}, 0);
    countEl.textContent = totalQty + ' 件';
    totalEl.textContent = '¥' + totalAmt.toFixed(2);
    btn.disabled = false;

    container.innerHTML = obCart.map(function(item, idx) {
        return '<div class="cart-item">' +
            '<div class="ci-info"><div class="ci-name">' + escapeHtml(item.product_name) + '</div>' +
            '<div class="ci-sub">' + escapeHtml(item.cond_name) + '</div></div>' +
            '<div class="ci-qty">' +
                '<button onclick="obChangeQty(' + idx + ',-1)">−</button>' +
                '<span class="ci-num">' + item.qty + '</span>' +
                '<button onclick="obChangeQty(' + idx + ',1)">+</button>' +
            '</div>' +
            '<div class="ci-price">¥' + (item.price * item.qty).toFixed(2) + '</div>' +
            '<button class="ci-del" onclick="obRemove(' + idx + ')">✕</button>' +
            '</div>';
    }).join('');
}

function obChangeQty(idx, delta) {
    var n = obCart[idx].qty + delta;
    if (n <= 0) { obRemove(idx); return; }
    if (n > obCart[idx].stock) { showToast('超出库存'); return; }
    obCart[idx].qty = n;
    obRenderCart();
}

function obRemove(idx) { obCart.splice(idx, 1); obRenderCart(); }

async function obConfirm() {
    if (!obCart.length) return;
    // Build items from batches with FIFO allocation
    var items = [];
    obCart.forEach(function(ci) {
        var remaining = ci.qty;
        ci.batches.forEach(function(b) {
            if (remaining <= 0) return;
            var avail = parseInt(b.remaining_qty);
            var take = Math.min(remaining, avail);
            if (take > 0) {
                items.push({
                    batch_id: b.batch_id || b.id,
                    product_id: b.product_id,
                    condition_type: b.condition_type,
                    qty: take,
                    price: ci.price
                });
                remaining -= take;
            }
        });
    });

    var btn = document.getElementById('confirmBtn');
    btn.disabled = true; btn.textContent = '提交中...';

    try {
        var res = await fetch('../api/outbound_batch.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({items: items, order_no: null, remark: '超管手机出库'})
        });
        var d = await res.json();
        if (d.success) {
            showToast('出库成功！' + d.data.total_items + '件 ¥' + d.data.total_amount.toFixed(2));
            obCart = [];
            obRenderCart();
            document.getElementById('scanResult').style.display = 'none';
        } else {
            showToast(d.error || '出库失败');
        }
    } catch(e) { showToast('网络错误'); }
    btn.textContent = '确认出库';
    btn.disabled = obCart.length === 0;
}

// ── Init ──
loadLiveData();

// Close pinyin dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#pinyinDropdown') && !e.target.closest('#obSearch')) {
        document.getElementById('pinyinDropdown').classList.remove('show');
    }
});
</script>
</body>
</html>
