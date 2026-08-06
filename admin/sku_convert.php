<?php
$pageTitle = 'SKU转换';
$currentPage = 'sku_convert';
require_once __DIR__ . '/layout.php';
$canSeeProfit = $currentUser['can_see_profit'] ?? true;
?>
        <div class="page-title">SKU转换</div>

        <!-- 搜索栏 -->
        <div class="scan-bar" style="margin-bottom:20px;">
            <div class="scan-bar-inner">
                <input type="text" id="scanInput" placeholder="扫描条码或输入拼音搜索商品..." class="scan-input">
                <div class="search-dropdown" id="searchDropdown"></div>
            </div>
        </div>

        <!-- 搜索到商品后显示 -->
        <div id="convertArea" style="display:none;">

            <!-- 商品信息栏 -->
            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span style="font-size:20px; font-weight:700;" id="productTitle"></span>
                        <span style="color:var(--text-tertiary); margin-left:12px; font-size:14px;" id="productBarcode"></span>
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="resetProduct()">换商品</button>
                </div>
            </div>

            <!-- 三栏 -->
            <div style="display:flex; gap:20px; align-items:flex-start;">

                <!-- 左栏：来源SKU -->
                <div style="flex:1;">
                    <div style="font-size:14px; color:var(--text-secondary); margin-bottom:12px; font-weight:600;">
                        📤 来源SKU（扣减库存）
                    </div>
                    <div id="sourceCards"></div>
                </div>

                <!-- 中栏：操作 -->
                <div class="card" style="width:280px; flex-shrink:0; text-align:center;">
                    <div style="font-size:48px; color:var(--text-tertiary); margin-bottom:8px;">⬇</div>
                    <div class="form-group" style="text-align:left;">
                        <label class="form-label">转换数量</label>
                        <input type="number" class="form-input" id="convertQty" min="1" value="1"
                            oninput="updatePreview()" style="text-align:center; font-size:24px; font-weight:700;">
                        <div id="qtyHint" style="font-size:12px; color:var(--text-tertiary); margin-top:4px; text-align:center;"></div>
                    </div>
                    <div class="form-group" style="text-align:left;">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-input" id="remark" placeholder="转换原因（选填）">
                    </div>
                    <div id="convertPreview" style="background:var(--bg-hover); padding:12px; border-radius:10px; margin:12px 0; display:none; font-size:13px; text-align:left;">
                        <div style="margin-bottom:6px;">
                            <span style="color:var(--danger);">− </span><span id="previewSource"></span>
                        </div>
                        <div>
                            <span style="color:var(--success);">+ </span><span id="previewTarget"></span>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="confirmConvert()" id="convertBtn" disabled style="width:100%; padding:14px; font-size:16px;">
                        确认转换
                    </button>
                </div>

                <!-- 右栏：目标SKU -->
                <div style="flex:1;">
                    <div style="font-size:14px; color:var(--text-secondary); margin-bottom:12px; font-weight:600;">
                        📥 目标SKU（增加库存）
                    </div>
                    <div id="targetCards"></div>
                </div>

            </div>
        </div>

        <style>
        .scan-bar {
            position: relative;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
        }
        .scan-bar-inner {
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }
        .scan-input {
            width: 400px; font-size: 18px; padding: 10px 16px;
            border: 2px solid var(--border); border-radius: 8px;
            background: var(--bg-card); color: var(--text); outline: none;
            transition: border-color 0.2s; box-sizing: border-box;
        }
        .scan-input:focus { border-color: var(--primary); }
        .search-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0; right: 0;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            display: none;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            z-index: 200;
        }
        .search-dropdown.show { display: block; }
        .search-dropdown-empty {
            padding: 30px; text-align: center; color: var(--text-tertiary); font-size: 14px;
        }
        .search-dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px; border-bottom: 1px solid var(--border);
            font-size: 13px; transition: background 0.15s; cursor: pointer;
        }
        .search-dropdown-item:last-child { border-bottom: none; }
        .search-dropdown-item:hover { background: var(--bg-hover); }

        .sku-card {
            background: var(--bg-elevated);
            border: 2px solid var(--border);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .sku-card:hover { border-color: var(--primary); transform: translateY(-1px); }
        .sku-card.source-selected {
            border-color: var(--danger);
            background: rgba(248,113,113,0.08);
            box-shadow: 0 0 0 3px rgba(248,113,113,0.15);
        }
        .sku-card.target-selected {
            border-color: var(--success);
            background: rgba(52,211,153,0.08);
            box-shadow: 0 0 0 3px rgba(52,211,153,0.15);
        }
        .sku-card.source-zero {
            opacity: 0.35;
            cursor: not-allowed;
            pointer-events: none;
        }
        .sku-card .stock-num {
            font-size: 42px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
        }
        .sku-card .sku-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .sku-card .sku-prices {
            font-size: 13px;
            color: var(--text-tertiary);
            display: flex;
            gap: 16px;
        }
        .sku-card .select-badge {
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            display: none;
        }
        .sku-card.source-selected .select-badge {
            display: block;
            background: var(--danger);
            color: white;
        }
        .sku-card.target-selected .select-badge {
            display: block;
            background: var(--success);
            color: white;
        }
        </style>

        <script>
        const CAN_SEE_PROFIT = <?= $canSeeProfit ? 'true' : 'false' ?>;
        let currentProduct = null;
        let skuStocks = [];
        let sourceSKU = null;
        let targetSKU = null;
        let conditionNameMap = {};
        let searchTimer = null;
        let searchResults = [];

        document.getElementById('scanInput').addEventListener('input', function() {
            const v = this.value.trim();
            clearTimeout(searchTimer);
            if (!v) { hideSearchDropdown(); return; }
            searchTimer = setTimeout(() => {
                /^\d{4,}$/.test(v) ? handleScan(v) : searchPinyin(v);
            }, /^\d{4,}$/.test(v) ? 200 : 300);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideSearchDropdown();
        });

        async function loadConditionNames() {
            try {
                const res = await fetch('../api/get_settings.php');
                const data = await res.json();
                if (data.success && data.data && data.data.condition_types) {
                    data.data.condition_types.forEach(t => { conditionNameMap[t.key] = t.name; });
                }
            } catch (e) {}
            if (!Object.keys(conditionNameMap).length) {
                conditionNameMap = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };
            }
        }

        function getCN(k) { return conditionNameMap[k] || k; }

        async function handleScan(barcode) {
            try {
                const res = await fetch('../api/search_stock.php?barcode=' + encodeURIComponent(barcode));
                const data = await res.json();
                if (data.success && data.data && data.data.length > 0) {
                    buildProduct(data.data);
                } else { alert('未找到该条码的商品库存'); }
            } catch (e) { alert('查询失败'); }
        }

        function searchPinyin(kw) {
            fetch('../api/search_outbound_stock.php?keyword=' + encodeURIComponent(kw))
                .then(r => r.json()).then(data => {
                    searchResults = data.success && data.data ? data.data : [];
                    showSearchDropdown();
                }).catch(() => { searchResults = []; showSearchDropdown(); });
        }

        function showSearchDropdown() {
            const dd = document.getElementById('searchDropdown');
            if (!searchResults.length) {
                dd.innerHTML = '<div class="search-dropdown-empty">未找到匹配商品</div>';
                dd.classList.add('show'); return;
            }
            const seen = {};
            const prods = searchResults.filter(b => { if (seen[b.product_id]) return false; seen[b.product_id]=true; return true; });
            dd.innerHTML = prods.map(p =>
                `<div class="search-dropdown-item" onclick="selectProduct(${p.product_id})">
                    <span style="font-weight:600;">${escHtml(p.common_name || p.product_name)}</span>
                    <span style="color:var(--text-tertiary); margin-left:4px;">${escHtml(p.series||'')}</span>
                    <span style="color:var(--text-quaternary); font-size:12px; margin-left:auto;">${escHtml(p.barcode||'')}</span>
                </div>`
            ).join('');
            dd.classList.add('show');
        }

        function hideSearchDropdown() { document.getElementById('searchDropdown').classList.remove('show'); }

        async function selectProduct(pid) {
            hideSearchDropdown();
            document.getElementById('scanInput').value = '';
            try {
                const res = await fetch('../api/get_product_stock.php?product_id=' + pid);
                const data = await res.json();
                if (!data.success || !data.data || !data.data.batches || !data.data.batches.length) {
                    alert('该商品暂无库存'); return;
                }
                buildProduct(data.data.batches);
            } catch (e) { alert('加载库存失败'); }
        }

        function buildProduct(batches) {
            const skuMap = {};
            batches.forEach(b => {
                const k = b.condition_type;
                if (!skuMap[k]) skuMap[k] = { condition_type: k, total: 0, purchase_prices: [], suggested_prices: [] };
                skuMap[k].total += parseInt(b.remaining_qty);
                if (parseFloat(b.purchase_price) > 0) skuMap[k].purchase_prices.push(parseFloat(b.purchase_price));
                if (parseFloat(b.suggested_price) > 0) skuMap[k].suggested_prices.push(parseFloat(b.suggested_price));
            });
            skuStocks = Object.values(skuMap).map(s => ({
                condition_type: s.condition_type,
                condition_name: getCN(s.condition_type),
                total_stock: s.total,
                purchase_price: s.purchase_prices.length ? Math.min(...s.purchase_prices) : 0,
                suggested_price: s.suggested_prices.length ? Math.max(...s.suggested_prices) : 0
            }));

            // 补齐零库存的SKU类型（目标可选择零库存SKU）
            const existingTypes = new Set(skuStocks.map(s => s.condition_type));
            for (const [key, name] of Object.entries(conditionNameMap)) {
                if (!existingTypes.has(key)) {
                    skuStocks.push({
                        condition_type: key,
                        condition_name: name,
                        total_stock: 0,
                        purchase_price: 0,
                        suggested_price: 0
                    });
                }
            }

            currentProduct = {
                product_id: batches[0].product_id,
                product_name: batches[0].product_name,
                common_name: batches[0].common_name,
                series: batches[0].series,
                barcode: batches[0].barcode
            };

            // 自动选择：来源=第一个有库存的SKU，目标=另一个SKU
            const srcIdx = skuStocks.findIndex(s => s.total_stock > 0);
            sourceSKU = srcIdx >= 0 ? skuStocks[srcIdx].condition_type : null;
            targetSKU = skuStocks.find(s => s.condition_type !== sourceSKU)?.condition_type || null;
            document.getElementById('convertQty').value = 1;

            document.getElementById('productTitle').textContent =
                (currentProduct.common_name || currentProduct.product_name) +
                (currentProduct.series ? ' · ' + currentProduct.series : '');
            document.getElementById('productBarcode').textContent = currentProduct.barcode || '';
            document.getElementById('convertArea').style.display = 'block';
            document.getElementById('scanInput').value = '';
            hideSearchDropdown();
            renderAll();
        }

        function renderAll() {
            // 来源卡片 — 零库存不可点
            document.getElementById('sourceCards').innerHTML = skuStocks.length
                ? skuStocks.map(s => renderCard(s, 'source')).join('')
                : '<div style="text-align:center; color:var(--text-tertiary); padding:30px;">无库存数据</div>';

            // 目标卡片 — 零库存可点
            document.getElementById('targetCards').innerHTML = skuStocks.length
                ? skuStocks.map(s => renderCard(s, 'target')).join('')
                : '<div style="text-align:center; color:var(--text-tertiary); padding:30px;">无库存数据</div>';

            updatePreview();
        }

        function renderCard(sku, side) {
            const isZero = sku.total_stock <= 0;
            const isSelected = side === 'source' ? sourceSKU === sku.condition_type : targetSKU === sku.condition_type;

            let cls = 'sku-card';
            // 来源：零库存置灰不可点；目标：零库存可点
            if (side === 'source' && isZero) cls += ' source-zero';
            if (side === 'source' && isSelected) cls += ' source-selected';
            if (side === 'target' && isSelected) cls += ' target-selected';

            const badge = side === 'source' ? '来源' : '目标';
            const stockColor = isZero ? 'var(--text-quaternary)' :
                (side === 'source' ? 'var(--danger)' : 'var(--success)');

            const onClick = (side === 'source' && isZero) ? '' :
                `onclick="onSelect${side === 'source' ? 'Source' : 'Target'}('${escHtml(sku.condition_type)}')"`;

            return `<div class="${cls}" ${onClick}>
                <span class="select-badge">${badge}</span>
                <div class="stock-num" style="color:${stockColor}">${sku.total_stock}</div>
                <div class="sku-name">${escHtml(sku.condition_name)}</div>
                <div class="sku-prices">
                    ${CAN_SEE_PROFIT ? `<span>进价 ¥${sku.purchase_price.toFixed(2)}</span>` : ''}
                    <span>售价 ¥${sku.suggested_price.toFixed(2)}</span>
                </div>
            </div>`;
        }

        function onSelectSource(ctype) {
            sourceSKU = sourceSKU === ctype ? null : ctype;
            if (targetSKU === ctype) targetSKU = null;
            renderAll();
        }

        function onSelectTarget(ctype) {
            targetSKU = targetSKU === ctype ? null : ctype;
            if (sourceSKU === ctype) sourceSKU = null;
            renderAll();
        }

        function updatePreview() {
            const src = skuStocks.find(s => s.condition_type === sourceSKU);
            const tgt = skuStocks.find(s => s.condition_type === targetSKU);
            const qty = parseInt(document.getElementById('convertQty').value) || 1;

            document.getElementById('qtyHint').textContent = src ? '可转换: 1 ~ ' + src.total_stock : '';

            const preview = document.getElementById('convertPreview');
            const btn = document.getElementById('convertBtn');
            if (sourceSKU && targetSKU && sourceSKU !== targetSKU && qty > 0 && src && qty <= src.total_stock) {
                preview.style.display = 'block';
                document.getElementById('previewSource').textContent =
                    src.condition_name + '  ' + src.total_stock + ' → ' + (src.total_stock - qty);
                document.getElementById('previewTarget').textContent =
                    tgt.condition_name + '  ' + (tgt ? tgt.total_stock : 0) + ' → ' + (tgt ? tgt.total_stock + qty : qty);
                btn.disabled = false;
            } else {
                preview.style.display = 'none';
                btn.disabled = true;
            }
        }

        function resetProduct() {
            currentProduct = null; skuStocks = []; sourceSKU = null; targetSKU = null;
            document.getElementById('convertArea').style.display = 'none';
            document.getElementById('scanInput').value = '';
            document.getElementById('scanInput').focus();
            updatePreview();
        }

        async function confirmConvert() {
            if (!sourceSKU || !targetSKU || !currentProduct) return;
            const src = skuStocks.find(s => s.condition_type === sourceSKU);
            const tgt = skuStocks.find(s => s.condition_type === targetSKU);
            if (!src || !tgt || src.condition_type === tgt.condition_type) { alert('来源和目标不能相同'); return; }
            const qty = parseInt(document.getElementById('convertQty').value);
            if (!qty || qty <= 0) { alert('请输入有效的转换数量'); return; }
            if (qty > src.total_stock) { alert('来源SKU库存不足'); return; }
            const remark = document.getElementById('remark').value.trim() || 'SKU转换';
            if (!confirm('确认将 ' + src.condition_name + ' 的 ' + qty + ' 件转换到 ' + tgt.condition_name + '？')) return;

            const btn = document.getElementById('convertBtn');
            btn.disabled = true; btn.textContent = '转换中...';
            try {
                const res = await fetch('../api/convert_sku.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_product_id: currentProduct.product_id,
                        source_condition_type: sourceSKU,
                        target_product_id: currentProduct.product_id,
                        target_condition_type: targetSKU,
                        qty: qty, remark: remark
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert('转换成功！\n' + src.condition_name + ': ' + data.data.source_before_qty + ' → ' + data.data.source_after_qty +
                        '\n' + tgt.condition_name + ': ' + data.data.target_before_qty + ' → ' + data.data.target_after_qty);
                    try {
                        const r2 = await fetch('../api/get_product_stock.php?product_id=' + currentProduct.product_id);
                        const d2 = await r2.json();
                        if (d2.success && d2.data && d2.data.batches) buildProduct(d2.data.batches);
                    } catch (e) {}
                } else {
                    alert('转换失败: ' + (data.error || '未知错误'));
                }
            } catch (e) { alert('请求失败: ' + e.message); }
            finally { btn.disabled = false; btn.textContent = '确认转换'; }
        }

        function escHtml(s) {
            if (!s) return '';
            const d = document.createElement('div'); d.textContent = s; return d.innerHTML;
        }

        loadConditionNames();
        document.getElementById('scanInput').focus();
        </script>
