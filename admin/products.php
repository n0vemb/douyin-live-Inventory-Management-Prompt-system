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
            <div class="modal-content" style="max-width:650px;">
                <div class="modal-header">
                    <h3 class="modal-title" id="purchaseModalTitle">采购入库</h3>
                    <button class="modal-close" onclick="closeModal('purchaseModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="purchaseProductName"></strong>
                    <span style="color:#666;" id="purchaseProductBarcode"></span>
                </div>
                <form id="purchaseForm" onsubmit="savePurchase(event)">
                    <input type="hidden" id="purchaseProductId">

                    <div style="background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #e2e8f0;">
                        <div style="display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; font-weight:bold; margin-bottom:10px; color:#64748b; font-size:12px;">
                            <div>商品状态</div>
                            <div>采购单价</div>
                            <div>数量</div>
                            <div>建议售价</div>
                        </div>

                        <div style="display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; align-items:center; padding:8px 0; border-bottom:1px solid #e2e8f0;">
                            <div><span class="condition-badge condition-sealed">① 原盒未拆</span></div>
                            <div><input type="number" step="0.01" class="form-input" id="price_sealed" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                            <div><input type="number" min="0" class="form-input" id="qty_sealed" value="0" style="padding:8px; font-size:14px; text-align:center;"></div>
                            <div><input type="number" step="0.01" class="form-input" id="sugg_sealed" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                        </div>

                        <div style="display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; align-items:center; padding:8px 0; border-bottom:1px solid #e2e8f0;">
                            <div><span class="condition-badge condition-opened">② 拆盒无瑕</span></div>
                            <div><input type="number" step="0.01" class="form-input" id="price_opened" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                            <div><input type="number" min="0" class="form-input" id="qty_opened" value="0" style="padding:8px; font-size:14px; text-align:center;"></div>
                            <div><input type="number" step="0.01" class="form-input" id="sugg_opened" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                        </div>

                        <div style="display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; align-items:center; padding:8px 0; border-bottom:1px solid #e2e8f0;">
                            <div><span class="condition-badge condition-boxless">③ 无盒无瑕</span></div>
                            <div><input type="number" step="0.01" class="form-input" id="price_boxless" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                            <div><input type="number" min="0" class="form-input" id="qty_boxless" value="0" style="padding:8px; font-size:14px; text-align:center;"></div>
                            <div><input type="number" step="0.01" class="form-input" id="sugg_boxless" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                        </div>

                        <div style="display:grid; grid-template-columns:1.5fr 1fr 80px 1fr; gap:10px; align-items:center; padding:8px 0;">
                            <div><span class="condition-badge condition-flawed">④ 微瑕</span></div>
                            <div><input type="number" step="0.01" class="form-input" id="price_flawed" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
                            <div><input type="number" min="0" class="form-input" id="qty_flawed" value="0" style="padding:8px; font-size:14px; text-align:center;"></div>
                            <div><input type="number" step="0.01" class="form-input" id="sugg_flawed" placeholder="¥0" style="padding:8px; font-size:14px;"></div>
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
            <div class="modal-content" style="max-width:700px; max-height:80vh; overflow-y:auto;">
                <div class="modal-header">
                    <h3 class="modal-title" id="stockDetailTitle">库存详情</h3>
                    <button class="modal-close" onclick="closeModal('stockDetailModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="stockDetailProductName"></strong>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                    <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                        <div style="font-size:14px; color:#666;">总库存</div>
                        <div id="stockDetailTotalQty" style="font-size:32px; font-weight:bold; color:#10b981;">-</div>
                    </div>
                    <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                        <div style="font-size:14px; color:#666;">库存价值</div>
                        <div id="stockDetailTotalValue" style="font-size:32px; font-weight:bold; color:#ef4444;">¥-</div>
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
                            </tr>
                        </thead>
                        <tbody id="stockDetailBatches"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 库存调整模态框 -->
        <div class="modal" id="adjustModal">
            <div class="modal-content" style="max-width:450px;">
                <div class="modal-header">
                    <h3 class="modal-title" id="adjustModalTitle">调整库存</h3>
                    <button class="modal-close" onclick="closeModal('adjustModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="adjustProductName"></strong>
                    <span style="color:#666;" id="adjustConditionName"></span>
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
            <div class="modal-content" style="max-width:450px;">
                <div class="modal-header">
                    <h3 class="modal-title" id="priceModalTitle">修改价格</h3>
                    <button class="modal-close" onclick="closeModal('priceModal')">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <strong style="font-size:18px;" id="priceProductName"></strong>
                    <span style="color:#666;" id="priceConditionName"></span>
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
    </div>

    <script>
    let allProducts = [];
    let productDetails = {};

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
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#999;padding:40px;">暂无商品，点击上方"添加商品"创建</td></tr>';
            return;
        }

        tbody.innerHTML = products.map(p => {
            const imageHtml = p.image_url
                ? `<img src="../${p.image_url}" style="width:50px; height:50px; object-fit:cover; border-radius:4px;">`
                : '<span style="font-size:24px;">📦</span>';

            const nameDisplay = p.common_name 
                ? `<strong>${p.common_name}</strong><br><span style="font-size:12px;color:#999;">${p.name}</span>`
                : `<strong>${p.name}</strong>`;

            const inventoryHtml = renderInventoryBadges(p.id, p.inventory_summary);
            const totalStock = getTotalStock(p.inventory_summary);
            const totalValue = getTotalValue(p.inventory_summary);
            const stockClass = totalStock <= 0 ? 'text-muted' : totalStock <= 5 ? 'text-warning' : '';

            return `
                <tr>
                    <td>${imageHtml}</td>
                    <td><code style="background:#f3f4f6;padding:4px 8px;border-radius:4px;">${p.barcode}</code></td>
                    <td>${nameDisplay}</td>
                    <td>${p.series || '-'}</td>
                    <td style="font-size:18px;font-weight:bold;color:#ef4444;">${p.qiandao_price ? '¥' + parseFloat(p.qiandao_price).toFixed(2) : '-'}</td>
                    <td style="cursor:pointer;" onclick="showStockDetail(${p.id}, '${p.common_name || p.name}')">
                        ${inventoryHtml}
                        <div style="font-size:12px;color:#666;margin-top:5px;">点击查看详情 ▼</div>
                    </td>
                    <td style="font-weight:bold; ${stockClass}; font-size:18px;">${totalStock}</td>
                    <td style="font-weight:bold; color:#10b981;">¥${totalValue.toFixed(0)}</td>
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

        const typeNames = { sealed: '原盒', opened: '拆盒', boxless: '无盒', flawed: '微瑕' };
        const typeColors = { sealed: 'condition-sealed', opened: 'condition-opened', boxless: 'condition-boxless', flawed: 'condition-flawed' };

        const badges = [];
        const types = ['sealed', 'opened', 'boxless', 'flawed'];
        
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
                const conditionNames = ['原盒未拆', '拆盒无瑕', '无盒无瑕', '微瑕'];
                
                let totalQty = 0;
                let totalValue = 0;
                let distributionHtml = '';
                let batchesHtml = '';

                conditionNames.forEach(name => {
                    if (inv[name]) {
                        const qty = inv[name].stock;
                        const value = inv[name].suggested_price * qty;
                        totalQty += qty;
                        totalValue += value;

                        distributionHtml += `
                            <div style="background:#f3f4f6; padding:12px 16px; border-radius:8px; min-width:140px;">
                                <div style="font-size:14px; color:#666;">${name}</div>
                                <div style="font-size:20px; font-weight:bold; color:#10b981;">${qty}</div>
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
                                </tr>
                            `;
                        });
                    }
                });

                document.getElementById('stockDetailTotalQty').textContent = totalQty;
                document.getElementById('stockDetailTotalValue').textContent = '¥' + totalValue.toLocaleString();
                document.getElementById('stockDetailDistribution').innerHTML = distributionHtml;
                document.getElementById('stockDetailBatches').innerHTML = batchesHtml || '<tr><td colspan="6" style="text-align:center;color:#999;">暂无批次记录</td></tr>';
            }
        } catch (err) {
            console.error(err);
        }

        showModal('stockDetailModal');
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

    function editProduct(id) {
        const p = allProducts.find(x => x.id === id);
        if (!p) return;

        document.getElementById('modalTitle').textContent = '编辑商品';
        document.getElementById('productId').value = p.id;
        document.getElementById('productName').value = p.name;
        document.getElementById('productCommonName').value = p.common_name || '';
        document.getElementById('productSeries').value = p.series || '';
        document.getElementById('productBarcode').value = p.barcode;
        document.getElementById('productBarcode').setAttribute('required', 'required');
        document.getElementById('qiandaoPrice').value = p.qiandao_price || '';
        document.getElementById('imageUrl').value = p.image_url || '';
        document.getElementById('productRemark').value = p.remark || '';

        if (p.image_url) {
            document.getElementById('previewImg').src = '../' + p.image_url;
            document.getElementById('previewImg').style.display = 'block';
        } else {
            document.getElementById('previewImg').style.display = 'none';
        }

        showModal('productModal');
    }

    function openPurchaseModal(productId) {
        const p = allProducts.find(x => x.id === productId);
        if (!p) return;

        document.getElementById('purchaseProductId').value = productId;
        document.getElementById('purchaseProductName').textContent = p.common_name || p.name;
        document.getElementById('purchaseProductBarcode').textContent = ' - ' + p.barcode;

        ['sealed', 'opened', 'boxless', 'flawed'].forEach(type => {
            document.getElementById('price_' + type).value = '';
            document.getElementById('qty_' + type).value = '0';
            document.getElementById('sugg_' + type).value = '';
        });

        if (p.qiandao_price) {
            document.getElementById('sugg_sealed').value = (p.qiandao_price * 0.9).toFixed(2);
            document.getElementById('sugg_opened').value = (p.qiandao_price * 0.8).toFixed(2);
            document.getElementById('sugg_boxless').value = (p.qiandao_price * 0.7).toFixed(2);
            document.getElementById('sugg_flawed').value = (p.qiandao_price * 0.5).toFixed(2);
        }

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
            barcode: document.getElementById('productBarcode').value,
            qiandao_price: parseFloat(document.getElementById('qiandaoPrice').value) || null,
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

        const conditions = ['sealed', 'opened', 'boxless', 'flawed'];
        const requests = [];

        try {
            for (const conditionType of conditions) {
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
                const typeNames = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };
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

    async function deleteProduct(id) {
        if (!confirm('确定要删除这个商品吗？所有库存和销售记录也会被删除。')) return;

        try {
            const res = await fetch('../api/delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: id })
            });
            const result = await res.json();
            if (result.success) {
                alert('删除成功');
                productDetails = {};
                loadProducts();
            } else {
                alert(result.error || '删除失败');
            }
        } catch (err) {
            alert('删除失败');
        }
    }

    loadProducts();
    </script>
</body>
</html>