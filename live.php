<?php
/**
 * 直播返送屏（新版）— 纯展示工具，固定模板自动布局 + 自动缩放
 *
 * 定位：所有直播间主播共用的大屏，扫码/输入商品 → 实时展示该商品各 SKU 可用库存与价格。
 * 不挂场次、不做售卖/改价/广播，只读展示。
 *
 * 布局（全屏流体自适应：字号随 vmin 缩放、列随屏幕比例自动分配，
 *       竖屏/方形屏自动切换上下结构，适配电脑/一体机/安卓触摸屏）：
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

/* ── 全屏流体布局：字号随 vmin 缩放，列随屏幕比例自适应 ── */
html { font-size: clamp(8px, 1.1vmin, 24px); }
#stage {
    position: fixed; inset: 0;
    background: var(--bg);
    overflow: hidden;
}
#initialView, #productDisplay {
    position: absolute; inset: 0;
}

/* ── 初始画面（纯待机） ── */
#initialView {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2em;
    z-index: 1;
}
#initialView .logo { max-width: 20em; max-height: 20em; border-radius: 2em; object-fit: contain; }
#initialView .store-name { font-size: 5.3em; font-weight: 800; letter-spacing: 0.2em; }
#initialView .hint { font-size: 2.5em; color: var(--text-tertiary); letter-spacing: 0.3em; }

/* ── 商品展示：固定模板自动布局 ── */
#productDisplay { display: none; z-index: 2; flex-direction: column; padding: 3.5em 4.6em 4em; }
#productDisplay.show { display: flex; }

.pd-header {
    flex-shrink: 0; text-align: center;
    padding-bottom: 1.8em;
    border-bottom: 0.16em solid var(--border);
}
.pd-title { font-size: 5.6em; font-weight: 800; line-height: 1.25; letter-spacing: 0.06em; }
.pd-ref { margin-top: 0.8em; font-size: 2.5em; color: var(--text-secondary); }

.pd-main { flex: 1; display: flex; gap: 3.3em; min-height: 0; padding-top: 2.3em; }

/* 左：图片（固定 4:5 比例，尺寸随一屏可用高度自适应，宽度由比例推出） */
.pd-left {
    flex: none;
    height: 100%;
    aspect-ratio: 4 / 5;
    max-width: 100%;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-card);
    border: 0.08em solid var(--border); border-radius: 1.8em;
    overflow: hidden; padding: 1.5em;
}
.pd-left img { max-width: 100%; max-height: 100%; object-fit: contain; }
.pd-left .no-image {
    color: var(--text-tertiary); font-size: 3.3em;
    display: flex; align-items: center; justify-content: center;
    width: 100%; height: 100%;
}

/* 中：简介（与 SKU 容器平分剩余宽度，不写死大小） */
.pd-middle { flex: 1 1 0; min-width: 0; padding: 1em 0.7em 0; }
#pdDesc {
    font-size: 3em; line-height: 1.75; color: var(--text);
    overflow: hidden;
    display: -webkit-box; -webkit-line-clamp: 9; -webkit-box-orient: vertical;
    word-break: break-word;
}

/* 右：SKU 列表（与简介容器平分剩余宽度，不写死大小） */
.pd-right { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; }
.pd-right::-webkit-scrollbar { display: none; }
.pd-sku-title { font-size: 2.5em; color: var(--text-tertiary); margin-bottom: 1.2em; letter-spacing: 0.15em; }
.sku-row {
    display: flex; align-items: center; gap: 1.8em;
    padding: 1.3em 1.8em; margin-bottom: 1.2em;
    background: var(--bg-card);
    border: 0.08em solid var(--border); border-radius: 1.3em;
    flex-shrink: 0;
}
.sku-row.out-of-stock { opacity: 0.55; }
.sku-row.out-of-stock .sku-qty .num { color: var(--danger); }
.sku-row.low-stock { border-color: rgba(251, 191, 36, 0.5); background: rgba(251, 191, 36, 0.05); }
.sku-no {
    flex-shrink: 0; width: 3.6em; height: 3.6em; border-radius: 0.8em;
    background: var(--primary-light); color: var(--primary);
    display: flex; align-items: center; justify-content: center;
    font-size: 2em; font-weight: 800;
}
.sku-name { font-size: 2.8em; font-weight: 600; min-width: 14em; }
.sku-price-box { display: flex; flex-direction: column; }
.sku-price { font-size: 3.8em; font-weight: 800; color: var(--success); line-height: 1.1; }
.sku-cost { font-size: 2em; color: var(--text-tertiary); margin-top: 0.3em; }
.sku-qty { margin-left: auto; text-align: center; flex-shrink: 0; min-width: 9em; }
.sku-qty .num { font-size: 4.5em; font-weight: 800; color: var(--success); line-height: 1.1; }
.sku-qty .label { font-size: 2em; color: var(--text-tertiary); }
.sku-empty { color: var(--text-tertiary); font-size: 2.8em; padding: 3.3em 0; text-align: center; }

/* ── 竖屏 / 方形屏：图片+SKU 上下结构，简介沉底 ── */
@media (max-aspect-ratio: 1/1) {
    #productDisplay { padding: 2.6em 2.2em 3.2em; }
    .pd-main { flex-wrap: wrap; gap: 2.4em; padding-top: 1.8em; }
    /* 竖屏：图片按宽度 46% 定宽、4:5 推出高度，SKU 占其余宽度 */
    .pd-left { flex: none; width: 46%; height: auto; align-self: flex-start; }
    .pd-right { flex: 1 1 0; }
    .pd-middle { flex: 1 1 100%; order: 3; padding: 0.8em 0.4em 0; }
    #pdDesc { -webkit-line-clamp: 4; font-size: 2.6em; }
    .sku-row { padding: 1em 1.3em; gap: 1.3em; margin-bottom: 0.9em; }
    .sku-name { min-width: 9em; font-size: 2.4em; }
    .sku-price { font-size: 3.2em; }
    .sku-qty { min-width: 7em; }
    .sku-qty .num { font-size: 3.8em; }
    .pd-title { font-size: 4.6em; }
}

/* ── 搜索栏 ── */
.search-bar-container {
    position: fixed; bottom: 5vmin; left: 50%; transform: translateX(-50%);
    width: min(30vw, 640px); max-width: 92vw; z-index: 100;
}
.search-bar {
    display: flex; align-items: center;
    background: rgba(30, 30, 50, 0.92);
    border: 0.1em solid var(--border); border-radius: 1.2em;
    padding: 0 1.4em; backdrop-filter: blur(12px);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.search-bar:focus-within { border-color: var(--primary); box-shadow: 0 0 20px var(--primary-glow); }
.search-bar .search-icon { font-size: 1.6em; margin-right: 0.8em; opacity: 0.5; flex-shrink: 0; }
.search-bar input {
    flex: 1; background: transparent; border: none; outline: none;
    color: var(--text); font-size: clamp(16px, 2.2vmin, 24px); height: clamp(42px, 6vmin, 56px); font-family: inherit;
}
.search-bar input::placeholder { color: var(--text-tertiary); }
.search-results {
    position: absolute; bottom: calc(100% + 0.7em); left: 0; right: 0;
    background: rgba(26, 26, 38, 0.97); border: 0.1em solid var(--border); border-radius: 1em;
    overflow: hidden; display: none; max-height: 320px; overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
}
.search-results.show { display: block; }
.search-result-item {
    padding: 1em 1.4em; cursor: pointer; display: flex; align-items: center; gap: 1em;
    border-bottom: 1px solid rgba(42, 42, 58, 0.5);
}
.search-result-item:last-child { border-bottom: none; }
.search-result-item:hover, .search-result-item.active { background: rgba(94, 92, 230, 0.15); }
.result-name { font-size: 1.4em; font-weight: 500; }
.result-barcode { font-size: 1.1em; color: var(--text-tertiary); margin-top: 0.2em; }
.result-stock { margin-left: auto; font-size: 1.2em; color: var(--text-secondary); white-space: nowrap; }
.search-result-empty { padding: 1.8em; text-align: center; color: var(--text-tertiary); font-size: 1.2em; }

/* ── 提示 ── */
#toast {
    position: fixed; top: 3vmin; left: 50%; transform: translateX(-50%);
    background: rgba(229, 96, 92, 0.94); color: #fff; padding: 0.9em 1.8em;
    border-radius: 0.7em; font-size: clamp(14px, 1.8vmin, 20px); display: none; z-index: 200;
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
let systemSettings = { live_display: { elements: [] }, condition_types: [] };
let currentProduct = null;
let searchResults = [];
let searchSelectedIndex = -1;
let searchDebounce = null;
let toastTimer = null;
let lastConfigHash = '';

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
        // SKU 列表由后端按店铺配置(condition_common)返回，顺序与名称均动态
        const skus = p.sku_list || [];
        const showCost = elementEnabled('purchasePrice');
        let anySku = false;

        skus.forEach((sku, index) => {
            anySku = true;

            const row = document.createElement('div');
            row.className = 'sku-row';
            if (sku.stock <= 0) row.classList.add('out-of-stock');
            else if (sku.stock <= 2) row.classList.add('low-stock');

            const priceText = sku.suggested_price != null
                ? '¥' + parseFloat(sku.suggested_price).toFixed(2)
                : '--';
            const costText = showCost && sku.purchase_price
                ? '进价 ¥' + String(sku.purchase_price).split('/').map(x => parseFloat(x).toFixed(2)).join('/¥')
                : '';

            row.innerHTML =
                '<div class="sku-no">' + (index + 1) + '</div>' +
                '<div class="sku-name">' + escapeHtml(sku.name) + '</div>' +
                '<div class="sku-price-box">' +
                    '<div class="sku-price">' + priceText + '</div>' +
                    (costText ? '<div class="sku-cost">' + costText + '</div>' : '') +
                '</div>' +
                '<div class="sku-qty">' +
                    '<div class="num">' + (sku.stock > 0 ? sku.stock : 0) + '</div>' +
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

/* ================= 初始化 ================= */
loadSettings().then(() => {
    document.getElementById('barcodeInput').focus();
    startPolling();
});
setInterval(checkForConfigUpdates, 200);
</script>
</body>
</html>
