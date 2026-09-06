<?php
/**
 * product_meta_fill.php — 商品信息完善（品牌/IP · 系列）
 * 直接访问地址（无菜单入口）：/admin/product_meta_fill.php
 * 说明：仅店管/超管；在当前会话店铺（默认数智新匠）下扫描在库商品，
 *       用 ysjp 目录推荐品牌(IP名)+系列名（去IP前缀保留代次），千岛作无匹配时的兜底。
 */
$pageTitle = '商品信息完善';
$currentPage = 'product_meta_fill';
require_once __DIR__ . '/layout.php';
$pmAllowed = in_array($currentUser['role'] ?? '', ['store_admin', 'super_admin'], true);
$pmStoreId = $storeId ?? 0;
?>
<div class="page-title">商品信息完善 <span class="sub" style="font-size:12px;color:var(--text-tertiary);font-weight:500">品牌=IP名 · 系列=系列名（去IP前缀保留代次）；ysjp 推荐 + 千岛兜底，人工确认后应用</span></div>

<?php if (!$pmAllowed || !$pmStoreId): ?>
<div class="card">
  <div class="pm-empty" style="text-align:center;padding:40px;color:var(--text-tertiary);">
    <?= $pmAllowed ? '请先在右上角选择店铺（数智新匠）' : '无权限：仅店管 / 超管可使用本工具' ?>
  </div>
</div>
<?php exit; endif; ?>

<style>
  .pmf-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px}
  .pmf-search{flex:1;min-width:220px;height:36px;padding:0 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg-body);color:var(--text);font-size:13px}
  .pmf-filters{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px}
  .pmf-chip{border:1px solid var(--border);background:var(--bg-surface);color:var(--text-secondary);padding:6px 12px;border-radius:999px;font-size:12.5px;cursor:pointer;user-select:none}
  .pmf-chip.on{background:var(--primary);color:#fff;border-color:var(--primary)}
  .pmf-chip .n{font-weight:800;margin-left:4px}
  .pmf-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-bottom:14px}
  .pmf-stat{background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:10px 12px}
  .pmf-stat .v{font-size:22px;font-weight:800;color:var(--primary)}
  .pmf-stat .l{font-size:12px;color:var(--text-tertiary);margin-top:2px}
  .pmf-sec{margin:18px 0 8px;font-size:14px;font-weight:800;color:var(--text)}
  .pmf-sec small{font-weight:500;color:var(--text-tertiary);font-size:12px;margin-left:6px}
  .pmf-grp{background:var(--bg-surface);border:1px solid var(--border);border-radius:10px;padding:10px 12px;margin-bottom:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
  .pmf-grp .gi{font-size:13px;font-weight:700;color:var(--text)}
  .pmf-grp .gv{font-size:12px;color:var(--text-secondary)}
  .pmf-tag{display:inline-block;font-size:11px;font-weight:700;padding:1px 7px;border-radius:8px;margin-right:5px}
  .t-mb{background:rgba(239,68,68,.14);color:#ef4444}
  .t-ms{background:rgba(249,115,22,.16);color:#f97316}
  .t-mm{background:rgba(168,85,247,.15);color:#a855f7}
  .t-vr{background:rgba(59,130,246,.15);color:#3b82f6}
  .t-ns{background:rgba(107,114,128,.16);color:#6b7280}
  .pmf-row{display:grid;grid-template-columns:28px minmax(170px,1.2fr) minmax(180px,1fr) minmax(150px,.9fr) minmax(210px,1.2fr) auto;gap:10px;align-items:center;padding:9px 12px;border-bottom:1px solid var(--border);font-size:13px}
  .pmf-row.head{background:var(--bg-hover);font-weight:700;color:var(--text-secondary);font-size:12px}
  .pmf-name{font-weight:700;color:var(--text)}
  .pmf-name .sub2{display:block;font-weight:500;color:var(--text-tertiary);font-size:11.5px;margin-top:2px}
  .pmf-cur{font-size:12.5px;color:var(--text-secondary);line-height:1.6}
  .pmf-cur b{color:var(--text)}
  .pmf-cur .del{color:var(--text-tertiary);font-style:italic}
  .pmf-inp{width:100%;padding:7px 9px;border:1px solid var(--border);border-radius:7px;background:var(--bg-body);color:var(--text);font-size:13px;min-width:0}
  .pmf-acts{display:flex;gap:6px;align-items:center;white-space:nowrap}
  .pmf-btn{border:1px solid var(--border);background:var(--bg-surface);color:var(--text-secondary);padding:6px 10px;border-radius:8px;font-size:12px;cursor:pointer}
  .pmf-btn:hover{border-color:var(--primary);color:var(--primary)}
  .pmf-btn.ok{background:var(--primary);border-color:var(--primary);color:#fff}
  .pmf-empty{text-align:center;color:var(--text-tertiary);padding:26px 10px;font-size:13px}
  .pmf-cand{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;background:var(--bg-surface)}
  .pmf-cand .cn{font-weight:700;font-size:13px;color:var(--text)}
  .pmf-cand .cc{font-size:12px;color:var(--text-tertiary);margin-top:2px}
  .pmf-bar{margin:8px 0 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  @media (max-width:1100px){ .pmf-row{grid-template-columns:24px 1fr;row-gap:6px} .pmf-row.head{display:none} }
</style>

<datalist id="pmfIpList"></datalist>
<datalist id="pmfSeriesList"></datalist>

<div class="pmf-stats" id="pmfStats"></div>

<div class="pmf-toolbar">
  <input class="pmf-search" id="pmfQ" placeholder="搜索商品名 / 常用名 / 当前品牌 / 当前系列…" onkeydown="if(event.key==='Enter')reload()">
  <button class="btn btn-secondary" onclick="reload()">搜索</button>
  <button class="btn btn-primary btn-sm" onclick="reload()" id="pmfRefresh">刷新扫描</button>
  <span id="pmfCatalogInfo" style="font-size:12px;color:var(--text-tertiary)"></span>
</div>

<div class="pmf-filters" id="pmfFilters">
  <span class="pmf-chip on" data-f="all" onclick="setFilter('all')">全部问题 <span class="n" id="fAll">0</span></span>
  <span class="pmf-chip" data-f="missing_brand" onclick="setFilter('missing_brand')">缺品牌 <span class="n" id="fMissingBrand">0</span></span>
  <span class="pmf-chip" data-f="missing_series" onclick="setFilter('missing_series')">缺系列 <span class="n" id="fMissingSeries">0</span></span>
  <span class="pmf-chip" data-f="mismatch" onclick="setFilter('mismatch')">疑似不符/待确认 <span class="n" id="fMismatch">0</span></span>
  <span class="pmf-chip" data-f="no_source" onclick="setFilter('no_source')">ysjp 无匹配 <span class="n" id="fNoSource">0</span></span>
</div>

<div id="pmfGroupsWrap"></div>

<div class="card" style="margin-top:12px">
  <div class="pmf-bar">
    <b style="font-size:14px">商品明细（推荐值已预填，可下拉选择或直接自定义）</b>
    <span style="flex:1"></span>
    <button class="btn btn-secondary btn-sm" onclick="checkAll(true)">全选本页</button>
    <button class="btn btn-secondary btn-sm" onclick="checkAll(false)">清空</button>
    <button class="btn btn-primary btn-sm" onclick="applyChecked()">应用选中（保存）</button>
  </div>
  <div class="pmf-row head">
    <span></span><span>商品</span><span>当前 品牌 / 系列</span><span>状态</span><span>推荐 品牌(IP) / 系列（可改）</span><span>操作</span>
  </div>
  <div id="pmfRows"><div class="pmf-empty">加载中…</div></div>
</div>

<!-- 候选弹窗 -->
<div class="mask" id="pmfCandMask" style="display:none;position:fixed;inset:0;background:rgba(15,20,40,.6);z-index:500;align-items:center;justify-content:center" onclick="if(event.target===this)hideCands()">
  <div style="background:var(--bg-surface);border-radius:14px;width:min(640px,94vw);max-height:84vh;overflow:auto;padding:18px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <b id="pmfCandTitle" style="font-size:15px">选择候选</b>
      <button class="pmf-btn" onclick="hideCands()">关闭</button>
    </div>
    <div id="pmfCandBody"></div>
  </div>
</div>

<script>
let PMF = { filter: 'all', rows: [], groups: [], candRowId: null, busy: false };
const $e = id => document.getElementById(id);

const FLAG_TEXT = {
  missing_brand:  ['缺品牌', 't-mb'],
  missing_series: ['缺系列', 't-ms'],
  brand_mismatch: ['品牌待核对', 't-mm'],
  series_mismatch:['系列不匹配', 't-mm'],
  series_variant: ['系列写法待统一', 't-vr'],
  multi_candidate:['多候选待确认', 't-vr'],
  no_source:      ['ysjp 无匹配', 't-ns'],
};

function toast(msg, err) {
  const t = $e('pmfToast') || (() => { const d = document.createElement('div'); d.id = 'pmfToast'; d.style.cssText = 'position:fixed;bottom:26px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;z-index:999'; document.body.appendChild(d); return d; })();
  t.textContent = msg; t.style.background = err ? '#b3261e' : '#111827'; t.style.display = 'block';
  clearTimeout(t._t); t._t = setTimeout(() => t.style.display = 'none', err ? 6000 : 1800);
}

async function api(url, body) {
  const res = await fetch('../api/' + url, {
    method: body ? 'POST' : 'GET',
    headers: body ? { 'Content-Type': 'application/json' } : {},
    body: body ? JSON.stringify(body) : null,
    cache: 'no-store',
  });
  return res.json();
}

function esc(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function reload() {
  if (PMF.busy) return;
  PMF.busy = true;
  const btn = $e('pmfRefresh'); if (btn) btn.disabled = true;
  try {
    const q = $e('pmfQ').value.trim();
    const d = await api('product_meta_scan.php?keyword=' + encodeURIComponent(q) + '&filter=' + PMF.filter);
    if (!d.success) { toast(d.error || '加载失败', true); return; }
    PMF.rows = d.problems || [];
    PMF.groups = d.groups || [];
    renderStats(d);
    renderDatalist(d.datalist || {});
    renderGroups();
    renderRows();
  } catch (e) { toast('加载失败: ' + e.message, true); }
  finally { PMF.busy = false; if (btn) btn.disabled = false; }
}

function renderStats(d) {
  const s = d.stats || {};
  const cards = [
    ['待处理项合计', (s.missing_brand || 0) + (s.missing_series || 0) + (s.mismatch || 0) + (s.variant || 0) + (s.no_source || 0)],
    ['缺品牌', s.missing_brand || 0],
    ['缺系列', s.missing_series || 0],
    ['疑似不符 / 待统一', (s.mismatch || 0) + (s.variant || 0)],
    ['ysjp 无匹配', s.no_source || 0],
  ];
  $e('pmfStats').innerHTML = cards.map(c => `<div class="pmf-stat"><div class="v">${c[1]}</div><div class="l">${c[0]}</div></div>`).join('');
  $e('fAll').textContent = cards[0][1];
  $e('fMissingBrand').textContent = s.missing_brand || 0;
  $e('fMissingSeries').textContent = s.missing_series || 0;
  $e('fMismatch').textContent = (s.mismatch || 0) + (s.variant || 0);
  $e('fNoSource').textContent = s.no_source || 0;
  const c = d.catalog || {};
  $e('pmfCatalogInfo').textContent = `${d.store ? d.store.name + ' · ' : ''}在库商品 ${s.total || 0} · ysjp 目录 ${c.updated_at || '-'}（${c.ips || 0} IP）`;
}

function renderDatalist(dl) {
  $e('pmfIpList').innerHTML = (dl.brands || []).map(b => `<option value="${esc(b)}">`).join('');
  $e('pmfSeriesList').innerHTML = (dl.series || []).map(s => `<option value="${esc(s)}">`).join('');
}

function renderGroups() {
  const w = $e('pmfGroupsWrap');
  if (!PMF.groups.length) { w.innerHTML = ''; return; }
  w.innerHTML = `<div class="pmf-sec">同系列写法不一致（可一键统一）<small>初代/第一代/第1代按同一代处理；例：在日光之下 / 第10代 在日光下系列 → 统一为推荐系列；取消勾选可保留“萌粒”等子类写法</small></div>` +
    PMF.groups.map(g => `
      <div class="pmf-grp">
        <div class="gi">${esc(g.ip_name)} · ${esc(g.series)}</div>
        <div class="gv">现有写法：${esc(g.variants.join(' / '))}</div>
        <div style="flex:1;min-width:180px"><input class="pmf-inp" id="gSeries_${g.key.replace(/[^a-zA-Z0-9]/g,'_')}" value="${esc(g.series)}" list="pmfSeriesList"></div>
        <label style="font-size:12px;color:var(--text-secondary);display:flex;gap:4px;align-items:center"><input type="checkbox" id="gBrand_${g.key.replace(/[^a-zA-Z0-9]/g,'_')}" checked>品牌同时改为 ${esc(g.ip_name)}</label>
        <button class="pmf-btn ok" onclick="applyGroup('${esc(g.key.replace(/[^a-zA-Z0-9]/g,'_'))}')">统一该组</button>
        <div style="width:100%;display:flex;flex-wrap:wrap;gap:6px 16px;margin-top:6px">
          ${g.products.map(p => {
            const def = !/萌粒/.test(p.series || '');
            return `<label style="font-size:12px;color:var(--text-secondary);display:inline-flex;gap:4px;align-items:center;cursor:pointer" title="${esc(p.series || '当前无系列')}">
              <input type="checkbox" class="gchk_${g.key.replace(/[^a-zA-Z0-9]/g,'_')}" data-id="${p.id}" ${def ? 'checked' : ''}> ${esc(p.name)}${p.series ? ` <i style="color:var(--text-tertiary);font-style:normal">（${esc(p.series)}）</i>` : ''}
            </label>`;
          }).join('')}
        </div>
      </div>`).join('');
}

function rowFlagHtml(flags) {
  return (flags || []).map(f => { const x = FLAG_TEXT[f]; return x ? `<span class="pmf-tag ${x[1]}">${x[0]}</span>` : ''; }).join('');
}

function renderRows() {
  const box = $e('pmfRows');
  if (!PMF.rows.length) { box.innerHTML = '<div class="pmf-empty">该筛选下暂无需要处理的在库商品 🎉</div>'; return; }
  box.innerHTML = PMF.rows.map(r => {
    const id = r.id;
    const curB = r.brand || '<span class="del">空</span>';
    const curS = r.series || '<span class="del">空</span>';
    const recB = r.rec_brand || r.brand || '';
    const recS = r.rec_series || r.series || '';
    return `<div class="pmf-row" data-id="${id}">
      <span><input type="checkbox" class="pmf-chk" data-id="${id}"></span>
      <span class="pmf-name">${esc(r.name)}<span class="sub2">${esc(r.common_name || '')}${r.stock_total ? ' · 库存 ' + r.stock_total : ''}</span></span>
      <span class="pmf-cur"><b>${curB}</b><br>${curS}</span>
      <span>${rowFlagHtml(r.flags)}</span>
      <span style="display:flex;flex-direction:column;gap:5px">
        <input class="pmf-inp" id="b_${id}" value="${esc(recB)}" list="pmfIpList" placeholder="自定义 IP 名…">
        <input class="pmf-inp" id="s_${id}" value="${esc(recS)}" list="pmfSeriesList" placeholder="自定义系列名…">
      </span>
      <span class="pmf-acts">
        <button class="pmf-btn ok" onclick="applyOne(${id})">应用</button>
        <button class="pmf-btn" onclick="showCands(${id},true)">候选/千岛</button>
      </span>
    </div>`;
  }).join('');
}

function setFilter(f) {
  PMF.filter = f;
  document.querySelectorAll('.pmf-chip').forEach(el => el.classList.toggle('on', el.dataset.f === f));
  reload();
}

function checkAll(on) {
  document.querySelectorAll('.pmf-chk').forEach(c => c.checked = on);
}

function collectItems(ids) {
  return ids.map(id => ({ id, brand: $e('b_' + id) ? $e('b_' + id).value : null, series: $e('s_' + id) ? $e('s_' + id).value : null }));
}

async function applyOne(id) {
  const d = await api('product_meta_save.php', { items: collectItems([id]) });
  if (!d.success) { toast(d.error || '保存失败', true); return; }
  toast(d.message || '已保存');
  reload();
}

async function applyChecked() {
  const ids = [...document.querySelectorAll('.pmf-chk:checked')].map(c => +c.dataset.id);
  if (!ids.length) { toast('请先勾选商品', true); return; }
  const d = await api('product_meta_save.php', { items: collectItems(ids) });
  if (!d.success) { toast(d.error || '保存失败', true); return; }
  toast(d.message || '已保存');
  reload();
}

async function applyGroup(k) {
  const g = PMF.groups.find(x => x.key.replace(/[^a-zA-Z0-9]/g, '_') === k);
  if (!g) return;
  const series = $e('gSeries_' + k).value.trim();
  const withBrand = $e('gBrand_' + k).checked;
  if (!series) { toast('请填写要统一的系列名', true); return; }
  const ids = [...document.querySelectorAll('.gchk_' + k + ':checked')].map(c => +c.dataset.id);
  if (!ids.length) { toast('该组未勾选要统一的商品', true); return; }
  const items = g.products.filter(p => ids.includes(p.id)).map(p => ({
    id: p.id,
    series,
    brand: withBrand ? g.ip_name : (p.brand || g.ip_name),
  }));
  const d = await api('product_meta_save.php', { items });
  if (!d.success) { toast(d.error || '保存失败', true); return; }
  const n = (d.applied || 0);
  const groupIds = items.map(x => x.id);
  reload();
  // reload 完成后提示是否还有同组商品未消失（多为品牌待核对）
  setTimeout(() => {
    const remain = PMF.rows.filter(r => groupIds.includes(r.id)).length;
    toast(n ? `已统一 ${n} 个商品：${series}${remain ? `；还有 ${remain} 个因品牌等其他问题仍在列表中，可在明细继续处理` : ''}` : '保存完成');
  }, 500);
}

async function showCands(id, useQiandao) {
  PMF.candRowId = id;
  const box = $e('pmfCandBody');
  $e('pmfCandTitle').textContent = '查询候选中…';
  box.innerHTML = '<div class="pmf-empty">正在查询…（千岛需联网，稍候）</div>';
  $e('pmfCandMask').style.display = 'flex';
  const r = PMF.rows.find(x => x.id === id);
  try {
    const d = await api('product_meta_candidates.php', { product_id: id, use_qiandao: !!useQiandao });
    if (!d.success) throw new Error(d.error || '查询失败');
    const p = d.product || {};
    $e('pmfCandTitle').textContent = p.name + ' — 选择推荐值';
    const ysjpCands = d.ysjp_cands || [];
    const qCands = d.qiandao_cands || [];
    const ysjpHtml = ysjpCands.map((c, i) => `
      <div class="pmf-cand"><div><div class="cn">${esc(c.ip_name || '')} · ${esc(c.series || '')}</div><div class="cc">ysjp 图库</div></div>
      <button class="pmf-btn ok" data-pick="y" data-i="${i}">采用</button></div>`).join('');
    const qHtml = qCands.map((c, i) => `
      <div class="pmf-cand"><div><div class="cn">${esc(c.name || '')}</div><div class="cc">${esc([c.series, c.ip_name].filter(Boolean).join(' · ') || '千岛候选')}</div></div>
      <button class="pmf-btn ok" data-pick="q" data-i="${i}">采用</button></div>`).join('');
    let html = (ysjpHtml ? '<div style="font-weight:700;font-size:13px;margin:6px 0">ysjp 推荐</div>' + ysjpHtml : '')
      + (qHtml ? '<div style="font-weight:700;font-size:13px;margin:12px 0 6px">千岛兜底</div>' + qHtml : '')
      + ((!ysjpHtml && !qHtml) ? '<div class="pmf-empty">没有可用候选，请手动输入品牌/系列后点「应用」</div>' : '');
    if (!useQiandao && !ysjpHtml) {
      html += '<div class="pmf-empty"><button class="pmf-btn" onclick="showCands(' + id + ',true)">用千岛再查一次</button></div>';
    }
    box.innerHTML = html;
    box.querySelectorAll('[data-pick]').forEach(btn => btn.onclick = () => {
      const c = btn.dataset.pick === 'y' ? ysjpCands[+btn.dataset.i] : qCands[+btn.dataset.i];
      if (!c) return;
      pickCand(id, c.ip_name || '', c.series || '');
    });
  } catch (e) {
    box.innerHTML = '<div class="pmf-empty">查询失败：' + esc(e.message) + '</div>';
  }
}

function pickCand(id, brand, series) {
  const b = $e('b_' + id), s = $e('s_' + id);
  if (b && brand) b.value = brand;
  if (s && series) s.value = series;
  hideCands();
  toast('已填入推荐值，可再修改后点「应用」');
}
function hideCands() { $e('pmfCandMask').style.display = 'none'; }

reload();
</script>
</body>
</html>
