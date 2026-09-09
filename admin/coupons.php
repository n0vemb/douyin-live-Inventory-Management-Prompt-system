<?php
$pageTitle = '优惠券';
$currentPage = 'coupons';
require_once __DIR__ . '/layout.php';
$couponAllowed = in_array($currentUser['role'] ?? '', ['store_admin', 'group_admin', 'super_admin'], true);
$couponPickShop = in_array($currentUser['role'] ?? '', ['group_admin', 'super_admin'], true);
?>
<div class="page-title">优惠券 <span class="sub" style="font-size:12px;color:var(--text-tertiary);font-weight:500">后台配置活动 · 公开页领券 · 收银台输入手机号自动核销</span></div>
<?php if (!$couponAllowed || !$storeId): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--text-tertiary)"><?= $couponAllowed ? '请先选择店铺' : '无权限' ?></div>
<?php exit; endif; ?>

<style>
  .cp-toolbar{display:flex;gap:10px;align-items:center;margin-bottom:12px}
  .cp-toolbar input{flex:1;min-width:180px;height:36px;padding:0 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-body);color:var(--text)}
  .cp-tbl{width:100%;border-collapse:collapse;font-size:13px}
  .cp-tbl th,.cp-tbl td{padding:9px 10px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
  .cp-tbl th{background:var(--bg-hover);color:var(--text-secondary);font-size:12px;white-space:nowrap}
  .cp-badge{display:inline-block;font-size:11px;font-weight:700;padding:1px 8px;border-radius:8px}
  .cp-on{background:rgba(62,207,142,.15);color:#2fa375}
  .cp-off{background:rgba(148,163,184,.15);color:#64748b}
  .cp-link{font-size:12px;color:var(--primary);word-break:break-all}
</style>

<div class="cp-toolbar">
  <input id="cpQ" placeholder="搜索活动名称…" oninput="render()">
  <?php if ($couponPickShop): ?>
  <select id="cpShop" style="height:36px;border:1px solid var(--border);border-radius:8px;background:var(--bg-body);color:var(--text);padding:0 8px;" onchange="load()"></select>
  <?php endif; ?>
  <button class="btn btn-primary" onclick="editCampaign(null)">+ 新建活动</button>
</div>
<div class="card">
  <div style="overflow-x:auto"><table class="cp-tbl">
    <thead><tr><th>活动</th><th>类型</th><th>优惠</th><th>发行/已领</th><th>使用中</th><th>限领</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead>
    <tbody id="cpRows"><tr><td colspan="9" style="text-align:center;color:var(--text-tertiary)">加载中…</td></tr></tbody>
  </table></div>
</div>

<!-- 编辑弹窗 -->
<div class="modal" id="cpModal">
  <div class="modal-content" style="max-width:620px">
    <div class="modal-header"><h3 class="modal-title" id="cpModalTitle">新建活动</h3><button class="modal-close" onclick="hide('cpModal')">&times;</button></div>
    <div style="display:flex;flex-direction:column;gap:12px">
      <input type="hidden" id="cpId">
      <div class="form-row">
        <div class="form-group" style="flex:2"><label class="form-label">活动名称 *</label><input class="form-input" id="cpName" placeholder="如：开业满100减20"></div>
        <div class="form-group"><label class="form-label">类型</label><select class="form-input" id="cpType" onchange="toggleThreshold()"><option value="threshold">满减</option><option value="fixed">无门槛立减</option></select></div>
      </div>
      <?php if ($couponPickShop): ?>
      <div class="form-row">
        <div class="form-group" style="flex:1"><label class="form-label">归属店铺 *</label><select class="form-input" id="cpShopNew"></select></div>
      </div>
      <?php endif; ?>
      <div class="form-row">
        <div class="form-group" id="cpThrGroup"><label class="form-label">满减门槛 ¥</label><input class="form-input" type="number" min="0" step="0.01" id="cpThreshold" value="0"></div>
        <div class="form-group"><label class="form-label">优惠金额 ¥ *</label><input class="form-input" type="number" min="0.01" step="0.01" id="cpAmount"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">总发行量（0=不限）</label><input class="form-input" type="number" min="0" id="cpTotal" value="0"></div>
        <div class="form-group"><label class="form-label">每人限领</label><input class="form-input" type="number" min="1" id="cpPer" value="1"></div>
      </div>
      <div class="form-row">
        <div class="form-group" style="flex:1"><label class="form-label">开始日期（当天 0 点生效）</label><input class="form-input" type="date" id="cpStartDate" onchange="renderEndPreview()"></div>
        <div class="form-group" style="flex:1.6">
          <label class="form-label">有效天数（含首尾）</label>
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <input class="form-input" type="number" min="1" id="cpDur" style="width:76px" oninput="renderEndPreview()">
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDur(7)">7天</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDur(15)">15天</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDur(30)">30天</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDur(90)">90天</button>
          </div>
        </div>
      </div>
      <div id="cpEndPreview" style="font-size:12px;color:var(--text-tertiary)"></div>
      <input type="hidden" id="cpStart"><input type="hidden" id="cpEnd">
      <label style="display:flex;gap:6px;font-size:13px;color:var(--text-secondary)"><input type="checkbox" id="cpStack"> 允许与其它券叠加（每单同一活动最多 1 张）</label>
      <div class="form-group"><label class="form-label">备注</label><input class="form-input" id="cpRemark" placeholder="选填"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-secondary" onclick="hide('cpModal')">取消</button>
        <button class="btn btn-primary" onclick="saveCampaign()">保存</button>
      </div>
    </div>
  </div>
</div>

<!-- 补发弹窗 -->
<div class="modal" id="cpIssueModal">
  <div class="modal-content" style="max-width:420px">
    <div class="modal-header"><h3 class="modal-title">手动补发</h3><button class="modal-close" onclick="hide('cpIssueModal')">&times;</button></div>
    <input type="hidden" id="issueCampaignId">
    <div style="display:flex;flex-direction:column;gap:12px">
      <div><b id="issueCampaignName" style="font-size:14px"></b></div>
      <div class="form-group"><label class="form-label">手机号</label><input class="form-input" id="issuePhone" type="tel" maxlength="11"></div>
      <div class="form-group"><label class="form-label">张数</label><input class="form-input" id="issueQty" type="number" min="1" max="20" value="1"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button class="btn btn-secondary" onclick="hide('cpIssueModal')">取消</button>
        <button class="btn btn-primary" onclick="doIssue()">补发</button>
      </div>
    </div>
  </div>
</div>

<div id="cpToast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;z-index:3000;display:none"></div>
<script>
let CP = [];
const CP_PICK = <?= $couponPickShop ? 'true' : 'false' ?>;
const $c = id => document.getElementById(id);
function toast(m, err){ const t=$c('cpToast'); t.textContent=m; t.style.background=err?'#b3261e':'#111827'; t.style.display='block'; clearTimeout(t._t); t._t=setTimeout(()=>t.style.display='none',2200); }
function hide(id){ $c(id).classList.remove('show'); }
function show(id){ $c(id).classList.add('show'); }
function esc(s){ return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
async function api(body){
  const res=await fetch('../api/coupon_admin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  return res.json();
}
async function load(){
  const body={action:'list'};
  const shopSel=$c('cpShop');
  if (CP_PICK && shopSel && shopSel.value) body.shop_id=parseInt(shopSel.value);
  const d=await api(body); if(!d.success){toast(d.error||'加载失败',true);return;}
  CP=d.campaigns||[]; render();
}
function typeTxt(t,th){ return t==='fixed' ? '无门槛' : '满'+fmt(th)+'可用'; }
function fmt(n){ return Number(n||0).toFixed(2).replace(/\.00$/,''); }
function statTxt(cp){
  if(cp.status!=='active')return '<span class="cp-badge cp-off">停用</span>';
  return '<span class="cp-badge cp-on">进行中</span>';
}
function render(){
  const q=($c('cpQ').value||'').trim().toLowerCase();
  const list=CP.filter(c=>!q||(c.name||'').toLowerCase().includes(q));
  if(!list.length){$c('cpRows').innerHTML='<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary)">暂无活动</td></tr>';return;}
  $c('cpRows').innerHTML=list.map(c=>{
    const link=location.origin + '/coupon_claim.php?token=' + c.claim_token;
    return `<tr>
      <td>${c.shop_name ? `<span style="font-size:11px;color:var(--primary);border:1px solid var(--border);border-radius:10px;padding:0 6px;margin-right:4px;">${esc(c.shop_name)}</span>` : ''}<b>${esc(c.name)}</b><br><span class="cp-link">${esc(link)}</span></td>
      <td>${typeTxt(c.coupon_type,c.threshold)}</td>
      <td>¥${fmt(c.amount)}</td>
      <td>${c.total_count>0?c.total_count:'不限'} / ${c.issued}</td>
      <td>${c.in_use||0}</td>
      <td>${c.per_user}</td>
      <td style="white-space:nowrap">${esc((c.start_at||'')+' ~ '+(c.end_at||'长期'))}</td>
      <td>${statTxt(c)}</td>
      <td style="white-space:nowrap">
        <button class="btn btn-sm btn-secondary" onclick="editCampaign(${c.id})">编辑</button>
        <button class="btn btn-sm btn-secondary" onclick="toggleCampaign(${c.id})">${c.status==='active'?'停用':'启用'}</button>
        <button class="btn btn-sm btn-secondary" onclick="issueOpen(${c.id})">补发</button>
      </td></tr>`;
  }).join('');
}
function toggleThreshold(){ $c('cpThrGroup').style.display=$c('cpType').value==='fixed'?'none':''; }
function setDur(n){ $c('cpDur').value=n; renderEndPreview(); }
function renderEndPreview(){
  const sd=$c('cpStartDate').value;
  const dur=Math.max(1, parseInt($c('cpDur').value)||0);
  const pre=$c('cpEndPreview');
  if(!sd){ pre.textContent=''; return; }
  const start=new Date(sd+'T00:00:00');
  const end=new Date(start.getTime()+(dur-1)*86400000);
  const p=n=>String(n).padStart(2,'0');
  const f=d=>d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate());
  $c('cpStart').value=sd+' 00:00:00';
  $c('cpEnd').value=f(end)+' 23:59:59';
  pre.textContent='有效期：'+sd+' 00:00 ~ '+f(end)+' 23:59（共 '+dur+' 天）';
}
function editCampaign(id){
  const c=id?CP.find(x=>x.id===id):null;
  $c('cpModalTitle').textContent=c?'编辑活动':'新建活动';
  $c('cpId').value=c?c.id:'';
  $c('cpName').value=c?c.name:'';
  $c('cpType').value=c?c.coupon_type:'threshold';
  $c('cpThreshold').value=c?c.threshold:0;
  $c('cpAmount').value=c?c.amount:'';
  $c('cpTotal').value=c?c.total_count:0;
  $c('cpPer').value=c?c.per_user:1;
  $c('cpStack').checked=c?!!+c.stackable:false;
  if(c && c.start_at && c.end_at){
    const sd=(c.start_at||'').slice(0,10);
    const ms=new Date(c.end_at.replace(' ','T')).getTime()-new Date(c.start_at.replace(' ','T')).getTime();
    const dur=Math.max(1, Math.ceil(ms/86400000));
    $c('cpStartDate').value=sd;
    $c('cpDur').value=dur;
  } else {
    const today=new Date(); const p=n=>String(n).padStart(2,'0');
    $c('cpStartDate').value=today.getFullYear()+'-'+p(today.getMonth()+1)+'-'+p(today.getDate());
  $c('cpDur').value=30;
  }
  $c('cpRemark').value=c?(c.remark||''):'';
  if (CP_PICK) {
    const shopSel=$c('cpShopNew');
    if (shopSel && c && c.shop_id) shopSel.value=c.shop_id;
  }
  toggleThreshold(); renderEndPreview(); show('cpModal');
}
async function saveCampaign(){
  renderEndPreview();
  const body={action:'save',id:+$c('cpId').value||0,name:$c('cpName').value.trim(),coupon_type:$c('cpType').value,
    threshold:+$c('cpThreshold').value||0,amount:+$c('cpAmount').value||0,total_count:+$c('cpTotal').value||0,
    per_user:+$c('cpPer').value||1,stackable:$c('cpStack').checked?1:0,
    start_at:$c('cpStart').value||'',
    end_at:$c('cpEnd').value||'',
    remark:$c('cpRemark').value.trim()};
  const shopSel=CP_PICK?$c('cpShopNew'):null;
  if (shopSel) {
    body.shop_id=parseInt(shopSel.value)||0;
    if (!body.id && !body.shop_id) { toast('请选择归属店铺',true); return; }
  }
  if(!body.name){toast('请填写活动名称',true);return;}
  if(!(body.amount>0)){toast('请填写优惠金额',true);return;}
  if(!body.start_at || !body.end_at){toast('请选择开始日期和有效天数',true);return;}
  const d=await api(body); if(!d.success){toast(d.error||'保存失败',true);return;}
  hide('cpModal'); toast('已保存'); load();
}
async function toggleCampaign(id){
  const c=CP.find(x=>x.id===id); if(!c)return;
  const d=await api({action:'toggle',id,status:c.status==='active'?'paused':'active'});
  if(!d.success){toast(d.error||'操作失败',true);return;} load();
}
function issueOpen(id){ const c=CP.find(x=>x.id===id); if(!c)return; $c('issueCampaignId').value=c.id; $c('issueCampaignName').textContent=c.name+'（¥'+fmt(c.amount)+'）'; $c('issuePhone').value=''; $c('issueQty').value=1; show('cpIssueModal'); }
async function doIssue(){
  const body={action:'manual_issue',campaign_id:+$c('issueCampaignId').value,phone:$c('issuePhone').value.trim(),qty:+$c('issueQty').value||1};
  if(!/^1[3-9]\d{9}$/.test(body.phone)){toast('手机号格式不正确',true);return;}
  const d=await api(body); if(!d.success){toast(d.error||'补发失败',true);return;}
  hide('cpIssueModal'); toast(d.message||'已补发'); load();
}
if (CP_PICK) {
  fetch('../api/list_shops.php').then(r=>r.json()).then(d=>{
    const shops=(d.data&&d.data.shops)||[];
    const sel=$c('cpShop'); const selNew=$c('cpShopNew');
    if (sel) sel.innerHTML='<option value="">全部店</option>'+shops.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
    if (selNew) selNew.innerHTML=shops.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
  }).catch(()=>{});
}
load();
</script>
