<?php
$pageTitle = '抽奖活动';
$currentPage = 'lottery';
require_once __DIR__ . '/layout.php';
$lotteryAllowed = in_array($currentUser['role'] ?? '', ['store_admin', 'group_admin', 'super_admin'], true);
$lotteryPickShop = in_array($currentUser['role'] ?? '', ['group_admin', 'super_admin'], true);
?>
<div class="page-title">抽奖活动 <span class="sub" style="font-size:12px;color:var(--text-tertiary);font-weight:500">线下收银台满额抽奖 · 付款后转盘 · 奖品进门店待出库</span></div>
<?php if (!$lotteryAllowed || !$storeId): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--text-tertiary)"><?= $lotteryAllowed ? '请先选择店铺' : '无权限' ?></div>
<?php exit; endif; ?>

<style>
  .lt-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
  .lt-toolbar input{flex:1;min-width:180px;height:36px;padding:0 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-body);color:var(--text)}
  .lt-tbl{width:100%;border-collapse:collapse;font-size:13px}
  .lt-tbl th,.lt-tbl td{padding:9px 10px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
  .lt-tbl th{background:var(--bg-hover);color:var(--text-secondary);font-size:12px;white-space:nowrap}
  .lt-tbl td.num,.lt-tbl th.num{text-align:right;font-variant-numeric:tabular-nums}
  .lt-badge{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:8px}
  .lt-on{background:rgba(62,207,142,.15);color:#2fa375}
  .lt-off{background:rgba(148,163,184,.15);color:#64748b}
  .lt-warn{background:rgba(245,158,11,.15);color:#b45309}
  .lt-void{background:rgba(239,68,68,.12);color:#dc2626}
  .lt-type{background:rgba(99,102,241,.12);color:#4f46e5}
  .lt-muted{color:var(--text-tertiary);font-size:12px}
  .lt-acts{display:flex;gap:6px;flex-wrap:wrap}
  .lt-row-dis{opacity:.5}
  .lt-hint{font-size:12px;color:var(--text-tertiary);margin:6px 0 0;line-height:1.6}
  .lt-apx{margin:0 0 12px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-hover)}
  .lt-apx-ttl{font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:6px}
  .lt-apx-seg{display:inline-block;margin:2px 6px 2px 0;padding:2px 8px;border-radius:10px;font-size:12px;background:rgba(99,102,241,.12);color:#4f46e5}
  .lt-apx-seg b{font-weight:700}
  .lt-apx-off{margin-top:6px;font-size:12px;color:#dc2626;line-height:1.6}
</style>

<div class="lt-toolbar">
  <input id="ltQ" placeholder="搜索活动名称…" oninput="renderCampaigns()">
  <?php if ($lotteryPickShop): ?>
  <select id="ltShop" style="height:36px;border:1px solid var(--border);border-radius:8px;background:var(--bg-body);color:var(--text);padding:0 8px;" onchange="refreshAll()"></select>
  <?php endif; ?>
  <button class="btn btn-primary" onclick="editCampaign(null)">+ 新建活动</button>
  <button class="btn btn-secondary" onclick="loadAll()">刷新</button>
</div>

<div class="card">
  <div style="overflow-x:auto"><table class="lt-tbl">
    <thead><tr><th>活动</th><th>归属</th><th class="num">满额门槛</th><th>再来一次</th><th>保底</th><th>有效期</th><th class="num">奖品</th><th class="num">抽奖/已领</th><th>状态</th><th>操作</th></tr></thead>
    <tbody id="ltRows"><tr><td colspan="10" style="text-align:center;color:var(--text-tertiary)">加载中…</td></tr></tbody>
  </table></div>
</div>

<!-- 活动编辑 -->
<div class="modal" id="ltModal">
  <div class="modal-content" style="max-width:640px">
    <div class="modal-header"><h3 class="modal-title" id="ltModalTitle">新建抽奖活动</h3><button class="modal-close" onclick="hide('ltModal')">&times;</button></div>
    <input type="hidden" id="ltId">
    <div style="display:flex;flex-direction:column;gap:12px">
      <div class="form-row">
        <div class="form-group" style="flex:2"><label class="form-label">活动名称 *</label><input class="form-input" id="ltName" placeholder="如：周年庆转盘抽奖"></div>
        <div class="form-group"><label class="form-label">单笔满额门槛 ¥</label><input class="form-input" type="number" min="0" step="0.01" id="ltThreshold" value="100"></div>
      </div>
      <?php if ($lotteryPickShop): ?>
      <div class="form-row">
        <div class="form-group" style="flex:1"><label class="form-label">归属店铺 *</label>
          <select class="form-input" id="ltShopNew"></select>
          <span class="lt-hint">「全部门店」= 集团通用活动（各店没有专属活动时生效）</span>
        </div>
      </div>
      <?php endif; ?>
      <div class="form-row">
        <div class="form-group"><label class="form-label">「再来一次」最多追加次数</label><input class="form-input" type="number" min="0" max="9" id="ltSpinAgain" value="2"></div>
        <div class="form-group"><label class="form-label">保底奖品（可选）</label><select class="form-input" id="ltGuarantee"><option value="0">不设置</option></select>
          <span class="lt-hint">设置后「未中奖」的权重整体改发保底奖品（每单必中）；若其余奖品权重已占满 100，保底不生效</span>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">开始时间（可空）</label><input class="form-input" type="datetime-local" id="ltStart"></div>
        <div class="form-group"><label class="form-label">结束时间（可空）</label><input class="form-input" type="datetime-local" id="ltEnd"></div>
      </div>
      <div class="form-group"><label class="form-label">备注</label><input class="form-input" id="ltRemark" placeholder="选填"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-secondary" onclick="hide('ltModal')">取消</button>
        <button class="btn btn-primary" onclick="saveCampaign()">保存</button>
      </div>
    </div>
  </div>
</div>

<!-- 奖品配置 -->
<div class="modal" id="ltPrizeModal">
  <div class="modal-content" style="max-width:860px">
    <div class="modal-header"><h3 class="modal-title">奖品配置 <span class="lt-muted" id="ltPrizeCampName"></span></h3><button class="modal-close" onclick="hide('ltPrizeModal')">&times;</button></div>
    <input type="hidden" id="ltPrizeCampId">
    <div style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap">
      <button class="btn btn-primary btn-sm" onclick="editPrize(null)">+ 新增奖品</button>
      <button class="btn btn-sm btn-secondary" onclick="evenWeights()">平均分配权重</button>
      <span class="lt-muted" id="ltPrizeSummary"></span>
    </div>
    <div class="lt-apx" id="ltPrizePreview"></div>
    <div style="overflow-x:auto"><table class="lt-tbl">
      <thead><tr><th>奖品</th><th>类型</th><th class="num">权重</th><th class="num">配置概率</th><th class="num">可中数量</th><th class="num">已中出</th><th>配置</th><th>状态</th><th>操作</th></tr></thead>
      <tbody id="ltPrizeRows"></tbody>
    </table></div>
    <p class="lt-hint">概率 = 权重 ÷ 100，权重之和不足 100 时剩余视为「未中奖」；未设置保底奖品时，转盘上会自动补一个「谢谢参与」扇区。<br>
      停用/已中完/券已结束/商品无库存的奖品不会出现在转盘上（权重自动让给其它奖品与「未中奖」）。<br>
      权重为 0 的启用奖品同样不会出现在转盘上；点「平均分配权重」可把启用奖品一键均分（合计 100）。</p>
  </div>
</div>

<!-- 奖品编辑 -->
<div class="modal" id="ltPrizeEditModal">
  <div class="modal-content" style="max-width:640px">
    <div class="modal-header"><h3 class="modal-title" id="ltPrizeEditTitle">新增奖品</h3><button class="modal-close" onclick="hide('ltPrizeEditModal')">&times;</button></div>
    <input type="hidden" id="ltPrId">
    <div style="display:flex;flex-direction:column;gap:12px">
      <div class="form-row">
        <div class="form-group" style="flex:2"><label class="form-label">奖品名称（转盘上显示）*</label><input class="form-input" id="ltPrName" placeholder="如：满 50 减 10 券 / 盲盒一个"></div>
        <div class="form-group"><label class="form-label">类型</label>
          <select class="form-input" id="ltPrType" onchange="onPrizeTypeChange()">
            <option value="coupon">优惠券</option>
            <option value="product">在库商品</option>
            <option value="custom">自定义商品</option>
            <option value="spin_again">再来一次</option>
            <option value="none">未中奖</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">权重（权重法）</label><input class="form-input" type="number" min="0" max="1000" id="ltPrWeight" value="10">
          <span class="lt-hint">例：权重 20 = 20%；全部权重之和 &lt; 100 时剩余为「未中奖」</span>
        </div>
        <div class="form-group"><label class="form-label">可中数量（0=不限）</label><input class="form-input" type="number" min="0" id="ltPrQuota" value="0"></div>
      </div>
      <div class="form-group" id="ltPrCouponGroup"><label class="form-label">券活动 *</label><select class="form-input" id="ltPrCoupon"></select></div>
      <div class="form-row" id="ltPrProductGroup">
        <div class="form-group" style="flex:1.4"><label class="form-label">商品 *</label><select class="form-input" id="ltPrProduct" onchange="renderProductConds()"></select></div>
        <div class="form-group" style="flex:1"><label class="form-label">品相 *</label><select class="form-input" id="ltPrCond"></select></div>
        <div class="form-group" style="flex:.6"><label class="form-label">数量</label><input class="form-input" type="number" min="1" id="ltPrQty" value="1"></div>
      </div>
      <div class="form-group" id="ltPrNoteGroup"><label class="form-label">自定义奖品说明 *（门店待出库登记用）</label><input class="form-input" id="ltPrNote" placeholder="如：官方周边挂件一个（非库存商品）"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">排序（越小越靠前）</label><input class="form-input" type="number" id="ltPrSort" value="0"></div>
        <div class="form-group"><label class="form-label">状态</label>
          <select class="form-input" id="ltPrStatus"><option value="1">启用</option><option value="0">停用</option></select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-secondary" onclick="hide('ltPrizeEditModal')">取消</button>
        <button class="btn btn-primary" onclick="savePrize()">保存</button>
      </div>
    </div>
  </div>
</div>

<!-- 抽奖记录 -->
<div class="card" style="margin-top:14px">
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
    <h3 class="card-title" style="margin:0">抽奖记录</h3>
    <input id="ltDrawPhone" class="form-input" style="width:170px;height:34px" placeholder="按手机号筛选" oninput="loadDraws()">
    <select id="ltDrawCamp" class="form-input" style="width:200px;height:34px" onchange="loadDraws()"></select>
    <button class="btn btn-secondary btn-sm" onclick="loadDraws()">刷新记录</button>
    <span class="lt-muted" id="ltDrawStats"></span>
  </div>
  <div style="overflow-x:auto"><table class="lt-tbl">
    <thead><tr><th>时间</th><th>订单号</th><th>门店</th><th>奖品</th><th class="num">第几次</th><th>手机号</th><th>状态</th><th>落地</th><th>操作</th></tr></thead>
    <tbody id="ltDrawRows"><tr><td colspan="9" style="text-align:center;color:var(--text-tertiary)">加载中…</td></tr></tbody>
  </table></div>
</div>

<div class="toast" id="toast"></div>

<script>
const LT_PICK = <?= $lotteryPickShop ? 'true' : 'false' ?>;
let LT = [];        // 活动
let LT_PRIZES = []; // 当前活动奖品
let LT_SEGMENTS = []; // 当前活动在收银台转盘上的真实扇区（服务端生成）
let LT_OPT = { coupons: [], products: [], shops: [] };
const PRIZE_TYPES = { coupon: '优惠券', product: '在库商品', custom: '自定义商品', spin_again: '再来一次', none: '未中奖' };
const CLAIM_NAMES = { pending: '待领取', claimed: '已领取', voided: '已作废' };

function $(id) { return document.getElementById(id); }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function fmt(n) { return (parseFloat(n) || 0).toFixed(2); }
function dt(s) { return s ? String(s).replace('T', ' ').slice(0, 16) : ''; }
function show(id) { $(id).classList.add('show'); }
function hide(id) { $(id).classList.remove('show'); }
let toastT;
function toast(msg, err) {
  const t = $('toast'); t.textContent = msg; t.className = 'toast' + (err ? ' err' : '');
  requestAnimationFrame(() => t.classList.add('show'));
  clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('show'), err ? 6000 : 2000);
}
async function api(body) {
  const res = await fetch('../api/lottery_admin.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body)
  });
  const data = await res.json();
  data._status = res.status;
  return data;
}
// 同归属已有进行中的活动：确认后暂停它并继续（方案B）
async function resolveActiveConflict(d, retryBody) {
  if (d.success || !d.conflict) return d;
  const isGroup = d.conflict.scope === 'group';
  const ok = confirm(isGroup
    ? `「${d.conflict.name}」是集团通用活动且正在生效。\n集团通用活动同一时间只允许一个。\n是否暂停它并启用本活动？`
    : `「${d.conflict.name}」正在该门店生效。\n同一门店同一时间只允许一个进行中的活动。\n是否暂停它并启用本活动？`);
  if (!ok) { toast('已取消，未做修改', true); return null; }
  return api(Object.assign({}, retryBody, { replace_active: 1 }));
}
function shopParam() {
  if (!LT_PICK) return {};
  const s = $('ltShop');
  return (s && s.value) ? { shop_id: parseInt(s.value) } : {};
}

// ===== 活动 =====
async function loadAll() {
  const d = await api(Object.assign({ action: 'list' }, shopParam()));
  if (!d.success) { toast(d.error || '加载失败', true); return; }
  LT = d.campaigns || [];
  renderCampaigns();
  renderDrawCampSelect();
  loadDraws();
}
async function loadOptions() {
  const d = await api(Object.assign({ action: 'options' }, shopParam()));
  if (!d.success) { toast(d.error || '加载选项失败', true); return; }
  LT_OPT = { coupons: d.coupons || [], products: d.products || [], shops: d.shops || [] };
  if (LT_PICK) {
    const selNew = $('ltShopNew');
    if (selNew) selNew.innerHTML = '<option value="0">全部门店</option>' + LT_OPT.shops.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
    const sel = $('ltShop');
    if (sel) {
      const cur = sel.value;
      sel.innerHTML = '<option value="">全部店</option>' + LT_OPT.shops.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
      if (cur) sel.value = cur;
    }
  }
  $('ltPrCoupon').innerHTML = LT_OPT.coupons.length
    ? LT_OPT.coupons.map(c => `<option value="${c.id}">${esc(c.name)}（${c.coupon_type === 'fixed' ? '无门槛减' : '满' + fmt(c.threshold) + '减'}${fmt(c.amount)}${c.active_now ? '' : ' · 未生效'}）</option>`).join('')
    : '<option value="">（暂无可用的券活动，请先到「优惠券」创建）</option>';
  $('ltPrProduct').innerHTML = LT_OPT.products.length
    ? LT_OPT.products.map(p => `<option value="${p.id}">${esc((p.series ? p.series + ' · ' : '') + p.name)}</option>`).join('')
    : '<option value="">（暂无在库商品）</option>';
  renderProductConds();
}
function renderCampaigns() {
  const kw = ($('ltQ').value || '').trim();
  const list = kw ? LT.filter(c => (c.name || '').includes(kw)) : LT;
  if (!list.length) {
    $('ltRows').innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--text-tertiary)">暂无抽奖活动，点右上角「新建活动」开始配置</td></tr>';
    return;
  }
  $('ltRows').innerHTML = list.map(c => {
    const on = c.status === 'active';
    const period = (c.start_at || c.end_at)
      ? `${dt(c.start_at) || '不限'} ~ ${dt(c.end_at) || '不限'}`
      : '长期有效';
    return `<tr>
      <td><b>${esc(c.name)}</b>${c.remark ? `<div class="lt-muted">${esc(c.remark)}</div>` : ''}</td>
      <td>${c.shop_id ? esc(c.shop_name || ('店#' + c.shop_id)) : '<span class="lt-badge lt-type">全部门店</span>'}</td>
      <td class="num">¥${fmt(c.threshold)}</td>
      <td class="num">${parseInt(c.spin_again_limit) || 0} 次</td>
      <td>${c.guarantee_prize_id ? '<span class="lt-badge lt-warn">已设置</span>' : '<span class="lt-muted">未设置</span>'}</td>
      <td class="lt-muted">${period}</td>
      <td class="num">${parseInt(c.prize_count) || 0}</td>
      <td class="num">${parseInt(c.draw_count) || 0} / ${parseInt(c.claimed_count) || 0}</td>
      <td><span class="lt-badge ${on ? 'lt-on' : 'lt-off'}">${on ? '进行中' : '已暂停'}</span>${parseInt(c.is_effective) ? ' <span class="lt-badge lt-type">当前生效</span>' : ''}</td>
      <td><div class="lt-acts">
        <button class="btn btn-sm btn-primary" onclick="openPrizes(${c.id})">奖品配置</button>
        <button class="btn btn-sm btn-secondary" onclick="editCampaign(${c.id})">编辑</button>
        <button class="btn btn-sm btn-secondary" onclick="toggleCampaign(${c.id})">${on ? '暂停' : '启用'}</button>
        <button class="btn btn-sm btn-outline" onclick="deleteCampaign(${c.id})">删除</button>
      </div></td>
    </tr>`;
  }).join('');
}
async function loadDraws() {
  const body = Object.assign({ action: 'draws' }, shopParam());
  const cid = $('ltDrawCamp').value;
  if (cid) body.campaign_id = parseInt(cid);
  const ph = ($('ltDrawPhone').value || '').trim();
  if (ph) body.phone = ph;
  const d = await api(body);
  if (!d.success) { toast(d.error || '加载记录失败', true); return; }
  const st = d.stats || {};
  $('ltDrawStats').textContent = `共 ${st.total || 0} 次 · 已中出 ${st.won || 0} · 待领取 ${st.pending || 0} · 已作废 ${st.voided || 0}`;
  const rows = d.draws || [];
  if (!rows.length) {
    $('ltDrawRows').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary)">暂无抽奖记录</td></tr>';
    return;
  }
  $('ltDrawRows').innerHTML = rows.map(r => {
    const badge = r.claim_status === 'voided' ? 'lt-void' : (r.claim_status === 'claimed' ? 'lt-on' : 'lt-warn');
    let land = '-';
    if (r.coupon_claim_id) land = `券 #${r.coupon_claim_id}`;
    else if (r.prize_order_id) land = `待出库单 #${r.prize_order_id}`;
    else if (r.prize_type === 'none') land = '未中奖';
    else if (r.prize_type === 'spin_again') land = '追加一次';
    return `<tr>
      <td class="lt-muted">${esc(r.created_at)}</td>
      <td class="lt-muted">${esc(r.order_no || '')}</td>
      <td class="lt-muted">${esc(r.shop_name || '')}</td>
      <td>${esc(r.prize_name || '')} <span class="lt-badge lt-type">${PRIZE_TYPES[r.prize_type] || r.prize_type}</span>${parseInt(r.is_guarantee) ? '<span class="lt-badge lt-warn">保底</span>' : ''}</td>
      <td class="num">${r.spin_index}</td>
      <td class="lt-muted">${esc(r.phone || '')}</td>
      <td><span class="lt-badge ${badge}">${CLAIM_NAMES[r.claim_status] || r.claim_status}</span></td>
      <td class="lt-muted">${land}</td>
      <td>${r.claim_status === 'voided' ? '' : `<button class="btn btn-sm btn-outline" onclick="voidDraw(${r.id})">作废</button>`}</td>
    </tr>`;
  }).join('');
}
function renderDrawCampSelect() {
  const sel = $('ltDrawCamp');
  const cur = sel.value;
  sel.innerHTML = '<option value="">全部活动</option>' + LT.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
  if (cur) sel.value = cur;
}
function editCampaign(id) {
  const c = LT.find(x => x.id === id) || null;
  $('ltId').value = c ? c.id : '';
  $('ltModalTitle').textContent = c ? '编辑抽奖活动' : '新建抽奖活动';
  $('ltName').value = c ? (c.name || '') : '';
  $('ltThreshold').value = c ? fmt(c.threshold) : '100';
  $('ltSpinAgain').value = c ? (parseInt(c.spin_again_limit) || 0) : 2;
  $('ltStart').value = c && c.start_at ? String(c.start_at).replace(' ', 'T').slice(0, 16) : '';
  $('ltEnd').value = c && c.end_at ? String(c.end_at).replace(' ', 'T').slice(0, 16) : '';
  $('ltRemark').value = c ? (c.remark || '') : '';
  if (LT_PICK) {
    const sel = $('ltShopNew');
    if (sel) sel.value = c ? (c.shop_id || 0) : (($('ltShop') && $('ltShop').value) || 0);
  }
  // 保底奖品下拉：当前活动的奖品
  const gSel = $('ltGuarantee');
  const gid = c ? (parseInt(c.guarantee_prize_id) || 0) : 0;
  gSel.innerHTML = '<option value="0">不设置</option>';
  if (c) {
    api({ action: 'prizes', campaign_id: c.id }).then(d => {
      if (!d.success) return;
      (d.prizes || []).filter(p => p.prize_type !== 'spin_again' && p.prize_type !== 'none').forEach(p => {
        gSel.insertAdjacentHTML('beforeend', `<option value="${p.id}"${parseInt(p.id) === gid ? ' selected' : ''}>${esc(p.name)}</option>`);
      });
    });
  }
  show('ltModal');
}
async function saveCampaign() {
  const body = Object.assign({
    action: 'save',
    id: parseInt($('ltId').value) || 0,
    name: ($('ltName').value || '').trim(),
    threshold: parseFloat($('ltThreshold').value) || 0,
    spin_again_limit: parseInt($('ltSpinAgain').value) || 0,
    guarantee_prize_id: parseInt($('ltGuarantee').value) || 0,
    start_at: $('ltStart').value ? $('ltStart').value.replace('T', ' ') + ':00' : '',
    end_at: $('ltEnd').value ? $('ltEnd').value.replace('T', ' ') + ':00' : '',
    remark: ($('ltRemark').value || '').trim(),
  }, shopParam());
  if (LT_PICK) {
    const selNew = $('ltShopNew');
    body.shop_id = selNew ? (parseInt(selNew.value) || 0) : 0;
    if (!body.id && body.shop_id === 0 && !confirm('归属「全部门店」表示集团通用活动，确定？')) return;
  }
  if (!body.name) { toast('请填写活动名称', true); return; }
  let d = await api(body);
  if (!d.success && d.conflict) {
    d = await resolveActiveConflict(d, body);
    if (!d) return;
  }
  if (!d.success) { toast(d.error || '保存失败', true); return; }
  hide('ltModal'); toast('已保存'); loadAll(); loadOptions();
}
async function toggleCampaign(id) {
  let d = await api({ action: 'toggle', id });
  if (!d.success && d.conflict) {
    d = await resolveActiveConflict(d, { action: 'toggle', id });
    if (!d) return;
  }
  if (!d.success) { toast(d.error || '操作失败', true); return; }
  if (d.paused_id) toast('已暂停另一个进行中的活动');
  loadAll();
}
async function deleteCampaign(id) {
  if (!confirm('删除活动会同时删除其奖品配置，确定删除？')) return;
  const d = await api({ action: 'delete', id });
  if (!d.success) { toast(d.error || '删除失败', true); return; }
  toast('已删除'); loadAll();
}

// ===== 奖品 =====
async function openPrizes(campaignId) {
  const c = LT.find(x => x.id === campaignId);
  $('ltPrizeCampId').value = campaignId;
  $('ltPrizeCampName').textContent = c ? '（' + c.name + '）' : '';
  await loadPrizes();
  show('ltPrizeModal');
}
async function loadPrizes() {
  const cid = parseInt($('ltPrizeCampId').value) || 0;
  const d = await api({ action: 'prizes', campaign_id: cid });
  if (!d.success) { toast(d.error || '加载奖品失败', true); return; }
  LT_PRIZES = d.prizes || [];
  LT_SEGMENTS = d.segments || [];
  renderPrizes();
}
// 转盘预览：直接展示服务端生成的扇区，配置完就知道收银台会显示几个
function renderWheelPreview() {
  const box = $('ltPrizePreview');
  if (!box) return;
  if (!LT_PRIZES.length) { box.innerHTML = ''; return; }
  const segs = LT_SEGMENTS || [];
  const sum = Math.max(100, segs.reduce((a, sg) => a + (parseInt(sg.weight) || 0), 0));
  const chips = segs.map(sg => {
    const pct = ((parseInt(sg.weight) || 0) / sum * 100).toFixed(1);
    const tag = sg.type === 'none' ? '未中奖' : (parseInt(sg.guarantee) ? '保底' : '');
    return `<span class="lt-apx-seg">${esc(sg.name)} ${pct}%${tag ? ` · ${tag}` : ''}</span>`;
  }).join('');
  const off = LT_PRIZES.filter(p => !parseInt(p.on_wheel))
    .map(p => `${esc(p.name)}（${esc(p.off_why || '未进入转盘')}）`);
  let html = `<div class="lt-apx-ttl">收银台转盘实际扇区：${segs.length} 个</div>`;
  html += segs.length ? `<div>${chips}</div>` : '<div class="lt-apx-off">当前没有可用扇区，收银台抽奖不可用</div>';
  if (segs.length === 1 && segs[0].type !== 'none') html += '<div class="lt-apx-off">只有 1 个扇区 = 每抽必中该奖品，建议补充其它奖品或调整权重</div>';
  if (off.length) html += `<div class="lt-apx-off">未上转盘：${off.join('；')}</div>`;
  box.innerHTML = html;
}
function renderPrizes() {
  const total = LT_PRIZES.filter(p => parseInt(p.status)).reduce((a, p) => a + (parseInt(p.weight) || 0), 0);
  const scale = Math.max(100, total);
  const camp = LT.find(c => String(c.id) === String($('ltPrizeCampId').value));
  const gid = camp ? parseInt(camp.guarantee_prize_id) || 0 : 0;
  const left = Math.max(0, 100 - total);
  const g = gid ? LT_PRIZES.find(p => parseInt(p.id) === gid) : null;
  const leftTxt = g
    ? `保底奖品「${esc(g.name)}」承接 ${left}（约 ${(left / scale * 100).toFixed(1)}%）`
    : `未中奖 ${left}（约 ${(left / scale * 100).toFixed(1)}%）`;
  $('ltPrizeSummary').innerHTML = `启用奖品权重合计 ${total}，${leftTxt}`;
  renderWheelPreview();
  // 概率按服务端真实扇区显示：权重为 0 / 库存不足 / 券结束的奖品不会上转盘
  const segSum = Math.max(100, (LT_SEGMENTS || []).reduce((a, sg) => a + (parseInt(sg.weight) || 0), 0));
  const segW = {};
  (LT_SEGMENTS || []).forEach(sg => { if (parseInt(sg.id)) segW[parseInt(sg.id)] = parseInt(sg.weight) || 0; });
  if (!LT_PRIZES.length) {
    $('ltPrizeRows').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary)">还没有奖品，点「新增奖品」添加</td></tr>';
    return;
  }
  $('ltPrizeRows').innerHTML = LT_PRIZES.map(p => {
    const on = parseInt(p.status) ? true : false;
    const w = parseInt(p.weight) || 0;
    const pid = parseInt(p.id);
    const onWheel = on && Object.prototype.hasOwnProperty.call(segW, pid);
    const pct = segSum > 0 ? (segW[pid] / segSum * 100) : 0;
    let cfg = '';
    if (p.prize_type === 'coupon') {
      const c = LT_OPT.coupons.find(x => String(x.id) === String(p.coupon_campaign_id));
      cfg = c ? esc(c.name) : `券活动 #${p.coupon_campaign_id}`;
    } else if (p.prize_type === 'product') {
      const prod = LT_OPT.products.find(x => String(x.id) === String(p.product_id));
      const sku = prod ? (prod.skus || []).find(s => s.condition_type === p.condition_type) : null;
      cfg = `${esc(prod ? prod.name : ('商品#' + p.product_id))} · ${esc(sku ? sku.cond_name : p.condition_type)} × ${p.qty}${sku ? `（可售 ${sku.stock}）` : ''}`;
    } else if (p.prize_type === 'custom') {
      cfg = esc(p.custom_note || '');
    } else if (p.prize_type === 'spin_again') {
      cfg = '中奖后追加一次机会';
    } else {
      cfg = '谢谢参与';
    }
    return `<tr class="${on ? '' : 'lt-row-dis'}">
      <td><b>${esc(p.name)}</b></td>
      <td><span class="lt-badge lt-type">${PRIZE_TYPES[p.prize_type] || p.prize_type}</span></td>
      <td class="num">${w}</td>
      <td class="num">${on ? (onWheel ? pct.toFixed(1) + '%' : `<span class="lt-badge lt-warn" title="${esc(p.off_why || '未进入转盘')}">不上转盘</span>`) : '-'}</td>
      <td class="num">${parseInt(p.quota) > 0 ? parseInt(p.quota) : '不限'}</td>
      <td class="num">${parseInt(p.won) || 0}</td>
      <td class="lt-muted">${cfg}</td>
      <td><span class="lt-badge ${on ? 'lt-on' : 'lt-off'}">${on ? '启用' : '停用'}</span></td>
      <td><div class="lt-acts">
        <button class="btn btn-sm btn-secondary" onclick="editPrize(${p.id})">编辑</button>
        <button class="btn btn-sm btn-secondary" onclick="togglePrize(${p.id})">${on ? '停用' : '启用'}</button>
        <button class="btn btn-sm btn-outline" onclick="deletePrize(${p.id})">删除</button>
      </div></td>
    </tr>`;
  }).join('');
}
function onPrizeTypeChange() {
  const t = $('ltPrType').value;
  $('ltPrCouponGroup').style.display = t === 'coupon' ? '' : 'none';
  $('ltPrProductGroup').style.display = t === 'product' ? '' : 'none';
  $('ltPrNoteGroup').style.display = t === 'custom' ? '' : 'none';
}
function renderProductConds() {
  const pid = parseInt($('ltPrProduct').value) || 0;
  const p = LT_OPT.products.find(x => x.id === pid);
  const sel = $('ltPrCond');
  sel.innerHTML = p ? (p.skus || []).map(s => `<option value="${esc(s.condition_type)}">${esc(s.cond_name)}（可售 ${s.stock}）</option>`).join('') : '<option value="">-</option>';
}
function editPrize(id) {
  const p = LT_PRIZES.find(x => x.id === id) || null;
  $('ltPrizeEditTitle').textContent = p ? '编辑奖品' : '新增奖品';
  $('ltPrId').value = p ? p.id : '';
  $('ltPrName').value = p ? (p.name || '') : '';
  $('ltPrType').value = p ? p.prize_type : 'coupon';
  $('ltPrWeight').value = p ? (parseInt(p.weight) || 0) : 10;
  $('ltPrQuota').value = p ? (parseInt(p.quota) || 0) : 0;
  $('ltPrQty').value = p ? (parseInt(p.qty) || 1) : 1;
  $('ltPrNote').value = p ? (p.custom_note || '') : '';
  $('ltPrSort').value = p ? (parseInt(p.sort_order) || 0) : 0;
  $('ltPrStatus').value = p && !parseInt(p.status) ? '0' : '1';
  if (p && p.coupon_campaign_id) $('ltPrCoupon').value = p.coupon_campaign_id;
  if (p && p.product_id) $('ltPrProduct').value = p.product_id;
  renderProductConds();
  if (p && p.condition_type) $('ltPrCond').value = p.condition_type;
  onPrizeTypeChange();
  show('ltPrizeEditModal');
}
async function savePrize() {
  const body = {
    action: 'save_prize',
    campaign_id: parseInt($('ltPrizeCampId').value) || 0,
    id: parseInt($('ltPrId').value) || 0,
    name: ($('ltPrName').value || '').trim(),
    prize_type: $('ltPrType').value,
    weight: parseInt($('ltPrWeight').value) || 0,
    quota: parseInt($('ltPrQuota').value) || 0,
    coupon_campaign_id: parseInt($('ltPrCoupon').value) || 0,
    product_id: parseInt($('ltPrProduct').value) || 0,
    condition_type: $('ltPrCond').value || '',
    qty: parseInt($('ltPrQty').value) || 1,
    custom_note: ($('ltPrNote').value || '').trim(),
    sort_order: parseInt($('ltPrSort').value) || 0,
    status: parseInt($('ltPrStatus').value) || 0,
  };
  if (!body.name) { toast('请填写奖品名称', true); return; }
  if (parseInt(body.status) && !(parseInt(body.weight) > 0)) {
    if (!confirm('该奖品权重为 0，不会出现在收银台转盘上（也不可能中奖）。仍要保存吗？')) return;
  }
  const d = await api(body);
  if (!d.success) { toast(d.error || '保存失败', true); return; }
  hide('ltPrizeEditModal'); toast('已保存'); loadPrizes();
}
// 把启用奖品权重平均分配（合计 100），避免出现「只配了 1 个上转盘」
async function evenWeights() {
  const act = LT_PRIZES.filter(p => parseInt(p.status));
  if (!act.length) { toast('没有启用的奖品', true); return; }
  if (!confirm(`把 ${act.length} 个启用奖品权重平均分配（合计 100），继续？`)) return;
  const base = Math.floor(100 / act.length);
  let rest = 100 - base * act.length;
  for (const p of act) {
    const w = base + (rest > 0 ? 1 : 0);
    if (rest > 0) rest--;
    const d = await api({
      action: 'save_prize',
      campaign_id: parseInt($('ltPrizeCampId').value) || 0,
      id: p.id,
      name: p.name,
      prize_type: p.prize_type,
      weight: w,
      quota: p.quota,
      coupon_campaign_id: p.coupon_campaign_id,
      product_id: p.product_id,
      condition_type: p.condition_type,
      qty: p.qty,
      custom_note: p.custom_note,
      sort_order: p.sort_order,
      status: p.status,
    });
    if (!d.success) { toast(d.error || '分配失败', true); return; }
  }
  toast('已平均分配权重');
  loadPrizes();
}
async function togglePrize(id) {
  const d = await api({ action: 'toggle_prize', id });
  if (!d.success) { toast(d.error || '操作失败', true); return; }
  loadPrizes();
}
async function deletePrize(id) {
  if (!confirm('确定删除该奖品配置？')) return;
  const d = await api({ action: 'delete_prize', id });
  if (!d.success) { toast(d.error || '删除失败', true); return; }
  toast('已删除'); loadPrizes();
}
async function voidDraw(id) {
  if (!confirm('作废后：未使用的券收回、奖品出库单作废、中出配额回退，确定？')) return;
  const d = await api({ action: 'void_draw', id });
  if (!d.success) { toast(d.error || '作废失败', true); return; }
  toast('已作废'); loadDraws(); loadAll();
}

async function refreshAll() {
  await loadOptions();
  await loadAll();
}

(async function init() {
  await refreshAll();
})();
</script>
