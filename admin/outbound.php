<?php
$pageTitle = '商品出库';
$currentPage = 'outbound';
require_once __DIR__ . '/layout.php';
?>
        <div class="page-title">商品出库</div>

<style>
@media (max-width: 768px) {
  .ob-layout { flex-wrap: wrap !important; }
}
</style>

        <div class="ob-layout" style="display:flex; gap:20px; align-items:flex-start;">
            <div style="flex:1; min-width:0; display:flex; flex-direction:column; gap:12px;">
                <!-- 扫码区（在待出库商品上方） -->
                <div class="scan-bar">
            <div class="scan-bar-inner">
                <input type="text" id="scanInput" placeholder="📷 扫描条码或输入拼音搜索..." class="scan-input">
                <button type="button" id="voiceBtn" class="voice-btn" onclick="toggleVoiceInput()" title="语音录入（例如：梦游 未拆 2个）">🎤</button>
                <div class="scan-result" id="obResult" style="display:none;">
                    <span class="sr-product" id="obProductName"></span>
                    <span class="sr-sep">|</span>
                    <span class="sr-label">SKU</span>
                    <span class="condition-group" id="conditionGroup">
                        <button class="sr-arrow" onclick="cycleCondition(-1)">▲</button>
                        <span class="sr-condition" id="obCondition"></span>
                        <button class="sr-arrow" onclick="cycleCondition(1)">▼</button>
                    </span>
                    <span class="sr-sep">|</span>
                    <span class="sr-label">售价</span>
                    <input type="number" id="obPrice" step="0.01" placeholder="0.00" onfocus="this.select()" autocomplete="off">
                    <button class="btn btn-sm btn-success" onclick="confirmBarAdd()">+ 添加</button>
                </div>
                <div class="search-dropdown" id="obSearchDropdown"></div>
            </div>
        </div>

        <!-- 语音识别提示浮层 -->
        <div id="voiceToast" class="voice-toast">
            <div class="vt-text">🎤 说 商品名+SKU名+数量，如「梦游 未拆 2个」</div>
            <div class="vt-recognized" id="vtText"></div>
            <div class="vt-confirm" id="vtConfirm"></div>
        </div>

                <div class="card">
                    <div class="card-title">🛒 待出库商品</div>
                    <table id="outboundTable">
                        <thead>
                            <tr>
                                <th>商品</th>
                                <th>SKU</th>
                                <th>批次</th>
                                <th>进价</th>
                                <th>售价</th>
                                <th>数量</th>
                                <th>小计</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="outboundItems"></tbody>
                    </table>
                    <div id="emptyCart" style="text-align:center; padding:60px; color:var(--text-tertiary); font-size:18px;">
                        扫描条码添加出库商品
                    </div>
                </div>
            </div>

            <div class="card" style="width:320px; flex-shrink:0;">
                <div class="card-title">💰 结算信息</div>
                <div style="background:var(--bg-hover); padding:20px; border-radius:12px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text-secondary);">商品种类</span>
                        <span id="totalTypes" style="font-weight:bold;">0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span style="color:var(--text-secondary);">商品总数</span>
                        <span id="totalQty" style="font-weight:bold;">0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:24px; margin-top:15px; padding-top:15px; border-top:2px solid var(--border);">
                        <span>合计金额</span>
                        <span id="totalAmount" style="font-weight:bold; color:var(--success);">¥0.00</span>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">订单号（选填）</label>
                    <input type="text" class="form-input" id="orderNo" placeholder="外部订单号">
                </div>
                <div class="form-group">
                    <label class="form-label">直播平台</label>
                    <select class="form-input" id="outboundPlatform">
                        <option value="">-- 选填 --</option>
                        <option value="小红书">小红书</option>
                        <option value="抖音">抖音</option>
                        <option value="视频号">视频号</option>
                        <option value="其他平台">其他平台</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">账号（选填）</label>
                    <input type="text" class="form-input" id="outboundAccount" placeholder="对应直播账号">
                </div>
                <div class="form-group">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-input" id="outboundRemark" placeholder="备注信息">
                </div>
                <div class="form-group">
                    <label class="form-label">GMV 成交金额（选填）</label>
                    <input type="number" class="form-input" id="outboundGmv" step="0.01" placeholder="平台实际成交金额(含运费)">
                </div>
                <div class="form-group">
                    <label class="form-label">订单数 / 快递单数（选填）</label>
                    <input type="number" class="form-input" id="outboundOrderCount" step="1" placeholder="实际发货订单数">
                </div>
                <div class="form-group">
                    <label class="form-label">投流费用（选填）</label>
                    <input type="number" class="form-input" id="outboundAdSpend" step="0.01" placeholder="本次投放流量费用">
                </div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn btn-primary" onclick="showOutboundList()" style="flex:1;">📋 记录</button>
                    <button class="btn btn-success" onclick="confirmOutbound()" id="confirmBtn" disabled style="flex:1;">✅ 出库 (<span id="confirmCount">0</span>)</button>
                </div>
            </div>
        </div>

        <!-- 扫码栏浮层：滚动出屏时显示 -->
        <div class="scan-float-bar" id="scanFloatBar">
            <div class="scan-bar-inner">
                <input type="text" id="scanFloatInput" placeholder="📷 扫描条码或输入拼音搜索..." class="scan-input" style="width:220px;">
                <button class="btn btn-sm btn-success" onclick="document.getElementById('scanFloatInput').focus()">扫码</button>
            </div>
        </div>

        <style>
        .scan-float-bar {
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 18px;
            display: none;
            align-items: center;
            z-index: 500;
            box-shadow: 0 8px 40px rgba(0,0,0,0.5);
        }
        .scan-float-bar.show { display: flex; }
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
            width: 260px; font-size: 18px; padding: 10px 16px;
            border: 2px solid var(--border); border-radius: 8px;
            background: var(--bg-card); color: var(--text); outline: none;
            transition: border-color 0.2s; box-sizing: border-box;
        }
        .scan-input:focus { border-color: var(--primary); }
        .voice-btn {
            background: var(--bg-hover);
            border: none;
            width: 42px; height: 42px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .voice-btn:hover { background: var(--bg-active); }
        .voice-btn.listening {
            background: var(--danger);
            color: white;
            animation: voice-pulse 1s ease-in-out infinite;
        }
        @keyframes voice-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(248,113,113,0.5); }
            50% { box-shadow: 0 0 0 12px rgba(248,113,113,0); }
        }
        .voice-btn.processing {
            background: var(--warning);
            color: black;
        }
        .voice-toast {
            position: fixed;
            top: 70px; left: 50%; transform: translateX(-50%);
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 20px;
            z-index: 9999;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            display: none;
            min-width: 200px;
            max-width: 90vw;
            text-align: center;
            font-size: 15px;
        }
        .voice-toast.show { display: block; }
        .voice-toast .vt-text { color: var(--text-secondary); font-size: 12px; margin-bottom: 4px; }
        .voice-toast .vt-recognized { font-size: 18px; font-weight: 600; }
        .voice-toast .vt-confirm { font-size: 13px; color: var(--success); margin-top: 6px; }
        .scan-result { display: flex; align-items: center; gap: 8px; }
        .sr-product { font-weight: bold; font-size: 15px; white-space: nowrap; }
        .sr-sep { color: var(--text-tertiary); font-size: 16px; }
        .sr-label { font-size: 12px; color: var(--text-secondary); }
        .sr-condition { font-weight: bold; font-size: 15px; min-width: 60px; text-align: center; color: var(--primary); }
        .condition-group {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 8px; border: 2px solid transparent; border-radius: 8px;
            transition: all 0.2s;
        }
        .condition-group.active {
            border-color: var(--primary);
            background: rgba(99,102,241,0.12);
            box-shadow: 0 0 12px rgba(99,102,241,0.2);
        }
        .sr-arrow {
            background: var(--bg-card); border: 1px solid var(--border); color: var(--text);
            padding: 2px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; line-height: 1;
        }
        .sr-arrow:hover { background: var(--primary-light); border-color: var(--primary); }
        .scan-result input[type="number"] {
            width: 90px; padding: 6px 10px; border: 2px solid var(--border); border-radius: 6px;
            background: var(--bg-card); color: var(--success); font-weight: bold; font-size: 16px;
            text-align: center; outline: none; transition: border-color 0.2s;
        }
        .scan-result input[type="number"]:focus { border-color: var(--success); }

        /* 拼音搜索下拉框 */
        .search-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
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
        .search-dropdown-header {
            padding: 10px 14px 6px; border-bottom: 1px solid var(--border);
            background: var(--bg-hover);
        }
        .search-dropdown-header .sdi-product-name {
            font-weight: 600; font-size: 14px;
        }
        .search-dropdown-header .sdi-product-meta {
            font-size: 11px; color: var(--text-tertiary); margin-top: 2px;
        }
        .search-dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 8px 14px; border-bottom: 1px solid var(--border);
            font-size: 13px; transition: background 0.15s;
        }
        .search-dropdown-item:last-child { border-bottom: none; }
        .search-dropdown-item:hover { background: var(--bg-hover); }
        .sdi-stock { font-size: 12px; color: var(--text-secondary); min-width: 50px; }
        .sdi-price { font-weight: bold; font-size: 14px; min-width: 65px; text-align: right; }
        .sdi-add-btn {
            padding: 4px 14px; border-radius: 6px; border: none;
            background: var(--primary); color: #fff; font-size: 12px;
            cursor: pointer; font-weight: 600; white-space: nowrap; transition: 0.15s;
        }
        .sdi-add-btn:hover { opacity: 0.85; }
        </style>

        <div class="card">
            <div class="card-title">📊 库存概览</div>
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:20px;">
                <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">商品种类</div>
                    <div id="statTypes" style="font-size:32px; font-weight:bold;">-</div>
                </div>
                <div style="background:linear-gradient(135deg, #10b981, #059669); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">库存总量</div>
                    <div id="statTotalQty" style="font-size:32px; font-weight:bold;">-</div>
                </div>
                <div style="background:linear-gradient(135deg, #f59e0b, #d97706); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">库存总成本</div>
                    <div id="statTotalCost" style="font-size:32px; font-weight:bold;">¥-</div>
                </div>
                <div style="background:linear-gradient(135deg, #ef4444, #dc2626); color:white; padding:20px; border-radius:12px;">
                    <div style="font-size:14px; opacity:0.9;">库存总价值</div>
                    <div id="statTotalValue" style="font-size:32px; font-weight:bold;">¥-</div>
                </div>
            </div>

            <div style="margin-bottom:15px; display:flex; gap:10px;">
                <input type="text" id="stockSearch" placeholder="搜索商品..." style="flex:1; padding:10px; border:1px solid var(--border); border-radius:8px;" onkeyup="searchStock()">
                <select id="stockSeriesFilter" style="padding:10px; border:1px solid var(--border); border-radius:8px;" onchange="searchStock()">
                    <option value="">全部系列</option>
                </select>
            </div>

            <table>
                <thead>
                    <tr>
                        <th onclick="toggleSort()" style="cursor:pointer;user-select:none;">商品 <span id="sortIndicator"></span></th>
                        <th>SKU</th>
                        <th>批次</th>
                        <th>进价</th>
                        <th>库存</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="stockList"></tbody>
            </table>
        </div>

        <div class="modal" id="historyModal">
            <div class="modal-content modal-wide" style="max-height:80vh; overflow-y:auto;">
                <div class="modal-header">
                    <h3 class="modal-title">出库历史</h3>
                    <button class="modal-close" onclick="closeHistoryModal()">&times;</button>
                </div>
                <!-- 合并工具栏 -->
                <div id="mergeToolbar" style="display:none; padding:10px 0; border-bottom:1px solid var(--border); margin-bottom:12px;">
                    <span id="mergeSelectedCount" style="font-size:14px; color:var(--text-secondary); margin-right:12px;">已选 0 个批次</span>
                    <button class="btn btn-warning" onclick="showMergeDialog()" id="mergeBtn" disabled style="opacity:0.5;">🔗 合并选中批次</button>
                    <button class="btn btn-sm" onclick="clearMergeSelection()" style="margin-left:8px;">取消选择</button>
                </div>
                <div id="historyList"></div>
            </div>
        </div>
    </div>

    <!-- 合并确认弹窗 -->
    <div class="modal" id="mergeModal">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title">🔗 确认合并批次</h3>
                <button class="modal-close" onclick="closeMergeModal()">&times;</button>
            </div>
            <div style="padding:16px 0;">
                <p style="color:var(--text-secondary); margin-bottom:12px; font-size:14px;">选择一个批次作为合并后的<b>主批次</b>（保留其时间，其余批次的记录将移入主批次）：</p>
                <div id="mergeBatchList" style="max-height:250px; overflow-y:auto;"></div>
                <div style="margin-top:16px; padding:12px; background:var(--bg-hover); border-radius:8px; font-size:13px; color:var(--text-secondary);">
                    <p>✅ 同商品+同SKU+同价格 → 合并数量</p>
                    <p>✅ 不同价格 → 各自保留</p>
                    <p>✅ 财务数据（GMV/订单数/投流）汇总到主批次</p>
                    <p>📌 此操作<b>不可撤销</b>，请确认无误</p>
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-warning" onclick="doMerge()" id="mergeConfirmBtn" style="flex:1;">确认合并</button>
                <button class="btn btn-secondary" onclick="closeMergeModal()" style="flex:1;">取消</button>
            </div>
        </div>
    </div>

    <!-- 退单弹窗 -->
    <div class="modal" id="returnModal">
        <div class="modal-content" style="max-width:380px;">
            <div class="modal-header">
                <h3 class="modal-title">↩ 退单</h3>
                <button class="modal-close" onclick="closeReturnModal()">&times;</button>
            </div>
            <div style="padding:16px 0;">
                <div id="returnProductInfo" style="margin-bottom:16px;">
                    <div style="font-size:16px;font-weight:600;" id="retProductName"></div>
                    <div style="font-size:13px;color:var(--text-secondary);margin-top:4px;">
                        <span id="retConditionName"></span> · 可退还: <span id="retMaxQty">0</span> 件
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">退还数量</label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button class="btn btn-sm" onclick="adjReturnQty(-1)" style="width:36px;height:36px;font-size:20px;">−</button>
                        <input type="number" id="retQty" value="1" min="1" step="1" style="width:80px;text-align:center;font-size:18px;font-weight:bold;padding:8px;border:2px solid var(--border);border-radius:8px;">
                        <button class="btn btn-sm" onclick="adjReturnQty(1)" style="width:36px;height:36px;font-size:20px;">+</button>
                        <button class="btn btn-sm" onclick="document.getElementById('retQty').value = document.getElementById('retMaxQty').textContent" style="font-size:12px;">全部</button>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button class="btn btn-primary" onclick="confirmReturn()" id="returnBtn" style="flex:1;">确认退单</button>
                    <button class="btn btn-secondary" onclick="closeReturnModal()" style="flex:1;">取消</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 财务数据编辑弹窗 -->
    <div class="modal" id="financeModal">
        <div class="modal-content" style="max-width:420px;">
            <div class="modal-header">
                <h3 class="modal-title">补充财务数据</h3>
                <button class="modal-close" onclick="closeFinanceModal()">&times;</button>
            </div>
            <form onsubmit="saveOutboundFinance(event)">
                <input type="hidden" id="financeBatchNo">
                <div class="form-group">
                    <label class="form-label">直播平台</label>
                    <select class="form-input" id="financePlatform">
                        <option value="">-- 选填 --</option>
                        <option value="小红书">小红书</option>
                        <option value="抖音">抖音</option>
                        <option value="视频号">视频号</option>
                        <option value="其他平台">其他平台</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">账号</label>
                    <input type="text" class="form-input" id="financeAccount" placeholder="对应直播账号">
                </div>
                <div class="form-group">
                    <label class="form-label">备注</label>
                    <input type="text" class="form-input" id="financeRemark" placeholder="备注信息">
                </div>
                <div class="form-group">
                    <label class="form-label">GMV (成交金额)</label>
                    <input type="number" class="form-input" id="financeGmv" step="0.01" placeholder="平台实际成交金额(含运费)">
                </div>
                <div class="form-group">
                    <label class="form-label">订单数 / 快递单数</label>
                    <input type="number" class="form-input" id="financeOrderCount" step="1" placeholder="实际发货订单数">
                </div>
                <div class="form-group">
                    <label class="form-label">投流费用</label>
                    <input type="number" class="form-input" id="financeAdSpend" step="0.01" placeholder="本次投放流量费用">
                </div>
                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">保存</button>
                    <button type="button" class="btn btn-secondary" onclick="closeFinanceModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    let cart = [];
    let stockData = [];
    let stockSortAsc = true;
    let scanTimer = null;
    let conditionNameMap = { sealed: '原盒未拆', opened: '拆盒无瑕', boxless: '无盒无瑕', flawed: '微瑕' };

    // ---- 待出库持久化 (localStorage) ----
    const PENDING_CART_KEY = 'ppmart_pending_cart_' + window.location.hostname;

    function savePendingCart() {
        try {
            const data = cart.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                common_name: item.common_name,
                series: item.series,
                condition_type: item.condition_type,
                condition_name: item.condition_name,
                price: item.price,
                qty: item.qty,
                batches: item.batches.map(b => ({
                    batch_id: b.batch_id,
                    batch_no: b.batch_no,
                    purchase_price: b.purchase_price,
                    available: b.available,
                    qty: b.qty
                }))
            }));
            localStorage.setItem(PENDING_CART_KEY, JSON.stringify(data));
        } catch(e) {
            console.warn('待出库保存失败:', e);
        }
    }

    function loadPendingCart() {
        try {
            const raw = localStorage.getItem(PENDING_CART_KEY);
            if (!raw) return;
            const data = JSON.parse(raw);
            if (!Array.isArray(data) || data.length === 0) return;
            const valid = data.filter(item =>
                item.product_id && item.condition_type &&
                item.price > 0 && item.qty > 0 &&
                Array.isArray(item.batches) && item.batches.length > 0
            );
            if (valid.length === 0) return;
            cart = valid;
            renderCart();
            updateStats();
            refreshStockDisplay();
        } catch(e) {
            console.warn('待出库恢复失败:', e);
            localStorage.removeItem(PENDING_CART_KEY);
        }
    }

    function clearPendingCart() {
        localStorage.removeItem(PENDING_CART_KEY);
    }

    // ---- 服务端待出库清单（Agent/语音远程添加的） ----
    async function loadServerPendingCart() {
        try {
            const res = await fetch('../api/get_pending_outbound.php');
            const data = await res.json();
            if (!data.success || !data.data.items || data.data.items.length === 0) return;

            for (const item of data.data.items) {
                // 在 stockData 中找该产品的可用批次
                const batches = stockData.filter(s =>
                    s.product_id == item.product_id && s.condition_type === item.condition_type && s.remaining_qty > 0
                );
                if (batches.length === 0) continue;

                const totalStock = batches.reduce((s, b) => s + parseInt(b.remaining_qty), 0);

                // 如果购物车已有同产品+SKU，累加数量
                const existing = cart.find(c =>
                    c.product_id == item.product_id && c.condition_type === item.condition_type
                );
                if (existing) {
                    existing.qty += parseInt(item.qty);
                    allocateFIFO(existing);
                    // 超量则截断
                    if (existing.qty > totalStock) existing.qty = totalStock;
                    continue;
                }

                const sku = {
                    product_id: item.product_id,
                    product_name: item.product_name || '',
                    common_name: item.common_name || '',
                    series: '',
                    condition_type: item.condition_type,
                    condition_name: getConditionName(item.condition_type),
                    suggested_price: item.price || batches[0].suggested_price,
                    total_stock: totalStock,
                    batches: batches
                };
                upsertCartItem(sku, parseInt(item.qty));
            }

            renderCart();
            updateStats();
            refreshStockDisplay();

            // 注入完成后删掉DB记录，避免刷新又回来
            await fetch('../api/clear_pending_outbound.php', { method: 'POST' }).catch(()=>{});
        } catch(e) {
            console.warn('加载服务端待出库失败:', e);
        }
    }

    // ---- 扫码工作流 ----
    let scanResult = [];    // 当前扫码返回的批次列表
    let selectedIdx = 0;    // 当前选中的条件索引
    let phase = 'condition'; // 'condition' | 'price' 扫码工作流阶段

    // 扫码输入：回车触发查询
    document.getElementById('scanInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const barcode = this.value.trim();
            if (barcode) {
                handleScan(barcode);
                this.value = '';
                var fi = document.getElementById('scanFloatInput');
                if (fi) fi.value = '';
            }
        }
    });

    // 扫码或拼音搜索输入
    document.getElementById('scanInput').addEventListener('input', function(e) {
        clearTimeout(scanTimer);
        const value = this.value.trim();

        if (!value) {
            hideSearchDropdown();
            return;
        }

        if (/^\d+$/.test(value)) {
            // 全数字 → 条码查询
            hideSearchDropdown();
            if (value.length >= 5) {
                scanTimer = setTimeout(() => {
                    handleScan(value);
                    this.value = '';
                    var fi = document.getElementById('scanFloatInput');
                    if (fi) fi.value = '';
                }, 250);
            }
        } else {
            // 含字母 → 拼音搜索
            if (document.getElementById('obResult').style.display === 'block') {
                resetBar();
            }
            scanTimer = setTimeout(() => {
                searchPinyinStock(value);
            }, 300);
        }
    });

    // 全局键盘：扫码后箭头切换条件，回车确认
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideSearchDropdown();
            return;
        }
        if (scanResult.length === 0) return;

        if (phase === 'condition') {
            // 状态选择阶段：上下切换，回车确认进入价格输入
            if (e.key === 'ArrowUp') { e.preventDefault(); cycleCondition(-1); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); cycleCondition(1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                phase = 'price';
                document.getElementById('conditionGroup').classList.remove('active');
                document.getElementById('obPrice').focus();
            }
        } else if (phase === 'price') {
            // 价格输入阶段：上下仍可切换状态，回车添加
            if (e.key === 'ArrowUp') { e.preventDefault(); cycleCondition(-1); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); cycleCondition(1); }
            else if (e.key === 'Enter') { e.preventDefault(); confirmBarAdd(); }
        }
    });

    // 扫码查询 — 同SKU多批次合并
    async function handleScan(barcode) {
        try {
            const res = await fetch(`../api/search_stock.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();
            if (data.success && data.data && data.data.length > 0) {
                scanResult = mergeBatchesBySKU(data.data);
                selectedIdx = 0;
                showBarResult();
                document.getElementById('scanInput').blur();
                if (scanResult.length === 1) {
                    phase = 'price';
                    document.getElementById('obPrice').focus();
                } else {
                    phase = 'condition';
                    document.getElementById('conditionGroup').classList.add('active');
                }
            } else {
                alert('未找到库存或库存为零');
                document.getElementById('scanInput').focus();
            }
        } catch (err) {
            console.error(err);
            alert('查询失败');
            document.getElementById('scanInput').focus();
        }
    }

    function mergeBatchesBySKU(batches) {
        const skuMap = {};
        batches.forEach(b => {
            const key = b.condition_type;
            if (!skuMap[key]) {
                skuMap[key] = {
                    product_id: b.product_id,
                    product_name: b.product_name,
                    common_name: b.common_name,
                    series: b.series,
                    barcode: b.barcode,
                    condition_type: b.condition_type,
                    condition_name: b.condition_name,
                    suggested_price: b.suggested_price,
                    total_stock: 0,
                    batches: []
                };
            }
            skuMap[key].batches.push(b);
            skuMap[key].total_stock += parseInt(b.remaining_qty);
            if (parseFloat(b.suggested_price) > parseFloat(skuMap[key].suggested_price)) {
                skuMap[key].suggested_price = b.suggested_price;
            }
        });
        Object.values(skuMap).forEach(sku => {
            sku.batches.sort((a, b) => (a.purchased_at || '').localeCompare(b.purchased_at || ''));
        });
        return Object.values(skuMap);
    }

    // 在浮层显示扫码结果
    function showBarResult() {
        const stock = scanResult[selectedIdx];
        document.getElementById('obProductName').textContent = stock.common_name || stock.product_name;
        document.getElementById('obCondition').textContent = getConditionName(stock.condition_type);
        document.getElementById('obPrice').value = parseFloat(stock.suggested_price || 0).toFixed(2);
        document.getElementById('obResult').style.display = 'block';
    }

    // 上下切换条件
    function cycleCondition(delta) {
        if (scanResult.length === 0) return;
        selectedIdx = (selectedIdx + delta + scanResult.length) % scanResult.length;
        const stock = scanResult[selectedIdx];
        document.getElementById('obCondition').textContent = getConditionName(stock.condition_type);
        document.getElementById('obPrice').value = parseFloat(stock.suggested_price || 0).toFixed(2);
    }

    // 确认添加（从浮层加入购物车）
    function confirmBarAdd() {
        if (scanResult.length === 0) return;
        const sku = scanResult[selectedIdx];
        const price = parseFloat(document.getElementById('obPrice').value);
        if (!price || price <= 0) {
            document.getElementById('obPrice').focus();
            return;
        }
        if (sku.total_stock < 1) {
            alert('库存不足');
            resetBar();
            return;
        }
        sku.suggested_price = price;
        upsertCartItem(sku, 1);
        resetBar();
        renderCart();
        updateStats();
        refreshStockDisplay();
            refreshSearchDropdown();
        savePendingCart();
    }

    // 重置浮层
    function resetBar() {
        phase = 'condition';
        document.getElementById('conditionGroup').classList.remove('active');
        scanResult = [];
        selectedIdx = 0;
        document.getElementById('obResult').style.display = 'none';
        document.getElementById('scanInput').value = '';
        var fi = document.getElementById('scanFloatInput');
        if (fi) fi.value = '';
        // 浮层可见时聚焦浮层，避免页面滚回顶部
        setTimeout(function() {
            if (scanFloatBar.classList.contains('show')) {
                scanFloatInput.focus({ preventScroll: true });
            } else {
                var input = document.getElementById('scanInput');
                if (input) input.focus({ preventScroll: true });
            }
        }, 50);
    }

    /* ---- 拼音搜索 ---- */
    let obSearchResults = [];

    function searchPinyinStock(keyword) {
        fetch(`../api/search_outbound_stock.php?keyword=${encodeURIComponent(keyword)}`)
            .then(r => r.json())
            .then(data => {
                obSearchResults = data.success && data.data ? data.data : [];
                showSearchDropdown();
            })
            .catch(() => {
                obSearchResults = [];
                showSearchDropdown();
            });
    }

    function showSearchDropdown() {
        const dd = document.getElementById('obSearchDropdown');
        if (!obSearchResults || !obSearchResults.length) {
            dd.innerHTML = '<div class="search-dropdown-empty">未找到匹配商品</div>';
            dd.classList.add('show');
            return;
        }

        // 按商品+SKU合并批次
        const productGroups = {};
        obSearchResults.forEach(b => {
            if (!productGroups[b.product_id]) {
                productGroups[b.product_id] = { product_id: b.product_id, product_name: b.product_name, common_name: b.common_name, series: b.series, barcode: b.barcode, conditions: {} };
            }
            const pg = productGroups[b.product_id];
            const cond = pg.conditions;
            if (!cond[b.condition_type]) {
                cond[b.condition_type] = {
                    product_id: pg.product_id,
                    product_name: pg.product_name,
                    common_name: pg.common_name,
                    series: pg.series,
                    barcode: pg.barcode,
                    condition_type: b.condition_type,
                    condition_name: b.condition_name,
                    total_stock: 0,
                    suggested_price: b.suggested_price,
                    batches: []
                };
            }
            cond[b.condition_type].batches.push(b);
            cond[b.condition_type].total_stock += parseInt(b.remaining_qty);
            if (parseFloat(b.suggested_price) > parseFloat(cond[b.condition_type].suggested_price)) {
                cond[b.condition_type].suggested_price = b.suggested_price;
            }
        });

        dd.innerHTML = '';
        // 按 product_id + condition_type 分配唯一 ID 用于事件绑定
        let addId = 0;
        const addMap = {};

        Object.values(productGroups).forEach(product => {
            const displayName = product.common_name || product.product_name;
            const mergedSKUs = Object.values(product.conditions);
            const section = document.createElement('div');
            section.innerHTML = `
                <div class="search-dropdown-header">
                    <div class="sdi-product-name">${escapeHtml(displayName)}</div>
                    <div class="sdi-product-meta">${escapeHtml(product.barcode)}${product.series ? ' · ' + escapeHtml(product.series) : ''}</div>
                </div>
                ${mergedSKUs.map(sku => {
                    const id = 'add_' + (addId++);
                    addMap[id] = sku;
                    const reserved = getCartReservedBySku(sku.product_id, sku.condition_type);
                    const remain = Math.max(0, sku.total_stock - reserved);
                    const dimmed = remain <= 0;
                    return `
                    <div class="search-dropdown-item" style="${dimmed ? 'opacity:0.5;' : ''}">
                        <span class="condition-badge condition-${sku.condition_type}">${escapeHtml(sku.condition_name)}</span>
                        <span class="sdi-stock">库存 ${remain}${reserved > 0 ? `<span style="color:var(--text-tertiary);font-weight:normal;">(-${reserved})</span>` : ''}</span>
                        <span class="sdi-price" style="color:var(--success);">¥${parseFloat(sku.suggested_price || 0).toFixed(2)}</span>
                        <button class="sdi-add-btn" data-add-id="${id}"${dimmed ? ' disabled' : ''}>${dimmed ? '已占完' : '添加'}</button>
                    </div>
                `;}).join('')}
            `;
            dd.appendChild(section);
        });

        dd.classList.add('show');

        // 浮层可见时，下拉框跟随浮层定位
        if (scanFloatBar.classList.contains('show')) {
            var fr = scanFloatBar.getBoundingClientRect();
            dd.style.position = 'fixed';
            dd.style.top = (fr.bottom + 4) + 'px';
            dd.style.left = fr.left + 'px';
            dd.style.right = 'auto';
            dd.style.width = fr.width + 'px';
        } else {
            dd.style.position = '';
            dd.style.top = '';
            dd.style.left = '';
            dd.style.right = '';
            dd.style.width = '';
        }

        dd.querySelectorAll('.sdi-add-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const sku = addMap[this.dataset.addId];
                if (!sku) return;

                try {
                    const reserved = getCartReservedBySku(sku.product_id, sku.condition_type);
                    const remain = sku.total_stock - reserved;
                    if (remain > 0) {
                        upsertCartItem(sku, 1);
                        renderCart();
                        updateStats();
                        refreshStockDisplay();
                    }
                } catch(ex) {
                    console.error('add err:', ex);
                }

                // 无论如何都更新库存显示（即使上面出错也不阻塞）
                var pn = this.parentNode;
                if (pn) {
                    var ss = pn.querySelector('.sdi-stock');
                    if (ss) {
                        var newRsv = getCartReservedBySku(sku.product_id, sku.condition_type);
                        var newRem = Math.max(0, sku.total_stock - newRsv);
                        ss.innerHTML = '库存 ' + newRem +
                            (newRsv > 0 ? '<span style="color:var(--text-tertiary);font-weight:normal;">(-' + newRsv + ')</span>' : '');
                        if (newRem <= 0) {
                            pn.style.opacity = '0.5';
                            this.disabled = true;
                            this.textContent = '已占完';
                        } else {
                            this.textContent = '✓';
                            this.style.background = '#34d399';
                            setTimeout(() => { this.textContent = '添加'; this.style.background = ''; }, 300);
                        }
                    }
                }
            });
        });
    }

    function hideSearchDropdown() {
        document.getElementById('obSearchDropdown').classList.remove('show');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // 点击外部关闭下拉框
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.scan-bar')) {
            hideSearchDropdown();
        }
    });

    // 获取购物车中已占用的数量（按商品+SKU）
    function getCartReservedBySku(productId, conditionType) {
        let reserved = 0;
        cart.forEach(item => {
            if (item.product_id === productId && item.condition_type === conditionType) {
                reserved += item.qty;
            }
        });
        return reserved;
    }

    function upsertCartItem(sku, qty = 1) {
        const index = cart.findIndex(item =>
            item.product_id === sku.product_id && item.condition_type === sku.condition_type
        );
        if (index >= 0) {
            const item = cart[index];
            const totalStock = item.batches.reduce((s, b) => s + b.available, 0);
            const nextQty = item.qty + qty;
            if (nextQty > totalStock) {
                alert('库存不足');
                item.qty = totalStock;
            } else {
                item.qty = nextQty;
            }
            allocateFIFO(item);
            savePendingCart();
            return;
        }

        const newItem = {
            product_id: sku.product_id,
            product_name: sku.product_name,
            common_name: sku.common_name || '',
            series: sku.series || '',
            condition_type: sku.condition_type,
            condition_name: sku.condition_name,
            price: parseFloat(sku.suggested_price) || 0,
            qty: qty,
            batches: (sku.batches || [sku]).map(b => ({
                batch_id: b.batch_id,
                batch_no: b.batch_no,
                purchase_price: parseFloat(b.purchase_price || 0),
                available: parseInt(b.remaining_qty || 0),
                qty: 0
            }))
        };
        allocateFIFO(newItem);
        cart.push(newItem);
        savePendingCart();
    }

    function allocateFIFO(item) {
        let remaining = item.qty;
        for (const b of item.batches) {
            b.qty = Math.min(b.available, remaining);
            remaining -= b.qty;
            if (remaining <= 0) break;
        }
        if (remaining > 0) {
            item.qty -= remaining;
        }
    }

    function renderCart() {
        const tbody = document.getElementById('outboundItems');
        const emptyCart = document.getElementById('emptyCart');
        const confirmBtn = document.getElementById('confirmBtn');

        if (cart.length === 0) {
            tbody.innerHTML = '';
            emptyCart.style.display = 'block';
            confirmBtn.disabled = true;
            return;
        }

        emptyCart.style.display = 'none';
        confirmBtn.disabled = false;

        tbody.innerHTML = cart.map((item, index) => {
            const totalStock = item.batches.reduce((s, b) => s + b.available, 0);
            const remaining = totalStock - item.qty;
            const usedBatches = item.batches.filter(b => b.qty > 0);
            const isMulti = usedBatches.length > 1;
            const avgCost = item.qty > 0
                ? usedBatches.reduce((s, b) => s + b.purchase_price * b.qty, 0) / item.qty
                : 0;
            return `
            <tr>
                <td>
                    <strong>${item.common_name || item.product_name}</strong>
                    ${item.common_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${item.product_name}</span>` : ''}
                </td>
                <td><span class="condition-badge condition-${item.condition_type}">${item.condition_name}</span></td>
                <td><code style="font-size:11px;">${isMulti ? '多批次(' + usedBatches.length + ')' : (item.batches[0]?.batch_no || '-')}</code></td>
                <td>¥${avgCost.toFixed(2)}</td>
                <td>
                    <input type="number" value="${item.price}" onclick="this.select()"
                           onchange="updatePrice(${index}, this.value)"
                           style="width:80px; padding:6px; text-align:center; border:1px solid var(--border); border-radius:4px; background:var(--bg-body); color:var(--success); font-weight:bold;">
                </td>
                <td>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <button class="btn btn-sm" onclick="changeQty(${index}, -1)">-</button>
                        <span style="min-width:30px; text-align:center; font-weight:bold;">${item.qty}</span>
                        <button class="btn btn-sm" onclick="changeQty(${index}, 1)">+</button>
                    </div>
                    <div style="font-size:11px; color:${remaining <= 2 ? 'var(--danger)' : 'var(--text-tertiary)'}; margin-top:2px;" class="stock-remain" data-index="${index}">
                        剩${remaining}件
                    </div>
                </td>
                <td style="font-weight:bold;">¥${(item.price * item.qty).toFixed(2)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="removeItem(${index})">删除</button>
                </td>
            </tr>
        `;}).join('');
    }

    function updatePrice(index, newPrice) {
        const price = parseFloat(newPrice) || 0;
        if (price > 0) {
            cart[index].price = price;
            updateStats();
            savePendingCart();
        }
    }

    function changeQty(index, delta) {
        const item = cart[index];
        const totalStock = item.batches.reduce((s, b) => s + b.available, 0);
        const newQty = item.qty + delta;
        if (newQty <= 0) {
            removeItem(index);
        } else if (newQty > totalStock) {
            alert('超出库存数量（共' + totalStock + '件）');
        } else {
            item.qty = newQty;
            allocateFIFO(item);
            renderCart();
            updateStats();
            refreshStockDisplay();
            refreshSearchDropdown();
            savePendingCart();
        }
    }

    function refreshSearchDropdown() {
        const dd = document.getElementById('obSearchDropdown');
        // 只在下拉框打开时刷新
        if (dd.classList.contains('show') && obSearchResults.length) {
            showSearchDropdown();
        }
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
        updateStats();
        refreshStockDisplay();
            refreshSearchDropdown();
        savePendingCart();
    }

    function updateStats() {
        const totalTypes = cart.length;
        const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
        const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

        document.getElementById('totalTypes').textContent = totalTypes;
        document.getElementById('totalQty').textContent = totalQty;
        document.getElementById('totalAmount').textContent = '¥' + totalAmount.toFixed(2);
        document.getElementById('confirmCount').textContent = totalTypes;
    }

    async function confirmOutbound() {
        if (cart.length === 0) return;

        const orderNo = document.getElementById('orderNo').value.trim();
        const remark = document.getElementById('outboundRemark').value.trim();
        const platform = document.getElementById('outboundPlatform').value;
        const account = document.getElementById('outboundAccount').value.trim();
        const gmv = parseFloat(document.getElementById('outboundGmv').value) || null;
        const orderCount = parseInt(document.getElementById('outboundOrderCount').value) || null;
        const adSpend = parseFloat(document.getElementById('outboundAdSpend').value) || null;

        const items = [];
        cart.forEach(item => {
            item.batches.filter(b => b.qty > 0).forEach(b => {
                items.push({
                    batch_id: b.batch_id,
                    product_id: item.product_id,
                    condition_type: item.condition_type,
                    qty: b.qty,
                    price: item.price
                });
            });
        });

        try {
            const res = await fetch('../api/outbound_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items, order_no: orderNo || null, remark, platform: platform || null, account: account || null, gmv, order_count: orderCount, ad_spend: adSpend })
            });

            const result = await res.json();

            if (result.success) {
                alert(`出库成功！\n批次号: ${result.data.batch_no}\n共 ${result.data.total_items} 个商品，合计 ¥${result.data.total_amount.toFixed(2)}`);
                cart = [];
                clearPendingCart();
                // 清空服务端待出库
                fetch('../api/clear_pending_outbound.php', { method: 'POST' }).catch(()=>{});
                renderCart();
                updateStats();
                document.getElementById('orderNo').value = '';
                document.getElementById('outboundPlatform').value = '';
                document.getElementById('outboundAccount').value = '';
                document.getElementById('outboundRemark').value = '';
                document.getElementById('outboundGmv').value = '';
                document.getElementById('outboundOrderCount').value = '';
                document.getElementById('outboundAdSpend').value = '';
                loadStockOverview();
            } else {
                alert('出库失败: ' + (result.error || '未知错误'));
            }
        } catch (err) {
            console.error(err);
            alert('出库失败');
        }
    }

    async function loadStockOverview() {
        try {
            const res = await fetch('../api/stock_overview.php');
            const data = await res.json();

            if (data.success) {
                document.getElementById('statTypes').textContent = data.data.types;
                document.getElementById('statTotalQty').textContent = data.data.total_qty;
                document.getElementById('statTotalCost').textContent = '¥' + parseFloat(data.data.total_cost || 0).toFixed(0);
                document.getElementById('statTotalValue').textContent = '¥' + parseFloat(data.data.total_value || 0).toFixed(0);

                stockData = data.data.stock_list || [];

                const seriesSet = new Set();
                stockData.forEach(s => { if (s.series) seriesSet.add(s.series); });

                const seriesSelect = document.getElementById('stockSeriesFilter');
                seriesSelect.innerHTML = '<option value="">全部系列</option>';
                seriesSet.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    seriesSelect.appendChild(opt);
                });

                renderStockList(stockData);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function searchStock() {
        const keyword = document.getElementById('stockSearch').value.toLowerCase();
        const series = document.getElementById('stockSeriesFilter').value;

        const filtered = stockData.filter(s => {
            const matchKeyword = !keyword ||
                (s.product_name && s.product_name.toLowerCase().includes(keyword)) ||
                (s.common_name && s.common_name.toLowerCase().includes(keyword)) ||
                (s.barcode && s.barcode.includes(keyword)) ||
                (s.pinyin_initials && s.pinyin_initials.toLowerCase().includes(keyword));
            const matchSeries = !series || s.series === series;
            return matchKeyword && matchSeries;
        });

        filtered.sort((a, b) => {
            const nameA = (a.common_name || a.product_name || '').toLowerCase();
            const nameB = (b.common_name || b.product_name || '').toLowerCase();
            return stockSortAsc ? nameA.localeCompare(nameB) : nameB.localeCompare(nameA);
        });

        renderStockList(filtered);
    }

    function toggleSort() {
        stockSortAsc = !stockSortAsc;
        document.getElementById('sortIndicator').textContent = stockSortAsc ? ' ▲' : ' ▼';
        searchStock();
    }

    function getCartReserved(batchId) {
        let reserved = 0;
        cart.forEach(item => {
            item.batches.forEach(b => {
                if (b.batch_id == batchId) reserved += b.qty;
            });
        });
        return reserved;
    }

    function renderStockList(stock) {
        const tbody = document.getElementById('stockList');

        if (!stock.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--text-tertiary);padding:40px;">暂无库存数据</td></tr>';
            return;
        }

        tbody.innerHTML = stock.map((s, idx) => {
            const reserved = getCartReserved(s.batch_id);
            const remain = Math.max(0, s.remaining_qty - reserved);
            const dimmed = remain <= 0;
            return `
            <tr style="${dimmed ? 'opacity:0.4;' : ''}">
                <td>
                    <strong>${s.product_name}</strong>
                </td>
                <td><span class="condition-badge condition-${s.condition_type}">${s.condition_name}</span></td>
                <td><code style="font-size:11px;">${s.batch_no}</code></td>
                <td>¥${parseFloat(s.purchase_price).toFixed(2)}</td>
                <td style="font-weight:bold; ${remain <= 2 ? 'color:var(--danger);' : ''}">
                    ${remain}
                    ${reserved > 0 ? `<span style="font-size:11px; color:var(--text-tertiary); font-weight:normal;">(-${reserved})</span>` : ''}
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="quickAdd(${s.batch_id})" ${dimmed ? 'disabled' : ''}>+ 添加</button>
                </td>
            </tr>
        `;}).join('');
    }

    function quickAdd(batchId) {
        const stock = stockData.find(s => s.batch_id == batchId);
        if (!stock) return;
        // 找到该SKU的所有批次
        const allBatches = stockData.filter(s =>
            s.product_id === stock.product_id && s.condition_type === stock.condition_type
        );
        const sku = {
            product_id: stock.product_id,
            product_name: stock.product_name,
            common_name: stock.common_name || '',
            series: stock.series || '',
            condition_type: stock.condition_type,
            condition_name: stock.condition_name,
            suggested_price: stock.suggested_price,
            total_stock: allBatches.reduce((s, b) => s + parseInt(b.remaining_qty), 0),
            batches: allBatches
        };
        upsertCartItem(sku, 1);
        renderCart();
        updateStats();
        refreshStockDisplay();
            refreshSearchDropdown();
    }

    // ---- 批次合并功能 ----
    let mergeSelected = {};
    let mergeBatchMap = {};

    function toggleMergeSelect(batchNo) {
        mergeSelected[batchNo] = !mergeSelected[batchNo];
        updateMergeUI();
    }

    function updateMergeUI() {
        const toolbar = document.getElementById('mergeToolbar');
        const mergeBtn = document.getElementById('mergeBtn');
        const countEl = document.getElementById('mergeSelectedCount');
        const selected = Object.keys(mergeSelected).filter(k => mergeSelected[k]);
        const count = selected.length;

        toolbar.style.display = 'block';
        countEl.textContent = '已选 ' + count + ' 个批次';

        if (count >= 2) {
            mergeBtn.disabled = false;
            mergeBtn.style.opacity = '1';
        } else {
            mergeBtn.disabled = true;
            mergeBtn.style.opacity = '0.5';
        }

        document.querySelectorAll('.batch-checkbox').forEach(cb => {
            const no = cb.dataset.batchno;
            cb.checked = !!mergeSelected[no];
        });
        document.querySelectorAll('.batch-card').forEach(card => {
            const no = card.dataset.batchno;
            card.style.outline = mergeSelected[no] ? '2px solid var(--warning)' : '';
        });
    }

    function clearMergeSelection() {
        mergeSelected = {};
        updateMergeUI();
    }

    function showMergeDialog() {
        const selected = Object.keys(mergeSelected).filter(k => mergeSelected[k]);
        if (selected.length < 2) return;

        const listEl = document.getElementById('mergeBatchList');
        listEl.innerHTML = selected.map((no, idx) => {
            const batch = mergeBatchMap[no];
            if (!batch) return '';
            return `
                <label style="display:flex; align-items:center; gap:10px; padding:10px 14px; border:2px solid ${idx === 0 ? 'var(--warning)' : 'var(--border)'}; border-radius:8px; margin-bottom:8px; cursor:pointer; ${idx === 0 ? 'background:rgba(245,158,11,0.08);' : ''}" onclick="selectMainBatch('${no}')">
                    <input type="radio" name="mainBatch" value="${no}" ${idx === 0 ? 'checked' : ''} style="width:18px;height:18px;accent-color:var(--warning);">
                    <div style="flex:1;">
                        <div style="font-weight:${idx === 0 ? 'bold' : 'normal'}; font-size:14px;">${batch.outbound_at}</div>
                        <div style="font-size:12px; color:var(--text-secondary);">
                            ${batch.total_qty} 件 · 金额 ¥${batch.total_amount.toFixed(2)}${batch.order_no ? ' · 订单: ' + escHtml(batch.order_no) : ''}
                        </div>
                    </div>
                    ${idx === 0 ? '<span style="font-size:11px; background:var(--warning); color:#000; padding:2px 8px; border-radius:4px; font-weight:bold;">主批次</span>' : ''}
                </label>
            `;
        }).join('');

        document.getElementById('mergeModal').classList.add('show');
    }

    function selectMainBatch(no) {
        document.querySelectorAll('#mergeBatchList label').forEach(el => {
            const radio = el.querySelector('input[type=radio]');
            if (radio.value === no) {
                radio.checked = true;
                el.style.borderColor = 'var(--warning)';
                el.style.background = 'rgba(245,158,11,0.08)';
                el.querySelector('div:first-child > div:first-child').style.fontWeight = 'bold';
                if (!el.querySelector('span:last-child') || !el.querySelector('span:last-child').textContent.includes('主批次')) {
                    const badge = document.createElement('span');
                    badge.style.cssText = 'font-size:11px; background:var(--warning); color:#000; padding:2px 8px; border-radius:4px; font-weight:bold;';
                    badge.textContent = '主批次';
                    el.querySelector('div:last-child').appendChild(badge);
                }
            } else {
                el.style.borderColor = 'var(--border)';
                el.style.background = '';
                el.querySelector('div:first-child > div:first-child').style.fontWeight = 'normal';
                const badge = el.querySelector('span:last-child');
                if (badge && badge.textContent.includes('主批次')) badge.remove();
            }
        });
    }

    function closeMergeModal() {
        document.getElementById('mergeModal').classList.remove('show');
    }

    async function doMerge() {
        const selectedRadio = document.querySelector('input[name="mainBatch"]:checked');
        if (!selectedRadio) { alert('请选择主批次'); return; }
        const mainBatchNo = selectedRadio.value;
        const mergedBatchNos = Object.keys(mergeSelected).filter(k => mergeSelected[k] && k !== mainBatchNo);

        if (mergedBatchNos.length === 0) { alert('没有需要合并的批次'); return; }

        if (!confirm(`确定将 ${mergedBatchNos.length} 个批次合并到 [${mainBatchNo}] ？\n\n此操作不可撤销！`)) return;

        const confirmBtn = document.getElementById('mergeConfirmBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = '⏳ 合并中...';

        try {
            const res = await fetch('../api/merge_outbound_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ main_batch_no: mainBatchNo, merged_batch_nos: mergedBatchNos })
            });
            const data = await res.json();
            if (data.success) {
                alert('✅ ' + data.data.message);
                closeMergeModal();
                clearMergeSelection();
                mergeSelected = {};
                document.getElementById('mergeToolbar').style.display = 'none';
                showOutboundList();
                loadStockOverview();
            } else {
                alert('❌ 合并失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            alert('❌ 请求失败: ' + err.message);
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '确认合并';
        }
    }

    async function showOutboundList() {
        const modal = document.getElementById('historyModal');
        const container = document.getElementById('historyList');
        modal.classList.add('show');
        container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:16px;">加载中...</div>';

        // 重置合并状态
        mergeSelected = {};
        mergeBatchMap = {};
        document.getElementById('mergeToolbar').style.display = 'none';

        try {
            const res = await fetch('../api/list_outbound.php');
            const data = await res.json();

            if (data.success) {
                const outboundList = data.data.outbound || [];

                if (!outboundList.length) {
                    container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">暂无出库记录</div>';
                } else {
                    outboundList.forEach(b => { if (b.batch_no) mergeBatchMap[b.batch_no] = b; });

                    container.innerHTML = outboundList.map(batch => {
                        const profit = batch.total_amount - batch.total_cost;
                        const hasFinance = batch.gmv !== null && batch.gmv !== undefined;
                        return `
                        <div class="batch-card" data-batchno="${batch.batch_no}" style="margin-bottom:25px; border:1px solid var(--border); border-radius:12px; overflow:hidden; transition:outline 0.15s;">
                            <div style="background:linear-gradient(135deg, #667eea, #764ba2); color:white; padding:15px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    ${batch.batch_no ? `<input type="checkbox" class="batch-checkbox" data-batchno="${batch.batch_no}" onclick="toggleMergeSelect('${batch.batch_no}'); event.stopPropagation();" style="width:18px;height:18px;accent-color:#fbbf24;cursor:pointer;flex-shrink:0;">` : ''}
                                    <div>
                                        <span style="font-size:18px; font-weight:bold;">${batch.outbound_at}</span>
                                        ${batch.order_no ? `<span style="margin-left:12px; font-size:13px; opacity:0.8;">订单: ${escHtml(batch.order_no)}</span>` : ''}
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <div style="text-align:right; font-size:13px;">
                                        <div>共 ${batch.total_qty} 件 | 成本 ¥${batch.total_cost.toFixed(2)}</div>
                                        <div style="font-weight:bold; ${profit >= 0 ? 'color:#dfffe3;' : 'color:#ffdfe3;'}">
                                            毛利: ${profit >= 0 ? '+' : ''}¥${profit.toFixed(2)}
                                        </div>
                                        ${hasFinance ? `<div style="margin-top:3px; padding-top:3px; border-top:1px solid rgba(255,255,255,0.2);">
                                            GMV: ¥${parseFloat(batch.gmv).toFixed(2)} | 订单: ${batch.order_count || 0} | 投流: ¥${parseFloat(batch.ad_spend || 0).toFixed(2)}
                                        </div>` : ''}
                                    </div>
                                    <button class="btn btn-sm" onclick="editOutboundFinance('${batch.batch_no}', ${batch.gmv || 'null'}, ${batch.order_count || 'null'}, ${batch.ad_spend || 'null'}, '${(batch.platform || '').replace(/'/g, "\\'")}', '${(batch.account || '').replace(/'/g, "\\'")}', '${(batch.remark || '').replace(/'/g, "\\'")}'); event.stopPropagation();" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3);">💰 财务</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteOutbound('${batch.batch_no}')" style="background:rgba(255,255,255,0.2); color:white; border:1px solid rgba(255,255,255,0.3);">🗑️</button>
                                </div>
                            </div>
                            <table style="margin:0;">
                                <thead>
                                    <tr>
                                        <th>商品</th>
                                        <th>SKU</th>
                                        <th>数量</th>
                                        <th>进价</th>
                                        <th>售价</th>
                                        <th>盈利</th>
                                        <th>金额</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${batch.items.map(item => {
                                        const returned = parseInt(item.returned_qty || 0);
                                        const actual = item.qty - returned;
                                        const itemProfit = (item.outbound_price - item.batch_purchase_price) * actual;
                                        const canReturn = actual > 0;
                                        return `
                                        <tr>
                                            <td>
                                                <strong>${escHtml(item.common_name || item.product_name || '-')}</strong>
                                                ${item.common_name && item.product_name ? `<br><span style="font-size:11px;color:var(--text-tertiary);">${escHtml(item.product_name)}</span>` : ''}
                                            </td>
                                            <td><span class="condition-badge condition-${item.condition_type}">${getConditionName(item.condition_type)}</span></td>
                                            <td>
                                                ${actual}
                                                ${returned > 0 ? `<br><span style="font-size:11px;color:var(--text-tertiary);text-decoration:line-through;">${item.qty}</span><span style="color:var(--warning);font-size:11px;"> 退${returned}</span>` : ''}
                                            </td>
                                            <td>¥${parseFloat(item.batch_purchase_price).toFixed(2)}</td>
                                            <td>¥${parseFloat(item.outbound_price).toFixed(2)}</td>
                                            <td style="${itemProfit >= 0 ? 'color:var(--success);' : 'color:var(--danger);'} font-weight:bold;">${itemProfit >= 0 ? '+' : ''}¥${itemProfit.toFixed(2)}</td>
                                            <td style="font-weight:bold;">¥${(parseFloat(item.outbound_price) * actual).toFixed(2)}</td>
                                            <td>
                                                ${canReturn ? `<button class="btn btn-sm" onclick="returnOutboundItem(${item.id}, ${item.batch_id}, '${escHtml(item.common_name || item.product_name || '')}', '${item.condition_type}', ${actual})" style="color:var(--warning); border-color:var(--warning);">↩ 退单</button>` : '<span style="font-size:11px;color:var(--text-tertiary);">已退完</span>'}
                                            </td>
                                        </tr>
                                    `}).join('')}
                                </tbody>
                            </table>
                            ${batch.order_no || batch.remark ? `
                                <div style="background:var(--bg-hover); padding:10px 20px; border-top:1px solid var(--border); font-size:13px; color:var(--text-secondary);">
                                    ${batch.remark ? `<span>备注: ${escHtml(batch.remark)}</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    `}).join('');
                }
            } else {
                container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">加载失败: ' + (data.error || '未知错误') + '</div>';
            }
        } catch (err) {
            console.error(err);
            container.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:60px;font-size:18px;">加载失败: ' + err.message + '</div>';
        }
    }

    async function loadConditionSettings() {
        try {
            const res = await fetch('../api/get_settings.php');
            const data = await res.json();
            if (data.success && data.settings && data.settings.condition_types) {
                conditionNameMap = Object.fromEntries(data.settings.condition_types.map(c => [c.key, c.name]));
            }
        } catch (e) {
            console.log('使用默认状态名称');
        }
    }

    function getConditionName(type) {
        return conditionNameMap[type] || type;
    }

    // ---- 退单功能 ----
    let returnLogId = 0;
    let returnBatchId = 0;
    let returnMaxQty = 0;

    function returnOutboundItem(logId, batchId, productName, conditionType, maxQty) {
        returnLogId = logId;
        returnBatchId = batchId;
        returnMaxQty = maxQty;
        document.getElementById('retProductName').textContent = productName;
        document.getElementById('retConditionName').textContent = getConditionName(conditionType);
        document.getElementById('retMaxQty').textContent = maxQty;
        document.getElementById('retQty').value = Math.min(1, maxQty);
        document.getElementById('returnBtn').disabled = maxQty <= 0;
        document.getElementById('returnModal').classList.add('show');
    }

    function adjReturnQty(delta) {
        const input = document.getElementById('retQty');
        let v = parseInt(input.value) || 1;
        v += delta;
        if (v < 1) v = 1;
        if (v > returnMaxQty) v = returnMaxQty;
        input.value = v;
    }

    function closeReturnModal() {
        document.getElementById('returnModal').classList.remove('show');
    }

    async function confirmReturn() {
        const qty = parseInt(document.getElementById('retQty').value) || 1;
        if (qty <= 0 || qty > returnMaxQty) {
            alert('请输入有效数量（1-' + returnMaxQty + '）');
            return;
        }

        const btn = document.getElementById('returnBtn');
        btn.disabled = true;
        btn.textContent = '⏳ 提交中...';

        try {
            const res = await fetch('../api/return_outbound.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ outbound_log_id: returnLogId, batch_id: returnBatchId, qty })
            });
            const data = await res.json();

            if (data.success) {
                alert('✅ ' + data.data.message);
                closeReturnModal();
                showOutboundList();
                loadStockOverview();
            } else {
                alert('❌ 退单失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            alert('❌ 请求失败: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = '确认退单';
        }
    }

    function closeHistoryModal() {
        document.getElementById('historyModal').classList.remove('show');
    }

    async function deleteOutbound(batchNo) {
        if (!confirm("确定要删除出库批次 " + batchNo + " 吗？\n\n删除后库存将自动恢复，此操作不可撤销！")) {
            return;
        }

        try {
            const res = await fetch('../api/delete_outbound.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ batch_no: batchNo })
            });
            const data = await res.json();

            if (data.success) {
                alert('删除成功！库存已恢复。');
                closeHistoryModal();
                loadStockOverview();
                showOutboundList();
            } else {
                alert('删除失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            console.error(err);
            alert('删除失败');
        }
    }

    (async function init() {
        await loadConditionSettings();
        await loadStockOverview();
        loadPendingCart();
        await loadServerPendingCart();
        document.getElementById('scanInput').focus();
    })();

    // 扫码栏浮层：主扫码栏滚出屏时在顶部显示
    const scanBar = document.querySelector('.scan-bar');
    const scanFloatBar = document.getElementById('scanFloatBar');
    const scanFloatInput = document.getElementById('scanFloatInput');

    // 浮层输入框事件 — 同步到主输入框
    scanFloatInput.addEventListener('input', function(e) {
        const mainInput = document.getElementById('scanInput');
        mainInput.value = e.target.value;
        mainInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
    scanFloatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const mainInput = document.getElementById('scanInput');
            mainInput.value = e.target.value;
            mainInput.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter', bubbles: true }));
            e.target.value = '';
        }
    });

    function checkScanFloatBar() {
        if (!scanBar) return;
        const rect = scanBar.getBoundingClientRect();
        const visible = rect.bottom > 0;
        scanFloatBar.classList.toggle('show', !visible);
        if (!visible && document.activeElement === document.getElementById('scanInput')) {
            scanFloatInput.focus();
        }
    }
    window.addEventListener('scroll', checkScanFloatBar, { passive: true });
    window.addEventListener('resize', checkScanFloatBar);

    function escHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function editOutboundFinance(batchNo, gmv, orderCount, adSpend, platform, account, remark) {
        document.getElementById('financeBatchNo').value = batchNo;
        document.getElementById('financePlatform').value = platform || '';
        document.getElementById('financeAccount').value = account || '';
        document.getElementById('financeRemark').value = remark || '';
        document.getElementById('financeGmv').value = gmv || '';
        document.getElementById('financeOrderCount').value = orderCount || '';
        document.getElementById('financeAdSpend').value = adSpend || '';
        document.getElementById('financeModal').classList.add('show');
    }

    async function saveOutboundFinance(e) {
        e.preventDefault();
        const batchNo = document.getElementById('financeBatchNo').value;
        const platform = document.getElementById('financePlatform').value;
        const account = document.getElementById('financeAccount').value.trim();
        const remark = document.getElementById('financeRemark').value.trim();
        const gmv = parseFloat(document.getElementById('financeGmv').value) || null;
        const orderCount = parseInt(document.getElementById('financeOrderCount').value) || null;
        const adSpend = parseFloat(document.getElementById('financeAdSpend').value) || null;
        try {
            const res = await fetch('../api/save_finance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ outbound_batch_no: batchNo, gmv, order_count: orderCount, ad_spend: adSpend, platform, account, remark })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('financeModal').classList.remove('show');
                showOutboundList();
            } else {
                alert('保存失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            alert('请求失败: ' + err.message);
        }
    }

    function closeFinanceModal() {
        document.getElementById('financeModal').classList.remove('show');
    }

    /* ═══════════════════════════════════
       语音出库 — Web Speech API
       ═══════════════════════════════════ */
    var voiceRecognition = null;
    var voiceListening = false;
    var recognizer = null;

    // 中文数字映射
    var CN_NUM = {
        '零':0,'一':1,'二':2,'两':2,'三':3,'四':4,'五':5,
        '六':6,'七':7,'八':8,'九':9,'十':10,
        '百':100,'千':1000,'万':10000
    };

    function parseCnNumber(str) {
        // 先尝试直接解析数字
        var n = parseInt(str);
        if (!isNaN(n)) return n;
        // 中文数字：十一 = 11, 二十 = 20, 三十四 = 34, 一百 = 100
        var total = 0, cur = 0;
        for (var i = 0; i < str.length; i++) {
            var c = str[i];
            var v = CN_NUM[c];
            if (v === undefined) return NaN;
            if (v >= 10) {
                if (cur === 0) cur = 1;
                total += cur * v;
                cur = 0;
            } else {
                cur = v;
            }
        }
        total += cur;
        return total || NaN;
    }

    function parseVoiceText(text) {
        // 解析语音："商品名 [SKU关键词] 数量个"
        // e.g. "白日梦游 未拆 1个", "梦游 已拆2个"
        // 返回: { keyword, conditionKeyword, qty, price } 或 null
        if (!text || !text.trim()) return null;
        var t = text.trim();

        // 提取价格：末尾的 "数字+块/元"
        var price = null;
        var priceMatch = t.match(/(\d+\.?\d*)\s*(?:块|元|块钱|元钱)\s*$/);
        if (priceMatch) {
            price = parseFloat(priceMatch[1]);
            t = t.slice(0, t.lastIndexOf(priceMatch[0]));
        }

        // 提取数量和单位
        var qty = null;
        var qtyStr = '';
        // 匹配末尾或中间的 "数字+个/包/盒/箱/件..."
        var unitMatch = t.match(/(\d+|[一二两三四五六七八九十百千]+)\s*(?:个|包|盒|箱|件|支|瓶|袋|条|双|对|只|瓶|罐|桶|板|枚|片|张|台|套|副|根|卷|粒|颗|把|块)\s*/);
        if (unitMatch) {
            qty = parseCnNumber(unitMatch[1]);
            qtyStr = unitMatch[0];
            t = t.replace(qtyStr, '');
        }

        // 如果没有单位词，试试末尾的纯数字
        if (!qty) {
            var plainNum = t.match(/(\d+|[一二两三四五六七八九十百千]+)$/);
            if (plainNum) {
                qty = parseCnNumber(plainNum[1]);
                if (qty > 100 || isNaN(qty)) {
                    qty = null;
                } else {
                    t = t.slice(0, t.lastIndexOf(plainNum[1]));
                }
            }
        }

        // 提取价格（第二次尝试）：末尾纯数字（可能之前被当数量但太大被过滤了）
        if (!price) {
            var endNum = t.match(/(\d+\.?\d*)\s*$/);
            if (endNum) {
                var lastNum = parseFloat(endNum[1]);
                if (!isNaN(lastNum) && lastNum > 0 && lastNum < 99999) {
                    price = lastNum;
                    t = t.slice(0, t.lastIndexOf(endNum[1]));
                }
            }
        }

        // 提取 SKU 条件关键词（在剩余文本中匹配已知SKU名）
        var conditionKeyword = '';
        var knownConditions = ['未拆','原盒','已拆','拆盒','无盒','微瑕','瑕'];
        for (var ci = 0; ci < knownConditions.length; ci++) {
            var ck = knownConditions[ci];
            var idx = t.indexOf(ck);
            if (idx >= 0) {
                conditionKeyword = ck;
                t = t.slice(0, idx) + t.slice(idx + ck.length);
                break;
            }
        }

        // 清理剩余文本
        var keyword = t.replace(/[,，、。！？:：（）()\s]+/g, ' ').trim();
        if (!keyword) return null;

        return { keyword: keyword, conditionKeyword: conditionKeyword, qty: qty || 1, price: price };
    }

    function toggleVoiceInput() {
        var btn = document.getElementById('voiceBtn');

        if (voiceListening) {
            stopVoiceInput();
            return;
        }

        // 检查浏览器是否支持
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SR) {
            alert('⚠️ 您的浏览器不支持语音识别。请使用 Chrome 或 Edge。');
            return;
        }

        // 请求麦克风权限
        try {
            recognizer = new SR();
            recognizer.lang = 'zh-CN';
            recognizer.continuous = false;
            recognizer.interimResults = true;
            recognizer.maxAlternatives = 3;

            recognizer.onresult = function(event) {
                var finalText = '';
                var interimText = '';
                for (var i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        finalText += event.results[i][0].transcript;
                    } else {
                        interimText += event.results[i][0].transcript;
                    }
                }

                var display = finalText || interimText;
                document.getElementById('vtText').textContent = display;

                if (finalText) {
                    stopVoiceInput();
                    handleVoiceResult(finalText);
                }
            };

            recognizer.onerror = function(event) {
                console.error('语音识别错误:', event.error);
                stopVoiceInput();
                if (event.error === 'not-allowed') {
                    alert('⚠️ 麦克风权限被拒绝，请在浏览器设置中允许麦克风访问。');
                } else if (event.error === 'no-speech') {
                    // 静默失败
                } else {
                    alert('语音识别失败: ' + event.error);
                }
            };

            recognizer.onend = function() {
                // 如果没有结果（用户没有说话），正常停止
                if (voiceListening) {
                    stopVoiceInput();
                }
            };

            recognizer.start();
            voiceListening = true;
            btn.classList.add('listening');
            btn.title = '点击停止聆听';

            // 显示浮层
            document.getElementById('voiceToast').classList.add('show');
            document.getElementById('vtText').textContent = '正在听...';
            document.getElementById('vtConfirm').textContent = '';

            // 5秒超时
            setTimeout(function() {
                if (voiceListening) {
                    stopVoiceInput();
                }
            }, 5000);

        } catch(e) {
            console.error('语音启动失败:', e);
            alert('启动语音识别失败，请使用 Chrome 浏览器。');
        }
    }

    function stopVoiceInput() {
        voiceListening = false;
        var btn = document.getElementById('voiceBtn');
        btn.classList.remove('listening', 'processing');
        btn.title = '语音录入';

        if (recognizer) {
            try { recognizer.stop(); } catch(e) {}
            recognizer = null;
        }

        // 3秒后隐藏浮层
        setTimeout(function() {
            document.getElementById('voiceToast').classList.remove('show');
        }, 3000);
    }

    function handleVoiceResult(text) {
        var btn = document.getElementById('voiceBtn');
        btn.classList.add('processing');

        var parsed = parseVoiceText(text);
        if (!parsed || !parsed.keyword) {
            document.getElementById('vtConfirm').textContent = '❌ 未能识别商品，请重试';
            return;
        }

        // 显示解析结果
        var confirmMsg = '🔍 "' + parsed.keyword + '" × ' + parsed.qty;
        if (parsed.price) confirmMsg += '  ¥' + parsed.price;
        document.getElementById('vtConfirm').textContent = confirmMsg;

        // 填入搜索框并触发搜索
        var input = document.getElementById('scanInput');
        input.value = parsed.keyword;

        // 触发 input 事件（仅作为备用，优先走本地索引）
        var evt = new Event('input', { bubbles: true });
        input.dispatchEvent(evt);

        // 直接通过本地索引匹配，无需等待API
        voiceDirectAdd(parsed);
    }

    // 使用本地 stockData 索引快速添加，无需 API 调用
    function voiceDirectAdd(parsed) {
        var kw = parsed.keyword.toLowerCase();
        var conditionKw = (parsed.conditionKeyword || '').toLowerCase();
        var qty = parsed.qty || 1;
        var price = parsed.price || null;

        if (!stockData || stockData.length === 0) {
            document.getElementById('vtConfirm').textContent += ' ❌ 库存数据未加载，请刷新页面';
            return;
        }

        // 在 stockData 中查找匹配的商品
        var matches = [];
        var seen = {};
        for (var i = 0; i < stockData.length; i++) {
            var s = stockData[i];
            var name = (s.common_name || s.product_name || '').toLowerCase();
            var series = (s.series || '').toLowerCase();
            if (name.indexOf(kw) >= 0 || series.indexOf(kw) >= 0 || (s.pinyin_initials && s.pinyin_initials.toLowerCase().indexOf(kw) >= 0)) {
                var key = s.product_id + '_' + s.condition_type;
                if (!seen[key]) {
                    seen[key] = true;
                    matches.push(s);
                }
            }
        }

        // 按条件过滤
        if (conditionKw) {
            var filtered = matches.filter(function(s) {
                return s.condition_name.indexOf(conditionKw) >= 0;
            });
            if (filtered.length > 0) matches = filtered;
        }

        if (matches.length === 0) {
            document.getElementById('vtConfirm').textContent += ' ❌ 未找到"' + parsed.keyword + '"';
            return;
        }

        var bestMatch = matches[0];
        // 该SKU的所有可用批次
        var allBatches = stockData.filter(function(s) {
            return s.product_id === bestMatch.product_id && s.condition_type === bestMatch.condition_type;
        });

        var totalStock = 0;
        for (var b = 0; b < allBatches.length; b++) {
            totalStock += parseInt(allBatches[b].remaining_qty || 0);
        }

        var sku = {
            product_id: bestMatch.product_id,
            product_name: bestMatch.product_name,
            common_name: bestMatch.common_name || '',
            series: bestMatch.series || '',
            condition_type: bestMatch.condition_type,
            condition_name: bestMatch.condition_name,
            suggested_price: price || bestMatch.suggested_price,
            total_stock: totalStock,
            batches: allBatches
        };

        var name = bestMatch.common_name || bestMatch.product_name;
        upsertCartItem(sku, qty);
        renderCart();
        updateStats();
        refreshStockDisplay();
        savePendingCart();
        refreshSearchDropdown();

        document.getElementById('vtConfirm').textContent = '✅ ' + name + ' ' + bestMatch.condition_name + ' ×' + qty + ' 已添加';
    }
    </script>
</body>
</html>