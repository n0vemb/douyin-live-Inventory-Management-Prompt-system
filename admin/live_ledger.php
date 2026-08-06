<?php
$pageTitle = '直播出库记账';
$currentPage = 'live_ledger';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../auth.php';
$user = getCurrentUser();
$canSeeProfit = $user['can_see_profit'] ?? true;
$isOperator = $user['role'] === 'operator';
?>
<div class="page-title">直播出库记账</div>

<!-- 顶部统计 -->
<div class="stats-bar" id="statsBar" style="display:none;">
  <div class="stat-card"><div class="label">客户数</div><div class="value" id="statCustomers">0</div></div>
  <div class="stat-card"><div class="label">总件数</div><div class="value" id="statTotalQty">0</div></div>
  <div class="stat-card"><div class="label">总消费</div><div class="value" id="statTotalGmv">¥0</div></div>
  <div class="stat-card"><div class="label">总成本</div><div class="value" id="statTotalCost">¥0</div></div>
  <div class="stat-card"><div class="label">毛利-无活动</div><div class="value" id="statTotalProfit">¥0</div></div>
</div>

<!-- 场次选择/新建（未进入时显示） -->
<div class="card" id="sessionListCard">
  <div class="flex-between mb-10">
    <h3 style="font-size:16px; font-weight:600; margin:0;">当前场次</h3>
    <button class="btn btn-success" onclick="openNewSessionModal()">新建场次</button>
  </div>
  <table>
    <thead>
      <tr>
        <th>场次名称</th>
        <th>状态</th>
        <th>创建时间</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody id="sessionList"></tbody>
  </table>
</div>

<!-- 当前场次信息（进入后显示） -->
<div class="card" id="sessionInfoCard" style="display:none;">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h3 style="font-size:16px; font-weight:600; margin:0;">当前场次：<span id="sessionInfoName">-</span></h3>
    <button class="btn btn-outline" onclick="exitSession()">返回场次列表</button>
  </div>
</div>

<!-- 场次设置（选中后显示） -->
<div class="card" id="settingsCard" style="display:none;">
  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="font-size:16px; font-weight:600;">场次设置</div>
    <button class="btn btn-primary btn-sm" onclick="saveSettings()">保存设置</button>
  </div>
  <div style="display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:nowrap; gap:16px;">
    <div style="flex-shrink:0; width:220px;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">场次名称</label>
      <input type="text" id="sessionName" class="form-input" style="width:100%; box-sizing:border-box;">
    </div>
    <div style="flex-shrink:0; width:120px;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">活动类型</label>
      <select id="activityType" class="form-input" style="width:100%; box-sizing:border-box;" onchange="activityChange()">
        <option value="none">无活动</option>
        <option value="full_gift">满赠</option>
        <option value="full_reduce">满减</option>
        <option value="both">满减+满赠</option>
      </select>
    </div>
    <div id="giftField" style="flex-shrink:0; width:105px; display:none;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">赠品触发（手动）</label>
      <div style="display:flex; align-items:center; gap:5px; white-space:nowrap;">
        <span style="font-size:13px; color:var(--text-secondary);">每满</span>
        <input type="number" id="giftEveryN" value="3" class="form-input" style="flex:1; min-width:30px; text-align:center;">
        <span style="font-size:13px; color:var(--text-secondary);">单</span>
      </div>
    </div>
    <div id="reduceField" style="flex-shrink:0; width:180px; display:none;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">满减规则</label>
      <div style="display:flex; align-items:center; gap:5px; white-space:nowrap;">
        <span style="font-size:13px; color:var(--text-secondary);">满</span>
        <input type="number" id="reduceThreshold" value="30" class="form-input" style="width:58px; text-align:center;">
        <span style="font-size:13px; color:var(--text-secondary);">减</span>
        <input type="number" id="reduceAmount" value="1" class="form-input" style="width:58px; text-align:center;">
        <span style="font-size:13px; color:var(--text-secondary);">元</span>
      </div>
    </div>
    <div style="flex-shrink:0; width:70px;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">平台扣点（%）</label>
      <input type="number" id="platformFeeRate" value="5" class="form-input" style="width:100%; box-sizing:border-box;">
    </div>
    <div style="flex-shrink:0; width:65px;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">包装成本（元）</label>
      <input type="number" id="packingCost" value="1" class="form-input" style="width:100%; box-sizing:border-box;">
    </div>
    <div style="flex-shrink:0; width:200px;">
      <label style="display:block; font-size:13px; color:var(--text-secondary); margin-bottom:4px;">运费（元）</label>
      <div style="display:flex; align-items:center; gap:5px; white-space:nowrap;">
        <input type="number" id="shippingFee8" value="3" class="form-input" style="width:54px; text-align:center;">
        <span style="font-size:13px; color:var(--text-secondary);">≤8件</span>
        <input type="number" id="shippingFee9" value="4" class="form-input" style="width:54px; text-align:center;">
        <span style="font-size:13px; color:var(--text-secondary);">≥9件</span>
      </div>
    </div>
  </div>
</div>

<!-- 操作按钮 -->
<div class="card" id="actionBar" style="display:none; padding:16px 24px;">
  <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
    <button class="btn btn-success" onclick="openAddCustomerModal()">新增客户</button>
    <button class="btn btn-primary" onclick="saveAll()">保存</button>
    <button class="btn btn-danger" onclick="endLive()">结束直播并出库</button>
    <span class="muted" style="margin-left:auto;">点击客户标题栏可收缩/展开</span>
  </div>
</div>

<!-- 客户列表 -->
<div class="card" id="customerListCard" style="display:none; padding:16px 24px;">
  <div id="customerList"></div>
</div>

<!-- 新建场次模态框 -->
<div class="modal" id="newSessionModal">
  <div class="modal-content" style="width:420px;">
    <div class="modal-header">
      <h3 class="modal-title">新建直播场次</h3>
      <button class="modal-close" onclick="closeNewSessionModal()">&times;</button>
    </div>
    <div style="margin-bottom:14px;">
      <label>场次名称</label>
      <input type="text" id="newSessionName" class="form-input" placeholder="如：8月6日晚场" style="margin-top:6px;">
    </div>
    <div class="flex" style="justify-content:flex-end; gap:15px;">
      <button class="btn btn-outline" onclick="closeNewSessionModal()">取消</button>
      <button class="btn btn-success" onclick="createSession()">创建</button>
    </div>
  </div>
</div>

<!-- 新增客户模态框 -->
<div class="modal" id="addCustomerModal">
  <div class="modal-content" style="width:420px;">
    <div class="modal-header">
      <h3 class="modal-title">新增客户</h3>
      <button class="modal-close" onclick="closeAddCustomerModal()">&times;</button>
    </div>
    <div style="margin-bottom:12px;">
      <label>VIP编号 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="newCustomerVip" class="form-input" placeholder="必填，输入后自动匹配昵称" style="margin-top:6px;" onblur="lookupNicknameByVip()" onkeydown="if(event.key==='Enter'){lookupNicknameByVip();event.preventDefault();}">
    </div>
    <div style="margin-bottom:14px;">
      <label>昵称 <span style="color:var(--text-tertiary); font-weight:400;">选填，VIP无记录时手动输入</span></label>
      <input type="text" id="newCustomerNickname" class="form-input" placeholder="自动匹配，无记录则手动输入" style="margin-top:6px;">
    </div>
    <div class="flex" style="justify-content:flex-end; gap:15px;">
      <button class="btn btn-outline" onclick="closeAddCustomerModal()">取消</button>
      <button class="btn btn-success" onclick="confirmAddCustomer()">添加客户</button>
    </div>
  </div>
</div>

<!-- 添加商品模态框（复用出库页搜索交互） -->
<div class="modal" id="addProductModal">
  <div class="modal-content modal-wide" style="width:560px;">
    <div class="modal-header">
      <h3 class="modal-title">添加商品</h3>
      <button class="modal-close" onclick="closeProductModal()">&times;</button>
    </div>
    <div style="position:relative;">
      <input type="text" id="productSearchInput" class="form-input" placeholder="扫描条码或输入拼音/名称搜索..." style="font-size:15px;" oninput="debounceSearchProduct()">
      <div class="search-dropdown" id="obSearchDropdown"></div>
    </div>
  </div>
</div>

<!-- 赠品弹窗 -->
<div class="modal" id="giftModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">添加赠品</h3>
      <button class="modal-close" onclick="closeGiftModal()">&times;</button>
    </div>
    <div style="margin-bottom:12px;">
      <label>赠品成本(元)</label>
      <input type="number" id="giftCostInput" class="form-input" step="0.01" min="0" placeholder="如 3.5" style="margin-top:6px;">
    </div>
    <div style="margin-bottom:14px;">
      <label>赠品说明(可选)</label>
      <input type="text" id="giftDescInput" class="form-input" placeholder="如 小挂件" style="margin-top:6px;">
    </div>
    <div class="flex" style="justify-content:flex-end; gap:15px;">
      <button class="btn btn-outline" onclick="closeGiftModal()">取消</button>
      <button class="btn btn-success" onclick="confirmGift()">确定添加</button>
    </div>
  </div>
</div>

<!-- 删除确认 -->
<div class="modal" id="confirmModal">
  <div class="modal-content" style="width:360px;">
    <div class="modal-header">
      <h3 class="modal-title">确认操作</h3>
      <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
    </div>
    <p id="confirmText" style="margin-bottom:16px;"></p>
    <div class="flex" style="justify-content:flex-end; gap:15px;">
      <button class="btn btn-outline" onclick="closeConfirmModal()">取消</button>
      <button class="btn btn-danger" id="confirmOkBtn">确定</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<style>
/* ===== 业务独有样式（通用类 .btn/.badge/.modal/.form-input 全部用全局 style.css） ===== */
.flex-between { display: flex; justify-content: space-between; align-items: center; }
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
.customer-header .badge { background: var(--primary, #6366f1); color: #fff; border-radius: 20px; padding: 2px 10px; font-size: 12px; margin-left: 8px; }
.customer-header .summary { margin-left: 16px; font-size: 13px; color: var(--text-secondary, #6b7280); display: flex; gap: 16px; }
.customer-header .actions { margin-left: auto; display: flex; gap: 15px; }
.customer-body { padding: 16px; display: none; }
.customer:not(.collapsed) .customer-body { display: block; }
.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-top: 14px; }
.metric { background: var(--bg-hover, #f3f4f6); border-radius: 8px; padding: 10px 12px; }
.metric .label { font-size: 11px; color: var(--text-tertiary, #9ca3af); margin-bottom: 4px; }
.metric .value { font-size: 15px; font-weight: 700; }
.metric .value.green { color: var(--success, #10b981); }
.metric .value.red { color: var(--danger, #ef4444); }
.metric .sub { font-size: 11px; color: var(--text-secondary, #6b7280); margin-top: 2px; }
.gift-row { background: #fefce8; }
.gift-badge { background: var(--warning, #f59e0b); color: #fff; font-size: 11px; padding: 1px 6px; border-radius: 4px; margin-left: 6px; }
.del-btn { background: none; border: none; font-size: 16px; cursor: pointer; color: var(--text-tertiary, #9ca3af); padding: 4px 6px; border-radius: 4px; }
.del-btn:hover { color: var(--danger, #ef4444); background: #fee2e2; }
.toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #111827; color: #fff; padding: 10px 20px; border-radius: 8px; font-size: 14px; z-index: 200; display: none; }
.toast.show { display: block; }

/* 场次表格：进行中行高亮（表格/badge/按钮/悬浮全部用全局样式） */
tr.tr-active { background: rgba(99,102,241,.04); }
tr.tr-active td:first-child { border-left: 3px solid var(--primary, #6366f1); }

/* 出库页同款搜索下拉（fixed 定位由 positionDropdown() 控制） */
.search-dropdown {
    background: var(--bg-elevated, #fff); border: 1px solid var(--border, #e5e7eb);
    border-radius: 12px; overflow: hidden; display: none;
    max-height: 60vh; overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0,0,0,.3); z-index: 200;
}
.search-dropdown.show { display: block; }
.search-dropdown-empty { padding: 30px; text-align: center; color: var(--text-tertiary, #9ca3af); font-size: 14px; }
.search-dropdown-header { padding: 10px 14px 6px; border-bottom: 1px solid var(--border, #e5e7eb); background: var(--bg-hover, #f3f4f6); }
.search-dropdown-header .sdi-product-name { font-weight: 600; font-size: 14px; }
.search-dropdown-header .sdi-product-meta { font-size: 11px; color: var(--text-tertiary, #9ca3af); margin-top: 2px; }
.search-dropdown-item { display: flex; align-items: center; gap: 10px; padding: 8px 14px; border-bottom: 1px solid var(--border, #e5e7eb); font-size: 13px; transition: background .15s; }
.search-dropdown-item:last-child { border-bottom: none; }
.search-dropdown-item:hover { background: var(--bg-hover, #f3f4f6); }
.sdi-stock { font-size: 12px; color: var(--text-secondary, #6b7280); min-width: 50px; }
.sdi-price { font-weight: bold; font-size: 14px; min-width: 65px; text-align: right; }
.sdi-add-btn { padding: 4px 14px; border-radius: 6px; border: none; background: var(--primary, #6366f1); color: #fff; font-size: 12px; cursor: pointer; font-weight: 600; }
.sdi-add-btn:hover { opacity: .85; }
.sdi-add-btn:disabled { background: var(--text-tertiary, #9ca3af); cursor: not-allowed; opacity: .5; }
</style>

<script>
let currentSessionId = null;
let sessionData = null;
let nextLocalId = -1;
let editingCustomerId = null;
let giftingCustomerId = null;
let searchTimer = null;
let obSearchResults = [];
let addMap = {};
const CAN_SEE_PROFIT = <?= $canSeeProfit ? 'true' : 'false' ?>;
const IS_OPERATOR = <?= $isOperator ? 'true' : 'false' ?>;

// ===== 场次管理 =====
async function loadSessions() {
    try {
        const res = await fetch('../api/live_ledger_list_sessions.php');
        const data = await res.json();
        const tbody = document.getElementById('sessionList');
        const sessions = data.data.sessions || [];
        if (!sessions.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无场次，点击「新建场次」开始</td></tr>';
            return;
        }
        const statusNames = { active: '进行中', ended: '已结束' };
        const statusClasses = { active: 'badge-success', ended: 'badge-info' };
        tbody.innerHTML = sessions.map(s => `
            <tr class="${s.status === 'active' ? 'tr-active' : ''}">
                <td><strong>${esc(s.session_name)}</strong></td>
                <td><span class="badge ${statusClasses[s.status] || 'badge-info'}">${statusNames[s.status] || s.status}</span></td>
                <td class="muted">${esc(s.created_at || '-')}</td>
                <td style="display:flex; gap:10px;">
                    <button class="btn btn-sm btn-primary" onclick="enterSession(${s.id})">进入</button>
                    ${IS_OPERATOR ? '' : `<button class="btn btn-sm btn-outline" onclick="confirmDeleteSession(${s.id}, '${esc(s.session_name)}')" style="color:var(--danger); border-color:var(--danger);">删除</button>`}
                </td>
            </tr>`).join('');
    } catch (e) { toast('加载场次失败: ' + e.message); }
}

function enterSession(id) {
    switchToSession(id);
}

function openNewSessionModal() {
    document.getElementById('newSessionName').value = '';
    document.getElementById('newSessionModal').classList.add('show');
    setTimeout(() => document.getElementById('newSessionName').focus(), 100);
}
function closeNewSessionModal() { document.getElementById('newSessionModal').classList.remove('show'); }

async function createSession() {
    const name = document.getElementById('newSessionName').value.trim();
    if (!name) { toast('请输入场次名称'); return; }
    try {
        const res = await fetch('../api/live_ledger_save_session.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ session_name: name, activity_type: 'both' })
        });
        const data = await res.json();
        if (data.success) {
            closeNewSessionModal();
            await loadSessions();
            switchToSession(data.data.session_id);
            toast('场次已创建');
        } else toast(data.error || '创建失败');
    } catch (e) { toast('创建失败: ' + e.message); }
}

function confirmDeleteSession(id, name) {
    showConfirm(`确定删除场次「${name}」吗？该场次的所有客户和购买记录将一并删除，且不可恢复。`, async () => {
        try {
            const res = await fetch('../api/live_ledger_delete_session.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: id })
            });
            const data = await res.json();
            if (data.success) {
                // 如果删的是当前场次，清空状态
                if (currentSessionId === id) {
                    currentSessionId = null; sessionData = null;
                    document.getElementById('settingsCard').style.display = 'none';
                    document.getElementById('actionBar').style.display = 'none';
                    document.getElementById('statsBar').style.display = 'none';
                    document.getElementById('sessionInfoCard').style.display = 'none';
                    document.getElementById('customerListCard').style.display = 'none';
                    document.getElementById('sessionListCard').style.display = 'block';
                }
                await loadSessions();
                toast('场次已删除');
            } else toast(data.error || '删除失败');
        } catch (e) { toast('删除失败: ' + e.message); }
    });
}

function switchSession() {
    // 清空状态，返回列表
    currentSessionId = null; sessionData = null;
    document.getElementById('settingsCard').style.display = 'none';
    document.getElementById('actionBar').style.display = 'none';
    document.getElementById('statsBar').style.display = 'none';
    document.getElementById('sessionInfoCard').style.display = 'none';
    document.getElementById('customerListCard').style.display = 'none';
    document.getElementById('sessionListCard').style.display = 'block';
}

function exitSession() {
    switchSession();
    loadSessions();
}

async function switchToSession(id) {
    currentSessionId = parseInt(id);
    try {
        const res = await fetch('../api/live_ledger_get_session.php?session_id=' + currentSessionId);
        const data = await res.json();
        if (!data.success) { toast(data.error || '加载失败'); return; }
        sessionData = data.data;
        fillSettings();
        // 进入场次：隐藏列表，显示信息+设置+操作+客户列表
        document.getElementById('sessionListCard').style.display = 'none';
        document.getElementById('sessionInfoCard').style.display = 'block';
        document.getElementById('sessionInfoName').textContent = sessionData.settings.session_name + ' · 创建于 ' + sessionData.settings.created_at;
        document.getElementById('settingsCard').style.display = 'block';
        document.getElementById('actionBar').style.display = 'flex';
        document.getElementById('statsBar').style.display = 'grid';
        document.getElementById('customerListCard').style.display = 'block';
        render();
    } catch (e) { toast('加载失败: ' + e.message); }
}

function fillSettings() {
    const s = sessionData.settings;
    document.getElementById('sessionName').value = s.session_name || '';
    document.getElementById('activityType').value = s.activity_type || 'none';
    document.getElementById('giftEveryN').value = s.gift_every_n || 3;
    document.getElementById('reduceThreshold').value = s.reduce_threshold || 30;
    document.getElementById('reduceAmount').value = s.reduce_amount || 1;
    document.getElementById('platformFeeRate').value = (s.platform_fee_rate || 0) * 100;
    document.getElementById('packingCost').value = s.packing_cost || 1;
    document.getElementById('shippingFee8').value = s.shipping_fee_8 || 3;
    document.getElementById('shippingFee9').value = s.shipping_fee_9 || 4;
    activityChange();
}

function activityChange() {
    const v = document.getElementById('activityType').value;
    document.getElementById('giftField').style.display = (v === 'full_gift' || v === 'both') ? 'block' : 'none';
    document.getElementById('reduceField').style.display = (v === 'full_reduce' || v === 'both') ? 'block' : 'none';
}

async function saveSettings() {
    try {
        const res = await fetch('../api/live_ledger_save_session.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                session_id: currentSessionId,
                session_name: document.getElementById('sessionName').value,
                activity_type: document.getElementById('activityType').value,
                gift_every_n: parseInt(document.getElementById('giftEveryN').value) || 3,
                reduce_threshold: parseFloat(document.getElementById('reduceThreshold').value) || 30,
                reduce_amount: parseFloat(document.getElementById('reduceAmount').value) || 1,
                platform_fee_rate: parseFloat(document.getElementById('platformFeeRate').value) || 5,
                packing_cost: parseFloat(document.getElementById('packingCost').value) || 1,
                shipping_fee_8: parseFloat(document.getElementById('shippingFee8').value) || 3,
                shipping_fee_9: parseFloat(document.getElementById('shippingFee9').value) || 4,
            })
        });
        const data = await res.json();
        if (data.success) { toast('设置已保存'); await switchToSession(currentSessionId); }
        else toast(data.error || '保存失败');
    } catch (e) { toast('保存失败: ' + e.message); }
}

// ===== 计算 =====
function getSettings() {
    const s = sessionData ? sessionData.settings : {};
    return {
        activity_type: document.getElementById('activityType').value || s.activity_type || 'none',
        gift_every_n: parseInt(document.getElementById('giftEveryN').value) || 3,
        reduce_threshold: parseFloat(document.getElementById('reduceThreshold').value) || 30,
        reduce_amount: parseFloat(document.getElementById('reduceAmount').value) || 1,
        platform_fee_rate: (parseFloat(document.getElementById('platformFeeRate').value) || 5) / 100,
        packing_cost: parseFloat(document.getElementById('packingCost').value) || 1,
        shipping_fee_8: parseFloat(document.getElementById('shippingFee8').value) || 3,
        shipping_fee_9: parseFloat(document.getElementById('shippingFee9').value) || 4,
    };
}

function calcCustomer(c) {
    const settings = getSettings();
    const realItems = (c.items || []).filter(i => !i.is_gift);
    const totalQty = realItems.reduce((s, i) => s + (parseInt(i.qty) || 0), 0);
    const gmv = realItems.reduce((s, i) => s + parseFloat(i.sell_price || 0) * (parseInt(i.qty) || 0), 0);
    const cost = realItems.reduce((s, i) => s + parseFloat(i.purchase_cost || 0) * (parseInt(i.qty) || 0), 0);
    const shipping = totalQty >= 9 ? settings.shipping_fee_9 : settings.shipping_fee_8;
    const platformFee = gmv * settings.platform_fee_rate;
    const packing = settings.packing_cost;
    const profitBase = gmv - cost - shipping - platformFee - packing;
    const giftCost = (c.gifts || []).reduce((s, g) => s + parseFloat(g.cost || 0), 0);
    const reduceAmount = gmv >= settings.reduce_threshold ? settings.reduce_amount : 0;
    return {
        totalQty, gmv, cost, shipping, platformFee, packing, profitBase,
        profitBaseRate: gmv > 0 ? profitBase / gmv : 0,
        giftCost, profitWithGift: profitBase - giftCost,
        profitWithGiftRate: gmv > 0 ? (profitBase - giftCost) / gmv : 0,
        reduceAmount, profitWithReduce: profitBase - reduceAmount,
        profitWithReduceRate: gmv > 0 ? (profitBase - reduceAmount) / gmv : 0,
        profitBoth: profitBase - giftCost - reduceAmount,
        profitBothRate: gmv > 0 ? (profitBase - giftCost - reduceAmount) / gmv : 0,
    };
}

function fmt(v) { return (parseFloat(v) || 0).toFixed(2); }
function fmtPct(v) { return ((parseFloat(v) || 0) * 100).toFixed(1) + '%'; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// ===== 渲染 =====
function render() {
    if (!sessionData) return;
    const customers = sessionData.customers || [];
    const settings = getSettings();
    const act = settings.activity_type;
    const showGift = act === 'full_gift' || act === 'both';
    const showReduce = act === 'full_reduce' || act === 'both';
    const list = document.getElementById('customerList');
    list.innerHTML = '';

    customers.forEach(c => {
        const m = calcCustomer(c);
        const collapsed = c._collapsed;

        let itemsHtml = (c.items || []).map(item => {
            const isGift = item.is_gift;
            return `<tr class="${isGift ? 'gift-row' : ''}">
                <td>${esc(item.product_name)}${isGift ? '<span class="gift-badge">赠品</span>' : ''}</td>
                ${CAN_SEE_PROFIT ? `<td>${fmt(item.purchase_cost)}</td>` : ''}
                <td><input type="number" step="0.01" value="${item.sell_price}" class="form-input" style="width:70px; padding:4px 8px;" onchange="updateItemPrice(${c.id}, ${item.id}, this.value)"></td>
                <td><input type="number" min="1" value="${item.qty}" class="form-input" style="width:60px; padding:4px 8px;" onchange="updateItemQty(${c.id}, ${item.id}, this.value)"></td>
                <td>${fmt(item.sell_price * item.qty)}</td>
                <td><button class="del-btn" onclick="deleteItem(${c.id}, ${item.id})">✕</button></td>
            </tr>`;
        }).join('');

        let giftHtml = (c.gifts || []).map((g, gi) => `
            <tr class="gift-row">
                <td>赠品${g.description ? ' - ' + esc(g.description) : ''}</td>
                ${CAN_SEE_PROFIT ? `<td>${fmt(g.cost)}</td>` : ''}
                <td colspan="${CAN_SEE_PROFIT ? 3 : 2}" style="color:var(--text-tertiary);">不入库，仅计成本</td>
                <td><button class="del-btn" onclick="deleteGift(${c.id}, ${gi})">✕</button></td>
            </tr>`).join('');

        let metrics = `
            <div class="metrics">
                <div class="metric"><div class="label">购买数量</div><div class="value">${m.totalQty}件</div></div>
                <div class="metric"><div class="label">消费合计</div><div class="value">¥${fmt(m.gmv)}</div></div>
                ${CAN_SEE_PROFIT ? `<div class="metric"><div class="label">娃成本</div><div class="value">¥${fmt(m.cost)}</div></div>` : ''}
                <div class="metric"><div class="label">运费</div><div class="value">¥${fmt(m.shipping)}</div><div class="sub">${m.totalQty}件</div></div>
                <div class="metric"><div class="label">平台扣点</div><div class="value">¥${fmt(m.platformFee)}</div></div>
                <div class="metric"><div class="label">包装成本</div><div class="value">¥${fmt(m.packing)}</div></div>
                ${CAN_SEE_PROFIT ? `
                <div class="metric"><div class="label">毛利-无活动</div><div class="value ${m.profitBase >= 0 ? 'green' : 'red'}">¥${fmt(m.profitBase)}</div><div class="sub">毛利率 ${fmtPct(m.profitBaseRate)}</div></div>
                ${showGift ? `<div class="metric"><div class="label">①满赠成本</div><div class="value">¥${fmt(m.giftCost)}</div></div>
                <div class="metric"><div class="label">①毛利-满赠</div><div class="value ${m.profitWithGift >= 0 ? 'green' : 'red'}">¥${fmt(m.profitWithGift)}</div><div class="sub">毛利率 ${fmtPct(m.profitWithGiftRate)}</div></div>` : ''}
                ${showReduce ? `<div class="metric"><div class="label">②满${settings.reduce_threshold}-${settings.reduce_amount}</div><div class="value">-¥${fmt(m.reduceAmount)}</div></div>
                <div class="metric"><div class="label">②毛利(满减不赠)</div><div class="value ${m.profitWithReduce >= 0 ? 'green' : 'red'}">¥${fmt(m.profitWithReduce)}</div><div class="sub">毛利率 ${fmtPct(m.profitWithReduceRate)}</div></div>` : ''}
                ${(showGift && showReduce) ? `<div class="metric"><div class="label">③毛利(满赠+满减)</div><div class="value ${m.profitBoth >= 0 ? 'green' : 'red'}">¥${fmt(m.profitBoth)}</div><div class="sub">毛利率 ${fmtPct(m.profitBothRate)}</div></div>` : ''}` : ''}
            </div>`;

        list.innerHTML += `
            <div class="customer ${collapsed ? 'collapsed' : 'active'}" id="cust_${c.id}">
                <div class="customer-header" onclick="toggleCustomer(${c.id})">
                    <span class="toggle-arrow">▼</span>
                    <span class="nickname">${esc(c.nickname) || '(未命名)'}</span>
                    ${c.vip_no ? `<span class="badge">${esc(c.vip_no)}</span>` : ''}
                    <span class="summary"><span>${m.totalQty}件</span><span>¥${fmt(m.gmv)}</span></span>
                    <span class="actions" onclick="event.stopPropagation()">
                        <button class="btn btn-sm btn-outline" onclick="addGift(${c.id})">赠品</button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteCustomer(${c.id})">删除</button>
                    </span>
                </div>
                <div class="customer-body">
                    <div class="search-bar mb-10">
                        <label>昵称</label><input type="text" value="${esc(c.nickname)}" class="form-input" style="width:140px;" onchange="updateNickname(${c.id}, this.value)">
                        <label>VIP编号</label><input type="text" value="${esc(c.vip_no)}" class="form-input" style="width:120px;" placeholder="选填" onchange="updateVip(${c.id}, this.value)">
                    </div>
                    <table>
                        <thead><tr><th>商品</th>${CAN_SEE_PROFIT ? '<th>进价</th>' : ''}<th>售价</th><th>数量</th><th>小计</th><th></th></tr></thead>
                        <tbody>${itemsHtml}${giftHtml}</tbody>
                    </table>
                    <div style="margin-top:10px;">
                        <button class="btn btn-sm btn-primary" onclick="openProductModal(${c.id})">添加商品</button>
                    </div>
                    ${metrics}
                </div>
            </div>`;
    });

    let tq = 0, tg = 0, tc = 0, tp = 0;
    customers.forEach(c => { const m = calcCustomer(c); tq += m.totalQty; tg += m.gmv; tc += m.cost; tp += m.profitBase; });
    document.getElementById('statCustomers').textContent = customers.length;
    document.getElementById('statTotalQty').textContent = tq;
    document.getElementById('statTotalGmv').textContent = '¥' + Math.round(tg);
    document.getElementById('statTotalCost').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(tc)) : '—';
    document.getElementById('statTotalProfit').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(tp)) : '—';
}

// ===== 客户操作 =====
function toggleCustomer(id) {
    const c = (sessionData.customers || []).find(x => x.id === id);
    if (c) { c._collapsed = !c._collapsed; render(); }
}

function openAddCustomerModal() {
    document.getElementById('newCustomerVip').value = '';
    document.getElementById('newCustomerNickname').value = '';
    document.getElementById('addCustomerModal').classList.add('show');
    setTimeout(() => document.getElementById('newCustomerVip').focus(), 100);
}
function closeAddCustomerModal() { document.getElementById('addCustomerModal').classList.remove('show'); }

// VIP编号失焦/回车时自动匹配昵称
async function lookupNicknameByVip() {
    const vip = document.getElementById('newCustomerVip').value.trim();
    const nickInput = document.getElementById('newCustomerNickname');
    if (!vip) { nickInput.value = ''; return; }
    try {
        const res = await fetch('../api/live_ledger_lookup_vip.php?vip_no=' + encodeURIComponent(vip));
        const data = await res.json();
        if (data.success && data.data.nickname) {
            nickInput.value = data.data.nickname;
        }
    } catch (e) {}
}

function confirmAddCustomer() {
    const vip = document.getElementById('newCustomerVip').value.trim();
    const nick = document.getElementById('newCustomerNickname').value.trim();
    if (!vip) { toast('请输入VIP编号'); return; }
    if (!nick) { toast('未匹配到昵称，请手动输入'); return; }
    sessionData.customers.push({ id: nextLocalId--, nickname: nick, vip_no: vip, items: [], gifts: [], _collapsed: false });
    closeAddCustomerModal();
    render();
    toast(`客户「${nick}」已添加（记得保存）`);
}

function updateNickname(id, v) { const c = (sessionData.customers || []).find(x => x.id === id); if (c) c.nickname = v; }
function updateVip(id, v) { const c = (sessionData.customers || []).find(x => x.id === id); if (c) c.vip_no = v; }

function updateItemPrice(cid, iid, v) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    const item = (c.items || []).find(i => i.id === iid);
    if (item) { item.sell_price = parseFloat(v) || 0; render(); }
}
function updateItemQty(cid, iid, v) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    const item = (c.items || []).find(i => i.id === iid);
    if (item) { item.qty = parseInt(v) || 1; render(); }
}

function deleteItem(cid, iid) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    c.items = (c.items || []).filter(i => i.id !== iid);
    render();
}
function deleteGift(cid, gi) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    c.gifts.splice(gi, 1);
    render();
}

function confirmDeleteCustomer(id) {
    showConfirm('确定删除该客户及其所有购买记录吗？', async () => {
        sessionData.customers = (sessionData.customers || []).filter(x => x.id !== id);
        render();
        await saveAll();
        toast('客户已删除');
    });
}

// ===== 商品搜索（复用出库页交互） =====
function openProductModal(cid) {
    editingCustomerId = cid;
    document.getElementById('productSearchInput').value = '';
    document.getElementById('obSearchDropdown').classList.remove('show');
    document.getElementById('addProductModal').classList.add('show');
    setTimeout(() => document.getElementById('productSearchInput').focus(), 100);
}
function closeProductModal() {
    document.getElementById('addProductModal').classList.remove('show');
    document.getElementById('obSearchDropdown').classList.remove('show');
}

function debounceSearchProduct() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const kw = document.getElementById('productSearchInput').value.trim();
        if (!kw) { document.getElementById('obSearchDropdown').classList.remove('show'); return; }
        searchOutboundStock(kw);
    }, 300);
}

// 下拉改为 fixed 定位（跟随输入框），避免被弹窗 overflow 裁剪
function positionDropdown() {
    const input = document.getElementById('productSearchInput');
    const dd = document.getElementById('obSearchDropdown');
    const rect = input.getBoundingClientRect();
    dd.style.position = 'fixed';
    dd.style.top = (rect.bottom + 4) + 'px';
    dd.style.left = rect.left + 'px';
    dd.style.right = 'auto';
    dd.style.width = rect.width + 'px';
    dd.style.zIndex = '9999';
}

window.addEventListener('scroll', () => {
    const dd = document.getElementById('obSearchDropdown');
    if (dd && dd.classList.contains('show')) positionDropdown();
}, true);

function searchOutboundStock(keyword) {
    fetch('../api/search_outbound_stock.php?keyword=' + encodeURIComponent(keyword))
        .then(r => r.json())
        .then(data => {
            obSearchResults = data.success && data.data ? data.data : [];
            showSearchDropdown();
        })
        .catch(() => { obSearchResults = []; showSearchDropdown(); });
}

function showSearchDropdown() {
    const dd = document.getElementById('obSearchDropdown');
    if (!obSearchResults || !obSearchResults.length) {
        dd.innerHTML = '<div class="search-dropdown-empty">未找到匹配商品</div>';
        dd.classList.add('show');
        positionDropdown();
        return;
    }

    const productGroups = {};
    obSearchResults.forEach(b => {
        if (!productGroups[b.product_id]) {
            productGroups[b.product_id] = { product_id: b.product_id, product_name: b.product_name, common_name: b.common_name, series: b.series, barcode: b.barcode, conditions: {} };
        }
        const pg = productGroups[b.product_id];
        const cond = pg.conditions;
        if (!cond[b.condition_type]) {
            cond[b.condition_type] = {
                product_id: pg.product_id, product_name: pg.product_name, common_name: pg.common_name,
                series: pg.series, barcode: pg.barcode,
                condition_type: b.condition_type, condition_name: b.condition_name,
                total_stock: 0, suggested_price: b.suggested_price, purchase_price: b.purchase_price, batches: []
            };
        }
        cond[b.condition_type].batches.push(b);
        cond[b.condition_type].total_stock += parseInt(b.remaining_qty);
        if (parseFloat(b.suggested_price) > parseFloat(cond[b.condition_type].suggested_price)) {
            cond[b.condition_type].suggested_price = b.suggested_price;
        }
        if (parseFloat(b.purchase_price) > parseFloat(cond[b.condition_type].purchase_price)) {
            cond[b.condition_type].purchase_price = b.purchase_price;
        }
    });

    dd.innerHTML = '';
    addMap = {};
    let addId = 0;

    Object.values(productGroups).forEach(product => {
        const displayName = product.common_name || product.product_name;
        const mergedSKUs = Object.values(product.conditions);
        const section = document.createElement('div');
        section.innerHTML = `
            <div class="search-dropdown-header">
                <div class="sdi-product-name">${esc(displayName)}</div>
                <div class="sdi-product-meta">${esc(product.barcode)}${product.series ? ' · ' + esc(product.series) : ''}</div>
            </div>
            ${mergedSKUs.map(sku => {
                const id = 'add_' + (addId++);
                addMap[id] = sku;
                const reserved = getReservedBySku(sku.product_id, sku.condition_type);
                const remain = Math.max(0, sku.total_stock - reserved);
                const dimmed = remain <= 0;
                return `
                <div class="search-dropdown-item" style="${dimmed ? 'opacity:0.5;' : ''}">
                    <span class="condition-badge">${esc(sku.condition_name)}</span>
                    <span class="sdi-stock">库存 ${remain}${reserved > 0 ? `<span style="color:var(--text-tertiary);font-weight:normal;">(-${reserved})</span>` : ''}</span>
                    <span class="sdi-price">¥${parseFloat(sku.suggested_price || 0).toFixed(2)}</span>
                    <button class="sdi-add-btn" data-add-id="${id}"${dimmed ? ' disabled' : ''}>${dimmed ? '已占完' : '添加'}</button>
                </div>
            `;}).join('')}
        `;
        dd.appendChild(section);
    });

    dd.classList.add('show');
    positionDropdown();

    dd.querySelectorAll('.sdi-add-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const sku = addMap[this.dataset.addId];
            if (!sku) return;
            pickSku(sku);
        });
    });
}

// 统计当前场次所有客户中，该商品+SKU 已添加的数量（排除赠品）
function getReservedBySku(productId, conditionType) {
    let reserved = 0;
    (sessionData.customers || []).forEach(c => {
        (c.items || []).forEach(i => {
            if (!i.is_gift && i.product_id === productId && (i.condition_type || '') === (conditionType || '')) {
                reserved += parseInt(i.qty) || 0;
            }
        });
    });
    return reserved;
}

function pickSku(sku) {
    const c = (sessionData.customers || []).find(x => x.id === editingCustomerId);
    if (!c) return;
    // 再次校验库存（防止超占）
    const reserved = getReservedBySku(sku.product_id, sku.condition_type);
    if (reserved >= sku.total_stock) {
        toast(`「${sku.product_name}」库存不足，无法添加`);
        return;
    }
    c.items.push({
        id: nextLocalId--,
        product_id: sku.product_id,
        condition_type: sku.condition_type || '',
        product_name: sku.product_name,
        qty: 1,
        sell_price: parseFloat(sku.suggested_price || 0),
        purchase_cost: CAN_SEE_PROFIT ? parseFloat(sku.purchase_price || 0) : 0,
        is_gift: 0
    });
    closeProductModal();
    render();
    toast(`已添加 ${sku.product_name}（${sku.condition_name}）`);
}

// ===== 赠品 =====
function addGift(cid) {
    giftingCustomerId = cid;
    document.getElementById('giftCostInput').value = '';
    document.getElementById('giftDescInput').value = '';
    document.getElementById('giftModal').classList.add('show');
}
function closeGiftModal() { document.getElementById('giftModal').classList.remove('show'); }
function confirmGift() {
    const cost = parseFloat(document.getElementById('giftCostInput').value);
    const desc = document.getElementById('giftDescInput').value;
    if (isNaN(cost) || cost <= 0) { toast('请输入有效赠品成本'); return; }
    const c = (sessionData.customers || []).find(x => x.id === giftingCustomerId);
    c.gifts.push({ id: nextLocalId--, cost, description: desc });
    closeGiftModal();
    render();
    toast('赠品已添加（记得保存）');
}

// ===== 保存 =====
async function saveAll() {
    if (!currentSessionId) { toast('请先选择场次'); return; }
    const payload = {
        session_id: currentSessionId,
        customers: (sessionData.customers || []).map(c => ({
            id: c.id > 0 ? c.id : 0,
            nickname: c.nickname,
            vip_no: c.vip_no,
            items: (c.items || []).map(i => ({
                id: i.id > 0 ? i.id : 0,
                product_id: i.product_id,
                condition_type: i.condition_type || '',
                product_name: i.product_name,
                qty: i.qty,
                sell_price: i.sell_price,
                purchase_cost: i.purchase_cost,
                is_gift: i.is_gift,
            })),
            gifts: (c.gifts || []).map(g => ({
                id: g.id > 0 ? g.id : 0,
                cost: g.cost,
                description: g.description,
            })),
        })),
    };
    try {
        const res = await fetch('../api/live_ledger_save.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            await switchToSession(currentSessionId);
            toast('已保存');
        } else toast(data.error || '保存失败');
    } catch (e) { toast('保存失败: ' + e.message); }
}

// ===== 结束直播 =====
function endLive() {
    showConfirm('结束直播将执行出库（扣减库存）并保留历史记录，确定？', async () => {
        try {
            const res = await fetch('../api/live_ledger_end.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: currentSessionId })
            });
            const data = await res.json();
            if (data.success) {
                toast('直播已结束，出库完成');
                switchSession();
                await loadSessions();
            } else toast(data.error || '结束失败');
        } catch (e) { toast('结束失败: ' + e.message); }
    });
}

// ===== 确认框 =====
function showConfirm(text, okFn) {
    document.getElementById('confirmText').textContent = text;
    document.getElementById('confirmModal').classList.add('show');
    document.getElementById('confirmOkBtn').onclick = () => { closeConfirmModal(); okFn(); };
}
function closeConfirmModal() { document.getElementById('confirmModal').classList.remove('show'); }

function toast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 2000);
}

document.getElementById('newCustomerNickname').addEventListener('keydown', e => { if (e.key === 'Enter') confirmAddCustomer(); });
document.getElementById('productSearchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') { const first = document.querySelector('#obSearchDropdown .sdi-add-btn'); if (first) first.click(); }
});

loadSessions();
</script>
