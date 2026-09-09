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
<div id="rpRoleDesc" style="font-size:12.5px;color:var(--text-secondary);background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:12px"></div>
<div id="rpCustomBar" style="display:none;margin-bottom:12px;display:flex;gap:8px;align-items:center">
  <input id="rpCustomName" class="form-input" style="max-width:220px" placeholder="自定义角色名">
  <button class="btn btn-sm btn-primary" onclick="rpAddCustom()">+ 添加角色</button>
</div>
<div id="rpMatrix"></div>

<script>
// 权限字典（原型用；四块详细，其余页面级）
const MODULES = [
  { id:'product', name:'商品管理', pages:[
    { id:'p_list', name:'商品列表/详情', funcs:[
      {n:'查看商品名称/条码/在库数量/最新售价', d:'不含成本'},
      {n:'查看 SKU 均价', d:'按在库数量加权的售价均价'},
      {n:'查看进价/库存成本/毛利', d:'涉及成本利润', cost:true},
      {n:'查看出入库/销售流水', d:'流水明细（不含成本）'},
      {n:'查看流水成本与单笔毛利', d:'涉及成本利润', cost:true}
    ]},
    { id:'p_edit', name:'新建/编辑商品', funcs:[
      {n:'编辑名称/系列/品牌/图片/参考价', d:'基础信息'},
      {n:'编辑 SKU/品相与售卖价', d:'售卖价'},
      {n:'编辑进价/成本', d:'涉及成本', cost:true}
    ]},
    { id:'p_in', name:'入库/批次', funcs:[
      {n:'新增入库（数量/进价/售价）', d:'进价即成本', cost:true},
      {n:'编辑批次数量', d:'数量调整'},
      {n:'编辑批次进价/售价', d:'涉及成本', cost:true}
    ]},
    { id:'p_convert', name:'SKU 转换', funcs:[{n:'状态转换（未拆→已拆等）', d:'数量转换'}] },
    { id:'p_price', name:'改价', funcs:[
      {n:'批量改售价', d:'不影响进价'},
      {n:'设置线下收银台售价', d:'收银台价格'},
      {n:'设置/查看利润相关参考', d:'涉及成本利润', cost:true}
    ]},
    { id:'p_delete', name:'删除商品', funcs:[{n:'删除/批量删除', d:'不可恢复'}] },
    { id:'p_export', name:'导出库存', funcs:[
      {n:'导出不含成本', d:'名称/数量/售价'},
      {n:'导出含进价成本', d:'涉及成本', cost:true}
    ]},
    { id:'p_audit', name:'库存盘点', funcs:[
      {n:'商品页盘点', d:'全店逐SKU'},
      {n:'仓库货架盘点', d:'逐格录入'},
      {n:'盘点时查看成本/毛利', d:'涉及成本利润', cost:true}
    ]}
  ]},
  { id:'live', name:'直播出库记账', pages:[
    { id:'l_session', name:'场次管理', funcs:[
      {n:'新建场次', d:'需填主播/运营'},
      {n:'删除场次', d:'不可恢复'},
      {n:'修改主播/运营/账号', d:'仅店管/超管'}
    ]},
    { id:'l_ledger', name:'场次内记账', funcs:[
      {n:'增删客户/添加商品/速录', d:'日常记账'},
      {n:'改价/撤单/退货/运费补偿', d:'涉及金额'},
      {n:'查看本场销售额/件数', d:'销售汇总'},
      {n:'查看本场成本/毛利', d:'涉及成本利润', cost:true}
    ]},
    { id:'l_end', name:'下播/结束出库', funcs:[{n:'下播', d:'记录时间'},{n:'打包出库', d:'扣真实库存'}] },
    { id:'l_view', name:'查看历史/返送', funcs:[{n:'直播账本历史', d:'只读'},{n:'直播返送屏', d:'主播屏'}] }
  ]},
  { id:'rack', name:'仓库货架', pages:[
    { id:'r_view', name:'货架分布/未在货架', funcs:['查看'] },
    { id:'r_manage', name:'货架管理', funcs:['新增/更名/删除货架','布局层数格数'] },
    { id:'r_put', name:'录入/移动商品', funcs:['录入','拖拽替换','移除'] },
    { id:'r_audit', name:'货架盘点', funcs:['盘点模式','提交差异'] }
  ]},
  { id:'pos', name:'线下收银/门店', pages:[
    { id:'po_view', name:'门店待出库列表', funcs:[
      {n:'查看订单/金额', d:'不含成本'},
      {n:'查看订单成本/进价', d:'涉及成本', cost:true}
    ]},
    { id:'po_do', name:'出库操作', funcs:[
      {n:'出库', d:'扣库存'},
      {n:'整单作废', d:'释放库存'},
      {n:'删除商品/彻底删除订单', d:'不可恢复'}
    ]},
    { id:'po_report', name:'线下销售报表', funcs:[
      {n:'查看销售额/单量', d:'销售统计'},
      {n:'查看成本/毛利/导出', d:'涉及成本利润', cost:true}
    ]}
  ]},
  { id:'misc', name:'其它页面（页面级）', pages:[
    { id:'m_overview', name:'首页概览', funcs:[{n:'查看待办/统计', d:'不含成本'},{n:'查看成本/利润概览', d:'涉及成本', cost:true}] },
    { id:'m_sales', name:'销售记录', funcs:[
      {n:'查看销售额流水', d:'销售明细'},
      {n:'查看单笔成本/毛利', d:'涉及成本利润', cost:true}
    ]},
    { id:'m_vip', name:'客户管理(VIP)', funcs:[{n:'查看/编辑VIP', d:'客户资料'},{n:'查看VIP消费金额', d:'消费统计'}] },
    { id:'m_label', name:'标签打印台', funcs:[{n:'打印标签', d:'含均价字段'},{n:'打印成本/毛利信息', d:'涉及成本', cost:true}] },
    { id:'m_coupon', name:'优惠券', funcs:[{n:'查看活动/领取记录', d:'只读'},{n:'配置活动', d:'增改停用'},{n:'手动补发', d:'需谨慎'}] },
    { id:'m_finance', name:'财务/成本', funcs:[{n:'查看销售额', d:'销售口径'},{n:'查看成本/利润/毛利', d:'核心财务', cost:true},{n:'导出财务', d:'含成本', cost:true}] },
    { id:'m_settings', name:'店铺设置', funcs:[{n:'店铺配置', d:'含价格比例/收银台'}] },
    { id:'m_users', name:'用户/角色管理', funcs:[{n:'管理账号', d:'新建/编辑/删除'},{n:'角色权限配置', d:'预留'}] }
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
      const fobj=typeof f==='string'?{n:f,cost:false}:f;
      const deny = (p.id==='p_price' && i===1) || (p.id==='l_session' && i===2);
      o[k] = allowed && !deny && !fobj.cost;
    });
  }));
  if(rid==='deputy_store_admin'){
    AUDIT.forEach(pid=>{
      MODULES.forEach(m=>m.pages.forEach(p=>{
        if(p.id===pid){o[p.id]=true;(p.funcs||[]).forEach((f,i)=>o[p.id+'|'+i]=true);}
      }));
    });
  }
  return o;
}
function saveState(){try{localStorage.setItem('ppmart_role_proto_v2',JSON.stringify({roles:roles.map(r=>r.id),state}));}catch(e){}}
function loadState(){try{const s=JSON.parse(localStorage.getItem('ppmart_role_proto_v2')||'null');if(s&&s.roles){roles=s.roles.map(id=>{const d=ROLES.find(r=>r.id===id);return {id,name:d?d.name:id,note:d?d.note:'自定义'};});state=s.state||{};}}catch(e){}}
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
function costKeys(){
  const ks=[];
  MODULES.forEach(m=>m.pages.forEach(p=>(p.funcs||[]).forEach((f,i)=>{const fo=typeof f==='string'?{cost:false}:f;if(fo.cost)ks.push(p.id+'|'+i);})));
  return ks;
}
function roleDesc(rid){
  const pageOn=MODULES.reduce((a,m)=>a+m.pages.filter(p=>pageChecked(rid,p.id)).length,0);
  let fnOn=0;MODULES.forEach(m=>m.pages.forEach(p=>p.funcs&&p.funcs.forEach((f,i)=>{if(funcChecked(rid,p.id+'|'+i))fnOn++;})));
  const ck=costKeys();const costOn=ck.filter(k=>funcChecked(rid,k)).length;
  const cur=roles.find(r=>r.id===rid);
  return '角色能力：'+(cur?cur.name+'（'+(cur.note||'自定义')+'）':'')+' · 可进页面 '+pageOn+' 个 · 可用功能 '+fnOn+' 项 · <span style="color:'+(costOn?'#f0b429':'var(--text-tertiary)')+'">成本/利润可见：'+(costOn?'是（勾选 '+costOn+' 项）':'否')+'</span>';
}
function updateRoleDesc(){const rid=curRole()||roles[0].id;$('rpRoleDesc').innerHTML=roleDesc(rid);}
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
        </div>`+(p.funcs?`<div class="rp-funcs">${p.funcs.map((f,i)=>{
          const fo=typeof f==='string'?{n:f,d:''}:f;
          return `<label title="${fo.d||fo.n}"><input type="checkbox" ${funcChecked(rid,p.id+'|'+i)?'checked':''} onclick="setFunc('${rid}','${p.id}',${i},this.checked)">${fo.n}${fo.cost?' <span style="color:#f0b429;font-size:10px">成本/利润</span>':''}</label>`;
        }).join('')}</div>`:'')+`</div>`;
      }).join('')+`</div>`;
  }).join('');
  updateRoleDesc();
  saveState();
}
loadState();renderRoleBar();render();
</script>
