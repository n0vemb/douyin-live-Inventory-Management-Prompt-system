<?php
$pageTitle = '仓库货架';
$currentPage = 'racks';
require_once __DIR__ . '/layout.php';
$isAdmin = in_array($currentUser['role'] ?? '', ['store_admin', 'super_admin']);
?>
<div class="page-title">仓库货架 <span class="sub" style="font-size:12px;color:var(--text-tertiary);font-weight:500">货架分布查询 · 布局可配置（默认 5层 × 5大格，每大格=2小格）</span></div>

<style>
/* ===== 仓库货架 局部样式（对齐全局暗黑主题） ===== */
/* 顶部紧凑：标题下边距收小，避免搜索框与货架之间太空 */
.page-title{margin-bottom:4px}
.rk-layout{display:flex;gap:14px;align-items:flex-start}
/* 主区：展开时让出右侧面板 330px（不被覆盖）；收起时也不占满（约78%），右侧留白 */
.rk-main{flex:0 1 auto;min-width:0;transition:width .25s ease;width:78%}
body.rk-panel-open .rk-main{width:calc(100% - 330px)}
.rk-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:nowrap;margin-bottom:6px}
.rk-search{flex:1;min-width:200px;padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg-body);color:var(--text);height:34px}
.rk-search:focus{outline:none;border-color:var(--primary)}
.rk-result{display:none;background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin:10px 0;font-size:13px}
.rk-result .rk-hit{display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px dashed var(--border)}
.rk-result .rk-hit:last-child{border-bottom:none}
.rk-hit b{color:var(--text)}
.rk-hit .where{color:var(--primary);font-weight:700;white-space:nowrap}
.rk-hit .note{color:var(--text-tertiary);font-size:12px}
.rk-empty{padding:40px 20px;text-align:center;color:var(--text-tertiary);font-size:13px}
/* 货架区：一行一个货架（等宽占满主区，宽度一致） */
.rk-racks{display:grid;grid-template-columns:1fr;gap:12px;margin-top:0}
/* 货架卡片 */
.rk-rack{background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;padding:14px;min-width:0}
.rk-rack-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.rk-rack-head .rk-code{font-size:17px;font-weight:800;color:var(--primary);letter-spacing:1px}
.rk-rack-head .rk-ops{display:flex;gap:6px}
.rk-rack-head .rk-ops button{padding:4px 9px;font-size:12px;line-height:1.2}
.rk-rack-head .rk-ops button:disabled{opacity:.3;cursor:not-allowed}
/* 每层一行：层标签 + 5 大格（高度一致） */
.rk-row{display:flex;align-items:stretch;margin:6px 0;gap:8px}
.rk-row-lbl{width:52px;flex:0 0 52px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--text-tertiary);font-weight:600;border:1px solid var(--border);border-radius:8px;background:var(--bg-hover)}
.rk-bigs{flex:1;display:grid;grid-template-columns:repeat(5,1fr);gap:6px}
/* 大格：固定高度 64px，内部左右分半（小格） */
.rk-big{height:64px;border-radius:8px;border:1px dashed var(--border);background:var(--bg-body);display:flex;overflow:hidden;position:relative}
.rk-big.hoverable{cursor:pointer}
.rk-big.hoverable:hover{border-color:var(--primary)}
.rk-big .rk-add{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:var(--text-tertiary);font-size:20px;opacity:.5}
.rk-big.hoverable:hover .rk-add{opacity:1;color:var(--primary)}
/* 商品块：占满大格（span2）或左/右半格（span1），高度一致 */
.rk-cell{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:4px 6px;cursor:pointer;background:linear-gradient(135deg,rgba(79,140,255,.14),rgba(143,91,255,.08));border-right:1px dashed var(--border);overflow:hidden;transition:all .15s}
.rk-cell:last-child{border-right:none}
.rk-cell:hover{background:linear-gradient(135deg,rgba(79,140,255,.26),rgba(143,91,255,.14))}
.rk-cell .nm{font-size:12px;font-weight:700;color:var(--text);text-align:center;line-height:1.3;word-break:break-all;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;width:100%}
.rk-cell .st{font-size:10.5px;color:var(--text-tertiary);margin-top:2px;white-space:nowrap}
.rk-cell .st.zero{color:var(--danger)}
/* 半格商品：字号略小 */
.rk-cell.half .nm{font-size:11px}
/* 搜索命中闪红 */
.rk-cell.flash{animation:rkFlash .9s ease-in-out infinite;z-index:2}
@keyframes rkFlash{0%,100%{background:rgba(255,80,80,.45);border-color:#ff5252}50%{background:rgba(255,80,80,.85);box-shadow:0 0 10px 2px rgba(255,82,82,.6)}}
/* 弹窗 */
.rk-modal{width:min(440px,94vw);max-width:440px}
.rk-ps{position:relative}
.rk-ps input{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg-body);color:var(--text);font-size:13px}
.rk-ps input:focus{outline:none;border-color:var(--primary)}
.rk-ps-list{position:absolute;left:0;right:0;top:calc(100% + 4px);background:var(--bg-elevated);border:1px solid var(--border);border-radius:8px;max-height:220px;overflow:auto;z-index:50;display:none;box-shadow:0 8px 24px rgba(0,0,0,.4)}
.rk-ps-list.show{display:block}
.rk-ps-item{padding:8px 11px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px}
.rk-ps-item:hover{background:var(--bg-hover)}
.rk-ps-item b{color:var(--text)}
.rk-ps-item .sub2{color:var(--text-tertiary);font-size:11.5px;margin-left:6px}
.rk-ps-item.sel{background:var(--primary-light);border-color:var(--primary)}
.rk-kv{display:flex;justify-content:space-between;font-size:13px;padding:5px 0;border-bottom:1px dashed var(--border);color:var(--text-secondary)}
.rk-kv b{font-weight:600;color:var(--text)}
.rk-msg{font-size:12.5px;margin-top:10px;min-height:18px}
.rk-msg.err{color:var(--danger)}
.rk-msg.ok{color:var(--success)}
/* 弹窗内部（参考出库记账页：label + form-input + 底部右对齐按钮） */
.rk-modal-body label{display:block;font-size:13px;color:var(--text-secondary);margin-bottom:6px}
.rk-modal-body .form-input{width:100%;font-size:14px}
.rk-modal-body .rk-flex{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.rk-modal-body .rk-flex.right{justify-content:flex-end}
.rk-modal-body .rk-kv{display:flex;justify-content:space-between;font-size:13px;padding:7px 0;border-bottom:1px dashed var(--border);color:var(--text-secondary)}
.rk-modal-body .rk-kv b{font-weight:600;color:var(--text)}
/* ===== 右侧吸附面板/抽屉（完全参考 live_ledger ps-panel 体系：价格库存查询/福袋记录） ===== */
/* 右侧展开 tab（常驻，面板收起时露出可点） */
.rk-tab{position:fixed;right:0;top:45%;transform:translateY(-50%);z-index:300;background:var(--primary);color:#fff;border-radius:8px 0 0 8px;padding:12px 6px;cursor:pointer;box-shadow:-2px 2px 10px rgba(0,0,0,.4);writing-mode:vertical-rl;font-size:13px;font-weight:700;letter-spacing:2px;user-select:none;transition:filter .2s;border:none}
.rk-tab:hover{filter:brightness(1.12)}
/* 未在货架商品面板 */
.rk-panel{position:fixed;right:0;top:0;bottom:0;width:330px;z-index:301;background:var(--bg-surface);border-left:1px solid var(--border);box-shadow:-4px 0 24px rgba(0,0,0,.5);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .25s ease}
.rk-panel.open{transform:translateX(0)}
.rk-panel .rk-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.rk-panel .rk-head .rk-title{font-weight:700;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px}
.rk-panel .rk-head .rk-title .rk-up-cnt{font-size:11.5px;color:var(--text-tertiary);font-weight:500}
.rk-panel .rk-close{border:none;background:none;font-size:18px;cursor:pointer;color:var(--text-tertiary);line-height:1}
.rk-panel .rk-close:hover{color:var(--text)}
.rk-panel .rk-search{padding:12px 16px 9px;border-bottom:1px solid var(--border)}
.rk-panel .rk-search input{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;background:var(--bg-elevated);color:var(--text)}
.rk-panel .rk-search input::placeholder{color:var(--text-tertiary)}
.rk-panel .rk-search input:focus{border-color:var(--primary)}
.rk-panel .rk-body{flex:1;overflow-y:auto;padding:5px 12px 10px}
.rk-panel .rk-empty{color:var(--text-tertiary);font-size:13px;text-align:center;padding:30px 10px}
.rk-up-item{background:var(--bg-hover);border:1px solid var(--border);border-radius:8px;padding:8px 10px;margin-bottom:7px;cursor:grab;transition:all .15s}
.rk-up-item:hover{border-color:var(--primary);background:var(--bg-active)}
.rk-up-item:active{cursor:grabbing}
.rk-up-item .rk-up-name{font-size:13px;font-weight:600;color:var(--text);word-break:break-all}
.rk-up-item .rk-up-meta{font-size:11px;color:var(--text-tertiary);margin-top:2px}
.rk-up-item.dragging{opacity:.4}
/* 录入/详情/布局设置 抽屉（同 ps-panel 体系，稍窄） */
.rk-drawer{position:fixed;right:0;top:0;bottom:0;width:360px;z-index:302;background:var(--bg-surface);border-left:1px solid var(--border);box-shadow:-4px 0 24px rgba(0,0,0,.55);display:flex;flex-direction:column;transform:translateX(100%);transition:transform .25s ease}
.rk-drawer.open{transform:translateX(0)}
.rk-drawer .rk-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.rk-drawer .rk-head .rk-title{font-weight:700;font-size:15px;color:var(--text)}
.rk-drawer .rk-close{border:none;background:none;font-size:18px;cursor:pointer;color:var(--text-tertiary);line-height:1}
.rk-drawer .rk-close:hover{color:var(--text)}
.rk-drawer .rk-body{padding:16px 18px;overflow-y:auto;flex:1}
/* 拖拽放置高亮 */
.rk-droppable.drag-over{border-color:var(--success)!important;box-shadow:0 0 0 2px var(--success)!important;position:relative}
</style>

<div class="rk-layout">
  <div class="rk-main">
    <div class="rk-toolbar">
      <input class="rk-search" id="rkQ" placeholder="搜索商品名称 / 常用名 / 条码 / 拼音（如 kbs → 卡比兽）…" autocomplete="off">
      <?php if ($isAdmin): ?>
        <button class="btn btn-primary btn-sm" onclick="addRack()">+ 新增货架</button>
        <button class="btn btn-secondary btn-sm" onclick="rkLayoutSet()">布局设置</button>
      <?php endif; ?>
    </div>
    <div class="rk-result" id="rkResult"></div>
    <div id="rkList"><div class="rk-empty">加载中…</div></div>
  </div>
</div>

<!-- 右侧展开 tab（常驻，面板收起时露出，参考 live_ledger sideTabs） -->
<button class="rk-tab" id="rkTab" onclick="rkTogglePanel()">未在货架</button>

<!-- 右侧吸附面板：未在货架商品（参考 live_ledger ps-panel，可拖拽到货架格子放置/替换） -->
<div class="rk-panel open" id="rkPanel">
  <div class="rk-head">
    <span class="rk-title">未在货架商品 <span class="rk-up-cnt" id="rkUpCnt"></span></span>
    <button class="rk-close" onclick="rkTogglePanel()">&times;</button>
  </div>
  <div class="rk-search"><input id="rkUpQ" placeholder="搜索商品…" oninput="renderUnplaced()"></div>
  <div class="rk-body" id="rkUpList"><div class="rk-empty">加载中…</div></div>
</div>

<!-- 录入/详情/布局设置 抽屉（参考 live_ledger ps-panel，右侧滑入） -->
<div class="rk-drawer" id="rkDrawer">
  <div class="rk-head">
    <span class="rk-title" id="rkMTitle">录入商品</span>
    <button class="rk-close" onclick="rkClose()">&times;</button>
  </div>
  <div class="rk-body rk-modal-body" id="rkMBody"></div>
</div>

<div id="toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg-elevated);color:var(--text);padding:10px 18px;border-radius:10px;font-size:13px;z-index:3000;display:none;border:1px solid var(--border)"></div>

<script>
let rkRacks={}, rkOrder=[], rkAdmin=false, rkIdx=[], rkLayout={rows:5,big_cols:5}, upItems=[];
const $id=id=>document.getElementById(id);
let rkToastT;
function rkToast(m){const t=$id('toast');t.textContent=m;t.style.display='block';clearTimeout(rkToastT);rkToastT=setTimeout(()=>t.style.display='none',2200);}
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}

// ---------- 加载 ----------
async function rkLoad(){
  try{
    const res=await fetch('../api/get_racks.php');
    const d=await res.json();
    if(!d.success)throw new Error(d.error||'加载失败');
    rkRacks=d.racks||{}; rkOrder=(d.order||[]).slice(); rkAdmin=!!d.admin;
    rkLayout=(d.layout&&d.layout.rows&&d.layout.big_cols)?d.layout:{rows:5,big_cols:5};
    buildIdx(); rkRender(); rkSearch(); loadUnplaced();
  }catch(e){ $id('rkList').innerHTML='<div class="rk-empty">加载失败：'+esc(e.message)+'</div>'; }
}

// ---------- 渲染 ----------
function buildIdx(){
  rkIdx=[];
  Object.keys(rkRacks).forEach(rack=>{
    Object.keys(rkRacks[rack]).forEach(row=>{
      const rowData=rkRacks[rack][row];
      Object.keys(rowData).forEach(pos=>{
        const c=rowData[pos];
        if(c&&c.product) rkIdx.push({rack,row:+row,pos:+pos,span:c.span||1,product:c.product});
      });
    });
  });
}
function rkRender(){
  const keys=rkOrder.slice();
  Object.keys(rkRacks).forEach(k=>{if(keys.indexOf(k)===-1)keys.push(k);});
  if(!keys.length){
    $id('rkList').innerHTML='<div class="rk-empty">还没有货架，'+(rkAdmin?'点右上角「+ 新增货架」开始。':'请联系店铺管理员新增货架。')+'</div>';
    return;
  }
  $id('rkList').innerHTML='<div class="rk-racks">'+keys.map(rack=>{
    const idx=keys.indexOf(rack);
    const ops=rkAdmin
      ? '<div class="rk-ops">'+
        '<button class="btn btn-sm btn-secondary" onclick="rkMove(\''+esc(rack)+'\',-1)" '+(idx>0?'':'disabled')+' title="上移">↑</button>'+
        '<button class="btn btn-sm btn-secondary" onclick="rkMove(\''+esc(rack)+'\',1)" '+(idx<keys.length-1?'':'disabled')+' title="下移">↓</button>'+
        '<button class="btn btn-sm btn-secondary" onclick="rkRename(\''+esc(rack)+'\')">更名</button>'+
        '<button class="btn btn-sm btn-secondary" onclick="rkDel(\''+esc(rack)+'\')">删除</button>'+
        '</div>' : '';
    let rowsHtml='';
    for(let row=rkLayout.rows;row>=1;row--){
      const rowData=rkRacks[rack][String(row)]||{};
      let bigs='';
      for(let b=1;b<=rkLayout.big_cols;b++){
        const p1=b*2-1,p2=b*2;
        const c1=rowData[String(p1)],c2=rowData[String(p2)];
        if(c1&&c1.span===2) bigs+=rkBigFull(rack,row,p1,c1);
        else if(c1&&c2) bigs+=rkBigTwo(rack,row,p1,p2,c1,c2);
        else if(c1) bigs+=rkBigHalf(rack,row,p1,p2,c1);   // 左半有商品，右半空（可点录入）
        else if(c2) bigs+=rkBigHalf(rack,row,p2,p1,c2);   // 右半有商品，左半空（可点录入）
        else bigs+='<div class="rk-big rk-droppable'+(rkAdmin?' hoverable':'')+'" data-rack="'+esc(rack)+'" data-row="'+row+'" data-pos="'+p1+'"'+(rkAdmin?' onclick="rkPut(\''+esc(rack)+'\','+row+','+p1+')"':'')+'><span class="rk-add">+</span></div>';
      }
      rowsHtml+='<div class="rk-row"><div class="rk-row-lbl">第'+row+'层</div><div class="rk-bigs">'+bigs+'</div></div>';
    }
    return '<div class="rk-rack"><div class="rk-rack-head"><span class="rk-code">'+esc(rack)+'</span>'+ops+'</div>'+rowsHtml+'</div>';
  }).join('')+'</div>';
}
function rkCellHtml(c,posCls){
  const p=c.product,stock=p.stock||0;
  return '<div class="rk-cell '+(posCls||'')+' rk-droppable" data-rack="'+esc(c._rack)+'" data-row="'+c._row+'" data-pos="'+c._pos+'" onclick="rkInfo(\''+esc(c._rack)+'\','+c._row+','+c._pos+')" title="'+esc(p.name)+(c.note?'（'+esc(c.note)+'）':'')+'">'+
    '<span class="nm">'+esc(p.name)+'</span>'+
    '<span class="st '+(stock===0?'zero':'')+'">库存 '+stock+'</span></div>';
}
function rkBigFull(rack,row,pos,c){ c._rack=rack;c._row=row;c._pos=pos; return '<div class="rk-big">'+rkCellHtml(c,'')+'</div>'; }
function rkBigTwo(rack,row,p1,p2,c1,c2){ c1._rack=rack;c1._row=row;c1._pos=p1; c2._rack=rack;c2._row=row;c2._pos=p2; return '<div class="rk-big">'+rkCellHtml(c1,'half')+rkCellHtml(c2,'half')+'</div>'; }
function rkBigHalf(rack,row,occPos,emptyPos,c){
  c._rack=rack;c._row=row;c._pos=occPos;
  // 空半格可点击/可拖放录入（span1 到该格），非管理员不显示
  const emptyHalf=rkAdmin
    ? '<div class="rk-cell rk-droppable" data-rack="'+esc(rack)+'" data-row="'+row+'" data-pos="'+emptyPos+'" onclick="rkPut(\''+esc(rack)+'\','+row+','+emptyPos+')" title="点击录入"><span style="color:var(--text-tertiary);opacity:.5;font-size:18px">+</span></div>'
    : '<div class="rk-cell" style="background:transparent;cursor:default"></div>';
  return '<div class="rk-big">'+rkCellHtml(c,'half')+emptyHalf+'</div>';
}

// ---------- 搜索 ----------
function rkSearch(){
  const kw=$id('rkQ').value.trim().toLowerCase();
  document.querySelectorAll('.rk-cell.flash').forEach(el=>el.classList.remove('flash'));
  const box=$id('rkResult');
  if(!kw){box.style.display='none';box.innerHTML='';return;}
  const hits=rkIdx.filter(it=>{
    const p=it.product;
    return (p.name||'').toLowerCase().indexOf(kw)!==-1 || (p.common_name||'').toLowerCase().indexOf(kw)!==-1
      || (p.barcode||'').toLowerCase().indexOf(kw)!==-1 || (p.pinyin||'').toLowerCase().indexOf(kw)!==-1;
  });
  hits.forEach(it=>{
    const el=document.querySelector('.rk-cell[data-rack="'+it.rack+'"][data-row="'+it.row+'"][data-pos="'+it.pos+'"]');
    if(el)el.classList.add('flash');
  });
  // 给 rk-cell 加 data 属性（在 rkCellHtml 里没有 data —— 通过 rkIdx 定位）
  box.style.display='block';
  if(!hits.length){box.innerHTML='<div class="rk-hit">未找到包含「'+esc($id('rkQ').value.trim())+'」的商品</div>';return;}
  box.innerHTML='<div class="rk-hit" style="color:var(--text-tertiary)">找到 '+hits.length+' 个位置：</div>'+hits.map(it=>{
    const spanTxt=it.span>1?'第 '+it.pos+'-'+(it.pos+1)+' 格':'第 '+it.pos+' 格';
    return '<div class="rk-hit"><span><b>'+esc(it.product.name)+'</b>'+(it.product.common_name?' <span class="note">('+esc(it.product.common_name)+')</span>':'')+'</span><span class="where">'+esc(it.rack)+' · 第'+it.row+'层 · '+spanTxt+'</span></div>';
  }).join('');
}

// ---------- 弹窗（全局 .modal 体系） ----------
function rkOpen(title,html){ $id('rkMTitle').textContent=title; $id('rkMBody').innerHTML=html; $id('rkDrawer').classList.add('open'); }
function rkClose(){ $id('rkDrawer').classList.remove('open'); }

// 商品搜索选择（录入）
let rkPickProduct=null, rkPickTimer=null;
function rkPickInit(){
  const inp=$id('rkPick');
  const list=$id('rkPickList');
  rkPickProduct=null; list.classList.remove('show');
  inp.oninput=()=>{ clearTimeout(rkPickTimer); rkPickTimer=setTimeout(()=>rkPickSearch(inp.value.trim()),200); };
  inp.onfocus=()=>{ if(inp.value.trim())rkPickSearch(inp.value.trim()); };
}
async function rkPickSearch(kw){
  const list=$id('rkPickList');
  if(!kw){list.classList.remove('show');return;}
  try{
    const res=await fetch('../api/list_products.php?keyword='+encodeURIComponent(kw));
    const d=await res.json();
    // 有库存的优先（总库存降序），再按原顺序
    const ps=((d.products||d.data)||[])
      .map(p=>({p,stock:Object.values(p.inventory_summary||{}).reduce((a,s)=>a+(s.total_stock||0),0)}))
      .sort((a,b)=>(b.stock-a.stock)||0)
      .slice(0,15)
      .map(x=>x.p);
    list.innerHTML=ps.length?ps.map(p=>{
      const st=Object.values(p.inventory_summary||{}).reduce((a,s)=>a+(s.total_stock||0),0);
      return '<div class="rk-ps-item" onclick="rkPickSel('+p.id+',this)" data-id="'+p.id+'"><b>'+esc(p.name)+'</b>'+
        (p.common_name?'<span class="sub2">'+esc(p.common_name)+'</span>':'')+
        '<span class="sub2">库存 '+st+'</span></div>';
    }).join('')
      :'<div class="rk-ps-item" style="cursor:default">无匹配商品</div>';
    list.classList.add('show');
  }catch(e){ list.innerHTML='<div class="rk-ps-item" style="cursor:default">搜索失败</div>'; list.classList.add('show'); }
}
function rkPickSel(id,el){
  rkPickProduct=+id;
  document.querySelectorAll('#rkPickList .rk-ps-item').forEach(x=>x.classList.remove('sel'));
  el.classList.add('sel');
  $id('rkPick').value=el.querySelector('b').textContent;
  $id('rkPickList').classList.remove('show');
}
// 录入
function rkPut(rack,row,pos){
  rkOpen('录入商品 · '+rack+' 第'+row+'层',
    '<div class="rk-ps" style="margin-bottom:12px;"><input id="rkPick" class="form-input" placeholder="输入名称/拼音/条码搜索商品…" autocomplete="off"><div class="rk-ps-list" id="rkPickList"></div></div>'+
    '<div class="rk-flex" style="margin-bottom:12px;">'+
      '<div style="flex:1;min-width:120px;"><label>起始格</label><input id="rkPos" class="form-input" type="number" value="'+pos+'" min="1" max="10"></div>'+
      '<div style="flex:1;min-width:150px;"><label>占格</label><select id="rkSpan" class="form-input"><option value="1">半大格（1小格）</option><option value="2">整大格（2小格）</option></select></div>'+
    '</div>'+
    '<div style="margin-bottom:14px;"><label>备注（选填）</label><input id="rkNote" class="form-input" type="text" placeholder="如：第一批"></div>'+
    '<div class="rk-flex right"><button class="btn btn-outline" onclick="rkClose()">取消</button><button class="btn btn-primary" onclick="rkPutSave(\''+esc(rack)+'\','+row+')">保存</button></div>'+
    '<div class="rk-msg" id="rkMsg"></div>');
  rkPickInit();
}
async function rkPutSave(rack,row){
  const msg=$id('rkMsg');
  if(!rkPickProduct){msg.textContent='请先选择商品';msg.className='rk-msg err';return;}
  const maxPos=rkLayout.big_cols*2;
  const pos=+$id('rkPos').value, span=+$id('rkSpan').value, note=$id('rkNote').value.trim();
  if(!pos||pos<1||pos>maxPos){msg.textContent='格位需在 1-'+maxPos;msg.className='rk-msg err';return;}
  if(span===2&&pos%2!==1){msg.textContent='整大格必须从奇数格开始（1/3/5…）';msg.className='rk-msg err';return;}
  try{
    const res=await fetch('../api/rack_cell.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'put',rack,row,pos,span,product_id:rkPickProduct,note})});
    const d=await res.json();
    if(d.success){rkClose();rkToast(d.message||'已录入');rkLoad();}
    else{msg.textContent=d.error||'保存失败';msg.className='rk-msg err';}
  }catch(e){msg.textContent='请求失败';msg.className='rk-msg err';}
}
// 详情：移除 / 拆分
function rkInfo(rack,row,pos){
  const rowData=(rkRacks[rack]&&rkRacks[rack][String(row)])||{};
  const c=rowData[String(pos)]||null;
  if(!c||!c.product)return;
  const p=c.product;
  const spanTxt=c.span>1?'第 '+pos+'-'+(pos+1)+' 格':'第 '+pos+' 格';
  const splitBtn=c.span>1&&rkAdmin?'<button class="btn btn-outline" style="width:100%;margin-top:12px" onclick="rkSplit(\''+esc(rack)+'\','+row+','+pos+')">拆分为两格</button>':'';
  rkOpen('商品详情',
    '<div class="rk-kv"><span>商品</span><b>'+esc(p.name)+(p.common_name?'（'+esc(p.common_name)+'）':'')+'</b></div>'+
    '<div class="rk-kv"><span>条码</span><b>'+esc(p.barcode||'-')+'</b></div>'+
    '<div class="rk-kv"><span>位置</span><b style="color:var(--primary)">'+esc(rack)+' · 第'+row+'层 · '+spanTxt+'</b></div>'+
    '<div class="rk-kv"><span>当前库存</span><b style="'+(p.stock===0?'color:var(--danger)':'')+'">'+p.stock+' 件</b></div>'+
    (c.note?'<div class="rk-kv"><span>备注</span><b>'+esc(c.note)+'</b></div>':'')+
    splitBtn+
    '<div class="rk-flex right" style="margin-top:14px;">'+
      (rkAdmin?'<button class="btn btn-outline" style="color:var(--danger);border-color:rgba(248,113,113,.4)" onclick="rkRemove(\''+esc(rack)+'\','+row+','+pos+')">移除此商品</button>':'')+
      '<button class="btn btn-outline" onclick="rkClose()">关闭</button></div>');
}
async function rkRemove(rack,row,pos){
  if(!confirm('确定移除「'+(rkRacks[rack]&&rkRacks[rack][String(row)]&&rkRacks[rack][String(row)][String(pos)].product.name)+'」？'))return;
  const d=await rkApi('rack_cell.php',{action:'remove',rack,row,pos});
  if(d&&d.success){rkClose();rkToast(d.message);rkLoad();}
}
async function rkSplit(rack,row,pos){
  if(!confirm('拆分后「'+rkRacks[rack][String(row)][String(pos)].product.name+'」保留在第 '+pos+' 格，第 '+(pos+1)+' 格变空格。继续？'))return;
  const d=await rkApi('rack_cell.php',{action:'split',rack,row,pos});
  if(d&&d.success){rkClose();rkToast(d.message);rkLoad();}
}
// 货架管理
function addRack(){
  const code=prompt('新货架编号（如 A / B / C1）：');
  if(code===null)return;
  const c=code.trim(); if(!c)return;
  rkApi('rack_manage.php',{action:'add_rack',code:c}).then(d=>{if(d&&d.success){rkToast(d.message);rkLoad();}});
}
// 布局设置（每店铺可不同，不写死 5×5）
function rkLayoutSet(){
  rkOpen('货架布局设置',
    '<div class="rk-flex" style="margin-bottom:12px;">'+
      '<div style="flex:1;min-width:120px;"><label>层数</label><input id="rkRows" class="form-input" type="number" value="'+rkLayout.rows+'" min="1" max="10"></div>'+
      '<div style="flex:1;min-width:140px;"><label>每层大格数</label><input id="rkBig" class="form-input" type="number" value="'+rkLayout.big_cols+'" min="1" max="10"></div>'+
    '</div>'+
    '<div style="font-size:12px;color:var(--text-tertiary);margin-bottom:14px;line-height:1.6">每大格可放 1 个整格商品（占 2 小格）或 2 个半格商品（各占 1 小格）。修改布局后超出范围的格子不会显示，需重新录入。</div>'+
    '<div class="rk-flex right"><button class="btn btn-outline" onclick="rkClose()">取消</button><button class="btn btn-primary" onclick="rkLayoutSave()">保存</button></div>'+
    '<div class="rk-msg" id="rkMsg"></div>');
}
async function rkLayoutSave(){
  const msg=$id('rkMsg');
  const rows=+$id('rkRows').value, big=+$id('rkBig').value;
  if(!rows||rows<1||rows>10||!big||big<1||big>10){msg.textContent='层数/每层大格数需在 1-10';msg.className='rk-msg err';return;}
  const d=await rkApi('rack_manage.php',{action:'set_layout',rows,big_cols:big});
  if(d&&d.success){rkClose();rkToast(d.message);rkLoad();}
}
function rkRename(rack){
  const nid=prompt('货架「'+rack+'」更名为：',rack);
  if(nid===null)return;
  const c=nid.trim(); if(!c||c===rack)return;
  rkApi('rack_manage.php',{action:'rename',code:rack,new_code:c}).then(d=>{if(d&&d.success){rkToast(d.message);rkLoad();}});
}
function rkDel(rack){
  if(!confirm('确定删除货架「'+rack+'」？该货架上的商品记录将一并删除。'))return;
  rkApi('rack_manage.php',{action:'delete_rack',code:rack}).then(d=>{if(d&&d.success){rkToast(d.message);rkLoad();}});
}
function rkMove(rack,dir){
  const keys=rkOrder.slice();
  const i=keys.indexOf(rack);
  const j=i+dir;
  if(i===-1||j<0||j>=keys.length)return;
  [keys[i],keys[j]]=[keys[j],keys[i]];
  rkApi('rack_manage.php',{action:'reorder',order:keys}).then(d=>{if(d&&d.success)rkLoad();});
}
async function rkApi(file,body){
  try{
    const res=await fetch('../api/'+file,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const d=await res.json();
    if(!d.success)rkToast(d.error||'操作失败');
    return d;
  }catch(e){rkToast('请求失败');return null;}
}

$id('rkQ').addEventListener('input',rkSearch);

// ---------- 右侧浮窗：未在货架商品（拖拽到格子放置/替换） ----------
async function loadUnplaced(){
  try{
    const res=await fetch('../api/racks_unplaced.php',{cache:'no-store'});
    const d=await res.json();
    if(!d.success)return;
    upItems=d.items||[];
    renderUnplaced();
  }catch(e){}
}
function renderUnplaced(){
  const kw=$id('rkUpQ').value.trim().toLowerCase();
  const list=upItems.filter(it=>!kw||(it.name||'').toLowerCase().indexOf(kw)!==-1||(it.common_name||'').toLowerCase().indexOf(kw)!==-1||(it.barcode||'').toLowerCase().indexOf(kw)!==-1||(it.pinyin||'').toLowerCase().indexOf(kw)!==-1);
  $id('rkUpCnt').textContent=upItems.length+' 个';
  $id('rkUpList').innerHTML=list.length?list.map(it=>
    '<div class="rk-up-item" draggable="true" data-pid="'+it.id+'" title="拖到货架格子放置（该格有商品则替换）">'+
    '<div class="rk-up-name">'+esc(it.name)+'</div>'+
    '<div class="rk-up-meta">'+(it.common_name?esc(it.common_name)+' · ':'')+(it.barcode?esc(it.barcode)+' · ':'')+'库存 '+(it.stock||0)+'</div></div>').join('')
    :'<div class="rk-empty">全部商品已在货架</div>';
  document.querySelectorAll('#rkUpList .rk-up-item').forEach(el=>{
    el.addEventListener('dragstart',e=>{
      e.dataTransfer.setData('text/plain',el.dataset.pid);
      e.dataTransfer.effectAllowed='copy';
      el.classList.add('dragging');
      setTimeout(()=>el.classList.remove('dragging'),300);
    });
  });
}
function rkTogglePanel(){
  // 面板开合：收起时右侧 tab 露出可点击展开（参考 live_ledger sideTabs/ps-panel）；
  // 展开时货架区让出右侧面板宽度（不被覆盖）
  const p=$id('rkPanel');
  p.classList.toggle('open');
  document.body.classList.toggle('rk-panel-open', p.classList.contains('open'));
}
// 拖放：格子 dragover 高亮 + drop 放置/替换
document.addEventListener('dragover',e=>{ if(e.target.closest('.rk-droppable'))e.preventDefault(); });
document.addEventListener('drop',e=>{
  e.preventDefault();
  const pid=e.dataTransfer.getData('text/plain');
  const cell=e.target.closest('.rk-droppable');
  if(!pid||!cell)return;
  const rack=cell.dataset.rack,row=+cell.dataset.row,pos=+cell.dataset.pos;
  if(!rack||!row||!pos)return;
  // 该格已有商品 → 替换（原商品移出货架回浮窗）；空 → 直接放入
  const has=(rkRacks[rack]&&rkRacks[rack][String(row)]&&rkRacks[rack][String(row)][String(pos)]);
  rkApi('rack_cell.php',{action:has?'replace':'put',rack,row,pos,product_id:+pid,span:1}).then(d=>{
    if(d&&d.success){rkToast(d.message||'已放入');rkLoad();}
  });
});
// 拖放经过高亮
document.addEventListener('dragenter',e=>{ const c=e.target.closest('.rk-droppable'); if(c)c.classList.add('drag-over'); });
document.addEventListener('dragleave',e=>{ const c=e.target.closest('.rk-droppable'); if(c)c.classList.remove('drag-over'); });
document.addEventListener('drop',e=>{ document.querySelectorAll('.rk-droppable.drag-over').forEach(x=>x.classList.remove('drag-over')); });

document.addEventListener('click',e=>{ const list=$id('rkPickList'); if(list&&!e.target.closest('.rk-ps'))list.classList.remove('show'); });
document.body.classList.add('rk-panel-open'); // 面板默认展开，货架区让出右侧面板宽度
rkLoad();
</script>
