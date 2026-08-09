<?php
$pageTitle = '标签打印台';
$currentPage = 'purchase_logs';
require_once __DIR__ . '/layout.php';
$isOperator = ($currentUser['role'] === 'operator');
?>
<div class="page-title">🏷️ 标签打印台</div>

<style>
/* ===== 标签打印台 局部样式（对齐全局暗黑主题变量） ===== */
.lp-layout{display:grid;grid-template-columns:65fr 35fr;gap:14px;align-items:start}
@media(max-width:1200px){.lp-layout{grid-template-columns:1fr}}
.lp-right{display:flex;flex-direction:column;gap:14px;position:sticky;top:14px;max-height:calc(100vh - 28px);overflow:auto}
.lp-right .lp-panel{flex-shrink:0}
/* 预览区固定 4:3 比例（增高，模板区块自然下移） */
.lp-right .lp-panel:first-child{flex:0 0 auto}
.lp-preview-wrap{aspect-ratio:4/3;width:100%;display:flex;align-items:center;justify-content:center;background:repeating-conic-gradient(var(--bg-surface) 0% 25%,var(--bg-body) 0% 50%) 50%/22px 22px;border:1px dashed var(--border);border-radius:10px;padding:14px;overflow:hidden}
#previewLabel{max-width:100%;margin:auto}
.lp-panel .card-title{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;margin-bottom:10px}
.lp-panel .sub{font-size:12px;color:var(--text-tertiary);font-weight:500}
.lp-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px}
.lp-filters input[type=date]{padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--text);background:var(--bg-body)}
.lp-filters input[type=date]:focus{outline:none;border-color:var(--primary)}
.lp-filters .fsep{color:var(--text-tertiary)}
.lp-sku-filters{display:flex;gap:6px;flex-wrap:wrap;margin-left:2px}
.lp-sku-btn{padding:6px 10px;font-size:12px;border:1px solid var(--border);border-radius:7px;background:var(--bg-hover);color:var(--text-secondary);cursor:pointer;transition:all .15s}
.lp-sku-btn:hover{background:var(--bg-active)}
.lp-sku-btn.on{background:var(--primary-light);border-color:var(--primary);color:var(--primary)}
.lp-bulkbar{display:flex;gap:6px;margin-bottom:10px}
.lp-bulkbar .btn{flex:1;padding:7px 4px;font-size:12px}
.lp-search{width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;margin-bottom:10px;background:var(--bg-body);color:var(--text)}
.lp-search:focus{outline:none;border-color:var(--primary)}
.lp-sortbar{display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:12px;color:var(--text-tertiary)}
.lp-sortbar select{padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--text);background:var(--bg-body)}
.lp-row{border:1px solid var(--border);border-radius:9px;margin-bottom:8px;padding:9px 10px;background:var(--bg-surface);transition:border-color .15s}
.lp-row.on{border-color:var(--primary);background:var(--primary-light)}
.lp-row-top{display:flex;align-items:center;gap:8px}
.lp-name{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:700;font-size:13px}
.lp-name i{font-style:normal;font-size:11.5px;color:var(--text-tertiary);margin-left:4px;font-weight:500}
.lp-meta{font-size:11.5px;color:var(--text-tertiary);margin-top:5px;display:flex;gap:10px;flex-wrap:wrap}
.lp-meta code{font-size:11px;background:var(--bg-hover);padding:1px 5px;border-radius:4px;color:var(--text-secondary)}
.lp-row-bottom{display:flex;align-items:center;gap:8px;margin-top:8px}
.lp-stock{font-size:12px;color:var(--text-secondary)}
.lp-price{font-size:13px;font-weight:700;color:var(--danger)}
.lp-acts{margin-left:auto;display:flex;align-items:center;gap:6px}
.lp-stepper{display:flex;align-items:center;border:1px solid var(--border);border-radius:7px;overflow:hidden}
.lp-stepper button{width:26px;height:28px;border:none;background:var(--bg-hover);color:var(--text-secondary);font-size:15px;cursor:pointer;line-height:1}
.lp-stepper button:hover{background:var(--bg-active)}
.lp-stepper input{width:46px;height:28px;border:none;border-left:1px solid var(--border);border-right:1px solid var(--border);text-align:center;font-size:12.5px;font-weight:700;color:var(--primary);background:var(--bg-body)}
.lp-stepper input:focus{outline:none}
.lp-empty{padding:36px 20px;text-align:center;color:var(--text-tertiary);font-size:13px}
.lp-pagination{display:flex;align-items:center;justify-content:center;gap:10px;padding:10px 0 2px}
.lp-pagination .pageinfo{font-size:12px;color:var(--text-tertiary)}
.lp-preview-note{font-size:12px;color:var(--text-tertiary);margin-top:8px;text-align:center;max-width:420px;line-height:1.5}
.lp-tpl-card{border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:9px;cursor:pointer;background:var(--bg-surface);position:relative}
.lp-tpl-card.on{border-color:var(--primary);background:var(--primary-light)}
.lp-tpl-card .tn{font-weight:600;color:var(--text)}
.lp-tpl-card .ts{font-size:11.5px;color:var(--text-tertiary);margin-top:2px}
.lp-tpl-del{position:absolute;top:6px;right:8px;width:18px;height:18px;line-height:16px;text-align:center;border-radius:50%;color:var(--text-tertiary);font-size:14px;cursor:pointer;display:none;background:transparent;border:none}
.lp-tpl-card:hover .lp-tpl-del{display:block}
.lp-tpl-del:hover{color:var(--danger);background:var(--danger-light)}
.lp-kv{display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px dashed var(--border);color:var(--text-secondary)}
.lp-kv b{font-weight:600;color:var(--text)}
.lp-actions{position:sticky;bottom:0;display:flex;gap:10px;padding:14px 0;background:var(--bg-body);border-top:1px solid var(--border);margin-top:14px}
.lp-actions .hint{font-size:12px;color:var(--text-tertiary);align-self:center;margin-right:auto;line-height:1.5}
/* 标签画布（白纸，出纸可读） */
.lp-canvas{position:relative;background:#fff;border:1px solid var(--border);margin:0 auto;overflow:hidden}
.lp-el{position:absolute;font-size:12px;cursor:move;user-select:none;padding:1px 2px;color:#1d2330;display:inline-flex;align-items:flex-start;border:1px dashed var(--border);box-sizing:border-box}
.lp-el>*{pointer-events:none}
.lp-el .barcode{display:flex;flex-direction:column;align-items:center;width:100%}
.lp-el .bc{display:block;width:100%}
.lp-el .bct{font-family:ui-monospace,monospace;font-size:9px;margin-top:2px;color:#1d2330}
.lp-el .rm{position:absolute;top:-8px;right:-8px;width:16px;height:16px;border-radius:50%;background:var(--danger);color:#fff;font-size:11px;line-height:16px;text-align:center;display:none;cursor:pointer}
.lp-el:hover .rm{display:block}
.lp-el.sel{border:1px solid var(--primary)}
.lp-el.sel .rm{display:block}
.lp-el .rs{position:absolute;bottom:-5px;right:-5px;width:13px;height:13px;background:var(--primary);border:2px solid #fff;border-radius:3px;cursor:nwse-resize;display:none}
.lp-el.sel .rs{display:block}
.lp-el-tools{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.lp-el-tools .btn{padding:5px 8px;font-size:11.5px}
.lp-el-props{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:10px;padding:10px 12px;background:var(--bg-surface);border:1px solid var(--border);border-radius:8px}
.lp-el-props label{display:flex;flex-direction:column;font-size:11px;color:var(--text-tertiary);gap:3px}
.lp-el-props input{width:64px;padding:5px 6px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:var(--bg-body);color:var(--text)}
.lp-field-row{display:flex;gap:10px;margin-bottom:10px}
.lp-field-row .f{flex:1}
.lp-field-row label{display:block;font-size:11px;color:var(--text-tertiary);margin-bottom:3px}
.lp-field-row input,.lp-field-row select{width:100%;padding:7px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--bg-body);color:var(--text)}
.lp-field-row input:focus,.lp-field-row select:focus{outline:none;border-color:var(--primary)}
.lp-payload{background:#0f172a;color:#cfe3ff;font-family:ui-monospace,monospace;font-size:11.5px;padding:12px;border-radius:8px;white-space:pre-wrap;max-height:240px;overflow:auto;line-height:1.55}
.lp-status{font-size:12.5px;color:var(--text-secondary);margin-top:8px}
.lp-details{margin-top:10px;border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:12.5px;background:var(--bg-surface)}
.lp-details summary{cursor:pointer;font-weight:600;color:var(--primary)}
/* 浏览器打印走独立 iframe，不再依赖主页面 @media print */
</style>

<div class="lp-layout">
  <!-- 左：商品/SKU 列表（照原页 get_purchase_logs 聚合行） -->
  <div class="card lp-panel">
    <div class="card-title">① 选择商品 / SKU <span class="sub" id="selSub">份数 = 张数</span></div>
    <div class="lp-filters">
      <input type="date" id="fStart" onchange="loadProducts(1)">
      <span class="fsep">—</span>
      <input type="date" id="fEnd" onchange="loadProducts(1)">
      <button class="btn btn-sm btn-secondary" onclick="setRange(1)">近1天</button>
      <div class="lp-sku-filters" id="skuFilters"></div>
    </div>
    <input class="lp-search" id="batchSearch" placeholder="搜索商品名称 / 常用名 / 条码 / 拼音" oninput="loadProducts(1)">
    <div class="lp-sortbar">
      排序
      <select id="sortBy" onchange="loadProducts(1)">
        <option value="date">入库时间</option>
        <option value="qty">数量</option>
        <option value="price">售价</option>
      </select>
      <select id="sortDir" onchange="loadProducts(1)">
        <option value="desc">降序</option>
        <option value="asc">升序</option>
      </select>
    </div>
    <div class="lp-bulkbar">
      <button class="btn btn-secondary btn-sm" onclick="bulk('all')">全部库存</button>
      <button class="btn btn-secondary btn-sm" onclick="bulk('one')">每SKU一张</button>
      <button class="btn btn-secondary btn-sm" onclick="bulk('clear')">清空</button>
    </div>
    <div id="prodList"></div>
    <div class="lp-pagination" id="pagination"></div>
  </div>

  <!-- 右上：标签预览 -->
  <div class="lp-right">
  <div class="card lp-panel">
    <div class="card-title">② 标签预览 <span class="sub" id="previewSub">模板实时渲染</span></div>
    <div class="lp-preview-wrap">
      <div id="previewLabel"></div>
    </div>
  </div>

  <!-- 右下：模板 + 打印设置 -->
  <div class="card lp-panel">
    <div class="card-title">③ 模板 <span class="sub">点选 / 新建 / 编辑</span></div>
    <div id="tplList"></div>
    <div style="display:flex;gap:8px;margin-top:4px">
      <button class="btn btn-secondary btn-sm" style="flex:1" onclick="newTemplate()">新建模板</button>
      <button class="btn btn-secondary btn-sm" style="flex:1" onclick="openEditor(curTpl)">编辑当前</button>
    </div>
    <div class="card-title" style="margin-top:16px">当前打印设置</div>
    <div class="lp-kv"><span>纸张</span><b id="setPaper">—</b></div>
    <div class="lp-kv"><span>打印代理</span><b id="setHost">—</b></div>
    <div class="lp-kv"><span>目标打印机</span><b id="setPrinter">—</b></div>
    <div class="lp-kv"><span>发送格式</span><b id="setFormat">PHP GD → 打印代理</b></div>
    <div class="lp-kv"><span>待打印标签</span><b id="setCount">0 张</b></div>
  </div>
  </div>
</div>

<div class="lp-actions">
  <span class="hint" id="actHint">设置份数 → 发送打印会把任务交给打印代理（不再走浏览器）。</span>
  <button class="btn btn-secondary" onclick="openPrinterSettings()">打印代理设置</button>
  <button class="btn btn-outline" onclick="browserPrint()">浏览器打印（备用）</button>
  <button class="btn btn-primary" onclick="sendPrint(this)">发送打印</button>
</div>

<!-- 模板编辑器 -->
<div class="modal" id="mEditor">
  <div class="modal-content" style="width:min(880px,94vw);max-width:880px">
    <div class="modal-header">
      <span class="modal-title">模板编辑器</span>
      <button class="modal-close" onclick="closeModal('mEditor')">×</button>
    </div>
    <div style="padding:16px 18px">
      <div class="lp-field-row">
        <div class="f"><label>模板名称</label><input id="tplName" placeholder="如：标准小标签"></div>
        <div class="f"><label>宽度 (mm)</label><input id="tplW" type="number" value="58" min="20" max="150"></div>
        <div class="f"><label>高度 (mm)</label><input id="tplH" type="number" value="40" min="10" max="150"></div>
      </div>
      <div class="lp-el-tools" id="elTools"></div>
      <div style="text-align:center">
        <div class="lp-canvas" id="designCanvas"></div>
      </div>
      <div class="lp-el-props" id="elProps" style="display:none">
        <label>X <input type="number" id="propX" min="0" step="0.5" onchange="applyProps()"></label>
        <label>Y <input type="number" id="propY" min="0" step="0.5" onchange="applyProps()"></label>
        <label>字号(mm) <input type="number" id="propFs" min="1" step="0.5" onchange="applyProps()"></label>
        <label>宽度(mm) <input type="number" id="propW" min="5" step="0.5" onchange="applyProps()"></label>
        <label>高度(mm) <input type="number" id="propH" min="1" step="0.5" onchange="applyProps()"></label>
        <label>对齐 <select id="propAlign" onchange="applyProps()"><option value="left">左</option><option value="center">居中</option><option value="right">右</option></select></label>
      </div>
      <div style="font-size:11.5px;color:var(--text-tertiary);margin-top:8px">画布按毫米缩放预览，拖拽定位，选中元素可在下方精确设置坐标/字号/宽度；保存后模板对所有设备生效（服务端存储）。</div>
    </div>
    <div class="modal-header" style="border-top:1px solid var(--border);border-bottom:none;justify-content:flex-end;gap:15px">
      <button class="btn btn-secondary" onclick="closeModal('mEditor')">取消</button>
      <button class="btn btn-primary" onclick="saveTemplate()">保存模板</button>
    </div>
  </div>
</div>

<!-- 打印代理设置 -->
<div class="modal" id="mPrinter">
  <div class="modal-content" style="width:min(580px,94vw);max-width:580px">
    <div class="modal-header">
      <span class="modal-title">打印代理设置</span>
      <button class="modal-close" onclick="closeModal('mPrinter')">×</button>
    </div>
    <div style="padding:16px 18px">
      <div class="lp-field-row">
        <div class="f"><label>打印代理地址</label><input id="psHost" placeholder="如 http://192.168.1.50:9101"></div>
      </div>
      <div class="lp-field-row">
        <div class="f"><label>目标打印机名（留空 = 服务默认）</label><input id="psPrinter" placeholder="留空 / USB-Thermal"></div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary btn-sm" onclick="checkStatus()">检测连接</button>
        <button class="btn btn-secondary btn-sm" onclick="testPrint()">测试打印</button>
      </div>
      <div class="lp-status" id="psStatus"></div>
      <details class="lp-details" open>
        <summary>真实打印链路</summary>
        <p style="line-height:1.7;color:var(--text-secondary)">有代理地址时 = <b>浏览器直连</b>（canvas 渲染 PNG 直接发到你的电脑），不走服务器：</p>
        <div class="lp-payload">浏览器
  → canvas 渲染每个标签为 PNG @203DPI（含条码）
  → POST {代理地址}/print
        { images:[base64 PNG…], printer, pageWidth, pageHeight }
        （print_server.py 的 HTTP /print，已内置 CORS，直接可用）
  → USB 热敏出纸

状态检测：GET {代理地址}/health → {"status":"ok"}
代理地址留空 = 回退服务器转发（PHP GD 渲染 → direct_print.php）

⚠️ 前提：页面需用 http 访问（https 页面禁止请求 http://局域网IP，
Chrome 会报 mixed content 拦截）。print_server.py 已带 CORS 头，无需改动。</div>
      </details>
      <div class="modal-header" style="border-top:1px solid var(--border);border-bottom:none;margin-top:10px;padding-top:12px">
        <button class="btn btn-secondary" onclick="closeModal('mPrinter')">取消</button>
        <div class="spacer" style="flex:1"></div>
        <button class="btn btn-primary" onclick="savePrinterSettings()">保存</button>
      </div>
    </div>
  </div>
</div>

<!-- 发送打印结果 -->
<div class="modal" id="mSend">
  <div class="modal-content" style="width:min(560px,94vw);max-width:560px">
    <div class="modal-header">
      <span class="modal-title">发送打印</span>
      <button class="modal-close" onclick="closeModal('mSend')">×</button>
    </div>
    <div style="padding:16px 18px">
      <div class="lp-status" id="sendStatus"></div>
      <p style="color:var(--text-tertiary);font-size:12px">下方为前端实际发给打印服务的请求体：</p>
      <div class="lp-payload" id="sendPayload">—</div>
    </div>
  </div>
</div>

<div id="toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg-elevated);color:var(--text);padding:10px 18px;border-radius:10px;font-size:13px;z-index:3000;display:none;border:1px solid var(--border)"></div>

<script>
const SCALE=7; // px per mm（预览）
let conditionNameMap={}, conditionClassMap={}, allConditionTypes=[];
let templates=[], curTpl=null;
let records=[], copies={}, currentPage=1, totalPages=1;
let psHost='', psPrinter='';
let skuFilter='';
let selEl=-1;
const $=id=>document.getElementById(id);

// ---------- 工具 ----------
function esc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function fmtDate(d){if(!d)return '-';const s=String(d).replace(' ','T');const dt=new Date(s);if(isNaN(dt))return String(d);const p=n=>String(n).padStart(2,'0');return `${dt.getFullYear()}-${p(dt.getMonth()+1)}-${p(dt.getDate())} ${p(dt.getHours())}:${p(dt.getMinutes())}`;}
function getCondName(k){return conditionNameMap[k]||k;}
function getCondClass(k){return conditionClassMap[k]||'';}
let toastT;
function toast(m){const t=$('toast');t.textContent=m;t.style.display='block';clearTimeout(toastT);toastT=setTimeout(()=>t.style.display='none',2200);}
function openModal(id){$(id).classList.add('show');}
function closeModal(id){$(id).classList.remove('show');}

// 装饰条码（预览用确定性条纹，高度可传）
// ===== EAN-13 条码（标准护条结构：左护条101 / 中分隔01010 / 右护条101）=====
const EAN_L={0:'0001101',1:'0011001',2:'0010011',3:'0111101',4:'0100011',5:'0110001',6:'0101111',7:'0111011',8:'0110111',9:'0001011'};
const EAN_G={0:'0100111',1:'0110011',2:'0011011',3:'0100001',4:'0011101',5:'0111001',6:'0000101',7:'0010001',8:'0001001',9:'0010111'};
const EAN_R={0:'1110010',1:'1100110',2:'1101100',3:'1000010',4:'1011100',5:'1001110',6:'1010000',7:'1000100',8:'1001000',9:'1110100'};
const EAN_FIRST={0:'LLLLLL',1:'LLGLGG',2:'LLGGLG',3:'LLGGGL',4:'LGLLGG',5:'LGGLLG',6:'LGGGLL',7:'LGLGLG',8:'LGLGGL',9:'LGGLGL'};
function ean13Check(code){
  const s=String(code).replace(/\D/g,'');
  const d=s.slice(0,12);
  if(d.length!==12)return null;
  let sum=0;for(let i=0;i<12;i++)sum+=(i%2===0?1:3)*+d[i];
  const check=(10-sum%10)%10;
  return d+check;
}
function ean13Bars(code){
  const full=ean13Check(code)||String(code).slice(0,13).padEnd(13,'0');
  const first=+full[0],left=full.slice(1,7),right=full.slice(7,13);
  const pattern=EAN_FIRST[first];
  let bits='101'; // 左护条
  for(let i=0;i<6;i++){const table=pattern[i]==='L'?EAN_L:EAN_G;bits+=table[+left[i]];}
  bits+='01010'; // 中间分隔
  for(let i=0;i<6;i++){bits+=EAN_R[+right[i]];}
  bits+='101'; // 右护条
  return {bits,digits:full};
}
// SVG 版（预览 / 编辑器 / 浏览器打印）：EAN-13 标准条码，viewBox 固定 100×50（2:1 宽高比），不变形居中
function barcodeBars(code,H){
  H=H||26;
  const {bits}=ean13Bars(code);
  const xUnit=100/bits.length; // viewBox 相对宽度（100 单位）
  let rects='';
  for(let i=0;i<bits.length;i++){
    if(bits[i]==='1')rects+=`<rect x="${(i*xUnit).toFixed(3)}" y="0" width="${(xUnit+0.05).toFixed(3)}" height="50" fill="#111"/>`;
  }
  return `<svg class="bc" viewBox="0 0 100 50" width="100%" height="${H}px" preserveAspectRatio="xMidYMid meet" style="display:block">${rects}</svg>`;
}

// ---------- 状态名动态加载 ----------
async function loadSettings(){
  try{
    const res=await fetch('../api/get_settings.php');
    const data=await res.json();
    const s=(data.settings||data.data||{});
    (s.condition_types||[]).forEach(c=>{conditionNameMap[c.key]=c.name;conditionClassMap[c.key]='condition-'+c.key;});
    allConditionTypes=Object.keys(conditionNameMap);
    renderSkuFilters();
    loadTemplates();loadProducts(1);
  }catch(e){console.error(e);renderSkuFilters();loadTemplates();loadProducts(1);}
}
function renderSkuFilters(){
  let html=`<button class="lp-sku-btn ${skuFilter===''?'on':''}" onclick="setSkuFilter('')">全部</button>`;
  allConditionTypes.forEach(k=>{html+=`<button class="lp-sku-btn ${skuFilter===k?'on':''}" onclick="setSkuFilter('${k}')">${esc(getCondName(k))}</button>`;});
  $('skuFilters').innerHTML=html;
}
function setSkuFilter(k){skuFilter=k;renderSkuFilters();loadProducts(1);}

// ---------- 商品/SKU 列表（照原页 get_purchase_logs 聚合行） ----------
async function loadProducts(page=1){
  currentPage=page;
  const startDate=$('fStart').value,endDate=$('fEnd').value,keyword=$('batchSearch').value.trim();
  try{
    const res=await fetch('../api/get_purchase_logs.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      start_date:startDate,end_date:endDate,keyword,condition_type:skuFilter,page,page_size:50,
      sort_by:$('sortBy').value||'date',sort_dir:$('sortDir').value||'desc'
    })});
    const data=await res.json();
    if(data.success){
      records=data.data.records||[];
      totalPages=Math.max(1,Math.ceil((data.data.total||0)/data.data.page_size));
      renderProducts();renderPagination(data.data.total||0);
    }else{
      $('prodList').innerHTML=`<div class="lp-empty">${esc(data.error||'暂无入库记录')}</div>`;
      $('pagination').innerHTML='';updateCount();
    }
  }catch(e){console.error(e);$('prodList').innerHTML='<div class="lp-empty">查询失败</div>';}
}
function renderProducts(){
  if(!records.length){$('prodList').innerHTML='<div class="lp-empty">暂无入库记录<br><span style="font-size:12px">该时间段内没有有库存的批次</span></div>';updateCount();return;}
  $('prodList').innerHTML=records.map(r=>{
    const c=copies[r.batch_id]||0;
    const name=esc(r.product_name||r.common_name||'-');
    const common=r.common_name&&r.common_name!==r.product_name?esc(r.common_name):'';
    return `<div class="lp-row ${c?'on':''}">
      <div class="lp-row-top">
        <span class="lp-name">${name}${common?` <i>${common}</i>`:''}</span>
        <span class="condition-badge ${getCondClass(r.condition_type)}">${esc(getCondName(r.condition_type))}</span>
      </div>
      <div class="lp-meta">
        <code>${esc(r.barcode||'-')}</code>
        <span>批次 ${esc(r.batch_no||'-')}</span>
        <span>入库 ${fmtDate(r.purchased_at)}</span>
      </div>
      <div class="lp-row-bottom">
        <span class="lp-stock">库存 ${r.qty}</span>
        <span class="lp-price">¥${parseFloat(r.suggested_price||0).toFixed(2)}</span>
        <span class="lp-acts">
          <span class="lp-stepper">
            <button onclick="step('${r.batch_id}',-1)">−</button>
            <input type="number" min="0" value="${c}" onchange="setCopies('${r.batch_id}',this.value)">
            <button onclick="step('${r.batch_id}',1)">+</button>
          </span>
          <button class="btn btn-sm btn-primary" onclick="quickPrint('${r.batch_id}')">打1张</button>
        </span>
      </div>
    </div>`;
  }).join('');
  updateCount();
}
function renderPagination(total){
  if(totalPages<=1){$('pagination').innerHTML=`<span class="pageinfo">共 ${total} 条</span>`;return;}
  $('pagination').innerHTML=`
    <button class="btn btn-sm btn-secondary" ${currentPage<=1?'disabled':''} onclick="loadProducts(${currentPage-1})">上一页</button>
    <span class="pageinfo">第 ${currentPage} / ${totalPages} 页 · 共 ${total} 条</span>
    <button class="btn btn-sm btn-secondary" ${currentPage>=totalPages?'disabled':''} onclick="loadProducts(${currentPage+1})">下一页</button>`;
}
function setRange(n){
  const now=new Date();const fmt=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
  if(n===0){$('fStart').value='';$('fEnd').value='';}
  else{const s=new Date(now);s.setDate(s.getDate()-(n-1));$('fEnd').value=fmt(now);$('fStart').value=fmt(s);}
  loadProducts(1);
}
function step(id,d){copies[id]=Math.max(0,(copies[id]||0)+d);renderProducts();}
function setCopies(id,v){copies[id]=Math.max(0,+v||0);renderProducts();}
function bulk(mode){
  if(mode==='clear'){records.forEach(r=>copies[r.batch_id]=0);}
  else if(mode==='all'){records.forEach(r=>copies[r.batch_id]=r.qty);}
  else if(mode==='one'){records.forEach(r=>copies[r.batch_id]=1);}
  renderProducts();
}
function updateCount(){
  let n=0,skuCnt=0;
  records.forEach(r=>{const v=+copies[r.batch_id]||0;n+=v;if(v>0)skuCnt++;});
  $('setCount').textContent=n+' 张';
  $('selSub').textContent=`已选 ${n} 张 / ${skuCnt} 个SKU`;
  $('actHint').textContent=n>0?`已设 ${n} 张标签（${skuCnt} 个SKU），发送即交给打印代理`:'请设置份数：批量按钮铺底，或逐条设置';
}

// ---------- 模板 ----------
const EL_TOOLS=[
  {type:'name',label:'商品名称'},{type:'common',label:'常用名'},{type:'barcode',label:'条码'},
  {type:'barcodeText',label:'条码数字'},{type:'price',label:'售价'},{type:'condition',label:'SKU'},
  {type:'batch',label:'批次号'},{type:'date',label:'入库日期'}
];
function defaultElSize(type){
  const m={
    barcode:{width:50,height:10,fontSize:3},
    barcodeText:{width:50,height:4,fontSize:2.5},
    name:{width:50,height:6,fontSize:4.5,fontWeight:'bold',color:'#000000'},
    common:{width:50,height:4,fontSize:3},
    price:{width:50,height:6,fontSize:5,fontWeight:'bold',color:'#e53e3e'},
    condition:{width:50,height:4,fontSize:2.5},
    batch:{width:50,height:4,fontSize:2.5},
    date:{width:50,height:4,fontSize:2.5}
  };
  return Object.assign({color:'#000000',fontWeight:'normal'},m[type]||m.name);
}
function sampleItem(){
  const hit=records.find(r=>(copies[r.batch_id]||0)>0);
  const r=hit||records[0]||{};
  return {barcode:r.barcode||'',productName:r.product_name||r.common_name||'',commonName:r.common_name||'',price:parseFloat(r.suggested_price||0),conditionType:r.condition_type||'sealed',batchNo:r.batch_no||'',date:r.purchased_at||''};
}
// elHTML：fsPx=字号(px)、bhPx=条码高度(px)——编辑器/预览/浏览器打印按各自 scale 传入，缩放元素时字号/条码真实变化
// elHTML：fsPx=字号(px)、bhPx=条码高度(px)、align=对齐——编辑器/预览/浏览器打印按各自 scale 传入
function elHTML(type,item,fsPx,bhPx,align){
  fsPx=fsPx||13;
  const alignStyle=align?`text-align:${align};`:'';
  // name 允许换行（width:100% 保证在元素宽度内折行）；其他元素单行（nowrap 不裁剪不换行）
  const base=(pct,nowrap)=>`${alignStyle}width:100%;${nowrap?'white-space:nowrap;':''}font-size:${Math.round(fsPx*pct)}px;line-height:1.2`;
  if(type==='name')return `<div style="${base(1,false)};font-weight:700">${esc(item.productName)}</div>`;
  if(type==='common')return `<div style="${base(0.75,true)};color:#5b6478">${esc(item.commonName||'')}</div>`;
  if(type==='barcode')return `<div class="barcode" style="height:${bhPx||26}px;width:100%">${barcodeBars(item.barcode,bhPx)}</div>`;
  if(type==='barcodeText')return `<div style="${alignStyle}font-family:ui-monospace,monospace;font-size:${Math.round(fsPx*0.8)}px;color:#1d2330">${esc(item.barcode)}</div>`;
  if(type==='price')return `<div style="${base(1,true)};font-weight:700;color:#d92d20">¥${(+item.price||0).toFixed(2)}</div>`;
  if(type==='condition')return `<div style="${base(0.8,true)}">${esc(allConditionTypes.map(k=>`${k===item.conditionType?'☑':'□'} ${getCondName(k)}`).join('  '))}</div>`;
  if(type==='batch')return `<div style="${base(0.8,true)};color:#5b6478">批次 ${esc(item.batchNo)}</div>`;
  if(type==='date')return `<div style="${base(0.8,true)};color:#5b6478">${esc(item.date?String(item.date).slice(0,10):'')}</div>`;
  return '';
}
// labelHTML：scale=px per mm，元素字号/条码/尺寸都按 scale 渲染
function labelHTML(tpl,item,scale){
  scale=scale||SCALE;
  const w=tpl.canvasWidth*scale,h=tpl.canvasHeight*scale;
  const inner=(tpl.elements||[]).map(e=>{
    const base=defaultElSize(e.type);
    const fs=(e.fontSize||base.fontSize)*scale;
    // 所有元素都应用宽度（文本元素宽度=配置宽度，超宽裁剪/换行，不再只 barcode/align）
    const wStyle=`width:${(e.width||base.width)*scale}px;`;
    const hStyle=e.type==='barcode'?`height:${(e.height||base.height)*scale}px;`:'';
    return `<div class="lp-el ${e.type}" style="left:${(e.x||0)*scale}px;top:${(e.y||0)*scale}px;${wStyle}${hStyle}font-size:${fs}px">${elHTML(e.type,item,fs,(e.height||base.height)*scale,e.align)}</div>`;
  }).join('');
  return `<div style="position:relative;width:${w}px;height:${h}px;background:#fff;border:1px solid #d7dcea">${inner}</div>`;
}
function renderPreview(){
  if(!curTpl)return;
  const t=templates.find(x=>String(x.id)===String(curTpl));if(!t)return;
  // 动态缩放：标签完整显示在预览容器内，不超出
  const wrap=$('previewLabel').parentElement;
  const availW=Math.max(80,wrap.clientWidth-36);
  const availH=Math.max(80,wrap.clientHeight-40);
  const scale=Math.max(1.5,Math.min(SCALE,availW/t.canvasWidth,availH/t.canvasHeight));
  $('previewLabel').innerHTML=labelHTML(t,sampleItem(),scale);
  $('previewSub').textContent=`模板：${esc(t.name)} · 物理 ${t.canvasWidth}×${t.canvasHeight}mm`;
  refreshSetPanel();
}
function renderTpls(){
  $('tplList').innerHTML=templates.map(t=>`<div class="lp-tpl-card ${String(t.id)===String(curTpl)?'on':''}" onclick="chooseTpl('${t.id}')">
    <div class="tn">${esc(t.name)} <button class="lp-tpl-del" title="删除模板" onclick="event.stopPropagation();delTemplate('${t.id}','${esc(t.name)}')">×</button></div>
    <div class="ts">${t.canvasWidth}×${t.canvasHeight}mm · ${(t.elements||[]).length} 个元素</div></div>`).join('');
}
async function delTemplate(id,name){
  if(!confirm(`删除模板「${name}」？`))return;
  try{
    const res=await fetch('../api/delete_label_template.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:+id})});
    const data=await res.json();
    if(data.success){
      if(String(curTpl)===String(id)){curTpl=null;localStorage.removeItem('ppmart_last_template');}
      toast('模板已删除');await loadTemplates();
    }else toast('删除失败：'+(data.error||''));
  }catch(e){toast('删除失败：'+e.message);}
}
function chooseTpl(id){curTpl=String(id);renderTpls();renderPreview();localStorage.setItem('ppmart_last_template',String(id));}
async function loadTemplates(){
  try{
    const res=await fetch('../api/get_label_templates.php');
    const data=await res.json();
    if(data.success){
      templates=(data.templates||[]).map(t=>({id:String(t.id),name:t.name,canvasWidth:t.canvasWidth,canvasHeight:t.canvasHeight,elements:t.elements||[]}));
      const saved=localStorage.getItem('ppmart_last_template');
      curTpl=templates.some(t=>String(t.id)===saved)?saved:(templates[0]?String(templates[0].id):null);
      renderTpls();renderPreview();
    }
  }catch(e){console.error(e);}
}

// ---------- 模板编辑器 ----------
let editingTpl=null;
function newTemplate(){editingTpl={id:null,name:'',canvasWidth:58,canvasHeight:40,elements:[]};openEditor(editingTpl);}
function openEditor(t){
  if(typeof t==='string')t=templates.find(x=>String(x.id)===String(t));
  if(!t){toast('请先选择模板');return;}
  editingTpl=JSON.parse(JSON.stringify(t));
  $('tplName').value=editingTpl.name||'';
  $('tplW').value=editingTpl.canvasWidth;
  $('tplH').value=editingTpl.canvasHeight;
  $('elTools').innerHTML=EL_TOOLS.map(x=>`<button class="btn btn-secondary" onclick="addEl('${x.type}')">+ ${x.label}</button>`).join('');
  drawCanvas();openModal('mEditor');
}
function drawCanvas(){
  const c=$('designCanvas');c.innerHTML='';
  c.style.width=(editingTpl.canvasWidth*SCALE)+'px';c.style.height=(editingTpl.canvasHeight*SCALE)+'px';
  (editingTpl.elements||[]).forEach((e,i)=>{
    const base=defaultElSize(e.type);
    const d=document.createElement('div');d.className='lp-el '+e.type+(i===selEl?' sel':'');
    d.style.left=(e.x*SCALE)+'px';d.style.top=(e.y*SCALE)+'px';
    // 所有元素都应用宽度（文本元素宽度=配置宽度），条码用高度
    d.style.width=((e.width||base.width)*SCALE)+'px';
    if(e.type==='barcode')d.style.height=((e.height||base.height)*SCALE)+'px';
    d.style.fontSize=((e.fontSize||base.fontSize)*SCALE)+'px';
    d.innerHTML=elHTML(e.type,sampleItem(),(e.fontSize||base.fontSize)*SCALE,(e.height||base.height)*SCALE,e.align);
    if(i===selEl)d.innerHTML+='<div class="rm" title="删除元素">×</div><div class="rs" title="拖拽缩放"></div>';
    d.onmousedown=ev=>{onElMouseDown(ev,i);};
    d.ondblclick=ev=>{ev.stopPropagation();removeEl(i);};
    c.appendChild(d);
  });
  c.onmousedown=ev=>{if(ev.target===c&&selEl!==-1){setSel(-1);}};
  updateProps();
}
function setSel(i){
  selEl=i;
  // 只更新选中态，不重建画布（重建会使拖拽中的元素引用失效 → 点击元素跳左上角）
  const c=$('designCanvas');
  [...c.children].forEach((d,idx)=>{
    d.classList.toggle('sel',idx===selEl);
    const rm=d.querySelector('.rm'),rs=d.querySelector('.rs');
    if(idx===selEl){if(!rm)d.insertAdjacentHTML('beforeend','<div class="rm" title="删除元素">×</div><div class="rs" title="拖拽缩放"></div>');}
    else{if(rm)rm.remove();if(rs)rs.remove();}
  });
  updateProps();
}
function updateProps(){
  const p=$('elProps');
  if(selEl<0||!editingTpl||!editingTpl.elements[selEl]){p.style.display='none';return;}
  const e=editingTpl.elements[selEl];const base=defaultElSize(e.type);
  p.style.display='flex';
  $('propX').value=e.x;$('propY').value=e.y;
  $('propFs').value=e.fontSize||base.fontSize;
  $('propW').value=e.width||base.width;
  $('propH').value=e.height||base.height;
  $('propAlign').value=e.align||'left';
}
function applyProps(){
  if(selEl<0)return;
  const e=editingTpl.elements[selEl];const base=defaultElSize(e.type);
  e.x=Math.max(0,+$('propX').value||0);
  e.y=Math.max(0,+$('propY').value||0);
  e.fontSize=Math.max(1,+$('propFs').value||base.fontSize);
  e.width=Math.max(5,+$('propW').value||base.width);
  e.height=Math.max(1,+$('propH').value||base.height);
  e.align=$('propAlign').value||'left';
  drawCanvas();
}
function onElMouseDown(ev,i){
  ev.preventDefault();ev.stopPropagation();
  if(ev.target.classList.contains('rm')){removeEl(i);return;}
  if(ev.target.classList.contains('rs')){startResize(ev,i);return;}
  if(selEl!==i){setSel(i);}
  startDrag(ev,i);
}
function startDrag(ev,i){
  const d=$('designCanvas').children[i];if(!d)return;
  const sx=ev.clientX,sy=ev.clientY,ox=d.offsetLeft,oy=d.offsetTop;
  const mv=e2=>{d.style.left=(ox+e2.clientX-sx)+'px';d.style.top=(oy+e2.clientY-sy)+'px';};
  const up=e2=>{document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);
    const e=editingTpl.elements[i];
    e.x=Math.max(0,Math.round(d.offsetLeft/SCALE));e.y=Math.max(0,Math.round(d.offsetTop/SCALE));
    updateProps();};
  document.addEventListener('mousemove',mv);document.addEventListener('mouseup',up);
}
function startResize(ev,i){
  ev.preventDefault();
  const e=editingTpl.elements[i];
  const base=defaultElSize(e.type);
  const ow=e.width||base.width, os=e.fontSize||base.fontSize;
  const sx=ev.clientX,sy=ev.clientY;
  const mv=e2=>{
    // 缩放 = 调整字号 + barcode 宽度（内容真实变大）
    const ratio=Math.max(0.5,Math.min(3,(1+(e2.clientX-sx)/SCALE/ow)));
    const nw=Math.max(10,Math.round(ow*ratio*10)/10);
    e.width=nw;
    e.fontSize=Math.max(2,Math.round(os*ratio*10)/10);
    if(e.type==='barcode')e.height=Math.max(4,Math.round((e.height||base.height)*ratio*10)/10);
    drawCanvas();
  };
  const up=()=>{document.removeEventListener('mousemove',mv);document.removeEventListener('mouseup',up);drawCanvas();};
  document.addEventListener('mousemove',mv);document.addEventListener('mouseup',up);
}
function addEl(type){
  const base=defaultElSize(type);
  const n=(editingTpl.elements||[]).length;
  editingTpl.elements.push(Object.assign({type,x:4+(n%4)*14,y:4+Math.floor(n/4)*12},base));
  selEl=editingTpl.elements.length-1;drawCanvas();
}
function removeEl(i){editingTpl.elements.splice(i,1);if(selEl>=i)selEl=editingTpl.elements.length?Math.min(selEl,editingTpl.elements.length-1):-1;drawCanvas();}
async function saveTemplate(){
  if(!editingTpl)return;
  editingTpl.name=$('tplName').value.trim()||'未命名模板';
  editingTpl.canvasWidth=Math.max(20,+$('tplW').value||58);
  editingTpl.canvasHeight=Math.max(10,+$('tplH').value||40);
  const config={canvasWidth:editingTpl.canvasWidth,canvasHeight:editingTpl.canvasHeight,paperType:'continuous',density:'normal',
    elements:(editingTpl.elements||[]).map(e=>{
      const base=defaultElSize(e.type);
      return {type:e.type,x:e.x,y:e.y,width:e.width??base.width,height:e.height??base.height,fontSize:e.fontSize??base.fontSize,fontWeight:e.fontWeight||base.fontWeight,color:e.color||base.color,align:e.align||'left'};
    })};
  try{
    const res=await fetch('../api/save_label_template.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:editingTpl.name,config})});
    const data=await res.json();
    if(data.success){
      closeModal('mEditor');await loadTemplates();toast('模板已保存');
    }else toast('保存失败：'+esc(data.error||''));
  }catch(e){toast('保存失败：'+e.message);}
}

// ---------- 打印代理设置 ----------
function openPrinterSettings(){
  $('psHost').value=psHost;$('psPrinter').value=psPrinter;checkStatus();openModal('mPrinter');
}
function baseURL(){return (psHost||'').replace(/\/+$/,'');}
function savePrinterSettings(){
  psHost=$('psHost').value.trim();psPrinter=$('psPrinter').value.trim();
  localStorage.setItem('ppmart_print_host',psHost);localStorage.setItem('ppmart_print_printer',psPrinter);
  refreshSetPanel();
  closeModal('mPrinter');
  toast(psHost?'打印代理设置已保存':'已保存（代理地址为空 = 使用服务端配置）');
}
function checkStatus(){
  psHost=$('psHost').value.trim();psPrinter=$('psPrinter').value.trim();
  localStorage.setItem('ppmart_print_host',psHost);localStorage.setItem('ppmart_print_printer',psPrinter);
  refreshSetPanel();
  $('psStatus').textContent='检测中…';
  const url=baseURL();
  if(!url){$('psStatus').innerHTML='<span style="color:var(--text-tertiary)">代理地址为空，将使用服务端配置</span>';return;}
  fetch(url+'/health').then(r=>r.json()).then(d=>{
    $('psStatus').innerHTML=d.status==='ok'
      ?`<span style="color:var(--success)">代理在线：${esc(url)}</span>`
      :`<span style="color:var(--danger)">代理响应异常：${esc(JSON.stringify(d))}</span>`;
  }).catch(()=>{
    $('psStatus').innerHTML=`<span style="color:var(--danger)">无法连接 ${esc(url)}/health</span>`;
  });
}
function refreshSetPanel(){
  const t=templates.find(x=>String(x.id)===String(curTpl));
  $('setPaper').textContent=t?t.canvasWidth+'×'+t.canvasHeight+'mm':'—';
  $('setHost').textContent=baseURL()||'服务端配置';
  $('setPrinter').textContent=psPrinter||'服务默认';
}
function quickPrint(id){
  const r=records.find(x=>String(x.batch_id)===String(id));if(!r){toast('未找到该记录');return;}
  doDirectPrint([{batch_id:r.batch_id,qty:1}],'已打印 1 张：'+(r.product_name||r.common_name));
}
async function doDirectPrint(items,okMsg){
  const t=templates.find(x=>String(x.id)===String(curTpl));if(!t){toast('请先选择模板');return;}
  // 浏览器直连打印代理（psHost 有值时）
  if(baseURL()){
    try{
      const images=[];
      items.forEach(i=>{
        const item=batchItem(i.batch_id);
        for(let k=0;k<i.qty;k++)images.push(renderLabelCanvas(t,item));
      });
      const payload={images,printer:psPrinter,pageWidth:t.canvasWidth,pageHeight:t.canvasHeight};
      const res=await fetch(baseURL()+'/print',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const data=await res.json().catch(()=>({}));
      if(data.success!==false&&res.ok)toast(okMsg||(data.message||`已发送 ${data.printed||images.length} 张到打印机`));
      else toast('打印失败：'+(data.error||(data.errors&&data.errors[0])||('HTTP '+res.status)));
    }catch(e){toast('直连打印失败：'+e.message);}
    return;
  }
  // 回退：服务器转发（PHP GD 渲染）
  const batchQty={};items.forEach(i=>{batchQty[i.batch_id]=i.qty;});
  const payload={batch_ids:items.map(i=>i.batch_id),batch_qty:batchQty,
    template:{canvasWidth:t.canvasWidth,canvasHeight:t.canvasHeight,elements:t.elements},
    printer:psPrinter,proxy:''};
  try{
    const res=await fetch('../api/direct_print.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const data=await res.json();
    if(data.success)toast(okMsg||(data.message||'打印完成'));
    else toast('打印失败：'+(data.error||''));
  }catch(e){toast('请求失败：'+e.message);}
}
// ===== 浏览器直连打印：canvas 渲染 PNG（203DPI ≈ 8px/mm）=====
const DPI_PX=8;
function drawBarcodeCanvas(ctx,code,x,y,w,h){
  // EAN-13 标准条码：按 bits 绘制，1=黑 0=白（含护条），不带数字
  const {bits}=ean13Bars(code);
  const xUnit=w/bits.length;
  ctx.fillStyle='#111';
  for(let i=0;i<bits.length;i++){
    if(bits[i]==='1')ctx.fillRect(x+i*xUnit,y,Math.ceil(xUnit),h);
  }
}
function renderLabelCanvas(tpl,item){
  const W=Math.round(tpl.canvasWidth*DPI_PX),H=Math.round(tpl.canvasHeight*DPI_PX);
  const cv=document.createElement('canvas');cv.width=W;cv.height=H;
  const ctx=cv.getContext('2d');
  ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);
  (tpl.elements||[]).forEach(e=>{
    const base=defaultElSize(e.type);
    const fs=(e.fontSize||base.fontSize)*DPI_PX;
    const ex=(e.x||0)*DPI_PX,ey=(e.y||0)*DPI_PX;
    const ew=(e.width||base.width)*DPI_PX,eh=(e.height||base.height)*DPI_PX;
    if(e.type==='barcode'){drawBarcodeCanvas(ctx,item.barcode,ex,ey,ew,eh);return;}
    let text='',color='#111';
    if(e.type==='name'){text=item.productName;color='#111';}
    else if(e.type==='common'){text=item.commonName||'';color='#5b6478';}
    else if(e.type==='price'){text='¥'+(+item.price||0).toFixed(2);color='#d92d20';}
    else if(e.type==='condition'){text=allConditionTypes.map(k=>`${k===item.conditionType?'☑':'□'} ${getCondName(k)}`).join('  ');color='#111';}
    else if(e.type==='batch'){text='批次 '+item.batchNo;color='#5b6478';}
    else if(e.type==='date'){text=(item.date||'').slice(0,10);color='#5b6478';}
    if(!text)return;
    ctx.fillStyle=color;
    ctx.font=(e.type==='name'||e.type==='price'?'bold ':'')+Math.round(fs)+'px sans-serif';
    ctx.textBaseline='top';
    const align=e.align||'left';
    // name 支持多行（超宽换行），其他元素单行不裁剪（溢出即溢出，保持真实）
    let lineY=ey;
    const drawLine=(t,x)=>{
      if(align==='center'){ctx.textAlign='center';ctx.fillText(t,x+ew/2,lineY);}
      else if(align==='right'){ctx.textAlign='right';ctx.fillText(t,x+ew,lineY);}
      else{ctx.textAlign='left';ctx.fillText(t,x,lineY);}
    };
    if(e.type==='name'){
      const chars=String(text).split('');
      let line='';
      const maxW=ew>0?ew:fs*10;
      for(let i=0;i<chars.length;i++){
        const test=line+chars[i];
        if(ctx.measureText(test).width>maxW&&line){
          drawLine(line,ex);line=chars[i];lineY+=fs*1.2;
        }else line=test;
      }
      if(line)drawLine(line,ex);
    }else{
      drawLine(text,ex);
    }
  });
  return cv.toDataURL('image/png').split(',')[1];
}
function batchItem(bid){
  const r=records.find(x=>String(x.batch_id)===String(bid))||{};
  return {barcode:r.barcode||'',productName:r.product_name||r.common_name||'',commonName:r.common_name||'',price:parseFloat(r.suggested_price||0),conditionType:r.condition_type||'sealed',batchNo:r.batch_no||'',date:r.purchased_at||''};
}

function buildDirectPayload(list){
  const t=templates.find(x=>String(x.id)===String(curTpl));if(!t){toast('请先选择模板');return null;}
  const batchIds=list.map(r=>r.batch_id);
  const batchQty={};list.forEach(r=>{batchQty[r.batch_id]=(copies[r.batch_id]||0)>0?copies[r.batch_id]:1;});
  return {batch_ids:batchIds,batch_qty:batchQty,
    template:{canvasWidth:t.canvasWidth,canvasHeight:t.canvasHeight,elements:t.elements},
    printer:psPrinter,proxy:baseURL()};
}
function openSendModal(list,isTest){
  const payload=buildDirectPayload(list);
  if(!payload)return;
  const total=list.reduce((a,r)=>a+((payload.batch_qty[r.batch_id]||1)),0);
  $('sendStatus').textContent=isTest?`测试打印：${esc(list[0].product_name||'')} 1 张 → /api/direct_print.php`:`将发送 ${total} 张标签 → /api/direct_print.php（PHP 渲染 PNG 并转发到打印代理）`;
  $('sendPayload').textContent=JSON.stringify(payload,null,2);
  openModal('mSend');
}
async function sendPrint(btn){
  const list=records.filter(r=>(copies[r.batch_id]||0)>0);
  if(!list.length){toast('请先设置份数');return;}
  const orig=btn.textContent;
  btn.disabled=true;btn.textContent='正在发送…';
  try{
    await doDirectPrint(list.map(r=>({batch_id:r.batch_id,qty:copies[r.batch_id]})),'打印任务已发送');
  }catch(e){toast('请求失败：'+e.message);}
  finally{btn.disabled=false;btn.textContent=orig;}
}
function testPrint(){
  checkStatus();
  if(!records.length){toast('暂无商品可测试');return;}
  doDirectPrint([{batch_id:records[0].batch_id,qty:1}],'测试打印已发送：'+(records[0].product_name||records[0].common_name));
}

// ---------- 浏览器打印（备用，仅打标签） ----------
// 用独立 iframe 文档打印：不继承主页面布局/样式，打印预览 = 标签本身，不会空白
function browserPrint(){
  const list=records.filter(r=>(copies[r.batch_id]||0)>0);
  if(!list.length){toast('请先设置份数');return;}
  const t=templates.find(x=>String(x.id)===String(curTpl));if(!t){toast('请先选择模板');return;}
  const MM_PX=96/25.4; // 打印 CSS：1mm ≈ 3.78px（1px=1/96in）
  let labels='';
  list.forEach(r=>{
    const item={barcode:r.barcode,productName:r.product_name||r.common_name,commonName:r.common_name,price:parseFloat(r.suggested_price||0),conditionType:r.condition_type,batchNo:r.batch_no,date:r.purchased_at};
    for(let i=0;i<(copies[r.batch_id]||0);i++){
      labels+=`<div class="plabel">`+
        (t.elements||[]).map(e=>{
          const base=defaultElSize(e.type);
          // 所有元素应用宽度（mm），name 换行、其他单行不裁剪
          const wStyle=`width:${e.width||base.width}mm;`;
          return `<div style="position:absolute;left:${e.x}mm;top:${e.y}mm;${wStyle}">${elHTML(e.type,item,(e.fontSize||base.fontSize)*MM_PX,(e.height||base.height)*MM_PX,e.align)}</div>`;
        }).join('')+`</div>`;
    }
  });
  // 独立 iframe：完整独立 HTML，@page size 精确 = 模板尺寸
  const f=document.createElement('iframe');
  f.style.cssText='position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
  document.body.appendChild(f);
  const doc=f.contentDocument;
  doc.open();
  doc.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title></title><style>
    @page{size:${t.canvasWidth}mm ${t.canvasHeight}mm;margin:0}
    html,body{margin:0;padding:0;width:${t.canvasWidth}mm}
    .plabel{width:${t.canvasWidth}mm;height:${t.canvasHeight}mm;position:relative;overflow:hidden;background:#fff;page-break-after:always;page-break-inside:avoid}
    .plabel:last-child{page-break-after:auto}
    .barcode{display:flex;flex-direction:column;align-items:center;width:100%}
    .bc{display:block;width:100%;height:auto}
    .bct{font-family:ui-monospace,monospace;font-size:9px;margin-top:1px;color:#1d2330}
  </style></head><body>${labels}</body></html>`);
  doc.close();
  // 等渲染就绪再打印，打印后清理 iframe
  setTimeout(()=>{
    try{f.contentWindow.focus();f.contentWindow.print();}catch(e){toast('打印失败：'+e.message);}
    setTimeout(()=>{f.remove();},1000);
  },300);
}

// ---------- init ----------
psHost=localStorage.getItem('ppmart_print_host')||'';
psPrinter=localStorage.getItem('ppmart_print_printer')||'';
window.addEventListener('resize',()=>{if(curTpl)renderPreview();});
setRange(30); // 默认近30天
loadSettings();
</script>
