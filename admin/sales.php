<?php
$pageTitle = '直播销售记录';
$currentPage = 'sales';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">💰 销售记录</div>

        <div class="card">
            <div class="search-bar">
                <input type="date" id="startDate">
                <span style="color:var(--text-secondary); line-height:40px;">至</span>
                <input type="date" id="endDate">
                <select id="sessionFilter">
                    <option value="">全部场次</option>
                </select>
                <button class="btn btn-primary" onclick="loadSales()">查询</button>
            </div>

            <div style="display:flex; gap:30px; margin-bottom:20px; padding:20px; background:var(--bg-hover); border-radius:8px;">
                <div>
                    <div style="font-size:14px; color:var(--text-secondary);">总销售额</div>
                    <div style="font-size:28px; font-weight:bold; color:var(--success);" id="totalAmount">¥0.00</div>
                </div>
                <div>
                    <div style="font-size:14px; color:var(--text-secondary);">总销售数量</div>
                    <div style="font-size:28px; font-weight:bold; color:var(--primary);" id="totalQty">0</div>
                </div>
                <div>
                    <div style="font-size:14px; color:var(--text-secondary);">总盈利</div>
                    <div style="font-size:28px; font-weight:bold; color:var(--warning);" id="totalProfit">¥0.00</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>商品</th>
                        <th>条码</th>
                        <th>状态</th>
                        <th>进价</th>
                        <th>售价</th>
                        <th>数量</th>
                        <th>盈利</th>
                        <th>直播场次</th>
                    </tr>
                </thead>
                <tbody id="salesList"></tbody>
            </table>
        </div>
    </div>

    <script>
    async function loadSessions() {
        try {
            const res = await fetch('../api/list_sessions.php');
            const data = await res.json();
            const sessions = Array.isArray(data.data)
                ? data.data
                : (data.data && Array.isArray(data.data.sessions) ? data.data.sessions : []);

            const select = document.getElementById('sessionFilter');
            sessions.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                const sessionName = s.session_name || s.name || `场次 #${s.id}`;
                opt.textContent = sessionName + (s.status === 'active' ? ' [进行中]' : '');
                select.appendChild(opt);
            });
        } catch (err) {
            console.error(err);
        }
    }

    async function loadSales() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const sessionId = document.getElementById('sessionFilter').value;

        let url = '../api/list_sales.php?limit=500';
        // 指定了具体场次就不再加日期筛选，避免场次不在本月时查不到
        if (sessionId) {
            url += '&live_session_id=' + sessionId;
        } else {
            if (startDate) url += '&start_date=' + startDate;
            if (endDate) url += '&end_date=' + endDate;
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            let sales = data.data.sales;
            if (sessionId) {
                sales = sales.filter(s => s.live_session_id == sessionId);
            }

            renderSales(sales);

            if (data.data.summary) {
                document.getElementById('totalAmount').textContent = '¥' + (parseFloat(data.data.summary.total_amount) || 0).toFixed(2);
                document.getElementById('totalQty').textContent = data.data.summary.total_qty || 0;
                const profit = parseFloat(data.data.summary.total_profit) || 0;
                document.getElementById('totalProfit').textContent = (profit >= 0 ? '+' : '') + '¥' + profit.toFixed(2);
                document.getElementById('totalProfit').style.color = profit >= 0 ? 'var(--success)' : 'var(--danger)';
            } else {
                const totalAmount = sales.reduce((sum, s) => sum + (parseFloat(s.sale_price) * s.qty), 0);
                const totalQty = sales.reduce((sum, s) => sum + s.qty, 0);
                const totalProfit = sales.reduce((sum, s) => sum + (parseFloat(s.sale_price) - (parseFloat(s.batch_purchase_price) || 0)) * s.qty, 0);
                document.getElementById('totalAmount').textContent = '¥' + totalAmount.toFixed(2);
                document.getElementById('totalQty').textContent = totalQty;
                document.getElementById('totalProfit').textContent = (totalProfit >= 0 ? '+' : '') + '¥' + totalProfit.toFixed(2);
                document.getElementById('totalProfit').style.color = totalProfit >= 0 ? 'var(--success)' : 'var(--danger)';
            }

        } catch (err) {
            console.error(err);
        }
    }

    let typeNames = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };

    async function loadSettings() {
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            if (data.success && data.settings && data.settings.condition_types) {
                typeNames = Object.fromEntries(data.settings.condition_types.map(c => [c.key, c.name]));
            }
        } catch (e) {
            console.log('使用默认状态名称');
        }
    }

    function renderSales(sales) {
        const tbody = document.getElementById('salesList');

        if (!sales.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无销售记录</td></tr>';
            return;
        }

        tbody.innerHTML = sales.map(s => {
            const purchasePrice = parseFloat(s.batch_purchase_price) || 0;
            const profit = (parseFloat(s.sale_price) - purchasePrice) * s.qty;
            const profitColor = profit >= 0 ? 'var(--success)' : 'var(--danger)';
            return `
            <tr>
                <td>${s.sold_at}</td>
                <td><strong>${s.product_name || '未知'}</strong></td>
                <td><code style="background:var(--bg-hover);padding:4px 8px;border-radius:4px;">${s.barcode || '-'}</code></td>
                <td><span class="condition-badge condition-${s.condition_type}">${typeNames[s.condition_type] || s.condition_type}</span></td>
                <td>¥${purchasePrice.toFixed(2)}</td>
                <td class="text-success">¥${parseFloat(s.sale_price).toFixed(2)}</td>
                <td>${s.qty}</td>
                <td style="color:${profitColor};font-weight:bold;">${profit >= 0 ? '+' : ''}¥${profit.toFixed(2)}</td>
                <td>${s.live_session_id || '-'}</td>
            </tr>
        `}).join('');
    }

    // 从 URL 读取 session_id 参数（从场次报表跳转过来）
    const urlParams = new URLSearchParams(window.location.search);
    const urlSessionId = urlParams.get('session_id');

    const today = new Date();
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];

    async function initializePage() {
        await loadSettings();
        await loadSessions(); // 先等场次列表加载完毕
        if (urlSessionId) {
            document.getElementById('sessionFilter').value = urlSessionId;
        }
        await loadSales(); // 再加载销售数据（此时筛选条件已设置）
    }

    initializePage();

    // 场次筛选变更时自动刷新
    document.getElementById('sessionFilter').addEventListener('change', loadSales);
    document.getElementById('startDate').addEventListener('change', loadSales);
    document.getElementById('endDate').addEventListener('change', loadSales);
    </script>
</body>
</html>