<?php
$pageTitle = '商品管理';
$currentPage = 'products';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">🏷️ 商品管理</div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
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
            <div style="background:linear-gradient(135deg, #ef4444, #dc2626); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">库存总价值</div>
                <div id="statTotalValue" style="font-size:36px; font-weight:bold;">¥-</div>
            </div>
            <div style="background:linear-gradient(135deg, #8b5cf6, #7c3aed); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">本月入库</div>
                <div id="statMonthPurchase" style="font-size:36px; font-weight:bold;">-</div>
            </div>
            <div style="background:linear-gradient(135deg, #06b6d4, #0891b2); color:white; padding:20px; border-radius:12px;">
                <div style="font-size:14px; opacity:0.9;">本月销售</div>
                <div id="statMonthSales" style="font-size:36px; font-weight:bold;">¥-</div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div class="search-bar" style="margin:0; flex:1;">
                    <input type="text" id="searchInput" placeholder="搜索商品名称、常用名或条码..." onkeyup="searchProducts()">
                    <select id="seriesFilter" onchange="searchProducts()">
                        <option value="">全部系列</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="openAddModal()" style="margin-left:20px;">
                    ➕ 添加商品
                </button>
                <button class="btn btn-success" onclick="openImportModal()" style="margin-left:10px;">
                    📁 批量导入
                </button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>图片</th>
                        <th>条码</th>
                        <th>商品名称</th>
                        <th>系列</th>
                        <th>参考价</th>
                        <th>库存状态</th>
                        <th>库存总量</th>
                        <th>库存价值</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="productList"></tbody>
            </table>
        </div>

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
                        <div style="display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; font-weight:bold; margin-bottom:10px; color:var(--text-secondary); font-size:12px;">
                            <div>商品状态</div>
                            <div>采购单价</div>
                            <div>数量</div>
                            <div>建议售价</div>
                        </div>

                        <div id="purchaseConditionsContainer">
                            <!-- 动态生成的状态输入框 -->
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

                <div style="margin-bottom:20px;">
                    <h4 style="margin-bottom:10px;">📊 状态分布</h4>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <div id="stockDetailDistribution"></div>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom:10px;">📦 批次详情</h4>
                    <table style="width:100%;">
                        <thead>
                            <tr>
                                <th>批次号</th>
                                <th>状态</th>
                                <th>进价</th>
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
                            <li>支持批量导入各状态库存</li>
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
    let allProducts = [];
    let productDetails = {};
    let currentProductDetailId = null;

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
            }

            const salesRes = await fetch('../api/sales_summary.php');
            const salesData = await salesRes.json();
            if (salesData.success) {
                document.getElementById('statMonthPurchase').textContent = salesData.data.month_purchase_count || '-';
                document.getElementById('statMonthSales').textContent = '¥' + (salesData.data.month_sales_amount || 0).toLocaleString();
            }
        } catch (err) {
            console.error(err);
        }
    }

    function searchProducts() {
        const keyword = document.getElementById('searchInput').value.toLowerCase();
        const series = document.getElementById('seriesFilter').value;

        const filtered = allProducts.filter(p => {
            const matchKeyword = !keyword || 
                p.name.toLowerCase().includes(keyword) ||
                (p.common_name && p.common_name.toLowerCase().includes(keyword)) ||
                p.barcode.includes(keyword);
            const matchSeries = !series || p.series === series;
            return matchKeyword && matchSeries;
        });

        renderProducts(filtered);
    }

    function renderProducts(products) {
        const tbody = document.getElementById('productList');
        if (!products.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无商品，点击上方"添加商品"创建</td></tr>';
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
            const totalValue = getTotalValue(p.inventory_summary);
            const stockClass = totalStock <= 0 ? 'text-muted' : totalStock <= 5 ? 'text-warning' : '';

            return `
                <tr>
                    <td>${imageHtml}</td>
                    <td><code style="background:var(--bg-hover);padding:4px 8px;border-radius:4px;">${p.barcode}</code></td>
                    <td>${nameDisplay}</td>
                    <td>${p.series || '-'}</td>
                    <td style="font-size:18px;font-weight:bold;color:var(--danger);">${p.qiandao_price ? '¥' + parseFloat(p.qiandao_price).toFixed(2) : '-'}</td>
                    <td style="cursor:pointer;" onclick="showStockDetail(${p.id}, '${p.common_name || p.name}')">
                        ${inventoryHtml}
                        <div style="font-size:12px;color:var(--text-secondary);margin-top:5px;">点击查看详情 ▼</div>
                    </td>
                    <td style="font-weight:bold; ${stockClass}; font-size:18px;">${totalStock}</td>
                    <td style="font-weight:bold; color:var(--success);">¥${totalValue.toFixed(0)}</td>
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

    function getTotalValue(inventory) {
        if (!inventory) return 0;
        let total = 0;
        Object.values(inventory).forEach(item => {
            total += (item.total_stock || 0) * (item.suggested_price || 0);
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
                                    <button class="btn btn-sm btn-secondary" onclick="unifyPrice(${productId}, '${ct.key}')" title="统一修改此状态所有批次的售价">💰 改价</button>
                                </div>
                            </div>
                        `;

                        inv[name].batches.forEach(batch => {
                            batchesHtml += `
                                <tr>
                                    <td><code>${batch.batch_no}</code></td>
                                    <td>${name}</td>
                                    <td>¥${parseFloat(batch.purchase_price).toFixed(2)}</td>
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
                loadProducts();
            } else {
                alert('修改失败: ' + data.error);
            }
        } catch (err) {
            alert('修改失败: ' + err.message);
        }
    }

    function generateBarcode() {
        const randomNum = String(Math.floor(Math.random() * 100000)).padStart(5, '0');
        return '69414486' + randomNum;
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
                alert(id ? '修改成功' : '添加成功');
                closeModal('productModal');
                productDetails = {};
                loadProducts();
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

                if (qty > 0 && purchasePrice > 0) {
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
            alert('请至少填写一种商品状态的采购信息');
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
        loadProducts();
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
                loadProducts();
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
                    loadProducts();
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
                loadProducts();
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
                loadProducts();
            } else {
                // 只在失败时显示错误
                showErrorToast(result.error || '删除失败');
            }
        } catch (err) {
            showErrorToast('删除失败');
        }
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
        document.getElementById('importModal').style.display = 'flex';
    }

    function closeImportModal() {
        document.getElementById('importModal').style.display = 'none';
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
                        <button class="btn btn-primary" onclick="closeImportModal(); loadProducts();">确定</button>
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
                div.style.cssText = `display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; align-items:center; padding:8px 0; ${borderStyle}`;
                div.innerHTML = `
                    <div><span class="condition-badge condition-${condition.key}">${number} ${condition.name}</span></div>
                    <div><input type="number" step="0.01" class="form-input" id="price_${condition.key}" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                    <div><input type="number" min="0" class="form-input" id="qty_${condition.key}" value="0" style="padding:8px; font-size:14px; text-align:center;"></div>
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
    </script>
</body>
</html>