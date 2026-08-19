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
  <div class="stat-card"><div class="label" id="statProfitLabel">毛利-无活动</div><div class="value" id="statTotalProfit">¥0</div></div>
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
        <th>主播</th>
        <th>运营</th>
        <th>账号</th>
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
  <div style="display:flex; justify-content:space-between; align-items:center; gap:15px;">
    <h3 style="font-size:16px; font-weight:600; margin:0;">当前场次：<span id="sessionInfoName">-</span></h3>
    <div style="display:flex; gap:15px;">
      <button class="btn btn-outline" onclick="openSettingsModal()">场次设置</button>
      <button class="btn btn-outline" onclick="exitSession()">返回场次列表</button>
    </div>
  </div>
</div>

<!-- 场次设置弹窗 -->
<div class="modal" id="settingsCard">
  <div class="modal-content" style="width:1100px; max-width:96vw; max-height:90vh; overflow-y:auto;">
    <div class="modal-header">
      <h3 class="modal-title">场次设置</h3>
      <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
    </div>
    <div style="padding-top:14px;">
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
  <!-- 赠品预设配置区 -->
  <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--border, #e5e7eb);">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
      <label style="font-size:13px; font-weight:600; color:var(--text-primary);">赠品预设</label>
      <button class="btn btn-sm btn-outline" type="button" onclick="addGiftPresetRow()">+ 添加赠品</button>
    </div>
    <div style="font-size:12px; color:var(--text-tertiary, #9ca3af); margin-bottom:8px;">配置后，给客户添加赠品时可直接选择，不用每次手填。名称、价格（成本元）、数量。</div>
    <div id="giftPresetRows" style="display:flex; flex-direction:column; gap:6px;"></div>
  </div>
    <div style="display:flex; justify-content:flex-end; gap:15px; margin-top:20px;">
      <button class="btn btn-outline" onclick="closeSettingsModal()">取消</button>
      <button class="btn btn-primary" onclick="saveSettings()">保存设置</button>
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
      <label>场次名称（已按当前时间自动生成，可修改）</label>
      <input type="text" id="newSessionName" class="form-input" style="margin-top:6px;">
    </div>
    <div style="margin-bottom:14px;">
      <label>主播 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="newSessionAnchor" class="form-input" list="anchorList" placeholder="如：张三" style="margin-top:6px;">
      <datalist id="anchorList"></datalist>
    </div>
    <div style="margin-bottom:14px;">
      <label>运营 <span style="color:var(--danger);">*</span></label>
      <input type="text" id="newSessionOperator" class="form-input" list="operatorList" placeholder="如：李四" style="margin-top:6px;">
      <datalist id="operatorList"></datalist>
    </div>
    <div style="margin-bottom:14px;">
      <label>直播平台账号</label>
      <input type="text" id="newSessionAccount" class="form-input" list="accountList" placeholder="如：@xxx 或 抖音号" style="margin-top:6px;">
      <datalist id="accountList"></datalist>
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
  <div class="modal-content" style="width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">添加赠品</h3>
      <button class="modal-close" onclick="closeGiftModal()">&times;</button>
    </div>
    <div id="giftPresetList" style="margin-bottom:14px; display:flex; flex-direction:column; gap:8px;"></div>
    <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; color:var(--text-tertiary, #9ca3af); font-size:12px;">
      <span style="flex:1; height:1px; background:var(--border, #e5e7eb);"></span>或手动添加<span style="flex:1; height:1px; background:var(--border, #e5e7eb);"></span>
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
.customer-header .badge { background: var(--primary, #6366f1); color: #fff; border-radius: 20px; padding: 2px 10px; font-size: 12px; margin-right: 15px; }
.customer-header .summary { margin-left: 16px; font-size: 13px; color: var(--text-secondary, #6b7280); display: flex; gap: 16px; }
.customer-header .actions { margin-left: auto; display: flex; gap: 15px; }
/* 满赠待提醒：暗红色提示（满足赠品条件但未添加赠品） */
.customer.needs-gift { background: rgba(190, 18, 60, 0.08); border-left: 3px solid var(--danger, #dc2626); }
.customer.needs-gift .customer-header { background: rgba(190, 18, 60, 0.06); }
.customer.needs-gift .customer-header:hover { background: rgba(190, 18, 60, 0.1); }
.gift-remind { margin-left: 10px; font-size: 12px; font-weight: 600; color: var(--danger, #dc2626); }
/* 售价/数量步进器：左右按钮 + 中间输入框，高度一致 */
.stepper { display: inline-flex; align-items: stretch; height: 30px; }
.stepper .stepper-btn {
    width: 26px; border: 1px solid var(--border, #d1d5db); background: #f9fafb;
    color: var(--text-secondary, #374151); font-size: 14px; line-height: 1;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    padding: 0; user-select: none;
}
.stepper .stepper-btn:hover { background: #eef2ff; color: var(--primary, #6366f1); }
.stepper .stepper-btn:active { background: #e0e7ff; }
.stepper .stepper-btn:first-child { border-radius: 6px 0 0 6px; }
.stepper .stepper-btn:last-child { border-radius: 0 6px 6px 0; }
.stepper .stepper-input {
    height: 30px; box-sizing: border-box; border-radius: 0; text-align: center;
    border-left: none; border-right: none; padding: 4px 2px;
    -moz-appearance: textfield; appearance: textfield;
}
.customer-body { padding: 16px; display: none; }
.customer:not(.collapsed) .customer-body { display: block; }
.metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-top: 14px; }
.metric { background: var(--bg-hover, #f3f4f6); border-radius: 8px; padding: 10px 12px; }
.metric .label { font-size: 11px; color: var(--text-tertiary, #9ca3af); margin-bottom: 4px; }
.metric .value { font-size: 15px; font-weight: 700; }
.metric .value.green { color: var(--success, #10b981); }
.metric .value.red { color: var(--danger, #ef4444); }
.metric .sub { font-size: 11px; color: var(--text-secondary, #6b7280); margin-top: 2px; }
.gift-row { background: rgba(245, 158, 11, 0.07); border-left: 3px solid rgba(245, 158, 11, 0.5); }
.gift-row td:first-child { padding-left: 10px; }
.gift-badge { background: var(--warning, #f59e0b); color: #fff; font-size: 11px; padding: 1px 6px; border-radius: 4px; margin-left: 6px; }
.del-btn { background: none; border: none; font-size: 16px; cursor: pointer; color: var(--text-tertiary, #9ca3af); padding: 4px 6px; border-radius: 4px; }
.del-btn:hover { color: var(--danger, #ef4444); background: #fee2e2; }
.toast { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #111827; color: #fff; padding: 10px 20px; border-radius: 8px; font-size: 14px; z-index: 3000; display: none; }
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

/* ===== 右侧快捷查询面板（适配平台暗黑主题） ===== */
.ps-tab { position: fixed; right: 0; top: 45%; transform: translateY(-50%); z-index: 300;
    background: var(--primary); color: #fff; border-radius: 8px 0 0 8px;
    padding: 12px 6px; cursor: pointer; box-shadow: -2px 2px 10px rgba(0,0,0,.4);
    writing-mode: vertical-rl; font-size: 13px; font-weight: 700; letter-spacing: 2px; user-select: none;
    transition: background .2s, opacity .2s; }
.ps-tab:hover { background: var(--primary-hover); }
/* 右侧快捷面板 tab 容器：纵向排布，间距 15px */
#sideTabs { position: fixed; right: 0; top: 45%; transform: translateY(-50%); z-index: 300;
    display: flex; flex-direction: column; gap: 15px; }
#sideTabs .ps-tab { position: static; transform: none; }
/* 福袋记录 tab：独立配色（橙色），与价格库存查询区分 */
#ldTab { background: var(--warning, #f59e0b); }
#ldTab:hover { background: #d97706; }
.ps-panel { position: fixed; right: 0; top: 0; bottom: 0; width: 320px; z-index: 301;
    background: var(--bg-surface); border-left: 1px solid var(--border);
    box-shadow: -4px 0 24px rgba(0,0,0,.5); display: flex; flex-direction: column;
    transform: translateX(100%); transition: transform .25s ease; }
.ps-panel.open { transform: translateX(0); }
/* 福袋面板独立加宽，避免行内元素被裁切 */
#ldPanel { width: 440px; }
#ldPanel .ld-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.ld-ship{flex-shrink:0;font-size:12px;font-weight:600;padding:4px 10px;border-radius:6px;white-space:nowrap;}
.ld-ship-btn{flex-shrink:0;}
.ld-shipped{color:#059669;background:rgba(5,150,105,.12);}
.ld-shipped-row{opacity:.75;}
.ld-shipped-row input{color:var(--text-tertiary);}
.ps-head { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.ps-head .title { font-weight: 700; font-size: 15px; color: var(--text); }
.ps-close { border: none; background: none; font-size: 18px; cursor: pointer; color: var(--text-tertiary); line-height: 1; }
.ps-close:hover { color: var(--text); }
.ps-search { padding: 12px 16px; border-bottom: 1px solid var(--border); }
.ps-search input { width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px;
    font-size: 14px; outline: none; background: var(--bg-elevated); color: var(--text); }
.ps-search input::placeholder { color: var(--text-tertiary); }
.ps-search input:focus { border-color: var(--border-focus); }
.ps-body { flex: 1; overflow-y: auto; padding: 10px 12px; }
.ps-empty { color: var(--text-tertiary); font-size: 13px; text-align: center; padding: 30px 10px; }
.ps-product { border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; overflow: hidden; background: var(--bg-elevated); }
.ps-product .ps-pname { padding: 9px 12px; font-weight: 600; font-size: 13.5px; background: var(--bg-hover); color: var(--text); }
.ps-product .ps-pmeta { padding: 0 12px 4px; font-size: 11px; color: var(--text-tertiary); }
.ps-sku { display: flex; align-items: center; justify-content: space-between; padding: 7px 12px; border-top: 1px dashed var(--border); font-size: 13px; }
.ps-sku .sname { color: var(--text-secondary); }
.ps-sku .sval { font-weight: 600; }
.ps-sku .sval .stock { color: #34d399; }
.ps-sku .sval .price { color: var(--text); margin-left: 10px; }
</style>

<script>
let currentSessionId = null;
let sessionData = null;
let otherReserved = {};   // 跨场次占用："product_id|condition_type" => qty（其他 active 场次已记账数量，防超卖）
let isReadOnly = false;   // 已结束场次：只读，隐藏全部编辑操作
let vipSpentMap = {};   // vip_no => 累计消费（用于VIP分档配色）
// VIP消费分档：0-300蓝 #3B82F6 / 301-1000紫 #8B5CF6 / 1001-3000玫红 #F43F5E / 3000+橙金 #F59E0B
function vipTierStyle(vipNo) {
    const t = Number(vipSpentMap[vipNo] || 0);
    if (t > 3000) return 'background:rgba(245,158,11,0.2); color:#F59E0B; border:1px solid rgba(245,158,11,0.55);';
    if (t > 1000) return 'background:rgba(244,63,94,0.18); color:#F43F5E; border:1px solid rgba(244,63,94,0.5);';
    if (t > 300) return 'background:rgba(139,92,246,0.18); color:#8B5CF6; border:1px solid rgba(139,92,246,0.5);';
    return 'background:rgba(59,130,246,0.16); color:#3B82F6; border:1px solid rgba(59,130,246,0.45);';
}
// 加载全部VIP累计消费映射（出库记账页分档配色）
async function loadVipSpentMap() {
    try {
        const res = await fetch('../api/get_vip_spent.php');
        const data = await res.json();
        if (data.success && data.data.spent_map) vipSpentMap = data.data.spent_map;
    } catch (e) { /* 静默失败，保持默认灰色 */ }
}
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
        window._ledgerSessions = sessions;
        if (!sessions.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无场次，点击「新建场次」开始</td></tr>';
            return;
        }
        const statusNames = { active: '进行中', ended: '已结束' };
        const statusClasses = { active: 'badge-success', ended: 'badge-info' };
        tbody.innerHTML = sessions.map(s => `
            <tr class="${s.status === 'active' ? 'tr-active' : ''}">
                <td><strong>${esc(s.session_name)}</strong>${(parseInt(s.unshipped_count)||0) > 0 ? ` <span class="badge" style="background:#dc2626;color:#fff;margin-left:6px;" title="有 ${s.unshipped_count} 个福袋未寄出">福袋未寄出 ${s.unshipped_count}</span>` : ''}</td>
                <td>${esc(s.anchor || '-')}</td>
                <td>${esc(s.operator || '-')}</td>
                <td>${esc(s.account || '-')}</td>
                <td><span class="badge ${statusClasses[s.status] || 'badge-info'}">${statusNames[s.status] || s.status}</span></td>
                <td class="muted">${esc(s.created_at || '-')}</td>
                <td style="display:flex; gap:10px;">
                    <button class="btn btn-sm btn-primary" onclick="enterSession(${s.id})">进入</button>
                    ${IS_OPERATOR ? '' : `<button class="btn btn-sm btn-outline" onclick="confirmDeleteSession(${s.id}, '${esc(s.session_name)}')" style="color:var(--danger); border-color:var(--danger);">删除</button>`}
                </td>
            </tr>`).join('');
    } catch (e) { toast('加载场次失败: ' + e.message, true); }
}

function enterSession(id) {
    switchToSession(id);
}

function openNewSessionModal() {
    // 自动生成：年月日时分秒（如 20260807 14:30:25）
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const name = `${now.getFullYear()}${pad(now.getMonth()+1)}${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    document.getElementById('newSessionName').value = name;

    // 历史高频下拉（从已加载 sessions 统计频次，自动维护无需手工名单）
    fillStaffDatalists();

    // localStorage 记忆上次（若有则优先填入，否则留空待选）
    let anchor = localStorage.getItem('ledger_last_anchor') || '';
    let operator = localStorage.getItem('ledger_last_operator') || '';
    let account = localStorage.getItem('ledger_last_account') || '';
    document.getElementById('newSessionAnchor').value = anchor;
    document.getElementById('newSessionOperator').value = operator;
    document.getElementById('newSessionAccount').value = account;

    document.getElementById('newSessionModal').classList.add('show');
    setTimeout(() => document.getElementById('newSessionAnchor').focus(), 100);
}

// 从场次列表统计高频主播/运营/账号，填充 datalist（频次降序，去空）
function fillStaffDatalists() {
    const sessions = window._ledgerSessions || [];
    const countBy = (key) => {
        const m = {};
        sessions.forEach(s => { const v = (s[key] || '').trim(); if (v) m[v] = (m[v] || 0) + 1; });
        return Object.entries(m).sort((a, b) => b[1] - a[1]).map(x => x[0]);
    };
    const setList = (id, arr) => {
        const dl = document.getElementById(id);
        if (dl) dl.innerHTML = arr.map(v => `<option value="${esc(v)}"></option>`).join('');
    };
    setList('anchorList', countBy('anchor'));
    setList('operatorList', countBy('operator'));
    setList('accountList', countBy('account'));
}
function closeNewSessionModal() { document.getElementById('newSessionModal').classList.remove('show'); }

async function createSession() {
    const name = document.getElementById('newSessionName').value.trim();
    const anchor = document.getElementById('newSessionAnchor').value.trim();
    const operator = document.getElementById('newSessionOperator').value.trim();
    const account = document.getElementById('newSessionAccount').value.trim();
    if (!name) { toast('请输入场次名称'); return; }
    if (!anchor) { toast('请输入主播'); return; }
    if (!operator) { toast('请输入运营'); return; }
    try {
        const res = await fetch('../api/live_ledger_save_session.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ session_name: name, anchor: anchor, operator: operator, account: account, activity_type: 'none' })
        });
        const data = await res.json();
        if (data.success) {
            // 记住本次填写值，下次新建自动带出
            localStorage.setItem('ledger_last_anchor', anchor);
            localStorage.setItem('ledger_last_operator', operator);
            localStorage.setItem('ledger_last_account', account);
            closeNewSessionModal();
            await loadSessions();
            switchToSession(data.data.session_id);
            toast('场次已创建');
        } else toast(data.error || '创建失败', true);
    } catch (e) { toast('创建失败: ' + e.message, true); }
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
                    // 回列表：隐藏右侧快捷面板 tab
                    document.getElementById('psTab').style.display = 'none';
                    document.getElementById('ldTab').style.display = 'none';
                    closePriceStockPanel();
                    closeLuckyPanel();
                }
                await loadSessions();
                toast('场次已删除');
            } else toast(data.error || '删除失败', true);
        } catch (e) { toast('删除失败: ' + e.message, true); }
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
        if (!data.success) { toast(data.error || '加载失败', true); return; }
        sessionData = data.data;
        isReadOnly = (sessionData.settings && sessionData.settings.status === 'ended');
        // 进入场次：所有客户默认收起
        (sessionData.customers || []).forEach(c => { c._collapsed = true; });
        fillSettings();
        // 进入场次：隐藏列表，显示信息+设置+操作+客户列表
        document.getElementById('sessionListCard').style.display = 'none';
        document.getElementById('sessionInfoCard').style.display = 'block';
        // 进入场次：显示右侧快捷面板 tab（价格库存查询/福袋记录）
        document.getElementById('psTab').style.display = '';
        document.getElementById('ldTab').style.display = '';
        // 关闭已打开的面板
        closePriceStockPanel();
        closeLuckyPanel();
        document.getElementById('sessionInfoName').textContent = sessionData.settings.session_name + ' · 主播' + (sessionData.settings.anchor || '-') + '，运营' + (sessionData.settings.operator || '-') + (sessionData.settings.account ? '，账号' + sessionData.settings.account : '');
        document.getElementById('settingsCard').classList.remove('show');
        // 已结束场次：隐藏 新增客户/保存/结束直播 操作栏
        document.getElementById('actionBar').style.display = isReadOnly ? 'none' : 'flex';
        document.getElementById('statsBar').style.display = 'grid';
        document.getElementById('customerListCard').style.display = 'block';
        render();
    } catch (e) { toast('加载失败: ' + e.message, true); }
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
    renderGiftPresetRows(s.gift_presets || []);
    activityChange();
}

// ===== 赠品预设 =====
let giftPresetDraft = []; // 场次设置弹窗内的草稿行
function renderGiftPresetRows(presets) {
    giftPresetDraft = (presets || []).map(p => ({ name: p.name || '', price: p.price ?? '' }));
    const box = document.getElementById('giftPresetRows');
    if (!box) return;
    box.innerHTML = '';
    giftPresetDraft.forEach((p, i) => {
        box.appendChild(giftPresetRowEl(p, i));
    });
    if (giftPresetDraft.length === 0) addGiftPresetRow();
}
function giftPresetRowEl(p, i) {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex; align-items:center; gap:8px;';
    row.innerHTML = `
        <input type="text" class="form-input" placeholder="赠品名称（如 小挂件）" value="${esc(p.name)}" style="flex:2; min-width:120px;" data-f="name">
        <input type="number" class="form-input" step="0.01" min="0" placeholder="价格(成本元)" value="${p.price}" style="flex:1; min-width:70px;" data-f="price">
        <button class="btn btn-sm btn-danger" type="button" onclick="removeGiftPresetRow(this)">删除</button>`;
    return row;
}
function addGiftPresetRow() {
    const box = document.getElementById('giftPresetRows');
    if (!box) return;
    giftPresetDraft.push({ name: '', price: '' });
    box.appendChild(giftPresetRowEl(giftPresetDraft[giftPresetDraft.length - 1], giftPresetDraft.length - 1));
}
function removeGiftPresetRow(btn) {
    const box = document.getElementById('giftPresetRows');
    const idx = Array.prototype.indexOf.call(box.children, btn.closest('div'));
    if (idx >= 0) {
        giftPresetDraft.splice(idx, 1);
        btn.closest('div').remove();
    }
    if (giftPresetDraft.length === 0) addGiftPresetRow();
}
function collectGiftPresets() {
    const box = document.getElementById('giftPresetRows');
    if (!box) return [];
    const presets = [];
    Array.prototype.forEach.call(box.children, (row) => {
        const name = (row.querySelector('[data-f="name"]')?.value || '').trim();
        const price = parseFloat(row.querySelector('[data-f="price"]')?.value);
        if (name && !isNaN(price) && price >= 0) presets.push({ name, price });
    });
    return presets;
}

// ===== 福袋记录（右侧面板，一行一条，可多条） =====
let luckyDrawDraft = []; // 面板内的草稿行
function luckyDrawRowEl(d) {
    const row = document.createElement('div');
    // d 可能是草稿（无 id）或已保存记录（有 id + shipped）
    const saved = !!d.id;
    const shipped = saved && d.shipped ? 1 : 0;
    row.className = 'ld-row' + (saved && shipped ? ' ld-shipped-row' : '');
    if (saved) row.dataset.ldId = d.id;
    row.innerHTML = `
        <input type="text" class="form-input" placeholder="中奖人" value="${esc(d.winner || '')}" style="flex:1; min-width:90px;" data-f="winner" ${shipped ? 'disabled' : ''}>
        <input type="text" class="form-input" placeholder="奖品" value="${esc(d.prize || '')}" style="flex:2; min-width:120px;" data-f="prize" ${shipped ? 'disabled' : ''}>
        <input type="number" class="form-input" step="0.01" min="0" placeholder="成本(元)" value="${d.cost ?? ''}" style="flex:1; min-width:70px;" data-f="cost" ${shipped ? 'disabled' : ''}>
        ${saved
            ? (shipped
                ? `<span class="ld-ship ld-shipped" title="${esc(d.shipped_at || '')}">已寄出</span>`
                : `<button class="btn btn-sm btn-outline ld-ship-btn" type="button" onclick="shipLuckyDraw(${d.id}, this)">寄出</button>`)
            : `<button class="btn btn-sm btn-danger" type="button" onclick="removeLuckyDrawRow(this)">删除</button>`}
        ${saved && !shipped ? `<button class="btn btn-sm btn-danger" type="button" onclick="removeLuckyDrawRow(this)">删除</button>` : ''}`;
    return row;
}
function addLuckyDrawRow() {
    const box = document.getElementById('ldRows');
    if (!box) return;
    luckyDrawDraft.push({ winner: '', prize: '', cost: '' });
    box.appendChild(luckyDrawRowEl(luckyDrawDraft[luckyDrawDraft.length - 1]));
}
function removeLuckyDrawRow(btn) {
    const box = document.getElementById('ldRows');
    const idx = Array.prototype.indexOf.call(box.children, btn.closest('div'));
    if (idx >= 0) {
        luckyDrawDraft.splice(idx, 1);
        btn.closest('div').remove();
    }
}
function renderLuckyDrawRows(draws) {
    const box = document.getElementById('ldRows');
    if (!box) return;
    box.innerHTML = '';
    luckyDrawDraft = (draws || []).map(d => ({ id: d.id, winner: d.winner || '', prize: d.prize || '', cost: d.cost ?? '', shipped: d.shipped ? 1 : 0, shipped_at: d.shipped_at || '' }));
    if (luckyDrawDraft.length === 0) addLuckyDrawRow();
    else luckyDrawDraft.forEach((d, i) => box.appendChild(luckyDrawRowEl(d)));
}
function collectLuckyDraws() {
    const box = document.getElementById('ldRows');
    if (!box) return [];
    const draws = [];
    Array.prototype.forEach.call(box.children, (row) => {
        const winner = (row.querySelector('[data-f="winner"]')?.value || '').trim();
        const prize = (row.querySelector('[data-f="prize"]')?.value || '').trim();
        const cost = parseFloat(row.querySelector('[data-f="cost"]')?.value);
        if (winner && prize && !isNaN(cost) && cost >= 0) {
            const saved = !!(row.dataset && row.dataset.ldId);
            draws.push({
                id: saved ? parseInt(row.dataset.ldId) : undefined,
                winner, prize, cost,
                shipped: saved && row.classList.contains('ld-shipped-row') ? 1 : 0
            });
        }
    });
    return draws;
}
function openLuckyPanel() {
    const panel = document.getElementById('ldPanel');
    if (!panel) return;
    const body = document.getElementById('ldBody');
    body.innerHTML = `
        <div style="font-size:12px; color:var(--text-tertiary); margin-bottom:10px;">一场可记录多个福袋，一行一条。成本计入本场次总成本。</div>
        <div id="ldRows" style="display:flex; flex-direction:column;"></div>
        <button class="btn btn-sm btn-outline" type="button" onclick="addLuckyDrawRow()" style="margin-top:2px;">+ 添加福袋</button>
        <div style="display:flex; justify-content:flex-end; gap:15px; margin-top:16px;">
            <button class="btn btn-primary btn-sm" onclick="saveLuckyDraws()">保存福袋</button>
        </div>`;
    // 加载当前场次福袋
    renderLuckyDrawRows(sessionData.lucky_draws || []);
    panel.classList.add('open');
}
function closeLuckyPanel() { document.getElementById('ldPanel').classList.remove('open'); }
async function saveLuckyDraws() {
    if (!currentSessionId) { toast('请先进入场次'); return; }
    const draws = collectLuckyDraws();
    try {
        const res = await fetch('../api/live_ledger_lucky_draw_save.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ session_id: currentSessionId, draws })
        });
        const data = await res.json();
        if (!data.success) { toast(data.error || '保存失败', true); return; }
        toast('福袋已保存');
        await switchToSession(currentSessionId);
        renderLuckyDrawRows(sessionData.lucky_draws || []);
    } catch (e) { toast('保存失败: ' + e.message, true); }
}

async function shipLuckyDraw(id, btn) {
    if (!id) return;
    if (!confirm('确认该福袋已寄出？')) return;
    try {
        const res = await fetch('../api/live_ledger_lucky_draw_ship.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (!data.success) { toast(data.error || '标记失败', true); return; }
        toast('已标记寄出');
        // 刷新面板行状态
        await switchToSession(currentSessionId);
        renderLuckyDrawRows(sessionData.lucky_draws || []);
        loadSessions(); // 刷新场次列表红色标签
    } catch (e) { toast('标记失败: ' + e.message, true); }
}

function activityChange() {
    const v = document.getElementById('activityType').value;
    document.getElementById('giftField').style.display = (v === 'full_gift' || v === 'both') ? 'block' : 'none';
    document.getElementById('reduceField').style.display = (v === 'full_reduce' || v === 'both') ? 'block' : 'none';
}

function openSettingsModal() {
    document.getElementById('settingsCard').classList.add('show');
}
function closeSettingsModal() { document.getElementById('settingsCard').classList.remove('show'); }

async function saveSettings() {
    if (isReadOnly) { toast('已结束场次，不可修改设置'); closeSettingsModal(); return; }
    try {
        const res = await fetch('../api/live_ledger_save_session.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                session_id: currentSessionId,
                session_name: document.getElementById('sessionName').value,
                anchor: (sessionData.settings && sessionData.settings.anchor) || '',
                operator: (sessionData.settings && sessionData.settings.operator) || '',
                account: (sessionData.settings && sessionData.settings.account) || '',
                activity_type: document.getElementById('activityType').value,
                gift_every_n: parseInt(document.getElementById('giftEveryN').value) || 3,
                reduce_threshold: parseFloat(document.getElementById('reduceThreshold').value) || 30,
                reduce_amount: parseFloat(document.getElementById('reduceAmount').value) || 1,
                platform_fee_rate: parseFloat(document.getElementById('platformFeeRate').value) || 5,
                packing_cost: parseFloat(document.getElementById('packingCost').value) || 1,
                shipping_fee_8: parseFloat(document.getElementById('shippingFee8').value) || 3,
                shipping_fee_9: parseFloat(document.getElementById('shippingFee9').value) || 4,
                gift_presets: collectGiftPresets(),
            })
        });
        const data = await res.json();
        if (data.success) {
            closeSettingsModal();
            toast('设置已保存');
            await switchToSession(currentSessionId);
        }
        else toast(data.error || '保存失败', true);
    } catch (e) { toast('保存失败: ' + e.message, true); }
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
    let customers = sessionData.customers || [];
    // 按VIP编号自动排序：有编号的按数值升序（33 < 100），无编号的排最后（保持添加顺序）
    customers = [...customers].sort((a, b) => {
        const av = a.vip_no === undefined || a.vip_no === null || a.vip_no === '' ? Infinity : parseFloat(a.vip_no);
        const bv = b.vip_no === undefined || b.vip_no === null || b.vip_no === '' ? Infinity : parseFloat(b.vip_no);
        if (av === Infinity && bv === Infinity) return 0;
        if (av === Infinity) return 1;
        if (bv === Infinity) return -1;
        return av - bv;
    });
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
            if (isReadOnly) {
                return `<tr class="${isGift ? 'gift-row' : ''}">
                <td>${esc(item.product_name)}${isGift ? '<span class="gift-badge">赠品</span>' : ''}</td>
                <td>${isGift ? '' : esc(item.condition_name || item.condition_type || '')}</td>
                ${CAN_SEE_PROFIT ? `<td>${fmt(item.purchase_cost)}</td>` : ''}
                <td>${fmt(item.sell_price)}</td>
                <td>${item.qty}</td>
                <td>${fmt(item.sell_price * item.qty)}</td>
                ${isGift ? '' : `<td><button class="btn btn-sm btn-outline" style="padding:2px 10px; font-size:12px;" onclick="returnItem(${c.id}, ${item.id})">退货</button></td>`}
            </tr>`;
            }
            return `<tr class="${isGift ? 'gift-row' : ''}">
                <td>${esc(item.product_name)}${isGift ? '<span class="gift-badge">赠品</span>' : ''}</td>
                <td>${isGift ? '' : esc(item.condition_name || item.condition_type || '')}</td>
                ${CAN_SEE_PROFIT ? `<td>${fmt(item.purchase_cost)}</td>` : ''}
                <td><span class="stepper"><button type="button" class="stepper-btn" onclick="stepPrice(${c.id}, ${item.id}, -1)">−</button><input type="text" inputmode="decimal" value="${item.sell_price}" class="form-input stepper-input" style="width:56px;" onchange="updateItemPrice(${c.id}, ${item.id}, this.value)"><button type="button" class="stepper-btn" onclick="stepPrice(${c.id}, ${item.id}, 1)">+</button></span></td>
                <td><span class="stepper"><button type="button" class="stepper-btn" onclick="stepQty(${c.id}, ${item.id}, -1)">−</button><input type="text" inputmode="numeric" value="${item.qty}" class="form-input stepper-input" style="width:44px;" onchange="updateItemQty(${c.id}, ${item.id}, this.value)"><button type="button" class="stepper-btn" onclick="stepQty(${c.id}, ${item.id}, 1)">+</button></span></td>
                <td>${fmt(item.sell_price * item.qty)}</td>
                <td><button class="del-btn" onclick="deleteItem(${c.id}, ${item.id})">✕</button></td>
            </tr>`;
        }).join('');

        let giftHtml = (c.gifts || []).map((g, gi) => `
            <tr class="gift-row">
                <td>${g.name ? esc(g.name) + (g.qty > 1 ? ' ×' + g.qty : '') : '赠品' + (g.description ? ' - ' + esc(g.description) : '')}</td>
                ${CAN_SEE_PROFIT ? `<td>${fmt(g.cost)}</td>` : ''}
                <td colspan="${CAN_SEE_PROFIT ? 4 : 3}" style="color:var(--text-tertiary);">不入库，仅计成本</td>
                ${isReadOnly ? '' : `<td><button class="del-btn" onclick="deleteGift(${c.id}, ${gi})">✕</button></td>`}
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

        const giftEveryN = parseInt(settings.gift_every_n) || 3;
        // 满赠提示：满赠活动开启 且 购买数量达到赠品门槛 且 尚未添加赠品
        const needsGift = showGift && m.totalQty >= giftEveryN && !(c.gifts || []).length;

        list.innerHTML += `
            <div class="customer ${collapsed ? 'collapsed' : 'active'} ${needsGift ? 'needs-gift' : ''}" id="cust_${c.id}">
                <div class="customer-header" onclick="toggleCustomer(${c.id})">
                    <span class="toggle-arrow">▼</span>
                    ${c.vip_no ? `<span class="badge" style="${vipTierStyle(c.vip_no)}">${esc(c.vip_no)}</span>` : ''}
                    <span class="nickname">${esc(c.nickname) || '(未命名)'}</span>
                    ${needsGift ? `<span class="gift-remind">🎁 待赠</span>` : ''}
                    <span class="summary"><span>${m.totalQty}件</span><span>¥${fmt(m.gmv)}</span></span>
                    <span class="actions" onclick="event.stopPropagation()">
                        ${isReadOnly
                            ? `<button class="btn btn-sm btn-danger" style="padding:2px 10px; font-size:12px;" onclick="cancelOrder(${c.id}, '${esc(c.nickname) || '未命名'}')">撤单</button>`
                            : `<button class="btn btn-sm btn-outline" onclick="addGift(${c.id})">赠品</button>
                        <button class="btn btn-sm btn-danger" onclick="confirmDeleteCustomer(${c.id})">删除</button>`}
                    </span>
                </div>
                <div class="customer-body">
                    <div class="search-bar mb-10">
                        ${isReadOnly
                            ? `<label>昵称</label><span style="display:inline-block; min-width:140px;">${esc(c.nickname) || '(未命名)'}</span><label>VIP编号</label><span style="display:inline-block; min-width:120px;">${esc(c.vip_no) || '-'}</span>`
                            : `<label>昵称</label><input type="text" value="${esc(c.nickname)}" class="form-input" style="width:140px;" onchange="updateNickname(${c.id}, this.value)">
                        <label>VIP编号</label><input type="text" value="${esc(c.vip_no)}" class="form-input" style="width:120px;" placeholder="选填" onchange="updateVip(${c.id}, this.value)">`}
                    </div>
                    <table>
                        <thead><tr><th>商品</th><th>SKU</th>${CAN_SEE_PROFIT ? '<th>进价</th>' : ''}<th>售价</th><th>数量</th><th>小计</th><th></th></tr></thead>
                        <tbody>${itemsHtml}${giftHtml}</tbody>
                    </table>
                    ${isReadOnly ? '' : `<div style="margin-top:10px;">
                        <button class="btn btn-sm btn-primary" onclick="openProductModal(${c.id})">添加商品</button>
                    </div>`}
                    ${metrics}
                </div>
            </div>`;
    });

    let tq = 0, tg = 0, tc = 0, tp = 0;
    // 根据场次活动类型决定顶部毛利口径：none→无活动 / full_gift→满赠 / full_reduce→满减 / both→满减+满赠
    const at = (sessionData.settings && sessionData.settings.activity_type) || 'none';
    const profitKeyMap = { none: 'profitBase', full_gift: 'profitWithGift', full_reduce: 'profitWithReduce', both: 'profitBoth' };
    const profitLabelMap = { none: '毛利-无活动', full_gift: '毛利-满赠', full_reduce: '毛利-满减', both: '毛利-满减+满赠' };
    const profitKey = profitKeyMap[at] || 'profitBase';
    document.getElementById('statProfitLabel').textContent = profitLabelMap[at] || '毛利-无活动';
    customers.forEach(c => { const m = calcCustomer(c); tq += m.totalQty; tg += m.gmv; tc += m.cost; tp += m[profitKey]; });
    // 福袋成本计入本场次总成本与毛利
    const luckyCost = parseFloat(sessionData.lucky_draw_cost || 0) || 0;
    tc += luckyCost;
    tp -= luckyCost;
    document.getElementById('statCustomers').textContent = customers.length;
    document.getElementById('statTotalQty').textContent = tq;
    document.getElementById('statTotalGmv').textContent = '¥' + Math.round(tg);
    document.getElementById('statTotalCost').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(tc)) : '—';
    document.getElementById('statTotalProfit').textContent = CAN_SEE_PROFIT ? ('¥' + Math.round(tp)) : '—';
}

// ===== 客户操作 =====

// 撤单：整单取消（已结束场次）→ 商品回库存 + 费用重算
function cancelOrder(cid, nickname) {
    showConfirm(`确定撤单「${nickname}」的全部订单吗？\n商品将退回库存，运费/平台扣点/包装成本将重新计算。`, async () => {
        try {
            const res = await fetch('../api/live_ledger_cancel.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: currentSessionId, customer_id: cid })
            });
            const data = await res.json();
            if (data.success) {
                toast('撤单成功');
                await loadSessionData();
            } else toast(data.error || '撤单失败', true);
        } catch (e) { toast('撤单失败: ' + e.message, true); }
    });
}

// 退货：单个商品取消（已结束场次）→ 该商品回库存 + 费用重算
function returnItem(cid, iid) {
    showConfirm('确定退货该商品吗？\n商品将退回库存，运费/平台扣点/包装成本将重新计算。', async () => {
        try {
            const res = await fetch('../api/live_ledger_cancel.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ session_id: currentSessionId, customer_id: cid, item_id: iid })
            });
            const data = await res.json();
            if (data.success) {
                toast('退货成功');
                await loadSessionData();
            } else toast(data.error || '退货失败', true);
        } catch (e) { toast('退货失败: ' + e.message, true); }
    });
}

// 重新加载当前场次数据并渲染（撤单/退货后刷新）
async function loadSessionData() {
    if (!currentSessionId) return;
    try {
        const res = await fetch('../api/live_ledger_get_session.php?session_id=' + currentSessionId);
        const data = await res.json();
        if (!data.success) { toast(data.error || '加载失败', true); return; }
        sessionData = data.data;
        otherReserved = data.data.other_reserved || {};
        (sessionData.customers || []).forEach(c => { c._collapsed = true; });
        render();
        // 顶部汇总也刷新（场次列表数据来自快照，需重新拉取）
        await loadSessions();
        // VIP 消费映射刷新（撤单/退货影响累计消费，VIP 分档配色需同步）
        await loadVipSpentMap();
    } catch (e) { toast('加载失败: ' + e.message, true); }
}

// 轻量重载（autoSave 后同步真实id）：只重载场次数据+渲染，不刷新列表/VIP映射
// 保留各客户折叠状态（不强制收起，避免打断用户操作）
async function reloadSessionData() {
    if (!currentSessionId) return;
    try {
        const res = await fetch('../api/live_ledger_get_session.php?session_id=' + currentSessionId);
        const data = await res.json();
        if (!data.success) { toast(data.error || '加载失败', true); return; }
        const collapsedMap = {};
        (sessionData.customers || []).forEach(c => { collapsedMap[c.id] = !!c._collapsed; });
        sessionData = data.data;
        otherReserved = data.data.other_reserved || {};
        (sessionData.customers || []).forEach(c => {
            // 新数据里同 id 客户保留原折叠状态；新出现的客户默认收起
            c._collapsed = collapsedMap[c.id] !== undefined ? collapsedMap[c.id] : true;
        });
        render();
    } catch (e) { toast('加载失败: ' + e.message, true); }
}

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

async function confirmAddCustomer() {
    const vip = document.getElementById('newCustomerVip').value.trim();
    const nickInput = document.getElementById('newCustomerNickname');
    let nick = nickInput.value.trim();
    if (!vip) { toast('请输入VIP编号'); return; }
    // 昵称为空时先尝试自动匹配（防止 onblur 异步结果未返回就点添加）
    if (!nick) {
        try {
            const res = await fetch('../api/live_ledger_lookup_vip.php?vip_no=' + encodeURIComponent(vip));
            const data = await res.json();
            if (data.success && data.data.nickname) {
                nick = data.data.nickname;
                nickInput.value = nick;
            }
        } catch (e) {}
    }
    if (!nick) { toast('未匹配到昵称，请手动输入'); return; }
    // 同场次重复VIP检查：已有同VIP编号客户时阻止，并定位到已存在客户
    const dup = sessionData.customers.find(c => c.vip_no === vip);
    if (dup) {
        toast(`该场次已添加过 VIP ${vip}（${dup.nickname}），已定位到该客户`);
        closeAddCustomerModal();
        scrollToCustomer(dup.id);
        return;
    }
    const newId = nextLocalId--;
    sessionData.customers.push({ id: newId, nickname: nick, vip_no: vip, items: [], gifts: [], _collapsed: false });
    closeAddCustomerModal();
    render();
    scrollToCustomer(newId);
    toast(`客户「${nick}」已添加（记得保存）`);
    scheduleAutoSave();
}

// 新增客户后定位滚动到该客户卡片（按VIP排序后仍能定位）
function scrollToCustomer(cid) {
    setTimeout(() => {
        const el = document.getElementById('cust_' + cid);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // 短暂高亮提示位置
            el.style.transition = 'box-shadow 0.8s';
            el.style.boxShadow = '0 0 0 3px rgba(245,158,11,0.6)';
            setTimeout(() => { el.style.boxShadow = ''; }, 1500);
        }
    }, 50);
}

function updateNickname(id, v) { const c = (sessionData.customers || []).find(x => x.id === id); if (c) { c.nickname = v; scheduleAutoSave(); } }
function updateVip(id, v) { const c = (sessionData.customers || []).find(x => x.id === id); if (c) { c.vip_no = v; scheduleAutoSave(); } }

function updateItemPrice(cid, iid, v) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    const item = (c.items || []).find(i => i.id === iid);
    if (item) { item.sell_price = parseFloat(v) || 0; render(); scheduleAutoSave(); }
}
function updateItemQty(cid, iid, v) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    const item = (c.items || []).find(i => i.id === iid);
    if (item) { item.qty = parseInt(v) || 1; render(); scheduleAutoSave(); }
}

// 售价步进（±按钮）
function stepPrice(cid, iid, delta) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    const item = (c.items || []).find(i => i.id === iid);
    if (!item) return;
    const v = (parseFloat(item.sell_price) || 0) + delta;
    item.sell_price = Math.max(0, Math.round(v * 100) / 100);
    render(); scheduleAutoSave();
}
// 数量步进（±按钮）
function stepQty(cid, iid, delta) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    const item = (c.items || []).find(i => i.id === iid);
    if (!item) return;
    const v = (parseInt(item.qty) || 1) + delta;
    item.qty = Math.max(1, v);
    render(); scheduleAutoSave();
}

function deleteItem(cid, iid) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    c.items = (c.items || []).filter(i => i.id !== iid);
    render();
    scheduleAutoSave();
}
function deleteGift(cid, gi) {
    const c = (sessionData.customers || []).find(x => x.id === cid);
    c.gifts.splice(gi, 1);
    render();
    scheduleAutoSave();
}

function confirmDeleteCustomer(id) {
    showConfirm('确定删除该客户及其所有购买记录吗？', async () => {
        sessionData.customers = (sessionData.customers || []).filter(x => x.id !== id);
        render();
        await saveAll();
        toast('客户已删除');
        scheduleAutoSave();
    });
}

// ===== 商品搜索（复用出库页交互） =====
let editingCustomerRef = null; // 客户对象引用（autoSave 重载后依然有效）
let modalOpen = false; // 添加商品弹窗打开标志（期间不 autoSave 重载，避免 id 失效）
function openProductModal(cid) {
    editingCustomerRef = (sessionData.customers || []).find(x => x.id === cid) || null;
    editingCustomerId = cid;
    modalOpen = true;
    document.getElementById('productSearchInput').value = '';
    document.getElementById('obSearchDropdown').classList.remove('show');
    document.getElementById('addProductModal').classList.add('show');
    setTimeout(() => document.getElementById('productSearchInput').focus(), 100);
}
function closeProductModal() {
    modalOpen = false;
    document.getElementById('addProductModal').classList.remove('show');
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

// 轻量刷新跨场次占用（只更新 otherReserved，不动 sessionData）
// 多场次并发时，别的场次随时可能加购，搜索/添加前刷新保证显示和拦截基于最新占用
async function refreshOtherReserved() {
    if (!currentSessionId) return;
    try {
        const res = await fetch('../api/live_ledger_get_session.php?session_id=' + currentSessionId);
        const data = await res.json();
        if (data.success && data.data.other_reserved) {
            otherReserved = data.data.other_reserved;
        }
    } catch (e) {}
}

async function searchOutboundStock(keyword) {
    await refreshOtherReserved();
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
                const localRes = getLocalReserved(sku.product_id, sku.condition_type);
                const otherRes = getOtherReserved(sku.product_id, sku.condition_type);
                const totalRes = localRes + otherRes;
                // 可加数量 = 总库存 - 本场占用 - 其他场次占用（跨场次占用直接体现在库存上）
                const remain = Math.max(0, sku.total_stock - totalRes);
                const dimmed = remain <= 0;
                return `
                <div class="search-dropdown-item" style="${dimmed ? 'opacity:0.5;' : ''}">
                    <span class="condition-badge">${esc(sku.condition_name)}</span>
                    <span class="sdi-stock">库存 ${remain}${totalRes > 0 ? `<span style="color:var(--text-tertiary);font-weight:normal;">(-${totalRes})</span>` : ''}${otherRes > 0 ? `<span style="color:var(--warning,#f59e0b);font-weight:normal;">⚠其他场次占${otherRes}</span>` : ''}</span>
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

// 统计当前场次所有客户中，该商品+SKU 已添加的数量（排除赠品）——本场次占用（硬拦依据）
function getLocalReserved(productId, conditionType) {
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

// 其他 active 场次占用（跨场次防超卖提示；不硬拦——两边同时占最后1件时若硬拦则都加不了，谁先结束谁扣走，后到的结束时报错定位客户）
function getOtherReserved(productId, conditionType) {
    return parseInt(otherReserved[productId + '|' + (conditionType || '')] || 0, 10);
}

// 总占用（本场 + 其他场次，仅用于展示）
function getReservedBySku(productId, conditionType) {
    return getLocalReserved(productId, conditionType) + getOtherReserved(productId, conditionType);
}

function pickSku(sku) {
    // 优先按当前 id 找；找不到（autoSave 重载后 id 变化）时用弹窗打开时保存的对象引用
    let c = (sessionData.customers || []).find(x => x.id === editingCustomerId);
    if (!c && editingCustomerRef) {
        // 引用对象可能已被替换，按 VIP 编号或昵称在最新数据里重新定位
        c = (sessionData.customers || []).find(x =>
            editingCustomerRef.vip_no && x.vip_no === editingCustomerRef.vip_no
        ) || (sessionData.customers || []).find(x => x.nickname === editingCustomerRef.nickname);
        if (c) editingCustomerId = c.id;
    }
    if (!c) return;
    // 再次校验库存（防止超占）：按总占用（本场 + 其他 active 场次）硬拦
    // 跨场次占用直接体现在库存上：A场次加了最后1件 → B场次这里库存0、加不进去
    const totalRes = getReservedBySku(sku.product_id, sku.condition_type);
    if (totalRes >= sku.total_stock) {
        toast(`「${sku.product_name}」库存不足：已占用 ${totalRes}/${sku.total_stock} 件（含其他进行中场次），无法添加`, true);
        return;
    }
    c.items.push({
        id: nextLocalId--,
        product_id: sku.product_id,
        condition_type: sku.condition_type || '',
        condition_name: sku.condition_name || '',
        product_name: sku.product_name,
        qty: 1,
        sell_price: parseFloat(sku.suggested_price || 0),
        purchase_cost: parseFloat(sku.purchase_price || 0),
        is_gift: 0
    });
    closeProductModal();
    render();
    toast(`已添加 ${sku.product_name}（${sku.condition_name}）`);
    scheduleAutoSave();
}

// ===== 赠品 =====
function addGift(cid) {
    giftingCustomerId = cid;
    document.getElementById('giftCostInput').value = '';
    document.getElementById('giftDescInput').value = '';
    // 渲染预设列表
    const listBox = document.getElementById('giftPresetList');
    const presets = (sessionData.settings && sessionData.settings.gift_presets) || [];
    listBox.innerHTML = '';
    if (presets.length === 0) {
        listBox.style.display = 'none';
    } else {
        listBox.style.display = 'flex';
        presets.forEach((p, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline';
            btn.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:10px 12px; font-size:14px;';
            btn.innerHTML = `<span>${esc(p.name)}</span><span style="color:var(--text-secondary); font-size:12px;">¥${(parseFloat(p.price)||0).toFixed(2)}</span>`;
            btn.onclick = () => addGiftFromPreset(p);
            listBox.appendChild(btn);
        });
    }
    document.getElementById('giftModal').classList.add('show');
}
function addGiftFromPreset(p) {
    if (giftingCustomerId == null) return;
    const c = (sessionData.customers || []).find(x => x.id === giftingCustomerId);
    if (!c) return;
    // 点击一次固定送 1 个（可无限送，仅用于毛利计算）
    const qty = 1;
    const unitCost = parseFloat(p.price) || 0;
    c.gifts.push({ id: nextLocalId--, name: p.name, qty, cost: unitCost * qty, unit_cost: unitCost, description: p.name });
    closeGiftModal();
    render();
    toast('赠品已添加（记得保存）');
    scheduleAutoSave();
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
    scheduleAutoSave();
}

// ===== 保存 =====
function buildPayload() {
    return {
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
                name: g.name || '',
                qty: g.qty || 1,
                cost: g.cost,
                description: g.description,
            })),
        })),
    };
}

// 发送保存请求（不重载），返回是否成功
async function doSave() {
    if (!currentSessionId) return false;
    try {
        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), 15000); // 15s 超时，避免卡死 autoSaving
        let res;
        try {
            res = await fetch('../api/live_ledger_save.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(buildPayload()),
                signal: ctrl.signal
            });
        } finally {
            clearTimeout(timer);
        }
        const data = await res.json();
        if (!data.success) { console.error('保存失败:', data.error); return false; }
        return true;
    } catch (e) {
        console.error('保存异常:', e.name === 'AbortError' ? '超时' : e.message);
        return false;
    }
}

// 自动保存（防抖1.5s）：每步操作后静默保存，失败才提示
let autoSaveTimer = null;
let autoSaving = false;
function scheduleAutoSave() {
    if (isReadOnly) return; // 已结束场次不自动保存
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(async () => {
        if (autoSaving) { scheduleAutoSave(); return; } // 上轮未完成则顺延
        autoSaving = true;
        try {
            const ok = await doSave();
            if (ok) {
                // 提示已自动保存（与手动保存一致的反馈）
                toast('已自动保存');
                // 添加商品弹窗打开期间不重载（避免客户 id 变化导致正在进行的添加失败），
                // 下次 autoSave 或手动保存时再同步真实 id
                const hasTempId = !modalOpen && (sessionData.customers || []).some(c =>
                    c.id <= 0 || (c.items || []).some(i => i.id <= 0) || (c.gifts || []).some(g => g.id <= 0)
                );
                if (hasTempId) {
                    // 用轻量重载（保留各客户折叠状态），避免打断用户操作
                    await reloadSessionData();
                }
            } else {
                toast('自动保存失败，请手动保存', true);
            }
        } finally {
            autoSaving = false;
        }
    }, 1500);
}

async function saveAll() {
    if (!currentSessionId) { toast('请先选择场次'); return; }
    if (isReadOnly) { toast('已结束场次，不可修改'); return; }
    // 记录当前折叠状态（保存后重载会丢失，需恢复）
    const collapsedMap = {};
    (sessionData.customers || []).forEach(c => { collapsedMap[c.id] = !!c._collapsed; });
    try {
        const ok = await doSave();
        if (ok) {
            await switchToSession(currentSessionId);
            // 恢复折叠状态
            (sessionData.customers || []).forEach(c => {
                if (collapsedMap[c.id] !== undefined) c._collapsed = collapsedMap[c.id];
            });
            render();
            toast('保存成功');
        } else toast('保存失败', true);
    } catch (e) { toast('保存失败: ' + e.message, true); }
}

// ===== 结束直播 =====
function endLive() {
    if (isReadOnly) { toast('该场次已结束'); return; }
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
            } else toast(data.error || '结束失败', true);
        } catch (e) { toast('结束失败: ' + e.message, true); }
    });
}

// ===== 确认框 =====
function showConfirm(text, okFn) {
    document.getElementById('confirmText').textContent = text;
    document.getElementById('confirmModal').classList.add('show');
    document.getElementById('confirmOkBtn').onclick = () => { closeConfirmModal(); okFn(); };
}
function closeConfirmModal() { document.getElementById('confirmModal').classList.remove('show'); }

function toast(msg, isError) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._timer);
    // 失败提示停留 10s（默认 2s × 5），方便看清库存不足/结束失败等错误详情
    t._timer = setTimeout(() => t.classList.remove('show'), isError ? 10000 : 2000);
}

document.getElementById('newCustomerNickname').addEventListener('keydown', e => { if (e.key === 'Enter') confirmAddCustomer(); });
document.getElementById('productSearchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') { const first = document.querySelector('#obSearchDropdown .sdi-add-btn'); if (first) first.click(); }
});

loadVipSpentMap();
loadSessions();

/* ===== 右侧快捷查询：价格/库存 ===== */
let psTimer = null;
function openPriceStockPanel() {
    document.getElementById('psPanel').classList.add('open');
    setTimeout(() => document.getElementById('psSearchInput').focus(), 250);
}
function closePriceStockPanel() {
    document.getElementById('psPanel').classList.remove('open');
    document.getElementById('psSearchInput').value = '';
    document.getElementById('psBody').innerHTML = '<div class="ps-empty">输入关键词搜索商品，查看各SKU价格与库存</div>';
}
// 点击面板外空白处收起
document.addEventListener('click', function (e) {
    const panel = document.getElementById('psPanel');
    if (!panel.classList.contains('open')) return;
    if (panel.contains(e.target) || e.target.closest('.ps-tab')) return;
    closePriceStockPanel();
});
// 福袋面板：点击面板外空白处收起
document.addEventListener('click', function (e) {
    const panel = document.getElementById('ldPanel');
    if (!panel.classList.contains('open')) return;
    if (panel.contains(e.target) || e.target.closest('.ps-tab')) return;
    closeLuckyPanel();
});
function psSearch() {
    clearTimeout(psTimer);
    const q = document.getElementById('psSearchInput').value.trim();
    const body = document.getElementById('psBody');
    if (!q) { body.innerHTML = '<div class="ps-empty">输入关键词搜索商品，查看各SKU价格与库存</div>'; return; }
    body.innerHTML = '<div class="ps-empty">查询中...</div>';
    psTimer = setTimeout(async () => {
        try {
            const res = await fetch('../api/live_ledger_price_stock.php?q=' + encodeURIComponent(q));
            const data = await res.json();
            if (!data.success) { body.innerHTML = '<div class="ps-empty">' + esc(data.error || '查询失败') + '</div>'; return; }
            const products = data.data.products || [];
            if (!products.length) { body.innerHTML = '<div class="ps-empty">未找到匹配商品</div>'; return; }
            body.innerHTML = products.map(p => {
                const skus = (p.skus || []).map(s => `
                    <div class="ps-sku">
                        <span class="sname">${esc(s.condition_name)}</span>
                        <span class="sval"><span class="stock">库存 ${s.stock}</span><span class="price">¥${s.price ? s.price.toFixed(2) : '-'}</span></span>
                    </div>`).join('');
                return `<div class="ps-product">
                    <div class="ps-pname">${esc(p.name)}</div>
                    <div class="ps-pmeta">${esc(p.barcode || '')}${p.series ? ' · ' + esc(p.series) : ''}</div>
                    ${skus}
                </div>`;
            }).join('');
        } catch (e) {
            body.innerHTML = '<div class="ps-empty">查询失败: ' + esc(e.message) + '</div>';
        }
    }, 300);
}
</script>

<!-- 右侧快捷查询：价格/库存 + 福袋记录（仅场次内显示） -->
<div id="sideTabs">
  <div class="ps-tab" id="psTab" onclick="openPriceStockPanel()" style="display:none;">价格库存查询</div>
  <div class="ps-tab" id="ldTab" onclick="openLuckyPanel()" style="display:none;">福袋记录</div>
</div>
<div class="ps-panel" id="psPanel">
    <div class="ps-head">
        <span class="title">价格/库存查询</span>
        <button class="ps-close" onclick="closePriceStockPanel()">&times;</button>
    </div>
    <div class="ps-search">
        <input type="text" id="psSearchInput" placeholder="输入商品名称/拼音搜索..." oninput="psSearch()">
    </div>
    <div class="ps-body" id="psBody">
        <div class="ps-empty">输入关键词搜索商品，查看各SKU价格与库存</div>
    </div>
</div>

<!-- 右侧快捷查询：福袋记录（仅场次内显示） -->
<div class="ps-panel" id="ldPanel">
    <div class="ps-head">
        <span class="title">福袋记录</span>
        <button class="ps-close" onclick="closeLuckyPanel()">&times;</button>
    </div>
    <div class="ps-body" id="ldBody">
        <div class="ps-empty">本场暂无福袋记录</div>
    </div>
</div>
