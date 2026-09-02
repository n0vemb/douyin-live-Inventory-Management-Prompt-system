<?php
/**
 * 直播返送屏（新版）— 纯展示工具，固定模板自动布局 + 自动缩放
 *
 * 定位：所有直播间主播共用的大屏，扫码/输入商品 → 实时展示该商品各 SKU 可用库存与价格。
 * 不挂场次、不做售卖/改价/广播，只读展示。
 *
 * 布局（1920×1080 虚拟画布，按窗口等比缩放，任意分辨率不变形）：
 *   顶部居中：商品名称 - 系列（参考价开关开启时下方显示参考价）
 *   左侧：商品图片
 *   中间：商品简介
 *   右侧：各 SKU（品相 + 建议价 + 进价[开关] + 可用数量）
 *
 * 实时可用库存 = 真实库存(inventory_batches.remaining_qty - locked_qty)
 *              − 全店所有进行中记账场次已录入的非赠品、非临时商品数量(live_ledger_item)
 * 停留结果页时每 2.5 秒自动轮询刷新；店铺设置页调整进价/参考价开关实时生效。
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
    width: 100vw;
    height: 100vh;
}

/* ── 虚拟画布：1920×1080，整体等比缩放居中 ── */
#stage {
    position: fixed; top: 50%; left: 50%;
    width: 1920px; height: 1080px;
    transform: translate(-50%, -50%) scale(1);
    transform-origin: center center;
    background: var(--bg);
    overflow: hidden;
}
#initialView, #productDisplay {
    position: absolute; inset: 0;
}

/* ── 初始画面（纯待机） ── */
#initialView {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 24px;
    z-index: 1;
}
#initialView .logo { max-width: 240px; max-height: 240px; border-radius: 24px; object-fit: contain; }
#initialView .store-name { font-size: 64px; font-weight: 800; letter-spacing: 3px; }
#initialView .hint { font-size: 30px; color: var(--text-tertiary); letter-spacing: 4px; }

/* ── 商品展示：固定模板自动布局 ── */
#productDisplay { display: none; z-index: 2; flex-direction: column; padding: 42px 56px 48px; }
#productDisplay.show { display: flex; }

.pd-header {
    flex-shrink: 0; text-align: center;
    padding-bottom: 22px;
    border-bottom: 2px solid var(--border);
}
.pd-title { font-size: 68px; font-weight: 800; line-height: 1.25; letter-spacing: 1px; }
.pd-ref { margin-top: 10px; font-size: 30px; color: var(--text-secondary); }

.pd-main { flex: 1; display: flex; gap: 40px; min-height: 0; padding-top: 28px; }

/* 左：图片 */
.pd-left {
    flex: 0 0 34%;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-card);
    border: 1px solid var(--border); border-radius: 22px;
    overflow: hidden; padding: 18px;
}
.pd-left img { max-width: 100%; max-height: 100%; object-fit: contain; }
.pd-left .no-image {
    color: var(--text-tertiary); font-size: 40px;
    display: flex; align-items: center; justify-content: center;
    width: 100%; height: 100%;
}
.pd-main.no-desc .pd-left { flex: 1 1 auto; }

/* 中：简介 */
.pd-middle { flex: 1; min-width: 0; padding: 12px 8px 0; }
#pdDesc {
    font-size: 36px; line-height: 1.75; color: var(--text);
    overflow: hidden;
    display: -webkit-box; -webkit-line-clamp: 9; -webkit-box-orient: vertical;
    word-break: break-word;
}

/* 右：SKU 列表 */
.pd-right { flex: 0 0 36%; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; }
.pd-right::-webkit-scrollbar { display: none; }
.pd-sku-title { font-size: 30px; color: var(--text-tertiary); margin-bottom: 14px; letter-spacing: 2px; }
.sku-row {
    display: flex; align-items: center; gap: 22px;
    padding: 16px 22px; margin-bottom: 14px;
    background: var(--bg-card);
    border: 1px solid var(--border); border-radius: 16px;
    flex-shrink: 0;
}
.sku-row.out-of-stock { opacity: 0.55; }
.sku-row.out-of-stock .sku-qty .num { color: var(--danger); }
.sku-row.low-stock { border-color: rgba(251, 191, 36, 0.5); background: rgba(251, 191, 36, 0.05); }
.sku-no {
    flex-shrink: 0; width: 44px; height: 44px; border-radius: 10px;
    background: var(--primary-light); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 800;
}
.sku-name { font-size: 34px; font-weight: 600; min-width: 180px; }
.sku-price-box { display: flex; flex-direction: column; }
.sku-price { font-size: 46px; font-weight: 800; color: var(--success); line-height: 1.1; }
.sku-cost { font-size: 24px; color: var(--text-tertiary); margin-top: 4px; }
.sku-qty { margin-left: auto; text-align: center; flex-shrink: 0; min-width: 120px; }
.sku-qty .num { font-size: 54px; font-weight: 800; color: var(--success); line-height: 1.1; }
.sku-qty .label { font-size: 24px; color: var(--text-tertiary); }
.sku-empty { color: var(--text-tertiary); font-size: 34px; padding: 40px 0; text-align: center; }

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

<div id="stage">
    <!-- 初始画面 -->
    <div id="initialView">
        <img id="storeLogo" class="logo" style="display:none" alt="logo">
        <div class="store-name" id="storeName">直播返送</div>
        <div class="hint">请扫描商品条码或输入关键词</div>
    </div>

    <!-- 商品展示 -->
    <div id="productDisplay">
        <div class="pd-header">
            <div class="pd-title" id="pdTitle">商品名称 - 系列</div>
            <div class="pd-ref" id="pdRef" style="display:none"></div>
        </div>
        <div class="pd-main" id="pdMain">
            <div class="pd-left" id="pdLeft">
                <img id="pdImage" style="display:none" alt="">
                <div class="no-image" id="pdNoImage">暂无图片</div>
            </div>
            <div class="pd-middle" id="pdMiddle">
                <div id="pdDesc"></div>
            </div>
            <div class="pd-right" id="pdRight">
                <div class="pd-sku-title">SKU 库存</div>
                <div id="pdSkus"></div>
            </div>
        </div>
    </div>
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
const STAGE_W = 1920, STAGE_H = 1080; // 虚拟画布尺寸，整体等比缩放
let systemSettings = { live_display: { elements: [] }, condition_types: [] };
let currentProduct = null;
let searchResults = [];
let searchSelectedIndex = -1;
let searchDebounce = null;
let toastTimer = null;
let lastConfigHash = '';

/* ================= 自动缩放 ================= */
function fitStage() {
    const stage = document.getElementById('stage');
    const s = Math.min(window.innerWidth / STAGE_W, window.innerHeight / STAGE_H);
    stage.style.transform = 'translate(-50%, -50%) scale(' + s + ')';
}

/* ================= 设置加载与实时同步 ================= */
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

function elementEnabled(type) {
    const list = (systemSettings.live_display && systemSettings.live_display.elements) || [];
    const found = list.find(el => el && el.type === type);
    return found ? found.enabled !== false : true;
}

function simpleHash(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash;
    }
    return hash.toString(36);
}

/**
 * 店铺设置页调整配置时实时同步（localStorage 'ppmart_temp_config'，200ms 轮询）。
 * 自动布局下只应用「显隐开关」（进价/参考价/简介/图片/SKU列表）与品相配置、店名/Logo。
 */
function checkForConfigUpdates() {
    try {
        const tempConfig = localStorage.getItem('ppmart_temp_config');
        if (!tempConfig) return;
        const hash = simpleHash(tempConfig);
        if (hash === lastConfigHash) return;
        lastConfigHash = hash;

        const newSettings = JSON.parse(tempConfig);
        if (!newSettings) return;
        if (newSettings.live_display) {
            systemSettings.live_display = newSettings.live_display;
        }
        if (newSettings.condition_types) {
            systemSettings.condition_types = newSettings.condition_types;
        }
        if (newSettings.store_name) {
            document.getElementById('storeName').textContent = newSettings.store_name;
        }
        if (newSettings.logo_path !== undefined) {
            const logo = document.getElementById('storeLogo');
            if (newSettings.logo_path) {
                logo.src = newSettings.logo_path;
                logo.style.display = 'block';
            } else {
                logo.style.display = 'none';
            }
        }
        if (currentProduct) {
            displayProduct(currentProduct);
        }
    } catch (e) {
        console.error('checkForConfigUpdates:', e);
    }
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

/* ================= 商品展示（固定模板自动布局） ================= */
function displayProduct(p) {
    currentProduct = p;
    document.getElementById('initialView').style.display = 'none';
    document.getElementById('productDisplay').classList.add('show');

    // 顶部居中：商品名称 - 系列
    const title = p.name + (p.series ? ' - ' + p.series : '');
    document.getElementById('pdTitle').textContent = title;

    // 参考价（开关开启时显示）
    const refEl = document.getElementById('pdRef');
    if (elementEnabled('suggestedPrice') && p.qiandao_price) {
        refEl.textContent = '参考价 ¥' + parseFloat(p.qiandao_price).toFixed(2);
        refEl.style.display = 'block';
    } else {
        refEl.style.display = 'none';
    }

    // 左：图片
    const leftEl = document.getElementById('pdLeft');
    const imgEl = document.getElementById('pdImage');
    const noImgEl = document.getElementById('pdNoImage');
    if (elementEnabled('image') && p.image_url) {
        imgEl.src = p.image_url;
        imgEl.style.display = 'block';
        noImgEl.style.display = 'none';
        leftEl.style.display = 'flex';
    } else {
        imgEl.style.display = 'none';
        noImgEl.style.display = 'flex';
        leftEl.style.display = elementEnabled('image') ? 'flex' : 'none';
    }

    // 中：简介
    const midEl = document.getElementById('pdMiddle');
    const descEl = document.getElementById('pdDesc');
    const hasDesc = elementEnabled('productDescription') && p.product_description;
    if (hasDesc) {
        descEl.textContent = p.product_description;
        midEl.style.display = 'block';
    } else {
        descEl.textContent = '';
        midEl.style.display = 'none';
    }
    document.getElementById('pdMain').classList.toggle('no-desc', !hasDesc);

    // 右：SKU 列表
    const skuWrap = document.getElementById('pdSkus');
    const rightEl = document.getElementById('pdRight');
    skuWrap.innerHTML = '';
    const showSkus = elementEnabled('condition');
    rightEl.style.display = showSkus ? 'flex' : 'none';

    if (showSkus) {
        const conditionTypes = (systemSettings.condition_types && systemSettings.condition_types.length)
            ? systemSettings.condition_types
            : [
                { key: 'sealed',  name: '原盒未拆', color: '#10b981' },
                { key: 'opened',  name: '拆盒无瑕', color: '#3b82f6' },
                { key: 'boxless', name: '无盒无瑕', color: '#f59e0b' },
                { key: 'flawed',  name: '微瑕',     color: '#ef4444' }
              ];
        const showCost = elementEnabled('purchasePrice');
        let anySku = false;

        conditionTypes.forEach((condition, index) => {
            const info = p.inventory[condition.key] || p.inventory[condition.name];
            if (!info) return;
            anySku = true;

            const row = document.createElement('div');
            row.className = 'sku-row';
            if (info.stock <= 0) row.classList.add('out-of-stock');
            else if (info.stock <= 2) row.classList.add('low-stock');

            const priceText = info.suggested_price != null
                ? '¥' + parseFloat(info.suggested_price).toFixed(2)
                : '--';
            const costText = showCost && info.purchase_price
                ? '进价 ¥' + String(info.purchase_price).split('/').map(x => parseFloat(x).toFixed(2)).join('/¥')
                : '';

            row.innerHTML =
                '<div class="sku-no">' + (index + 1) + '</div>' +
                '<div class="sku-name">' + escapeHtml(condition.name) + '</div>' +
                '<div class="sku-price-box">' +
                    '<div class="sku-price">' + priceText + '</div>' +
                    (costText ? '<div class="sku-cost">' + costText + '</div>' : '') +
                '</div>' +
                '<div class="sku-qty">' +
                    '<div class="num">' + (info.stock > 0 ? info.stock : 0) + '</div>' +
                    '<div class="label">可用</div>' +
                '</div>';
            skuWrap.appendChild(row);
        });

        if (!anySku) {
            skuWrap.innerHTML = '<div class="sku-empty">暂无库存</div>';
        }
    }
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
    }
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('.search-bar-container')) hideSearchResults();
});

window.addEventListener('resize', fitStage);

/* ================= 初始化 ================= */
loadSettings().then(() => {
    fitStage();
    document.getElementById('barcodeInput').focus();
    startPolling();
});
setInterval(checkForConfigUpdates, 200);
</script>
</body>
</html>
