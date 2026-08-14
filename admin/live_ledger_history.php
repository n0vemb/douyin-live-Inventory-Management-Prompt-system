<?php
$pageTitle = '直播账本历史';
$currentPage = 'live_ledger_history';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../auth.php';
$user = getCurrentUser();
$canSeeProfit = $user['can_see_profit'] ?? true;
?>
<div class="page-title">直播账本历史</div>

<!-- 筛选 -->
<div class="card">
  <div class="search-bar" style="gap:12px; align-items:flex-end;">
    <div><label>开始日期</label><br><input type="date" id="startDate" class="form-input"></div>
    <div><label>结束日期</label><br><input type="date" id="endDate" class="form-input"></div>
    <div><label>场次</label><br><select id="sessionFilter" class="form-input" style="min-width:140px;"><option value="">全部</option></select></div>
    <div><label>客户昵称</label><br><input type="text" id="nicknameFilter" class="form-input" placeholder="模糊搜索" style="width:130px;"></div>
    <div><label>VIP编号</label><br><input type="text" id="vipFilter" class="form-input" placeholder="模糊搜索" style="width:120px;"></div>
    <div><label>活动类型</label><br>
      <select id="activityFilter" class="form-input">
        <option value="">全部</option>
        <option value="none">无活动</option>
        <option value="full_gift">满赠</option>
        <option value="full_reduce">满减</option>
        <option value="both">满减+满赠</option>
      </select>
    </div>
    <div style="margin-left:auto; display:flex; gap:12px;">
      <button class="btn btn-primary" onclick="search()">查询</button>
      <button class="btn btn-outline" onclick="resetFilter()">重置</button>
    </div>
  </div>
</div>

<!-- 视图切换 -->
<div class="card" style="padding:14px 24px; display:flex; align-items:center; gap:15px;">
  <span style="font-size:14px; font-weight:600; color:var(--text-secondary);">视图</span>
  <button class="btn <?= 'btn-primary' ?>" id="viewSessionBtn" onclick="setView('session')">按场次</button>
  <button class="btn btn-outline" id="viewCustomerBtn" onclick="setView('customer')">按客户</button>
  <button class="btn btn-outline" id="viewProductBtn" onclick="setView('product')">按商品</button>
</div>

<!-- 汇总 -->
<div class="stats-bar" id="summaryBar">
  <div class="stat-card"><div class="label">场次数</div><div class="value" id="sumSessions">-</div></div>
  <div class="stat-card"><div class="label">客户数</div><div class="value" id="sumCustomers">-</div></div>
  <div class="stat-card"><div class="label">总件数</div><div class="value" id="sumQty">-</div></div>
  <div class="stat-card"><div class="label">总消费</div><div class="value" id="sumGmv">-</div></div>
  <div class="stat-card"><div class="label">总成本</div><div class="value" id="sumCost">-</div></div>
  <div class="stat-card"><div class="label">毛利-无活动</div><div class="value" id="sumProfit">-</div></div>
</div>

<!-- 结果 -->
<div class="card">
  <div id="resultArea"><div class="empty-state">选择筛选条件后点击「查询」查看账本记录</div></div>
</div>

<style>
.stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
.stat-card { background: var(--bg-surface, #fff); border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.stat-card .label { font-size: 12px; color: var(--text-tertiary, #9ca3af); }
.stat-card .value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.session-block { border: 1px solid var(--border, #e5e7eb); border-radius: 10px; margin-bottom: 14px; overflow: hidden; }
.session-head { padding: 12px 16px; background: var(--bg-hover, #f3f4f6); display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background .15s; }
.session-head:hover { background: var(--primary-light, #eef2ff); }
.session-head .toggle-arrow { transition: transform .2s; margin-right: 8px; font-size: 12px; color: var(--text-tertiary, #9ca3af); }
.session-block.open .toggle-arrow { transform: rotate(90deg); }
.session-title { font-weight: 600; font-size: 14px; }
.session-body { padding: 14px 16px; display: none; }
.session-block.open .session-body { display: block; }
.empty-state { text-align:center; color:var(--text-tertiary, #9ca3af); padding:48px 20px; font-size:14px; }
</style>

<script>
let currentView = 'session';
let CAN_SEE_PROFIT = <?= $canSeeProfit ? 'true' : 'false' ?>;

function fmt(v) { return (parseFloat(v) || 0).toFixed(2); }
function fmtPct(v) { return ((parseFloat(v) || 0) * 100).toFixed(1) + '%'; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function loadSessions() {
    try {
        const res = await fetch('../api/live_ledger_list_sessions.php?status=ended');
        const data = await res.json();
        const select = document.getElementById('sessionFilter');
        (data.data.sessions || []).forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.session_name;
            select.appendChild(opt);
        });
    } catch (e) {}
}

function setView(v) {
    currentView = v;
    document.getElementById('viewSessionBtn').className = 'btn ' + (v === 'session' ? 'btn-primary' : 'btn-outline');
    document.getElementById('viewCustomerBtn').className = 'btn ' + (v === 'customer' ? 'btn-primary' : 'btn-outline');
    document.getElementById('viewProductBtn').className = 'btn ' + (v === 'product' ? 'btn-primary' : 'btn-outline');
    search();
}

function getParams() {
    const p = new URLSearchParams();
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    const sid = document.getElementById('sessionFilter').value;
    const nick = document.getElementById('nicknameFilter').value.trim();
    const vip = document.getElementById('vipFilter').value.trim();
    const act = document.getElementById('activityFilter').value;
    if (start) p.set('start_date', start);
    if (end) p.set('end_date', end);
    if (sid) p.set('session_id', sid);
    if (nick) p.set('nickname', nick);
    if (vip) p.set('vip_no', vip);
    if (act) p.set('activity_type', act);
    p.set('view', currentView);
    p.set('limit', 200);
    return p;
}

async function search() {
    const res = await fetch('../api/live_ledger_history.php?' + getParams().toString());
    const data = await res.json();
    if (!data.success) { document.getElementById('resultArea').innerHTML = '<div class="empty-state">' + esc(data.error || '查询失败') + '</div>'; return; }
    const area = document.getElementById('resultArea');
    if (data.data.view === 'session') renderSessions(data.data.sessions || []);
    else if (data.data.view === 'customer') renderCustomers(data.data.customers || []);
    else renderProducts(data.data.products || []);
}

function renderSessions(sessions) {
    let sums = { s: sessions.length, c: 0, q: 0, g: 0, cost: 0, p: 0 };
    let html = '';
    sessions.forEach(s => {
        const t = s.totals || {};
        sums.c += (t.customers || 0);
        sums.q += (t.qty || 0);
        sums.g += (t.gmv || 0);
        sums.cost += (t.cost || 0);
        sums.p += (t.profit_base || 0);
        const actLabel = s.activity_label || s.activity_type;
        html += `
        <div class="session-block">
            <div class="session-head" onclick="this.parentElement.classList.toggle('open')">
                <div style="display:flex; align-items:center;"><span class="toggle-arrow">▶</span><span class="session-title">${esc(s.session_name)}</span> <span class="muted" style="margin-left:8px;">#${s.id}</span></div>
                <div class="muted">${esc(actLabel)} · ${esc(s.created_at)} · 出库批次 ${esc(s.outbound_batch_no || '-')}</div>
            </div>
            <div class="session-body">
                <table>
                    <thead><tr><th>客户数</th><th>件数</th><th>消费</th>${CAN_SEE_PROFIT ? '<th>成本</th><th>毛利-无活动</th>' : ''}${CAN_SEE_PROFIT ? '<th>③毛利</th>' : ''}<th>毛利率</th></tr></thead>
                    <tbody><tr>
                        <td>${t.customers || 0}</td><td>${t.qty || 0}</td><td>¥${fmt(t.gmv)}</td>
                        ${CAN_SEE_PROFIT ? `<td>¥${fmt(t.cost)}</td><td>¥${fmt(t.profit_base)}</td><td>¥${fmt(t.profit_both)}</td>` : ''}
                        <td>${t.gmv ? fmtPct((t.profit_both ?? t.profit_base) / t.gmv) : '-'}</td>
                    </tr></tbody>
                </table>
                <div class="muted" style="margin-top:8px;">💡 点击标题查看场次明细（客户列表）</div>
            </div>
        </div>`;
    });
    document.getElementById('resultArea').innerHTML = html || '<div class="empty-state">暂无数据</div>';
    document.getElementById('sumSessions').textContent = sums.s;
    document.getElementById('sumCustomers').textContent = sums.c;
    document.getElementById('sumQty').textContent = sums.q;
    document.getElementById('sumGmv').textContent = '¥' + Math.round(sums.g);
    document.getElementById('sumCost').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(sums.cost)) : '—';
    document.getElementById('sumProfit').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(sums.p)) : '—';
}

function renderCustomers(customers) {
    let sums = { c: customers.length, q: 0, g: 0, cost: 0, p: 0 };
    let html = '<table><thead><tr><th>客户</th><th>场次</th><th>件数</th><th>消费</th>' + (CAN_SEE_PROFIT ? '<th>成本</th><th>③毛利</th><th>毛利率</th>' : '') + '<th>明细</th></tr></thead><tbody>';
    customers.forEach(c => {
        const m = c.metrics || {};
        sums.q += (m.total_qty || 0);
        sums.g += (m.gmv || 0);
        sums.cost += (m.cost || 0);
        sums.p += (m.profit_both || 0);
        const itemNames = (c.snapshot_items || []).filter(i => !i.is_gift).map(i => `${esc(i.product_name)}×${i.qty}`).join('、');
        const giftNames = (c.snapshot_gifts || []).map(g => `🎁${g.description || ''}(${fmt(g.cost)})`).join('、');
        html += `<tr>
            <td><b>${esc(c.nickname)}</b> ${c.vip_no ? `<span class="muted">${esc(c.vip_no)}</span>` : ''}</td>
            <td class="muted">${esc(c.session_name || '')}</td>
            <td>${m.total_qty || 0}</td>
            <td>¥${fmt(m.gmv)}</td>
            ${CAN_SEE_PROFIT ? `<td>¥${fmt(m.cost)}</td><td>¥${fmt(m.profit_both)}</td><td>${fmtPct(m.profit_both_rate)}</td>` : ''}
            <td class="muted" style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${esc(itemNames)}${giftNames ? ' | ' + esc(giftNames) : ''}">${esc(itemNames)}${giftNames ? ' | ' + esc(giftNames) : ''}</td>
        </tr>`;
    });
    html += '</tbody></table>';
    document.getElementById('resultArea').innerHTML = html || '<div class="empty-state">暂无数据</div>';
    document.getElementById('sumSessions').textContent = '-';
    document.getElementById('sumCustomers').textContent = sums.c;
    document.getElementById('sumQty').textContent = sums.q;
    document.getElementById('sumGmv').textContent = '¥' + Math.round(sums.g);
    document.getElementById('sumCost').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(sums.cost)) : '—';
    document.getElementById('sumProfit').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(sums.p)) : '—';
}

function renderProducts(products) {
    let sums = { q: 0, g: 0, cost: 0, p: 0 };
    let html = '<table><thead><tr><th>商品</th><th>销量</th><th>库存</th><th>销售额</th>' + (CAN_SEE_PROFIT ? '<th>成本</th><th>毛利</th><th>毛利率</th>' : '') + '</tr></thead><tbody>';
    products.forEach(p => {
        sums.q += (p.qty || 0);
        sums.g += (p.gmv || 0);
        sums.cost += (p.cost || 0);
        sums.p += (p.profit || 0);
        html += `<tr>
            <td>${esc(p.product_name)}</td>
            <td>${p.qty || 0}</td>
            <td>${p.stock ?? 0}</td>
            <td>¥${fmt(p.gmv)}</td>
            ${CAN_SEE_PROFIT ? `<td>¥${fmt(p.cost)}</td><td>¥${fmt(p.profit)}</td><td>${fmtPct(p.profit_rate)}</td>` : ''}
        </tr>`;
    });
    html += '</tbody></table>';
    document.getElementById('resultArea').innerHTML = html || '<div class="empty-state">暂无数据</div>';
    document.getElementById('sumSessions').textContent = '-';
    document.getElementById('sumCustomers').textContent = '-';
    document.getElementById('sumQty').textContent = sums.q;
    document.getElementById('sumGmv').textContent = '¥' + Math.round(sums.g);
    document.getElementById('sumCost').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(sums.cost)) : '—';
    document.getElementById('sumProfit').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(sums.p)) : '—';
}

function resetFilter() {
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('sessionFilter').value = '';
    document.getElementById('nicknameFilter').value = '';
    document.getElementById('vipFilter').value = '';
    document.getElementById('activityFilter').value = '';
    search();
}

loadSessions();
search();
</script>
