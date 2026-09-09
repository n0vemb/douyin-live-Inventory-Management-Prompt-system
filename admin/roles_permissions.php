<?php
$pageTitle = '角色权限配置';
$currentPage = 'roles_permissions';
require_once __DIR__ . '/layout.php';
$rpCan = in_array($currentUser['role'] ?? '', ['store_admin', 'group_admin', 'super_admin'], true);

$RP_GROUPS = [
  ['name'=>'盘点','items'=>[
    ['audit.inventory','商品页库存盘点','全店逐SKU盘点（已接入真实校验）'],
    ['audit.rack','仓库货架盘点','逐格×SKU盘点（已接入真实校验）']
  ]],
  ['name'=>'商品','items'=>[
    ['product.export','导出库存','库存导出（待接入，默认按角色）'],
    ['product.delete','删除商品','单个/批量删除（待接入）'],
    ['product.offline_price','线下售价设置','收银台SKU价（待接入）'],
    ['product.batch_edit','批次进价售价编辑','批次编辑（待接入）']
  ]],
  ['name'=>'直播/门店','items'=>[
    ['live.session_meta','场次主播/运营/账号修改','历史场次信息（已按权限建议）'],
    ['pos.delete_order','彻底删除POS订单','门店订单删除（待接入）']
  ]],
  ['name'=>'财务/系统','items'=>[
    ['finance.view_cost','查看成本/毛利','涉及成本（默认仅店管/超管）'],
    ['finance.report','线下销售报表','报表查看导出（待接入）'],
    ['user.manage','用户管理','账号管理（待接入）'],
    ['todo.cross_shop_view','跨店待办只读','集团管理员跨店看待办（同店规则不变）']
  ]],
  ['name'=>'营销/补偿','items'=>[
    ['coupon.issue','优惠券配置/补发','券管理（待接入）'],
    ['compensate.shipping','直播运费补偿','撤单/退货补偿（默认全员）']
  ]],
];
$ROLE_LABELS = ['super_admin'=>'超管','group_admin'=>'集团管理员','store_admin'=>'店管','deputy_store_admin'=>'副店长','operator'=>'运营','warehouse'=>'仓库'];
$DEFAULTS = [];
foreach (defaultPermMap() as $perm=>$roles) $DEFAULTS[$perm] = $roles;
$OVERRIDES = [];
try { $q=getDB()->query('SELECT role,perm,allowed FROM role_permissions'); foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $OVERRIDES[$r['role'].'|'.$r['perm']]=(int)$r['allowed']===1; } catch(Exception $e){}
?>
<div class="page-title">角色权限配置 <span class="sub" style="font-size:12px;color:var(--text-tertiary);font-weight:500">细粒度权限覆盖（默认值已内置；改动立即生效）</span></div>
<?php if (!$rpCan): ?><div class="card" style="padding:40px;text-align:center;color:var(--text-tertiary)">无权限</div><?php exit; endif; ?>

<style>
  .rpc-tip{font-size:12.5px;color:var(--text-tertiary);margin-bottom:10px}
  .rpc-tbl{width:100%;border-collapse:collapse;font-size:13px}
  .rpc-tbl th,.rpc-tbl td{border-bottom:1px solid var(--border);padding:8px 10px;text-align:left}
  .rpc-tbl th{background:var(--bg-hover);font-size:12px;color:var(--text-secondary)}
  .rpc-grp{font-size:12px;color:var(--text-tertiary);background:var(--bg-body)}
  .rpc-role{text-align:center}
  .rpc-chk{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
  .rpc-over{outline:1px solid var(--primary);border-radius:3px}
</style>
<div class="rpc-tip">说明：勾选=允许，取消=拒绝；若该格与系统默认一致则不生成覆盖记录，恢复“默认”可一键回原值。当前已接入真实校验的权限点会标注，其余默认仍按原角色逻辑。</div>
<div class="card" style="overflow-x:auto">
<table class="rpc-tbl">
  <thead><tr><th>权限点</th><?php foreach($ROLE_LABELS as $id=>$l): ?><th class="rpc-role"><?= $l ?></th><?php endforeach; ?></tr></thead>
  <tbody id="rpcBody"></tbody>
</table>
</div>
<div style="margin-top:14px;display:flex;gap:10px;align-items:center">
  <button class="btn btn-primary" onclick="savePerms()">保存配置</button>
  <span style="font-size:12px;color:var(--text-tertiary)" id="rpcMsg"></span>
</div>

<script>
const ROLES=<?= json_encode(array_keys($ROLE_LABELS)) ?>;
const DEFAULTS=<?= json_encode($DEFAULTS) ?>;
const OVERRIDES=<?= json_encode($OVERRIDES) ?>;
const GROUPS=<?= json_encode($RP_GROUPS, JSON_UNESCAPED_UNICODE) ?>;
const ROLE_NAMES=<?= json_encode($ROLE_LABELS) ?>;
function valOf(role,perm){if(perm in OVERRIDES && role+'|'+perm in OVERRIDES)return OVERRIDES[role+'|'+perm];return (DEFAULTS[perm]||[]).includes(role);}
function rowHtml(item){
  const [perm,name,desc]=item;
  const applied=desc.includes('已接入');
  return `<tr><td><b>${name}</b><div style="font-size:11.5px;color:var(--text-tertiary)">${desc}</div><code style="font-size:10.5px">${perm}</code></td>`+
    ROLES.map(role=>{const v=valOf(role,perm);const isOver=role+'|'+perm in OVERRIDES;return `<td class="rpc-role"><input type="checkbox" class="rpc-chk ${isOver?'rpc-over':''}" data-role="${role}" data-perm="${perm}" ${v?'checked':''} onchange="onChg(this)"></td>`;}).join('')+`</tr>`;
}
document.getElementById('rpcBody').innerHTML=GROUPS.map(g=>{
  const head=`<tr class="rpc-grp"><td colspan="${ROLES.length+1}">${g.name}</td></tr>`;
  return head+g.items.map(rowHtml).join('');
}).join('');
function onChg(el){el.classList.add('rpc-over');}
async function savePerms(){
  const items=[];
  document.querySelectorAll('.rpc-chk').forEach(c=>{
    const role=c.dataset.role,perm=c.dataset.perm,allowed=c.checked;
    const def=(DEFAULTS[perm]||[]).includes(role);
    if(allowed===def)return;
    items.push({role,perm,allowed:allowed?1:0});
  });
  if(!items.length){document.getElementById('rpcMsg').textContent='没有需要保存的变更';return;}
  const res=await fetch('../api/role_permission_save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items})});
  const d=await res.json();
  document.getElementById('rpcMsg').textContent=d.success?(d.message||'已保存'):(d.error||'保存失败');
  if(d.success)setTimeout(()=>location.reload(),800);
}
</script>
