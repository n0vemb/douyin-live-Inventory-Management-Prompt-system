<?php
/**
 * roles_matrix_preview.php — 角色×页面×功能 两级权限原型（只读演示，不保存不生效）
 * 访问：/admin/roles_matrix_preview.php
 */
$pageTitle = '角色权限原型';
$currentPage = 'roles_preview';
require_once __DIR__ . '/layout.php';
$canView = in_array($currentUser['role'] ?? '', ['super_admin', 'store_admin'], true);
?>
<div class="page-title">角色权限原型 <span class="sub" style="font-size:12px;color:var(--text-tertiary);font-weight:500">两级勾选演示：角色 → 页面 → 页面内功能（当前为演示，不会真实保存）</span></div>
<?php if (!$canView): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--text-tertiary)">仅店管/超管可查看原型</div>
<?php exit; endif; ?>

<style>
  .rp-tip{background:rgba(240,180,41,.08);border:1px solid rgba(240,180,41,.3);color:#d9a514;font-size:12.5px;padding:9px 12px;border-radius:8px;margin-bottom:14px}
  .rp-rolebar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
  .rp-role{background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:8px 12px;font-size:13px;color:var(--text);cursor:pointer}
  .rp-role.on{border-color:var(--primary);color:var(--primary);font-weight:700}
  .rp-role .n{color:var(--text-tertiary);font-size:11px;display:block}
  .rp-mod{margin-bottom:18px}
  .rp-mod-title{font-weight:800;font-size:14px;color:var(--text);margin:0 0 6px;display:flex;gap:8px;align-items:center}
  .rp-page{border:1px solid var(--border);border-radius:10px;background:var(--bg-surface);margin-bottom:6px;overflow:hidden}
  .rp-page-head{display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer}
  .rp-page-head:hover{background:var(--bg-hover)}
  .rp-page-head .pn{flex:1;font-size:13.5px;font-weight:700;color:var(--text)}
  .rp-page-head .pf{font-size:11px;color:var(--text-tertiary)}
  .rp-funcs{display:flex;flex-wrap:wrap;gap:4px 14px;padding:2px 12px 10px 38px;border-top:1px dashed var(--border);margin-top:2px}
  .rp-funcs label{font-size:12.5px;color:var(--text-secondary);display:inline-flex;gap:5px;align-items:center;cursor:pointer}
  .rp-page-head input[type=checkbox],.rp-funcs input[type=checkbox]{accent-color:var(--primary);width:15px;height:15px}
  .rp-empty{color:var(--text-tertiary);font-size:13px;text-align:center;padding:30px}
  .rp-count{font-size:12px;color:var(--text-tertiary)}
</style>

<div class="rp-tip">📋 原型说明：左边点角色查看默认权限；页面可展开勾选页面内功能；可临时新增“自定义角色”演示。这里只改页面状态，<b>不会写入系统</b>。</div>

<div class="rp-rolebar" id="rpRoleBar"></div>
<div id="rpCustomBar" style="display:none;margin-bottom:12px;display:flex;gap:8px;align-items:center">
  <input id="rpCustomName" class="form-input" style="max-width:220px" placeholder="自定义角色名">
  <button class="btn btn-sm btn-primary" onclick="rpAddCustom()">+ 添加角色</button>
</div>
<div id="rpMatrix"></div>

<script>
// 权限字典（原型用；四块详细，其余页面级）
const MODULES = [
  { id:'product', name:'商品管理', pages:[
    { id:'p_list', name:'商品列表/详情/搜索', funcs:['查看在库/售价/均价','查看出入库与销售记录'] },
    { id:'p_edit', name:'新建/编辑商品', funcs:['基础信息编辑','SKU/品相管理'] },
    { id:'p_in', name:'入库/批次', funcs:['入库','批次数量编辑','批次进价售价编辑'] },
    { id:'p_convert', name:'SKU 转换', funcs:['状态转换'] },
    { id:'p_price', name:'改价', funcs:['批量改价','线下售价设置'] },
    { id:'p_delete', name:'删除商品', funcs:['单个删除','批量删除'] },
    { id:'p_export', name:'导出库存', funcs:['导出CSV'] },
    { id:'p_audit', name:'库存盘点', funcs:['商品页盘点','货架盘点'] }
  ]},
  { id:'live', name:'直播出库记账', pages:[
    { id:'l_session', name:'场次管理', funcs:['新建场次','删除场次','修改主播/运营/账号'] },
    { id:'l_ledger', name:'场次内记账', funcs:['增删客户','添加商品/速录','改价','撤单/退货','运费补偿'] },
    { id:'l_end', name:'下播/结束出库', funcs:['下播','打包出库'] },
    { id:'l_view', name:'查看历史/返送', funcs:['直播账本历史','直播返送屏'] }
  ]},
  { id:'rack', name:'仓库货架', pages:[
    { id:'r_view', name:'货架分布/未在货架', funcs:['查看'] },
    { id:'r_manage', name:'货架管理', funcs:['新增/更名/删除货架','布局层数格数'] },
    { id:'r_put', name:'录入/移动商品', funcs:['录入','拖拽替换','移除'] },
    { id:'r_audit', name:'货架盘点', funcs:['盘点模式','提交差异'] }
  ]},
  { id:'pos', name:'线下收银/门店', pages:[
    { id:'po_view', name:'门店待出库列表', funcs:['查看订单','查看成本/进价'] },
    { id:'po_do', name:'出库操作', funcs:['出库','整单作废','删除商品','彻底删除订单'] },
    { id:'po_report', name:'线下销售报表', funcs:['查看','导出'] }
  ]},
  { id:'misc', name:'其它页面（页面级）', pages:[
    { id:'m_overview', name:'首页概览', funcs:['查看待办/统计'] },
    { id:'m_sales', name:'销售记录', funcs:['查看销售流水'] },
    { id:'m_vip', name:'客户管理(VIP)', funcs:['查看/编辑VIP'] },
    { id:'m_label', name:'标签打印台', funcs:['打印标签'] },
    { id:'m_coupon', name:'优惠券', funcs:['查看','配置活动','手动补发'] },
    { id:'m_finance', name:'财务/成本', funcs:['查看成本利润'] },
    { id:'m_settings', name:'店铺设置', funcs:['设置'] },
    { id:'m_users', name:'用户/角色管理', funcs:['管理账号','权限配置'] }
  ]}
];

// 角色默认权限（true=勾选；未列功能默认与页面一致）
const ROLES = [
  { id:'super_admin', name:'超管', note:'全部' },
  { id:'store_admin', name:'店管', note:'全部（仅本店）' },
  { id:'deputy_store_admin', name:'副店长', note:'运营能力 + 盘点' },
  { id:'operator', name:'运营', note:'无成本/财务/删除/盘点/导出' },
  { id:'warehouse', name:'仓库', note:'仅仓库出库台' }
];
const DEFAULT_TRUE_ROLES = ['super_admin','store_admin'];
const DEFAULT_OP = ['deputy_store_admin','operator'];
const AUDIT = ['p_audit','r_audit'];
const EXCLUDE_OP = ['p_delete','p_export','p_price.p_offline','m_finance','m_settings','m_users','po_report','l_session.delete'];
let state = {};
let roles = ROLES.map(r=>({id:r.id,name:r.name,note:r.note}));
function $(id){return document.getElementById(id);}

function allIds(){const a=[];MODULES.forEach(m=>m.pages.forEach(p=>{a.push(p.id);(p.funcs||[]).forEach((f,i)=>a.push(p.id+'|'+i));}));return a;}
function roleDefaults(rid){
  if(DEFAULT_TRUE_ROLES.includes(rid)){const o={};allIds().forEach(k=>o[k]=true);return o;}
  if(rid==='warehouse'){const o={};o.m_overview=true;o.po_view=true;o.po_do=true;return o;}
  const o={};
  MODULES.forEach(m=>m.pages.forEach(p=>{
    const allowed = (rid==='deputy_store_admin'||rid==='operator') &&
      !['p_delete','p_export','m_finance','m_settings','m_users'].includes(p.id);
    o[p.id]=allowed;
    (p.funcs||[]).forEach((f,i)=>{
      const k=p.id+'|'+i;
      const deny = p.id==='p_price' && i===1; // 线下售价
      const deny2 = p.id==='l_session' && i===2; // 主播/运营/账号修改
      const deny3 = p.id==='l_view' && i===1;
      o[k] = allowed && !deny && !deny2 && !deny3;
    });
  }));
  if(rid==='deputy_store_admin'){AUDIT.forEach(k=>o[k]=true);o['p_audit|1']=true;o['r_audit|0']=true;o['r_audit|1']=true;}
  return o;
}
function saveState(){try{localStorage.setItem('ppmart_role_proto',JSON.stringify({roles:roles.map(r=>r.id),state}));}catch(e){}}
function loadState(){try{const s=JSON.parse(localStorage.getItem('ppmart_role_proto')||'null');if(s&&s.roles){roles=s.roles.map(id=>{const d=ROLES.find(r=>r.id===id);return {id,name:d?d.name:id,note:d?d.note:'自定义'};});state=s.state||{};}}catch(e){}}
function initRoleState(rid){if(!state[rid])state[rid]=roleDefaults(rid);}
function curRole(){const el=document.querySelector('.rp-role.on');return el?el.dataset.id:null;}
function toggleRole(el){document.querySelectorAll('.rp-role').forEach(x=>x.classList.remove('on'));el.classList.add('on');render();}
function rpAddCustom(){
  const name=($('rpCustomName')||{}).value;if(!name){alert('请输入角色名');return;}
  const id='custom'+Date.now();
  roles.push({id,name,note:'自定义'});state[id]=roleDefaults('operator');
  render();
}
function renderRoleBar(){
  const bar=$('rpRoleBar');
  bar.innerHTML=roles.map((r,i)=>`<div class="rp-role ${i===0?'on':''}" data-id="${r.id}" onclick="toggleRole(this)">${r.name}<span class="n">${r.note||'自定义'}</span></div>`).join('');
  if(!curRole())render();
}
function pageChecked(rid,pid){return !!state[rid][pid];}
function funcChecked(rid,k){return !!state[rid][k];}
function setPage(rid,pid,on){
  state[rid][pid]=on;
  MODULES.forEach(m=>m.pages.forEach(p=>{if(p.id===pid)(p.funcs||[]).forEach((f,i)=>state[rid][pid+'|'+i]=on);}));
  render();
}
function setFunc(rid,pid,i,on){state[rid][pid+'|'+i]=on;render();}
function render(){
  const rid=curRole()||roles[0].id;
  document.querySelectorAll('.rp-role').forEach(x=>x.classList.toggle('on',x.dataset.id===rid));
  const box=$('rpMatrix');
  if(!state[rid])state[rid]=roleDefaults(rid);
  box.innerHTML=MODULES.map(mod=>{
    const onCount=mod.pages.filter(p=>pageChecked(rid,p.id)).length;
    return `<div class="rp-mod"><div class="rp-mod-title">${mod.name}<span class="rp-count">${onCount}/${mod.pages.length} 页面</span></div>`+
      mod.pages.map(p=>{
        const on=pageChecked(rid,p.id);
        const funcOn=(p.funcs||[]).filter((f,i)=>funcChecked(rid,p.id+'|'+i)).length;
        return `<div class="rp-page"><div class="rp-page-head" onclick="event.target.closest('input')||setPage('${rid}','${p.id}',!${on})">
          <input type="checkbox" ${on?'checked':''} onclick="event.stopPropagation();setPage('${rid}','${p.id}',this.checked)">
          <span class="pn">${p.name}</span><span class="pf">${p.funcs?funcOn+'/'+p.funcs.length+' 功能':'页面级'}</span>
        </div>`+(p.funcs?`<div class="rp-funcs">${p.funcs.map((f,i)=>`<label><input type="checkbox" ${funcChecked(rid,p.id+'|'+i)?'checked':''} onclick="setFunc('${rid}','${p.id}',${i},this.checked)">${f}</label>`).join('')}</div>`:'')+`</div>`;
      }).join('')+`</div>`;
  }).join('');
  saveState();
}
loadState();renderRoleBar();render();
</script>
