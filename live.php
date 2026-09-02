<?php
/**
 * 直播返送屏（新版）— 纯展示工具
 *
 * 定位：所有直播间主播共用的大屏，扫码/输入商品 → 实时展示该商品各 SKU 可用库存与价格。
 * 不挂场次、不做售卖/改价/广播，只读展示。
 *
 * 实时可用库存 = 真实库存(inventory_batches.remaining_qty - locked_qty)
 *              − 全店所有进行中记账场次已录入的非赠品、非临时商品数量(live_ledger_item)
 * 停留结果页时每 2.5 秒自动轮询刷新，其他场次记账扣减后无需手动查询。
 *
 * 展示布局复用店铺设置 live_display.elements（商品名/系列/参考价/进价/简介/图片/品相列表
 * 的位置、字号、显隐、颜色），店铺设置里的开关与位置调整直接生效。
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>直播返送</title>
<style>
:root {
    --bg: #0a0a0f;
    --bg-card: #12121a;
    --bg-elevated: #1a1a26;
    --border: #2a2a3a;
    --text: #e8e8ed;
    --text-secondary: #9d9daf;
    --text-tertiary: #6b6b80;
    --primary: #5e5ce6;
    --primary-light: rgba(94, 92, 230, 0.12);
    --primary-glow: rgba(94, 92, 230, 0.3);
    --success: #34d399;
    --danger: #f87171;
    --warning: #fbbf24;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Microsoft YaHei", sans-serif;
    background: var(--bg);
    color: var(--text);
    overflow: hidden;
    height: 100vh;
}

/* ── 初始画面（纯待机，无动画） ── */
#initialView {
    position: fixed; inset: 0; z-index: 1;
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 22px;
}
#initialView .logo { max-width: 220px; max-height: 220px; border-radius: 22px; object-fit: contain; }
#initialView .store-name { font-size: 52px; font-weight: 800; letter-spacing: 2px; }
#initialView .hint { font-size: 24px; color: var(--text-tertiary); letter-spacing: 3px; }

/* ── 搜索栏 ── */
.search-bar-container {
    position: fixed; bottom: 60px; left: 50%; transform: translateX(-50%);
    width: 560px; max-width: 92vw; z-index: 100;
}
.search-bar {
    display: flex; align-items: center;
    background: rgba(30, 30, 50, 0.92);
    border: 1px solid var(--border); border-radius: 14px;
    padding: 0 16px; backdrop-filter: blur(12px);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.search-bar:focus-within { border-color: var(--primary); box-shadow: 0 0 20px var(--primary-glow); }
.search-bar .search-icon { font-size: 18px; margin-right: 10px; opacity: 0.5; flex-shrink: 0; }
.search-bar input {
    flex: 1; background: transparent; border: none; outline: none;
    color: var(--text); font-size: 20px; height: 48px; font-family: inherit;
}
.search-bar input::placeholder { color: var(--text-tertiary); }
.search-results {
    position: absolute; bottom: calc(100% + 8px); left: 0; right: 0;
    background: rgba(26, 26, 38, 0.97); border: 1px solid var(--border); border-radius: 12px;
    overflow: hidden; display: none; max-height: 320px; overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}
.search-results.show { display: block; }
.search-result-item {
    padding: 12px 16px; cursor: pointer; display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid rgba(42, 42, 58, 0.5);
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover, .search-result-item.active { background: rgba(94, 92, 230, 0.15); }
.result-name { font-size: 16px; font-weight: 500; }
.result-barcode { font-size: 12px; color: var(--text-tertiary); margin-top: 2px; }
.result-stock { margin-left: auto; font-size: 13px; color: var(--text-secondary); white-space: nowrap; }
.search-result-empty { padding: 20px; text-align: center; color: var(--text-tertiary); font-size: 14px; }

/* ── 商品展示（元素按 live_display 配置绝对定位） ── */
#productDisplay { position: fixed; inset: 0; z-index: 2; background: var(--bg); display: none; }
#productDisplay.show { display: block; }
.qd-element { position: absolute; }
.qd-element .no-image {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    color: var(--text-tertiary); font-size: 32px; background: var(--bg-card);
    border-radius: 16px; border: 1px dashed var(--border);
}
#pricesContainer {
    display: flex; flex-direction: column; overflow: hidden;
}
.price-row {
    display: flex; align-items: center; gap: 24px; padding: 8px 14px;
    border: 1px solid rgba(42, 42, 58, 0.6); border-radius: 10px;
    background: rgba(18, 18, 26, 0.7); flex-shrink: 0;
}
.price-row.out-of-stock { opacity: 0.5; }
.price-row.out-of-stock .stock-number { color: var(--danger); }
.price-row.low-stock { border-color: rgba(251, 191, 36, 0.5); background: rgba(251, 191, 36, 0.05); }
.condition-info { display: flex; align-items: center; gap: 12px; }
.condition-number {
    width: 34px; height: 34px; border-radius: 8px; background: var(--primary-light);
    color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;
}
.price-info { display: flex; flex-direction: column; }
.price-info .suggested-price { font-size: 16px; color: var(--text-tertiary); }
.live-price { font-weight: 700; }
.stock-info { margin-left: auto; text-align: center; min-width: 90px; }
.stock-number { font-size: 34px; font-weight: 800; color: var(--success); line-height: 1.1; }
.stock-label { font-size: 13px; color: var(--text-tertiary); }

/* ── 提示 ── */
#toast {
    position: fixed; top: 30px; left: 50%; transform: translateX(-50%);
    background: rgba(229, 96, 92, 0.94); color: #fff; padding: 10px 22px;
    border-radius: 8px; font-size: 16px; display: none; z-index: 200;
    box-shadow: 0 6px 20px rgba(0,0,0,0.4);
}
</style>
</head>
<body>

<!-- 初始画面 -->
<div id="initialView">
    <img id="storeLogo" class="logo" style="display:none" alt="logo">
    <div class="store-name" id="storeName">直播返送</div>
    <div class="hint">请扫描商品条码或输入关键词</div>
</div>

<!-- 商品展示 -->
<div id="productDisplay">
    <div class="qd-element" id="productNameElement"><div id="productName"></div></div>
    <div class="qd-element" id="productSeriesElement"><div id="productSeries"></div></div>
    <div class="qd-element" id="productCommonNameElement"><div id="productCommonName"></div></div>
    <div class="qd-element" id="suggestedPriceElement">参考价: <span id="qiandaoPrice"></span></div>
    <div class="qd-element" id="purchasePriceElement">进价: <span id="purchasePrice"></span></div>
    <div class="qd-element" id="productDescriptionElement"><div id="productDescription"></div></div>
    <div class="qd-element" id="productImageContainer">
        <img id="productImage" style="display:none" alt="">
        <div id="noImagePlaceholder" class="no-image">暂无图片</div>
    </div>
    <div class="qd-element" id="pricesContainer"></div>
</div>

<!-- 搜索栏 -->
<div class="search-bar-container">
    <div class="search-results" id="searchResults"></div>
    <div class="search-bar">
        <span class="search-icon">🔍</span>
        <input id="barcodeInput" autocomplete="off" placeholder="扫描条码 / 输入商品名称、拼音首字母..." autofocus>
    </div>
</div>

<div id="toast"></div>

<script>
/* ================= 配置 ================= */
const POLL_INTERVAL = 2500; // 轮询刷新间隔（毫秒）
let systemSettings = { live_display: { elements: [] }, condition_types: [] };
let currentProduct = null;
let searchResults = [];
let searchSelectedIndex = -1;
let searchDebounce = null;
let toastTimer = null;

const DEFAULT_ELEMENTS = {
    productName:       { enabled: true, left: 60, top: 60,  width: 900, height: 80,  fontSize: '72px', zIndex: 2 },
    productSeries:     { enabled: true, left: 60, top: 150, width: 600, height: 60,  fontSize: '48px', zIndex: 2 },
    commonName:        { enabled: true, left: 60, top: 220, width: 600, height: 80,  fontSize: '42px', zIndex: 2 },
    suggestedPrice:    { enabled: true, left: 60, top: 310, width: 500, height: 100, fontSize: '72px', zIndex: 2, color: '#e8e8ed' },
    purchasePrice:     { enabled: true, left: 60, top: 420, width: 500, height: 60,  fontSize: '28px', zIndex: 2, color: '#9d9daf' },
    productDescription:{ enabled: true, left: 60, top: 430, width: 800, height: 80,  fontSize: '32px', zIndex: 2 },
    image:             { enabled: true, left: 60, top: 540, width: 600, height: 600, fontSize: '0px',  zIndex: 1 },
    condition:         { enabled: true, left: 750, top: 450, width: 1100, height: 600, fontSize: '40px', zIndex: 1,
                         itemSpacing: 30, statusFontSize: '28px', statusColor: '#9d9daf',
                         priceFontSize: '46px', priceColor: '#34d399', priceOffsetX: 0, stockOffsetX: 0 }
};

function getElementConfig(type) {
    const list = (systemSettings.live_display && systemSettings.live_display.elements) || [];
    const found = list.find(el => el && el.type === type) || {};
    return Object.assign({}, DEFAULT_ELEMENTS[type] || {}, found);
}

/* ================= 设置加载 ================= */
function loadSettings() {
    return fetch('api/get_settings.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.settings) {
                systemSettings = Object.assign({}, systemSettings, data.settings);
                const s = systemSettings;
                if (s.store_name) document.getElementById('storeName').textContent = s.store_name;
                if (s.logo_path) {
                    const logo = document.getElementById('storeLogo');
                    logo.src = s.logo_path;
                    logo.style.display = 'block';
                }
            }
        })
        .catch(() => {});
}

/* ================= 查询 ================= */
function apiQuery(q) {
    return fetch('api/return_screen_search.php?q=' + encodeURIComponent(q)).then(r => r.json());
}
function apiRefresh(id) {
    return fetch('api/return_screen_search.php?product_id=' + id).then(r => r.json());
}

function processQuery(raw) {
    const q = String(raw || '').trim();
    if (!q) { hideSearchResults(); return; }
    apiQuery(q)
        .then(data => {
            if (!data.success) { showToast('❌ ' + (data.error || '查询失败')); return; }
            if (data.data.mode === 'exact' && data.data.product) {
                hideSearchResults();
                displayProduct(data.data.product);
            } else {
                searchResults = data.data.products || [];
                showSearchResults();
            }
        })
        .catch(() => showToast('❌ 查询失败'));
}

/* ================= 搜索下拉 ================= */
function showSearchResults() {
    const container = document.getElementById('searchResults');
    searchSelectedIndex = -1;
    if (!searchResults.length) {
        container.innerHTML = '<div class="search-result-empty">未找到匹配商品</div>';
        container.classList.add('show');
        return;
    }
    container.innerHTML = '';
    searchResults.forEach((product, index) => {
        const item = document.createElement('div');
        item.className = 'search-result-item';
        item.dataset.index = index;
        item.innerHTML =
            '<div>' +
                '<div class="result-name">' + escapeHtml(product.name) + '</div>' +
                '<div class="result-barcode">' + escapeHtml(product.barcode) + (product.series ? ' · ' + escapeHtml(product.series) : '') + '</div>' +
            '</div>' +
            '<div class="result-stock">可用 ' + (product.available_total > 0 ? product.available_total : 0) + '</div>';
        item.addEventListener('click', () => selectSearchResult(index));
        container.appendChild(item);
    });
    container.classList.add('show');
}

function hideSearchResults() {
    document.getElementById('searchResults').classList.remove('show');
    searchResults = [];
    searchSelectedIndex = -1;
}

function highlightSearchItem() {
    const items = document.getElementById('searchResults').querySelectorAll('.search-result-item');
    items.forEach((item, index) => {
        item.classList.toggle('active', index === searchSelectedIndex);
        if (index === searchSelectedIndex) item.scrollIntoView({ block: 'nearest' });
    });
}

function selectSearchResult(index) {
    const product = searchResults[index];
    if (!product) return;
    hideSearchResults();
    document.getElementById('barcodeInput').value = '';
    apiRefresh(product.id)
        .then(data => {
            if (data.success && data.data && data.data.product) {
                displayProduct(data.data.product);
            } else {
                showToast('❌ 加载商品失败');
            }
        })
        .catch(() => showToast('❌ 加载商品失败'));
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

/* ================= 商品展示 ================= */
function displayProduct(p) {
    currentProduct = p;
    document.getElementById('initialView').style.display = 'none';
    document.getElementById('productDisplay').classList.add('show');

    // 商品名
    const nameEl = document.getElementById('productNameElement');
    const nameCfg = getElementConfig('productName');
    if (nameCfg.enabled && p.name) {
        document.getElementById('productName').textContent = p.name;
        applyElementStyle(nameEl, nameCfg);
    } else nameEl.style.display = 'none';

    // 系列
    const seriesEl = document.getElementById('productSeriesElement');
    const seriesCfg = getElementConfig('productSeries');
    if (seriesCfg.enabled && p.series) {
        document.getElementById('productSeries').textContent = p.series;
        applyElementStyle(seriesEl, seriesCfg);
    } else seriesEl.style.display = 'none';

    // 常用名
    const cnEl = document.getElementById('productCommonNameElement');
    const cnCfg = getElementConfig('commonName');
    if (cnCfg.enabled && p.common_name) {
        document.getElementById('productCommonName').textContent = p.common_name;
        applyElementStyle(cnEl, cnCfg);
    } else cnEl.style.display = 'none';

    // 参考价（商品 qiandao_price）
    const refEl = document.getElementById('suggestedPriceElement');
    const refCfg = getElementConfig('suggestedPrice');
    if (refCfg.enabled) {
        document.getElementById('qiandaoPrice').textContent = '¥' + parseFloat(p.qiandao_price || 0).toFixed(2);
        document.getElementById('qiandaoPrice').style.color = refCfg.color || '#e8e8ed';
        applyElementStyle(refEl, refCfg);
        refEl.style.display = 'flex';
        refEl.style.alignItems = 'center';
    } else refEl.style.display = 'none';

    // 进价（店铺设置开关控制显隐）
    const costEl = document.getElementById('purchasePriceElement');
    const costCfg = getElementConfig('purchasePrice');
    if (costCfg.enabled && p.purchase_prices) {
        document.getElementById('purchasePrice').textContent =
            '¥' + String(p.purchase_prices).split('/').map(x => parseFloat(x).toFixed(2)).join('/¥');
        document.getElementById('purchasePrice').style.color = costCfg.color || '#9d9daf';
        applyElementStyle(costEl, costCfg);
        costEl.style.display = 'flex';
        costEl.style.alignItems = 'center';
    } else costEl.style.display = 'none';

    // 产品简介
    const descEl = document.getElementById('productDescriptionElement');
    const descCfg = getElementConfig('productDescription');
    if (descCfg.enabled && p.product_description) {
        document.getElementById('productDescription').textContent = p.product_description;
        applyElementStyle(descEl, descCfg);
    } else descEl.style.display = 'none';

    // 图片
    const imgWrap = document.getElementById('productImageContainer');
    const imgCfg = getElementConfig('image');
    if (imgCfg.enabled) {
        applyElementStyle(imgWrap, imgCfg);
        imgWrap.style.display = 'flex';
        imgWrap.style.alignItems = 'center';
        imgWrap.style.justifyContent = 'center';
        if (p.image_url) {
            document.getElementById('productImage').src = p.image_url;
            document.getElementById('productImage').style.display = 'block';
            document.getElementById('productImage').style.width = '100%';
            document.getElementById('productImage').style.height = '100%';
            document.getElementById('productImage').style.objectFit = 'contain';
            document.getElementById('noImagePlaceholder').style.display = 'none';
        } else {
            document.getElementById('productImage').style.display = 'none';
            document.getElementById('noImagePlaceholder').style.display = 'flex';
        }
    } else imgWrap.style.display = 'none';

    // 品相列表
    const listEl = document.getElementById('pricesContainer');
    const condCfg = getElementConfig('condition');
    applyElementStyle(listEl, condCfg);
    listEl.style.gap = (condCfg.itemSpacing || 30) + 'px';
    listEl.style.display = condCfg.enabled ? 'flex' : 'none';
    listEl.innerHTML = '';

    const conditionTypes = (systemSettings.condition_types && systemSettings.condition_types.length)
        ? systemSettings.condition_types
        : [
            { key: 'sealed',  name: '原盒未拆', color: '#10b981' },
            { key: 'opened',  name: '拆盒无瑕', color: '#3b82f6' },
            { key: 'boxless', name: '无盒无瑕', color: '#f59e0b' },
            { key: 'flawed',  name: '微瑕',     color: '#ef4444' }
          ];

    conditionTypes.forEach((condition, index) => {
        const info = p.inventory[condition.key] || p.inventory[condition.name];
        if (!info) return;

        const row = document.createElement('div');
        row.className = 'price-row';
        if (info.stock <= 0) row.classList.add('out-of-stock');
        else if (info.stock <= 2) row.classList.add('low-stock');

        row.innerHTML =
            '<div class="condition-info">' +
                '<div class="condition-number">' + (index + 1) + '</div>' +
                '<div class="condition-name" style="font-size:' + (condCfg.statusFontSize || '28px') +
                    ';color:' + (condCfg.statusColor || '#9d9daf') + '">' + escapeHtml(condition.name) + '</div>' +
            '</div>' +
            '<div class="price-info" style="transform:translateX(' + (condCfg.priceOffsetX || 0) + 'px)">' +
                '<div class="live-price" style="font-size:' + (condCfg.priceFontSize || '46px') +
                    ';color:' + (condCfg.priceColor || '#34d399') + '">¥' + parseFloat(info.suggested_price || 0).toFixed(2) + '</div>' +
            '</div>' +
            '<div class="stock-info" style="transform:translateX(' + (condCfg.stockOffsetX || 0) + 'px)">' +
                '<div class="stock-number">' + (info.stock > 0 ? info.stock : 0) + '</div>' +
                '<div class="stock-label">可用</div>' +
            '</div>';
        listEl.appendChild(row);
    });
}

function applyElementStyle(el, cfg) {
    el.style.position = 'absolute';
    el.style.left = cfg.left + 'px';
    el.style.top = cfg.top + 'px';
    el.style.width = cfg.width + 'px';
    el.style.minHeight = cfg.height + 'px';
    el.style.zIndex = cfg.zIndex || 1;
    el.style.fontSize = cfg.fontSize || '28px';
    el.style.display = 'block';
}

/* ================= 轮询刷新 ================= */
function startPolling() {
    setInterval(() => {
        if (!currentProduct) return;
        apiRefresh(currentProduct.id)
            .then(data => {
                if (data.success && data.data && data.data.product) {
                    displayProduct(data.data.product);
                }
            })
            .catch(() => {});
    }, POLL_INTERVAL);
}

/* ================= 提示 ================= */
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.style.display = 'none'; }, 2500);
}

/* ================= 事件 ================= */
document.getElementById('barcodeInput').addEventListener('input', function () {
    const q = this.value.trim();
    clearTimeout(searchDebounce);
    if (!q) { hideSearchResults(); return; }
    searchDebounce = setTimeout(() => processQuery(q), 250);
});

document.getElementById('barcodeInput').addEventListener('keydown', function (e) {
    const isOpen = document.getElementById('searchResults').classList.contains('show');
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!searchResults.length) return;
        searchSelectedIndex = (searchSelectedIndex + 1) % searchResults.length;
        highlightSearchItem();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!searchResults.length) return;
        searchSelectedIndex = (searchSelectedIndex - 1 + searchResults.length) % searchResults.length;
        highlightSearchItem();
    } else if (e.key === 'Escape') {
        hideSearchResults();
    } else if (e.key === 'Enter') {
        if (isOpen && searchSelectedIndex >= 0) {
            e.preventDefault();
            selectSearchResult(searchSelectedIndex);
        }
        // Enter 本身交给 processQuery（扫码枪回车/手动回车都走完整查询）
    }
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('.search-bar-container')) hideSearchResults();
});

/* ================= 初始化 ================= */
loadSettings().then(() => {
    document.getElementById('barcodeInput').focus();
    startPolling();
});
</script>
</body>
</html>
