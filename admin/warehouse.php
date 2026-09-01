<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireAuth();
// 运营不可进仓库页（仓库操作专属：超管/店管/仓库）
if (isOperator()) {
    header('Location: sessions.php');
    exit;
}
$currentUser = getCurrentUser();
$storeName = $_SESSION['store_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
<title>仓库出库台</title>
<style>
:root {
  --bg-body: #0a0a0f;
  --bg-surface: #12121a;
  --bg-elevated: #1a1a26;
  --bg-hover: #22222f;
  --bg-active: #2a2a3a;
  --border: #2a2a3a;
  --border-light: #1e1e2e;
  --text: #e8e8ed;
  --text-secondary: #9d9daf;
  --text-tertiary: #6b6b80;
  --primary: #5e5ce6;
  --primary-hover: #7b79f0;
  --primary-light: rgba(94, 92, 230, 0.12);
  --primary-glow: rgba(94, 92, 230, 0.3);
  --success: #34d399;
  --success-hover: #6ee7b7;
  --success-light: rgba(52, 211, 153, 0.12);
  --warning: #fbbf24;
  --danger: #f87171;
  --info: #60a5fa;
}
* { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
html, body { height:100%; }
body {
  font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif;
  background: var(--bg-body);
  color: var(--text);
  overflow: hidden;
  user-select: none;
}
#app {
  height: 100vh;
  height: 100dvh;
  display: flex;
  flex-direction: column;
  max-width: 620px;
  margin: 0 auto;
  min-height: 0;
}

/* ===== 顶部栏 ===== */
.topbar {
  flex-shrink:0;
  background: var(--bg-surface);
  border-bottom: 1px solid var(--border);
  padding: 14px 20px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}
.topbar-left { display:flex; align-items:center; gap:12px; min-width:0; }
.logo {
  width:44px; height:44px; border-radius:12px; flex-shrink:0;
  background: linear-gradient(135deg, #5e5ce6, #8b5cf6);
  display:flex; align-items:center; justify-content:center;
  font-weight:800; font-size:20px; color:#fff;
}
.topbar-title { min-width:0; }
.topbar-title h1 { font-size:22px; font-weight:700; letter-spacing:1px; white-space:nowrap; }
.topbar-title .sub { font-size:13px; color:var(--text-tertiary); margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
.topbar-right { display:flex; align-items:center; gap:10px; flex-shrink:0; }
.conn-dot { width:10px; height:10px; border-radius:50%; background:var(--success); box-shadow:0 0 8px var(--success); }
.conn-dot.off { background:var(--danger); box-shadow:0 0 8px var(--danger); }
.clock { font-size:16px; color:var(--text-secondary); font-variant-numeric:tabular-nums; }
.fullscreen-btn {
  border:1px solid var(--border); background:var(--bg-elevated); color:var(--text-secondary);
  font-size:13px; padding:6px 12px; border-radius:8px; cursor:pointer; flex-shrink:0;
}
.fullscreen-btn:active { background: var(--bg-active); color: var(--text); }
.logout-btn {
  padding: 8px 14px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--bg-elevated); color: var(--text); font-size: 14px;
  cursor: pointer; transition: background .2s, color .2s; flex-shrink: 0;
}
.logout-btn:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.4); color: #f87171; }
.logout-btn:active { background: rgba(239,68,68,.25); }

/* 待处理总数徽标 */
.pending-badge {
  background: var(--primary);
  color:#fff;
  border-radius:999px;
  padding:4px 14px;
  font-size:16px;
  font-weight:700;
  box-shadow: 0 0 14px var(--primary-glow);
}

/* ===== 场次分组头 ===== */
.session-header {
  display:flex; align-items:center; gap:10px;
  padding: 14px 20px 8px;
  position: sticky; top:0;
  background: var(--bg-body);
  z-index: 2;
}
.session-header .live-tag {
  font-size:12px; font-weight:700; letter-spacing:1px;
  color:#fff; background:var(--danger);
  padding:3px 10px; border-radius:6px;
  animation: pulse 1.6s infinite;
}
.session-header .live-tag.ended { background: var(--text-tertiary); animation:none; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
.session-header .sname { font-size:18px; font-weight:700; }
.session-header .scount { font-size:13px; color:var(--text-tertiary); margin-left:auto; }

/* ===== 任务列表 ===== */
.task-list {
  flex:1;
  overflow-y:auto;
  padding: 4px 16px 24px;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  min-height: 0;
}
.task-list::-webkit-scrollbar { width:0; }

/* 新单置顶闪烁 */
@keyframes flashNew {
  0% { background: var(--primary-light); box-shadow: 0 0 0 2px var(--primary); }
  50% { background: rgba(94,92,230,.28); box-shadow: 0 0 24px var(--primary-glow); }
  100% { background: var(--primary-light); box-shadow: 0 0 0 2px var(--primary); }
}
.task-card.new { animation: flashNew 1.2s ease-in-out 4; }

/* 单卡片 */
.task-card {
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  margin-bottom: 12px;
  padding: 16px 18px;
  display:flex;
  flex-direction:column;
  gap:10px;
  transition: transform .1s, background .15s;
}
.task-card:active { transform: scale(.99); background: var(--bg-hover); }
.task-card.return { border-color: rgba(251,191,36,.4); }
.task-card.return .tc-kind { background: var(--warning); }

/* 单头部：类型标签 + 场次 + 时间 */
.tc-top { display:flex; align-items:center; gap:8px; }
.tc-kind {
  font-size:12px; font-weight:700; color:#fff;
  background: var(--primary);
  padding:2px 8px; border-radius:6px;
  flex-shrink:0;
}
.tc-session {
  font-size:14px; color:var(--text-secondary);
  overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.tc-time { font-size:12px; color:var(--text-tertiary); margin-left:auto; flex-shrink:0; }

/* 商品名 + 数量 */
.tc-body { display:flex; align-items:center; gap:12px; }
.tc-name {
  flex:1; min-width:0;
  display:flex; align-items:center; gap:8px;
  font-size:26px; font-weight:700;
  overflow:hidden;
}
.tc-name .tn-txt { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
/* 商品名右侧价格 */
.tc-price {
  font-size:20px; font-weight:700;
  color: var(--success, #22c55e);
  margin-left:10px;
  white-space:nowrap;
}
.tc-qty {
  flex-shrink:0;
  font-size:30px; font-weight:800;
  color: var(--warning);
  background: rgba(251,191,36,.1);
  border: 2px solid var(--warning);
  border-radius: 12px;
  min-width: 84px;
  text-align:center;
  padding: 4px 12px;
  font-variant-numeric: tabular-nums;
}
.task-card.return .tc-qty { color: var(--text-secondary); border-color: var(--text-tertiary); background: var(--bg-elevated); }

/* 明细行：客户 + SKU */
.tc-meta { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.tc-vip {
  font-size:14px; color:var(--text);
  background: var(--bg-elevated);
  border:1px solid var(--border);
  padding:2px 10px; border-radius:8px;
}
.tc-cond {
  font-size:13px; color:var(--text-secondary);
  background: var(--bg-elevated);
  padding:2px 10px; border-radius:8px;
}
.tc-gift {
  font-size:13px; font-weight:700; color:#fff;
  background: var(--success);
  padding:2px 10px; border-radius:8px;
}
.tc-rack {
  font-size:12.5px; font-weight:700; color:#7b79f0;
  background: var(--primary-light);
  border:1px solid rgba(123,121,240,.4);
  padding:2px 10px; border-radius:8px;
  flex-shrink:0; white-space:nowrap;
}

/* 操作按钮 */
.tc-actions { display:flex; gap:10px; margin-top:2px; }
.btn-out {
  flex:1;
  border:none; cursor:pointer;
  font-size:22px; font-weight:700; letter-spacing:4px;
  color:#fff;
  background: linear-gradient(135deg, var(--success), #10b981);
  border-radius: 12px;
  padding: 14px 0;
  min-height: 58px;
  box-shadow: 0 4px 16px rgba(52,211,153,.25);
}
.btn-out:active { transform: scale(.98); filter: brightness(1.1); }
.task-card.return .btn-out { background: linear-gradient(135deg, var(--warning), #f59e0b); box-shadow: 0 4px 16px rgba(251,191,36,.2); }

/* ===== 底部 tab ===== */
.bottombar {
  flex-shrink:0;
  display:flex;
  background: var(--bg-surface);
  border-top: 1px solid var(--border);
  padding-bottom: env(safe-area-inset-bottom, 0px);
}
.tab {
  flex:1;
  padding: 14px 0 18px;
  text-align:center;
  font-size:17px;
  color: var(--text-secondary);
  cursor:pointer;
  border:none; background:none;
  border-top: 3px solid transparent;
}
.tab.active { color: var(--text); border-top-color: var(--primary); font-weight:700; }
.tab .cnt { font-size:13px; margin-left:6px; color:var(--text-tertiary); }

/* 已处理区 */
.done-list { flex:1; overflow-y:auto; padding: 16px 16px 24px; -webkit-overflow-scrolling:touch; overscroll-behavior: contain; min-height: 0; }
.done-list::-webkit-scrollbar { width:0; }
.done-card {
  background: var(--bg-surface);
  border:1px solid var(--border-light);
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 10px;
  display:flex; align-items:center; gap:10px;
  opacity:.85;
}
.done-card .done-main { flex:1; min-width:0; }
.done-card .done-name { font-size:18px; font-weight:600; display:flex; align-items:center; gap:6px; overflow:hidden; }
.done-card .done-name .dn-txt { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
.done-card .done-name .tc-rack { font-size:11px; padding:1px 7px; flex-shrink:0; }
.done-card .done-name .tc-kind, .done-card .done-name .tc-price { flex-shrink:0; }
.done-card .done-meta { font-size:12px; color:var(--text-tertiary); margin-top:3px; }
.done-card .done-qty { font-size:20px; font-weight:700; color:var(--text-secondary); flex-shrink:0; }
.btn-undo {
  border:1px solid var(--border); background:var(--bg-elevated); color:var(--text-secondary);
  font-size:15px; padding:8px 14px; border-radius:10px; cursor:pointer; flex-shrink:0;
}
.btn-undo:active { background: var(--bg-active); color: var(--text); }

/* 空状态 */
.empty {
  flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
  color: var(--text-tertiary); font-size:16px; gap:10px; padding: 40px 20px;
}
.empty .big { font-size:52px; opacity:.5; }
.empty .hint { font-size:13px; opacity:.7; }

/* 新单提示 toast */
#newToast {
  position: fixed;
  top: 70px; left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, var(--primary), #8b5cf6);
  color:#fff;
  font-size:18px; font-weight:700;
  padding: 12px 28px;
  border-radius: 999px;
  box-shadow: 0 8px 30px var(--primary-glow);
  transition: opacity .3s ease, transform .3s ease;
  opacity: 0;
  visibility: hidden;
  z-index: 99;
  pointer-events: none;
  white-space: nowrap;
  max-width: 90%;
  overflow: hidden; text-overflow: ellipsis;
}
#newToast.show { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(10px); }
</style>
</head>
<body>
<div id="app">
  <!-- 顶部 -->
  <div class="topbar">
    <div class="topbar-left">
      <div class="logo">仓</div>
      <div class="topbar-title">
        <h1>仓库出库台</h1>
        <div class="sub"><?= htmlspecialchars($storeName) ?> · 待出库按单处理</div>
      </div>
    </div>
    <div class="topbar-right">
      <span class="pending-badge" id="pendingBadge">0</span>
      <span class="conn-dot" id="connDot"></span>
      <span class="clock" id="clock"></span>
      <button class="fullscreen-btn" onclick="toggleFullscreen()">全屏</button>
      <button class="logout-btn" onclick="logout()">登出</button>
    </div>
  </div>

  <!-- 待出库列表 -->
  <div class="task-list" id="taskList"></div>

  <!-- 已处理列表（隐藏） -->
  <div class="done-list" id="doneList" style="display:none;"></div>

  <!-- 底部 tab -->
  <div class="bottombar">
    <button class="tab active" id="tabTodo" onclick="switchTab('todo')">待出库 <span class="cnt" id="tabTodoCnt"></span></button>
    <button class="tab" id="tabDone" onclick="switchTab('done')">已处理 <span class="cnt" id="tabDoneCnt"></span></button>
  </div>
</div>

<div id="newToast"></div>

<script>
const $ = id => document.getElementById(id);
const now = () => new Date();

// ============ 状态 ============
let allItems = [];          // 后端返回全部（pending + done）
let knownIds = new Set();   // 已渲染 id（用于检测新单）
let currentTab = 'todo';
let loading = false;
let condMap = {};           // condition_type key → 中文名（从店铺设置加载）
let posMap = {};            // product_id → 货架位置（拣货/归位提示）

// 默认映射（设置未加载时兜底）
const COND_FALLBACK = { sealed: '未拆袋', opened: '已拆无瑕', boxless: '无盒无瑕', flawed: '微瑕' };
function condCN(key) {
  if (!key) return '';
  return condMap[key] || COND_FALLBACK[key] || key;
}
// 加载店铺状态设置
async function loadSettings() {
  try {
    const res = await fetch('../api/get_settings.php');
    const data = await res.json();
    if (data.success && data.data && data.data.condition_types) {
      const cm = {};
      data.data.condition_types.forEach(c => { cm[c.key] = c.name; });
      condMap = cm;
    }
  } catch (e) {}
}
// 价格显示：客户记账实际售价（操作人改过即改后价）
function fmtPrice(v) {
  if (v === null || v === undefined || v === '') return '';
  return '¥' + (parseFloat(v) || 0).toFixed(2);
}

// 加载货架分布 → product_id 位置映射（拣货/归位提示）
async function loadRacks() {
  try {
    const res = await fetch('../api/get_racks.php', { cache: 'no-store' });
    const d = await res.json();
    if (!d.success) return;
    posMap = {};
    Object.keys(d.racks).forEach(rack => {
      Object.keys(d.racks[rack]).forEach(row => {
        Object.keys(d.racks[rack][row]).forEach(pos => {
          const c = d.racks[rack][row][pos];
          if (c && c.product) {
            posMap[c.product.id] = escapeHtml(rack) + ' · 第' + row + '层 · 第' + pos + (c.span > 1 ? '-' + (+pos + 1) : '') + '格';
          }
        });
      });
    });
  } catch (e) { /* 货架未配置时不阻塞 */ }
}

// ============ 渲染 ============
function fmtTime(ts) {
  if (!ts) return '';
  const d = new Date(ts.replace(' ', 'T'));
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

function fmtClock(d) {
  return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
}

function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function render(newIds = null) {
  const pending = allItems.filter(t => t.status === 'pending');
  const done = allItems.filter(t => t.status === 'done')
    .sort((a, b) => new Date(b.done_at) - new Date(a.done_at)); // 已处理：最新处理在上

  $('pendingBadge').textContent = pending.length;
  $('tabTodoCnt').textContent = pending.length;
  $('tabDoneCnt').textContent = done.length;

  // ---- 待出库：按场次分组（顺序 = API 已排序：直播中在前、组间稳定） ----
  const groups = [];
  for (const t of pending) {
    let g = groups.find(x => x.session_id === t.session_id);
    if (!g) { g = { session_id: t.session_id, session_name: t.session_name, session_status: t.session_status, tasks: [] }; groups.push(g); }
    g.tasks.push(t);
  }

  $('taskList').innerHTML = '';
  if (!pending.length) {
    $('taskList').innerHTML = `<div class="empty"><div class="big">✓</div><div>暂无待出库</div><div class="hint">运营记账添加商品后自动出现在这里</div></div>`;
  } else {
    for (const g of groups) {
      const head = document.createElement('div');
      head.className = 'session-header';
      head.innerHTML = `
        <span class="live-tag ${g.session_status === 'active' ? '' : 'ended'}">${g.session_status === 'active' ? '直播中' : '已结束'}</span>
        <span class="sname">${escapeHtml(g.session_name)}</span>
        <span class="scount">${g.tasks.length} 单</span>
      `;
      $('taskList').appendChild(head);
      for (const t of g.tasks) {
        // 拆件显示：qty>1 拆成 qty 张独立卡片（每张 ×1），每件独立处理
        const n = Math.max(1, parseInt(t.qty) || 1);
        for (let i = 0; i < n; i++) {
          const card = buildCard(t);
          if (newIds && newIds.has(t.id)) card.classList.add('new');
          $('taskList').appendChild(card);
        }
      }
    }
  }

  // ---- 已处理 ----
  $('doneList').innerHTML = '';
  if (!done.length) {
    $('doneList').innerHTML = `<div class="empty"><div class="big">✓</div><div>暂无已处理</div></div>`;
  } else {
    for (const t of done) {
      // 已处理也拆件显示：qty=0（整单完成）显示 1 张供撤销；qty>1 拆多张，每次撤销恢复 1 件
      const n = Math.max(1, parseInt(t.qty) || 1);
      for (let i = 0; i < n; i++) {
        const card = document.createElement('div');
        card.className = 'done-card';
        card.innerHTML = `
          <div class="done-main">
            <div class="done-name"><span class="dn-txt">${escapeHtml(t.product_name)}</span>${posMap[t.product_id] ? `<span class="tc-rack">${posMap[t.product_id]}</span>` : ''} <span class="tc-kind" style="font-size:11px;${t.type === 'return' ? 'background:var(--warning);color:#000' : ''}">${t.type === 'return' ? '回库' : '出库'}</span>${t.sell_price !== null && t.sell_price !== undefined && t.sell_price !== '' ? `<span class="tc-price">${fmtPrice(t.sell_price)}</span>` : ''}</div>
            <div class="done-meta">${escapeHtml(t.session_name)} · ${escapeHtml(t.vip_no ? (t.vip_no + (t.nickname ? ' ' + t.nickname : '')) : (t.nickname || '新客户'))} · ${escapeHtml(condCN(t.condition_type))}${t.is_gift ? ' · 赠品' : ''} · ${fmtTime(t.done_at)} 处理</div>
          </div>
          <div class="done-qty">×1</div>
          <button class="btn-undo" onclick="undoTask(${t.id})">撤销</button>
        `;
        $('doneList').appendChild(card);
      }
    }
  }
}

function buildCard(t) {
  const card = document.createElement('div');
  card.className = 'task-card' + (t.type === 'return' ? ' return' : '');
  card.dataset.id = t.id;
  const vipText = t.vip_no ? (t.vip_no + (t.nickname ? ' ' + t.nickname : '')) : (t.nickname || '新客户');
  card.innerHTML = `
    <div class="tc-top">
      <span class="tc-kind">${t.type === 'return' ? '待回库' : '待出库'}</span>
      <span class="tc-session">${escapeHtml(t.session_name)}</span>
      <span class="tc-time">${fmtTime(t.created_at)}</span>
    </div>
    <div class="tc-body">
      <div class="tc-name"><span class="tn-txt">${escapeHtml(t.product_name)}</span>${posMap[t.product_id] ? `<span class="tc-rack">${posMap[t.product_id]}</span>` : ''} ${t.sell_price !== null && t.sell_price !== undefined && t.sell_price !== '' ? `<span class="tc-price">${fmtPrice(t.sell_price)}</span>` : ''}</div>
      <div class="tc-qty">×1</div>
    </div>
    <div class="tc-meta">
      <span class="tc-vip">${escapeHtml(vipText)}</span>
      ${t.condition_type ? `<span class="tc-cond">${escapeHtml(condCN(t.condition_type))}</span>` : ''}
      ${t.is_gift ? `<span class="tc-gift">赠品</span>` : ''}
    </div>
    <div class="tc-actions">
      <button class="btn-out" onclick="completeTask(${t.id})">${t.type === 'return' ? '已回库' : '已出库'}</button>
    </div>
  `;
  return card;
}

// ============ 数据加载（5s 轮询） ============
async function load() {
  if (loading) return;
  loading = true;
  try {
    await loadRacks(); // 顺带刷新货架位置映射
    const res = await fetch('../api/warehouse_list.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || '加载失败');
    const items = data.data.items || [];
    $('connDot').classList.remove('off');

    // 检测新单（本轮新增且为 pending）
    const newIds = new Set();
    if (knownIds.size > 0) {
      for (const t of items) {
        if (!knownIds.has(t.id) && t.status === 'pending') {
          newIds.add(t.id);
        }
      }
    }
    // 更新 known 集合
    knownIds = new Set(items.map(t => t.id));

    allItems = items;
    render(newIds);

    if (newIds.size > 0) {
      const names = items.filter(t => newIds.has(t.id)).map(t => t.product_name).join('、');
      showNewToast('新待出库：' + names);
      alarm();
    }
  } catch (e) {
    $('connDot').classList.add('off');
    console.error(e);
  } finally {
    loading = false;
  }
}

// ============ 操作 ============
async function completeTask(id) {
  try {
    const res = await fetch('../api/warehouse_complete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || '操作失败');
    beep(880, .08);
    await load();  // 重新拉取保证顺序正确
  } catch (e) {
    showNewToast('操作失败：' + e.message);
  }
}

async function undoTask(id) {
  try {
    const res = await fetch('../api/warehouse_undo.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || '操作失败');
    await load();
  } catch (e) {
    showNewToast('撤销失败：' + e.message);
  }
}

// ============ 新单提示 ============
function showNewToast(msg) {
  const el = $('newToast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 2500);
}

// ============ 声音（Web Audio，无外部文件） ============
let audioCtx = null;
function ensureAudio() {
  if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  if (audioCtx.state === 'suspended') audioCtx.resume();
}
function alarm() {
  ensureAudio();
  beep(660, .18); setTimeout(() => beep(880, .18), 200); setTimeout(() => beep(1320, .22), 400);
}
function beep(freq, dur) {
  if (!audioCtx) return;
  const o = audioCtx.createOscillator(), g = audioCtx.createGain();
  o.type = 'sine'; o.frequency.value = freq;
  g.gain.setValueAtTime(.25, audioCtx.currentTime);
  g.gain.exponentialRampToValueAtTime(.001, audioCtx.currentTime + dur);
  o.connect(g); g.connect(audioCtx.destination);
  o.start(); o.stop(audioCtx.currentTime + dur);
}
// 首次触摸解锁音频（触摸屏电视）
document.addEventListener('touchstart', ensureAudio, { once: true });
document.addEventListener('click', ensureAudio, { once: true });

// ============ UI ============
function switchTab(which) {
  currentTab = which;
  const isTodo = which === 'todo';
  $('tabTodo').classList.toggle('active', isTodo);
  $('tabDone').classList.toggle('active', !isTodo);
  $('taskList').style.display = isTodo ? '' : 'none';
  $('doneList').style.display = isTodo ? 'none' : '';
}

function toggleFullscreen() {
  if (!document.fullscreenElement) {
    (document.documentElement.requestFullscreen ? document.documentElement.requestFullscreen() : Promise.resolve())
      .then(() => {}).catch(() => {});
  } else {
    document.exitFullscreen().catch(() => {});
  }
}

// 登出：调接口清 session 后回登录页
function logout() {
  fetch('../api/logout.php', { method: 'POST' })
    .then(r => r.json())
    .then(d => { window.location.href = '../login.php'; })
    .catch(() => { window.location.href = '../login.php'; });
}

// 时钟
setInterval(() => { $('clock').textContent = fmtClock(now()); }, 1000);
$('clock').textContent = fmtClock(now());

// 轮询
loadSettings();  // 预加载状态中文名（异步，首帧可能用兜底映射，刷新后正确）
load();
setInterval(load, 5000);
</script>
</body>
</html>
