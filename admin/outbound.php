<?php
$pageTitle = '商品出库';
$currentPage = 'outbound';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">商品出库</div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div class="search-bar" style="margin:0;">
                    <input type="text" id="scanInput" placeholder="📷 扫描枪扫码或输入条码..." style="min-width:300px; font-size:18px; padding:12px;">
                </div>
                <div>
                    <button class="btn btn-primary" onclick="showOutboundList()">📋 出库记录</button>
                    <button class="btn btn-success" onclick="confirmOutbound()" id="confirmBtn" disabled>✅ 确认出库 (<span id="confirmCount">0</span>)</button>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 400px; gap:20px;">
            <div class="card">
                <div class="card-title">🛒 出库商品</div>
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
                <div id="emptyCart" style="text-align:center; padding:60px; color:#999; font-size:18px;">
                    扫描条码添加出库商品
                </div>
            </div>

            <div class="card" style="height:fit-content;">
                <div class="card-title">💰 结算信息</div>

                <div style="background:#f3f4f6; padding:20px; border-radius:12px; margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:#666;">商品种类</span>
                        <span id="totalTypes" style="font-weight:bold;">0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:#666;">商品总数</span>
                        <span id="totalQty" style="font-weight:bold;">0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:24px; margin-top:15px; padding-top:15px; border-top:2px solid #ddd;">
                        <span>合计金额</span>
                        <span id="totalAmount" style="font-weight:bold; color:#10b981;">¥0.00</span>
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
            </div>
        </div>

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
                <input type="text" id="stockSearch" placeholder="搜索商品..." style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px;" onkeyup="searchStock()">
                <select id="stockSeriesFilter" style="padding:10px; border:1px solid #ddd; border-radius:8px;" onchange="searchStock()">
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

        <div class="modal" id="scanResultModal">
            <div class="modal-content" style="max-width:500px;">
                <div class="modal-header">
                    <h3 class="modal-title">📷 选择库存</h3>
                    <button class="modal-close" onclick="closeScanModal()">&times;</button>
                </div>
                <div id="scanResultContent"></div>
            </div>
        </div>

        <div class="modal" id="priceModal">
            <div class="modal-content" style="max-width:400px;">
                <div class="modal-header">
                    <h3 class="modal-title">💰 修改价格</h3>
                    <button class="modal-close" onclick="closePriceModal()">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong id="priceProductName"></strong>
                </div>
                <div class="form-group">
                    <label class="form-label">出库售价</label>
                    <input type="number" step="0.01" class="form-input" id="editOutboundPrice" style="font-size:24px; text-align:center;">
                </div>
                <div class="form-group">
                    <label class="form-label">数量</label>
                    <input type="number" min="1" class="form-input" id="editOutboundQty" style="font-size:24px; text-align:center;">
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button class="btn btn-primary" style="flex:1;" onclick="updateCartItem()">确认</button>
                    <button class="btn btn-secondary" onclick="closePriceModal()">取消</button>
                </div>
            </div>
        </div>

        <div class="modal" id="historyModal">
            <div class="modal-content" style="max-width:1000px; max-height:80vh; overflow-y:auto;">
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
    let currentEditIndex = null;
    let stockData = [];
    let pendingStock = null;
    let scanTimer = null;

    document.getElementById('scanInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const barcode = this.value.trim();
            if (barcode) {
                searchAndShow(barcode);
                this.value = '';
            }
        }
    });

    document.getElementById('scanInput').addEventListener('input', function(e) {
        clearTimeout(scanTimer);
        if (this.value.length >= 5) {
            scanTimer = setTimeout(() => {
                const barcode = this.value.trim();
                if (barcode) {
                    searchAndShow(barcode);
                    this.value = '';
                }
            }, 250);
        }
    });

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

    async function searchAndShow(barcode) {
        try {
            const res = await fetch(`../api/search_stock.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();

            if (data.success && data.data && data.data.length > 0) {
                if (data.data.length === 1) {
                    upsertCartItem(data.data[0], 1);
                    renderCart();
                    updateStats();
                    return;
                }

                pendingStock = data.data;

                const content = document.getElementById('scanResultContent');
                const productName = data.data[0].common_name || data.data[0].product_name;

                let html = `<div style="margin-bottom:15px;"><strong>${productName}</strong></div>`;

                data.data.forEach((stock, idx) => {
                    html += `
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px; border:1px solid #ddd; border-radius:8px; margin-bottom:10px;">
                            <div>
                                <span class="condition-badge condition-${stock.condition_type}">${stock.condition_name}</span>
                                <div style="margin-top:5px; font-size:12px; color:#666;">
                                    批次: ${stock.batch_no} | 进价: ¥${parseFloat(stock.purchase_price).toFixed(2)} | 库存: ${stock.remaining_qty}
                                </div>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <input type="number" id="qty_${idx}" value="1" min="1" max="${stock.remaining_qty}" style="width:60px; padding:8px; text-align:center; border:1px solid #ddd; border-radius:4px;">
                                <button class="btn btn-success" onclick="addToCart(${idx})">+ 添加</button>
                            </div>
                        </div>
                    `;
                });

                content.innerHTML = html;
                document.getElementById('scanResultModal').classList.add('show');
            } else {
                alert('未找到库存或库存为零');
            }
        } catch (err) {
            console.error(err);
            alert('查询失败');
        }
    }

    function addToCart(index) {
        const stock = pendingStock[index];
        const qtyInput = document.getElementById(`qty_${index}`);
        const qty = parseInt(qtyInput.value) || 1;

        if (qty > stock.remaining_qty) {
            alert('超出库存数量');
            return;
        }

        upsertCartItem(stock, qty);

        closeScanModal();
        renderCart();
        updateStats();
    }

    function closeScanModal() {
        document.getElementById('scanResultModal').classList.remove('show');
        pendingStock = null;
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
                    ${item.common_name ? `<br><span style="font-size:11px;color:#999;">${item.product_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${item.condition_type}">${item.condition_name}</span></td>
                <td><code style="font-size:11px;">${item.batch_no}</code></td>
                <td>¥${item.purchase_price.toFixed(2)}</td>
                <td>
                    <input type="number" value="${item.price}" onclick="this.select()"
                           onchange="updatePrice(${index}, this.value)"
                           style="width:80px; padding:6px; text-align:center; border:1px solid #ddd; border-radius:4px; color:#10b981; font-weight:bold;">
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
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:40px;">暂无库存数据</td></tr>';
            return;
        }

        tbody.innerHTML = stock.map((s, idx) => `
            <tr>
                <td>
                    <strong>${s.common_name || s.product_name}</strong>
                    ${s.common_name ? `<br><span style="font-size:11px;color:#999;">${s.product_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${s.condition_type}">${s.condition_name}</span></td>
                <td><code style="font-size:11px;">${s.batch_no}</code></td>
                <td>¥${parseFloat(s.purchase_price).toFixed(2)}</td>
                <td style="font-weight:bold; ${s.remaining_qty <= 2 ? 'color:#ef4444;' : ''}">${s.remaining_qty}</td>
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
                    container.innerHTML = '<div style="text-align:center;color:#999;padding:60px;font-size:18px;">暂无出库记录</div>';
                } else {
                    container.innerHTML = outboundList.map(batch => `
                        <div style="margin-bottom:25px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
                            <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <span style="font-size:18px; font-weight:bold;">批次号: ${batch.batch_no || '-'}</span>
                                    <span style="margin-left:15px; opacity:0.9;">${batch.outbound_at}</span>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:12px; opacity:0.9;">共 ${batch.total_qty} 件</div>
                                    <div style="font-size:20px; font-weight:bold;">¥${batch.total_amount.toFixed(2)}</div>
                                </div>
                            </div>
                            <table style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>商品</th>
                                        <th>状态</th>
                                        <th>数量</th>
                                        <th>售价</th>
                                        <th>金额</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${batch.items.map(item => `
                                        <tr>
                                            <td>
                                                <strong>${item.common_name || item.product_name || '-'}</strong>
                                                ${item.common_name && item.product_name ? `<br><span style="font-size:11px;color:#999;">${item.product_name}</span>` : ''}
                                            </td>
                                            <td><span class="condition-badge condition-${item.condition_type}">${getConditionName(item.condition_type)}</span></td>
                                            <td>${item.qty}</td>
                                            <td>¥${parseFloat(item.outbound_price).toFixed(2)}</td>
                                            <td style="font-weight:bold;">¥${(parseFloat(item.outbound_price) * item.qty).toFixed(2)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            ${batch.order_no || batch.remark ? `
                                <div style="background:#f9fafb; padding:10px 20px; border-top:1px solid #e5e7eb; font-size:13px; color:#666;">
                                    ${batch.order_no ? `<span style="margin-right:20px;">订单号: <code>${batch.order_no}</code></span>` : ''}
                                    ${batch.remark ? `<span>备注: ${batch.remark}</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    `).join('');
                }
                document.getElementById('historyModal').classList.add('show');
            }
        } catch (err) {
            console.error(err);
            alert('加载失败');
        }
    }

    function getConditionName(type) {
        const map = {
            'sealed': '全新未拆',
            'opened': '已拆未玩',
            'boxless': '无盒',
            'flawed': '有瑕疵'
        };
        return map[type] || type;
    }

    function closeHistoryModal() {
        document.getElementById('historyModal').classList.remove('show');
    }

    loadStockOverview();
    document.getElementById('scanInput').focus();
    </script>
</body>
</html>