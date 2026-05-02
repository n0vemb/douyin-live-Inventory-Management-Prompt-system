<?php
$pageTitle = '直播销售记录';
$currentPage = 'sales';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">💰 销售记录</div>

        <div class="card">
            <div class="search-bar">
                <input type="date" id="startDate">
                <span style="color:#666; line-height:40px;">至</span>
                <input type="date" id="endDate">
                <select id="sessionFilter">
                    <option value="">全部场次</option>
                </select>
                <button class="btn btn-primary" onclick="loadSales()">查询</button>
            </div>

            <div style="display:flex; gap:30px; margin-bottom:20px; padding:20px; background:#f9fafb; border-radius:8px;">
                <div>
                    <div style="font-size:14px; color:#666;">总销售额</div>
                    <div style="font-size:28px; font-weight:bold; color:#10b981;" id="totalAmount">¥0.00</div>
                </div>
                <div>
                    <div style="font-size:14px; color:#666;">总销售数量</div>
                    <div style="font-size:28px; font-weight:bold; color:#667eea;" id="totalQty">0</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>商品</th>
                        <th>条码</th>
                        <th>状态</th>
                        <th>售价</th>
                        <th>数量</th>
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
        if (startDate) url += '&start_date=' + startDate;
        if (endDate) url += '&end_date=' + endDate;
        if (sessionId) url += '&live_session_id=' + sessionId;

        try {
            const res = await fetch(url);
            const data = await res.json();

            let sales = data.data.sales;
            if (sessionId) {
                sales = sales.filter(s => s.live_session_id == sessionId);
            }

            renderSales(sales);

            const totalAmount = sales.reduce((sum, s) => sum + (parseFloat(s.sale_price) * s.qty), 0);
            const totalQty = sales.reduce((sum, s) => sum + s.qty, 0);
            document.getElementById('totalAmount').textContent = '¥' + totalAmount.toFixed(2);
            document.getElementById('totalQty').textContent = totalQty;

        } catch (err) {
            console.error(err);
        }
    }

    function renderSales(sales) {
        const tbody = document.getElementById('salesList');
        const typeNames = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };

        if (!sales.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;padding:40px;">暂无销售记录</td></tr>';
            return;
        }

        tbody.innerHTML = sales.map(s => `
            <tr>
                <td>${s.sold_at}</td>
                <td><strong>${s.product_name || '未知'}</strong></td>
                <td><code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;">${s.barcode || '-'}</code></td>
                <td><span class="condition-badge condition-${s.condition_type}">${typeNames[s.condition_type] || s.condition_type}</span></td>
                <td class="text-success">¥${parseFloat(s.sale_price).toFixed(2)}</td>
                <td>${s.qty}</td>
                <td>${s.live_session_id || '-'}</td>
            </tr>
        `).join('');
    }

    const today = new Date();
    document.getElementById('endDate').value = today.toISOString().split('T')[0];
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('startDate').value = firstDay.toISOString().split('T')[0];

    loadSessions();
    loadSales();
    </script>
</body>
</html>