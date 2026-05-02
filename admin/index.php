<?php
$pageTitle = '数据概览';
$currentPage = 'index';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">📊 数据概览</div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:18px; margin-bottom:25px;">
            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:24px; border-radius:16px; color:white; box-shadow:0 4px 15px rgba(102,126,234,0.25);">
                <div style="font-size:42px; font-weight:bold; margin-bottom:6px;" id="totalProducts">-</div>
                <div style="font-size:16px; opacity:0.9; display:flex; align-items:center; gap:6px;">
                    <span>🏪</span> 商品种类
                </div>
            </div>
            <div style="background:linear-gradient(135deg, #10b981 0%, #059669 100%); padding:24px; border-radius:16px; color:white; box-shadow:0 4px 15px rgba(16,185,129,0.25);">
                <div style="font-size:42px; font-weight:bold; margin-bottom:6px;" id="totalStock">-</div>
                <div style="font-size:16px; opacity:0.9; display:flex; align-items:center; gap:6px;">
                    <span>📦</span> 总库存件数
                </div>
            </div>
            <div style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding:24px; border-radius:16px; color:white; box-shadow:0 4px 15px rgba(245,158,11,0.25);">
                <div style="font-size:42px; font-weight:bold; margin-bottom:6px;" id="stockValue">-</div>
                <div style="font-size:16px; opacity:0.9; display:flex; align-items:center; gap:6px;">
                    <span>💰</span> 库存总价值
                </div>
            </div>
            <div style="background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding:24px; border-radius:16px; color:white; box-shadow:0 4px 15px rgba(239,68,68,0.25);">
                <div style="font-size:42px; font-weight:bold; margin-bottom:6px;" id="todaySales">-</div>
                <div style="font-size:16px; opacity:0.9; display:flex; align-items:center; gap:6px;">
                    <span>📈</span> 今日销售额
                </div>
            </div>
            <div style="background:linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); padding:24px; border-radius:16px; color:white; box-shadow:0 4px 15px rgba(6,182,212,0.25);">
                <div style="font-size:42px; font-weight:bold; margin-bottom:6px;" id="todayProfit">-</div>
                <div style="font-size:16px; opacity:0.9; display:flex; align-items:center; gap:6px;">
                    <span>�</span> 今日盈利
                </div>
            </div>
            <div style="background:linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); padding:24px; border-radius:16px; color:white; box-shadow:0 4px 15px rgba(139,92,246,0.25);">
                <div style="font-size:42px; font-weight:bold; margin-bottom:6px;" id="monthProfit">-</div>
                <div style="font-size:16px; opacity:0.9; display:flex; align-items:center; gap:6px;">
                    <span>📊</span> 本月盈利
                </div>
            </div>
        </div>

        <div style="display:flex; gap:20px; margin-bottom:25px; flex-wrap:wrap;">
            <div style="flex:1; min-width:280px;">
                <div style="background:#fff; border-radius:12px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <div style="font-size:16px; font-weight:600; color:#374151; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                        <span>⚡</span> 快捷操作
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:10px;">
                        <a href="products.php" style="flex:1; min-width:140px; background:linear-gradient(135deg, #667eea, #764ba2); color:white; text-align:center; padding:14px; border-radius:8px; text-decoration:none; font-weight:600;">🏪 商品管理</a>
                        <a href="outbound.php" style="flex:1; min-width:140px; background:linear-gradient(135deg, #10b981, #059669); color:white; text-align:center; padding:14px; border-radius:8px; text-decoration:none; font-weight:600;">📦 扫码出库</a>
                        <a href="sessions.php" style="flex:1; min-width:140px; background:linear-gradient(135deg, #f59e0b, #d97706); color:white; text-align:center; padding:14px; border-radius:8px; text-decoration:none; font-weight:600;">📺 直播场次</a>
                        <a href="sales.php" style="flex:1; min-width:140px; background:linear-gradient(135deg, #06b6d4, #0891b2); color:white; text-align:center; padding:14px; border-radius:8px; text-decoration:none; font-weight:600;">💰 销售记录</a>
                    </div>
                </div>
            </div>
            <div style="flex:1; min-width:280px;">
                <div style="background:#fff; border-radius:12px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <div style="font-size:16px; font-weight:600; color:#374151; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                        <span>🎬</span> 当前直播
                    </div>
                    <div id="liveSession">
                        <div style="text-align:center; color:#999; padding:20px;">暂无进行中的直播</div>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(400px, 1fr)); gap:20px;">
            <div class="card">
                <div class="card-title">📦 最近入库</div>
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>商品</th>
                            <th>状态</th>
                            <th>数量</th>
                            <th>进价</th>
                        </tr>
                    </thead>
                    <tbody id="recentPurchase"></tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-title">💰 最近出库</div>
                <table>
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>商品</th>
                            <th>状态</th>
                            <th>售价</th>
                            <th>盈利</th>
                        </tr>
                    </thead>
                    <tbody id="recentOutbound"></tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-top:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <div class="card-title" style="margin:0;">⚠️ 低库存商品</div>
                <a href="products.php" style="color:#667eea; text-decoration:none; font-size:14px;">查看全部 →</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>状态</th>
                        <th>库存</th>
                        <th>建议售价</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="lowStockList"></tbody>
            </table>
        </div>
    </div>

    <script>
    let conditionNames = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };

    async function loadSettings() {
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            if (data.success && data.settings && data.settings.condition_types) {
                conditionNames = Object.fromEntries(data.settings.condition_types.map(c => [c.key, c.name]));
            }
        } catch (e) {
            console.log('使用默认状态名称');
        }
    }

    async function loadStats() {
        try {
            const [productsRes, stockRes, outboundRes, salesRes] = await Promise.all([
                fetch('../api/list_products.php'),
                fetch('../api/stock_overview.php'),
                fetch('../api/list_outbound.php'),
                fetch('../api/sales_summary.php')
            ]);
            const productsData = await productsRes.json();
            const stockData = await stockRes.json();
            const outboundData = await outboundRes.json();
            const salesData = await salesRes.json();

            document.getElementById('totalProducts').textContent = productsData.data.products.length;
            document.getElementById('totalStock').textContent = stockData.data.total_qty;
            document.getElementById('stockValue').textContent = '¥' + parseFloat(stockData.data.total_value || 0).toLocaleString();
            document.getElementById('todaySales').textContent = '¥' + (salesData.data.today_sales_amount || 0).toLocaleString();
            document.getElementById('todayProfit').textContent = '¥' + (salesData.data.today_profit || 0).toLocaleString();
            document.getElementById('monthProfit').textContent = '¥' + (salesData.data.month_profit || 0).toLocaleString();

            let lowStockList = [];
            let lowStockCount = 0;
            productsData.data.products.forEach(p => {
                if (p.inventory_summary) {
                    Object.keys(p.inventory_summary).forEach(type => {
                        const item = p.inventory_summary[type];
                        const qty = item.total_stock || 0;
                        if (qty > 0 && qty <= 2) {
                            lowStockCount++;
                            lowStockList.push({
                                product_id: p.id,
                                product_name: p.common_name || p.name,
                                official_name: p.name,
                                condition_type: type,
                                condition_name: conditionNames[type] || type,
                                qty: qty,
                                suggested_price: item.suggested_price
                            });
                        }
                    });
                }
            });
            document.getElementById('lowStock').textContent = lowStockCount;

            renderRecentPurchase(productsData.data.products);
            renderRecentOutbound(outboundData.data.outbound.slice(0, 10));
            renderLowStockList(lowStockList);
            loadCurrentLiveSession(productsData.data.products);

        } catch (err) {
            console.error(err);
        }
    }

    async function loadCurrentLiveSession(products) {
        try {
            const sessionsRes = await fetch('../api/list_sessions.php');
            const sessionsData = await sessionsRes.json();
            const sessionList = Array.isArray(sessionsData.data)
                ? sessionsData.data
                : (sessionsData.data && Array.isArray(sessionsData.data.sessions) ? sessionsData.data.sessions : []);
            const activeSessions = sessionList.filter(s => s.status === 'active');
            const div = document.getElementById('liveSession');
            
            if (activeSessions.length > 0) {
                const session = activeSessions[0];
                const sessionName = session.session_name || session.name || `场次 #${session.id}`;
                div.innerHTML = `
                    <div style="background:#f0fdf4; border:1px solid #a7f3d0; padding:16px; border-radius:8px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:10px; height:10px; background:#10b981; border-radius:50%; animation:pulse 1.5s infinite;"></div>
                                <span style="font-weight:600; color:#065f46;">正在直播中</span>
                            </div>
                            <span style="font-size:12px; color:#666;">${session.started_at}</span>
                        </div>
                        <div style="font-size:18px; font-weight:700; color:#111827; margin-bottom:4px;">${sessionName}</div>
                        <div style="font-size:13px; color:#666; margin-bottom:12px;">
                            📺 场次 ID: ${session.id}
                        </div>
                        <a href="../live.php?session_id=${session.id}" target="_blank" style="display:block; width:100%; background:linear-gradient(135deg, #10b981, #059669); color:white; text-align:center; padding:10px; border-radius:6px; text-decoration:none; font-weight:600;">
                            进入直播间 →
                        </a>
                    </div>
                `;
            } else {
                div.innerHTML = `
                    <div style="text-align:center; color:#999; padding:20px;">
                        <div style="font-size:48px; margin-bottom:10px;">🎬</div>
                        <div style="margin-bottom:10px;">暂无进行中的直播</div>
                        <a href="sessions.php" style="color:#667eea; text-decoration:none; font-size:14px;">创建直播场次 →</a>
                    </div>
                `;
            }
        } catch (err) {
            console.error(err);
        }
    }

    function renderRecentPurchase(products) {
        const tbody = document.getElementById('recentPurchase');
        if (!products.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">暂无数据</td></tr>';
            return;
        }

        let recentBatches = [];
        products.forEach(p => {
            if (p.batches) {
                p.batches.forEach(b => {
                    recentBatches.push({
                        product_name: p.common_name || p.name,
                        condition_type: b.condition_type,
                        condition_name: conditionNames[b.condition_type] || b.condition_type,
                        qty: b.total_qty,
                        purchase_price: b.purchase_price,
                        purchased_at: b.purchased_at
                    });
                });
            }
        });
        
        recentBatches.sort((a, b) => b.purchased_at.localeCompare(a.purchased_at));
        recentBatches = recentBatches.slice(0, 10);

        if (!recentBatches.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">暂无入库记录</td></tr>';
            return;
        }

        tbody.innerHTML = recentBatches.map(b => `
            <tr>
                <td>${b.purchased_at.split(' ')[0]}</td>
                <td>${b.product_name}</td>
                <td><span class="condition-badge condition-${b.condition_type}">${b.condition_name}</span></td>
                <td style="font-weight:600;">${b.qty}</td>
                <td style="color:#ef4444; font-weight:600;">¥${parseFloat(b.purchase_price).toFixed(2)}</td>
            </tr>
        `).join('');
    }

    function renderRecentOutbound(outbound) {
        const tbody = document.getElementById('recentOutbound');
        if (!outbound.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">暂无数据</td></tr>';
            return;
        }

        tbody.innerHTML = outbound.map(o => {
            const profit = o.batch_purchase_price ? 
                (parseFloat(o.outbound_price) - parseFloat(o.batch_purchase_price)) * parseInt(o.qty) : 0;
            const profitClass = profit >= 0 ? 'color:#10b981' : 'color:#ef4444';
            return `
                <tr>
                    <td>${o.outbound_at.split(' ')[0]}</td>
                    <td>${o.product_name || o.common_name || '-'}</td>
                    <td><span class="condition-badge condition-${o.condition_type}">${conditionNames[o.condition_type] || o.condition_type}</span></td>
                    <td style="color:#10b981; font-weight:600;">¥${parseFloat(o.outbound_price).toFixed(2)}</td>
                    <td style="font-weight:700; ${profitClass};">¥${profit.toFixed(2)}</td>
                </tr>
            `;
        }).join('');
    }

    function renderLowStockList(stockList) {
        const tbody = document.getElementById('lowStockList');
        if (!stockList.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999; padding:30px;">🎉 暂无低库存商品</td></tr>';
            return;
        }

        tbody.innerHTML = stockList.slice(0, 15).map(s => `
            <tr style="background:#fef2f2;">
                <td>
                    <strong>${s.product_name}</strong>
                    ${s.official_name !== s.product_name ? `<br><span style="font-size:12px; color:#999;">${s.official_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${s.condition_type}">${s.condition_name}</span></td>
                <td style="color:#ef4444; font-weight:800; font-size:18px;">${s.qty}</td>
                <td>¥${parseFloat(s.suggested_price).toFixed(2)}</td>
                <td>
                    <a href="products.php" style="color:#667eea; text-decoration:none; font-size:14px; font-weight:600;">立即补货</a>
                </td>
            </tr>
        `).join('');
    }

    async function initializePage() {
        await loadSettings();
        await loadStats();
    }

    initializePage();
    </script>
    <style>
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.3); }
    }
    </style>
</body>
</html>
