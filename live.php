<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>直播辅助系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            overflow: hidden;
        }

        #barcodeInput {
            position: absolute;
            left: -9999px;
            width: 1px;
            height: 1px;
        }

        .standby {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .standby-icon {
            font-size: 120px;
            margin-bottom: 30px;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .standby h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .standby p {
            font-size: 24px;
            opacity: 0.9;
        }
        .live-status {
            margin-top: 30px;
            padding: 15px 30px;
            background: rgba(255,255,255,0.2);
            border-radius: 30px;
            font-size: 18px;
        }

        .product-display {
            display: none;
            width: 100vw;
            height: 100vh;
            background: white;
        }
        .product-display.show {
            display: block;
        }

        .broadcaster-view {
            padding: 0;
            height: 100vh;
            position: relative;
            overflow: hidden;
        }
        .product-name {
            position: absolute;
            font-size: 72px;
            font-weight: bold;
            color: #333;
        }
        .product-series {
            position: absolute;
            font-size: 48px;
            color: #667eea;
        }
        .product-common-name {
            font-size: 28px;
            color: #666;
            margin-bottom: 10px;
        }
        .qiandao-price {
            font-size: 48px;
            color: #333;
            margin-top: 20px;
        }
        .qiandao-price span {
            color: #ef4444;
            font-weight: bold;
        }
        .product-description {
            font-size: 32px;
            color: #fff;
            line-height: 1.4;
            padding: 15px;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            border-left: 4px solid #667eea;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-content {
            display: flex;
            gap: 40px;
            flex: 1;
        }
        .product-image {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            border-radius: 20px;
            overflow: hidden;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .product-image.no-image {
            font-size: 150px;
            color: #ddd;
        }

        .product-prices {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .price-row {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 15px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .price-row:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        .price-row.low-stock {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            animation: pulse 1.5s ease-in-out infinite;
        }
        .price-row.out-of-stock {
            background: #9ca3af;
            opacity: 0.6;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .condition-info {
            flex: 1;
        }
        .condition-number {
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .condition-name {
            font-size: 28px;
            opacity: 0.95;
        }
        .price-info {
            text-align: center;
        }
        .suggested-price {
            font-size: 24px;
            opacity: 0.7;
            text-decoration: line-through;
            margin-bottom: 5px;
        }
        .live-price {
            font-size: 52px;
            font-weight: bold;
        }
        .live-price.changed {
            color: #fbbf24;
        }
        .stock-info {
            text-align: center;
            min-width: 120px;
        }
        .stock-number {
            font-size: 60px;
            font-weight: bold;
            line-height: 1;
        }
        .stock-label {
            font-size: 20px;
            opacity: 0.9;
            margin-top: 5px;
        }

        .keyboard-hint {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(0,0,0,0.85);
            color: white;
            padding: 20px 25px;
            border-radius: 12px;
            font-size: 15px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 100;
        }
        .keyboard-hint.show {
            opacity: 1;
        }
        .keyboard-hint div {
            margin: 8px 0;
        }
        .keyboard-hint kbd {
            background: #555;
            padding: 4px 10px;
            border-radius: 5px;
            margin: 0 5px;
            font-family: monospace;
            font-size: 14px;
        }

        .operation-toast {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 20px 30px;
            border-radius: 12px;
            font-size: 24px;
            z-index: 1000;
            display: none;
        }
        .operation-toast.show {
            display: block;
            animation: fadeInOut 1.5s ease-in-out;
        }
        @keyframes fadeInOut {
            0%, 100% { opacity: 0; transform: scale(0.9); }
            15%, 85% { opacity: 1; transform: scale(1); }
        }

        .price-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .price-modal.show {
            display: flex;
        }
        .price-modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            min-width: 450px;
        }
        .price-modal h3 {
            font-size: 32px;
            color: #333;
            margin-bottom: 30px;
        }
        .price-modal input {
            width: 100%;
            padding: 20px;
            font-size: 48px;
            text-align: center;
            border: 2px solid #667eea;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .price-modal-buttons {
            display: flex;
            gap: 20px;
        }
        .price-modal-buttons button {
            flex: 1;
            padding: 18px;
            font-size: 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-confirm {
            background: #10b981;
            color: white;
        }
        .btn-cancel {
            background: #6b7280;
            color: white;
        }

        .voice-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            z-index: 100;
        }
        .voice-toggle.muted {
            opacity: 0.5;
        }

        .broadcast-overlay {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 0, 0, 0.52);
            color: #fff;
            padding: 120px 180px;
            border-radius: 48px;
            font-size: 96px;
            text-align: center;
            z-index: 1000;
            display: none;
            animation: broadcastIn 0.3s ease;
            max-width: 80%;
            word-wrap: break-word;
        }

        .broadcast-overlay.show {
            display: block;
        }

        .broadcast-overlay.fade-out {
            animation: broadcastOut 0.5s ease forwards;
        }

        @keyframes broadcastIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }

        @keyframes broadcastOut {
            from { opacity: 1; transform: translate(-50%, -50%) scale(1); }
            to { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
        }

        .broadcast-icon {
            font-size: 144px;
            margin-bottom: 45px;
        }
    </style>
</head>
<body>
    <input type="text" id="barcodeInput" autocomplete="off">

    <div class="standby" id="standbyScreen">
        <div class="standby-icon">🎪</div>
        <h1>泡泡玛特直播辅助系统</h1>
        <p>请扫描商品条码...</p>
        <div class="live-status" id="liveSessionInfo">准备中</div>
        <div style="margin-top:20px; font-size:14px; opacity:0.7;">按 <kbd style="background:rgba(255,255,255,0.3);padding:5px 10px;border-radius:5px;">F11</kbd> 全屏效果更佳</div>
    </div>

    <div class="product-display" id="productDisplay">
        <div class="broadcaster-view">
            <!-- 商品名称 -->
            <div class="product-name" id="productNameElement">
                <span id="productName"></span>
            </div>
            
            <!-- 商品系列 -->
            <div class="product-series" id="productSeriesElement">
                <span id="productSeries"></span>
            </div>
            
            <!-- 常用名称 -->
            <div class="product-common-name" id="productCommonName" style="display:none;"></div>
            
            <!-- 参考价格 -->
            <div class="qiandao-price" id="suggestedPriceElement">参考价: <span id="qiandaoPrice"></span></div>

            <!-- 产品介绍 -->
            <div class="product-description" id="productDescription" style="display:none;"></div>

            <!-- 商品图片 -->
            <div class="product-image" id="productImageContainer">
                <img id="productImage" src="" alt="" style="display:none;">
                <span id="noImagePlaceholder">📦</span>
            </div>

            <!-- 价格列表 -->
            <div class="product-prices" id="pricesContainer"></div>
        </div>
    </div>

    <div class="operation-toast" id="operationToast"></div>

    <div class="keyboard-hint" id="keyboardHint" style="font-size: 12px; padding: 12px 12px; width: auto; max-width: 500px;">
    <div><kbd>Num 1</kbd>-<kbd>4</kbd> 小键盘减库存</div>
    <div><kbd>Shift</kbd>+<kbd>Num 1</kbd>-<kbd>4</kbd> 加库存</div>
    <div>左键点击减库存|中键点击加库存</div>
    <div><kbd>Q</kbd><kbd>W</kbd><kbd>E</kbd><kbd>R</kbd> 修改对应价格</div>
    <div><kbd>Space</kbd> 关闭</div>
    </div>

    <div class="broadcast-overlay" id="broadcastOverlay">
        <div id="broadcastMessage"></div>
    </div>

    <div class="price-modal" id="priceModal">
        <div class="price-modal-content">
            <h3 id="priceModalTitle">修改价格</h3>
            <input type="number" id="newPriceInput" step="0.01" placeholder="输入新价格">
            <div class="price-modal-buttons">
                <button class="btn-cancel" onclick="closePriceModal()">取消</button>
                <button class="btn-confirm" onclick="confirmPriceChange()">确认</button>
            </div>
        </div>
    </div>

    <div class="voice-toggle muted" id="voiceToggle" onclick="toggleVoice()">
        🔇 语音已关闭
    </div>

    <script>
        console.log('Live page JavaScript loaded');
        let CONDITION_TYPES_CN = ['原盒未拆', '拆盒无瑕', '无盒无瑕', '微瑕'];
        let CONDITION_COLORS = { '原盒未拆': '#10b981', '拆盒无瑕': '#3b82f6', '无盒无瑕': '#f59e0b', '微瑕': '#ef4444' };
        let CONDITION_KEYS = { '1': 0, '2': 1, '3': 2, '4': 3, 'q': 0, 'w': 1, 'e': 2, 'r': 3 };
        let CONDITION_KEYS_EN = { 'sealed': 0, 'opened': 1, 'boxless': 2, 'flawed': 3 };
        let CONDITION_NUMBERS = ['❶', '❷', '❸', '❹'];

        let currentProduct = null;
        let liveSessionId = null;
        let currentPriceChangeCondition = null;
        let voiceEnabled = false;
        let lastScannedBarcode = '';
        let scanDebounceTimer = null;
        let lastBroadcastId = 0;
        let broadcastTimeout = null;
        let systemSettings = {};
        let liveDisplaySettings = {};

        async function loadSettings() {
            try {
                const res = await fetch('api/get_settings.php');
                const data = await res.json();
                console.log('loadSettings - response:', data);
                if (data.success && data.settings) {
                    systemSettings = data.settings;
                    console.log('loadSettings - systemSettings:', systemSettings);
                    if (systemSettings.condition_types) {
                        const conditions = systemSettings.condition_types;
                        CONDITION_TYPES_CN = conditions.map(c => c.name);
                        CONDITION_COLORS = {};
                        CONDITION_KEYS_EN = {};
                        CONDITION_KEYS = {};
                        CONDITION_NUMBERS = ['❶', '❷', '❸', '❹', '❺', '❻', '❼', '❽', '❾', '❿'];
                        
                        conditions.forEach((c, index) => {
                            CONDITION_COLORS[c.name] = c.color;
                            CONDITION_KEYS_EN[c.key] = index;
                            // 数字键映射 (1-9, 0)
                            if (index < 9) {
                                CONDITION_KEYS[(index + 1).toString()] = index;
                            } else {
                                CONDITION_KEYS['0'] = index;
                            }
                            // 字母键映射 (q, w, e, r, t, y, u, i, o, p)
                            const letterKeys = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'];
                            if (index < 10) {
                                CONDITION_KEYS[letterKeys[index]] = index;
                            }
                        });
                    }
                    if (systemSettings.live_display) {
                        // 确保有 productSeries 元素
                        if (systemSettings.live_display.elements) {
                            const hasProductSeries = systemSettings.live_display.elements.some(e => e.type === 'productSeries');
                            if (!hasProductSeries) {
                                const productNameIndex = systemSettings.live_display.elements.findIndex(e => e.type === 'productName');
                                const productName = systemSettings.live_display.elements[productNameIndex];
                                if (productName) {
                                    const productSeries = {
                                        type: 'productSeries',
                                        enabled: true,
                                        left: productName.left,
                                        top: productName.top + productName.height + 10,
                                        width: 600,
                                        height: 60,
                                        fontSize: '48px',
                                        zIndex: 2
                                    };
                                    systemSettings.live_display.elements.splice(productNameIndex + 1, 0, productSeries);
                                }
                            }
                        }
                        
                        liveDisplaySettings = systemSettings.live_display;
                        applyLiveDisplaySettings();
                    }
                    document.querySelector('.standby h1').textContent = systemSettings.system_name || '直播辅助系统';
                }
            } catch (e) {
                console.log('使用默认设置', e);
            }
        }

        function getElementConfig(type) {
            const defaultConfigs = {
                productName: { enabled: true, left: 60, top: 60, width: 900, height: 80, fontSize: '72px', zIndex: 2 },
                productSeries: { enabled: true, left: 60, top: 150, width: 600, height: 60, fontSize: '48px', zIndex: 2 },
                commonName: { enabled: true, left: 60, top: 220, width: 600, height: 80, fontSize: '42px', zIndex: 2 },
                suggestedPrice: { enabled: true, left: 60, top: 310, width: 500, height: 100, fontSize: '72px', zIndex: 2 },
                productDescription: { enabled: true, left: 60, top: 430, width: 800, height: 80, fontSize: '32px', zIndex: 2 },
                image: { enabled: true, left: 60, top: 540, width: 600, height: 600, fontSize: '0px', zIndex: 1 },
                condition: { enabled: true, left: 750, top: 450, width: 1100, height: 600, fontSize: '40px', zIndex: 1, itemSpacing: 30 }
            };

            if (liveDisplaySettings.elements) {
                const found = liveDisplaySettings.elements.find(el => el.type === type);
                if (found) return found;
            }
            return defaultConfigs[type] || { enabled: true, left: 60, top: 60, width: 200, height: 80, fontSize: '36px', zIndex: 1, itemSpacing: 30 };
        }

        function adjustColor(hex, amount) {
            const num = parseInt(hex.replace('#', ''), 16);
            const r = Math.min(255, Math.max(0, (num >> 16) + amount));
            const g = Math.min(255, Math.max(0, ((num >> 8) & 0x00FF) + amount));
            const b = Math.min(255, Math.max(0, (num & 0x0000FF) + amount));
            return '#' + (0x1000000 + (r << 16) + (g << 8) + b).toString(16).slice(1);
        }

        function applyLiveDisplaySettings() {
            const container = document.querySelector('.broadcaster-view');
            if (liveDisplaySettings.containerWidth) {
                container.style.width = liveDisplaySettings.containerWidth;
            }
            if (liveDisplaySettings.containerPadding) {
                container.style.padding = liveDisplaySettings.containerPadding;
            }

            const productName = document.querySelector('.product-name');
            const productCommonName = document.getElementById('productCommonName');
            const qiandaoPrice = document.querySelector('.qiandao-price');

            if (liveDisplaySettings.elements) {
                liveDisplaySettings.elements.forEach(el => {
                    if (el.type === 'productName' && el.enabled) {
                        productName.style.fontSize = el.fontSize || '48px';
                        productName.style.textAlign = el.position || 'left';
                    }
                    if (el.type === 'commonName' && el.enabled) {
                        productCommonName.style.fontSize = el.fontSize || '28px';
                    }
                    if (el.type === 'suggestedPrice' && el.enabled) {
                        qiandaoPrice.style.fontSize = el.fontSize || '48px';
                    }
                });
            }
        }

        function init() {
            loadSettings();

            fetch('api/get_current_session.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        liveSessionId = data.data.id;
                        document.getElementById('liveSessionInfo').textContent =
                            '🔴 ' + data.data.session_name + ' 直播中';
                        setInterval(pollBroadcast, 2000);
                    } else {
                        document.getElementById('liveSessionInfo').textContent = '⚠️ 未开启直播场次';
                    }
                })
                .catch(() => {
                    document.getElementById('liveSessionInfo').textContent = '❌ 连接失败';
                });

            document.getElementById('barcodeInput').focus();

            document.getElementById('barcodeInput').addEventListener('input', handleBarcodeInput);
            document.getElementById('barcodeInput').addEventListener('keypress', handleBarcodeKeypress);
        }

        function handleBarcodeInput(e) {
            clearTimeout(scanDebounceTimer);
            const value = e.target.value;

            scanDebounceTimer = setTimeout(() => {
                if (value.length >= 5) {
                    processBarcode(value);
                    e.target.value = '';
                }
            }, 150);
        }

        function handleBarcodeKeypress(e) {
            if (e.key === 'Enter' && e.target.value.length >= 5) {
                processBarcode(e.target.value);
                e.target.value = '';
            }
        }

        function pollBroadcast() {
            if (!liveSessionId) return;

            fetch(`api/get_broadcast.php?session_id=${liveSessionId}&last_id=${lastBroadcastId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.messages.length > 0) {
                        const msg = data.data.messages[0];
                        lastBroadcastId = msg.id;
                        showBroadcast(msg.message);
                    }
                })
                .catch(() => {});
        }

        function showBroadcast(message) {
            if (broadcastTimeout) {
                clearTimeout(broadcastTimeout);
            }

            const overlay = document.getElementById('broadcastOverlay');
            const msgEl = document.getElementById('broadcastMessage');
            msgEl.textContent = message;
            overlay.classList.remove('fade-out');
            overlay.classList.add('show');

            if ('speechSynthesis' in window && voiceEnabled) {
                speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'zh-CN';
                utterance.rate = 1.0;
                speechSynthesis.speak(utterance);
            }

            broadcastTimeout = setTimeout(() => {
                overlay.classList.add('fade-out');
                setTimeout(() => {
                    overlay.classList.remove('show', 'fade-out');
                }, 500);
            }, 5000);
        }

        function processBarcode(barcode) {
            if (barcode === lastScannedBarcode && currentProduct) {
                return;
            }
            lastScannedBarcode = barcode;
            scanProduct(barcode);
        }

        function scanProduct(barcode) {
            showToast('🔍 查询中...');

            fetch('api/scan_product_live.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    barcode: barcode,
                    live_session_id: liveSessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    currentProduct = data.data;
                    displayProduct();
                    speakProductInfo();
                } else {
                    showToast('❌ ' + (data.error || '商品未找到'));
                    speak('商品未找到');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('❌ 查询失败');
                speak('查询失败');
            });
        }

        function displayProduct() {
            const p = currentProduct;

            const productNameElement = document.getElementById('productNameElement');
            const productNameEl = document.getElementById('productName');
            const productSeriesElement = document.getElementById('productSeriesElement');
            const productSeriesEl = document.getElementById('productSeries');
            const productCommonNameEl = document.getElementById('productCommonName');
            const suggestedPriceElement = document.getElementById('suggestedPriceElement');
            const qiandaoPriceEl = document.getElementById('qiandaoPrice');
            const productImageContainer = document.getElementById('productImageContainer');
            const pricesContainer = document.getElementById('pricesContainer');

            const productNameConfig = getElementConfig('productName');
            const productSeriesConfig = getElementConfig('productSeries');
            const commonNameConfig = getElementConfig('commonName');
            const suggestedPriceConfig = getElementConfig('suggestedPrice');
            const imageConfig = getElementConfig('image');
            const conditionConfig = getElementConfig('condition');

            if (productNameConfig.enabled) {
                productNameEl.textContent = p.name;
                productNameElement.style.position = 'absolute';
                productNameElement.style.left = productNameConfig.left + 'px';
                productNameElement.style.top = productNameConfig.top + 'px';
                productNameElement.style.width = productNameConfig.width + 'px';
                productNameElement.style.minHeight = productNameConfig.height + 'px';
                productNameElement.style.zIndex = productNameConfig.zIndex || 1;
                productNameElement.style.fontSize = productNameConfig.fontSize || '72px';
                productNameElement.style.display = 'block';
            } else {
                productNameElement.style.display = 'none';
            }

            if (productSeriesConfig.enabled && p.series) {
                productSeriesEl.textContent = p.series;
                productSeriesElement.style.position = 'absolute';
                productSeriesElement.style.left = productSeriesConfig.left + 'px';
                productSeriesElement.style.top = productSeriesConfig.top + 'px';
                productSeriesElement.style.width = productSeriesConfig.width + 'px';
                productSeriesElement.style.minHeight = productSeriesConfig.height + 'px';
                productSeriesElement.style.zIndex = productSeriesConfig.zIndex || 1;
                productSeriesElement.style.fontSize = productSeriesConfig.fontSize || '48px';
                productSeriesElement.style.display = 'block';
            } else {
                productSeriesElement.style.display = 'none';
            }

            if (commonNameConfig.enabled && p.common_name) {
                productCommonNameEl.textContent = p.common_name;
                productCommonNameEl.style.position = 'absolute';
                productCommonNameEl.style.left = commonNameConfig.left + 'px';
                productCommonNameEl.style.top = commonNameConfig.top + 'px';
                productCommonNameEl.style.width = commonNameConfig.width + 'px';
                productCommonNameEl.style.minHeight = commonNameConfig.height + 'px';
                productCommonNameEl.style.zIndex = commonNameConfig.zIndex || 1;
                productCommonNameEl.style.fontSize = commonNameConfig.fontSize || '24px';
                productCommonNameEl.style.display = 'flex';
                productCommonNameEl.style.alignItems = 'center';
            } else {
                productCommonNameEl.style.display = 'none';
            }

            if (suggestedPriceConfig.enabled) {
                qiandaoPriceEl.textContent = '¥' + parseFloat(p.qiandao_price || 0).toFixed(2);
                suggestedPriceElement.style.position = 'absolute';
                suggestedPriceElement.style.left = suggestedPriceConfig.left + 'px';
                suggestedPriceElement.style.top = suggestedPriceConfig.top + 'px';
                suggestedPriceElement.style.width = suggestedPriceConfig.width + 'px';
                suggestedPriceElement.style.minHeight = suggestedPriceConfig.height + 'px';
                suggestedPriceElement.style.zIndex = suggestedPriceConfig.zIndex || 1;
                suggestedPriceElement.style.fontSize = suggestedPriceConfig.fontSize || '28px';
                suggestedPriceElement.style.display = 'flex';
                suggestedPriceElement.style.alignItems = 'center';
            } else {
                suggestedPriceElement.style.display = 'none';
            }

            // 产品介绍显示
            const productDescriptionEl = document.getElementById('productDescription');
            const descriptionConfig = getElementConfig('productDescription');
            
            if (descriptionConfig.enabled && p.product_description) {
                productDescriptionEl.textContent = p.product_description;
                productDescriptionEl.style.position = 'absolute';
                productDescriptionEl.style.left = descriptionConfig.left + 'px';
                productDescriptionEl.style.top = descriptionConfig.top + 'px';
                productDescriptionEl.style.width = descriptionConfig.width + 'px';
                productDescriptionEl.style.minHeight = descriptionConfig.height + 'px';
                productDescriptionEl.style.zIndex = descriptionConfig.zIndex || 1;
                productDescriptionEl.style.fontSize = descriptionConfig.fontSize || '32px';
                productDescriptionEl.style.display = 'block';
            } else {
                productDescriptionEl.style.display = 'none';
            }

            if (imageConfig.enabled) {
                productImageContainer.style.position = 'absolute';
                productImageContainer.style.left = imageConfig.left + 'px';
                productImageContainer.style.top = imageConfig.top + 'px';
                productImageContainer.style.width = imageConfig.width + 'px';
                productImageContainer.style.minHeight = imageConfig.height + 'px';
                productImageContainer.style.zIndex = imageConfig.zIndex || 1;
                
                if (p.image_url) {
                    document.getElementById('productImage').src = p.image_url;
                    document.getElementById('productImage').style.display = 'block';
                    document.getElementById('productImage').style.width = '100%';
                    document.getElementById('productImage').style.height = '100%';
                    document.getElementById('productImage').style.objectFit = 'contain';
                    document.getElementById('noImagePlaceholder').style.display = 'none';
                } else {
                    document.getElementById('productImage').style.display = 'none';
                    document.getElementById('noImagePlaceholder').style.display = 'flex';
                }
                productImageContainer.style.display = 'flex';
                productImageContainer.style.alignItems = 'center';
                productImageContainer.style.justifyContent = 'center';
            } else {
                productImageContainer.style.display = 'none';
            }

            pricesContainer.innerHTML = '';
            
            pricesContainer.style.position = 'absolute';
            pricesContainer.style.left = conditionConfig.left + 'px';
            pricesContainer.style.top = conditionConfig.top + 'px';
            pricesContainer.style.width = conditionConfig.width + 'px';
            pricesContainer.style.minHeight = conditionConfig.height + 'px';
            pricesContainer.style.zIndex = conditionConfig.zIndex || 1;
            pricesContainer.style.gap = (conditionConfig.itemSpacing || 30) + 'px';
            pricesContainer.style.display = conditionConfig.enabled ? 'flex' : 'none';

            // 使用系统配置中的状态类型
            const conditionTypes = systemSettings.condition_types || [
                { key: 'sealed', name: '原盒未拆', color: '#10b981' },
                { key: 'opened', name: '拆盒无瑕', color: '#3b82f6' },
                { key: 'boxless', name: '无盒无瑕', color: '#f59e0b' },
                { key: 'flawed', name: '微瑕', color: '#ef4444' }
            ];

            conditionTypes.forEach((condition, index) => {
                const info = p.inventory[condition.name];
                if (!info) return;

                const row = document.createElement('div');
                row.className = 'price-row';
                row.dataset.condition = condition.name;
                row.dataset.index = index;

                if (info.stock <= 0) {
                    row.classList.add('out-of-stock');
                } else if (info.stock <= 2) {
                    row.classList.add('low-stock');
                }

                if (info.stock > 0 && info.stock > 2) {
                    const bgColor = condition.color || '#667eea';
                    row.style.background = `linear-gradient(135deg, ${bgColor} 0%, ${adjustColor(bgColor, -20)} 100%)`;
                }

                const livePrice = info.live_price || info.suggested_price;
                const priceChanged = info.live_price && info.live_price !== info.suggested_price;

                row.innerHTML = `
                    <div class="condition-info">
                        <div class="condition-number">${CONDITION_NUMBERS[index]}</div>
                        <div class="condition-name" style="font-size:${conditionConfig.fontSize || '20px'}">${condition.name}</div>
                    </div>
                    <div class="price-info">
                        ${info.suggested_price && priceChanged ?
                            `<div class="suggested-price">¥${parseFloat(info.suggested_price).toFixed(2)}</div>` : ''}
                        <div class="live-price ${priceChanged ? 'changed' : ''}">
                            ¥${parseFloat(livePrice || 0).toFixed(2)}
                        </div>
                    </div>
                    <div class="stock-info">
                        <div class="stock-number">${info.stock}</div>
                        <div class="stock-label">库存</div>
                    </div>
                `;

                row.addEventListener('click', (e) => {
                    sellItem(condition.name);
                });

                row.addEventListener('mousedown', (e) => {
                    if (e.button === 1) {
                        e.preventDefault();
                        addItem(condition.name);
                    }
                });

                pricesContainer.appendChild(row);
            });

            document.getElementById('standbyScreen').style.display = 'none';
            document.getElementById('productDisplay').classList.add('show');
            document.getElementById('keyboardHint').classList.add('show');

            setTimeout(() => {
                document.getElementById('barcodeInput').focus();
            }, 100);
        }

        document.addEventListener('keydown', function(e) {
            if (!currentProduct) return;
            if (e.target.id === 'newPriceInput') return;

            const isNumpad = e.location === 3;

            if (isNumpad) {
                if (['1', '2', '3', '4'].includes(e.key)) {
                    const num = parseInt(e.key);
                    if (e.shiftKey) {
                        e.preventDefault();
                        addItem(CONDITION_TYPES_CN[num - 1]);
                    } else {
                        e.preventDefault();
                        sellItem(CONDITION_TYPES_CN[num - 1]);
                    }
                    return;
                }
            }

            if (['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                const index = CONDITION_KEYS[e.key.toLowerCase()];
                if (index !== undefined && CONDITION_TYPES_CN[index]) {
                    openPriceModal(CONDITION_TYPES_CN[index]);
                }
            }

            if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                closeProduct();
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                if (document.getElementById('priceModal').classList.contains('show')) {
                    closePriceModal();
                } else {
                    closeProduct();
                }
            }
        });

        function sellItem(condition) {
            const info = currentProduct.inventory[condition];
            if (!info) return;

            if (info.stock <= 0) {
                showToast('❌ 库存不足');
                speak('库存不足');
                return;
            }

            const livePrice = info.live_price || info.suggested_price;

            showToast(`✅ 售出 ${condition} ¥${parseFloat(livePrice).toFixed(2)}`);

            fetch('api/sell_product_live.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: currentProduct.id,
                    condition_type: getConditionKey(condition),
                    sale_price: livePrice,
                    live_session_id: liveSessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    currentProduct.inventory[condition].stock--;
                    displayProduct();
                    speak(`${condition} 已售出，价格${parseInt(livePrice)}元`);
                } else {
                    showToast('❌ ' + (data.error || '操作失败'));
                }
            })
            .catch(err => {
                console.error(err);
                showToast('❌ 操作失败');
            });
        }

        function addItem(condition) {
            const info = currentProduct.inventory[condition];
            if (!info) return;

            fetch('api/return_product_live.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: currentProduct.id,
                    condition_type: getConditionKey(condition),
                    live_session_id: liveSessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    currentProduct.inventory[condition].stock++;
                    displayProduct();
                    showToast(`✅ 退还 ${condition} 库存+1`);
                    speak(`${condition} 库存已加1`);
                } else {
                    showToast('❌ ' + (data.error || '退还失败'));
                    speak(data.error || '退还失败');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('❌ 操作失败');
            });
        }

        function getConditionKey(conditionName) {
            // 从 systemSettings 中获取状态映射
            if (systemSettings && systemSettings.condition_types) {
                const condition = systemSettings.condition_types.find(c => c.name === conditionName);
                if (condition) {
                    return condition.key;
                }
            }
            // 降级到默认映射
            const map = {
                '原盒未拆': 'sealed',
                '拆盒无瑕': 'opened',
                '无盒无瑕': 'boxless',
                '微瑕': 'flawed',
                '未拆袋': 'sealed',
                '已拆无瑕': 'opened'
            };
            return map[conditionName] || conditionName;
        }

        function openPriceModal(condition) {
            currentPriceChangeCondition = condition;
            const info = currentProduct.inventory[condition];
            const currentPrice = info.live_price || info.suggested_price;

            document.getElementById('priceModalTitle').textContent = `修改【${condition}】价格`;
            document.getElementById('newPriceInput').value = currentPrice;
            document.getElementById('priceModal').classList.add('show');

            setTimeout(() => {
                document.getElementById('newPriceInput').focus();
                document.getElementById('newPriceInput').select();
            }, 100);
        }

        document.getElementById('newPriceInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmPriceChange();
            }
        });

        function closePriceModal() {
            document.getElementById('priceModal').classList.remove('show');
            currentPriceChangeCondition = null;
            document.getElementById('barcodeInput').focus();
        }

        function confirmPriceChange() {
            const newPrice = parseFloat(document.getElementById('newPriceInput').value);

            if (!newPrice || newPrice <= 0) {
                alert('请输入有效价格');
                return;
            }

            fetch('api/change_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: currentProduct.id,
                    condition_type: getConditionKey(currentPriceChangeCondition),
                    new_price: newPrice,
                    live_session_id: liveSessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(`✅ ${currentPriceChangeCondition} 改为 ¥${newPrice.toFixed(2)}`);
                    speak(`${currentPriceChangeCondition}价格改为${parseInt(newPrice)}元`);
                    const info = currentProduct.inventory[currentPriceChangeCondition];
                    if (newPrice == info.suggested_price) {
                        info.live_price = null;
                    } else {
                        info.live_price = newPrice;
                    }
                    displayProduct();
                    closePriceModal();
                } else {
                    showToast('❌ 改价失败');
                }
            });
        }

        function closeProduct() {
            document.getElementById('productDisplay').classList.remove('show');
            document.getElementById('standbyScreen').style.display = 'flex';
            document.getElementById('keyboardHint').classList.remove('show');
            currentProduct = null;
            lastScannedBarcode = '';
            document.getElementById('barcodeInput').focus();
        }

        function showToast(message) {
            const toast = document.getElementById('operationToast');
            toast.textContent = message;
            toast.classList.remove('show');
            void toast.offsetWidth;
            toast.classList.add('show');
        }

        function speak(text) {
            if (!voiceEnabled) return;
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'zh-CN';
                utterance.rate = 1.2;
                utterance.pitch = 1;
                speechSynthesis.cancel();
                speechSynthesis.speak(utterance);
            }
        }

        function speakProductInfo() {
            if (!currentProduct || !voiceEnabled) return;

            const p = currentProduct;
            let info = `${p.common_name || p.name}，`;

            let hasStock = false;
            // 使用系统配置中的状态类型
            const conditionTypes = systemSettings.condition_types || [
                { key: 'sealed', name: '原盒未拆' },
                { key: 'opened', name: '拆盒无瑕' },
                { key: 'boxless', name: '无盒无瑕' },
                { key: 'flawed', name: '微瑕' }
            ];
            
            conditionTypes.forEach((condition, index) => {
                const inv = p.inventory[condition.name];
                if (inv && inv.stock > 0) {
                    const price = inv.live_price || inv.suggested_price;
                    info += `${condition}，库存${inv.stock}件，价格${parseInt(price)}元。`;
                    hasStock = true;
                }
            });

            if (!hasStock) {
                info += '所有状态都已售罄。';
            }

            speak(info);
        }

        function toggleVoice() {
            voiceEnabled = !voiceEnabled;
            const toggle = document.getElementById('voiceToggle');
            if (voiceEnabled) {
                toggle.textContent = '🔊 语音播报';
                toggle.classList.remove('muted');
            } else {
                toggle.textContent = '🔇 语音已关闭';
                toggle.classList.add('muted');
            }
        }

        function simpleHash(str) {
            let hash = 0;
            for (let i = 0; i < str.length; i++) {
                const char = str.charCodeAt(i);
                hash = ((hash << 5) - hash) + char;
                hash = hash & hash;
            }
            return hash.toString(36);
        }

        let lastConfigHash = '';
        
        function checkForConfigUpdates() {
            try {
                const tempConfig = localStorage.getItem('ppmart_temp_config');
                if (tempConfig) {
                    const hash = simpleHash(tempConfig);
                    if (hash !== lastConfigHash) {
                        lastConfigHash = hash;
                        const newSettings = JSON.parse(tempConfig);
                        if (newSettings) {
                            if (newSettings.live_display) {
                                liveDisplaySettings = newSettings.live_display;
                            }
                            if (newSettings.condition_types) {
                                systemSettings.condition_types = newSettings.condition_types;
                                CONDITION_TYPES_CN = newSettings.condition_types.map(c => c.name);
                                CONDITION_COLORS = {};
                                CONDITION_KEYS_EN = {};
                                CONDITION_KEYS = {};
                                newSettings.condition_types.forEach((c, index) => {
                                    CONDITION_COLORS[c.name] = c.color;
                                    CONDITION_KEYS_EN[c.key] = index;
                                    if (index < 9) {
                                        CONDITION_KEYS[(index + 1).toString()] = index;
                                    } else {
                                        CONDITION_KEYS['0'] = index;
                                    }
                                    const letterKeys = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'];
                                    if (index < 10) {
                                        CONDITION_KEYS[letterKeys[index]] = index;
                                    }
                                });
                            }
                            if (newSettings.system_name) {
                                systemSettings.system_name = newSettings.system_name;
                                const standbyTitle = document.getElementById('standbyTitle');
                                if (standbyTitle) {
                                    standbyTitle.textContent = newSettings.system_name;
                                }
                            }
                            if (currentProduct) {
                                displayProduct();
                            }
                        }
                    }
                }
            } catch (e) {
                console.error('Check config error:', e);
            }
        }
        
        window.addEventListener('load', init);
        
        setInterval(checkForConfigUpdates, 200);

        setInterval(() => {
            if (document.activeElement.id !== 'newPriceInput') {
                document.getElementById('barcodeInput').focus();
            }
        }, 1000);

        document.addEventListener('click', function(e) {
            if (e.target.closest('.price-modal') || e.target.closest('.voice-toggle')) return;
            if (document.activeElement.id !== 'newPriceInput') {
                document.getElementById('barcodeInput').focus();
            }
        });
    </script>
</body>
</html>