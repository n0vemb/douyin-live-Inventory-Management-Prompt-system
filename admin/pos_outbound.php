<?php
/**
 * admin/pos_outbound.php — 门店待出库（店管/超管）
 * 线下收银订单逐单出库：订单卡片复用「直播出库记账」客户 DOM 结构
 * 出库（FIFO扣库存）/ 整单作废（=已退款）/ 单品删除（退一件重算）/ 彻底删除（店管）
 */
$pageTitle = '门店待出库';
$currentPage = 'pos_outbound';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/layout.php';
$IS_OPERATOR = isOperator(); // 运营：隐藏成本列，不可删订单
?>
<div class="page-title">门店待出库</div>

<!-- 顶部统计（对齐直播出库记账 stats-bar） -->
<div class="stats-bar">
    <div class="stat-card"><div class="label">待出库订单</div><div class="value" id="stOrders">0</div></div>
    <div class="stat-card"><div class="label">待出库总件数</div><div class="value" id="stPcs">0</div></div>
    <div class="stat-card"><div class="label">应收合计</div><div class="value" id="stAmt">¥0.00</div></div>
    <div class="stat-card"><div class="label">已出库</div><div class="value" id="stDone">0</div></div>
    <div class="stat-card"><div class="label">已作废</div><div class="value" id="stVoid">0</div></div>
</div>

<!-- 筛选 -->
<div class="card" style="padding:12px 16px; margin-bottom:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
        <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button class="btn btn-sm filter-btn" data-f="all" onclick="setFilter('all')">全部</button>
            <button class="btn btn-sm filter-btn" data-f="pending" onclick="setFilter('pending')">待出库</button>
            <button class="btn btn-sm filter-btn" data-f="done" onclick="setFilter('done')">已出库</button>
            <button class="btn btn-sm filter-btn" data-f="voided" onclick="setFilter('voided')">已作废</button>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="load()">刷新</button>
    </div>
</div>

<div id="list"></div>

<style>
/* ===== 复用「直播出库记账」页面内嵌样式（live_ledger.php 同款） ===== */
.flex { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.mb-10 { margin-bottom: 10px; }
.muted { color: var(--text-tertiary, #9ca3af); font-size: 12px; }
.stats-bar { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
.stat-card { background: var(--bg-surface, #fff); border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.stat-card .label { font-size: 12px; color: var(--text-tertiary, #9ca3af); }
.stat-card .value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.customer { border: 1px solid var(--border, #e5e7eb); border-radius: 12px; margin-bottom: 12px; background: var(--bg-surface, #fff); overflow: hidden; }
.customer.active { border-color: var(--primary, #6366f1); box-shadow: 0 0 0 2px rgba(99,102,241,.15); }
.customer-header { display: flex; align-items: center; padding: 12px 16px; cursor: pointer; background: var(--bg-hover, #f3f4f6); transition: background .15s; }
.customer-header:hover { background: var(--primary-light, #eef2ff); }
.customer-header .toggle-arrow { transition: transform .2s; margin-right: 8px; font-size: 12px; color: var(--text-tertiary, #9ca3af); }
.customer.collapsed .toggle-arrow { transform: rotate(-90deg); }
.customer-header .nickname { font-weight: 600; font-size: 15px; }
.customer-header .badge { background: var(--primary, #6366f1); color: #fff; border-radius: 20px; padding: 2px 10px; font-size: 12px; margin-right: 15px; }
.customer-header .summary { margin-left: 16px; font-size: 13px; color: var(--text-secondary, #6b7280); display: flex; gap: 16px; }
.customer-header .actions { margin-left: auto; display: flex; gap: 15px; }
.customer-body { padding: 16px; display: none; }
.customer:not(.collapsed) .customer-body { display: block; }
/* ===== 本页业务样式 ===== */
.filter-btn { background: var(--bg-hover, #f3f4f6); color: var(--text-secondary, #6b7280); border: 1px solid var(--border, #e5e7eb); }
.filter-btn:hover { background: var(--primary-light, #eef2ff); color: var(--primary, #6366f1); }
.filter-btn.active { background: var(--primary, #6366f1); color: #fff; border-color: var(--primary, #6366f1); }
.order-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.order-table th { text-align: left; color: var(--text-tertiary, #9ca3af); font-size: 12px; font-weight: 600; padding: 6px 10px; border-bottom: 1px solid var(--border, #e5e7eb); }
.order-table th.num { text-align: right; }
.order-table td { padding: 8px 10px; border-bottom: 1px dashed var(--border, #e5e7eb); }
.order-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.cond-tag { display: inline-block; font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 9px; margin-left: 6px; }
.cond-sealed { background: #e8f0ff; color: #2f6fed; }
.cond-opened { background: #eafaf0; color: #16a34a; }
.cond-boxless { background: #fff4e0; color: #b45309; }
.cond-flawed { background: #fdecec; color: #dc2626; }
.empty-box { text-align: center; color: var(--text-tertiary, #9ca3af); padding: 50px 20px; }
.cost-cell { color: var(--text-tertiary, #9ca3af); font-size: 12px; }
.order-foot { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; gap: 10px; flex-wrap: wrap; }
.order-foot .note { font-size: 12px; color: var(--text-tertiary, #9ca3af); }
.order-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.order-actions .btn { min-height: 36px; padding: 8px 16px; }
.pay-note { font-size: 12px; color: #b45309; font-weight: 600; }
</style>

<div class="toast" id="toast"></div>

<script>
const IS_OPERATOR = <?= $IS_OPERATOR ? 'true' : 'false' ?>;
let filter = 'pending';
const STATUS_BADGE = { pending: ['待出库', '#6366f1', '#fff'], done: ['已出库', '#10b981', '#fff'], voided: ['已作废', '#dc2626', '#fff'] };
const PAY_NAMES = { cash: '现金', scan: '扫码' };

function $(id) { return document.getElementById(id); }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

async function load() {
    try {
        const res = await fetch('../api/pos_orders.php?outbound_status=' + filter, { cache: 'no-store' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '加载失败');
        render(data);
    } catch (e) {
        toast(e.message, true);
    }
}

function setFilter(f) {
    filter = f;
    [...document.querySelectorAll('.filter-btn')].forEach(b => b.classList.toggle('active', b.dataset.f === f));
    load();
}

function render(d) {
    // 顶部统计：待出库口径 + 全量已出库/已作废（请求 all 一次）
    $('stOrders').textContent = d.stats.order_count;
    $('stPcs').textContent = d.stats.total_qty;
    $('stAmt').textContent = '¥' + d.stats.total_payable.toFixed(2);
    $('stAmt').style.color = 'var(--primary, #6366f1)';
    let doneCnt = 0, voidCnt = 0;
    d.orders.forEach(o => { if (o.outbound_status === 'done') doneCnt++; if (o.outbound_status === 'voided') voidCnt++; });
    $('stDone').textContent = doneCnt;
    $('stVoid').textContent = voidCnt;

    const box = $('list');
    if (!d.orders.length) {
        box.innerHTML = `<div class="empty-box">暂无${ { all: '', pending: '待出库', done: '已出库', voided: '已作废' }[filter] }订单</div>`;
        return;
    }
    box.innerHTML = d.orders.map(o => {
        const [bl, bg, fg] = STATUS_BADGE[o.outbound_status];
        // 成本列头：待出库阶段无成本数据（出库时才回填），仅显示「操作」；已出库才显示「成本/操作」
        const costCol = IS_OPERATOR ? '' : `<th class="num">${o.outbound_status === 'pending' ? '操作' : '成本/操作'}</th>`;
        const rows = o.items.map(it => `
            <tr>
                <td>${esc(it.name)}<span class="cond-tag cond-${esc(it.condition_type)}">${esc(it.cond_name || it.condition_type)}</span></td>
                <td class="num">¥${it.unit_price.toFixed(2)}</td>
                <td class="num">${it.qty}</td>
                <td class="num">¥${it.line_total.toFixed(2)}</td>
                ${IS_OPERATOR ? '' : `<td class="num">
                    ${it.cost_price != null ? `<span class="cost-cell">成本 ¥${it.cost_price.toFixed(2)}</span>` : ''}
                    ${o.outbound_status === 'pending' ? `<button class="btn btn-sm btn-outline" onclick="voidItem(${o.id},${it.id},event)">删除</button>` : ''}
                </td>`}
            </tr>`).join('');

        const discRows = o.discount_amount > 0
            ? `<tr><td colspan="3" style="text-align:right;color:var(--success,#10b981);font-weight:600">整单打折（${Math.round((1 - o.staff_discount) * 100)}%）</td>
                <td class="num" style="color:var(--success,#10b981)">−¥${o.discount_amount.toFixed(2)}</td><td></td></tr>` : '';

        const payInfo = o.outbound_status === 'pending'
            ? (o.pay_status === 'paid' ? `<span class="pay-note">已收款（${PAY_NAMES[o.pay_method] || o.pay_method}）</span>` : `<span class="pay-note">未收款：扫码待确认</span>`)
            : '';
        const foot = o.outbound_status === 'pending'
            ? `<div class="order-foot">
                <div class="note">${payInfo}</div>
                <div class="order-actions">
                    <button class="btn btn-primary" onclick="outbound(${o.id})">出库（扣库存）</button>
                    <button class="btn btn-danger" onclick="voidOrder(${o.id})">作废（=退款）</button>
                    ${IS_OPERATOR ? '' : `<button class="btn btn-outline" onclick="deleteOrder(${o.id})">删除</button>`}
                </div>
              </div>`
            : `<div class="order-foot">
                <div class="note">${o.outbound_status === 'done'
                    ? `该订单已出库，库存已扣减${o.paid_at ? ' · 收款 ' + o.paid_at : ''}`
                    : `该订单已作废${o.void_reason ? ' · ' + esc(o.void_reason) : ''}`}</div>
                <div class="order-actions">
                    ${(!IS_OPERATOR && o.outbound_status === 'voided') ? `<button class="btn btn-outline" onclick="deleteOrder(${o.id})">删除</button>` : ''}
                </div>
              </div>`;

        const summary = `<span>${o.item_count} 件</span><span>应付 <b style="color:var(--primary,#6366f1)">¥${o.payable.toFixed(2)}</b></span>`;

        // 复用 live_ledger 客户卡片结构
        return `<div class="customer" id="ord-${o.id}">
            <div class="customer-header" onclick="toggle(${o.id})">
                <span class="toggle-arrow">▼</span>
                <span class="nickname">${esc(o.order_no)}</span>
                <span class="badge" style="background:${bg};color:${fg}">${bl}</span>
                <span class="summary">
                    <span class="o-time">${esc(o.created_at)}</span>
                    ${summary}
                </span>
                <span class="actions">
                    <span class="muted" style="font-size:12px">${o.cashier_name ? esc(o.cashier_name) : '自助下单'}</span>
                </span>
            </div>
            <div class="customer-body">
                <table class="order-table">
                    <thead><tr><th>商品</th><th class="num">单价</th><th class="num">数量</th><th class="num">小计</th>${costCol}</tr></thead>
                    <tbody>${rows}${discRows}
                        <tr><td colspan="${IS_OPERATOR ? 3 : 3}" style="text-align:right;font-weight:700">应付合计</td>
                            <td class="num" style="font-weight:700;color:var(--primary,#6366f1);font-size:15px">¥${o.payable.toFixed(2)}</td>${IS_OPERATOR ? '' : '<td></td>'}</tr>
                    </tbody>
                </table>
                ${foot}
            </div>
        </div>`;
    }).join('');
}

function toggle(id) {
    const el = $('ord-' + id);
    if (el) el.classList.toggle('collapsed');
}

// 出库
async function outbound(orderId) {
    if (!confirm('确认出库？将按 FIFO 扣减库存。')) return;
    try {
        const res = await fetch('../api/pos_outbound.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'outbound', order_id: orderId })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '出库失败');
        toast('已出库，库存已扣减');
        load();
    } catch (e) { toast(e.message, true); }
}

// 整单作废 = 已退款
async function voidOrder(orderId) {
    if (!confirm('确认作废该订单？（已收款视为退款，将释放锁定库存）')) return;
    try {
        const res = await fetch('../api/pos_outbound.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'void', order_id: orderId })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '作废失败');
        toast('已作废（退款）');
        load();
    } catch (e) { toast(e.message, true); }
}

// 单品删除（退一件，重算金额）
async function voidItem(orderId, itemId, ev) {
    ev.stopPropagation();
    if (!confirm('确认删除该商品？将释放其锁定库存并重算订单金额。')) return;
    try {
        const res = await fetch('../api/pos_outbound.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'void_item', order_id: orderId, item_id: itemId })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '删除失败');
        toast('已删除该商品');
        load();
    } catch (e) { toast(e.message, true); }
}

// 彻底删除（店管）
async function deleteOrder(orderId) {
    if (!confirm('彻底删除订单记录？（不可恢复）')) return;
    try {
        const res = await fetch('../api/pos_outbound.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_order', order_id: orderId })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '删除失败');
        toast('订单已彻底删除');
        load();
    } catch (e) { toast(e.message, true); }
}

// toast
let toastT;
function toast(msg, isError) {
    const t = $('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.cssText = `position:fixed;bottom:26px;left:50%;transform:translateX(-50%);background:${isError ? '#b3261e' : '#1c2230'};color:#fff;padding:10px 18px;border-radius:10px;font-size:13.5px;z-index:999;max-width:90vw`;
    clearTimeout(toastT);
    toastT = setTimeout(() => { t.textContent = ''; t.style.cssText = 'display:none'; }, isError ? 10000 : 2000);
}

setFilter('pending'); // 默认待出库 tab + 首次加载
</script>
</body>
</html>
