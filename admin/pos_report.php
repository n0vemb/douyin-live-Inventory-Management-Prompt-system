<?php
/**
 * admin/pos_report.php — 线下销售报表（店管/超管，运营隐藏）
 * 按日：订单数 / 件数 / 销售额 / 毛利 / 支付方式拆分
 * UI 对齐「财务管理」finance.php：渐变汇总卡 + 预设日期 + 全局表格
 */
$pageTitle = '线下销售报表';
$currentPage = 'pos_report';
require_once __DIR__ . '/layout.php';
?>
<div class="page-title">线下销售报表</div>

<!-- 日期筛选 -->
<div class="card" style="margin-bottom:18px;">
    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
        <span style="font-weight:600; white-space:nowrap;">日期范围</span>
        <input type="date" id="fromDate" class="form-input" style="width:150px;" onchange="load()">
        <span>至</span>
        <input type="date" id="toDate" class="form-input" style="width:150px;" onchange="load()">
        <div style="display:flex; gap:6px;">
            <button class="btn btn-sm period-btn active" data-days="7" onclick="setPreset(7)">近7天</button>
            <button class="btn btn-sm period-btn" data-days="30" onclick="setPreset(30)">近30天</button>
            <button class="btn btn-sm period-btn" data-days="this_month" onclick="setPreset('this_month')">本月</button>
            <button class="btn btn-sm period-btn" data-days="last_month" onclick="setPreset('last_month')">上月</button>
        </div>
        <button class="btn btn-primary btn-sm" onclick="load()">查询</button>
        <span style="font-size:12px; color:var(--text-tertiary);">销售额 = 已收款且未作废订单；毛利仅含已出库订单（实际扣减批次进价）</span>
    </div>
</div>

<!-- 汇总卡片（对齐 finance.php 渐变卡） -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:14px; margin-bottom:20px;">
    <div style="background:linear-gradient(135deg, #667eea, #764ba2); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(102,126,234,0.2);">
        <div style="font-size:28px; font-weight:bold;" id="tOrders">-</div>
        <div style="font-size:13px; opacity:0.85;">订单数</div>
    </div>
    <div style="background:linear-gradient(135deg, #f59e0b, #d97706); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(245,158,11,0.2);">
        <div style="font-size:28px; font-weight:bold;" id="tQty">-</div>
        <div style="font-size:13px; opacity:0.85;">件数</div>
    </div>
    <div style="background:linear-gradient(135deg, #06b6d4, #0891b2); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(6,182,212,0.2);">
        <div style="font-size:28px; font-weight:bold;" id="tSales">-</div>
        <div style="font-size:13px; opacity:0.85;">销售额</div>
    </div>
    <div style="background:linear-gradient(135deg, #10b981, #059669); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(16,185,129,0.2);">
        <div style="font-size:28px; font-weight:bold;" id="tProfit">-</div>
        <div style="font-size:13px; opacity:0.85;">毛利</div>
    </div>
    <div style="background:linear-gradient(135deg, #06b6d4, #0891b2); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(6,182,212,0.2);">
        <div style="font-size:28px; font-weight:bold;" id="tCash">-</div>
        <div style="font-size:13px; opacity:0.85;">现金（单数 / 金额）</div>
    </div>
    <div style="background:linear-gradient(135deg, #8b5cf6, #7c3aed); padding:18px; border-radius:12px; color:white; box-shadow:0 3px 10px rgba(139,92,246,0.2);">
        <div style="font-size:28px; font-weight:bold;" id="tScan">-</div>
        <div style="font-size:13px; opacity:0.85;">扫码（单数 / 金额）</div>
    </div>
</div>

<!-- 心愿单（求补货统计） -->
<div class="card" style="margin-bottom:18px; padding:16px 18px;">
    <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
        <span style="font-size:16px;">🎁</span>
        <span style="font-weight:700; font-size:15px;">心愿单（求补货统计）</span>
        <span style="font-size:12px; color:var(--text-tertiary);">期间内顾客对售罄商品点击「求补货」的次数（每客户每商品仅计一次）</span>
    </div>
    <div style="font-size:13px; color:var(--text-secondary);" id="wishEmpty" style="display:none; padding:8px 0;">该时间段暂无求补货记录</div>
    <div style="overflow-x:auto; margin-top:8px;">
        <table>
            <thead>
                <tr>
                    <th>商品</th>
                    <th>系列</th>
                    <th style="text-align:right">求补货次数</th>
                    <th>最近求补货时间</th>
                </tr>
            </thead>
            <tbody id="wishTbody">
                <tr><td colspan="4" style="text-align:center;color:var(--text-tertiary);padding:20px">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 明细表 -->
<div class="card" style="padding:0; overflow:hidden;">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>日期</th>
                    <th style="text-align:right">订单数</th>
                    <th style="text-align:right">件数</th>
                    <th style="text-align:right">销售额</th>
                    <th style="text-align:right">毛利</th>
                    <th style="text-align:right">现金单数</th>
                    <th style="text-align:right">现金额</th>
                    <th style="text-align:right">扫码单数</th>
                    <th style="text-align:right">扫码额</th>
                </tr>
            </thead>
            <tbody id="tbody">
                <tr><td colspan="9" style="text-align:center;color:var(--text-tertiary);padding:30px">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.period-btn.active { background: var(--primary, #6366f1); color: #fff; border-color: var(--primary, #6366f1); }
</style>

<div class="toast" id="toast"></div>

<script>
function $(id) { return document.getElementById(id); }

// 预设日期区间
function setDefaultRange() {
    const d = new Date();
    const fmt = x => x.toISOString().slice(0, 10);
    $('fromDate').value = fmt(new Date(d.getTime() - 30 * 86400000));
    $('toDate').value = fmt(d);
}
function setPreset(days) {
    [...document.querySelectorAll('.period-btn')].forEach(b => b.classList.toggle('active', b.dataset.days == days));
    const d = new Date();
    const fmt = x => x.toISOString().slice(0, 10);
    $('toDate').value = fmt(d);
    if (days === 'this_month') {
        $('fromDate').value = fmt(new Date(d.getFullYear(), d.getMonth(), 1));
    } else if (days === 'last_month') {
        const first = new Date(d.getFullYear(), d.getMonth() - 1, 1);
        const last = new Date(d.getFullYear(), d.getMonth(), 0);
        $('fromDate').value = fmt(first);
        $('toDate').value = fmt(last);
    } else {
        $('fromDate').value = fmt(new Date(d.getTime() - days * 86400000));
    }
    load();
}

async function load() {
    const from = $('fromDate').value, to = $('toDate').value;
    if (!from || !to) { toast('请选择日期范围', true); return; }
    try {
        const res = await fetch(`../api/pos_report.php?from=${from}&to=${to}`, { cache: 'no-store' });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || '加载失败');
        render(data);
    } catch (e) { toast(e.message, true); }
}

function render(d) {
    $('tOrders').textContent = d.total.order_count;
    $('tQty').textContent = d.total.qty;
    $('tSales').textContent = '¥' + d.total.sales.toFixed(2);
    $('tProfit').textContent = '¥' + d.total.profit.toFixed(2);
    $('tCash').textContent = d.total.cash_orders + ' / ¥' + d.total.cash_sales.toFixed(2);
    $('tScan').textContent = d.total.scan_orders + ' / ¥' + d.total.scan_sales.toFixed(2);

    // 心愿单
    const wishes = d.wishlist || [];
    const wt = $('wishTbody');
    const we = $('wishEmpty');
    if (!wishes.length) {
        wt.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-tertiary);padding:20px">该时间段暂无求补货记录</td></tr>';
    } else {
        wt.innerHTML = wishes.map(w => `
            <tr>
                <td>${w.product_name || ('商品#' + w.product_id)}</td>
                <td>${w.series || '-'}</td>
                <td style="text-align:right"><b style="color:#e6021f">${w.wish_count}</b></td>
                <td>${(w.last_wished || '').replace('T', ' ').slice(0, 19)}</td>
            </tr>`).join('');
    }

    if (!d.rows.length) {
        $('tbody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary);padding:30px">该时间段暂无线下销售数据</td></tr>';
        return;
    }
    $('tbody').innerHTML = d.rows.map(r => `
        <tr>
            <td>${r.date}</td>
            <td style="text-align:right">${r.order_count}</td>
            <td style="text-align:right">${r.qty}</td>
            <td style="text-align:right">¥${r.sales.toFixed(2)}</td>
            <td style="text-align:right">¥${r.profit.toFixed(2)}</td>
            <td style="text-align:right">${r.cash_orders}</td>
            <td style="text-align:right">¥${r.cash_sales.toFixed(2)}</td>
            <td style="text-align:right">${r.scan_orders}</td>
            <td style="text-align:right">¥${r.scan_sales.toFixed(2)}</td>
        </tr>`).join('');
}

let toastT;
function toast(msg, isError) {
    const t = $('toast');
    if (!t) return;
    t.textContent = msg;
    t.style.cssText = `position:fixed;bottom:26px;left:50%;transform:translateX(-50%);background:${isError ? '#b3261e' : '#1c2230'};color:#fff;padding:10px 18px;border-radius:10px;font-size:13.5px;z-index:999;max-width:90vw`;
    clearTimeout(toastT);
    toastT = setTimeout(() => { t.textContent = ''; t.style.cssText = 'display:none'; }, isError ? 10000 : 2000);
}

setDefaultRange();
load();
</script>
</body>
</html>
