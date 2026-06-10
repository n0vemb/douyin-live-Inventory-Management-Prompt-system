<?php
$pageTitle = '商品出库';
$currentPage = 'outbound';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">商品出库</div>

        <div style="display:flex; gap:20px; align-items:flex-start;">
            <div style="flex:1; display:flex; flex-direction:column; gap:12px;">
                <!-- 扫码区（在待出库商品上方） -->
                <div class="scan-bar">
            <div class="scan-bar-inner">
                <input type="text" id="scanInput" placeholder="📷 扫描条码或输入拼音搜索..." class="scan-input">
                <div class="scan-result" id="obResult" style="display:none;">
                    <span class="sr-product" id="obProductName"></span>
                    <span class="sr-sep">|</span>
                    <span class="sr-label">SKU</span>
                    <span class="condition-group" id="conditionGroup">
                        <button class="sr-arrow" onclick="cycleCondition(-1)">▲</button>
                        <span class="sr-condition" id="obCondition"></span>
                        <button class="sr-arrow" onclick="cycleCondition(1)">▼</button>
                    </span>
                    <span class="sr-sep">|</span>
                    <span class="sr-label">售价</span>
                    <input type="number" id="obPrice" step="0.01" placeholder="0.00" onfocus="this.select()" autocomplete="off">
                    <button class="btn btn-sm btn-success" onclick="confirmBarAdd()">+ 添加</button>
                </div>
                <div class="search-dropdown" id="obSearchDropdown"></div>
            </div>
        </div>

                <div class="card">
                    <div class="card-title">🛒 待出库商品</div>
                    <table id="outboundTable">
                        <thead>
                            <tr>
                                <th>商品</th>
                                <th>SKU</th>
                                <th>批次</th>
                                <th>进价</th>
                                <th>售价</th>
                                <th>数量</th>
                                <th>小计</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="outboundItems"></tbody>
                    </table>
                    <div id="emptyCart" style="text-align:center; padding:60px; color:var(--text-tertiary); font-size:18px;">
                        扫描条码添加出库商品
                    </div>
                </div>
            </div>

            <div class="card" style="width:320px; flex-shrink:0;">
                <div class="card-title">💰 结算信息</div>
                <div style="background:var(--bg-hover); padding:20px; border-radius:12px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text-secondary);">商品种类</span>
                        <span id="totalTypes" style="font-weight:bold;">0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text-secondary);">商品总数</span>
                        <span id="totalQty" style="font-weight:bold;">0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:24px; margin-top:15px; padding-top:15px; border-top:2px solid var(--border);">
                        <span>合计金额</span>
                        <span id="totalAmount" style="font-weight:bold; color:var(--success);">¥0.00</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">订单号（选填）</label>
                    <input type="text" class="form-input" id="orderNo" placeholder="外部订单号">
                </div>
                <div class="form-group">
                    <label class="form-label">直播平台</label>
                    <select class="form-input" id="outboundPlatform">
                        <option value="">-- 选填 --</option>
                        <option value="小红书">小红书</option>
                        <option value="抖音">抖音</option>
                        <option value="视频号">视频号</option>
                        <option value="其他平台">其他平台</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">账号（选填）</label>
                    <input type="text" class="form-input" id="outboundAccount" placeholder="对应直播账号">
                </div>
                <div class="form-group">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-input" id="outboundRemark" placeholder="备注信息">
                </div>
                <div class="form-group">
                    <label class="form-label">GMV 成交金额（选填）</label>
                    <input type="number" class="form-input" id="outboundGmv" step="0.01" placeholder="平台实际成交金额(含运费)">
                </div>
                <div class="form-group">
                    <label class="form-label">订单数 / 快递单数（选填）</label>
                    <input type="number" class="form-input" id="outboundOrderCount" step="1" placeholder="实际发货订单数">
                </div>
                <div class="form-group">
                    <label class="form-label">投流费用（选填）</label>
                    <input type="number" class="form-input" id="outboundAdSpend" step="0.01" placeholder="本次投放流量费用">
                </div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn btn-primary" onclick="showOutboundList()" style="flex:1;">📋 记录</button>
                    <button class="btn btn-success" onclick="confirmOutbound()" id="confirmBtn" disabled style="flex:1;">✅ 出库 (<span id="confirmCount">0</span>)</button>
                </div>
            </div>
        </div>

        <!-- 扫码栏浮层：滚动出屏时显示 -->
        <div class="scan-float-bar" id="scanFloatBar">
            <div class="scan-bar-inner">
                <input type="text" id="scanFloatInput" placeholder="📷 扫描条码或输入拼音搜索..." class="scan-input" style="width:220px;">
                <button class="btn btn-sm btn-success" onclick="document.getElementById('scanFloatInput').focus()">扫码</button>
            </div>
        </div>

        <style>
        .scan-float-bar {
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 18px;
            display: none;
            align-items: center;
            z-index: 500;
            box-shadow: 0 8px 40px rgba(0,0,0,0.5);
        }
        .scan-float-bar.show { display: flex; }
        .scan-bar {
            position: relative;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
        }
        .scan-bar-inner {
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }
        .scan-input {
            width: 260px; font-size: 18px; padding: 10px 16px;
            border: 2px solid var(--border); border-radius: 8px;
            background: var(--bg-card); color: var(--text); outline: none;
            transition: border-color 0.2s; box-sizing: border-box;
        }
        .scan-input:focus { border-color: var(--primary); }
        .scan-result { display: flex; align-items: center; gap: 8px; }
        .sr-product { font-weight: bold; font-size: 15px; white-space: nowrap; }
        .sr-sep { color: var(--text-tertiary); font-size: 16px; }
        .sr-label { font-size: 12px; color: var(--text-secondary); }
        .sr-condition { font-weight: bold; font-size: 15px; min-width: 60px; text-align: center; color: var(--primary); }
        .condition-group {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 8px; border: 2px solid transparent; border-radius: 8px;
            transition: all 0.2s;
        }
        .condition-group.active {
            border-color: var(--primary);
            background: rgba(99,102,241,0.12);
            box-shadow: 0 0 12px rgba(99,102,241,0.2);
        }
        .sr-arrow {
            background: var(--bg-card); border: 1px solid var(--border); color: var(--text);
            padding: 2px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; line-height: 1;
        }
        .sr-arrow:hover { background: var(--primary-light); border-color: var(--primary); }
        .scan-result input[type="number"] {
            width: 90px; padding: 6px 10px; border: 2px solid var(--border); border-radius: 6px;
            background: var(--bg-card); color: var(--success); font-weight: bold; font-size: 16px;
            text-align: center; outline: none; transition: border-color 0.2s;
        }
        .scan-result input[type="number"]:focus { border-color: var(--success); }

        /* 拼音搜索下拉框 */
        .search-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            display: none;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            z-index: 200;
        }
        .search-dropdown.show { display: block; }
        .search-dropdown-empty {
            padding: 30px; text-align: center; color: var(--text-tertiary); font-size: 14px;
        }
        .search-dropdown-header {
            padding: 10px 14px 6px; border-bottom: 1px solid var(--border);
            background: var(--bg-hover);
        }
        .search-dropdown-header .sdi-product-name {
            font-weight: 600; font-size: 14px;
        }
        .search-dropdown-header .sdi-product-meta {
            font-size: 11px; color: var(--text-tertiary); margin-top: 2px;
        }
        .search-dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px; border-bottom: 1px solid var(--border);
            font-size: 13px; transition: background 0.15s;
        }
        .search-dropdown-item:last-child { border-bottom: none; }
        .search-dropdown-item:hover { background: var(--bg-hover); }
        .sdi-stock { font-size: 12px; color: var(--text-secondary); min-width: 50px; }
        .sdi-price { font-weight: bold; font-size: 14px; min-width: 65px; text-align: right; }
        .sdi-add-btn {
            padding: 4px 14px; border-radius: 6px; border: none;
            background: var(--primary); color: #fff; font-size: 12px;
            cursor: pointer; font-weight: 600; white-space: nowrap; transition: 0.15s;
        }
        .sdi-add-btn:hover { opacity: 0.85; }
        </style>

        <div class="card">
            <div class="card-title">📊 库存概览</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
                <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">商品种类</div>
                    <div id="statTypes" style="font-size:32px; font-weight:bold;">-</div>
                </div>
                <div style="background:linear-gradient(135deg, #10b981, #059669); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">库存总量</div>
                    <div id="statTotalQty" style="font-size:32px; font-weight:bold;">-</div>
                </div>
                <div style="background:linear-gradient(135deg, #f59e0b, #d97706); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">库存总成本</div>
                    <div id="statTotalCost" style="font-size:32px; font-weight:bold;">¥-</div>
                </div>
                <div style="background:linear-gradient(135deg, #ef4444, #dc2626); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">库存总价值</div>
                    <div id="statTotalValue" style="font-size:32px; font-weight:bold;">¥-</div>
                </div>
            </div>

            <div style="margin-bottom:15px; display:flex; gap:10px;">
                <input type="text" id="stockSearch" placeholder="搜索商品..." style="flex:1; padding:10px; border:1px solid var(--border); border-radius:8px;" onkeyup="searchStock()">
                <select id="stockSeriesFilter" style="padding:10px; border:1px solid var(--border); border-radius:8px;" onchange="searchStock()">
                    <option value="">全部系列</option>
                </select>
            </div>

            <table>
                <thead>
                    <tr>
                        <th onclick="toggleSort()" style="cursor:pointer;user-select:none;">商品 <span id="sortIndicator"></span></th>
                        <th>SKU</th>
                        <th>批次</th>
                        <th>进价</th>
                        <th>库存</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="stockList"></tbody>
            </table>
        </div>

        <div class="modal" id="historyModal">
            <div class="modal-content modal-wide" style="max-height:80vh; overflow-y:auto;">
                <div class="modal-header">
                    <h3 class="modal-title">出库历史</h3>
                    <button class="modal-close" onclick="closeHistoryModal()">&times;</button>
                </div>
                <div id="historyList"></div>
            </div>
        </div>
    </div>

    <!-- 财务数据编辑弹窗 -->
    <div class="modal" id="financeModal">
        <div class="modal-content" style="max-width:420px;">
            <div class="modal-header">
                <h3 class="modal-title">补充财务数据</h3>
                <button class="modal-close" onclick="closeFinanceModal()">&times;</button>
            </div>
            <form onsubmit="saveOutboundFinance(event)">
                <input type="hidden" id="financeBatchNo">
                <div class="form-group">
                    <label class="form-label">直播平台</label>
                    <select class="form-input" id="financePlatform">
                        <option value="">-- 选填 --</option>
                        <option value="小红书">小红书</option>
                        <option value="抖音">抖音</option>
                        <option value="视频号">视频号</option>
                        <option value="其他平台">其他平台</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">账号</label>
                    <input type="text" class="form-input" id="financeAccount" placeholder="对应直播账号">
                </div>
                <div class="form-group">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-input" id="financeRemark" placeholder="备注信息">
                </div>
                <div class="form-group">
                    <label class="form-label">GMV (成交金额)</label>
                    <input type="number" class="form-input" id="financeGmv" step="0.01" placeholder="平台实际成交金额(含运费)">
                </div>
                <div class="form-group">
                    <label class="form-label">订单数 / 快递单数</label>
                    <input type="number" class="form-input" id="financeOrderCount" step="1" placeholder="实际发货订单数">
                </div>
                <div class="form-group">
                    <label class="form-label">投流费用</label>
                    <input type="number" class="form-input" id="financeAdSpend" step="0.01" placeholder="本次投放流量费用">
                </div>
                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                    <button type="button" class="btn btn-secondary" onclick="closeFinanceModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let cart = [];
    let stockData = [];
    let stockSortAsc = true;
    let scanTimer = null;
    let conditionNameMap = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };

    // ---- 扫码工作流 ----
    let scanResult = [];    // 当前扫码返回的批次列表
    let selectedIdx = 0;    // 当前选中的条件索引
    let phase = 'condition'; // 'condition' | 'price' 扫码工作流阶段

    // 扫码输入：回车触发查询
    document.getElementById('scanInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const barcode = this.value.trim();
            if (barcode) {
                handleScan(barcode);
                this.value = '';
                var fi = document.getElementById('scanFloatInput');
                if (fi) fi.value = '';
            }
        }
    });

    // 扫码或拼音搜索输入
    document.getElementById('scanInput').addEventListener('input', function(e) {
        clearTimeout(scanTimer);
        const value = this.value.trim();

        if (!value) {
            hideSearchDropdown();
            return;
        }

        if (/^\d+$/.test(value)) {
            // 全数字 → 条码查询
            hideSearchDropdown();
            if (value.length >= 5) {
                scanTimer = setTimeout(() => {
                    handleScan(value);
                    this.value = '';
                    var fi = document.getElementById('scanFloatInput');
                    if (fi) fi.value = '';
                }, 250);
            }
        } else {
            // 含字母 → 拼音搜索
            if (document.getElementById('obResult').style.display === 'block') {
                resetBar();
            }
            scanTimer = setTimeout(() => {
                searchPinyinStock(value);
            }, 300);
        }
    });

    // 全局键盘：扫码后箭头切换条件，回车确认
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideSearchDropdown();
            return;
        }
        if (scanResult.length === 0) return;

        if (phase === 'condition') {
            // 状态选择阶段：上下切换，回车确认进入价格输入
            if (e.key === 'ArrowUp') { e.preventDefault(); cycleCondition(-1); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); cycleCondition(1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                phase = 'price';
                document.getElementById('conditionGroup').classList.remove('active');
                document.getElementById('obPrice').focus();
            }
        } else if (phase === 'price') {
            // 价格输入阶段：上下仍可切换状态，回车添加
            if (e.key === 'ArrowUp') { e.preventDefault(); cycleCondition(-1); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); cycleCondition(1); }
            else if (e.key === 'Enter') { e.preventDefault(); confirmBarAdd(); }
        }
    });

    // 扫码查询 — 同SKU多批次合并
    async function handleScan(barcode) {
        try {
            const res = await fetch(`../api/search_stock.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                scanResult = mergeBatchesBySKU(data.data);
                selectedIdx = 0;
                showBarResult();
                document.getElementById('scanInput').blur();
                if (scanResult.length === 1) {
                    phase = 'price';
                    document.getElementById('obPrice').focus();
                } else {
                    phase = 'condition';
                    document.getElementById('conditionGroup').classList.add('active');
                }
            } else {
                alert('未找到库存或库存为零');
                document.getElementById('scanInput').focus();
            }
        } catch (err) {
            console.error(err);
            alert('查询失败');
            document.getElementById('scanInput').focus();
        }
    }

    function mergeBatchesBySKU(batches) {
        const skuMap = {};
        batches.forEach(b => {
            const key = b.condition_type;
            if (!skuMap[key]) {
                skuMap[key] = {
                    product_id: b.product_id,
                    product_name: b.product_name,
                    common_name: b.common_name,
                    series: b.series,
                    barcode: b.barcode,
                    condition_type: b.condition_type,
                    condition_name: b.condition_name,
                    suggested_price: b.suggested_price,
                    total_stock: 0,
                    batches: []
                };
            }
            skuMap[key].batches.push(b);
            skuMap[key].total_stock += parseInt(b.remaining_qty);
            if (parseFloat(b.suggested_price) > parseFloat(skuMap[key].suggested_price)) {
                skuMap[key].suggested_price = b.suggested_price;
            }
        });
        Object.values(skuMap).forEach(sku => {
            sku.batches.sort((a, b) => (a.purchased_at || '').localeCompare(b.purchased_at || ''));
        });
        return Object.values(skuMap);
    }

    // 在浮层显示扫码结果
    function showBarResult() {
        const stock = scanResult[selectedIdx];
        document.getElementById('obProductName').textContent = stock.common_name || stock.product_name;
        document.getElementById('obCondition').textContent = getConditionName(stock.condition_type);
        document.getElementById('obPrice').value = parseFloat(stock.suggested_price || 0).toFixed(2);
        document.getElementById('obResult').style.display = 'block';
    }

    // 上下切换条件
    function cycleCondition(delta) {
        if (scanResult.length === 0) return;
        selectedIdx = (selectedIdx + delta + scanResult.length) % scanResult.length;
        const stock = scanResult[selectedIdx];
        document.getElementById('obCondition').textContent = getConditionName(stock.condition_type);
        document.getElementById('obPrice').value = parseFloat(stock.suggested_price || 0).toFixed(2);
    }

    // 确认添加（从浮层加入购物车）
    function confirmBarAdd() {
        if (scanResult.length === 0) return;
        const sku = scanResult[selectedIdx];
        const price = parseFloat(document.getElementById('obPrice').value);
        if (!price || price <= 0) {
            document.getElementById('obPrice').focus();
            return;
        }
        if (sku.total_stock < 1) {
            alert('库存不足');
            resetBar();
            return;
        }
        sku.suggested_price = price;
        upsertCartItem(sku, 1);
        resetBar();
        renderCart();
        updateStats();
        refreshStockDisplay();
    }

    // 重置浮层
    function resetBar() {
        phase = 'condition';
        document.getElementById('conditionGroup').classList.remove('active');
        scanResult = [];
        selectedIdx = 0;
        document.getElementById('obResult').style.display = 'none';
        document.getElementById('scanInput').value = '';
        var fi = document.getElementById('scanFloatInput');
        if (fi) fi.value = '';
        // 浮层可见时聚焦浮层，避免页面滚回顶部
        setTimeout(function() {
            if (scanFloatBar.classList.contains('show')) {
                scanFloatInput.focus({ preventScroll: true });
            } else {
                var input = document.getElementById('scanInput');
                if (input) input.focus({ preventScroll: true });
            }
        }, 50);
    }

    /* ---- 拼音搜索 ---- */
    let obSearchResults = [];

    function searchPinyinStock(keyword) {
        fetch(`../api/search_outbound_stock.php?keyword=${encodeURIComponent(keyword)}`)
            .then(r => r.json())
            .then(data => {
                obSearchResults = data.success && data.data ? data.data : [];
                showSearchDropdown();
            })
            .catch(() => {
                obSearchResults = [];
                showSearchDropdown();
            });
    }

    function showSearchDropdown() {
        const dd = document.getElementById('obSearchDropdown');
        if (!obSearchResults || !obSearchResults.length) {
            dd.innerHTML = '<div class="search-dropdown-empty">未找到匹配商品</div>';
            dd.classList.add('show');
            return;
        }

        // 按商品+SKU合并批次
        const productGroups = {};
        obSearchResults.forEach(b => {
            if (!productGroups[b.product_id]) {
                productGroups[b.product_id] = { product_id: b.product_id, product_name: b.product_name, common_name: b.common_name, series: b.series, barcode: b.barcode, conditions: {} };
            }
            const pg = productGroups[b.product_id];
            const cond = pg.conditions;
            if (!cond[b.condition_type]) {
                cond[b.condition_type] = {
                    product_id: pg.product_id,
                    product_name: pg.product_name,
                    common_name: pg.common_name,
                    series: pg.series,
                    barcode: pg.barcode,
                    condition_type: b.condition_type,
                    condition_name: b.condition_name,
                    total_stock: 0,
                    suggested_price: b.suggested_price,
                    batches: []
                };
            }
            cond[b.condition_type].batches.push(b);
            cond[b.condition_type].total_stock += parseInt(b.remaining_qty);
            if (parseFloat(b.suggested_price) > parseFloat(cond[b.condition_type].suggested_price)) {
                cond[b.condition_type].suggested_price = b.suggested_price;
            }
        });

        dd.innerHTML = '';
        // 按 product_id + condition_type 分配唯一 ID 用于事件绑定
        let addId = 0;
        const addMap = {};

        Object.values(productGroups).forEach(product => {
            const displayName = product.common_name || product.product_name;
            const mergedSKUs = Object.values(product.conditions);
            const section = document.createElement('div');
            section.innerHTML = `
                <div class="search-dropdown-header">
                    <div class="sdi-product-name">${escapeHtml(displayName)}</div>
                    <div class="sdi-product-meta">${escapeHtml(product.barcode)}${product.series ? ' · ' + escapeHtml(product.series) : ''}</div>
                </div>
                ${mergedSKUs.map(sku => {
                    const id = 'add_' + (addId++);
                    addMap[id] = sku;
                    return `
                    <div class="search-dropdown-item">
                        <span class="condition-badge condition-${sku.condition_type}">${escapeHtml(sku.condition_name)}</span>
                        <span class="sdi-stock">库存 ${sku.total_stock}</span>
                        <span class="sdi-price" style="color:var(--success);">¥${parseFloat(sku.suggested_price || 0).toFixed(2)}</span>
                        <button class="sdi-add-btn" data-add-id="${id}">添加</button>
                    </div>
                `;}).join('')}
            `;
            dd.appendChild(section);
        });

        dd.classList.add('show');

        // 浮层可见时，下拉框跟随浮层定位
        if (scanFloatBar.classList.contains('show')) {
            var fr = scanFloatBar.getBoundingClientRect();
            dd.style.position = 'fixed';
            dd.style.top = (fr.bottom + 4) + 'px';
            dd.style.left = fr.left + 'px';
            dd.style.right = 'auto';
            dd.style.width = fr.width + 'px';
        } else {
            dd.style.position = '';
            dd.style.top = '';
            dd.style.left = '';
            dd.style.right = '';
            dd.style.width = '';
        }

        dd.querySelectorAll('.sdi-add-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const sku = addMap[this.dataset.addId];
                if (sku) {
                    upsertCartItem(sku, 1);
                    renderCart();
                    updateStats();
                    refreshStockDisplay();
                    this.textContent = '✓';
                    this.style.background = '#34d399';
                    setTimeout(() => { this.textContent = '添加'; this.style.background = ''; }, 600);
                    document.getElementById('scanInput').value = '';
                    var floatInput = document.getElementById('scanFloatInput');
                    if (floatInput) floatInput.value = '';
                    document.getElementById('obSearchDropdown').classList.remove('show');
                    // 浮层可见时聚焦浮层输入框，避免页面滚回顶部
                    if (scanFloatBar.classList.contains('show')) {
                        scanFloatInput.focus({ preventScroll: true });
                    } else {
                        document.getElementById('scanInput').focus({ preventScroll: true });
                    }
                }
            });
        });
    }

    function hideSearchDropdown() {
        document.getElementById('obSearchDropdown').classList.remove('show');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // 点击外部关闭下拉框
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.scan-bar')) {
            hideSearchDropdown();
        }
    });

    function upsertCartItem(sku, qty = 1) {
        const index = cart.findIndex(item =>
            item.product_id === sku.product_id && item.condition_type === sku.condition_type
        );
        if (index >= 0) {
            const item = cart[index];
            const totalStock = item.batches.reduce((s, b) => s + b.available, 0);
            const nextQty = item.qty + qty;
            if (nextQty > totalStock) {
                alert('库存不足');
                item.qty = totalStock;
            } else {
                item.qty = nextQty;
            }
            allocateFIFO(item);
            return;
        }

        const newItem = {
            product_id: sku.product_id,
            product_name: sku.product_name,
            common_name: sku.common_name || '',
            series: sku.series || '',
            condition_type: sku.condition_type,
            condition_name: sku.condition_name,
            price: parseFloat(sku.suggested_price) || 0,
            qty: qty,
            batches: (sku.batches || [sku]).map(b => ({
                batch_id: b.batch_id,
                batch_no: b.batch_no,
                purchase_price: parseFloat(b.purchase_price || 0),
                available: parseInt(b.remaining_qty || 0),
                qty: 0
            }))
        };
        allocateFIFO(newItem);
        cart.push(newItem);
    }

    function allocateFIFO(item) {
        let remaining = item.qty;
        for (const b of item.batches) {
            b.qty = Math.min(b.available, remaining);
            remaining -= b.qty;
            if (remaining <= 0) break;
        }
        if (remaining > 0) {
            item.qty -= remaining;
        }
    }

    function renderCart() {
        const tbody = document.getElementById('outboundItems');
        const emptyCart = document.getElementById('emptyCart');
        const confirmBtn = document.getElementById('confirmBtn');

        if (cart.length === 0) {
            tbody.innerHTML = '';
            emptyCart.style.display = 'block';
            confirmBtn.disabled = true;
            return;
        }

        emptyCart.style.display = 'none';
        confirmBtn.disabled = false;

        tbody.innerHTML = cart.map((item, index) => {
            const totalStock = item.batches.reduce((s, b) => s + b.available, 0);
            const remaining = totalStock - item.qty;
            const usedBatches = item.batches.filter(b => b.qty > 0);
            const isMulti = usedBatches.length > 1;
            const avgCost = item.qty > 0
                ? usedBatches.reduce((s, b) => s + b.purchase_price * b.qty, 0) / item.qty
                : 0;
            return `
            <tr>
                <td>
                    <strong>${item.common_name || item.product_name}</strong>
                    ${item.common_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${item.product_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${item.condition_type}">${item.condition_name}</span></td>
                <td><code style="font-size:11px;">${isMulti ? '多批次(' + usedBatches.length + ')' : (item.batches[0]?.batch_no || '-')}</code></td>
                <td>¥${avgCost.toFixed(2)}</td>
                <td>
                    <input type="number" value="${item.price}" onclick="this.select()"
                           onchange="updatePrice(${index}, this.value)"
                           style="width:80px; padding:6px; text-align:center; border:1px solid var(--border); border-radius:4px; background:var(--bg-body); color:var(--success); font-weight:bold;">
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <button class="btn btn-sm" onclick="changeQty(${index}, -1)">-</button>
                        <span style="min-width:30px; text-align:center; font-weight:bold;">${item.qty}</span>
                        <button class="btn btn-sm" onclick="changeQty(${index}, 1)">+</button>
                    </div>
                    <div style="font-size:11px; color:${remaining <= 2 ? 'var(--danger)' : 'var(--text-tertiary)'}; margin-top:2px;" class="stock-remain" data-index="${index}">
                        剩${remaining}件
                    </div>
                </td>
                <td style="font-weight:bold;">¥${(item.price * item.qty).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="removeItem(${index})">删除</button>
                </td>
            </tr>
        `;}).join('');
    }

    function updatePrice(index, newPrice) {
        const price = parseFloat(newPrice) || 0;
        if (price > 0) {
            cart[index].price = price;
            updateStats();
        }
    }

    function changeQty(index, delta) {
        const item = cart[index];
        const totalStock = item.batches.reduce((s, b) => s + b.available, 0);
        const newQty = item.qty + delta;
        if (newQty <= 0) {
            removeItem(index);
        } else if (newQty > totalStock) {
            alert('超出库存数量（共' + totalStock + '件）');
        } else {
            item.qty = newQty;
            allocateFIFO(item);
            renderCart();
            updateStats();
            refreshStockDisplay();
        }
    }

    function refreshStockDisplay() {
        if (stockData.length) renderStockList(stockData);
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
        updateStats();
        refreshStockDisplay();
    }

    function updateStats() {
        const totalTypes = cart.length;
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
        const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        document.getElementById('totalTypes').textContent = totalTypes;
        document.getElementById('totalQty').textContent = totalQty;
        document.getElementById('totalAmount').textContent = '¥' + totalAmount.toFixed(2);
        document.getElementById('confirmCount').textContent = totalTypes;
    }

    async function confirmOutbound() {
        if (cart.length === 0) return;

        const orderNo = document.getElementById('orderNo').value.trim();
        const remark = document.getElementById('outboundRemark').value.trim();
        const platform = document.getElementById('outboundPlatform').value;
        const account = document.getElementById('outboundAccount').value.trim();
        const gmv = parseFloat(document.getElementById('outboundGmv').value) || null;
        const orderCount = parseInt(document.getElementById('outboundOrderCount').value) || null;
        const adSpend = parseFloat(document.getElementById('outboundAdSpend').value) || null;

        const items = [];
        cart.forEach(item => {
            item.batches.filter(b => b.qty > 0).forEach(b => {
                items.push({
                    batch_id: b.batch_id,
                    product_id: item.product_id,
                    condition_type: item.condition_type,
                    qty: b.qty,
                    price: item.price
                });
            });
        });

        try {
            const res = await fetch('../api/outbound_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items, order_no: orderNo || null, remark, platform: platform || null, account: account || null, gmv, order_count: orderCount, ad_spend: adSpend })
            });

            const result = await res.json();

            if (result.success) {
                alert(`出库成功！\n批次号: ${result.data.batch_no}\n共 ${result.data.total_items} 个商品，合计 ¥${result.data.total_amount.toFixed(2)}`);
                cart = [];
                renderCart();
                updateStats();
                document.getElementById('orderNo').value = '';
                document.getElementById('outboundPlatform').value = '';
                document.getElementById('outboundAccount').value = '';
                document.getElementById('outboundRemark').value = '';
                document.getElementById('outboundGmv').value = '';
                document.getElementById('outboundOrderCount').value = '';
                document.getElementById('outboundAdSpend').value = '';
                loadStockOverview();
            } else {
                alert('出库失败: ' + (result.error || '未知错误'));
            }
        } catch (err) {
            console.error(err);
            alert('出库失败');
        }
    }

    async function loadStockOverview() {
        try {
            const res = await fetch('../api/stock_overview.php');
            const data = await res.json();

            if (data.success) {
                document.getElementById('statTypes').textContent = data.data.types;
                document.getElementById('statTotalQty').textContent = data.data.total_qty;
                document.getElementById('statTotalCost').textContent = '¥' + parseFloat(data.data.total_cost || 0).toFixed(0);
                document.getElementById('statTotalValue').textContent = '¥' + parseFloat(data.data.total_value || 0).toFixed(0);

                stockData = data.data.stock_list || [];

                const seriesSet = new Set();
                stockData.forEach(s => { if (s.series) seriesSet.add(s.series); });

                const seriesSelect = document.getElementById('stockSeriesFilter');
                seriesSelect.innerHTML = '<option value="">全部系列</option>';
                seriesSet.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    seriesSelect.appendChild(opt);
                });

                renderStockList(stockData);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function searchStock() {
        const keyword = document.getElementById('stockSearch').value.toLowerCase();
        const series = document.getElementById('stockSeriesFilter').value;

        const filtered = stockData.filter(s => {
            const matchKeyword = !keyword ||
                (s.product_name && s.product_name.toLowerCase().includes(keyword)) ||
                (s.common_name && s.common_name.toLowerCase().includes(keyword)) ||
                (s.barcode && s.barcode.includes(keyword)) ||
                (s.pinyin_initials && s.pinyin_initials.toLowerCase().includes(keyword));
            const matchSeries = !series || s.series === series;
            return matchKeyword && matchSeries;
        });

        filtered.sort((a, b) => {
            const nameA = (a.common_name || a.product_name || '').toLowerCase();
            const nameB = (b.common_name || b.product_name || '').toLowerCase();
            return stockSortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });

        renderStockList(filtered);
    }

    function toggleSort() {
        stockSortAsc = !stockSortAsc;
        document.getElementById('sortIndicator').textContent = stockSortAsc ? ' ▲' : ' ▼';
        searchStock();
    }

    function getCartReserved(batchId) {
        let reserved = 0;
        cart.forEach(item => {
            item.batches.forEach(b => {
                if (b.batch_id == batchId) reserved += b.qty;
            });
        });
        return reserved;
    }

    function renderStockList(stock) {
        const tbody = document.getElementById('stockList');

        if (!stock.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无库存数据</td></tr>';
            return;
        }

        tbody.innerHTML = stock.map((s, idx) => {
            const reserved = getCartReserved(s.batch_id);
            const remain = Math.max(0, s.remaining_qty - reserved);
            const dimmed = remain <= 0;
            return `
            <tr style="${dimmed ? 'opacity:0.4;' : ''}">
                <td>
                    <strong>${s.product_name}</strong>
                </td>
                <td><span class="condition-badge condition-${s.condition_type}">${s.condition_name}</span></td>
                <td><code style="font-size:11px;">${s.batch_no}</code></td>
                <td>¥${parseFloat(s.purchase_price).toFixed(2)}</td>
                <td style="font-weight:bold; ${remain <= 2 ? 'color:var(--danger);' : ''}">
                    ${remain}
                    ${reserved > 0 ? `<span style="font-size:11px; color:var(--text-tertiary); font-weight:normal;">(-${reserved})</span>` : ''}
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="quickAdd(${s.batch_id})" ${dimmed ? 'disabled' : ''}>+ 添加</button>
                </td>
            </tr>
        `;}).join('');
    }

    function quickAdd(batchId) {
        const stock = stockData.find(s => s.batch_id == batchId);
        if (!stock) return;
        // 找到该SKU的所有批次
        const allBatches = stockData.filter(s =>
            s.product_id === stock.product_id && s.condition_type === stock.condition_type
        );
        const sku = {
            product_id: stock.product_id,
            product_name: stock.product_name,
            common_name: stock.common_name || '',
            series: stock.series || '',
            condition_type: stock.condition_type,
            condition_name: stock.condition_name,
            suggested_price: stock.suggested_price,
            total_stock: allBatches.reduce((s, b) => s + parseInt(b.remaining_qty), 0),
            batches: allBatches
        };
        upsertCartItem(sku, 1);
        renderCart();
        updateStats();
        refreshStockDisplay();
    }

    async function showOutboundList() {
        const modal = document.getElementById('historyModal');
        const container = document.getElementById('historyList');
        modal.classList.add('show');
        container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:16px;">加载中...</div>';

        try {
            const res = await fetch('../api/list_outbound.php');
            const data = await res.json();

            if (data.success) {
                const outboundList = data.data.outbound || [];

                if (!outboundList.length) {
                    container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">暂无出库记录</div>';
                } else {
                    container.innerHTML = outboundList.map(batch => {
                        const profit = batch.total_amount - batch.total_cost;
                        const hasFinance = batch.gmv !== null && batch.gmv !== undefined;
                        return `
                        <div style="margin-bottom:25px; border:1px solid var(--border); border-radius:12px; overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <span style="font-size:18px; font-weight:bold;">${batch.outbound_at}</span>
                                    ${batch.order_no ? `<span style="margin-left:12px; font-size:13px; opacity:0.8;">订单: ${escHtml(batch.order_no)}</span>` : ''}
                                </div>
                                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <div style="text-align:right; font-size:13px;">
                                        <div>共 ${batch.total_qty} 件 | 成本 ¥${batch.total_cost.toFixed(2)}</div>
                                        <div style="font-weight:bold; ${profit >= 0 ? 'color:#dfffe3;' : 'color:#ffdfe3;'}">
                                            毛利: ${profit >= 0 ? '+' : ''}¥${profit.toFixed(2)}
                                        </div>
                                        ${hasFinance ? `<div style="margin-top:3px; padding-top:3px; border-top:1px solid rgba(255,255,255,0.2);">
                                            GMV: ¥${parseFloat(batch.gmv).toFixed(2)} | 订单: ${batch.order_count || 0} | 投流: ¥${parseFloat(batch.ad_spend || 0).toFixed(2)}
                                        </div>` : ''}
                                    </div>
                                    <button class="btn btn-sm" onclick="editOutboundFinance('${batch.batch_no}', ${batch.gmv || 'null'}, ${batch.order_count || 'null'}, ${batch.ad_spend || 'null'}, '${(batch.platform || '').replace(/'/g, "\\'")}', '${(batch.account || '').replace(/'/g, "\\'")}', '${(batch.remark || '').replace(/'/g, "\\'")}'); event.stopPropagation();" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3);">💰 财务</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteOutbound('${batch.batch_no}')" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3);">🗑️</button>
                                </div>
                            </div>
                            <table style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>商品</th>
                                        <th>SKU</th>
                                        <th>数量</th>
                                        <th>进价</th>
                                        <th>售价</th>
                                        <th>盈利</th>
                                        <th>金额</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${batch.items.map(item => {
                                        const itemProfit = (item.outbound_price - item.batch_purchase_price) * item.qty;
                                        return `
                                        <tr>
                                            <td>
                                                <strong>${escHtml(item.common_name || item.product_name || '-')}</strong>
                                                ${item.common_name && item.product_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${escHtml(item.product_name)}</span>` : ''}
                                            </td>
                                            <td><span class="condition-badge condition-${item.condition_type}">${getConditionName(item.condition_type)}</span></td>
                                            <td>${item.qty}</td>
                                            <td>¥${parseFloat(item.batch_purchase_price).toFixed(2)}</td>
                                            <td>¥${parseFloat(item.outbound_price).toFixed(2)}</td>
                                            <td style="${itemProfit >= 0 ? 'color:var(--success);' : 'color:var(--danger);'} font-weight:bold;">${itemProfit >= 0 ? '+' : ''}¥${itemProfit.toFixed(2)}</td>
                                            <td style="font-weight:bold;">¥${(parseFloat(item.outbound_price) * item.qty).toFixed(2)}</td>
                                        </tr>
                                    `}).join('')}
                                </tbody>
                            </table>
                            ${batch.order_no || batch.remark ? `
                                <div style="background:var(--bg-hover); padding:10px 20px; border-top:1px solid var(--border); font-size:13px; color:var(--text-secondary);">
                                    ${batch.remark ? `<span>备注: ${escHtml(batch.remark)}</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    `}).join('');
                }
            } else {
                container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">加载失败: ' + (data.error || '未知错误') + '</div>';
            }
        } catch (err) {
            console.error(err);
            container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">加载失败: ' + err.message + '</div>';
        }
    }

    async function loadConditionSettings() {
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            if (data.success && data.settings && data.settings.condition_types) {
                conditionNameMap = Object.fromEntries(data.settings.condition_types.map(c => [c.key, c.name]));
            }
        } catch (e) {
            console.log('使用默认状态名称');
        }
    }

    function getConditionName(type) {
        return conditionNameMap[type] || type;
    }

    function closeHistoryModal() {
        document.getElementById('historyModal').classList.remove('show');
    }

    async function deleteOutbound(batchNo) {
        if (!confirm("确定要删除出库批次 " + batchNo + " 吗？\n\n删除后库存将自动恢复，此操作不可撤销！")) {
            return;
        }

        try {
            const res = await fetch('../api/delete_outbound.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ batch_no: batchNo })
            });
            const data = await res.json();

            if (data.success) {
                alert('删除成功！库存已恢复。');
                closeHistoryModal();
                loadStockOverview();
                showOutboundList();
            } else {
                alert('删除失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            console.error(err);
            alert('删除失败');
        }
    }

    loadConditionSettings();
    loadStockOverview();
    document.getElementById('scanInput').focus();

    // 扫码栏浮层：主扫码栏滚出屏时在顶部显示
    const scanBar = document.querySelector('.scan-bar');
    const scanFloatBar = document.getElementById('scanFloatBar');
    const scanFloatInput = document.getElementById('scanFloatInput');

    // 浮层输入框事件 — 同步到主输入框
    scanFloatInput.addEventListener('input', function(e) {
        const mainInput = document.getElementById('scanInput');
        mainInput.value = e.target.value;
        mainInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
    scanFloatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const mainInput = document.getElementById('scanInput');
            mainInput.value = e.target.value;
            mainInput.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter', bubbles: true }));
            e.target.value = '';
        }
    });

    function checkScanFloatBar() {
        if (!scanBar) return;
        const rect = scanBar.getBoundingClientRect();
        const visible = rect.bottom > 0;
        scanFloatBar.classList.toggle('show', !visible);
        if (!visible && document.activeElement === document.getElementById('scanInput')) {
            scanFloatInput.focus();
        }
    }
    window.addEventListener('scroll', checkScanFloatBar, { passive: true });
    window.addEventListener('resize', checkScanFloatBar);

    function escHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function editOutboundFinance(batchNo, gmv, orderCount, adSpend, platform, account, remark) {
        document.getElementById('financeBatchNo').value = batchNo;
        document.getElementById('financePlatform').value = platform || '';
        document.getElementById('financeAccount').value = account || '';
        document.getElementById('financeRemark').value = remark || '';
        document.getElementById('financeGmv').value = gmv || '';
        document.getElementById('financeOrderCount').value = orderCount || '';
        document.getElementById('financeAdSpend').value = adSpend || '';
        document.getElementById('financeModal').classList.add('show');
    }

    async function saveOutboundFinance(e) {
        e.preventDefault();
        const batchNo = document.getElementById('financeBatchNo').value;
        const platform = document.getElementById('financePlatform').value || null;
        const account = document.getElementById('financeAccount').value.trim() || null;
        const remark = document.getElementById('financeRemark').value.trim() || null;
        const gmv = parseFloat(document.getElementById('financeGmv').value) || null;
        const orderCount = parseInt(document.getElementById('financeOrderCount').value) || null;
        const adSpend = parseFloat(document.getElementById('financeAdSpend').value) || null;
        try {
            const res = await fetch('../api/save_finance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ outbound_batch_no: batchNo, gmv, order_count: orderCount, ad_spend: adSpend, platform, account, remark })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('financeModal').classList.remove('show');
                showOutboundList();
            } else {
                alert('保存失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            alert('请求失败: ' + err.message);
        }
    }

    function closeFinanceModal() {
        document.getElementById('financeModal').classList.remove('show');
    }
    </script>
</body>
</html>