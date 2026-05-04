<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
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
                    <span>📈</span> 今日盈利
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
                <div style="background:var(--bg-surface); border-radius:12px; padding:18px;">
                    <div style="font-size:16px; font-weight:600; color:var(--text); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
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
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <div class="card-title" style="margin:0;">📈 销售趋势</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-sm period-btn active" data-period="day" onclick="switchPeriod('day')">按日</button>
                    <button class="btn btn-sm period-btn" data-period="week" onclick="switchPeriod('week')">按周</button>
                    <button class="btn btn-sm period-btn" data-period="month" onclick="switchPeriod('month')">按月</button>
                </div>
            </div>
            <canvas id="salesChart" height="300"></canvas>
        </div>

        <style>
        .period-btn { background:var(--bg-hover); border:1px solid var(--border); color:var(--text-secondary); cursor:pointer; padding:8px 16px; border-radius:6px; font-size:14px; transition:all 0.2s; }
        .period-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .period-btn:hover:not(.active) { border-color:var(--primary); color:var(--primary); }
        </style>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
    let salesChart = null;
    let currentPeriod = 'day';

    function switchPeriod(period) {
        currentPeriod = period;
        document.querySelectorAll('.period-btn').forEach(b => b.classList.toggle('active', b.dataset.period === period));
        loadTrend();
    }

    async function loadTrend() {
        try {
            const days = currentPeriod === 'month' ? 365 : 60;
            const res = await fetch(`../api/sales_trend.php?period=${currentPeriod}&days=${days}`);
            const data = await res.json();
            if (!data.success) return;

            const ctx = document.getElementById('salesChart').getContext('2d');
            if (salesChart) salesChart.destroy();

            salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.data.labels,
                    datasets: [
                        {
                            label: '销售额',
                            data: data.data.amounts,
                            backgroundColor: 'rgba(94, 92, 230, 0.7)',
                            borderColor: 'rgba(94, 92, 230, 1)',
                            borderWidth: 1,
                            borderRadius: 4,
                            yAxisID: 'y',
                            order: 2
                        },
                        {
                            label: '销量',
                            data: data.data.qtys,
                            type: 'line',
                            borderColor: '#34d399',
                            backgroundColor: 'rgba(52, 211, 153, 0.1)',
                            pointBackgroundColor: '#34d399',
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y1',
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { color: '#9d9daf', font: { size: 13 }, usePointStyle: true, padding: 20 }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(18,18,26,0.95)',
                            titleColor: '#e8e8ed',
                            bodyColor: '#9d9daf',
                            borderColor: '#2a2a3a',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) {
                                    if (ctx.dataset.label === '销售额') return '销售额: ¥' + parseFloat(ctx.raw).toFixed(2);
                                    return '销量: ' + ctx.raw + ' 件';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(42,42,58,0.5)', drawBorder: false },
                            ticks: { color: '#6b6b80', font: { size: 11 } }
                        },
                        y: {
                            position: 'left',
                            grid: { color: 'rgba(42,42,58,0.3)', drawBorder: false },
                            ticks: {
                                color: '#6b6b80',
                                font: { size: 11 },
                                callback: function(v) { return '¥' + v; }
                            }
                        },
                        y1: {
                            position: 'right',
                            grid: { display: false },
                            ticks: {
                                color: '#6b6b80',
                                font: { size: 11 },
                                callback: function(v) { return v + '件'; }
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('trend err:', e);
        }
    }

    async function loadStats() {
        try {
            const [productsRes, stockRes, salesRes] = await Promise.all([
                fetch('../api/list_products.php'),
                fetch('../api/stock_overview.php'),
                fetch('../api/sales_summary.php')
            ]);

            let productsData, stockData, salesData;
            try { productsData = await productsRes.json(); } catch (e) { productsData = { data: { products: [] } }; }
            try { stockData = await stockRes.json(); } catch (e) { stockData = { data: {} }; }
            try { salesData = await salesRes.json(); } catch (e) { salesData = { data: {} }; }

            const products = productsData.data && Array.isArray(productsData.data.products) ? productsData.data.products : [];

            document.getElementById('totalProducts').textContent = products.length;
            document.getElementById('totalStock').textContent = stockData.data.total_qty || 0;
            document.getElementById('stockValue').textContent = '¥' + parseFloat(stockData.data.total_value || 0).toLocaleString();
            document.getElementById('todaySales').textContent = '¥' + (salesData.data.today_sales_amount || 0).toLocaleString();
            document.getElementById('todayProfit').textContent = '¥' + (salesData.data.today_profit || 0).toLocaleString();
            document.getElementById('monthProfit').textContent = '¥' + (salesData.data.month_profit || 0).toLocaleString();
        } catch (err) {
            console.error(err);
        }
    }

    loadStats();
    loadTrend();
    </script>
</body>
</html>
