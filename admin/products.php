<?php
$pageTitle = '商品管理';
$currentPage = 'products';
require_once __DIR__ . '/layout.php';
$canSeeProfit = $currentUser['can_see_profit'] ?? true;
?>
        <div class="page-title">🏷️ 商品管理</div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
            <div style="background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">本月入库</div>
                <div id="statMonthPurchase" style="font-size:36px; font-weight:bold;">-</div>
            </div>
            <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">商品总数</div>
                <div id="statTotalProducts" style="font-size:36px; font-weight:bold;">-</div>
            </div>
            <div style="background:linear-gradient(135deg, #10b981, #059669); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">有库存商品</div>
                <div id="statActiveProducts" style="font-size:36px; font-weight:bold;">-</div>
            </div>
            <div style="background:linear-gradient(135deg, #f59e0b, #d97706); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">库存总量</div>
                <div id="statTotalStock" style="font-size:36px; font-weight:bold;">-</div>
            </div>
            <?php if ($canSeeProfit): ?>
            <div style="background:linear-gradient(135deg, #06b6d4, #0891b2); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">库存成本总价</div>
                <div id="statInventoryCost" style="font-size:36px; font-weight:bold;">¥-</div>
            </div>
            <?php endif; ?>
            <div style="background:linear-gradient(135deg, #ef4444, #dc2626); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">库存总价值</div>
                <div id="statTotalValue" style="font-size:36px; font-weight:bold;">¥-</div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div class="search-bar" style="margin:0; flex:1;">
                    <input type="text" id="searchInput" placeholder="搜索商品名称、常用名、条码、拼音..." onkeyup="searchProducts()">
                    <select id="seriesFilter" onchange="searchProducts()">
                        <option value="">全部系列</option>
                    </select>
                    <label style="display:inline-flex;align-items:center;gap:4px;margin-left:8px;white-space:nowrap;cursor:pointer;font-size:13px;color:var(--text-secondary);">
                        <input type="checkbox" id="stockFilter" onchange="searchProducts()">
                        仅显示有库存
                    </label>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()" style="margin-left:20px;">
                    ➕ 添加商品
                </button>
                <button class="btn btn-success" onclick="openImportModal()" style="margin-left:10px;">
                    📁 批量导入
                </button>
                <button class="btn btn-secondary" onclick="exportInventory()" style="margin-left:10px;">
                    📥 导出现有库存
                </button>
                <?php if ($isSuperAdmin): ?>
                <button class="btn btn-warning" onclick="openAuditModal()" style="margin-left:10px;">
                    📊 库存盘点
                </button>
                <?php endif; ?>
                <button class="btn btn-danger" id="batchDeleteBtn" onclick="batchDelete()" style="margin-left:10px; display:none;">
                    🗑️ 批量删除 (<span id="selectedCount">0</span>)
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)" title="全选"></th>
                        <th onclick="sortProductsBy('image')" class="sortable">图片 <span class="sort-indicator" id="sortImage"></span></th>
                        <th onclick="sortProductsBy('barcode')" class="sortable">条码 <span class="sort-indicator" id="sortBarcode"></span></th>
                        <th onclick="sortProductsBy('name')" class="sortable">商品名称 <span class="sort-indicator" id="sortName"></span></th>
                        <th onclick="sortProductsBy('series')" class="sortable">系列 <span class="sort-indicator" id="sortSeries"></span></th>
                        <th onclick="sortProductsBy('description')" class="sortable">简介 <span class="sort-indicator" id="sortDescription"></span></th>
                        <th onclick="sortProductsBy('sku')" class="sortable">SKU <span class="sort-indicator" id="sortSku"></span></th>
                        <th onclick="sortProductsBy('stock')" class="sortable">库存总量 <span class="sort-indicator" id="sortStock"></span></th>
                        <?php if ($canSeeProfit): ?><th onclick="sortProductsBy('purchase_price')" class="sortable">进价 <span class="sort-indicator" id="sortPurchasePrice"></span></th><?php endif; ?>
                        <th onclick="sortProductsBy('suggested_price')" class="sortable">售价 <span class="sort-indicator" id="sortSuggestedPrice"></span></th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="productList"></tbody>
            </table>
        </div>

<style>
.sortable { cursor: pointer; user-select: none; }
.sortable:hover { background: var(--bg-hover); }
.sort-indicator { font-size: 11px; margin-left: 3px; color: var(--text-tertiary); }
.sort-asc::after { content: ' ▲'; color: var(--primary); }
.sort-desc::after { content: ' ▼'; color: var(--primary); }
</style>

        <!-- 添加/编辑商品模态框 -->
        <div class="modal" id="productModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="modalTitle">添加商品</h3>
                    <button class="modal-close" onclick="closeModal('productModal')">&times;</button>
                </div>
                <form id="productForm" onsubmit="saveProduct(event)">
                    <input type="hidden" id="productId">
                    <div class="form-group">
                <label class="form-label">官方商品名称 *</label>
                <input type="text" class="form-input" id="productName" required>
            </div>
            <div class="form-group">
                <label class="form-label">常用名称</label>
                <input type="text" class="form-input" id="productCommonName">
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
                        <div class="form-group">
                            <label class="form-label">品牌</label>
                            <input type="text" class="form-input" id="productBrand">
                        </div>
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
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('imageFile').click()">
                                    📷 上传
                                </button>
                            </div>
                            <div id="imagePreview" style="margin-top:10px;">
                                <img id="previewImg" src="" style="max-width:200px; max-height:200px; display:none; border-radius:8px;">
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
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 采购入库模态框 -->
        <div class="modal" id="purchaseModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="purchaseModalTitle">采购入库</h3>
                    <button class="modal-close" onclick="closeModal('purchaseModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="purchaseProductName"></strong>
                    <span style="color:var(--text-secondary);" id="purchaseProductBarcode"></span>
                </div>
                <form id="purchaseForm" onsubmit="savePurchase(event)">
                    <input type="hidden" id="purchaseProductId">

                    <div style="background:var(--bg-hover); padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border);">
                        <div style="display:grid; grid-template-columns:1.5fr 80px 1fr 1fr; gap:10px; font-weight:bold; margin-bottom:10px; color:var(--text-secondary); font-size:12px;">
                            <div>SKU</div>
                            <div>数量</div>
                            <div>采购单价</div>
                            <div>建议售价</div>
                        </div>

                        <div id="purchaseConditionsContainer">
                            <!-- 动态生成的SKU输入框 -->
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">供应商</label>
                            <input type="text" class="form-input" id="supplier" placeholder="供应商名称">
                        </div>
                        <div class="form-group">
                            <label class="form-label">备注</label>
                            <input type="text" class="form-input" id="purchaseRemark" placeholder="备注信息">
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-success" style="flex:1;">确认入库</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('purchaseModal')">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 库存详情模态框 -->
        <div class="modal" id="stockDetailModal">
            <div class="modal-content modal-wide" style="max-height:80vh; overflow-y:auto;">
                <div class="modal-header">
                    <h3 class="modal-title" id="stockDetailTitle">库存详情</h3>
                    <button class="modal-close" onclick="closeModal('stockDetailModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="stockDetailProductName"></strong>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                    <div style="background:var(--bg-hover); padding:15px; border-radius:8px;">
                        <div style="font-size:14px; color:var(--text-secondary);">总库存</div>
                        <div id="stockDetailTotalQty" style="font-size:32px; font-weight:bold; color:var(--success);">-</div>
                    </div>
                    <div style="background:var(--bg-hover); padding:15px; border-radius:8px;">
                        <div style="font-size:14px; color:var(--text-secondary);">库存价值</div>
                        <div id="stockDetailTotalValue" style="font-size:32px; font-weight:bold; color:var(--danger);">¥-</div>
                    </div>
                </div>

                <div style="display:flex; gap:15px; align-items:flex-start; flex-wrap:wrap;">
                    <div style="flex:none; max-width:50%;">
                        <h4 style="margin-bottom:10px;">📊 SKU分布</h4>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <div id="stockDetailDistribution"></div>
                        </div>
                    </div>
                    <?php if ($canSeeProfit): ?>
                    <div id="priceTrendSection" style="display:none; flex:1; min-width:350px; max-width:100%;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <h4 style="margin:0; font-size:14px;">📈 进价趋势</h4>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <input type="date" id="trendStartDate" style="padding:4px 8px; border:1px solid var(--border); border-radius:4px; font-size:12px; background:var(--bg); color:var(--text);">
                                <span style="font-size:12px; color:var(--text-secondary);">~</span>
                                <input type="date" id="trendEndDate" style="padding:4px 8px; border:1px solid var(--border); border-radius:4px; font-size:12px; background:var(--bg); color:var(--text);">
                                <button class="btn btn-sm btn-primary" onclick="refreshPriceTrend()" style="font-size:12px;">筛选</button>
                            </div>
                        </div>
                        <div style="background:var(--bg-hover); border-radius:8px; padding:12px;">
                            <canvas id="priceTrendChart" style="width:100%; height:200px;"></canvas>
                            <div id="priceTrendEmpty" style="display:none; text-align:center; padding:40px 0; color:var(--text-secondary); font-size:14px;">
                                暂无进货记录
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h4 style="margin-bottom:10px;">📦 批次详情</h4>
                    <table style="width:100%;">
                        <thead>
                            <tr>
                                <th>批次号</th>
                                <th>SKU</th>
                                <?php if ($canSeeProfit): ?><th>进价</th><?php endif; ?>
                                <th>售价</th>
                                <th>库存</th>
                                <th>入库时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="stockDetailBatches"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 库存调整模态框 -->
        <div class="modal" id="adjustModal">
            <div class="modal-content"><!-- 库存调整 -->
                <div class="modal-header">
                    <h3 class="modal-title" id="adjustModalTitle">调整库存</h3>
                    <button class="modal-close" onclick="closeModal('adjustModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="adjustProductName"></strong>
                    <span style="color:var(--text-secondary);" id="adjustConditionName"></span>
                </div>
                <div class="form-group">
                    <label class="form-label">当前库存: <span id="currentQty">0</span></label>
                </div>
                <form id="adjustForm" onsubmit="saveAdjust(event)">
                    <input type="hidden" id="adjustProductId">
                    <input type="hidden" id="adjustConditionType">
                    <div class="form-group">
                        <label class="form-label">调整数量（正数增加，负数减少）*</label>
                        <input type="number" class="form-input" id="adjustQty" required style="font-size:24px; text-align:center;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">备注</label>
                        <input type="text" class="form-input" id="adjustRemark">
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">确认调整</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 编辑库存价格模态框 -->
        <div class="modal" id="priceModal">
            <div class="modal-content"><!-- 修改价格 -->
                <div class="modal-header">
                    <h3 class="modal-title" id="priceModalTitle">修改价格</h3>
                    <button class="modal-close" onclick="closeModal('priceModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="priceProductName"></strong>
                    <span style="color:var(--text-secondary);" id="priceConditionName"></span>
                </div>
                <form id="priceForm" onsubmit="savePrice(event)">
                    <input type="hidden" id="priceProductId">
                    <input type="hidden" id="priceConditionType">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">采购进价</label>
                            <input type="number" step="0.01" class="form-input" id="editPurchasePrice">
                        </div>
                        <div class="form-group">
                            <label class="form-label">建议售价 *</label>
                            <input type="number" step="0.01" class="form-input" id="editSuggestedPrice" required>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('priceModal')">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 编辑批次模态框 -->
        <div class="modal" id="editBatchModal">
            <div class="modal-content"><!-- 编辑批次 -->
                <div class="modal-header">
                    <h3 class="modal-title" id="editBatchModalTitle">编辑批次</h3>
                    <button class="modal-close" onclick="closeModal('editBatchModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="editBatchProductName"></strong>
                    <span style="color:var(--text-secondary);" id="editBatchConditionName"></span>
                </div>
                <div style="background:var(--bg-hover); padding:12px; border-radius:8px; margin-bottom:15px;">
                    <div style="font-size:14px; color:var(--text-secondary);">批次号</div>
                    <div id="editBatchNo" style="font-size:18px; font-family:monospace;"></div>
                </div>
                <form id="editBatchForm" onsubmit="saveEditBatch(event)">
                    <input type="hidden" id="editBatchId">
                    <input type="hidden" id="editBatchProductId">
                    <input type="hidden" id="editBatchConditionType">
                    <div class="form-group">
                        <label class="form-label">库存数量 *</label>
                        <input type="number" class="form-input" id="editBatchQty" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">采购进价 *</label>
                            <input type="number" step="0.01" class="form-input" id="editBatchPurchasePrice" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">建议售价 *</label>
                            <input type="number" step="0.01" class="form-input" id="editBatchSuggestedPrice" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">备注</label>
                        <textarea class="form-input" id="editBatchRemark" rows="2"></textarea>
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">保存修改</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editBatchModal')">取消</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 库存盘点模态框 -->
        <div class="modal" id="auditModal">
            <div class="modal-content modal-wide" style="max-width:95vw; max-height:90vh; overflow-y:auto;">
                <div class="modal-header">
                    <h3 class="modal-title">📊 库存盘点</h3>
                    <button class="modal-close" onclick="closeAuditModal()">&times;</button>
                </div>
                <div id="auditContent">
                    <div style="text-align:center; padding:40px; color:var(--text-tertiary);">加载中...</div>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; padding-top:15px; border-top:1px solid var(--border); position:sticky; bottom:0; background:var(--bg-surface);">
                    <button class="btn btn-primary" onclick="saveAuditChanges()">💾 保存修改</button>
                    <button class="btn btn-secondary" onclick="closeAuditModal()">取消</button>
                </div>
            </div>
        </div>

        <!-- 批量导入模态框 -->
        <div class="modal" id="importModal">
            <div class="modal-content"><!-- 批量导入 -->
                <div class="modal-header">
                    <h3 class="modal-title">📁 批量导入商品</h3>
                    <button class="modal-close" onclick="closeImportModal()">&times;</button>
                </div>
                
                <div style="margin-bottom:20px;">
                    <p style="color:var(--text-secondary); margin-bottom:15px;">
                        支持 CSV 和 Excel (.xlsx) 格式文件。推荐使用 CSV 格式以确保最佳兼容性。
                    </p>
                    
                    <div style="background:var(--bg-hover); padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid var(--border);">
                        <h4 style="margin-bottom:10px; color:var(--text);">📋 导入说明</h4>
                        <ul style="margin:0; padding-left:20px; color:var(--text-secondary); font-size:14px; line-height:1.6;">
                            <li><strong>推荐使用CSV格式</strong>：兼容性最好，处理速度最快</li>
                            <li>Excel文件支持.xlsx格式（.xls格式请转换为.csv或.xlsx）</li>
                            <li>商品名称为必填项</li>
                            <li>条码不能重复</li>
                            <li>支持批量导入各SKU库存</li>
                            <li>日期格式：YYYY-MM-DD</li>
                            <li>价格格式：数字（如：299.00）</li>
                        </ul>
                    </div>
                    
                    <div style="text-align:center; margin-bottom:15px;">
                        <button class="btn btn-secondary" onclick="downloadTemplate()">
                            📥 下载导入模板
                        </button>
                    </div>
                </div>

                <form onsubmit="importProducts(); return false;">
                    <div class="form-group">
                        <label class="form-label">选择文件 *</label>
                        <input type="file" id="importFile" accept=".xlsx,.xls,.csv" required class="form-input">
                    </div>
                    
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1;">
                            🚀 开始导入
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeImportModal()">
                            取消
                        </button>
                    </div>
                </form>
                
                <div id="importResult" style="margin-top:15px;"></div>
            </div>
        </div>
    </div>

    <script>
    const CAN_SEE_PROFIT = <?= $canSeeProfit ? 'true' : 'false' ?>;
    let allProducts = [];
    let productSort = { field: null, dir: 'asc' };
    let productDetails = {};
    let currentProductDetailId = null;

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // ---- 排序 ----
    function clearSortIndicators() {
        document.querySelectorAll('.sort-indicator').forEach(el => {
            el.className = 'sort-indicator';
        });
    }

    function sortProductsBy(field) {
        if (productSort.field === field) {
            productSort.dir = productSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            productSort.field = field;
            productSort.dir = 'asc';
        }
        clearSortIndicators();
        const id = 'sort' + field.replace(/_./g, s => s[1].toUpperCase()).replace(/^./, s => s.toUpperCase());
        const el = document.getElementById('sort' + field.replace(/_./g, s => s[1].toUpperCase()).replace(/^./, s => s.toUpperCase()));
        if (el) el.className = 'sort-indicator ' + (productSort.dir === 'asc' ? 'sort-asc' : 'sort-desc');
        searchProducts();
    }

    function applySort(products) {
        const f = productSort.field;
        if (!f) return products;
        return products.sort((a, b) => {
            let va, vb;
            switch (f) {
                case 'image':
                    va = a.image_url ? 1 : 0; vb = b.image_url ? 1 : 0; break;
                case 'barcode':
                    va = a.barcode || ''; vb = b.barcode || ''; break;
                case 'name':
                    va = (a.common_name || a.name || '').toLowerCase();
                    vb = (b.common_name || b.name || '').toLowerCase(); break;
                case 'series':
                    va = (a.series || '').toLowerCase(); vb = (b.series || '').toLowerCase(); break;
                case 'description':
                    va = a.product_description ? 1 : 0; vb = b.product_description ? 1 : 0; break;
                case 'sku':
                    va = a.inventory_summary ? Object.keys(a.inventory_summary).length : 0;
                    vb = b.inventory_summary ? Object.keys(b.inventory_summary).length : 0; break;
                case 'stock':
                    va = getTotalStock(a.inventory_summary); vb = getTotalStock(b.inventory_summary); break;
                case 'purchase_price':
                    va = parseFloat(a.overall_purchase_price) || 0; vb = parseFloat(b.overall_purchase_price) || 0; break;
                case 'suggested_price':
                    va = parseFloat(a.overall_suggested_price) || 0; vb = parseFloat(b.overall_suggested_price) || 0; break;
                default: return 0;
            }
            if (typeof va === 'string') {
                return productSort.dir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
            }
            return productSort.dir === 'asc' ? va - vb : vb - va;
        });
    }

    async function loadProducts() {
        try {
            const res = await fetch('../api/list_products.php');
            const data = await res.json();
            allProducts = data.data.products;

            const seriesSelect = document.getElementById('seriesFilter');
            seriesSelect.innerHTML = '<option value="">全部系列</option>';
            data.data.series_list.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s;
                opt.textContent = s;
                seriesSelect.appendChild(opt);
            });

            renderProducts(allProducts);
            loadStats();
        } catch (err) {
            console.error(err);
        }
    }

    async function loadStats() {
        try {
            const res = await fetch('../api/stock_overview.php');
            const data = await res.json();

            if (data.success) {
                document.getElementById('statTotalProducts').textContent = allProducts.length;
                document.getElementById('statActiveProducts').textContent = data.data.types;
                document.getElementById('statTotalStock').textContent = data.data.total_qty;
                document.getElementById('statTotalValue').textContent = '¥' + parseFloat(data.data.total_value || 0).toLocaleString();
                if (CAN_SEE_PROFIT) {
                    document.getElementById('statInventoryCost').textContent = '¥' + parseFloat(data.data.total_cost || 0).toLocaleString();
                }
            }

            const salesRes = await fetch('../api/sales_summary.php');
            const salesData = await salesRes.json();
            if (salesData.success) {
                document.getElementById('statMonthPurchase').textContent = (salesData.data.month_purchase_qty || 0) + ' 件';

            }
        } catch (err) {
            console.error(err);
        }
    }

    async function reloadWithFilter() {
        const keyword = document.getElementById('searchInput').value;
        const series = document.getElementById('seriesFilter').value;
        await loadProducts();
        document.getElementById('searchInput').value = keyword;
        document.getElementById('seriesFilter').value = series;
        searchProducts();
    }

    function searchProducts() {
        const keyword = document.getElementById('searchInput').value.toLowerCase();
        const series = document.getElementById('seriesFilter').value;

        const filtered = allProducts.filter(p => {
            const matchKeyword = !keyword || 
                p.name.toLowerCase().includes(keyword) ||
                (p.common_name && p.common_name.toLowerCase().includes(keyword)) ||
                p.barcode.includes(keyword) ||
                (p.pinyin_initials && p.pinyin_initials.toLowerCase().includes(keyword));
            const matchSeries = !series || p.series === series;
            const matchStock = !document.getElementById('stockFilter').checked || getTotalStock(p.inventory_summary) > 0;
            return matchKeyword && matchSeries && matchStock;
        });

        renderProducts(applySort(filtered));
    }

    function renderProducts(products) {
        const tbody = document.getElementById('productList');
        if (!products.length) {
            tbody.innerHTML = '<tr><td colspan="' + (CAN_SEE_PROFIT ? 11 : 10) + '" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无商品，点击上方"添加商品"创建</td></tr>';
            return;
        }

        tbody.innerHTML = products.map(p => {
            const imageHtml = p.image_url
                ? `<img src="../${p.image_url}" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">`
                : '<span style="font-size:24px;">📦</span>';

            const nameDisplay = p.common_name 
                ? `<strong>${p.common_name}</strong><br><span style="font-size:12px;color:var(--text-tertiary);">${p.name}</span>`
                : `<strong>${p.name}</strong>`;

            const inventoryHtml = renderInventoryBadges(p.id, p.inventory_summary);
            const totalStock = getTotalStock(p.inventory_summary);
            const stockClass = totalStock <= 0 ? 'text-muted' : totalStock <= 5 ? 'text-warning' : '';

            const purchasePrice = p.overall_purchase_price ? '¥' + parseFloat(p.overall_purchase_price).toFixed(2) : '-';
            const suggestedPrice = p.overall_suggested_price ? '¥' + parseFloat(p.overall_suggested_price).toFixed(2) : '-';

            return `
                <tr>
                    <td><input type="checkbox" class="product-checkbox" value="${p.id}" onchange="updateBatchDeleteButton()"></td>
                    <td>${imageHtml}</td>
                    <td><code style="background:var(--bg-hover);padding:4px 8px;border-radius:4px;">${p.barcode}</code></td>
                    <td>${nameDisplay}</td>
                    <td>${p.series || '-'}</td>
                    <td style="text-align:center;">${p.product_description ? '<span style="display:inline-block;width:12px;height:12px;background:#34d399;border-radius:50%;" title="' + escapeHtml(p.product_description) + '"></span>' : ''}</td>
                    <td style="cursor:pointer;" onclick="showStockDetail(${p.id}, '${p.common_name || p.name}')">
                        ${inventoryHtml}
                        <div style="font-size:12px;color:var(--text-secondary);margin-top:5px;">点击查看详情 ▼</div>
                    </td>
                    <td style="font-weight:bold; ${stockClass}; font-size:18px;">${totalStock}</td>
                    ${CAN_SEE_PROFIT ? `<td style="color:var(--text-secondary);">${purchasePrice}</td>` : ''}
                    <td style="font-weight:bold; color:var(--success);">${suggestedPrice}</td>
                    <td>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                            <button class="btn btn-sm btn-primary" onclick="editProduct(${p.id})">编辑</button>
                            <button class="btn btn-sm btn-success" onclick="openPurchaseModal(${p.id})">入库</button>
                            <button class="btn btn-sm btn-danger" onclick="deleteProduct(${p.id})">删除</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function getTotalStock(inventory) {
        if (!inventory) return 0;
        let total = 0;
        Object.values(inventory).forEach(item => {
            total += item.total_stock || 0;
        });
        return total;
    }

    function renderInventoryBadges(productId, inventory) {
        if (!inventory || Object.keys(inventory).length === 0) return '<span class="text-muted">暂无库存</span>';

        // 使用系统配置中的状态类型，如果没有则使用默认值
        const typeNames = systemSettings.condition_types ? 
            Object.fromEntries(systemSettings.condition_types.map(c => [c.key, c.name])) :
            { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };
        
        const typeColors = systemSettings.condition_types ? 
            Object.fromEntries(systemSettings.condition_types.map(c => [c.key, `condition-${c.key}`])) :
            { sealed: 'condition-sealed', opened: 'condition-opened', boxless: 'condition-boxless', flawed: 'condition-flawed' };

        const badges = [];
        // 使用系统配置中的状态类型
        const types = systemSettings.condition_types ? 
            systemSettings.condition_types.map(c => c.key) :
            ['sealed', 'opened', 'boxless', 'flawed'];
        
        types.forEach(type => {
            if (inventory[type]) {
                const qtyNum = inventory[type].total_stock || 0;
                const cls = typeColors[type];
                const qtyClass = qtyNum === 0 ? 'stock-out' : qtyNum <= 2 ? 'stock-low' : '';
                
                badges.push(`
                    <span class="condition-badge ${cls}" style="margin:2px;">
                        ${typeNames[type]}: <span class="${qtyClass}">${qtyNum}</span>
                    </span>
                `);
            }
        });

        return badges.join(' ');
    }

    /* ---- 库存盘点 ---- */
    let auditProducts = [];
    let auditConditionTypes = [];

    async function openAuditModal() {
        showModal('auditModal');
        document.getElementById('auditContent').innerHTML = '<div style="text-align:center; padding:40px; color:var(--text-tertiary);">加载中...</div>';

        try {
            const res = await fetch('../api/inventory_audit.php');
            const data = await res.json();
            if (data.success) {
                auditProducts = data.data.products;
                auditConditionTypes = data.data.condition_types;
                renderAuditTable();
            } else {
                document.getElementById('auditContent').innerHTML = '<div style="text-align:center; padding:40px; color:var(--danger);">加载失败: ' + (data.error || '未知错误') + '</div>';
            }
        } catch (err) {
            document.getElementById('auditContent').innerHTML = '<div style="text-align:center; padding:40px; color:var(--danger);">加载失败: ' + err.message + '</div>';
        }
    }

    function closeAuditModal() {
        closeModal('auditModal');
        reloadWithFilter();
    }

    function renderAuditTable() {
        const content = document.getElementById('auditContent');

        let html = '<div style="margin-bottom:12px;">';
        html += '<input type="text" id="auditSearch" placeholder="搜索商品..." style="padding:8px 14px; border-radius:8px; border:1px solid var(--border); background:var(--bg-elevated); color:var(--text); font-size:14px; width:250px;" oninput="filterAuditTable()">';
        html += ' <span style="font-size:13px; color:var(--text-secondary); margin-left:8px;">共 ' + auditProducts.length + ' 个商品</span>';
        html += '</div>';

        html += '<div style="overflow-x:auto;">';
        html += '<table style="font-size:13px; white-space:nowrap; min-width:100%; border-collapse:collapse;">';
        html += '<thead><tr style="position:sticky; top:0; background:var(--bg-surface); z-index:2;">';
        html += '<th style="text-align:left; padding:8px 10px; border-bottom:2px solid var(--border); min-width:150px;">商品名称</th>';

        // 状态列标题（每个状态3列）
        auditConditionTypes.forEach(ct => {
            html += '<th style="text-align:center; padding:8px 4px; border-bottom:2px solid var(--border); color:var(--text-secondary); font-size:12px; min-width:55px;" colspan="3">';
            html += '<span class="condition-badge condition-' + ct.key + '">' + escapeHtml(ct.name) + '</span>';
            html += '</th>';
        });

        html += '</tr>';
        html += '<tr style="position:sticky; top:36px; background:var(--bg-hover); z-index:2;">';
        html += '<th style="text-align:left; padding:4px 10px; border-bottom:1px solid var(--border); font-size:11px; color:var(--text-tertiary);">条码 / 系列</th>';
        auditConditionTypes.forEach(() => {
            html += '<th style="text-align:center; padding:4px 2px; border-bottom:1px solid var(--border); font-size:11px; color:var(--text-tertiary);">数量</th>';
            html += '<th style="text-align:center; padding:4px 2px; border-bottom:1px solid var(--border); font-size:11px; color:var(--text-tertiary);">进价</th>';
            html += '<th style="text-align:center; padding:4px 2px; border-bottom:1px solid var(--border); font-size:11px; color:var(--text-tertiary);">售价</th>';
        });
        html += '</tr></thead><tbody id="auditTableBody">';

        auditProducts.forEach(p => {
            html += '<tr class="audit-row" data-pid="' + p.product_id + '" data-name="' + escapeHtml((p.product_name || p.official_name || '').toLowerCase()) + '" data-barcode="' + escapeHtml(p.barcode) + '">';
            html += '<td style="padding:6px 10px; border-bottom:1px solid var(--border);">';
            html += '<div style="font-weight:600; font-size:13px;">' + escapeHtml(p.product_name) + '</div>';
            html += '<div style="font-size:11px; color:var(--text-tertiary);">' + escapeHtml(p.barcode || '') + (p.series ? ' · ' + escapeHtml(p.series) : '') + '</div>';
            html += '</td>';

            auditConditionTypes.forEach(ct => {
                const c = p.conditions[ct.key] || { qty: 0, purchase_price: null, suggested_price: null };
                const qty = c.qty || 0;
                const pp = c.purchase_price !== null && c.purchase_price !== undefined ? c.purchase_price.toFixed(2) : '';
                const sp = c.suggested_price !== null && c.suggested_price !== undefined ? c.suggested_price.toFixed(2) : '';
                const inputStyle = 'width:58px; padding:4px 6px; border:1px solid var(--border); border-radius:4px; background:var(--bg-elevated); color:var(--text); font-size:12px; text-align:center;';

                html += '<td style="padding:3px 2px; border-bottom:1px solid var(--border);">';
                html += '<input type="number" min="0" class="audit-qty" data-pid="' + p.product_id + '" data-ctype="' + ct.key + '" value="' + qty + '" style="' + inputStyle + 'width:52px;">';
                html += '</td>';
                html += '<td style="padding:3px 2px; border-bottom:1px solid var(--border);">';
                html += '<input type="number" step="0.01" min="0" class="audit-price" data-pid="' + p.product_id + '" data-ctype="' + ct.key + '" value="' + pp + '" placeholder="-" style="' + inputStyle + '">';
                html += '</td>';
                html += '<td style="padding:3px 2px; border-bottom:1px solid var(--border);">';
                html += '<input type="number" step="0.01" min="0" class="audit-price" data-pid="' + p.product_id + '" data-ctype="' + ct.key + '" value="' + sp + '" placeholder="-" style="' + inputStyle + '">';
                html += '</td>';
            });

            html += '</tr>';
        });

        html += '</tbody></table></div>';
        content.innerHTML = html;
    }

    function filterAuditTable() {
        const keyword = document.getElementById('auditSearch').value.toLowerCase().trim();
        document.querySelectorAll('#auditTableBody .audit-row').forEach(row => {
            const name = row.dataset.name || '';
            const barcode = row.dataset.barcode || '';
            const match = !keyword || name.includes(keyword) || barcode.includes(keyword);
            row.style.display = match ? '' : 'none';
        });
    }

    async function saveAuditChanges() {
        if (!confirm('确认保存所有修改？这将覆盖当前库存数量和价格。')) return;

        const items = [];
        const inputs = document.querySelectorAll('#auditContent .audit-qty, #auditContent .audit-price');

        // 按 product_id+condition_type 分组
        const map = {};
        inputs.forEach(inp => {
            const key = inp.dataset.pid + '_' + inp.dataset.ctype;
            if (!map[key]) {
                map[key] = { product_id: parseInt(inp.dataset.pid), condition_type: inp.dataset.ctype, qty: 0, purchase_price: null, suggested_price: null };
            }
            if (inp.classList.contains('audit-qty')) {
                map[key].qty = parseInt(inp.value) || 0;
            } else {
                // 第一个 price 是进价，第二个是售价
                if (map[key].purchase_price === null) {
                    map[key].purchase_price = inp.value !== '' ? parseFloat(inp.value) : null;
                } else {
                    map[key].suggested_price = inp.value !== '' ? parseFloat(inp.value) : null;
                }
            }
        });

        Object.values(map).forEach(item => items.push(item));

        const btn = document.querySelector('#auditModal .btn-primary');
        const origText = btn.textContent;
        btn.textContent = '⏳ 保存中...';
        btn.disabled = true;

        try {
            const res = await fetch('../api/batch_inventory_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items })
            });
            const data = await res.json();
            if (data.success) {
                alert('✅ ' + (data.data.message || '盘点更新完成'));
                closeAuditModal();
            } else {
                alert('❌ ' + (data.error || '保存失败'));
            }
        } catch (err) {
            alert('❌ 保存失败: ' + err.message);
        } finally {
            btn.textContent = origText;
            btn.disabled = false;
        }
    }

    async function showStockDetail(productId, productName) {
        currentProductDetailId = productId;
        
        // 如果没有传入 productName，从 allProducts 中获取
        if (!productName || productName === '') {
            const p = allProducts.find(x => x.id === productId);
            if (p) {
                productName = p.common_name || p.name;
            }
        }
        
        document.getElementById('stockDetailTitle').textContent = `${productName} - 库存详情`;
        document.getElementById('stockDetailProductName').textContent = productName;

        try {
            const res = await fetch('../api/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            });

            const data = await res.json();
            if (data.success) {
                const inv = data.data.inventory;
                // 使用系统配置中的状态名称
                const conditionNames = systemSettings.condition_types ?
                    systemSettings.condition_types.map(c => c.name) :
                    ['原盒未拆', '拆盒无瑕', '无盒无瑕', '微瑕'];
                const conditionTypeList = systemSettings.condition_types || [];

                let totalQty = 0;
                let totalValue = 0;
                let distributionHtml = '';
                let batchesHtml = '';

                conditionTypeList.forEach(ct => {
                    const name = ct.name;
                    if (inv[name]) {
                        const qty = inv[name].stock;
                        const value = inv[name].suggested_price * qty;
                        totalQty += qty;
                        totalValue += value;

                        distributionHtml += `
                            <div style="background:var(--bg-hover); padding:12px 16px; border-radius:8px; min-width:160px;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <div>
                                        <div style="font-size:14px; color:var(--text-secondary);">${name}</div>
                                        <div style="font-size:20px; font-weight:bold; color:var(--success);">${qty}</div>
                                    </div>
                                    <button class="btn btn-sm btn-secondary" onclick="unifyPrice(${productId}, '${ct.key}')" title="统一修改此SKU所有批次的售价">💰 改价</button>
                                </div>
                            </div>
                        `;

                        inv[name].batches.forEach(batch => {
                            batchesHtml += `
                                <tr>
                                    <td><code>${batch.batch_no}</code></td>
                                    <td>${name}</td>
                                    ${CAN_SEE_PROFIT ? `<td>¥${parseFloat(batch.purchase_price).toFixed(2)}</td>` : ''}
                                    <td>¥${parseFloat(batch.suggested_price).toFixed(2)}</td>
                                    <td>${batch.remaining_qty}</td>
                                    <td>${batch.purchased_at}</td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm" onclick="openEditBatchModal(${productId}, '${batch.condition_type}', ${batch.batch_id})">
                                            ✏️ 编辑
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                });

                document.getElementById('stockDetailTotalQty').textContent = totalQty;
                document.getElementById('stockDetailTotalValue').textContent = '¥' + totalValue.toLocaleString();
                document.getElementById('stockDetailDistribution').innerHTML = distributionHtml;
                document.getElementById('stockDetailBatches').innerHTML = batchesHtml || '<tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);">暂无批次记录</td></tr>';
            }
        } catch (err) {
            console.error(err);
        }

        // 重置进货价趋势筛选器并加载（运营不可见进价，跳过）
        if (CAN_SEE_PROFIT) {
            document.getElementById('trendStartDate').value = '';
            document.getElementById('trendEndDate').value = '';
            document.getElementById('priceTrendSection').style.display = 'none';
            loadPriceTrend(productId, '', '');
        }

        showModal('stockDetailModal');
    }

    async function unifyPrice(productId, conditionType) {
        const p = allProducts.find(x => x.id === productId);
        const productName = p ? (p.common_name || p.name) : '';

        const input = prompt(`请输入「${productName}」新的统一售价：`);
        if (input === null || input === '') return;

        const price = parseFloat(input);
        if (isNaN(price) || price <= 0) {
            alert('请输入有效的价格');
            return;
        }

        if (!confirm(`确定将所有「${productName}」的售价统一修改为 ¥${price.toFixed(2)} 吗？`)) return;

        try {
            const res = await fetch('../api/purchase.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: productId,
                    condition_type: conditionType,
                    suggested_price: price,
                    remark: '一键统一改价'
                })
            });

            const data = await res.json();
            if (data.success) {
                alert(`✅ 售价已统一为 ¥${price.toFixed(2)}，共更新 ${data.data.updated_batches} 个批次`);
                showStockDetail(productId, productName);
                reloadWithFilter();
            } else {
                alert('修改失败: ' + data.error);
            }
        } catch (err) {
            alert('修改失败: ' + err.message);
        }
    }

    function generateBarcode() {
        const prefix = '69414486';
        const randomNum = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
        const digits = prefix + randomNum;
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(digits[i]) * (i % 2 === 0 ? 1 : 3);
        }
        const checkDigit = (10 - (sum % 10)) % 10;
        return digits + checkDigit;
    }

    function openAddModal() {
        document.getElementById('modalTitle').textContent = '添加商品';
        document.getElementById('productForm').reset();
        document.getElementById('productId').value = '';
        document.getElementById('productBarcode').value = generateBarcode();
        document.getElementById('productBarcode').removeAttribute('required');
        document.getElementById('previewImg').style.display = 'none';
        showModal('productModal');
    }

    async function editProduct(id) {
        try {
            const res = await fetch('../api/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: id })
            });
            
            const data = await res.json();
            if (!data.success) {
                alert('获取商品信息失败');
                return;
            }
            
            const p = data.data;

            document.getElementById('modalTitle').textContent = '编辑商品';
            document.getElementById('productId').value = p.id;
            document.getElementById('productName').value = p.name;
            document.getElementById('productCommonName').value = p.common_name || '';
            document.getElementById('productSeries').value = p.series || '';
            document.getElementById('productBarcode').value = p.barcode;
            document.getElementById('productBarcode').setAttribute('required', 'required');
            document.getElementById('qiandaoPrice').value = p.qiandao_price || '';
            document.getElementById('productBrand').value = p.brand || '';
            document.getElementById('releaseDate').value = p.release_date || '';
            document.getElementById('productDescription').value = p.product_description || '';
            document.getElementById('imageUrl').value = p.image_url || '';
            document.getElementById('productRemark').value = p.remark || '';

            if (p.image_url) {
                document.getElementById('previewImg').src = '../' + p.image_url;
                document.getElementById('previewImg').style.display = 'block';
            } else {
                document.getElementById('previewImg').style.display = 'none';
            }

            showModal('productModal');
        } catch (err) {
            console.error(err);
            alert('获取商品信息失败');
        }
    }

    async function openPurchaseModal(productId) {
        const p = allProducts.find(x => x.id === productId);
        if (!p) return;

        document.getElementById('purchaseProductId').value = productId;
        document.getElementById('purchaseProductName').textContent = p.common_name || p.name;
        document.getElementById('purchaseProductBarcode').textContent = ' - ' + p.barcode;

        // 确保状态输入框已渲染
        await renderPurchaseConditions();

        // 清空所有状态输入框
        const conditionTypes = systemSettings.condition_types || [
            { key: 'sealed', name: '原盒未拆' },
            { key: 'opened', name: '拆盒无瑕' },
            { key: 'boxless', name: '无盒无瑕' },
            { key: 'flawed', name: '微瑕' }
        ];

        conditionTypes.forEach(condition => {
            const priceEl = document.getElementById('price_' + condition.key);
            const qtyEl = document.getElementById('qty_' + condition.key);
            const suggEl = document.getElementById('sugg_' + condition.key);
            
            if (priceEl) priceEl.value = '';
            if (qtyEl) qtyEl.value = '0';
            if (suggEl) suggEl.value = '';
        });

        // 不再自动填充建议售价，让用户手动输入

        document.getElementById('supplier').value = '';
        document.getElementById('purchaseRemark').value = '';
        showModal('purchaseModal');
    }

    function showModal(id) {
        document.getElementById(id).classList.add('show');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('show');
    }

    async function uploadImage(e) {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('image', file);
        const series = document.getElementById('productSeries').value.trim();
        if (series) {
            formData.append('series', series);
        }

        try {
            const res = await fetch('../api/upload_image.php', {
                method: 'POST',
                body: formData
            });

            if (!res.ok) {
                throw new Error('服务器响应错误: ' + res.status);
            }

            const result = await res.json();

            if (result && result.success && result.data && result.data.url) {
                document.getElementById('imageUrl').value = result.data.url;
                document.getElementById('previewImg').src = '../' + result.data.url;
                document.getElementById('previewImg').style.display = 'block';
            } else if (result && result.error) {
                alert('上传失败: ' + result.error);
            } else {
                console.error('Unexpected response:', result);
                alert('上传失败: 服务器返回格式错误');
            }
        } catch (err) {
            alert('上传失败: ' + err.message);
            console.error('Upload error:', err);
        }
    }

    async function saveProduct(event) {
        event.preventDefault();
        const id = document.getElementById('productId').value;
        const data = {
            name: document.getElementById('productName').value,
            common_name: document.getElementById('productCommonName').value || null,
            series: document.getElementById('productSeries').value || null,
            brand: document.getElementById('productBrand').value || null,
            barcode: document.getElementById('productBarcode').value,
            qiandao_price: parseFloat(document.getElementById('qiandaoPrice').value) || null,
            release_date: document.getElementById('releaseDate').value || null,
            product_description: document.getElementById('productDescription').value || null,
            image_url: document.getElementById('imageUrl').value || null,
            remark: document.getElementById('productRemark').value || null
        };

        try {
            const url = id ? '../api/update_product.php' : '../api/add_product.php';
            if (id) data.id = parseInt(id);

            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await res.json();
            if (result.success) {
                closeModal('productModal');
                productDetails = {};
                await reloadWithFilter();
            } else {
                alert(result.error || '保存失败');
            }
        } catch (err) {
            alert('保存失败');
        }
    }

    async function savePurchase(e) {
        e.preventDefault();
        const submitBtn = document.querySelector('#purchaseForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '提交中...';
        }

        const productId = parseInt(document.getElementById('purchaseProductId').value);
        const supplier = document.getElementById('supplier').value || null;
        const remark = document.getElementById('purchaseRemark').value || null;

        // 使用系统配置中的状态类型
        const conditionTypes = systemSettings.condition_types || [
            { key: 'sealed', name: '原盒未拆' },
            { key: 'opened', name: '拆盒无瑕' },
            { key: 'boxless', name: '无盒无瑕' },
            { key: 'flawed', name: '微瑕' }
        ];
        
        const requests = [];

        try {
            for (const condition of conditionTypes) {
                const conditionType = condition.key;
                const qty = parseInt(document.getElementById('qty_' + conditionType).value) || 0;
                const purchasePrice = parseFloat(document.getElementById('price_' + conditionType).value) || 0;
                const suggestedPrice = parseFloat(document.getElementById('sugg_' + conditionType).value) || purchasePrice;

                if (qty > 0) {
                    requests.push(
                        fetch('../api/purchase_batch.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                product_id: productId,
                                condition_type: conditionType,
                                qty: qty,
                            purchase_price: purchasePrice,
                            suggested_price: suggestedPrice,
                            supplier: supplier,
                            remark: remark
                        })
                    })
                );
            }
        }

        if (!requests.length) {
            alert('请至少填写一种SKU的采购信息');
            return;
        }

        const responses = await Promise.all(requests);
        const results = await Promise.all(responses.map(r => r.json()));
        const failed = results.find(r => !r.success);
        if (failed) {
            alert(failed.error || '入库失败');
            return;
        }

        alert('入库成功！');
        closeModal('purchaseModal');
        productDetails = {};
        reloadWithFilter();
        } catch (err) {
            alert('入库失败');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = '确认入库';
            }
        }
    }

    async function saveAdjust(e) {
        e.preventDefault();

        const data = {
            product_id: parseInt(document.getElementById('adjustProductId').value),
            condition_type: document.getElementById('adjustConditionType').value,
            adjust_qty: parseInt(document.getElementById('adjustQty').value),
            remark: document.getElementById('adjustRemark').value || null
        };

        try {
            const res = await fetch('../api/adjust_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await res.json();
            if (result.success) {
                alert('调整成功');
                closeModal('adjustModal');
                productDetails = {};
                reloadWithFilter();
            } else {
                alert(result.error || '调整失败');
            }
        } catch (err) {
            alert('调整失败');
        }
    }

    async function savePrice(e) {
        e.preventDefault();

        const productId = parseInt(document.getElementById('priceProductId').value);
        const conditionType = document.getElementById('priceConditionType').value;
        const purchasePrice = parseFloat(document.getElementById('editPurchasePrice').value) || 0;
        const suggestedPrice = parseFloat(document.getElementById('editSuggestedPrice').value);

        try {
            const [checkRes] = await Promise.all([
                fetch('../api/get_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId })
                })
            ]);

            const checkData = await checkRes.json();

            if (checkData.success && checkData.data) {
                // 使用系统配置中的状态类型
                const typeNames = systemSettings.condition_types ? 
                    Object.fromEntries(systemSettings.condition_types.map(c => [c.key, c.name])) :
                    { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };
                const inventory = checkData.data.inventory[typeNames[conditionType]];
                const currentQty = inventory?.stock || 0;

                const data = {
                    product_id: productId,
                    condition_type: conditionType,
                    qty: currentQty,
                    purchase_price: purchasePrice,
                    suggested_price: suggestedPrice,
                    remark: '调整价格'
                };

                const res = await fetch('../api/purchase.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await res.json();
                if (result.success) {
                    alert('价格修改成功');
                    closeModal('priceModal');
                    productDetails = {};
                    reloadWithFilter();
                } else {
                    alert(result.error || '修改失败');
                }
            }
        } catch (err) {
            alert('修改失败');
        }
    }

    async function openEditBatchModal(productId, conditionType, batchId) {
        const p = allProducts.find(x => x.id === productId);
        if (!p) return;

        document.getElementById('editBatchProductId').value = productId;
        document.getElementById('editBatchConditionType').value = conditionType;
        document.getElementById('editBatchProductName').textContent = p.common_name || p.name;
        
        // 加载商品详情获取批次信息和状态名称
        try {
            const res = await fetch('../api/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            });
            const data = await res.json();
            if (data.success && data.data) {
                // 从商品实际 inventory 中找状态名称
                let conditionName = conditionType;
                Object.entries(data.data.inventory).forEach(([name, inv]) => {
                    if (inv.batches && inv.batches.some(b => b.condition_type === conditionType)) {
                        conditionName = name;
                    }
                });
                document.getElementById('editBatchConditionName').textContent = ' - ' + conditionName;
                
                // 找到对应的批次
                let targetBatch = null;
                Object.values(data.data.inventory).forEach(inv => {
                    if (inv.batches) {
                        const batch = inv.batches.find(b => b.batch_id === batchId);
                        if (batch) targetBatch = batch;
                    }
                });
                
                if (targetBatch) {
                    document.getElementById('editBatchId').value = batchId;
                    document.getElementById('editBatchNo').textContent = targetBatch.batch_no;
                    document.getElementById('editBatchQty').value = targetBatch.remaining_qty;
                    document.getElementById('editBatchPurchasePrice').value = targetBatch.purchase_price;
                    document.getElementById('editBatchSuggestedPrice').value = targetBatch.suggested_price;
                    document.getElementById('editBatchRemark').value = targetBatch.remark || '';
                }
            }
        } catch (err) {
            console.error(err);
        }

        showModal('editBatchModal');
    }

    async function saveEditBatch(e) {
        e.preventDefault();

        const batchId = parseInt(document.getElementById('editBatchId').value);
        const productId = parseInt(document.getElementById('editBatchProductId').value);
        const conditionType = document.getElementById('editBatchConditionType').value;
        const qty = parseInt(document.getElementById('editBatchQty').value);
        const purchasePrice = parseFloat(document.getElementById('editBatchPurchasePrice').value);
        const suggestedPrice = parseFloat(document.getElementById('editBatchSuggestedPrice').value);
        const remark = document.getElementById('editBatchRemark').value;

        try {
            const res = await fetch('../api/update_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    batch_id: batchId,
                    product_id: productId,
                    condition_type: conditionType,
                    qty: qty,
                    purchase_price: purchasePrice,
                    suggested_price: suggestedPrice,
                    remark: remark
                })
            });
            const result = await res.json();
            
            if (result.success) {
                alert('批次修改成功');
                closeModal('editBatchModal');
                // 重新加载产品详情
                if (currentProductDetailId) {
                    showStockDetail(currentProductDetailId, '');
                }
                reloadWithFilter();
            } else {
                alert(result.error || '修改失败');
            }
        } catch (err) {
            console.error(err);
            alert('修改失败');
        }
    }

    async function deleteProduct(id) {
        // 显示确认对话框
        showDeleteConfirmDialog(id);
    }

    function showDeleteConfirmDialog(productId) {
        // 创建确认对话框
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        
        dialog.innerHTML = `
            <div style="background: var(--bg-surface); padding: 30px; border-radius: 12px; max-width: 400px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
                <h3 style="margin-bottom: 15px; color: var(--text);">确认删除商品</h3>
                <p style="color: var(--text-secondary); margin-bottom: 25px; line-height: 1.5;">
                    确定要删除这个商品吗？<br>
                    所有相关的库存和销售记录也会被删除，此操作不可恢复。
                </p>
                <div style="display: flex; gap: 10px;">
                    <button id="cancelDelete" class="btn btn-secondary" style="flex:1;">取消</button>
                    <button id="confirmDelete" style="flex: 1; padding: 12px; border: none; background: var(--danger); color: white; border-radius: 6px; cursor: pointer;">确认删除</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(dialog);
        
        // 绑定事件
        document.getElementById('cancelDelete').onclick = () => {
            document.body.removeChild(dialog);
        };
        
        document.getElementById('confirmDelete').onclick = () => {
            document.body.removeChild(dialog);
            performDelete(productId);
        };
        
        // 点击背景关闭
        dialog.onclick = (e) => {
            if (e.target === dialog) {
                document.body.removeChild(dialog);
            }
        };
    }

    async function performDelete(id) {
        try {
            const res = await fetch('../api/delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: id })
            });
            const result = await res.json();
            if (result.success) {
                // 删除成功，直接刷新列表，不再提示
                productDetails = {};
                reloadWithFilter();
            } else {
                // 只在失败时显示错误
                showErrorToast(result.error || '删除失败');
            }
        } catch (err) {
            showErrorToast('删除失败');
        }
    }

    function toggleSelectAll(checked) {
        document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = checked);
        updateBatchDeleteButton();
    }

    function updateBatchDeleteButton() {
        const checked = document.querySelectorAll('.product-checkbox:checked');
        const btn = document.getElementById('batchDeleteBtn');
        const countEl = document.getElementById('selectedCount');
        if (checked.length > 0) {
            countEl.textContent = checked.length;
            btn.style.display = 'inline-flex';
        } else {
            btn.style.display = 'none';
        }
    }

    function batchDelete() {
        const checked = document.querySelectorAll('.product-checkbox:checked');
        if (checked.length === 0) return;

        const ids = Array.from(checked).map(cb => parseInt(cb.value));
        const dialog = document.createElement('div');
        dialog.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;

        dialog.innerHTML = `
            <div style="background: var(--bg-surface); padding: 30px; border-radius: 12px; max-width: 420px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
                <h3 style="margin-bottom: 15px; color: var(--text);">确认批量删除</h3>
                <p style="color: var(--text-secondary); margin-bottom: 25px; line-height: 1.5;">
                    确定要删除选中的 <strong style="color: var(--danger);">${ids.length}</strong> 个商品吗？<br>
                    所有相关的库存和销售记录也会被删除，此操作不可恢复。
                </p>
                <div style="display: flex; gap: 10px;">
                    <button id="cancelBatchDelete" class="btn btn-secondary" style="flex:1;">取消</button>
                    <button id="confirmBatchDelete" style="flex: 1; padding: 12px; border: none; background: var(--danger); color: white; border-radius: 6px; cursor: pointer;">确认删除</button>
                </div>
            </div>
        `;

        document.body.appendChild(dialog);

        document.getElementById('cancelBatchDelete').onclick = () => {
            document.body.removeChild(dialog);
        };

        document.getElementById('confirmBatchDelete').onclick = async () => {
            document.body.removeChild(dialog);
            try {
                const res = await fetch('../api/delete_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_ids: ids })
                });
                const result = await res.json();
                if (result.success) {
                    productDetails = {};
                    await reloadWithFilter();
                    updateBatchDeleteButton();
                } else {
                    showErrorToast(result.error || '批量删除失败');
                }
            } catch (err) {
                showErrorToast('批量删除失败');
            }
        };

        dialog.onclick = (e) => {
            if (e.target === dialog) {
                document.body.removeChild(dialog);
            }
        };
    }

    function showErrorToast(message) {
        // 创建错误提示
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--danger);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 10000;
            font-size: 14px;
        `;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // 3秒后自动消失
        setTimeout(() => {
            if (document.body.contains(toast)) {
                document.body.removeChild(toast);
            }
        }, 3000);
    }

    // 批量导入功能
    function openImportModal() {
        document.getElementById('importModal').classList.add('show');
    }

    function closeImportModal() {
        document.getElementById('importModal').classList.remove('show');
        document.getElementById('importFile').value = '';
        document.getElementById('importResult').innerHTML = '';
    }

    async function importProducts() {
        const fileInput = document.getElementById('importFile');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('请选择要导入的文件');
            return;
        }

        const formData = new FormData();
        formData.append('import_file', file);

        try {
            document.getElementById('importResult').innerHTML = '<div style="text-align:center; padding:20px;"><div style="color:var(--primary);">正在导入，请稍候...</div></div>';
            
            const response = await fetch('../api/bulk_import_products.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                const { success_count, total_count, errors } = result.data;
                let resultHtml = `
                    <div style="padding:20px;">
                        <div style="color:var(--success); font-size:18px; margin-bottom:15px;">
                            ✅ 导入完成！成功导入 ${success_count} 个商品，共处理 ${total_count} 个商品
                        </div>
                `;
                
                if (errors && errors.length > 0) {
                    resultHtml += `
                        <div style="color:var(--danger); margin-bottom:15px;">
                            <strong>导入错误：</strong>
                            <ul style="margin:10px 0; padding-left:20px;">
                    `;
                    errors.forEach(error => {
                        resultHtml += `<li>${error}</li>`;
                    });
                    resultHtml += `
                            </ul>
                        </div>
                    `;
                }
                
                resultHtml += `
                    <div style="text-align:center; margin-top:20px;">
                        <button class="btn btn-primary" onclick="closeImportModal(); reloadWithFilter();">确定</button>
                    </div>
                </div>
                `;
                
                document.getElementById('importResult').innerHTML = resultHtml;
            } else {
                document.getElementById('importResult').innerHTML = `
                    <div style="padding:20px; color:var(--danger);">
                        ❌ 导入失败：${result.message}
                    </div>
                `;
            }
        } catch (error) {
            console.error('导入错误:', error);
            let errorMessage = '网络错误';
            
            // 尝试从响应中获取具体错误信息
            if (error.response) {
                try {
                    const errorData = await error.response.json();
                    errorMessage = errorData.message || errorData.error || '服务器错误';
                } catch (e) {
                    errorMessage = `服务器错误 (${error.response.status})`;
                }
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            document.getElementById('importResult').innerHTML = `
                <div style="padding:20px; color:var(--danger);">
                    ❌ 导入失败：${errorMessage}
                </div>
            `;
        }
    }

    // 动态生成状态输入框
    async function renderPurchaseConditions() {
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            
            let conditionTypes = [
                { key: 'sealed', name: '原盒未拆', color: '#10b981' },
                { key: 'opened', name: '拆盒无瑕', color: '#3b82f6' },
                { key: 'boxless', name: '无盒无瑕', color: '#f59e0b' },
                { key: 'flawed', name: '微瑕', color: '#ef4444' }
            ];
            
            if (data.success && data.settings && data.settings.condition_types) {
                conditionTypes = data.settings.condition_types;
            }
            
            const container = document.getElementById('purchaseConditionsContainer');
            container.innerHTML = '';
            
            conditionTypes.forEach((condition, index) => {
                const number = index < 9 ? (index + 1).toString() : '0';
                const borderStyle = index < conditionTypes.length - 1 ? 'border-bottom:1px solid var(--border);' : '';
                
                const div = document.createElement('div');
                div.style.cssText = `display:grid; grid-template-columns:1.5fr 80px 1fr 1fr; gap:10px; align-items:center; padding:8px 0; ${borderStyle}`;
                div.innerHTML = `
                    <div><span class="condition-badge condition-${condition.key}">${number} ${condition.name}</span></div>
                    <div><input type="number" min="0" class="form-input" id="qty_${condition.key}" value="0" style="padding:8px; font-size:14px; text-align:center;"></div>
                    <div><input type="number" step="0.01" class="form-input" id="price_${condition.key}" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                    <div><input type="number" step="0.01" class="form-input" id="sugg_${condition.key}" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                `;
                container.appendChild(div);
            });
        } catch (error) {
            console.error('加载状态配置失败:', error);
        }
    }

    async function downloadTemplate() {
        try {
            // 获取系统配置
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            
            let conditionTypes = [
                { name: '原盒未拆', key: 'sealed' },
                { name: '拆盒无瑕', key: 'opened' },
                { name: '无盒无瑕', key: 'boxless' },
                { name: '微瑕', key: 'flawed' }
            ];
            
            if (data.success && data.settings && data.settings.condition_types) {
                conditionTypes = data.settings.condition_types;
            }
            
            // 构建表头
            const headers = [
                '商品名称', '常用名称', '系列', '品牌', '条码', '参考价', '发售时间', '产品介绍', '图片链接'
            ];
            
            // 为每个状态添加数量、进价、售价列
            conditionTypes.forEach(condition => {
                headers.push(`${condition.name}数量`);
                headers.push(`${condition.name}进价`);
                headers.push(`${condition.name}售价`);
            });
            
            // 添加最后的列
            headers.push('供应商', '备注');
            
            // 构建示例数据行
            const exampleRow = [
                'LABUBU 秘境夜游', '小夜游', 'LABUBU', 'POP MART', '6901234567001', '299.00', '2024-01-15', 'LABUBU秘境夜游系列盲盒', ''
            ];
            
            // 为每个状态添加示例数据
            conditionTypes.forEach((condition, index) => {
                const exampleQty = index === 0 ? '10' : (index === 1 ? '5' : (index === 2 ? '3' : '2'));
                const examplePurchase = [180, 150, 120, 80][index] || '100';
                const exampleSuggested = [280, 240, 200, 150][index] || '180';
                exampleRow.push(exampleQty, examplePurchase + '.00', exampleSuggested + '.00');
            });
            
            // 添加最后的示例数据
            exampleRow.push('官方渠道', '');
            
            const templateData = [headers, exampleRow];
            
            // 创建CSV内容
            const csvContent = templateData.map(row => row.join(',')).join('\n');
            
            // 创建下载链接
            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', '商品导入模板.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
        } catch (error) {
            console.error('下载模板失败:', error);
            alert('下载模板失败，请重试');
        }
    }

    function exportInventory() {
        window.open('../api/export_inventory.php', '_blank');
    }

    async function initializePage() {
        // 加载系统设置
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            if (data.success && data.settings) {
                systemSettings = data.settings;
            }
        } catch (e) {
            console.log('使用默认系统设置');
        }
        
        // 渲染动态内容
        await renderPurchaseConditions();
        
        // 加载产品列表
        loadProducts();
    }

    initializePage();

    /* ========== 进货价趋势图（按 SKU 分色显示） ========== */
    let priceTrendChartInstance = null;

    function getColorForSku(index) {
        const palette = ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];
        return palette[index % palette.length];
    }

    function getSkuName(key) {
        if (!systemSettings || !systemSettings.condition_types) return key;
        const ct = systemSettings.condition_types.find(c => c.key === key);
        return ct ? ct.name : key;
    }

    async function loadPriceTrend(productId, startDate, endDate) {
        try {
            const res = await fetch('../api/get_purchase_price_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, start_date: startDate, end_date: endDate })
            });
            const data = await res.json();
            if (!data.success) return;

            const records = data.data.records || [];
            const section = document.getElementById('priceTrendSection');
            const canvas = document.getElementById('priceTrendChart');
            const empty = document.getElementById('priceTrendEmpty');

            if (records.length === 0) {
                section.style.display = 'block';
                canvas.style.display = 'none';
                empty.style.display = 'block';
                return;
            }

            canvas.style.display = 'block';
            empty.style.display = 'none';
            section.style.display = 'block';

            // 按 SKU(condition_type) 分组，每组内按日期聚合取平均进价
            const skuGroups = {};
            records.forEach(r => {
                const sku = r.condition_type;
                if (!skuGroups[sku]) skuGroups[sku] = {};
                const day = r.purchased_at.slice(0, 10);
                const p = parseFloat(r.purchase_price);
                if (!skuGroups[sku][day]) skuGroups[sku][day] = { sum: 0, count: 0 };
                skuGroups[sku][day].sum += p;
                skuGroups[sku][day].count++;
            });

            // 收集所有日期作为 X 轴
            const allDays = new Set();
            Object.values(skuGroups).forEach(days => Object.keys(days).forEach(d => allDays.add(d)));
            const sortedDays = Array.from(allDays).sort();

            // 构造每个 SKU 的数据集
            const datasets = [];
            let skuIndex = 0;
            for (const [sku, days] of Object.entries(skuGroups)) {
                const color = getColorForSku(skuIndex++);
                const data = sortedDays.map(d => {
                    if (days[d]) return +(days[d].sum / days[d].count).toFixed(2);
                    return null; // 该 SKU 在该日期无数据
                });
                datasets.push({
                    label: getSkuName(sku),
                    data: data,
                    borderColor: color,
                    backgroundColor: color + '18',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: color,
                    fill: false,
                    tension: 0.3,
                    spanGaps: false
                });
            }

            if (priceTrendChartInstance) {
                priceTrendChartInstance.destroy();
            }

            const ctx = canvas.getContext('2d');
            priceTrendChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: sortedDays,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: { size: 10 },
                                boxWidth: 12,
                                padding: 8,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': ¥' + ctx.parsed.y.toFixed(2)
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: { font: { size: 10 }, maxRotation: 45 },
                            grid: { display: false }
                        },
                        y: {
                            ticks: {
                                font: { size: 10 },
                                callback: v => '¥' + v.toFixed(0)
                            },
                            grid: { color: 'rgba(0,0,0,0.06)' }
                        }
                    }
                }
            });

            // 设置日期筛选器默认值
            if (records.length > 0) {
                const dates = records.map(r => r.purchased_at.slice(0, 10)).sort();
                const sdInput = document.getElementById('trendStartDate');
                const edInput = document.getElementById('trendEndDate');
                if (!sdInput.value) sdInput.value = dates[0];
                if (!edInput.value) edInput.value = dates[dates.length - 1];
            }
        } catch (err) {
            console.error('加载进货价趋势失败:', err);
        }
    }

    function refreshPriceTrend() {
        const pid = currentProductDetailId;
        if (!pid) return;
        const sd = document.getElementById('trendStartDate').value;
        const ed = document.getElementById('trendEndDate').value;
        loadPriceTrend(pid, sd, ed);
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
</body>
</html>