<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$pageTitle = '商品管理';
$currentPage = 'products';
require_once __DIR__ . '/layout.php';
$canSeeProfit = $currentUser['can_see_profit'] ?? true;
$isSuper = ($currentUser['role'] === 'super_admin');
$isOperator = ($currentUser['role'] === 'operator');
?>
<div class="page-title">🏷️ 商品管理</div>

<!-- 统计卡 -->
<div class="pm-stats">
    <div class="pm-stat g1"><div class="pm-lbl">商品总数</div><div class="pm-num" id="sTotal">-</div></div>
    <div class="pm-stat g2"><div class="pm-lbl">有库存商品</div><div class="pm-num" id="sActive">-</div></div>
    <div class="pm-stat g3"><div class="pm-lbl">库存总量</div><div class="pm-num" id="sStock">-<span class="pm-unit">件</span></div></div>
    <div class="pm-stat g4"><div class="pm-lbl">缺货 / 低库存</div><div class="pm-num" id="sWarn">-</div></div>
    <?php if ($canSeeProfit): ?>
    <div class="pm-stat g5"><div class="pm-lbl">库存成本</div><div class="pm-num" id="sCost">¥-</div></div>
    <div class="pm-stat g6"><div class="pm-lbl">库存价值</div><div class="pm-num" id="sValue">¥-</div></div>
    <?php else: ?>
    <div class="pm-stat g5"><div class="pm-lbl">库存价值</div><div class="pm-num" id="sValue">¥-</div></div>
    <?php endif; ?>
</div>

<!-- 工具栏 -->
<div class="card pm-toolbar">
    <div class="search-bar pm-search">
        <input type="text" id="searchInput" placeholder="搜索商品名称、常用名、条码、拼音..." oninput="searchProducts()">
        <select id="seriesFilter" onchange="searchProducts()">
            <option value="">全部系列</option>
        </select>
        <label class="pm-check" title="仅显示有库存">
            <input type="checkbox" id="stockFilter" onchange="searchProducts()">
            仅看有库存
        </label>
        <label class="pm-check" title="仅显示存在 SKU 均价与最新售价不一致（多批次定价不同）的商品">
            <input type="checkbox" id="priceDiffFilter" onchange="searchProducts()">
            仅看价差
        </label>
    </div>
    <button class="btn btn-secondary" onclick="openImportModal()">批量导入</button>
    <?php if (!$isOperator): ?>
    <button class="btn btn-secondary" onclick="exportInventory()">导出库存</button>
    <?php endif; ?>
    <button class="btn btn-warning" onclick="openAuditModal()">库存盘点</button>
    <button class="btn btn-danger pm-hidden" id="batchDeleteBtn" onclick="batchDelete()">批量删除 (<span id="selectedCount">0</span>)</button>
    <button class="btn btn-primary" onclick="openAddModal()">新建商品</button>
</div>

<!-- 商品表格 -->
<div class="card pm-table-card">
    <table>
        <thead>
            <tr>
                <th style="width:38px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)" title="全选"></th>
                <th class="pm-no-sort" style="width:60px;">图片</th>
                <th class="pm-sortable" onclick="sortProductsBy('name')">商品名称 / 条码 <span class="pm-arrow" id="ar-name"></span></th>
                <th class="pm-sortable" onclick="sortProductsBy('series')">系列 <span class="pm-arrow" id="ar-series"></span></th>
                <th class="pm-sortable" onclick="sortProductsBy('brand')">品牌 <span class="pm-arrow" id="ar-brand"></span></th>
                <th>在库数量</th>
                <th>最新售价</th>
                <th title="按在库批次数量加权的均价">均价</th>
                <th class="pm-sortable" onclick="sortProductsBy('stock')">库存 <span class="pm-arrow" id="ar-stock"></span></th>
                <th class="pm-no-sort">操作</th>
            </tr>
        </thead>
        <tbody id="productList"></tbody>
    </table>
    <div class="pm-empty pm-hidden" id="emptyState">没有匹配的商品</div>
</div>

<!-- ============ 详情抽屉 ============ -->
<div class="pm-mask" id="drawerMask" onclick="closeDrawer()"></div>
<div class="pm-drawer" id="detailDrawer">
    <div class="pm-drawer-head">
        <div>
            <div class="pm-drawer-title" id="dTitle">商品详情</div>
            <div class="pm-drawer-meta" id="dMeta"></div>
        </div>
        <button class="modal-close" onclick="closeDrawer()">&times;</button>
    </div>
    <div class="pm-tabs">
        <div class="pm-tab on" data-t="inv" onclick="switchTab(this)">库存明细</div>
        <div class="pm-tab" data-t="in" onclick="switchTab(this)">入库记录</div>
        <div class="pm-tab" data-t="flow" onclick="switchTab(this)">出入库流水</div>
        <div class="pm-tab" data-t="conv" onclick="switchTab(this)">状态转换</div>
        <div class="pm-tab" data-t="sales" onclick="switchTab(this)">销售记录</div>
    </div>
    <div class="pm-drawer-body" id="dBody"></div>
    <div class="pm-drawer-foot">
        <button class="btn btn-secondary" onclick="openPurchaseModal(currentId)">入库</button>
        <button class="btn btn-primary" onclick="openConvertModal(currentId)">转换</button>
        <button class="btn btn-secondary" onclick="openEditModal(currentId)">编辑商品</button>
    </div>
</div>

<!-- ============ 新建/编辑商品 ============ -->
<div class="modal" id="productModal">
    <div class="modal-content pm-modal-wide">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">新建商品</h3>
            <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
        </div>
        <form id="productForm" onsubmit="saveProduct(event)">
            <input type="hidden" id="productId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">官方商品名称 *</label>
                    <input type="text" class="form-input" id="productName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">常用名称</label>
                    <input type="text" class="form-input" id="productCommonName">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">系列</label>
                    <input type="text" class="form-input" id="productSeries">
                </div>
                <div class="form-group">
                    <label class="form-label">条码</label>
                    <input type="text" class="form-input" id="productBarcode">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">参考价</label>
                    <input type="number" step="0.01" class="form-input" id="qiandaoPrice">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">品牌</label>
                    <input type="text" class="form-input" id="productBrand">
                </div>
            </div>
            <div class="form-group" id="productOfflineGroup" style="display:none;">
                <label class="form-label">线下售价（收银台专用，按品相 / SKU 分别设置）</label>
                <div id="productOfflineRows" style="display:flex;flex-direction:column;gap:8px;"></div>
                <span style="font-size:11px; color:var(--text-tertiary);">留空 = 自动（进价×加价比例）；不同品相可设不同价格。</span>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">发售时间</label>
                    <input type="date" class="form-input" id="releaseDate">
                </div>
                <div class="form-group">
                    <label class="form-label">商品图片</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" class="form-input" id="imageUrl" placeholder="或手动输入URL">
                        <input type="file" id="imageFile" accept="image/*" style="display:none;" onchange="uploadImage(event)">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('imageFile').click()">上传</button>
                    </div>
                    <div id="imagePreview" style="margin-top:10px;">
                        <img id="previewImg" src="" style="max-width:120px; max-height:120px; display:none; border-radius:8px;">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">产品介绍</label>
                <textarea class="form-input" id="productDescription" rows="3" placeholder="请输入产品详细介绍..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">备注</label>
                <textarea class="form-input" id="productRemark" rows="2"></textarea>
            </div>
            <div style="display:flex; gap:15px; margin-top:20px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ 入库 ============ -->
<div class="modal" id="purchaseModal">
    <div class="modal-content pm-modal-wide">
        <div class="modal-header">
            <h3 class="modal-title">入库</h3>
            <button class="modal-close" onclick="closeModal('purchaseModal')">&times;</button>
        </div>
        <form id="purchaseForm" onsubmit="savePurchase(event)">
            <input type="hidden" id="purchaseProductId">
            <div class="form-group">
                <label class="form-label">商品</label>
                <input type="text" class="form-input" id="purchaseProductName" disabled style="background:var(--bg-hover);">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">供应商</label>
                    <input type="text" class="form-input" id="supplier" placeholder="选填">
                </div>
                <div class="form-group">
                    <label class="form-label">批次备注</label>
                    <input type="text" class="form-input" id="purchaseRemark" placeholder="如：8月首批">
                </div>
            </div>
            <div class="pm-sec-title">按状态分别填写（数量留空 = 该状态本次不入货）</div>
            <div class="pm-in-tbl" id="purchaseConditionsContainer"></div>
            <div style="display:flex; gap:15px; margin-top:20px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('purchaseModal')">取消</button>
                <button type="submit" class="btn btn-success">确认入库</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ SKU 转换 ============ -->
<div class="modal" id="convertModal">
    <div class="modal-content pm-modal-wide">
        <div class="modal-header">
            <h3 class="modal-title">拆盒 / SKU 状态转换</h3>
            <button class="modal-close" onclick="closeModal('convertModal')">&times;</button>
        </div>
        <form id="convertForm" onsubmit="saveConvert(event)">
            <input type="hidden" id="convertProductId">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">源商品</label>
                    <input type="text" class="form-input" id="convertProductName" disabled style="background:var(--bg-hover);">
                </div>
                <div class="form-group">
                    <label class="form-label">目标商品 <span style="color:var(--text-tertiary);font-weight:400;">（默认本商品，可跨商品转换）</span></label>
                    <select class="form-input" id="convertTargetProduct"></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">从状态 *</label>
                    <select class="form-input" id="convertFrom"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">到状态 *</label>
                    <select class="form-input" id="convertTo"></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">转换数量 *</label>
                    <input type="number" class="form-input" id="convertQty" value="1" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">说明</label>
                    <input type="text" class="form-input" id="convertNote" placeholder="如：直播拆盒展示">
                </div>
            </div>
            <div style="font-size:12px; color:var(--text-tertiary); margin-bottom:6px;">采用 FIFO：从「从状态」最早批次扣除，追加到「到状态」新批次，全程写 inventory_log，完全可追溯。</div>
            <div style="display:flex; gap:15px; margin-top:20px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('convertModal')">取消</button>
                <button type="submit" class="btn btn-primary">执行转换</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ 批量改价 ============ -->
<div class="modal" id="priceModal">
    <div class="modal-content pm-modal-wide">
        <div class="modal-header">
            <h3 class="modal-title">批量改价</h3>
            <button class="modal-close" onclick="closeModal('priceModal')">&times;</button>
        </div>
        <form id="priceForm" onsubmit="savePrice(event)">
            <input type="hidden" id="priceProductId">
            <div class="form-group">
                <label class="form-label">商品</label>
                <input type="text" class="form-input" id="priceProductName" disabled style="background:var(--bg-hover);">
            </div>
            <div class="form-group">
                <label class="form-label">作用状态</label>
                <select class="form-input" id="priceCondition"></select>
            </div>
            <div class="form-group">
                <label class="form-label">统一售价</label>
                <input type="number" step="0.01" class="form-input" id="priceSuggested" placeholder="留空不改">
            </div>
            <div style="font-size:12px; color:var(--text-tertiary); margin-top:6px;">仅统一售价，不影响各批次进价。</div>
            <div class="form-group" id="offlinePriceGroup" style="display:none;">
                <label class="form-label">线下售价（收银台专用，按品相设置）</label>
                <div id="offlinePriceRows" style="display:flex;flex-direction:column;gap:8px;"></div>
                <div style="font-size:12px; color:var(--text-tertiary); margin-top:6px;">留空 = 自动（进价×加价比例）；配置后入库不覆盖。</div>
            </div>
            <div style="display:flex; gap:15px; margin-top:20px; justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('priceModal')">取消</button>
                <button type="submit" class="btn btn-primary">应用改价</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ 批量导入 ============ -->
<div class="modal" id="importModal">
    <div class="modal-content pm-modal-wide" style="width:min(1280px,98vw); max-width:none; max-height:calc(100vh - 14px); display:flex; flex-direction:column; overflow:hidden; padding:14px 18px;">
        <div class="modal-header">
            <h3 class="modal-title">批量导入（Excel / CSV）</h3>
            <button class="modal-close" onclick="closeImportModal()">&times;</button>
        </div>
        <div id="importHint" style="font-size:12px; color:var(--text-tertiary); margin-bottom:10px; line-height:1.6;">
            支持 .xlsx / .csv，格式与「库存导出」一致：<b>每个商品一行</b>，列 = 商品名称、常用名称、系列、品牌、条码、参考价、发售时间、产品介绍、图片链接，<b>每个 SKU 状态各占 数量 / 进价 / 售价 三列</b>（如：未拆袋数量、未拆袋进价、未拆袋售价…），最后加 供应商、备注。<br>
            有数量的 SKU 才会入库；同条码/同名商品自动匹配补库存，不重复建商品。
        </div>
        <div style="margin-bottom:8px;">
            <button type="button" class="btn btn-sm btn-outline" style="padding:2px 10px;font-size:12px;" onclick="toggleImportHint()">收起/展开格式说明</button>
        </div>
        <div style="display:flex; gap:15px; margin-bottom:15px;">
            <button class="btn btn-secondary" onclick="downloadTemplate()">下载导入模板</button>
            <label class="btn btn-primary" style="cursor:pointer; margin:0;">选择文件
                <input type="file" id="importFile" accept=".csv,.xlsx" style="display:none;" onchange="handleImportFile(this)">
            </label>
        </div>
        <div id="importResult" class="pm-imp-prev"></div>
        <div id="importErr" class="pm-imp-err"></div>
        <div style="display:flex; gap:15px; margin-top:20px; justify-content:flex-end;">
            <button class="btn btn-primary" id="impConfirmBtn" style="display:none;" onclick="doImportCommit()">确认入库</button>
            <button class="btn btn-secondary" onclick="closeImportModal()">关闭</button>
        </div>
    </div>
</div>

<!-- ============ 库存盘点 ============ -->
<div class="modal" id="auditModal">
    <div class="modal-content modal-wide" style="max-width:96vw; max-height:94vh; display:flex; flex-direction:column; overflow:hidden;">
        <div class="modal-header">
            <h3 class="modal-title">库存盘点</h3>
            <button class="modal-close" onclick="closeAuditModal()">&times;</button>
        </div>
        <div style="padding:12px 16px; border-bottom:1px solid var(--border); display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="text" id="auditSearch" placeholder="搜商品名/系列/品牌/条码/拼音" style="flex:1; min-width:200px; padding:8px 12px; border:1px solid var(--border); border-radius:8px; background:var(--bg-elevated); color:var(--text); font-size:14px;" oninput="auditOnSearch(this.value)">
            <div id="auditSegs" style="display:flex; border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                <button data-f="all" class="audit-seg on">全部</button>
                <button data-f="unc" class="audit-seg">未盘完</button>
                <button data-f="trans" class="audit-seg">已转变</button>
                <button data-f="diff" class="audit-seg">有差异</button>
                <button data-f="same" class="audit-seg">一致</button>
                <button data-f="stock" class="audit-seg">仅线上有货</button>
            </div>
            <button class="btn btn-secondary btn-sm" onclick="auditExportCSV()">导出CSV</button>
            <button class="btn btn-secondary btn-sm" onclick="auditClearAll()">清空</button>
            <button class="btn btn-secondary btn-sm" onclick="openAuditModal()">刷新</button>
        </div>
        <div id="auditStats" style="padding:8px 16px; display:flex; gap:10px; flex-wrap:wrap; font-size:13px; border-bottom:1px solid var(--border);"></div>
        <div id="auditContent" style="flex:1; overflow:auto; min-height:200px;"></div>
        <div style="padding:12px 16px; border-top:1px solid var(--border); display:flex; gap:10px; align-items:center;">
            <span id="auditFootTip" style="font-size:12px; color:var(--text-tertiary);">—</span>
            <span style="flex:1"></span>
            <button class="btn btn-primary" id="auditAdjBtn" onclick="auditOpenConfirm()" disabled>批量调整（0）</button>
            <button class="btn btn-secondary" onclick="closeAuditModal()">关闭</button>
        </div>
    </div>
</div>

<!-- 盘点调整确认 -->
<div class="modal" id="auditConfirmModal">
    <div class="modal-content" style="width:560px; max-width:94vw; max-height:88vh; overflow:auto;">
        <div class="modal-header">
            <h3 class="modal-title">确认调整线上库存</h3>
            <button class="modal-close" onclick="closeModal('auditConfirmModal')">&times;</button>
        </div>
        <div style="padding:16px 18px;">
            <div id="auditCfList" style="max-height:300px; overflow:auto; border:1px solid var(--border); border-radius:8px; margin-bottom:12px; background:var(--bg-elevated);"></div>
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; color:var(--text-secondary); margin-bottom:4px;">调整备注（写入库存流水，便于追溯）</label>
                <input type="text" id="auditCfRemark" class="form-input" placeholder="如：月度盘点">
            </div>
            <div style="font-size:12px; color:var(--warning); margin-bottom:12px;">调整会真实改动线上库存且不可撤销，请确认现场已盘点完毕。</div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button class="btn btn-secondary" onclick="closeModal('auditConfirmModal')">取消</button>
                <button class="btn btn-primary" id="auditCfOk" onclick="auditDoAdjust()">确认调整</button>
            </div>
        </div>
    </div>
</div>

<!-- 盘点结果 -->
<div class="modal" id="auditResultModal">
    <div class="modal-content" style="width:560px; max-width:94vw; max-height:80vh; overflow:auto;">
        <div class="modal-header">
            <h3 class="modal-title" id="auditRsTitle">调整结果</h3>
            <button class="modal-close" onclick="closeModal('auditResultModal')">&times;</button>
        </div>
        <div style="padding:16px 18px;">
            <div id="auditRsList" style="max-height:340px; overflow:auto;"></div>
            <div style="display:flex; justify-content:flex-end; margin-top:12px;">
                <button class="btn btn-primary" onclick="closeModal('auditResultModal')">知道了</button>
            </div>
        </div>
    </div>
</div>

<!-- 盘点专用样式 -->
<style>
.audit-seg { padding:7px 11px; font-size:12px; border:none; background:transparent; color:var(--text-secondary); cursor:pointer; }
.audit-seg + .audit-seg { border-left:1px solid var(--border); }
.audit-seg.on { background:var(--primary); color:#fff; }
.audit-on { font-size:11px; color:var(--text-tertiary); line-height:1.2; }
.audit-qty { width:58px; padding:5px 6px; border:1px solid var(--border); border-radius:6px; background:var(--bg-elevated); color:var(--text); text-align:center; font-size:13px; }
.audit-qty:focus { outline:none; border-color:var(--primary); }
.audit-badge { font-size:12px; border-radius:6px; padding:3px 9px; white-space:nowrap; }
.audit-badge-same { background:rgba(52,211,153,.15); color:var(--success); }
.audit-badge-diff { background:rgba(248,113,113,.15); color:var(--danger); }
.audit-badge-part, .audit-badge-unc { background:var(--bg-hover); color:var(--text-tertiary); }
.audit-badge-trans { background:rgba(251,191,36,.15); color:var(--warning); }
/* 行变色（参考工具箱：一致绿/差异红/已转变黄/未盘灰） */
.audit-tr-same { background:rgba(52,211,153,.05); }
.audit-tr-diff { background:rgba(248,113,113,.07); }
.audit-tr-trans { background:rgba(251,191,36,.07); }
.audit-stat b { font-size:15px; margin-right:4px; }
.audit-stat.diff b { color:var(--danger); }
.audit-stat.trans b { color:var(--warning); }
.audit-stat.same b { color:var(--success); }
.audit-stat.unc b { color:var(--text-tertiary); }
.audit-checks-row td { padding:4px 10px; font-size:11.5px; color:var(--text-tertiary); background:var(--bg-hover); }
.audit-checks-row label { margin-right:12px; cursor:pointer; }
.audit-trans-note { font-size:11.5px; color:var(--warning); }
.audit-cf-item { padding:7px 10px; border-bottom:1px solid var(--border); font-size:13px; }
.audit-cf-item:last-child { border-bottom:none; }
.audit-plus { color:var(--success); font-weight:700; }
.audit-minus { color:var(--danger); font-weight:700; }
.audit-res-item { padding:6px 10px; font-size:12.5px; border-bottom:1px solid var(--border); }
.audit-res-item:last-child { border-bottom:none; }
</style>
<!-- 删除确认框 -->
<div class="modal" id="confirmModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">删除确认</h3>
            <button class="modal-close" onclick="closeModal('confirmModal')">&times;</button>
        </div>
        <div style="padding:18px 0; color:var(--text-secondary);" id="confirmText">确定要删除吗？</div>
        <div style="display:flex; gap:15px; justify-content:flex-end;">
            <button class="btn btn-secondary" onclick="closeModal('confirmModal')">取消</button>
            <button class="btn btn-danger" id="confirmOk">确认删除</button>
        </div>
    </div>
</div>

<style>
/* ===== 商品管理页专属样式（作用域化，不污染全局） ===== */
.pm-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px;}
.pm-stat{background:var(--bg-surface);border:1px solid var(--border);border-radius:12px;padding:16px 18px;}
.pm-stat .pm-lbl{font-size:12.5px;color:var(--text-secondary);margin-bottom:8px;}
.pm-stat .pm-num{font-size:26px;font-weight:800;letter-spacing:-.5px;color:var(--text);}
.pm-stat .pm-unit{font-size:13px;font-weight:600;color:var(--text-tertiary);margin-left:2px;}
.pm-stat.g1{background:linear-gradient(135deg,#8b5cf6,#7c3aed);border:none;color:#fff;}
.pm-stat.g1 .pm-lbl,.pm-stat.g1 .pm-unit{color:rgba(255,255,255,.85);}
.pm-stat.g2{background:linear-gradient(135deg,#667eea,#764ba2);border:none;color:#fff;}
.pm-stat.g2 .pm-lbl,.pm-stat.g2 .pm-unit{color:rgba(255,255,255,.85);}
.pm-stat.g3{background:linear-gradient(135deg,#10b981,#059669);border:none;color:#fff;}
.pm-stat.g3 .pm-lbl,.pm-stat.g3 .pm-unit{color:rgba(255,255,255,.85);}
.pm-stat.g4{background:linear-gradient(135deg,#f59e0b,#d97706);border:none;color:#fff;}
.pm-stat.g4 .pm-lbl,.pm-stat.g4 .pm-unit{color:rgba(255,255,255,.85);}
.pm-stat.g5{background:linear-gradient(135deg,#06b6d4,#0891b2);border:none;color:#fff;}
.pm-stat.g5 .pm-lbl,.pm-stat.g5 .pm-unit{color:rgba(255,255,255,.85);}
.pm-stat.g6{background:linear-gradient(135deg,#ef4444,#dc2626);border:none;color:#fff;}
.pm-stat.g6 .pm-lbl,.pm-stat.g6 .pm-unit{color:rgba(255,255,255,.85);}

.pm-toolbar{display:flex;flex-wrap:nowrap;gap:10px;align-items:center;padding:14px 16px;margin-bottom:16px;}
.pm-search{flex:1;min-width:200px;margin:0;}
.pm-check{display:inline-flex;align-items:center;gap:4px;margin-left:8px;white-space:nowrap;cursor:pointer;font-size:13px;color:var(--text-secondary);}
.pm-legend{display:flex;gap:12px;font-size:12px;color:var(--text-secondary);align-items:center;}
.pm-legend .pm-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:4px;vertical-align:middle;}
.pm-seg{display:inline-flex;background:var(--bg-hover);border-radius:8px;padding:3px;}
.pm-seg button{border:none;background:transparent;padding:5px 11px;border-radius:6px;font-size:12.5px;color:var(--text-secondary);cursor:pointer;}
.pm-seg button.on{background:var(--bg-active);color:var(--text);box-shadow:0 1px 2px rgba(0,0,0,.2);font-weight:600;}
.pm-hidden{display:none!important;}

.pm-table-card{overflow-x:auto;}
.pm-sortable{cursor:pointer;user-select:none;}
.pm-sortable:hover{background:var(--bg-hover);}
.pm-no-sort{cursor:default;}
.pm-arrow{color:var(--primary);font-size:10px;}
.pm-empty{padding:48px 20px;text-align:center;color:var(--text-tertiary);}

/* 商品名 + 条码 */
.pm-pname{font-weight:600;color:var(--text);}
.pm-pcommon{font-size:12px;color:var(--text-tertiary);}
.pm-barcode{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;color:var(--text-tertiary);margin-top:2px;}
.pm-thumb{width:42px;height:42px;border-radius:8px;background:var(--bg-hover);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--text-tertiary);object-fit:cover;}
.pm-series-tag{display:inline-block;font-size:11.5px;color:var(--text-secondary);background:var(--bg-hover);border-radius:6px;padding:1px 7px;margin-top:3px;}
.pm-stock-total{font-weight:800;font-size:15px;color:var(--text);}
.pm-warn-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px;vertical-align:middle;}
.pm-warn-dot.low{background:var(--warning);}
.pm-warn-dot.out{background:var(--danger);}
.pm-warn-dot.ok{background:var(--success);}
.pm-price{font-variant-numeric:tabular-nums;font-weight:600;color:var(--success);}
.pm-imp-act{display:inline-block;padding:1px 8px;border-radius:4px;font-size:11px;font-weight:600;white-space:nowrap}
.pm-imp-act.new{background:rgba(56,189,248,.16);color:#38bdf8}
.pm-imp-act.match{background:rgba(52,211,153,.16);color:#34d399}
.pm-imp-act.skip{background:rgba(248,113,113,.16);color:#f87171}
/* SKU 明细三列：每 SKU 一行，固定行高保证跨列对齐 */
.pm-sku-line{display:flex;align-items:center;gap:8px;min-height:28px;white-space:nowrap;}
.pm-sku-line .condition-badge{min-width:72px;text-align:center;}
.pm-sku-qty{font-variant-numeric:tabular-nums;font-weight:700;font-size:15px;color:var(--text);}
.pm-sku-empty{font-size:12px;color:var(--text-tertiary);}
/* 均价与最新售价不一致：橙色 + 虚线下划线 + 悬停解释 */
.pm-sku-line.pm-avg-diff{color:var(--warning);text-decoration:underline dotted;text-underline-offset:3px;cursor:help;}
/* 操作按钮：3×2 网格 */
.pm-row-actions{display:grid;grid-template-columns:repeat(3,minmax(88px,1fr));gap:5px;width:max-content;max-width:310px;}
.pm-row-actions .btn{width:100%;padding:5px 4px;font-size:12px;white-space:nowrap;text-align:center;}
/* 24小时内新入库商品：柔和青色底（暗黑主题适配，区别于库存警告色） */
.pm-row-newin{background:rgba(56,189,248,0.06);}
.pm-row-newin:hover{background:rgba(56,189,248,0.1);}
.pm-row-newin td:first-child{border-left:3px solid rgba(56,189,248,0.35);}

/* 抽屉 */
.pm-mask{position:fixed;inset:0;background:rgba(0,0,0,.5);opacity:0;pointer-events:none;transition:.2s;z-index:90;}
.pm-mask.show{opacity:1;pointer-events:auto;}
.pm-drawer{position:fixed;top:0;right:0;width:760px;max-width:96vw;height:100vh;background:var(--bg-surface);box-shadow:-8px 0 30px rgba(0,0,0,.4);transform:translateX(100%);transition:.25s;z-index:100;display:flex;flex-direction:column;border-left:1px solid var(--border);}
.pm-drawer.show{transform:translateX(0);}
.pm-drawer-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.pm-drawer-title{font-size:16px;font-weight:700;color:var(--text);}
.pm-drawer-meta{font-size:12px;color:var(--text-tertiary);margin-top:3px;}
.pm-tabs{display:flex;gap:4px;padding:0 14px;border-bottom:1px solid var(--border);}
.pm-tab{padding:11px 12px;font-size:13px;color:var(--text-secondary);cursor:pointer;border-bottom:2px solid transparent;font-weight:600;}
.pm-tab.on{color:var(--primary);border-bottom-color:var(--primary);}
.pm-drawer-body{padding:16px 20px;overflow:auto;flex:1;}
.pm-drawer-foot{padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:15px;justify-content:flex-end;background:var(--bg-elevated);}
.pm-sec-title{font-size:13px;font-weight:700;color:var(--text-secondary);margin:4px 0 12px;}
.pm-kv{display:grid;grid-template-columns:auto 1fr;gap:8px 16px;font-size:13px;margin-bottom:18px;}
.pm-kv .pm-k{color:var(--text-tertiary);}
.pm-kv .pm-v{font-weight:600;color:var(--text);}
.pm-mini-stat{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.pm-mini{flex:1;min-width:90px;background:var(--bg-hover);border:1px solid var(--border);border-radius:10px;padding:10px 12px;}
.pm-mini .pm-l{font-size:11.5px;color:var(--text-tertiary);}
.pm-mini .pm-n{font-size:18px;font-weight:800;margin-top:2px;color:var(--text);}
.pm-log-item{display:flex;gap:10px;padding:10px 0;border-bottom:1px dashed var(--border);font-size:13px;}
.pm-log-item .pm-lt{width:6px;height:6px;border-radius:50%;margin-top:7px;flex-shrink:0;}
.pm-log-item .pm-lt.in{background:var(--success);}
.pm-log-item .pm-lt.out{background:var(--danger);}
.pm-log-item .pm-lt.conv{background:var(--info);}
.pm-log-item .pm-lt.ret{background:var(--warning);}
.pm-log-item .pm-lt.adjust{background:var(--primary);}
.pm-log-item .pm-lc{color:var(--text-secondary);font-size:12px;margin-top:2px;}
.pm-tag{font-size:11px;padding:2px 7px;border-radius:6px;background:var(--bg-active);color:var(--text-secondary);}

/* 入库表 */
.pm-in-tbl{border:1px solid var(--border);border-radius:10px;overflow:hidden;margin:4px 0;}
.pm-in-row{display:grid;grid-template-columns:1.2fr .9fr .9fr .9fr;gap:8px;align-items:center;padding:8px 10px;border-bottom:1px solid var(--border);}
.pm-in-row:last-child{border-bottom:none;}
.pm-in-head{background:var(--bg-hover);font-size:12px;color:var(--text-secondary);font-weight:600;}
.pm-in-row input{width:100%;padding:7px 8px;border:1px solid var(--border);border-radius:7px;text-align:center;background:var(--bg-elevated);color:var(--text);}
.pm-in-cond{font-size:13px;font-weight:600;}
.pm-imp-prev{max-height:230px;overflow:auto;border:1px solid var(--border);border-radius:10px;margin-top:10px;}
.pm-imp-prev table{width:100%;border-collapse:collapse;font-size:12px;}
.pm-imp-prev th,.pm-imp-prev td{padding:6px 8px;border-bottom:1px solid var(--border);white-space:nowrap;text-align:left;}
.pm-imp-prev th{background:var(--bg-hover);position:sticky;top:0;}
.pm-imp-err{color:var(--danger);font-size:12px;margin-top:8px;white-space:pre-line;}
.pm-modal-wide{max-width:640px;}
#importModal .modal-content{width:min(1280px,98vw); max-width:none; max-height:calc(100vh - 14px); display:flex; flex-direction:column; overflow:hidden;}
#importModal #importResult{flex:1; min-height:0; overflow:auto; max-height:none;}
#importModal #importResult .pm-imp-cmp{white-space:nowrap; border-collapse:collapse; width:100%; font-size:12px;}
#importModal #importResult .pm-imp-cmp th{position:sticky; top:0; z-index:2; background:var(--bg-hover,#eef2ff); padding:6px 10px; text-align:left;}
#importModal #importResult .pm-imp-cmp td{padding:5px 10px; border-top:1px solid var(--border,#e5e7eb); vertical-align:top;}
</style>

<script>
const CAN_SEE_PROFIT = <?= $canSeeProfit ? 'true' : 'false' ?>;
const IS_SUPER = <?= $isSuper ? 'true' : 'false' ?>;
const IS_OPERATOR = <?= $isOperator ? 'true' : 'false' ?>;
const CURRENT_STORE_ID = <?= json_encode($storeId) ?>;
let allProducts = [];
let productSort = { field: null, dir: 'asc' };
let currentId = null;
let currentTab = 'inv';
let systemSettings = {};
let conditionTypes = [];
let selectedIds = new Set();
let currentFiltered = []; // 当前筛选可见的商品（全选只作用于这些）

const $ = id => document.getElementById(id);

function escapeHtml(text) {
    if (!text) return '';
    return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

/* ---------- Toast ---------- */
let toastTimer = null;
function showToast(msg, isError) {
    let t = $('pmToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'pmToast';
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--bg-active);color:var(--text);padding:11px 20px;border-radius:10px;font-size:13.5px;z-index:3000;box-shadow:0 8px 24px rgba(0,0,0,.4);border:1px solid var(--border);transition:opacity .25s;opacity:0;';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.background = isError ? 'var(--danger)' : 'var(--bg-active)';
    t.style.opacity = '1';
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.style.opacity = '0', 2200);
}
function showErrorToast(msg) { showToast(msg, true); }
/* 超管未选店铺时禁止写操作 */
function requireStore() {
    if (IS_SUPER && !CURRENT_STORE_ID) {
        showErrorToast('请先在右上角切换店铺，再执行此操作');
        return false;
    }
    return true;
}

/* ---------- 弹窗 ---------- */
function showModal(id) { $(id).classList.add('show'); }
function closeModal(id) { $(id).classList.remove('show'); }
// 商品页弹窗：点空白处不退出（布局全局默认点遮罩关闭，这里在捕获阶段拦截）
document.addEventListener('click', function (e) {
    const t = e.target;
    if (t && t.classList && t.classList.contains('modal') && t.classList.contains('show')) {
        e.stopPropagation();
    }
}, true);

/* ---------- 状态名称 ---------- */
function getConditionNames() {
    return systemSettings.condition_types ? 
        Object.fromEntries(systemSettings.condition_types.map(c => [c.key, c.name])) :
        { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };
}
function getConditionKeys() {
    return systemSettings.condition_types ? systemSettings.condition_types.map(c => c.key) : ['sealed', 'opened', 'boxless', 'flawed'];
}
function getCN(key) { return (getConditionNames())[key] || key; }
function getCondColor(key) {
    const map = { sealed: 'condition-sealed', opened: 'condition-opened', boxless: 'condition-boxless', flawed: 'condition-flawed' };
    return map[key] || 'condition-opened';
}
/* 从 get_product 返回的 inventory[状态名].batches 聚合所有批次（补 condition_type/total_qty/supplier） */
function getAllBatches(detail) {
    const inv = detail.inventory || {};
    const keyByName = {};
    getConditionKeys().forEach(k => { keyByName[getCN(k)] = k; });
    const out = [];
    Object.keys(inv).forEach(cname => {
        const grp = inv[cname] || {};
        const ckey = keyByName[cname] || cname;
        (grp.batches || []).forEach(b => {
            out.push(Object.assign({}, b, {
                condition_type: b.condition_type || ckey,
                total_qty: (b.total_qty != null ? b.total_qty : b.remaining_qty),
                supplier: b.supplier || ''
            }));
        });
    });
    return out;
}

/* ---------- 加载数据 ---------- */
async function loadSettings() {
    try {
        const res = await fetch('../api/get_settings.php');
        const data = await res.json();
        if (data.success) systemSettings = data.data || {};
    } catch (e) { console.error('loadSettings', e); }
}

async function loadProducts() {
    try {
        const res = await fetch('../api/list_products.php');
        const data = await res.json();
        allProducts = data.data.products;
        const seriesSelect = $('seriesFilter');
        const prevSeries = seriesSelect.value; // 保留筛选值
        seriesSelect.innerHTML = '<option value="">全部系列</option>';
        (data.data.series_list || []).forEach(s => {
            const opt = document.createElement('option');
            opt.value = s; opt.textContent = s;
            seriesSelect.appendChild(opt);
        });
        if (prevSeries) seriesSelect.value = prevSeries; // 恢复筛选值
        renderProducts(allProducts);
        loadStats();
        // 保留当前筛选/搜索条件（编辑保存后不重置筛选）
        searchProducts();
    } catch (err) { console.error(err); showErrorToast('商品列表加载失败'); }
}

async function loadStats() {
    try {
        const res = await fetch('../api/stock_overview.php');
        const data = await res.json();
        if (data.success) {
            $('sTotal').textContent = allProducts.length;
            $('sActive').textContent = data.data.types || 0;
            $('sStock').innerHTML = (data.data.total_qty || 0) + '<span class="pm-unit">件</span>';
            $('sValue').textContent = '¥' + parseFloat(data.data.total_value || 0).toLocaleString();
            if (CAN_SEE_PROFIT) $('sCost').textContent = '¥' + parseFloat(data.data.total_cost || 0).toLocaleString();
        }
        const warnCount = allProducts.filter(p => { const t = getTotalStock(p.inventory_summary); return t === 0 || t <= 5; }).length;
        $('sWarn').textContent = warnCount;
    } catch (err) { console.error(err); }
}

/* ---------- 分段控件 ---------- */
function setSeg(id, el) {
    [...$(id).children].forEach(b => b.classList.remove('on'));
    el.classList.add('on');
}
function selVal(id) {
    const el = [...$(id).children].find(b => b.classList.contains('on'));
    return el ? el.dataset.v : 'all';
}

/* ---------- 排序 ---------- */
function clearSortIndicators() {
    document.querySelectorAll('.pm-arrow').forEach(a => a.textContent = '');
}
function sortProductsBy(field) {
    if (productSort.field === field) productSort.dir = productSort.dir === 'asc' ? 'desc' : 'asc';
    else { productSort.field = field; productSort.dir = 'asc'; }
    clearSortIndicators();
    const ar = $('ar-' + field);
    if (ar) ar.textContent = productSort.dir === 'asc' ? '▲' : '▼';
    searchProducts();
}
function applySort(products) {
    const f = productSort.field;
    if (!f) return products;
    return products.sort((a, b) => {
        let va, vb;
        switch (f) {
            case 'name': va = (a.common_name || a.name || '').toLowerCase(); vb = (b.common_name || b.name || '').toLowerCase(); break;
            case 'series': va = (a.series || '').toLowerCase(); vb = (b.series || '').toLowerCase(); break;
            case 'brand': va = (a.brand || '').toLowerCase(); vb = (b.brand || '').toLowerCase(); break;
            case 'stock': va = getTotalStock(a.inventory_summary); vb = getTotalStock(b.inventory_summary); break;
            case 'suggested_price': va = parseFloat(a.overall_suggested_price) || 0; vb = parseFloat(b.overall_suggested_price) || 0; break;
            default: return 0;
        }
        if (typeof va === 'string') return productSort.dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        return productSort.dir === 'asc' ? va - vb : vb - va;
    });
}

/* ---------- 搜索/渲染 ---------- */
function searchProducts() {
    const keyword = $('searchInput').value.toLowerCase().trim();
    const series = $('seriesFilter').value;
    const stockOnly = $('stockFilter').checked;
    const priceDiffOnly = $('priceDiffFilter').checked;

    const filtered = allProducts.filter(p => {
        const matchKeyword = !keyword ||
            p.name.toLowerCase().includes(keyword) ||
            (p.common_name && p.common_name.toLowerCase().includes(keyword)) ||
            p.barcode.includes(keyword) ||
            (p.pinyin_initials && p.pinyin_initials.toLowerCase().includes(keyword));
        const matchSeries = !series || p.series === series;
        const t = getTotalStock(p.inventory_summary);
        const matchStock = !stockOnly || t > 0;
        const matchPriceDiff = !priceDiffOnly || hasSkuPriceDiff(p.inventory_summary);
        return matchKeyword && matchSeries && matchStock && matchPriceDiff;
    });
    renderProducts(applySort(filtered));
}

// 是否存在 SKU 均价与最新售价不一致（多批次定价不同）
function hasSkuPriceDiff(inventory) {
    if (!inventory) return false;
    return getConditionKeys().some(k => {
        const it = inventory[k];
        if (!it || !(it.total_stock > 0)) return false;
        if (it.latest_price === null || it.latest_price === undefined) return false;
        if (it.avg_price === null || it.avg_price === undefined) return false;
        return Math.abs(parseFloat(it.avg_price) - parseFloat(it.latest_price)) > 0.004;
    });
}

function getTotalStock(inventory) {
    if (!inventory) return 0;
    return Object.values(inventory).reduce((s, it) => s + (it.total_stock || 0), 0);
}

function renderProducts(products) {
    currentFiltered = products; // 记录当前筛选可见的商品
    const tbody = $('productList');
    if (!products.length) {
        tbody.innerHTML = '';
        $('emptyState').classList.remove('pm-hidden');
        return;
    }
    $('emptyState').classList.add('pm-hidden');
    tbody.innerHTML = products.map(p => {
        const t = getTotalStock(p.inventory_summary);
        const sc = t === 0 ? 'out' : t <= 5 ? 'low' : 'ok';
        const imageHtml = p.image_url
            ? `<img src="../${p.image_url}" class="pm-thumb" style="object-fit:cover;">`
            : `<div class="pm-thumb"></div>`;
        const nameHtml = p.common_name
            ? `<div class="pm-pname">${escapeHtml(p.common_name)}</div><div class="pm-pcommon">${escapeHtml(p.name)}</div>`
            : `<div class="pm-pname">${escapeHtml(p.name)}</div>`;
        const barcodeHtml = `<div class="pm-barcode">${escapeHtml(p.barcode)}</div>`;
        const seriesHtml = p.series ? `<span class="pm-series-tag">${escapeHtml(p.series)}</span>` : '<span class="pm-pcommon">-</span>';
        const brandHtml = p.brand ? escapeHtml(p.brand) : '<span class="pm-pcommon">-</span>';
        // SKU 明细三列：同一 SKU 顺序与行高，跨列对齐
        const skuLines = getSkuLines(p.inventory_summary);
        const skuQtyHtml = skuLines.length
            ? skuLines.map(l => `<div class="pm-sku-line"><span class="condition-badge ${getCondColor(l.key)}">${escapeHtml(getCN(l.key))}</span><span class="pm-sku-qty">${l.qty}</span></div>`).join('')
            : '<span class="pm-sku-empty">暂无库存</span>';
        const skuLatestHtml = skuLines.length
            ? skuLines.map(l => `<div class="pm-sku-line pm-price">${fmtSkuPrice(l.latest)}</div>`).join('')
            : '<span class="pm-sku-empty">-</span>';
        // 均价与最新售价不一致（多批次定价不同）→ 橙色高亮 + 悬停说明
        const skuAvgHtml = skuLines.length
            ? skuLines.map(l => {
                const diff = l.avg !== null && l.latest !== null &&
                    Math.abs(parseFloat(l.avg) - parseFloat(l.latest)) > 0.004;
                const cls = diff ? ' pm-avg-diff' : '';
                const tip = diff ? ' title="均价与最新售价不一致（该 SKU 多批次定价不同）"' : '';
                return `<div class="pm-sku-line pm-price${cls}"${tip}>${fmtSkuPriceAvg(l.avg)}</div>`;
            }).join('')
            : '<span class="pm-sku-empty">-</span>';
        const checked = selectedIds.has(p.id) ? 'checked' : '';
        // 24小时内新入库：行底色柔和高亮（区分新入库商品）
        let newInClass = '';
        if (p.latest_purchase_at) {
            const diffMs = Date.now() - new Date(p.latest_purchase_at.replace(' ', 'T')).getTime();
            if (diffMs >= 0 && diffMs <= 24 * 3600 * 1000) newInClass = 'pm-row-newin';
        }
        return `<tr class="${newInClass}">
            <td><input type="checkbox" class="pm-cb" value="${p.id}" ${checked} onchange="toggleSelectOne(${p.id}, this)"></td>
            <td>${imageHtml}</td>
            <td><div style="cursor:pointer;" onclick="openDrawer(${p.id})">${nameHtml}${barcodeHtml}</div></td>
            <td>${seriesHtml}</td>
            <td>${brandHtml}</td>
            <td>${skuQtyHtml}</td>
            <td>${skuLatestHtml}</td>
            <td>${skuAvgHtml}</td>
            <td><span class="pm-warn-dot ${sc}"></span><span class="pm-stock-total">${t}</span></td>
            <td>
                <div class="pm-row-actions">
                    <button class="btn btn-sm btn-primary" onclick="openDrawer(${p.id})">库存</button>
                    <button class="btn btn-sm btn-success" onclick="openPurchaseModal(${p.id})">入库</button>
                    <button class="btn btn-sm btn-secondary" onclick="openConvertModal(${p.id})">转换</button>
                    <button class="btn btn-sm btn-secondary" onclick="openPriceModal(${p.id})">改价</button>
                    <button class="btn btn-sm btn-secondary" onclick="openEditModal(${p.id})">编辑</button>
                    ${IS_OPERATOR ? '' : `<button class="btn btn-sm btn-danger" onclick="showDeleteConfirm([${p.id}], '${escapeHtml(p.name)}')">删除</button>`}
                </div>
            </td>
        </tr>`;
    }).join('');
}

// SKU 明细行数据：按店铺品相配置顺序，仅取有在库的 SKU（三列共用同一顺序对齐）
function getSkuLines(inventory) {
    const lines = [];
    getConditionKeys().forEach(k => {
        const it = inventory && inventory[k];
        const q = (it && it.total_stock) || 0;
        if (q <= 0) return;
        lines.push({ key: k, qty: q, latest: it.latest_price, avg: it.avg_price });
    });
    return lines;
}

function fmtSkuPrice(v) {
    return (v === null || v === undefined || v === '') ? '-' : '¥' + parseFloat(v).toFixed(2);
}

// 均价取整显示：服务端已按「满 0.01 进 1」取整，这里不带小数
function fmtSkuPriceAvg(v) {
    if (v === null || v === undefined || v === '') return '-';
    return '¥' + Math.ceil(parseFloat(v));
}

/* ---------- 详情抽屉 ---------- */
function openDrawer(id) {
    currentId = id;
    currentTab = 'inv';
    document.querySelectorAll('.pm-tab').forEach(t => t.classList.remove('on'));
    document.querySelector('.pm-tab[data-t="inv"]').classList.add('on');
    $('drawerMask').classList.add('show');
    $('detailDrawer').classList.add('show');
    renderDrawer();
}
function closeDrawer() {
    $('drawerMask').classList.remove('show');
    $('detailDrawer').classList.remove('show');
}
function switchTab(el) {
    document.querySelectorAll('.pm-tab').forEach(t => t.classList.remove('on'));
    el.classList.add('on');
    currentTab = el.dataset.t;
    renderDrawer();
}

async function renderDrawer() {
    const p = allProducts.find(x => x.id === currentId);
    if (!p) return;
    $('dTitle').textContent = p.common_name || p.name;
    $('dMeta').textContent = `${p.common_name ? p.name + ' · ' : ''}${p.series || ''} · 条码 ${p.barcode}`;
    const body = $('dBody');

    if (currentTab === 'inv') {
        // 拉取详情（含批次）
        try {
            const res = await fetch('../api/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: currentId })
            });
            const data = await res.json();
            if (!data.success) { body.innerHTML = '<div class="pm-pcommon">加载失败</div>'; return; }
            const detail = data.data;
            const batches = getAllBatches(detail);
            const t = batches.reduce((s, b) => s + (parseInt(b.remaining_qty) || 0), 0);

            const dist = getConditionKeys().map(k => {
                const q = batches.filter(b => b.condition_type === k).reduce((s, b) => s + (parseInt(b.remaining_qty) || 0), 0);
                return q > 0 ? `<div class="pm-mini"><div class="pm-l">${escapeHtml(getCN(k))}</div><div class="pm-n">${q}</div></div>` : '';
            }).join('') || '<div class="pm-mini"><div class="pm-l">暂无库存</div><div class="pm-n">0</div></div>';

            const kv = `
                <div class="pm-kv">
                    <div class="pm-k">参考价</div><div class="pm-v">¥${detail.qiandao_price || '-'}</div>
                    <div class="pm-k">品牌</div><div class="pm-v">${detail.brand || '-'}</div>
                    <div class="pm-k">发售时间</div><div class="pm-v">${detail.release_date || '-'}</div>
                    <div class="pm-k">产品介绍</div><div class="pm-v">${detail.product_description || '-'}</div>
                    <div class="pm-k">备注</div><div class="pm-v">${detail.remark || '-'}</div>
                </div>`;

            const bt = batches.map(b => `<tr>
                <td><span class="condition-badge ${getCondColor(b.condition_type)}">${escapeHtml(getCN(b.condition_type))}</span></td>
                <td class="pm-stock-total">${parseInt(b.remaining_qty) || 0}</td>
                ${CAN_SEE_PROFIT ? `<td class="pm-price">¥${parseFloat(b.purchase_price || 0).toFixed(2)}</td>` : ''}
                <td class="pm-price">¥${parseFloat(b.suggested_price || 0).toFixed(2)}</td>
                <td class="pm-pcommon">${escapeHtml(b.supplier || '-')}</td>
                <td class="pm-pcommon">${escapeHtml(b.purchased_at || b.created_at || '-')}</td>
                <td class="pm-pcommon">${escapeHtml(b.batch_no || '')}</td>
                <td>
                    ${IS_OPERATOR ? '' : `<button class="btn btn-sm btn-secondary" onclick="openEditBatchModal(${b.id}, ${currentId}, '${b.condition_type}')">编辑</button>`}
                </td></tr>`).join('') || '<tr><td colspan="8" class="pm-pcommon" style="text-align:center;padding:20px;">暂无批次，点下方「入库」添加</td></tr>';

            const profitCol = CAN_SEE_PROFIT ? '<th>进价</th>' : '';
            const profitTd = CAN_SEE_PROFIT ? '<th class="profit-col">进价</th>' : '';
            body.innerHTML = `
                <div class="pm-mini-stat">${dist}<div class="pm-mini"><div class="pm-l">库存总量</div><div class="pm-n">${t}</div></div></div>
                ${kv}
                ${CAN_SEE_PROFIT ? '<div class="pm-sec-title">进价走势</div><div id="priceTrendWrap" style="background:var(--bg-hover);border-radius:10px;padding:12px;margin-bottom:16px;">加载中...</div>' : ''}
                <div class="pm-sec-title">批次明细</div>
                <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>状态</th><th>剩余</th>${profitCol}<th>售价</th><th>供应商</th><th>入库时间</th><th>批次号</th>${IS_OPERATOR ? '' : '<th></th>'}</tr></thead>
                    <tbody>${bt}</tbody>
                </table></div>`;
            if (CAN_SEE_PROFIT) loadPriceTrend(detail);
        } catch (err) {
            body.innerHTML = '<div class="pm-pcommon">加载失败: ' + escapeHtml(err.message) + '</div>';
        }
    } else if (currentTab === 'in') {
        try {
            // 入库记录：复用流水接口（含"当前库存"倒推值），只取入库事件
            const res = await fetch('../api/get_product_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: currentId, limit: 200 })
            });
            const data = await res.json();
            const inLogs = (data.success ? data.data.logs : []).filter(l => l.source === 'batch');
            const it = inLogs.map(l => `<div class="pm-log-item"><span class="pm-lt in"></span><div style="flex:1;"><div>入库 · <b>${escapeHtml(l.condition_name)}</b> ×${Math.abs(l.qty_change) || 0}　<span class="pm-tag">${escapeHtml(l.supplier || '-')}</span></div><div class="pm-lc">${escapeHtml(l.created_at || '')} · ${escapeHtml(l.remark || '')}</div></div><span class="pm-tag" style="align-self:center; flex-shrink:0;">当前库存 ${l.current_stock ?? 0} 件</span></div>`).join('') || '<div class="pm-pcommon">暂无入库记录</div>';
            body.innerHTML = `<div class="pm-sec-title">入库记录</div>${it}`;
        } catch (err) {
            body.innerHTML = '<div class="pm-pcommon">加载失败</div>';
        }
    } else if (currentTab === 'flow') {
        body.innerHTML = '<div class="pm-pcommon" style="padding:20px;text-align:center;">加载中...</div>';
        try {
            const res = await fetch('../api/get_product_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: currentId, limit: 100 })
            });
            const data = await res.json();
            const logs = data.success ? data.data.logs : [];
            const colorMap = { purchase: 'in', sale: 'out', outbound: 'out', convert_out: 'conv', convert_in: 'conv', adjust: 'adjust', return: 'ret' };
            const items = logs.map(l => `<div class="pm-log-item">
                <span class="pm-lt ${colorMap[l.change_type] || 'adjust'}"></span>
                <div style="flex:1;">
                    <div>${escapeHtml(l.change_type_name)} · <b>${escapeHtml(l.condition_name)}</b> ×${l.qty_change > 0 ? '+' : ''}${l.qty_change}${l.session_name ? ` <span class="pm-tag">${escapeHtml(l.session_name)}</span>` : ''}${l.operator ? ` <span class="pm-tag">运营 ${escapeHtml(l.operator)}</span>` : ''}${l.account ? ` <span class="pm-tag">账号 ${escapeHtml(l.account)}</span>` : ''}</div>
                    <div class="pm-lc">${escapeHtml(l.created_at || '')}${l.price && CAN_SEE_PROFIT ? ' · ¥' + parseFloat(l.price).toFixed(2) : ''}${l.operator_username ? ` · 操作人：${escapeHtml(l.operator_username)}` : ''}${l.remark ? ' · ' + (l.operator_username ? escapeHtml(l.remark).replace(/^场次：[^·]*/, '') : escapeHtml(l.remark)) : ''}</div>
                </div>
                <span class="pm-tag" style="align-self:center; flex-shrink:0;">当前库存 ${l.current_stock ?? 0} 件</span>
            </div>`).join('') || '<div class="pm-pcommon">暂无流水记录</div>';
            body.innerHTML = `<div class="pm-sec-title">出入库流水</div>${items}`;
        } catch (err) {
            body.innerHTML = '<div class="pm-pcommon">加载失败</div>';
        }
    } else if (currentTab === 'conv') {
        try {
            const res = await fetch('../api/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: currentId })
            });
            const data = await res.json();
            const batches = data.success ? getAllBatches(data.data) : [];
            const dist = getConditionKeys().map(k => {
                const q = batches.filter(b => b.condition_type === k).reduce((s, b) => s + (parseInt(b.remaining_qty) || 0), 0);
                return `<div class="pm-k">${escapeHtml(getCN(k))}</div><div class="pm-v">${q} 件</div>`;
            }).join('');
            body.innerHTML = `
                <div class="pm-sec-title">状态转换（拆盒 / 降级瑕疵）</div>
                <div class="pm-kv">${dist}</div>
                <button class="btn btn-primary" onclick="openConvertModal(${currentId})">新建转换</button>
                <div class="pm-sec-title" style="margin-top:18px;">最近转换</div>
                <div id="convRecent">加载中...</div>`;
            // 从流水里找 convert 记录
            const logRes = await fetch('../api/get_product_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: currentId, limit: 50 })
            });
            const logData = await logRes.json();
            const convs = (logData.success ? logData.data.logs : []).filter(l => l.change_type === 'convert_out' || l.change_type === 'convert_in');
            $('convRecent').innerHTML = convs.length
                ? convs.map(l => `<div class="pm-log-item"><span class="pm-lt conv"></span><div><div>${escapeHtml(l.change_type_name)} · ${escapeHtml(l.condition_name)} ×${Math.abs(l.qty_change)}</div><div class="pm-lc">${escapeHtml(l.created_at || '')} · ${escapeHtml(l.remark || '')}</div></div></div>`).join('')
                : '<div class="pm-pcommon">暂无转换记录</div>';
        } catch (err) {
            body.innerHTML = '<div class="pm-pcommon">加载失败</div>';
        }
    } else if (currentTab === 'sales') {
        body.innerHTML = '<div class="pm-pcommon" style="padding:20px;text-align:center;">加载中...</div>';
        try {
            const res = await fetch('../api/get_product_sales.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: currentId })
            });
            const data = await res.json();
            const sales = data.success ? data.data.sales : [];
            const items = sales.map(s => `<div class="pm-log-item">
                <span class="pm-lt out"></span>
                <div style="flex:1;min-width:0;">
                    <div>
                        <b>${escapeHtml(s.nickname || '(未命名客户)')}</b>
                        ${s.vip_no ? ` <span class="pm-tag">VIP ${escapeHtml(s.vip_no)}</span>` : ''}
                        <span class="pm-tag">${escapeHtml(s.condition_name)}</span>
                        <span class="pm-tag">×${s.qty}</span>
                        <span class="pm-tag">¥${parseFloat(s.amount || 0).toFixed(2)}</span>
                    </div>
                    <div class="pm-lc">${escapeHtml(s.sold_at || '')}${s.session_name ? ' · 场次 ' + escapeHtml(s.session_name) : ''}${s.session_operator ? ' · 运营 ' + escapeHtml(s.session_operator) : ''}${s.session_account ? ' · 账号 ' + escapeHtml(s.session_account) : ''}${s.operator_username ? ' · 操作人：' + escapeHtml(s.operator_username) : ''}</div>
                </div>
                <span class="pm-tag" style="align-self:center; flex-shrink:0;">¥${parseFloat(s.price || 0).toFixed(2)}/件</span>
            </div>`).join('') || '<div class="pm-pcommon">暂无销售记录（直播记账售出会显示客户）</div>';
            body.innerHTML = `<div class="pm-sec-title">销售记录（售出客户）</div>${items}`;
        } catch (err) {
            body.innerHTML = '<div class="pm-pcommon">加载失败: ' + escapeHtml(err.message) + '</div>';
        }
    }
}

async function loadPriceTrend(detail) {
    const wrap = $('priceTrendWrap');
    if (!wrap) return;
    if (!CAN_SEE_PROFIT) { wrap.innerHTML = '<div class="pm-pcommon">运营不可见进价走势</div>'; return; }
    const batches = getAllBatches(detail).filter(b => (b.remark || '') !== 'SKU转换'); // 转换产生的不记录进价走势
    if (!batches.length) { wrap.innerHTML = '<div class="pm-pcommon">暂无批次，入库后可查看进价走势</div>'; return; }

    // 按 SKU(condition_type) 分组
    const groups = {};
    batches.forEach(b => {
        const key = b.condition_type || 'opened';
        if (!groups[key]) groups[key] = [];
        groups[key].push(b);
    });
    const keys = Object.keys(groups);
    // 颜色：按 SKU 顺序取不同色
    const palette = ['#2e90fa', '#f43f5e', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4'];

    const allBs = keys.flatMap(k => groups[k]).sort((a, b) => (a.purchased_at || '').localeCompare(b.purchased_at || ''));
    const costs = allBs.map(b => parseFloat(b.purchase_price) || 0);
    const min = Math.min(...costs), max = Math.max(...costs), span = (max - min) || 1;
    const W = 540, H = 150, padL = 40, padR = 10, padT = 12, padB = 24;
    // x 按全局时间线（所有批次合并排序）
    const xOf = (b) => {
        const idx = allBs.indexOf(b);
        return padL + (W - padL - padR) * (allBs.length === 1 ? 0.5 : idx / (allBs.length - 1));
    };
    const y = v => H - padB - (H - padT - padB) * ((v - min) / span);

    let lines = '', dots = '', legend = '';
    keys.forEach((k, gi) => {
        const color = palette[gi % palette.length];
        const bs = [...groups[k]].sort((a, b) => (a.purchased_at || '').localeCompare(b.purchased_at || ''));
        const c = bs.map(b => parseFloat(b.purchase_price) || 0);
        const path = bs.map((b, i) => `${i ? 'L' : 'M'}${xOf(b).toFixed(1)},${y(c[i]).toFixed(1)}`).join(' ');
        lines += `<path d="${path}" fill="none" stroke="${color}" stroke-width="2"/>`;
        dots += bs.map(b => `<circle cx="${xOf(b).toFixed(1)}" cy="${y(parseFloat(b.purchase_price) || 0).toFixed(1)}" r="3.5" fill="${color}"/>`).join('');
        legend += `<span style="display:inline-flex;align-items:center;margin-right:12px;"><span style="display:inline-block;width:14px;height:3px;background:${color};vertical-align:middle;margin-right:5px;"></span>${escapeHtml(getCN(k))}</span>`;
    });

    const xlab = allBs.map((b, i) => `<text x="${xOf(b).toFixed(1)}" y="${H - 6}" font-size="9" fill="#6b7280" text-anchor="middle">${(b.purchased_at || '').slice(5, 10)}</text>`).join('');
    const ylab = [min, Math.round((min + max) / 2), max].map(v => `<text x="2" y="${(y(v) + 3).toFixed(1)}" font-size="9" fill="#6b7280">¥${v}</text>`).join('');
    wrap.innerHTML = `<svg viewBox="0 0 ${W} ${H}" style="width:100%;height:auto;">
        <line x1="${padL}" y1="${H - padB}" x2="${W - padR}" y2="${H - padB}" stroke="var(--border)"/>
        <line x1="${padL}" y1="${padT}" x2="${padL}" y2="${H - padB}" stroke="var(--border)"/>
        ${lines}${dots}${xlab}${ylab}
    </svg>
    <div style="font-size:11.5px;color:var(--text-tertiary);margin-top:6px;">${legend}<span style="display:inline-flex;align-items:center;"><span style="display:inline-block;width:14px;height:3px;background:#9ca3af;vertical-align:middle;margin-right:5px;"></span>每批进价（SKU转换不记录）</span></div>`;
}

/* ---------- 新建/编辑商品 ---------- */
let editingId = null;
function offlineRowsHtml() {
    return getConditionKeys().map(k => `
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="flex:0 0 90px;font-size:13px;font-weight:600;">${escapeHtml(getCN(k))}</span>
            <input type="number" step="0.01" min="0" class="form-input" data-cond="${k}" placeholder="留空自动" style="max-width:150px;">
        </div>`).join('');
}
// 生成品相行并回显已配置的线下售价（productId 为空=新建，不请求）
async function initProductOfflineRows(productId) {
    const og = $('productOfflineGroup');
    const rows = $('productOfflineRows');
    if (!og || !rows) return;
    if (IS_OPERATOR) { og.style.display = 'none'; return; }
    og.style.display = 'block';
    rows.innerHTML = offlineRowsHtml();
    if (!productId) return;
    try {
        const res = await fetch('../api/get_offline_prices.php?product_id=' + productId);
        const d = await res.json();
        if (d.success && d.configured) {
            rows.querySelectorAll('input[data-cond]').forEach(inp => {
                const v = d.configured[inp.dataset.cond];
                if (v) inp.value = v;
            });
        }
    } catch (err) { /* 配置回显失败不阻塞编辑 */ }
}
// 逐品相保存线下售价（空值 = 恢复自动定价）
async function saveProductOfflineRows(productId) {
    if (IS_OPERATOR) return true;
    const og = $('productOfflineGroup');
    const rows = $('productOfflineRows');
    if (!og || !rows || og.style.display === 'none') return true;
    for (const inp of rows.querySelectorAll('input[data-cond]')) {
        const cond = inp.dataset.cond;
        const v = inp.value;
        try {
            const res = await fetch('../api/save_offline_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, condition_type: cond, offline_price: v !== '' ? parseFloat(v) : null })
            });
            const d = await res.json();
            if (!d.success) { showErrorToast('线下售价保存失败(' + getCN(cond) + '): ' + d.error); return false; }
        } catch (err) {
            showErrorToast('线下售价保存失败(' + getCN(cond) + '): ' + err.message);
            return false;
        }
    }
    return true;
}
function openAddModal() {
    if (!requireStore()) return;
    editingId = null;
    $('modalTitle').textContent = '新建商品';
    $('productForm').reset();
    $('productId').value = '';
    $('productBarcode').value = generateBarcode();
    $('previewImg').style.display = 'none';
    initProductOfflineRows(null);
    showModal('productModal');
}
function generateBarcode() {
    const prefix = '69414486';
    const randomNum = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
    const digits = prefix + randomNum;
    let sum = 0;
    for (let i = 0; i < 12; i++) sum += parseInt(digits[i]) * (i % 2 === 0 ? 1 : 3);
    const checkDigit = (10 - (sum % 10)) % 10;
    return digits + checkDigit;
}

async function openEditModal(id) {
    if (!requireStore()) return;
    try {
        const res = await fetch('../api/get_product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: id })
        });
        const data = await res.json();
        if (!data.success) { showErrorToast('获取商品信息失败'); return; }
        const p = data.data;
        editingId = p.id;
        $('modalTitle').textContent = '编辑商品';
        $('productId').value = p.id;
        $('productName').value = p.name;
        $('productCommonName').value = p.common_name || '';
        $('productSeries').value = p.series || '';
        $('productBarcode').value = p.barcode;
        $('qiandaoPrice').value = p.qiandao_price || '';
        $('productBrand').value = p.brand || '';
        $('releaseDate').value = p.release_date || '';
        $('productDescription').value = p.product_description || '';
        $('imageUrl').value = p.image_url || '';
        $('productRemark').value = p.remark || '';
        if (p.image_url) {
            $('previewImg').src = '../' + p.image_url;
            $('previewImg').style.display = 'block';
        } else {
            $('previewImg').style.display = 'none';
        }
        await initProductOfflineRows(p.id);
        showModal('productModal');
    } catch (err) { console.error(err); showErrorToast('获取商品信息失败'); }
}

async function uploadImage(e) {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('image', file);
    try {
        const res = await fetch('../api/upload_image.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            const url = data.data.url || data.data.path || '';
            $('imageUrl').value = url;
            $('previewImg').src = url ? '../' + url : '';
            $('previewImg').style.display = url ? 'block' : 'none';
            showToast('图片上传成功');
        } else {
            showErrorToast(data.error || '上传失败');
        }
    } catch (err) { showErrorToast('上传失败'); }
}

async function saveProduct(event) {
    event.preventDefault();
    const id = $('productId').value;
    const name = $('productName').value.trim();
    if (!name) { showErrorToast('请填写商品名称'); return; }
    const payload = {
        id: id ? parseInt(id) : null,
        name: name,
        common_name: $('productCommonName').value || null,
        series: $('productSeries').value || null,
        barcode: $('productBarcode').value || null,
        qiandao_price: $('qiandaoPrice').value ? parseFloat($('qiandaoPrice').value) : null,
        brand: $('productBrand').value || null,
        release_date: $('releaseDate').value || null,
        product_description: $('productDescription').value || null,
        image_url: $('imageUrl').value || null,
        remark: $('productRemark').value || null
    };
    try {
        const url = id ? '../api/update_product.php' : '../api/add_product.php';
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            const savedId = id ? parseInt(id) : (data.data && data.data.id ? parseInt(data.data.id) : null);
            if (savedId && !await saveProductOfflineRows(savedId)) {
                showErrorToast('商品信息已保存，线下售价保存失败，请重试保存');
                return;
            }
            showToast(id ? '商品已更新' : '商品已新建');
            closeModal('productModal');
            await loadProducts();
        } else {
            showErrorToast(data.error || '保存失败');
        }
    } catch (err) { showErrorToast('保存失败: ' + err.message); }
}

/* ---------- 入库 ---------- */
let purchaseProductId = null;
let purchaseSaving = false; // 入库防重复提交锁
async function renderPurchaseConditions() {
    const container = $('purchaseConditionsContainer');
    const keys = getConditionKeys();
    container.innerHTML = `
        <div class="pm-in-row pm-in-head"><span>状态</span><span>数量</span>${CAN_SEE_PROFIT ? '<span>进价</span>' : ''}<span>售价</span></div>
        ${keys.map(k => `<div class="pm-in-row">
            <span class="pm-in-cond" style="color:var(--text);">${escapeHtml(getCN(k))}</span>
            <input type="number" min="0" placeholder="0" id="pur_qty_${k}">
            ${CAN_SEE_PROFIT ? `<input type="number" step="0.01" min="0" placeholder="0.00" id="pur_cost_${k}">` : ''}
            <input type="number" step="0.01" min="0" placeholder="0.00" id="pur_price_${k}">
        </div>`).join('')}
    `;
}
function openPurchaseModal(productId) {
    if (!requireStore()) return;
    purchaseProductId = productId;
    const p = allProducts.find(x => x.id === productId);
    if (!p) return;
    $('purchaseProductId').value = productId;
    $('purchaseProductName').value = p.common_name || p.name;
    renderPurchaseConditions();
    const keys = getConditionKeys();
    keys.forEach(k => {
        $('pur_qty_' + k).value = '';
        // 进价框仅超管/店管可见(CAN_SEE_PROFIT)，运营无此元素，跳过避免 null 报错
        if (CAN_SEE_PROFIT && $('pur_cost_' + k)) $('pur_cost_' + k).value = '';
        $('pur_price_' + k).value = '';
    });
    $('supplier').value = '';
    $('purchaseRemark').value = '';
    showModal('purchaseModal');
}
async function savePurchase(e) {
    e.preventDefault();
    // 防重复提交：网络慢时用户连点会重复入库
    if (purchaseSaving) { showToast('正在提交，请稍候...'); return; }
    purchaseSaving = true;
    try {
        await doSavePurchase(e);
    } finally {
        purchaseSaving = false;
    }
}

async function doSavePurchase(e) {
    const productId = purchaseProductId;
    const supplier = $('supplier').value.trim() || null;
    const remark = $('purchaseRemark').value.trim() || null;
    const keys = getConditionKeys();
    let added = 0;
    for (const k of keys) {
        const qty = parseInt($('pur_qty_' + k).value);
        if (!qty || qty <= 0) continue;
        const cost = CAN_SEE_PROFIT ? (parseFloat($('pur_cost_' + k).value) || 0) : 0;
        const price = parseFloat($('pur_price_' + k).value) || 0;
        try {
            const res = await fetch('../api/purchase_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, condition_type: k, qty, purchase_price: cost, suggested_price: price, supplier, remark })
            });
            const data = await res.json();
            if (!data.success) { showErrorToast(`状态「${getCN(k)}」入库失败: ${data.error}`); return; }
            added++;
        } catch (err) { showErrorToast('入库失败: ' + err.message); return; }
    }
    if (added === 0) { showErrorToast('请至少填写一种状态的数量'); return; }
    showToast(`入库成功：新增 ${added} 个批次`);
    closeModal('purchaseModal');
    await loadProducts();
    if (currentId) renderDrawer();
}

/* ---------- SKU 转换 ---------- */
let convertProductId = null;
function openConvertModal(productId) {
    if (!requireStore()) return;
    convertProductId = productId;
    const p = allProducts.find(x => x.id === productId);
    if (!p) return;
    $('convertProductId').value = productId;
    $('convertProductName').value = p.common_name || p.name;
    // 目标商品：默认本商品，可跨商品（含当前库存内的所有商品）
    const targetSel = $('convertTargetProduct');
    targetSel.innerHTML = '<option value="' + productId + '">本商品（同商品状态转换）</option>' + allProducts
        .filter(x => x.id !== productId)
        .map(x => `<option value="${x.id}">${escapeHtml(x.common_name || x.name)}</option>`)
        .join('');
    const keys = getConditionKeys();
    const fromSel = $('convertFrom');
    const toSel = $('convertTo');
    fromSel.innerHTML = keys.map(k => `<option value="${k}">${escapeHtml(getCN(k))}</option>`).join('');
    toSel.innerHTML = keys.map(k => `<option value="${k}">${escapeHtml(getCN(k))}</option>`).join('');
    // 默认 原盒→已拆
    const sealedIdx = keys.indexOf('sealed');
    const openedIdx = keys.indexOf('opened');
    if (sealedIdx >= 0) fromSel.selectedIndex = sealedIdx;
    if (openedIdx >= 0) toSel.selectedIndex = openedIdx;
    $('convertQty').value = 1;
    $('convertNote').value = '';
    showModal('convertModal');
}
async function saveConvert(e) {
    e.preventDefault();
    const from = $('convertFrom').value;
    const to = $('convertTo').value;
    const targetId = parseInt($('convertTargetProduct').value) || convertProductId;
    const qty = parseInt($('convertQty').value);
    if (!qty || qty < 1) { showErrorToast('数量无效'); return; }
    if (from === to && targetId === convertProductId) { showErrorToast('来源与目标状态不能相同'); return; }
    const remark = $('convertNote').value.trim() || 'SKU转换';
    try {
        const res = await fetch('../api/convert_sku.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ source_product_id: convertProductId, source_condition_type: from, target_product_id: targetId, target_condition_type: to, qty, remark })
        });
        const data = await res.json();
        if (!data.success) { showErrorToast(data.error || '转换失败'); return; }
        showToast('转换完成，已写 inventory_log');
        closeModal('convertModal');
        await loadProducts();
        if (currentId) renderDrawer();
    } catch (err) { showErrorToast('转换失败: ' + err.message); }
}

/* ---------- 批量改价 ---------- */
let priceProductId = null;
let offlinePriceConfig = {}; // 商品品相 → 线下售价配置（仅店长/超管加载）
function openPriceModal(productId) {
    if (!requireStore()) return;
    priceProductId = productId;
    const p = allProducts.find(x => x.id === productId);
    if (!p) return;
    $('priceProductId').value = productId;
    $('priceProductName').value = p.common_name || p.name;
    const sel = $('priceCondition');
    sel.innerHTML = '<option value="all">全部状态</option>' + getConditionKeys().map(k => `<option value="${k}">${escapeHtml(getCN(k))}</option>`).join('');
    $('priceSuggested').value = '';
    // 线下售价：运营不可见
    const og = $('offlinePriceGroup');
    if (og) {
        if (IS_OPERATOR) { og.style.display = 'none'; }
        else {
            og.style.display = 'block';
            // 动态生成品相行（来自店铺配置，非写死）
            const rows = $('offlinePriceRows');
            rows.innerHTML = getConditionKeys().map(k => `
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="flex:0 0 90px;font-size:13px;font-weight:600;">${escapeHtml(getCN(k))}</span>
                    <input type="number" step="0.01" min="0" class="form-input" data-cond="${k}" placeholder="留空自动" style="max-width:150px;">
                </div>`).join('');
            // 加载已配置的线下售价并回显
            fetch('../api/get_offline_prices.php?product_id=' + productId)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.configured) {
                        offlinePriceConfig = d.configured || {};
                        rows.querySelectorAll('input[data-cond]').forEach(inp => {
                            const c = inp.dataset.cond;
                            if (offlinePriceConfig[c]) inp.value = offlinePriceConfig[c];
                        });
                    }
                })
                .catch(() => {});
        }
    }
    showModal('priceModal');
}
async function savePrice(e) {
    e.preventDefault();
    const productId = priceProductId;
    const condition = $('priceCondition').value;
    const suggested = $('priceSuggested').value;
    if (!suggested) { showErrorToast('请填写售价'); return; }
    const price = parseFloat(suggested);
    if (isNaN(price) || price <= 0) { showErrorToast('售价无效'); return; }
    const payload = { product_id: productId, condition_type: condition, suggested_price: price, remark: '批量改价' };
    try {
        const res = await fetch('../api/purchase.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) { showErrorToast('改价失败: ' + data.error); return; }
    } catch (err) { showErrorToast('改价失败: ' + err.message); return; }
    // 线下售价：仅店长/超管；逐品相保存（空值=恢复自动）
    if (!IS_OPERATOR && !$('offlinePriceGroup').classList.contains('hidden')) {
        const rows = $('offlinePriceRows');
        for (const inp of rows.querySelectorAll('input[data-cond]')) {
            const cond = inp.dataset.cond;
            const v = inp.value;
            try {
                const olRes = await fetch('../api/save_offline_price.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, condition_type: cond, offline_price: v !== '' ? parseFloat(v) : null })
                });
                const olData = await olRes.json();
                if (!olData.success) { showErrorToast('线下售价保存失败(' + getCN(cond) + '): ' + olData.error); return; }
            } catch (err) { showErrorToast('线下售价保存失败(' + getCN(cond) + '): ' + err.message); return; }
        }
    }
    showToast('改价已应用');
    closeModal('priceModal');
    await loadProducts();
    if (currentId) renderDrawer();
}

/* ---------- 编辑批次 ---------- */
async function openEditBatchModal(batchId, productId, conditionType) {
    try {
        const res = await fetch('../api/get_product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });
        const data = await res.json();
        const batch = data.success ? getAllBatches(data.data).find(b => b.id === batchId) : null;
        if (!batch) { showErrorToast('批次未找到'); return; }
        const p = allProducts.find(x => x.id === productId);
        const newQty = prompt(`批次 ${batch.batch_no || batch.id} 当前库存 ${batch.remaining_qty}，请输入新库存数量：`, batch.remaining_qty);
        if (newQty === null) return;
        const qty = parseInt(newQty);
        if (isNaN(qty) || qty < 0) { showErrorToast('数量无效'); return; }
        if (!confirm(`确定将库存调整为 ${qty} 吗？`)) return;
        const res2 = await fetch('../api/update_batch.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ batch_id: batchId, product_id: productId, condition_type: conditionType, qty: qty, purchase_price: batch.purchase_price, suggested_price: batch.suggested_price, remark: batch.remark || '' })
        });
        const d2 = await res2.json();
        if (d2.success) {
            showToast('批次库存已更新');
            renderDrawer();
            await loadProducts();
        } else showErrorToast(d2.error || '更新失败');
    } catch (err) { showErrorToast('操作失败'); }
}

/* ---------- 删除 ---------- */
function toggleSelectAll(checked) {
    document.querySelectorAll('.pm-cb').forEach(cb => cb.checked = checked);
    selectedIds.clear();
    if (checked) {
        // 只全选当前筛选可见的商品（不选被筛选掉的）
        currentFiltered.forEach(p => selectedIds.add(p.id));
    }
    updateBatchDeleteButton();
}
function toggleSelectOne(id, el) {
    if (el.checked) selectedIds.add(id); else selectedIds.delete(id);
    updateBatchDeleteButton();
}
function updateBatchDeleteButton() {
    const btn = $('batchDeleteBtn');
    if (IS_OPERATOR) { btn.classList.add('pm-hidden'); return; }
    btn.classList.toggle('pm-hidden', selectedIds.size === 0);
    $('selectedCount').textContent = selectedIds.size;
}
function showDeleteConfirm(ids, name) {
    if (!requireStore()) return;
    const names = (name || '该商品');
    $('confirmText').textContent = `确定删除「${names}」吗？删除后不可恢复。`;
    const ok = $('confirmOk');
    ok.onclick = async () => {
        closeModal('confirmModal');
        await performDelete(ids);
    };
    showModal('confirmModal');
}
function batchDelete() {
    if (!requireStore()) return;
    if (selectedIds.size === 0) return;
    $('confirmText').textContent = `确定删除选中的 ${selectedIds.size} 个商品吗？删除后不可恢复。`;
    const ok = $('confirmOk');
    ok.onclick = async () => {
        closeModal('confirmModal');
        const ids = [...selectedIds];
        selectedIds.clear();
        updateBatchDeleteButton();
        await performDelete(ids);
    };
    showModal('confirmModal');
}
async function performDelete(ids) {
    try {
        const res = await fetch('../api/delete_product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_ids: ids })
        });
        const data = await res.json();
        if (data.success) {
            showToast(`已删除 ${ids.length} 个商品`);
            await loadProducts();
        } else {
            showErrorToast(data.error || '删除失败');
        }
    } catch (err) { showErrorToast('删除失败: ' + err.message); }
}

/* ---------- 盘点 ---------- */
const AUDIT_LS_KEY = 'ppmart_audit_draft_v1';
let auditProducts = [];
let auditConditionTypes = [];
let auditDraft = {};     // pid -> { condKey: number|null }
let auditSel = {};       // "pid|condKey" -> bool
let auditFilter = 'all';
let auditSortKey = 'name';
let auditSortAsc = true;
let auditSearchTimer = null;

function auditIsFilled(v) { return v !== null && v !== undefined && v !== ''; }
function auditOnlineQty(p, key) { return (p.conditions && p.conditions[key]) ? (p.conditions[key].qty || 0) : 0; }
function auditOnlineTotal(p) { let t = 0; auditConditionTypes.forEach(ct => { t += auditOnlineQty(p, ct.key); }); return t; }

/* 状态判定：unc未盘 / part待盘完 / same一致 / trans已转变(总数守恒) / diff有差异 */
function auditStatusOf(p) {
    const d = auditDraft[p.product_id] || {};
    let all = true, any = false, diff = false;
    let onTot = 0, realTot = 0;
    for (const ct of auditConditionTypes) {
        const v = d[ct.key];
        if (!auditIsFilled(v)) { all = false; continue; }
        any = true;
        const on = auditOnlineQty(p, ct.key);
        const real = +v;
        onTot += on; realTot += real;
        if (real !== on) diff = true;
    }
    if (!any) return 'unc';
    if (!all) return 'part';
    if (diff && onTot === realTot) return 'trans';
    return diff ? 'diff' : 'same';
}
function auditBadgeText(st) { return { same: '一致', diff: '有差异', part: '待盘完', unc: '未盘', trans: '已转变' }[st] || st; }
function auditDiffEntries(p) {
    if (auditStatusOf(p) !== 'diff') return [];
    const d = auditDraft[p.product_id] || {};
    const out = [];
    for (const ct of auditConditionTypes) {
        const v = +d[ct.key], on = auditOnlineQty(p, ct.key);
        if (v - on !== 0) out.push({ t: ct, online: on, real: v, delta: v - on });
    }
    return out;
}
function auditTransformDetail(p) {
    const d = auditDraft[p.product_id] || {};
    const from = [], to = [];
    for (const ct of auditConditionTypes) {
        const v = d[ct.key];
        if (!auditIsFilled(v)) continue;
        const delta = +v - auditOnlineQty(p, ct.key);
        if (delta < 0) from.push({ t: ct, n: -delta });
        else if (delta > 0) to.push({ t: ct, n: delta });
    }
    const pairs = [];
    let fi = 0, ti = 0;
    while (fi < from.length && ti < to.length) {
        const n = Math.min(from[fi].n, to[ti].n);
        pairs.push({ from: from[fi].t, to: to[ti].t, n: n });
        from[fi].n -= n; to[ti].n -= n;
        if (from[fi].n === 0) fi++;
        if (to[ti].n === 0) ti++;
    }
    return pairs;
}
function auditSelOf(k) { return auditSel[k] === undefined ? true : !!auditSel[k]; }

async function openAuditModal() {
    showModal('auditModal');
    $('auditContent').innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-tertiary);">加载中...</div>';
    try {
        // 盘点前置校验：还有未结束(未下播/未打包出库)的直播场次时禁止盘点
        const actRes = await fetch('../api/live_ledger_list_sessions.php?status=active');
        const actData = await actRes.json();
        const actSessions = (actData.success && actData.data && actData.data.sessions) || [];
        if (actSessions.length) {
            const names = actSessions.slice(0, 3).map(s => '「' + (s.session_name || ('#' + s.id)) + '」').join('、');
            $('auditContent').innerHTML =
                '<div style="text-align:center; padding:36px 20px;">' +
                '<div style="font-size:22px; margin-bottom:12px;">⛔</div>' +
                '<div style="font-size:15px; font-weight:700; color:var(--danger); margin-bottom:10px;">还有 ' + actSessions.length + ' 场未结束的直播场次，暂时不能盘点</div>' +
                '<div style="font-size:13px; color:var(--text-secondary); line-height:1.8;">' + names + (actSessions.length > 3 ? ' 等' : '') + '<br>请在所有场次「下播/打包出库」结束后再进行盘点，避免库存数据不准确。</div></div>';
            return;
        }
        const res = await fetch('../api/inventory_audit.php');
        const data = await res.json();
        if (data.success) {
            auditProducts = data.data.products;
            auditConditionTypes = data.data.condition_types || [];
            const ts = auditLoadDraft();
            if (ts) { $('auditFootTip').textContent = '已恢复上次盘点记录（' + new Date(ts).toLocaleString() + '）'; }
            auditFilter = 'all';
            auditResetSegs();
            renderAuditTable();
        } else {
            $('auditContent').innerHTML = '<div style="text-align:center; padding:40px; color:var(--danger);">加载失败: ' + escapeHtml(data.error || '') + '</div>';
        }
    } catch (err) {
        $('auditContent').innerHTML = '<div style="text-align:center; padding:40px; color:var(--danger);">加载失败: ' + escapeHtml(err.message) + '</div>';
    }
}
function closeAuditModal() { closeModal('auditModal'); loadProducts(); }

function auditSaveDraft() { try { localStorage.setItem(AUDIT_LS_KEY, JSON.stringify({ ts: Date.now(), draft: auditDraft, sel: auditSel })); } catch (e) {} }
function auditLoadDraft() {
    try {
        const o = JSON.parse(localStorage.getItem(AUDIT_LS_KEY) || 'null');
        if (o && o.draft) { auditDraft = o.draft; auditSel = o.sel || {}; return o.ts; }
    } catch (e) {}
    return 0;
}

function auditMatchKW(p) {
    const kw = ($('auditSearch').value || '').trim().toLowerCase();
    if (!kw) return true;
    return [p.product_name, p.official_name, p.series, p.brand, p.barcode].some(v => String(v || '').toLowerCase().indexOf(kw) >= 0);
}
function auditOnSearch(v) { clearTimeout(auditSearchTimer); auditSearchTimer = setTimeout(() => renderAuditTable(), 200); }
function auditResetSegs() { document.querySelectorAll('#auditSegs .audit-seg').forEach(b => b.classList.toggle('on', b.dataset.f === auditFilter)); }
document.getElementById('auditSegs').addEventListener('click', e => {
    const b = e.target.closest('.audit-seg'); if (!b) return;
    auditFilter = b.dataset.f;
    auditResetSegs();
    renderAuditTable();
});
function auditSort(key) {
    if (auditSortKey === key) auditSortAsc = !auditSortAsc;
    else { auditSortKey = key; auditSortAsc = true; }
    renderAuditTable();
}

function renderAuditStats() {
    let nUnc = 0, nSame = 0, nDiff = 0, nTrans = 0;
    auditProducts.forEach(p => {
        const s = auditStatusOf(p);
        if (s === 'diff') nDiff++;
        else if (s === 'trans') nTrans++;
        else if (s === 'same') nSame++;
        else nUnc++;
    });
    $('auditStats').innerHTML =
        '<span class="audit-stat">共 <b>' + auditProducts.length + '</b> 商品</span>' +
        '<span class="audit-stat diff">有差异 <b>' + nDiff + '</b></span>' +
        '<span class="audit-stat trans">已转变 <b>' + nTrans + '</b></span>' +
        '<span class="audit-stat same">一致 <b>' + nSame + '</b></span>' +
        '<span class="audit-stat unc">未盘完 <b>' + nUnc + '</b></span>';
}

function renderAuditTable() {
    const content = $('auditContent');
    const sorted = [...auditProducts].sort((a, b) => {
        let va, vb;
        if (auditSortKey === 'series') { va = a.series || ''; vb = b.series || ''; }
        else if (auditSortKey === 'brand') { va = a.brand || ''; vb = b.brand || ''; }
        else { va = a.product_name || ''; vb = b.product_name || ''; }
        const cmp = String(va).localeCompare(String(vb), 'zh-Hans-CN', { numeric: true });
        return auditSortAsc ? cmp : -cmp;
    });
    const view = sorted.filter(p => {
        if (!auditMatchKW(p)) return false;
        const s = auditStatusOf(p);
        if (auditFilter === 'all') return true;
        if (auditFilter === 'unc') return s === 'unc' || s === 'part';
        if (auditFilter === 'trans') return s === 'trans';
        if (auditFilter === 'diff') return s === 'diff';
        if (auditFilter === 'same') return s === 'same';
        if (auditFilter === 'stock') return auditOnlineTotal(p) > 0;
        return true;
    });

    let html = '<table style="font-size:13px; min-width:100%; border-collapse:collapse;">';
    html += '<thead>';
    html += '<tr style="position:sticky; top:0; background:var(--bg-surface); z-index:2;">';
    html += '<th rowspan="2" style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--border); min-width:150px; cursor:pointer;" onclick="auditSort(&#39;name&#39;)">商品' + (auditSortKey === 'name' ? (auditSortAsc ? ' ▲' : ' ▼') : '') + '</th>';
    html += '<th rowspan="2" style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--border); min-width:80px; cursor:pointer;" onclick="auditSort(&#39;series&#39;)">系列' + (auditSortKey === 'series' ? (auditSortAsc ? ' ▲' : ' ▼') : '') + '</th>';
    html += '<th rowspan="2" style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--border); min-width:70px; cursor:pointer;" onclick="auditSort(&#39;brand&#39;)">品牌' + (auditSortKey === 'brand' ? (auditSortAsc ? ' ▲' : ' ▼') : '') + '</th>';
    html += '<th rowspan="2" style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--border); min-width:90px;">条码</th>';
    auditConditionTypes.forEach(ct => {
        html += '<th colspan="2" style="text-align:center; padding:8px 4px; border-bottom:1px solid var(--border); color:var(--text-secondary); font-size:12px; min-width:120px;">' + escapeHtml(ct.name) + '</th>';
    });
    html += '<th rowspan="2" style="text-align:center; padding:8px 10px; border-bottom:2px solid var(--border); min-width:70px;">状态</th>';
    html += '<th rowspan="2" style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--border); min-width:150px;">操作</th>';
    html += '</tr>';
    html += '<tr style="position:sticky; top:40px; background:var(--bg-hover); z-index:2;">';
    auditConditionTypes.forEach(() => {
        html += '<th style="text-align:center; padding:4px 4px; border-bottom:2px solid var(--border); font-size:11px; color:var(--text-tertiary);">在库</th>';
        html += '<th style="text-align:center; padding:4px 4px; border-bottom:2px solid var(--border); font-size:11px; color:var(--text-tertiary);">实际</th>';
    });
    html += '</tr></thead><tbody>';

    view.forEach(p => {
        const st = auditStatusOf(p);
        const d = auditDraft[p.product_id] || {};
        html += '<tr id="auditRow-' + p.product_id + '" data-pid="' + p.product_id + '" class="audit-tr-' + st + '">';
        html += '<td style="padding:6px 10px; border-bottom:1px solid var(--border);">';
        html += '<div style="font-weight:600; font-size:13px;">' + escapeHtml(p.product_name) + '</div>';
        html += (p.official_name && p.official_name !== p.product_name ? '<div style="font-size:11px; color:var(--text-tertiary);">' + escapeHtml(p.official_name) + '</div>' : '') + '</td>';
        html += '<td style="padding:6px 10px; border-bottom:1px solid var(--border); font-size:12px; color:var(--text-secondary);">' + escapeHtml(p.series || '-') + '</td>';
        html += '<td style="padding:6px 10px; border-bottom:1px solid var(--border); font-size:12px; color:var(--text-secondary);">' + escapeHtml(p.brand || '-') + '</td>';
        html += '<td style="padding:6px 10px; border-bottom:1px solid var(--border); font-size:12px; color:var(--text-secondary);">' + escapeHtml(p.barcode || '-') + '</td>';
        auditConditionTypes.forEach(ct => {
            const on = auditOnlineQty(p, ct.key);
            const v = d[ct.key];
            const filled = auditIsFilled(v);
            const chg = filled && (+v !== on);
            // 在库库存（只读）
            html += '<td style="text-align:center; padding:6px 4px; border-bottom:1px solid var(--border); font-size:13px; font-weight:600; color:var(--text);">' + on + '</td>';
            // 实际库存（手输）
            html += '<td style="text-align:center; padding:4px; border-bottom:1px solid var(--border);">';
            html += '<input type="text" inputmode="numeric" pattern="[0-9]*" class="audit-qty" autocomplete="off" data-pid="' + p.product_id + '" data-cond="' + escapeHtml(ct.key) + '" data-orig="' + on + '" value="' + (filled ? escapeHtml(String(v)) : '') + '" placeholder="手输" ' + (chg ? 'style="border-color:var(--warning); background:rgba(240,180,41,.1);"' : '') + ' oninput="auditInput(this)" onchange="auditCommit(this)" onfocus="this.select()">';
            html += '</td>';
        });
        html += '<td style="text-align:center; padding:6px 8px; border-bottom:1px solid var(--border);"><span class="audit-badge audit-badge-' + st + '">' + auditBadgeText(st) + '</span></td>';
        html += '<td style="padding:6px 10px; border-bottom:1px solid var(--border); white-space:nowrap;">';
        html += '<button class="btn btn-sm btn-outline" style="padding:2px 8px; font-size:11px;" onclick="auditFillOnline(' + p.product_id + ')" title="把在库数量填入实际">按在库</button> ';
        html += '<button class="btn btn-sm btn-outline" style="padding:2px 8px; font-size:11px;" onclick="auditFillZero(' + p.product_id + ')" title="未填的补0">补0</button> ';
        html += '<button class="btn btn-sm btn-outline" style="padding:2px 8px; font-size:11px;" onclick="auditClearRow(' + p.product_id + ')">清空</button></td>';
        html += '</tr>';
        const diffs = auditDiffEntries(p);
        if (st === 'trans') {
            const pairs = auditTransformDetail(p);
            const txt = pairs.map(x => x.n + ' ' + x.from.name + '→' + x.to.name).join('，');
            html += '<tr class="audit-checks-row"><td colspan="' + (4 + auditConditionTypes.length * 2 + 2) + '"><span class="audit-trans-note">状态转变：' + escapeHtml(txt) + '（总数守恒，实物未变）</span></td></tr>';
        } else if (diffs.length) {
            html += '<tr class="audit-checks-row"><td colspan="' + (4 + auditConditionTypes.length * 2 + 2) + '">' +
                diffs.map(x => {
                    const k = p.product_id + '|' + x.t.key;
                    return '<label><input type="checkbox" ' + (auditSelOf(k) ? 'checked' : '') + ' onchange="auditToggleSel(&#39;' + k + '&#39;,this.checked)"> ' + escapeHtml(x.t.name) + ' ' + (x.delta > 0 ? '+' : '') + x.delta + '</label>';
                }).join('') + '</td></tr>';
        }
    });
    html += '</tbody></table>';
    if (!view.length) html = '<div style="text-align:center; padding:40px; color:var(--text-tertiary);">没有符合条件的商品</div>';
    content.innerHTML = html;
    renderAuditStats();
    auditUpdateFooter();
}

// 边输入边入库：0 是合法盘点值，立即写入草稿并保存，避免重绘/切焦点时丢失
function auditInput(input) {
    const pid = parseInt(input.dataset.pid);
    const cond = input.dataset.cond;
    const d = auditDraft[pid] || (auditDraft[pid] = {});
    const val = (input.value || '').trim();
    const n = parseInt(val, 10);
    d[cond] = (val === '' || isNaN(n)) ? null : Math.max(0, n);
    auditSaveDraft();
    const orig = parseInt(input.dataset.orig) || 0;
    const chg = d[cond] !== null && d[cond] !== orig;
    input.style.borderColor = chg ? 'var(--warning)' : 'var(--border)';
    input.style.background = chg ? 'rgba(240,180,41,.1)' : 'var(--bg-elevated)';
}
// 点击行内快捷按钮前，先提交当前正在编辑的格子（防 blur→重绘 丢正在输入的值）
function auditFlushFocused() {
    const el = document.activeElement;
    if (el && el.classList && el.classList.contains('audit-qty')) auditInput(el);
}
function auditCommit(input) {
    auditInput(input); // 确保草稿已含 0
    renderAuditTable(); // 失焦/回车后再刷新状态、统计与筛选
}
function auditToggleSel(k, on) { auditSel[k] = on; auditSaveDraft(); auditUpdateFooter(); }

function auditFillOnline(pid) {
    auditFlushFocused();
    const p = auditProducts.find(x => x.product_id === pid); if (!p) return;
    const d = auditDraft[pid] || (auditDraft[pid] = {});
    auditConditionTypes.forEach(ct => { d[ct.key] = auditOnlineQty(p, ct.key); });
    auditSaveDraft(); renderAuditTable();
}
function auditFillZero(pid) {
    auditFlushFocused();
    const d = auditDraft[pid] || (auditDraft[pid] = {});
    // 只填空格：0 也算已填，不覆盖任何手输/已在库值
    auditConditionTypes.forEach(ct => { if (!auditIsFilled(d[ct.key])) d[ct.key] = 0; });
    auditSaveDraft(); renderAuditTable();
}
function auditClearRow(pid) {
    auditFlushFocused();
    delete auditDraft[pid]; auditSaveDraft(); renderAuditTable();
}
function auditClearAll() {
    if (!confirm('确定清空本次所有盘点记录？（线上库存不会被改动）')) return;
    auditDraft = {}; auditSel = {};
    try { localStorage.removeItem(AUDIT_LS_KEY); } catch (e) {}
    $('auditFootTip').textContent = '';
    renderAuditTable();
}

function auditAdjustItems() {
    const out = [];
    for (const p of auditProducts) {
        const st = auditStatusOf(p);
        if (st !== 'diff' && st !== 'trans') continue;
        const d = auditDraft[p.product_id] || {};
        for (const ct of auditConditionTypes) {
            const v = +d[ct.key], on = auditOnlineQty(p, ct.key);
            const delta = v - on;
            if (delta === 0) continue;
            if (!auditSelOf(p.product_id + '|' + ct.key)) continue;
            out.push({ product_id: p.product_id, condition_type: ct.key, qty: v, name: p.product_name, tname: ct.name, online: on, real: v, delta });
        }
    }
    return out;
}
function auditUpdateFooter() {
    const items = auditAdjustItems();
    const btn = $('auditAdjBtn');
    btn.textContent = '批量调整（' + items.length + '）';
    btn.disabled = !items.length;
}
function auditOpenConfirm() {
    const items = auditAdjustItems();
    if (!items.length) return;
    $('auditCfList').innerHTML = items.map(it => {
        const warn = (it.delta > 0 && it.online === 0) ? ' <span style="color:var(--danger);font-size:11px;">（线上为0，将新建批次）</span>' : '';
        return '<div class="audit-cf-item"><div><b>' + escapeHtml(it.name) + '</b> · ' + escapeHtml(it.tname) + '</div>' +
            '<div style="font-size:12px; color:var(--text-tertiary);">线上 ' + it.online + ' → 现场 ' + it.real + '　<span class="' + (it.delta > 0 ? 'audit-plus' : 'audit-minus') + '">' + (it.delta > 0 ? '+' : '') + it.delta + '</span>' + warn + '</div></div>';
    }).join('');
    const dd = new Date(), pad = n => String(n).padStart(2, '0');
    $('auditCfRemark').value = '线下盘点调整 ' + dd.getFullYear() + '-' + pad(dd.getMonth() + 1) + '-' + pad(dd.getDate());
    showModal('auditConfirmModal');
}
async function auditDoAdjust() {
    const items = auditAdjustItems();
    if (!items.length) { closeModal('auditConfirmModal'); return; }
    if (!confirm('将按上述明细真实修改线上库存，确定继续？')) return;
    const btn = $('auditCfOk');
    btn.disabled = true; btn.textContent = '调整中…';
    try {
        const res = await fetch('../api/batch_inventory_update.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: items.map(i => ({ product_id: i.product_id, condition_type: i.condition_type, qty: i.qty, name: i.name, tname: i.tname })), remark: $('auditCfRemark').value })
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.error || '接口失败');
        const dd = j.data || {};
        $('auditRsTitle').textContent = '调整完成：成功 ' + dd.success + ' 项，失败 ' + dd.failed + ' 项';
        $('auditRsList').innerHTML = (dd.results || []).map(x =>
            '<div class="audit-res-item">' + (x.ok ? '<span style="color:var(--success);">✓</span> ' : '<span style="color:var(--danger);">✗</span> ') + escapeHtml(x.name) +
            ' <span style="color:var(--text-tertiary);">(' + escapeHtml(x.condition_type) + ')</span> ' +
            (x.ok ? '（' + x.before + ' → ' + x.after + '）' : escapeHtml(x.msg || '失败')) + '</div>').join('');
        closeModal('auditConfirmModal');
        showModal('auditResultModal');
        await openAuditModal();
    } catch (e) {
        alert('调整失败：' + e.message);
    }
    btn.disabled = false; btn.textContent = '确认调整';
}

function auditExportCSV() {
    const head = ['商品ID', '商品名称', '系列', '品牌', '条码'];
    auditConditionTypes.forEach(ct => { head.push('线上-' + ct.name, '现场-' + ct.name, '差异-' + ct.name); });
    head.push('结论');
    const rows = [head];
    auditProducts.forEach(p => {
        const d = auditDraft[p.product_id] || {};
        const row = [p.product_id, p.product_name, p.series || '', p.brand || '', p.barcode || ''];
        auditConditionTypes.forEach(ct => {
            const on = auditOnlineQty(p, ct.key);
            const v = auditIsFilled(d[ct.key]) ? +d[ct.key] : '';
            row.push(on, v, (v === '' ? '' : v - on));
        });
        row.push(auditBadgeText(auditStatusOf(p)));
        rows.push(row);
    });
    const csv = rows.map(r => r.map(c => {
        const s = String(c == null ? '' : c);
        return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
    }).join(',')).join('\r\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    const d = new Date(), pad = n => String(n).padStart(2, '0');
    a.download = '库存盘点_' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '.csv';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 3000);
}
/* ---------- 批量导入 ---------- */
// 原批量导入流程：选择文件 → 上传后端 bulk_import_products.php（服务端解析，支持 csv/xlsx）
// 格式与「库存导出」一致：每商品一行，每个 SKU 状态各占 数量/进价/售价 三列
function openImportModal() {
    if (!requireStore()) return;
    $('importResult').innerHTML = '';
    $('importErr').textContent = '';
    $('importFile').value = '';
    const h = $('importHint');
    if (h) h.style.display = 'none';
    const t = document.querySelector('#importModal button[onclick="toggleImportHint()"]');
    if (t) t.textContent = '展开格式说明';
    showModal('importModal');
}
function closeImportModal() {
    closeModal('importModal');
    const b = $('impConfirmBtn');
    if (b) { b.style.display = 'none'; b.disabled = false; b.textContent = '确认入库'; }
    window._impToken = '';
}
function toggleImportHint() {
    const h = $('importHint');
    if (!h) return;
    const show = h.style.display === 'none';
    h.style.display = show ? '' : 'none';
    const t = document.querySelector('#importModal button[onclick="toggleImportHint()"]');
    if (t) t.textContent = show ? '收起格式说明' : '展开格式说明';
}
function downloadTemplate() {
    // 表头：商品基础信息 + 每个状态 数量/进价/售价 三列 + 供应商/备注（与导出格式一致）
    const base = ['商品名称', '常用名称', '系列', '品牌', '条码', '参考价', '发售时间', '产品介绍', '图片链接'];
    const conds = getConditionKeys(); // 动态取店铺配置的状态
    conds.forEach(k => { base.push(getCN(k) + '数量'); base.push(getCN(k) + '进价'); base.push(getCN(k) + '售价'); });
    base.push('供应商', '备注');
    const head = base.join(',');
    const row = ['示例商品', '示例·常用名', '示例系列', '示例品牌', '6901234000001', '79', '2026-01-01', '产品介绍', ''];
    conds.forEach((k, i) => { row.push(i === 0 ? '12' : ''); row.push(i === 0 ? '49' : ''); row.push(i === 0 ? '79' : ''); });
    row.push('', '');
    const csv = '\uFEFF' + head + '\n' + row.join(',') + '\n';
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = '商品批量导入模板.csv';
    a.click();
    showToast('模板已下载');
}
async function handleImportFile(input) {
    const f = input.files[0];
    if (!f) return;
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!['csv', 'xlsx'].includes(ext)) { $('importErr').textContent = '仅支持 .csv / .xlsx 文件'; return; }
    const formData = new FormData();
    formData.append('import_file', f);
    formData.append('mode', 'preview');
    $('impConfirmBtn').style.display = 'none';
    $('importResult').innerHTML = '<div style="text-align:center; padding:24px; color:var(--text-tertiary);">正在解析文件，请稍候…</div>';
    $('importErr').textContent = '';
    try {
        const res = await fetch('../api/bulk_import_products.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            window._impToken = result.data.token;
            renderImportPreview(result.data);
            $('impConfirmBtn').style.display = 'inline-flex';
        } else {
            $('importResult').innerHTML = `<div style="padding:16px; color:var(--danger);">导入失败：${escapeHtml(result.message || '未知错误')}</div>`;
        }
    } catch (err) {
        $('importResult').innerHTML = `<div style="padding:16px; color:var(--danger);">导入失败：${escapeHtml(err.message)}</div>`;
    }
}

function renderImportPreview(data) {
    const rows = data.rows || [];
    const errors = data.errors || [];
    let html = `<div style="padding:14px 16px;">
        <div style="color:var(--text); font-size:15px; margin-bottom:10px;">
            ✅ 解析完成，共 ${rows.length} 行商品（尚未写入库存）
            <span style="font-size:12px;color:var(--text-tertiary);margin-left:8px;">请核对下方比对结果，确认无误再点「确认入库」；格式有问题可直接关闭后重新上传</span>
        </div>
        ${data.file_bak ? `<div style="font-size:12px;color:var(--text-tertiary);margin-bottom:10px;">原文件已留档备查：<code>${escapeHtml(data.file_bak)}</code></div>` : ''}
        <div style="border:1px solid var(--border); border-radius:8px;">
            <table class="pm-import-cmp" style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead><tr style="background:var(--bg-hover);">
                    <th style="padding:7px 8px; text-align:left;">Excel行</th>
                    <th style="padding:7px 8px; text-align:left;">商品</th>
                    <th style="padding:7px 8px; text-align:left;">条码</th>
                    <th style="padding:7px 8px; text-align:left;">预计动作</th>
                    <th style="padding:7px 8px; text-align:left;">SKU · 数量 / 进价 / 售价</th>
                </tr></thead>
                <tbody>` +
        rows.map(r => `<tr style="border-top:1px solid var(--border);">
            <td style="padding:6px 8px; vertical-align:top;">第${r.row}行</td>
            <td style="padding:6px 8px; vertical-align:top;"><b>${escapeHtml(r.name)}</b>${r.series ? `<div class="pm-pcommon">${escapeHtml(r.series)}</div>` : ''}${r.brand ? `<div class="pm-pcommon">${escapeHtml(r.brand)}</div>` : ''}</td>
            <td style="padding:6px 8px; vertical-align:top;"><code>${escapeHtml(r.barcode || '(自动生成)')}</code></td>
            <td style="padding:6px 8px; vertical-align:top;">
                <span class="pm-imp-act ${r.action}">${r.action === 'match' ? '匹配' : r.action === 'skip' ? '跳过' : '新建'}</span>
                <div class="pm-pcommon" style="margin-top:2px;">${escapeHtml(r.action_note || '')}</div>
            </td>
            <td style="padding:6px 8px; vertical-align:top;">` +
            (r.skus || []).map(s =>
                `<div style="white-space:nowrap;"><span class="pm-tag" style="background:var(--bg-hover);color:var(--text-secondary);">${escapeHtml(getCN(s.key))}</span> ×${s.qty} · 进价 ¥${parseFloat(s.purchase_price || 0).toFixed(2)} · 售价 ¥${parseFloat(s.suggested_price || 0).toFixed(0)}</div>`
            ).join('') || '<span class="pm-pcommon">无库存</span>' +
            `</td></tr>`).join('') + `</tbody></table>
        </div>`;
    if (errors && errors.length) {
        html += `<div style="color:var(--danger); margin-top:10px;"><strong>提示（不影响确认，可只导有效行）：</strong><ul style="margin:6px 0 0; padding-left:20px; max-height:140px; overflow:auto;">` +
            errors.map(e => `<li>${escapeHtml(e)}</li>`).join('') + `</ul></div>`;
    }
    html += `</div>`;
    $('importResult').innerHTML = html;
}

async function doImportCommit() {
    const token = window._impToken;
    if (!token) return;
    const btn = $('impConfirmBtn');
    btn.disabled = true;
    btn.textContent = '导入中…';
    $('importResult').innerHTML = '<div style="text-align:center; padding:24px; color:var(--text-tertiary);">正在写入库存，请稍候…</div>';
    try {
        const res = await fetch('../api/bulk_import_products.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'commit', token })
        });
        const result = await res.json();
        if (result.success) {
            renderImportResult(result.data);
            showToast(`导入完成：成功 ${result.data.success_count} / ${result.data.total_count}`);
            await loadProducts();
        } else {
            $('importResult').innerHTML = `<div style="padding:16px; color:var(--danger);">导入失败：${escapeHtml(result.message || '未知错误')}</div>`;
        }
    } catch (err) {
        $('importResult').innerHTML = `<div style="padding:16px; color:var(--danger);">导入失败：${escapeHtml(err.message)}</div>`;
    } finally {
        btn.disabled = false;
        btn.style.display = 'none';
        btn.textContent = '确认入库';
    }
}

function renderImportResult(data) {
    const details = data.details || [];
    const errors = data.errors || [];
    let html = `<div style="padding:14px 16px;">
        <div style="color:var(--success); font-size:15px; margin-bottom:10px;">导入完成：成功 ${data.success_count} 个 / 共处理 ${data.total_count} 个</div>
        ${data.file_bak ? `<div style="font-size:12px;color:var(--text-tertiary);margin-bottom:10px;">原文件已留档备查：<code>${escapeHtml(data.file_bak)}</code></div>` : ''}
        <div style="border:1px solid var(--border); border-radius:8px;">
            <table class="pm-import-cmp" style="width:100%; border-collapse:collapse; font-size:12px;">
                <thead><tr style="background:var(--bg-hover);">
                    <th style="padding:7px 8px; text-align:left;">Excel行</th>
                    <th style="padding:7px 8px; text-align:left;">商品</th>
                    <th style="padding:7px 8px; text-align:left;">条码</th>
                    <th style="padding:7px 8px; text-align:left;">结果</th>
                    <th style="padding:7px 8px; text-align:left;">SKU · 入库数量 / 进价 / 售价</th>
                </tr></thead><tbody>` +
        details.map(r => `<tr style="border-top:1px solid var(--border);">
            <td style="padding:6px 8px; vertical-align:top;">第${r.row}行</td>
            <td style="padding:6px 8px; vertical-align:top;"><b>${escapeHtml(r.name)}</b>${r.matched_name && r.action === 'match' ? `<div class="pm-pcommon">匹配：${escapeHtml(r.matched_name)}</div>` : ''}</td>
            <td style="padding:6px 8px; vertical-align:top;"><code>${escapeHtml(r.barcode || '-')}</code></td>
            <td style="padding:6px 8px; vertical-align:top;">
                <span class="pm-imp-act ${r.action === 'skip' ? 'skip' : r.action === 'match' ? 'match' : 'new'}">${r.action === 'match' ? '匹配入库' : r.action === 'skip' ? '跳过' : '新建入库'}</span>
                <div class="pm-pcommon" style="margin-top:2px;">${escapeHtml(r.action_note || '')}</div>
            </td>
            <td style="padding:6px 8px; vertical-align:top;">` +
            (r.skus || []).map(s =>
                `<div style="white-space:nowrap;"><span class="pm-tag" style="background:var(--bg-hover);color:var(--text-secondary);">${escapeHtml(getCN(s.key))}</span> ×${s.qty} · 进价 ¥${parseFloat(s.purchase_price || 0).toFixed(2)} · 售价 ¥${parseFloat(s.suggested_price || 0).toFixed(0)}</div>`
            ).join('') || '<span class="pm-pcommon">无库存</span>' +
            `</td></tr>`).join('') + `</tbody></table>
        </div>`;
    if (errors && errors.length) {
        html += `<div style="color:var(--danger); margin-top:10px;"><strong>未导入的行：</strong><ul style="margin:6px 0 0; padding-left:20px; max-height:140px; overflow:auto;">` +
            errors.map(e => `<li>${escapeHtml(e)}</li>`).join('') + `</ul></div>`;
    }
    html += `</div>`;
    $('importResult').innerHTML = html;
}

/* ---------- 导出 ---------- */
async function exportInventory() {
    try {
        const res = await fetch('../api/export_inventory.php');
        if (!res.ok) { showErrorToast('导出失败'); return; }
        const blob = await res.blob();
        const a = document.createElement('a');
        const url = URL.createObjectURL(blob);
        a.href = url;
        const d = new Date();
        a.download = '库存导出_' + d.getFullYear() + ('0' + (d.getMonth() + 1)).slice(-2) + ('0' + d.getDate()).slice(-2) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
        showToast('库存已导出');
    } catch (err) { showErrorToast('导出失败'); }
}

/* ---------- 初始化 ---------- */
async function initializePage() {
    await loadSettings();
    await loadProducts();
}
initializePage();
</script>
