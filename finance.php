<?php
$pageTitle = '财务管理';
$currentPage = 'finance';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">财务管理</div>

        <!-- 日期筛选 -->
        <div class="card" style="margin-bottom:18px;">
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <span style="font-weight:600; white-space:nowrap;">日期范围</span>
                <input type="date" id="dateFrom" class="form-input" style="width:150px;" onchange="loadFinance()">
                <span>至</span>
                <input type="date" id="dateTo" class="form-input" style="width:150px;" onchange="loadFinance()">
                <div style="display:flex; gap:6px;">
                    <button class="btn btn-sm period-btn active" data-days="7" onclick="setPreset(7)">近7天</button>
                    <button class="btn btn-sm period-btn" data-days="30" onclick="setPreset(30)">近30天</button>
                    <button class="btn btn-sm period-btn" data-days="this_month" onclick="setPreset('this_month')">本月</button>
                    <button class="btn btn-sm period-btn" data-days="last_month" onclick="setPreset('last_month')">上月</button>
                </div>
                <span style="color:var(--text-tertiary);">|</span>
                <span style="font-size:13px; color:var(--text-secondary);">平台</span>
                <span id="platformChips" style="display:flex; gap:6px; flex-wrap:wrap;"></span>
                <span style="color:var(--text-tertiary);">|</span>
                <span style="font-size:13px; color:var(--text-secondary);">账号</span>
                <span id="accountChips" style="display:flex; gap:6px; flex-wrap:wrap;"></span>
                <span style="color:var(--text-tertiary);">|</span>
                <span style="font-size:13px; color:var(--text-secondary);">备注</span>
                <span id="remarkChips" style="display:flex; gap:6px; flex-wrap:wrap;"></span>
            </div>
        </div>

        <!-- 汇总卡片 -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:20px;">
            <div style="background:linear-gradient(135deg, #667eea, #764ba2); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(102,126,234,0.2);">
                <div style="font-size:28px; font-weight:bold;" id="sumGmv">-</div>
                <div style="font-size:13px; opacity:0.85;">总GMV</div>
            </div>
            <div style="background:linear-gradient(135deg, #f59e0b, #d97706); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(245,158,11,0.2);">
                <div style="font-size:28px; font-weight:bold;" id="sumCost">-</div>
                <div style="font-size:13px; opacity:0.85;">总成本</div>
            </div>
            <div style="background:linear-gradient(135deg, #06b6d4, #0891b2); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(6,182,212,0.2);">
                <div style="font-size:28px; font-weight:bold;" id="sumShipping">-</div>
                <div style="font-size:13px; opacity:0.85;">总快递费</div>
            </div>
            <div style="background:linear-gradient(135deg, #8b5cf6, #7c3aed); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(139,92,246,0.2);">
                <div style="font-size:28px; font-weight:bold;" id="sumPlatform">-</div>
                <div style="font-size:13px; opacity:0.85;">总平台抽成</div>
            </div>
            <div style="background:linear-gradient(135deg, #ef4444, #dc2626); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(239,68,68,0.2);">
                <div style="font-size:28px; font-weight:bold;" id="sumAd">-</div>
                <div style="font-size:13px; opacity:0.85;">总投流费</div>
            </div>
            <div style="background:linear-gradient(135deg, #10b981, #059669); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(16,185,129,0.2);">
                <div style="font-size:28px; font-weight:bold;" id="sumProfit">-</div>
                <div style="font-size:13px; opacity:0.85;">总利润</div>
            </div>
        </div>

        <!-- 利润趋势 -->
        <div class="card" style="margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <div class="card-title" style="margin:0;">利润趋势</div>
                <div style="display:flex; gap:6px;">
                    <button class="btn btn-sm trend-btn active" data-period="day" onclick="switchTrend('day')">按日</button>
                    <button class="btn btn-sm trend-btn" data-period="week" onclick="switchTrend('week')">按周</button>
                    <button class="btn btn-sm trend-btn" data-period="month" onclick="switchTrend('month')">按月</button>
                </div>
            </div>
            <div style="position:relative; height:280px;">
                <canvas id="profitChart"></canvas>
            </div>
        </div>

        <!-- 批次列表 -->
        <div class="card">
            <div class="card-title">出库批次</div>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>出库时间</th>
                            <th>批次号</th>
                            <th>订单号</th>
                            <th>平台</th>
                            <th>账号</th>
                            <th>备注</th>
                            <th>商品数</th>
                            <th>货物成本</th>
                            <th>GMV</th>
                            <th>订单数</th>
                            <th>快递费</th>
                            <th>平台抽成</th>
                            <th>投流费</th>
                            <th>利润</th>
                            <th>利润率</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="batchList"></tbody>
                </table>
            </div>
        </div>

        <!-- 编辑弹窗 -->
        <div class="modal" id="editFinanceModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">编辑财务数据</h3>
                    <button class="modal-close" onclick="closeModal('editFinanceModal')">&times;</button>
                </div>
                <form onsubmit="saveFinance(event)">
                    <input type="hidden" id="editBatchNo">
                    <div class="form-group">
                        <label class="form-label">批次号</label>
                        <input type="text" class="form-input" id="editBatchNoDisplay" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">直播平台</label>
                        <select class="form-input" id="editPlatform">
                            <option value="">-- 选填 --</option>
                            <option value="小红书">小红书</option>
                            <option value="抖音">抖音</option>
                            <option value="视频号">视频号</option>
                            <option value="其他平台">其他平台</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">账号</label>
                        <input type="text" class="form-input" id="editAccount" placeholder="对应直播账号">
                    </div>
                    <div class="form-group">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-input" id="editRemark" placeholder="备注信息">
                    </div>
                    <div class="form-group">
                        <label class="form-label">GMV (成交金额)</label>
                        <input type="number" class="form-input" id="editGmv" step="0.01" placeholder="平台实际成交金额(含运费)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">订单数 / 快递单数</label>
                        <input type="number" class="form-input" id="editOrderCount" step="1" placeholder="实际发货订单数">
                    </div>
                    <div class="form-group">
                        <label class="form-label">投流费用</label>
                        <input type="number" class="form-input" id="editAdSpend" step="0.01" placeholder="本次投放流量费用">
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editFinanceModal')">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <style>
        .period-btn, .trend-btn {
            background: var(--bg-hover); border: 1px solid var(--border); color: var(--text-secondary);
            cursor: pointer; padding: 6px 12px; border-radius: 6px; font-size: 13px; transition: all 0.2s;
        }
        .period-btn.active, .trend-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .period-btn:hover:not(.active), .trend-btn:hover:not(.active) { border-color: var(--primary); color: var(--primary); }
        .remark-chip {
            display: inline-block; padding: 5px 12px; border-radius: 20px;
            background: var(--bg-hover); border: 1px solid var(--border);
            color: var(--text-secondary); font-size: 13px; cursor: pointer;
            white-space: nowrap; transition: all 0.15s; user-select: none;
        }
        .remark-chip:hover { border-color: var(--primary); color: var(--primary); }
        .remark-chip.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
        let profitChart = null;
        let currentTrendPeriod = 'day';

        // 初始化日期
        (function() {
            const now = new Date();
            document.getElementById('dateTo').value = now.toISOString().slice(0,10);
            const ago = new Date(now - 30*86400000);
            document.getElementById('dateFrom').value = ago.toISOString().slice(0,10);
        })();

        function setPreset(days) {
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            const now = new Date();
            document.getElementById('dateTo').value = now.toISOString().slice(0,10);
            if (days === 'this_month') {
                document.getElementById('dateFrom').value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0,10);
                document.querySelector('[data-days="this_month"]').classList.add('active');
            } else if (days === 'last_month') {
                const last = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                const lastEnd = new Date(now.getFullYear(), now.getMonth(), 0);
                document.getElementById('dateFrom').value = last.toISOString().slice(0,10);
                document.getElementById('dateTo').value = lastEnd.toISOString().slice(0,10);
                document.querySelector('[data-days="last_month"]').classList.add('active');
            } else {
                document.getElementById('dateFrom').value = new Date(now - days*86400000).toISOString().slice(0,10);
                document.querySelector('[data-days="' + days + '"]').classList.add('active');
            }
            loadFinance();
        }

        function switchTrend(period) {
            currentTrendPeriod = period;
            document.querySelectorAll('.trend-btn').forEach(b => b.classList.toggle('active', b.dataset.period === period));
            loadTrend();
        }

        async function loadFinance() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            try {
                let url = '../api/get_finance.php?date_from=' + dateFrom + '&date_to=' + dateTo;
                const ap = document.getElementById('platformChips').dataset.active || '';
                if (ap) url += '&platform=' + encodeURIComponent(ap === '__empty__' ? '' : ap);
                const aa = document.getElementById('accountChips').dataset.active || '';
                if (aa) url += '&account=' + encodeURIComponent(aa === '__empty__' ? '' : aa);
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) return;

                const s = data.data.summary;
                document.getElementById('sumGmv').textContent = '¥' + s.total_gmv.toLocaleString();
                document.getElementById('sumCost').textContent = '¥' + s.total_cost.toLocaleString();
                document.getElementById('sumShipping').textContent = '¥' + s.total_shipping.toLocaleString();
                document.getElementById('sumPlatform').textContent = '¥' + s.total_platform_fee.toLocaleString();
                document.getElementById('sumAd').textContent = '¥' + s.total_ad_spend.toLocaleString();
                document.getElementById('sumProfit').textContent = '¥' + s.total_profit.toLocaleString();

                const tbody = document.getElementById('batchList');

                // 平台筛选标签
                const platforms = [...new Set(data.data.batches.map(b => b.platform || '').filter(p => p))];
                const pChipsEl = document.getElementById('platformChips');
                const activePlatform = pChipsEl.dataset.active || '';
                pChipsEl.innerHTML = '<span class="remark-chip' + (activePlatform === '' ? ' active' : '') + '" onclick="filterBy(\'platform\', \'\')">全部</span>' +
                    platforms.map(p => '<span class="remark-chip' + (activePlatform === p ? ' active' : '') + '" onclick="filterBy(\'platform\', \'' + escHtml(p).replace(/'/g, "\\'") + '\')">' + escHtml(p) + '</span>').join('') +
                    '<span class="remark-chip' + (activePlatform === '__empty__' ? ' active' : '') + '" onclick="filterBy(\'platform\', \'__empty__\')" style="opacity:0.6;">空</span>';

                // 账号筛选标签
                const accounts = [...new Set(data.data.batches.map(b => b.account || '').filter(a => a))];
                const aChipsEl = document.getElementById('accountChips');
                const activeAccount = aChipsEl.dataset.active || '';
                aChipsEl.innerHTML = '<span class="remark-chip' + (activeAccount === '' ? ' active' : '') + '" onclick="filterBy(\'account\', \'\')">全部</span>' +
                    accounts.map(a => '<span class="remark-chip' + (activeAccount === a ? ' active' : '') + '" onclick="filterBy(\'account\', \'' + escHtml(a).replace(/'/g, "\\'") + '\')">' + escHtml(a) + '</span>').join('') +
                    '<span class="remark-chip' + (activeAccount === '__empty__' ? ' active' : '') + '" onclick="filterBy(\'account\', \'__empty__\')" style="opacity:0.6;">空</span>';

                // 备注筛选标签
                const remarks = [...new Set(data.data.batches.map(b => (b.remark || '').trim()).filter(r => r))];
                const chipsEl = document.getElementById('remarkChips');
                const activeRemark = chipsEl.dataset.active || '';
                chipsEl.innerHTML = '<span class="remark-chip' + (activeRemark === '' ? ' active' : '') + '" onclick="filterBy(\'remark\', \'\')">全部</span>' +
                    remarks.map(r => '<span class="remark-chip' + (activeRemark === r ? ' active' : '') + '" onclick="filterBy(\'remark\', \'' + escHtml(r).replace(/'/g, "\\'") + '\')">' + escHtml(r) + '</span>').join('') +
                    '<span class="remark-chip' + (activeRemark === '__empty__' ? ' active' : '') + '" onclick="filterBy(\'remark\', \'__empty__\')" style="opacity:0.6;">空</span>';

                // 筛选
                let filtered = data.data.batches;
                if (activePlatform === '__empty__') {
                    filtered = filtered.filter(b => !(b.platform || ''));
                } else if (activePlatform) {
                    filtered = filtered.filter(b => (b.platform || '') === activePlatform);
                }
                if (activeAccount === '__empty__') {
                    filtered = filtered.filter(b => !(b.account || ''));
                } else if (activeAccount) {
                    filtered = filtered.filter(b => (b.account || '') === activeAccount);
                }
                if (activeRemark === '__empty__') {
                    filtered = filtered.filter(b => !(b.remark || '').trim());
                } else if (activeRemark) {
                    filtered = filtered.filter(b => (b.remark || '').trim() === activeRemark);
                }

                if (!filtered.length) {
                    tbody.innerHTML = '<tr><td colspan="16" style="text-align:center; padding:30px; color:var(--text-tertiary);">暂无数据</td></tr>';
                } else {
                    tbody.innerHTML = filtered.map(b => {
                        const hasFinance = b.gmv !== null;
                        return `<tr>
                            <td>${(b.outbound_at || '').slice(0,16)}</td>
                            <td><code>${b.outbound_batch_no || '-'}</code></td>
                            <td>${escHtml(b.order_no || '-')}</td>
                            <td>${escHtml(b.platform || '-')}</td>
                            <td>${escHtml(b.account || '-')}</td>
                            <td style="max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escHtml(b.remark || '')}">${escHtml(b.remark || '-')}</td>
                            <td>${b.total_qty}</td>
                            <td>¥${b.total_cost.toFixed(2)}</td>
                            <td>${hasFinance ? '¥' + b.gmv.toFixed(2) : '<span style="color:var(--text-tertiary);">待填写</span>'}</td>
                            <td>${b.order_count !== null ? b.order_count : '-'}</td>
                            <td>${hasFinance ? '¥' + (b.shipping_cost || 0).toFixed(2) : '-'}</td>
                            <td>${hasFinance ? '¥' + (b.platform_fee || 0).toFixed(2) : '-'}</td>
                            <td>${b.ad_spend !== null ? '¥' + b.ad_spend.toFixed(2) : '-'}</td>
                            <td style="font-weight:bold; color:${b.profit !== null ? (b.profit >= 0 ? 'var(--success)' : 'var(--danger)') : 'var(--text-tertiary)'};">
                                ${b.profit !== null ? '¥' + b.profit.toFixed(2) : '-'}
                            </td>
                            <td style="font-weight:bold; color:${b.profit !== null ? (b.profit >= 0 ? 'var(--success)' : 'var(--danger)') : 'var(--text-tertiary)'};">
                                ${b.profit !== null && b.gmv > 0 ? (b.profit / b.gmv * 100).toFixed(2) + '%' : '-'}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick="openEdit('${b.outbound_batch_no}', '${(b.platform||'').replace(/'/g, "\\'")}', '${(b.account||'').replace(/'/g, "\\'")}', '${(b.remark||'').replace(/'/g, "\\'")}', ${b.gmv || 'null'}, ${b.order_count || 'null'}, ${b.ad_spend || 'null'})">编辑</button>
                            </td>
                        </tr>`;
                    }).join('');
                }

                loadTrend();
            } catch (e) {
                console.error('finance err:', e);
            }
        }

        async function loadTrend() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            try {
                const res = await fetch('../api/finance_trend.php?period=' + currentTrendPeriod + '&date_from=' + dateFrom + '&date_to=' + dateTo);
                const data = await res.json();
                if (!data.success) return;

                const ctx = document.getElementById('profitChart').getContext('2d');
                if (profitChart) profitChart.destroy();

                profitChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.data.labels,
                        datasets: [{
                            label: '利润',
                            data: data.data.profit,
                            backgroundColor: data.data.profit.map(v => v >= 0 ? 'rgba(52,211,153,0.6)' : 'rgba(248,113,113,0.6)'),
                            borderColor: data.data.profit.map(v => v >= 0 ? '#34d399' : '#f87171'),
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { labels: { color: '#9d9daf', font: { size: 12 } } },
                            tooltip: {
                                callbacks: {
                                    label: ctx => '利润: ¥' + parseFloat(ctx.raw).toFixed(2)
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(42,42,58,0.5)' },
                                ticks: { color: '#6b6b80', font: { size: 11 } }
                            },
                            y: {
                                grid: { color: 'rgba(42,42,58,0.3)' },
                                ticks: {
                                    color: '#6b6b80', font: { size: 11 },
                                    callback: v => '¥' + v
                                }
                            }
                        }
                    }
                });
            } catch (e) {
                console.error('trend err:', e);
            }
        }

        function openEdit(batchNo, platform, account, remark, gmv, orderCount, adSpend) {
            document.getElementById('editBatchNo').value = batchNo;
            document.getElementById('editBatchNoDisplay').value = batchNo;
            document.getElementById('editPlatform').value = platform || '';
            document.getElementById('editAccount').value = account || '';
            document.getElementById('editRemark').value = remark || '';
            document.getElementById('editGmv').value = gmv || '';
            document.getElementById('editOrderCount').value = orderCount || '';
            document.getElementById('editAdSpend').value = adSpend || '';
            document.getElementById('editFinanceModal').classList.add('show');
        }

        async function saveFinance(e) {
            e.preventDefault();
            const batchNo = document.getElementById('editBatchNo').value;
            const platform = document.getElementById('editPlatform').value || null;
            const account = document.getElementById('editAccount').value.trim() || null;
            const remark = document.getElementById('editRemark').value.trim() || null;
            const gmv = parseFloat(document.getElementById('editGmv').value) || null;
            const orderCount = parseInt(document.getElementById('editOrderCount').value) || null;
            const adSpend = parseFloat(document.getElementById('editAdSpend').value) || null;
            try {
                const res = await fetch('../api/save_finance.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ outbound_batch_no: batchNo, gmv, order_count: orderCount, ad_spend: adSpend, platform, account, remark })
                });
                const data = await res.json();
                if (data.success) {
                    closeModal('editFinanceModal');
                    loadFinance();
                } else {
                    alert('保存失败: ' + (data.error || '未知错误'));
                }
            } catch (err) {
                alert('请求失败: ' + err.message);
            }
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        function filterBy(type, val) {
            const el = document.getElementById(
                type === 'platform' ? 'platformChips' : type === 'account' ? 'accountChips' : 'remarkChips'
            );
            if (el.dataset.active === val) {
                el.dataset.active = '';
            } else {
                el.dataset.active = val;
            }
            loadFinance();
        }

        function escHtml(s) {
            if (!s) return '';
            const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
        }

        loadFinance();
        </script>
