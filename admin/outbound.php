<?php
$pageTitle = '商品出库';
$currentPage = 'outbound';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">商品出库</div>

        <div style="display:flex; gap:20px; align-items:flex-start;">
            <div class="card" style="flex:1;">
                <div class="card-title">🛒 待出库商品</div>
                <table id="outboundTable">
                    <thead>
                        <tr>
                            <th>商品</th>
                            <th>状态</th>
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
                    <label class="form-label">备注</label>
                    <input type="text" class="form-input" id="outboundRemark" placeholder="备注信息">
                </div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn btn-primary" onclick="showOutboundList()" style="flex:1;">📋 记录</button>
                    <button class="btn btn-success" onclick="confirmOutbound()" id="confirmBtn" disabled style="flex:1;">✅ 出库 (<span id="confirmCount">0</span>)</button>
                </div>
            </div>
        </div>

        <!-- 底部扫码区 -->
        <div class="scan-bar">
            <div class="scan-bar-inner">
                <input type="text" id="scanInput" placeholder="📷 扫描条码..." class="scan-input">
                <div class="scan-result" id="obResult" style="display:none;">
                    <span class="sr-product" id="obProductName"></span>
                    <span class="sr-sep">|</span>
                    <span class="sr-label">状态</span>
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
            </div>
        </div>

        <style>
        .scan-bar {
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
                        <th>商品</th>
                        <th>状态</th>
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

    <script>
    let cart = [];
    let stockData = [];
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
            }
        }
    });

    // 扫码枪快速输入自动触发
    document.getElementById('scanInput').addEventListener('input', function(e) {
        clearTimeout(scanTimer);
        if (this.value.length >= 5) {
            scanTimer = setTimeout(() => {
                const barcode = this.value.trim();
                if (barcode) {
                    handleScan(barcode);
                    this.value = '';
                }
            }, 250);
        }
    });

    // 全局键盘：扫码后箭头切换条件，回车确认
    document.addEventListener('keydown', function(e) {
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

    // 扫码查询
    async function handleScan(barcode) {
        try {
            const res = await fetch(`../api/search_stock.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                scanResult = data.data;
                selectedIdx = 0;
                showBarResult();
                // 扫码框失去焦点，避免光标混淆
                document.getElementById('scanInput').blur();
                if (scanResult.length === 1) {
                    // 仅一个状态，直接进入价格阶段
                    phase = 'price';
                    document.getElementById('obPrice').focus();
                } else {
                    // 多状态，在状态选择阶段等待回车确认
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
        const stock = scanResult[selectedIdx];
        const price = parseFloat(document.getElementById('obPrice').value);
        if (!price || price <= 0) {
            document.getElementById('obPrice').focus();
            return;
        }
        if (stock.remaining_qty < 1) {
            alert('库存不足');
            resetBar();
            return;
        }
        stock.suggested_price = price;
        upsertCartItem(stock, 1);
        resetBar();
        renderCart();
        updateStats();
    }

    // 重置浮层
    function resetBar() {
        phase = 'condition';
        document.getElementById('conditionGroup').classList.remove('active');
        scanResult = [];
        selectedIdx = 0;
        document.getElementById('obResult').style.display = 'none';
        document.getElementById('scanInput').focus();
    }

    function upsertCartItem(stock, qty = 1) {
        const index = cart.findIndex(item => item.batch_id === stock.batch_id);
        if (index >= 0) {
            const nextQty = cart[index].qty + qty;
            cart[index].qty = Math.min(nextQty, cart[index].stock_qty);
            return;
        }

        cart.push({
            batch_id: stock.batch_id,
            product_id: stock.product_id,
            product_name: stock.product_name,
            common_name: stock.common_name,
            series: stock.series,
            condition_type: stock.condition_type,
            condition_name: stock.condition_name,
            batch_no: stock.batch_no,
            purchase_price: parseFloat(stock.purchase_price),
            price: parseFloat(stock.suggested_price) || parseFloat(stock.purchase_price),
            qty: qty,
            stock_qty: stock.remaining_qty
        });
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

        tbody.innerHTML = cart.map((item, index) => `
            <tr>
                <td>
                    <strong>${item.common_name || item.product_name}</strong>
                    ${item.common_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${item.product_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${item.condition_type}">${item.condition_name}</span></td>
                <td><code style="font-size:11px;">${item.batch_no}</code></td>
                <td>¥${item.purchase_price.toFixed(2)}</td>
                <td>
                    <input type="number" value="${item.price}" onclick="this.select()"
                           onchange="updatePrice(${index}, this.value)"
                           style="width:80px; padding:6px; text-align:center; border:1px solid var(--border); border-radius:4px; background:var(--bg-body); color:var(--success); font-weight:bold;">
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <button class="btn btn-sm" onclick="changeQty(${index}, -1)">-</button>
                        <span style="min-width:30px; text-align:center;">${item.qty}</span>
                        <button class="btn btn-sm" onclick="changeQty(${index}, 1)">+</button>
                    </div>
                </td>
                <td style="font-weight:bold;">¥${(item.price * item.qty).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="removeItem(${index})">删除</button>
                </td>
            </tr>
        `).join('');
    }

    function updatePrice(index, newPrice) {
        const price = parseFloat(newPrice) || 0;
        if (price > 0) {
            cart[index].price = price;
            updateStats();
        }
    }

    function changeQty(index, delta) {
        const newQty = cart[index].qty + delta;
        if (newQty <= 0) {
            removeItem(index);
        } else if (newQty > cart[index].stock_qty) {
            alert('超出库存数量');
        } else {
            cart[index].qty = newQty;
            renderCart();
            updateStats();
        }
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
        updateStats();
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

        const items = cart.map(item => ({
            batch_id: item.batch_id,
            product_id: item.product_id,
            condition_type: item.condition_type,
            qty: item.qty,
            price: item.price
        }));

        try {
            const res = await fetch('../api/outbound_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items, order_no: orderNo || null, remark })
            });

            const result = await res.json();

            if (result.success) {
                alert(`出库成功！\n批次号: ${result.data.batch_no}\n共 ${result.data.total_items} 个商品，合计 ¥${result.data.total_amount.toFixed(2)}`);
                cart = [];
                renderCart();
                updateStats();
                document.getElementById('orderNo').value = '';
                document.getElementById('outboundRemark').value = '';
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
                (s.barcode && s.barcode.includes(keyword));
            const matchSeries = !series || s.series === series;
            return matchKeyword && matchSeries;
        });

        renderStockList(filtered);
    }

    function renderStockList(stock) {
        const tbody = document.getElementById('stockList');

        if (!stock.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无库存数据</td></tr>';
            return;
        }

        tbody.innerHTML = stock.map((s, idx) => `
            <tr>
                <td>
                    <strong>${s.common_name || s.product_name}</strong>
                    ${s.common_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${s.product_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${s.condition_type}">${s.condition_name}</span></td>
                <td><code style="font-size:11px;">${s.batch_no}</code></td>
                <td>¥${parseFloat(s.purchase_price).toFixed(2)}</td>
                <td style="font-weight:bold; ${s.remaining_qty <= 2 ? 'color:var(--danger);' : ''}">${s.remaining_qty}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="quickAdd(${idx})">+ 添加</button>
                </td>
            </tr>
        `).join('');
    }

    function quickAdd(index) {
        const stock = stockData[index];
        upsertCartItem(stock, 1);

        renderCart();
        updateStats();
    }

    async function showOutboundList() {
        try {
            const res = await fetch('../api/list_outbound.php');
            const data = await res.json();

            if (data.success) {
                const container = document.getElementById('historyList');
                const outboundList = data.data.outbound || [];

                if (!outboundList.length) {
                    container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">暂无出库记录</div>';
                } else {
                    container.innerHTML = outboundList.map(batch => {
                        const profit = batch.total_amount - batch.total_cost;
                        return `
                        <div style="margin-bottom:25px; border:1px solid var(--border); border-radius:12px; overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <span style="font-size:18px; font-weight:bold;">${batch.outbound_at}</span>
                                </div>
                                <div style="display:flex; align-items:center; gap:15px;">
                                    <div style="text-align:right;">
                                        <div style="font-size:12px; opacity:0.9;">共 ${batch.total_qty} 件</div>
                                        <div style="font-size:18px; font-weight:bold;">
                                            <div>销售额: ¥${batch.total_amount.toFixed(2)}</div>
                                            <div style="font-size:14px; ${profit >= 0 ? 'color:#dfffe3;' : 'color:#ffdfe3;'}">
                                                盈利: ${profit >= 0 ? '+' : ''}¥${profit.toFixed(2)}
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-danger" onclick="deleteOutbound(&quot;${batch.batch_no}&quot;)" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3);">🗑️ 删除</button>
                                </div>
                            </div>
                            <table style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>商品</th>
                                        <th>状态</th>
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
                                                <strong>${item.common_name || item.product_name || '-'}</strong>
                                                ${item.common_name && item.product_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${item.product_name}</span>` : ''}
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
                                    ${batch.order_no ? `<span style="margin-right:20px;">订单号: <code>${batch.order_no}</code></span>` : ''}
                                    ${batch.remark ? `<span>备注: ${batch.remark}</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    `}).join('');
                }
                document.getElementById('historyModal').classList.add('show');
            }
        } catch (err) {
            console.error(err);
            alert('加载失败');
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
    </script>
</body>
</html>