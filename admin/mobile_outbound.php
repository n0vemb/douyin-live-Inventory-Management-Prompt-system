<?php
// 手机出库页 - 独立页面，调用相机扫描条形码
$pageTitle = '手机出库';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>手机出库</title>
    <script src="assets/js/html5-qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100dvh; overflow: hidden; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f7; color: #1d1d1f; height: 100dvh; overflow: hidden;
            display: flex; flex-direction: column;
        }

        .header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; padding: 10px 14px; text-align: center;
            font-size: 16px; font-weight: 600; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            position: relative; z-index: 50;
        }
        .header .sub { font-size: 12px; font-weight: 400; opacity: 0.8; }
        .header .toggle-scanner {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: rgba(255,255,255,0.2); border: none; border-radius: 6px;
            color: #fff; padding: 4px 8px; font-size: 11px; font-weight: 500;
            cursor: pointer; transition: 0.15s;
        }
        .header .toggle-scanner:active { background: rgba(255,255,255,0.35); }

        #scanner-section {
            position: relative; width: 100%; background: #000;
            overflow: hidden; flex-shrink: 0;
            max-height: 200px; transition: max-height 0.25s ease;
        }
        #scanner-section.collapsed { max-height: 0; }
        #camera-view {
            width: 100%; aspect-ratio: 16/9; background: #111; position: relative;
            display: flex; align-items: center; justify-content: center; overflow: hidden;
        }
        #camera-view video { width: 100%; height: 100%; object-fit: cover; display: none; }
        #camera-view .placeholder {
            color: #666; font-size: 13px; text-align: center; padding: 12px;
        }
        #camera-view .placeholder .icon { font-size: 32px; display: block; margin-bottom: 6px; }

        .scan-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none; display: flex; align-items: center; justify-content: center;
        }
        .scan-frame {
            width: 55%; max-width: 200px; aspect-ratio: 1;
            border: 2px solid rgba(102, 126, 234, 0.7);
            border-radius: 10px; position: relative;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.4);
        }
        .scan-frame .corner {
            position: absolute; width: 14px; height: 14px;
            border-color: #667eea; border-style: solid;
        }
        .scan-frame .corner.tl { top: -2px; left: -2px; border-width: 3px 0 0 3px; border-radius: 3px 0 0 0; }
        .scan-frame .corner.tr { top: -2px; right: -2px; border-width: 3px 3px 0 0; border-radius: 0 3px 0 0; }
        .scan-frame .corner.bl { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; border-radius: 0 0 0 3px; }
        .scan-frame .corner.br { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; border-radius: 0 0 3px 0; }

        .scanner-hint {
            position: absolute; bottom: 6px; left: 0; right: 0; text-align: center;
            color: rgba(255,255,255,0.5); font-size: 11px; pointer-events: none;
        }

        #scanStatusBar {
            padding: 3px 12px; background: #f0fdf4; border-bottom: 1px solid #e5e5e7;
            font-size: 11px; color: #16a34a; text-align: center; display: none; flex-shrink: 0;
        }

        .manual-bar {
            padding: 6px 12px; background: #fff; border-bottom: 1px solid #e5e5e7; flex-shrink: 0;
            display: flex; flex-wrap: wrap; gap: 6px; align-items: center;
        }
        .manual-bar input {
            flex: 1; min-width: 100px; padding: 7px 10px;
            border: 1.5px solid #d2d2d7; border-radius: 8px;
            font-size: 14px; outline: none; background: #f5f5f7;
        }
        .manual-bar input:focus { border-color: #667eea; background: #fff; }
        .manual-bar .btn-row { display: flex; gap: 5px; }
        .manual-bar button {
            padding: 7px 12px; border: none; border-radius: 7px;
            font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;
        }
        .manual-bar button:active { transform: scale(0.96); opacity: 0.85; }
        .btn-query { background: #667eea; color: #fff; }
        .btn-photo { background: #34d399; color: #fff; }
        .btn-retry { background: #86868b; color: #fff; display: none; }

        #result-section {
            background: #fff; padding: 8px 14px; margin: 0;
            border-bottom: 1px solid #e5e5e7; display: none; flex-shrink: 0;
        }
        #result-section .product-name {
            font-size: 14px; font-weight: 600; margin-bottom: 2px;
        }
        #result-section .product-meta {
            font-size: 11px; color: #86868b; margin-bottom: 5px;
        }
        #result-section .form-row {
            display: flex; gap: 5px; align-items: center; flex-wrap: wrap;
        }
        #result-section .form-row select,
        #result-section .form-row input {
            padding: 7px 8px; border: 1.5px solid #d2d2d7; border-radius: 7px;
            font-size: 13px; background: #f5f5f7; color: #1d1d1f; outline: none;
        }
        #result-section .form-row select { flex: 1; min-width: 70px; }
        #result-section .form-row input[type="number"] {
            width: 60px; text-align: center; font-weight: 600;
        }
        #result-section .form-row input:focus { border-color: #667eea; background: #fff; }
        #result-section .form-row select:focus { border-color: #667eea; }

        .btn-add {
            padding: 7px 16px; border: none; border-radius: 7px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff; font-size: 13px; font-weight: 600; cursor: pointer;
            white-space: nowrap; flex-shrink: 0; transition: 0.15s;
        }
        .btn-add:active { transform: scale(0.96); opacity: 0.85; }
        .btn-add.added { background: #34d399; }

        #cart-section {
            flex: 1; display: flex; flex-direction: column; min-height: 0;
            overflow: hidden;
        }
        .cart-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 14px; background: #fff; border-bottom: 1px solid #e5e5e7;
            flex-shrink: 0;
        }
        .cart-header .title { font-size: 14px; font-weight: 600; }
        .cart-header .count { font-size: 12px; color: #86868b; }

        #cart-items {
            flex: 1; overflow-y: auto; padding: 6px 14px;
            background: #f5f5f7; -webkit-overflow-scrolling: touch;
        }
        .cart-empty {
            text-align: center; padding: 24px 16px; color: #86868b; font-size: 14px;
        }
        .cart-empty .icon { font-size: 36px; display: block; margin-bottom: 8px; }

        .cart-item {
            background: #fff; border-radius: 10px; padding: 9px 10px;
            margin-bottom: 6px; display: flex; align-items: center; gap: 6px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .cart-item .info { flex: 1; min-width: 0; }
        .cart-item .info .name { font-size: 13px; font-weight: 500; }
        .cart-item .info .sub { font-size: 11px; color: #86868b; margin-top: 1px; }
        .cart-item .qty-controls {
            display: flex; align-items: center; gap: 3px; flex-shrink: 0;
        }
        .cart-item .qty-controls button {
            width: 26px; height: 26px; border-radius: 50%; border: 1.5px solid #d2d2d7;
            background: #fff; font-size: 15px; display: flex; align-items: center;
            justify-content: center; cursor: pointer; line-height: 1;
        }
        .cart-item .qty-controls button:active { background: #f5f5f7; }
        .cart-item .qty-controls .qty-num {
            min-width: 20px; text-align: center; font-size: 14px; font-weight: 600;
        }
        .cart-item .price {
            font-size: 13px; font-weight: 600; color: #34d399; min-width: 55px;
            text-align: right;
        }
        .cart-item .btn-del {
            padding: 4px 6px; border: none; background: transparent;
            color: #ff3b30; font-size: 11px; cursor: pointer;
        }

        .cart-footer {
            background: #fff; padding: 8px 14px;
            padding-bottom: calc(8px + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid #e5e5e7; flex-shrink: 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .cart-footer .total-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 6px;
        }
        .cart-footer .total-row .label { font-size: 12px; color: #86868b; }
        .cart-footer .total-row .amount {
            font-size: 18px; font-weight: 700; color: #34d399;
        }
        .cart-footer .btn-confirm {
            width: 100%; padding: 11px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #34d399, #10b981);
            color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;
            transition: 0.15s;
        }
        .cart-footer .btn-confirm:disabled {
            background: #d2d2d7; color: #86868b; cursor: not-allowed;
        }
        .cart-footer .btn-confirm:active:not(:disabled) { transform: scale(0.98); }

        .toast {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8); color: #fff; padding: 12px 18px;
            border-radius: 10px; font-size: 13px; z-index: 999;
            text-align: center; max-width: 75%;
            opacity: 0; transition: opacity 0.2s; pointer-events: none;
        }
        .toast.show { opacity: 1; }

        @media (prefers-color-scheme: dark) {
            body { background: #1c1c1e; color: #f5f5f7; }
            #result-section { background: #2c2c2e; border-color: #38383a; }
            #result-section .form-row select,
            #result-section .form-row input { background: #1c1c1e; border-color: #38383a; color: #f5f5f7; }
            #result-section .form-row input:focus,
            #result-section .form-row select:focus { border-color: #667eea; background: #2c2c2e; }
            .manual-bar { background: #2c2c2e; border-color: #38383a; }
            .manual-bar input { background: #1c1c1e; border-color: #38383a; color: #f5f5f7; }
            .manual-bar input:focus { border-color: #667eea; background: #2c2c2e; }
            #scanStatusBar { background: #1a2e1a; border-color: #38383a; color: #4ade80; }
            .cart-header { background: #2c2c2e; border-color: #38383a; }
            #cart-items { background: #1c1c1e; }
            .cart-item { background: #2c2c2e; }
            .cart-item .qty-controls button { background: #1c1c1e; border-color: #38383a; color: #f5f5f7; }
            .cart-footer { background: #2c2c2e; border-color: #38383a; }
        }

        .hidden { display: none !important; }

        .condition-badge {
            display: inline-block; padding: 1px 6px; border-radius: 3px;
            font-size: 10px; font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="header">
        <span>📦 手机出库 <span class="sub">扫描条形码出库</span></span>
        <button class="toggle-scanner" id="toggleScannerBtn" onclick="toggleScanner()">收起</button>
    </div>

    <!-- 相机扫描区域 -->
    <div id="scanner-section">
        <div id="camera-view">
            <video id="video" playsinline autoplay muted style="width:100%;height:100%;object-fit:cover;"></video>
            <div class="placeholder" id="cameraPlaceholder">
                <span class="icon">📷</span>
                <span id="cameraStatus">正在启动相机...</span>
            </div>
        </div>
        <div class="scan-overlay" id="scanOverlay">
            <div class="scan-frame">
                <div class="corner tl"></div>
                <div class="corner tr"></div>
                <div class="corner bl"></div>
                <div class="corner br"></div>
            </div>
        </div>
        <div class="scanner-hint">将条形码对准框内自动扫描</div>
    </div>

    <!-- 扫描状态条 -->
    <div id="scanStatusBar">
        🔄 自动扫描中...
    </div>

    <!-- 手动输入 -->
    <div class="manual-bar">
        <input type="text" id="manualBarcode" placeholder="输入条形码..." onkeydown="if(event.key==='Enter')manualSearch()">
        <div class="btn-row">
            <button class="btn-query" onclick="manualSearch()">查询</button>
            <button class="btn-photo" id="manualScanBtn" onclick="manualScan()">📷 拍照</button>
            <button class="btn-retry" id="retryCameraBtn" onclick="startScanner()">重试相机</button>
        </div>
    </div>

    <!-- 扫描结果 -->
    <div id="result-section">
        <div class="product-name" id="scanProductName"></div>
        <div class="product-meta">
            <span id="scanBarcode"></span>
            <span id="scanSeries"></span>
        </div>
        <div class="form-row">
            <select id="scanCondition"></select>
            <input type="number" id="scanQty" value="1" min="1" step="1">
            <input type="number" id="scanPrice" step="0.01" placeholder="售价" onfocus="this.select()">
            <button class="btn-add" id="btnAddToCart" onclick="addScanToCart()">+ 添加</button>
        </div>
        <div style="font-size:12px; color:#86868b; margin-top:8px;" id="scanStockInfo"></div>
    </div>

    <!-- 购物车 -->
    <div id="cart-section">
        <div class="cart-header">
            <div class="title">🛒 待出库</div>
            <div class="count" id="cartCount">0 件</div>
        </div>
        <div id="cart-items">
            <div class="cart-empty">
                <span class="icon">📦</span>
                扫描条形码添加出库商品
            </div>
        </div>
        <div class="cart-footer" id="cartFooter">
            <div class="total-row">
                <div class="label">合计</div>
                <div class="amount" id="totalAmount">¥0.00</div>
            </div>
            <button class="btn-confirm" id="confirmBtn" disabled onclick="confirmOutbound()">
                ✅ 确认出库
            </button>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    // ---- State ----
    let scanBatches = [];
    let cart = [];
    let conditionTypeList = [];

    // ---- Scanner ----
    let videoStream = null;
    let scanInterval = null;
    let scanCooldown = false;
    let scanMethod = null; // 'native' | 'library'

    function updateCameraStatus(msg, isError) {
        var el = document.getElementById('cameraStatus');
        if (el) el.textContent = msg;
        if (isError) {
            document.getElementById('scanOverlay').style.display = 'none';
            document.getElementById('retryCameraBtn').style.display = 'block';
        }
    }

    // 检查 BarcodeDetector 是否可用
    async function checkBarcodeDetector() {
        try {
            if (window.BarcodeDetector) {
                var formats = await BarcodeDetector.getSupportedFormats();
                if (formats && formats.length > 0) return true;
            }
        } catch(e) {}
        return false;
    }

    async function startScanner() {
        var retryBtn = document.getElementById('retryCameraBtn');
        retryBtn.style.display = 'none';
        updateCameraStatus('正在启动相机...');

        stopScanner();

        var video = document.getElementById('video');
        video.style.display = 'none';
        document.getElementById('cameraPlaceholder').style.display = 'flex';

        try {
            videoStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: "environment", width: { ideal: 640 }, height: { ideal: 480 } }
            });

            video.srcObject = videoStream;
            await video.play();
            video.style.display = 'block';
            document.getElementById('cameraPlaceholder').style.display = 'none';

            // 检测可用的扫码方式
            var hasNative = await checkBarcodeDetector();
            var hasLibrary = (typeof Html5Qrcode !== 'undefined');

            if (hasNative) {
                scanMethod = 'native';
                document.getElementById('scanOverlay').style.display = 'block';
                updateCameraStatus('✅ 扫描就绪');
                startNativeScan(video);
            } else if (hasLibrary) {
                scanMethod = 'library';
                document.getElementById('scanOverlay').style.display = 'block';
                updateCameraStatus('✅ 扫描就绪');
                startLibraryScan(video);
            } else {
                updateCameraStatus('⚠️ 当前浏览器不支持自动扫码', true);
                document.getElementById('scanOverlay').style.display = 'none';
                showToast('请使用手动输入或点击拍照识别');
            }
        } catch (err) {
            console.error('Camera error:', err);
            var msg = err.message || String(err);
            if (msg.indexOf('NotAllowed') >= 0 || msg.indexOf('Permission') >= 0) {
                msg = '相机权限被拒绝，请在浏览器设置中允许相机访问';
            } else if (msg.indexOf('NotFound') >= 0) {
                msg = '未找到摄像头';
            } else if (msg.indexOf('NotReadable') >= 0) {
                msg = '相机被其他应用占用';
            } else if (msg.indexOf('Overconstrained') >= 0) {
                msg = '不支持的摄像头配置';
            }
            updateCameraStatus('⚠️ ' + msg, true);
        }
    }

    // ---- 方式一：原生 BarcodeDetector（Android Chrome） ----
    function startNativeScan(video) {
        var statusBar = document.getElementById('scanStatusBar');
        statusBar.style.display = 'block';
        statusBar.innerHTML = '🔄 自动扫描中...';

        var detector;
        try {
            detector = new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','code_39','upc_a','upc_e','code_93','itf'] });
        } catch(e) {
            statusBar.innerHTML = '⚠️ 扫码初始化失败';
            return;
        }

        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        var emptyFrames = 0;

        scanInterval = setInterval(async function() {
            if (scanCooldown) return;
            try {
                var vw = video.videoWidth, vh = video.videoHeight;
                if (vw === 0 || vh === 0) return;
                var s = Math.min(1, 480 / Math.max(vw, vh));
                canvas.width = Math.round(vw * s);
                canvas.height = Math.round(vh * s);
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                var codes = await detector.detect(canvas);
                if (codes && codes.length > 0) {
                    emptyFrames = 0;
                    var best = codes.reduce(function(a,b) { return (b.confidence||0)>(a.confidence||0)?b:a; });
                    if (best.rawValue) {
                        statusBar.innerHTML = '✅ 已识别: ' + best.rawValue;
                        scanCooldown = true;
                        setTimeout(function() { scanCooldown = false; }, 2000);
                        handleBarcode(best.rawValue.trim());
                    }
                } else {
                    emptyFrames++;
                    if (emptyFrames > 100) { statusBar.innerHTML = '📷 未检测到，请对准条码'; }
                }
            } catch(e) {
                emptyFrames++;
                if (emptyFrames > 100) { statusBar.innerHTML = '⚠️ 识别异常'; }
            }
        }, 200);
    }

    // ---- 方式二：html5-qrcode 库（iOS/其他） ----
    var libScanner = null;

    function startLibraryScan(video) {
        var statusBar = document.getElementById('scanStatusBar');
        statusBar.style.display = 'block';
        statusBar.innerHTML = '🔄 启动扫码引擎...';

        // 停止摄像头流（html5-qrcode 会自己管理相机）
        if (videoStream) {
            videoStream.getTracks().forEach(function(t) { t.stop(); });
            videoStream = null;
        }
        video.srcObject = null;
        video.style.display = 'none';

        try {
            libScanner = new Html5Qrcode("camera-view");

            libScanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 280, height: 280 } },
                function(text) {
                    if (scanCooldown) return;
                    scanCooldown = true;
                    setTimeout(function() { scanCooldown = false; }, 2000);
                    statusBar.innerHTML = '✅ 已识别: ' + text;
                    handleBarcode(text.trim());
                },
                function() {
                    // 忽略帧错误
                }
            ).then(function() {
                statusBar.innerHTML = '🔄 自动扫描中...';
                document.getElementById('scanOverlay').style.display = 'block';
                document.getElementById('cameraPlaceholder').style.display = 'none';
                updateCameraStatus('✅ 扫描就绪');
            }).catch(function(err) {
                statusBar.innerHTML = '⚠️ 扫码引擎启动失败';
                updateCameraStatus('⚠️ ' + (err.message || '引擎错误'), true);
            });
        } catch(e) {
            statusBar.innerHTML = '⚠️ 扫码库加载失败';
            updateCameraStatus('⚠️ 请使用手动输入', true);
        }
    }

    // ---- 拍照识别（一次性，兼容所有设备） ----
    function manualScan() {
        if (scanCooldown) return;

        // html5-qrcode 自动扫描模式下不需要手动拍照
        if (scanMethod === 'library' && libScanner) {
            showToast('已将条码对准相机，等待自动识别...');
            return;
        }

        var video = document.getElementById('video');
        if (!video || !video.videoWidth) {
            showToast('⚠️ 相机未就绪');
            return;
        }

        scanCooldown = true;
        setTimeout(function() { scanCooldown = false; }, 2000);

        // 先尝试原生 BarcodeDetector
        if (window.BarcodeDetector) {
            try {
                var detector = new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','code_39','upc_a','upc_e'] });
                detector.detect(video).then(function(codes) {
                    if (codes && codes.length > 0) {
                        handleBarcode(codes[0].rawValue.trim());
                    } else {
                        showToast('未识别到条形码，请调整角度');
                        scanCooldown = false;
                    }
                }).catch(function() {
                    showToast('识别失败');
                    scanCooldown = false;
                });
                return;
            } catch(e) {}
        }

        // 兜底：截帧并用 Html5Qrcode 实例的 scanFile 做一次性识别
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        canvas.toBlob(function(blob) {
            try {
                // 临时隐藏容器来避免干扰已有 DOM
                var tempDiv = document.createElement('div');
                tempDiv.style.display = 'none';
                document.body.appendChild(tempDiv);
                var tempScanner = new Html5Qrcode(tempDiv);
                tempScanner.scanFile(new File([blob], 'frame.jpg', { type: 'image/jpeg' }), false)
                    .then(function(text) {
                        tempScanner.clear();
                        document.body.removeChild(tempDiv);
                        handleBarcode(text.trim());
                    })
                    .catch(function() {
                        tempScanner.clear();
                        document.body.removeChild(tempDiv);
                        showToast('未识别到条形码');
                        scanCooldown = false;
                    });
            } catch(e) {
                showToast('当前设备不支持扫码识别');
                scanCooldown = false;
            }
        }, 'image/jpeg', 0.8);
    }

    function stopScanner() {
        if (libScanner) {
            try { libScanner.stop(); } catch(e) {}
            try { libScanner.clear(); } catch(e) {}
            libScanner = null;
        }
        if (scanInterval) { clearInterval(scanInterval); scanInterval = null; }
        if (videoStream) {
            videoStream.getTracks().forEach(function(t) { t.stop(); });
            videoStream = null;
        }
    }

    function manualSearch() {
        var barcode = document.getElementById('manualBarcode').value.trim();
        if (barcode) handleBarcode(barcode);
    }

    // ---- Barcode handling ----
    async function handleBarcode(barcode) {
        if (!barcode) return;

        showToast('扫描到: ' + barcode);

        try {
            const res = await fetch('api/search_stock.php?barcode=' + encodeURIComponent(barcode));
            const data = await res.json();

            if (data.success && data.data && data.data.length > 0) {
                scanBatches = data.data;
                showScanResult(data.data);
            } else {
                showToast('❌ 未找到库存或库存为零');
            }
        } catch (err) {
            showToast('❌ 查询失败');
        }
    }

    function showScanResult(batches) {
        const first = batches[0];
        document.getElementById('scanProductName').textContent = first.common_name || first.product_name;
        document.getElementById('scanBarcode').textContent = first.barcode || '';
        document.getElementById('scanSeries').textContent = first.series ? '· ' + first.series : '';

        // 状态选择
        const condSelect = document.getElementById('scanCondition');
        condSelect.innerHTML = '';
        conditionTypeList = [];
        batches.forEach((b, idx) => {
            if (!conditionTypeList.some(c => c.key === b.condition_type)) {
                conditionTypeList.push({ key: b.condition_type, name: b.condition_name });
            }
        });
        conditionTypeList.forEach(ct => {
            const opt = document.createElement('option');
            opt.value = ct.key;
            opt.textContent = ct.name;
            condSelect.appendChild(opt);
        });

        // 默认取第一个有库存的状态
        updateScanPrice();
        document.getElementById('scanQty').value = 1;

        // 库存信息
        const totalStock = batches.reduce((s, b) => s + b.remaining_qty, 0);
        document.getElementById('scanStockInfo').textContent = '库存总量: ' + totalStock;

        document.getElementById('result-section').style.display = 'block';
        document.getElementById('btnAddToCart').textContent = '+ 添加';
        document.getElementById('btnAddToCart').className = 'btn-add';

        // 自动聚焦价格框
        document.getElementById('scanPrice').focus();
    }

    function updateScanPrice() {
        const key = document.getElementById('scanCondition').value;
        const batch = scanBatches.find(b => b.condition_type === key);
        if (batch) {
            document.getElementById('scanPrice').value = parseFloat(batch.suggested_price || 0).toFixed(2);
            const condSelect = document.getElementById('scanCondition');
            var idx = conditionTypeList.findIndex(function(c) { return c.key === key; });
            if (idx >= 0) condSelect.selectedIndex = idx;
        }
    }

    // 状态切换时更新价格
    document.getElementById('scanCondition').addEventListener('change', updateScanPrice);

    // ---- Add to cart ----
    function addScanToCart() {
        const key = document.getElementById('scanCondition').value;
        const batch = scanBatches.find(b => b.condition_type === key);
        if (!batch) {
            showToast('❌ 未找到该 SKU 的库存');
            return;
        }

        const qty = parseInt(document.getElementById('scanQty').value) || 1;
        const price = parseFloat(document.getElementById('scanPrice').value);
        if (!price || price <= 0) {
            showToast('❌ 请输入有效售价');
            document.getElementById('scanPrice').focus();
            return;
        }

        if (qty > batch.remaining_qty) {
            showToast('❌ 库存不足 (剩余 ' + batch.remaining_qty + ')');
            return;
        }

        upsertCartItem(batch, qty, price);
        renderCart();
        showToast('✅ 已添加');
        collapseScannerAfterAdd();
        document.getElementById('btnAddToCart').textContent = '✓';
        document.getElementById('btnAddToCart').className = 'btn-add added';
        setTimeout(() => {
            document.getElementById('btnAddToCart').textContent = '+ 添加';
            document.getElementById('btnAddToCart').className = 'btn-add';
        }, 600);
    }

    function upsertCartItem(batch, qty, price) {
        const idx = cart.findIndex(item => item.batch_id === batch.batch_id);
        if (idx >= 0) {
            cart[idx].qty = Math.min(cart[idx].qty + qty, cart[idx].stock_qty);
            cart[idx].price = price;
        } else {
            cart.push({
                batch_id: batch.batch_id,
                product_id: batch.product_id,
                product_name: batch.product_name,
                common_name: batch.common_name,
                condition_type: batch.condition_type,
                condition_name: batch.condition_name,
                batch_no: batch.batch_no,
                purchase_price: parseFloat(batch.purchase_price || 0),
                price: price,
                qty: qty,
                stock_qty: batch.remaining_qty
            });
        }
    }

    // ---- Cart rendering ----
    function renderCart() {
        const container = document.getElementById('cart-items');
        const countEl = document.getElementById('cartCount');
        const totalEl = document.getElementById('totalAmount');
        const confirmBtn = document.getElementById('confirmBtn');

        if (cart.length === 0) {
            container.innerHTML = '<div class="cart-empty"><span class="icon">📦</span>扫描条形码添加出库商品</div>';
            countEl.textContent = '0 件';
            totalEl.textContent = '¥0.00';
            confirmBtn.disabled = true;
            return;
        }

        const totalQty = cart.reduce((s, i) => s + i.qty, 0);
        const totalAmount = cart.reduce((s, i) => s + i.price * i.qty, 0);

        countEl.textContent = totalQty + ' 件';
        totalEl.textContent = '¥' + totalAmount.toFixed(2);
        confirmBtn.disabled = false;

        container.innerHTML = cart.map((item, idx) => `
            <div class="cart-item">
                <div class="info">
                    <div class="name">${escapeHtml(item.common_name || item.product_name)}</div>
                    <div class="sub">
                        <span class="condition-badge" style="${getCondStyle(item.condition_type)}">${escapeHtml(item.condition_name)}</span>
                        <span style="margin-left:6px;">¥${item.purchase_price.toFixed(2)}</span>
                    </div>
                </div>
                <div class="qty-controls">
                    <button onclick="changeQty(${idx}, -1)">−</button>
                    <span class="qty-num">${item.qty}</span>
                    <button onclick="changeQty(${idx}, 1)">+</button>
                </div>
                <div class="price">¥${(item.price * item.qty).toFixed(2)}</div>
                <button class="btn-del" onclick="removeItem(${idx})">✕</button>
            </div>
        `).join('');
    }

    function changeQty(idx, delta) {
        const newQty = cart[idx].qty + delta;
        if (newQty <= 0) {
            removeItem(idx);
        } else if (newQty > cart[idx].stock_qty) {
            showToast('超出库存数量');
        } else {
            cart[idx].qty = newQty;
            renderCart();
        }
    }

    function removeItem(idx) {
        cart.splice(idx, 1);
        renderCart();
    }

    // ---- Confirm outbound ----
    async function confirmOutbound() {
        if (cart.length === 0) return;

        const items = cart.map(item => ({
            batch_id: item.batch_id,
            product_id: item.product_id,
            condition_type: item.condition_type,
            qty: item.qty,
            price: item.price
        }));

        const btn = document.getElementById('confirmBtn');
        btn.disabled = true;
        btn.textContent = '⏳ 提交中...';

        try {
            const res = await fetch('api/outbound_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items, order_no: null, remark: '手机出库' })
            });
            const result = await res.json();

            if (result.success) {
                showToast('✅ 出库成功！共 ' + result.data.total_items + ' 件，合计 ¥' + result.data.total_amount.toFixed(2));
                cart = [];
                renderCart();
            } else {
                showToast('❌ ' + (result.error || '出库失败'));
            }
        } catch (err) {
            showToast('❌ 出库失败: ' + err.message);
        } finally {
            btn.textContent = '✅ 确认出库';
            btn.disabled = false;
        }
    }

    // ---- Condition color ----
    let conditionColorMap = {};
    async function loadConditionColors() {
        try {
            const res = await fetch('api/get_settings.php');
            const data = await res.json();
            if (data.success && data.settings && data.settings.condition_types) {
                data.settings.condition_types.forEach(c => { conditionColorMap[c.key] = c.color; });
            }
        } catch(e) {}
    }
    function getCondStyle(key) {
        const color = conditionColorMap[key] || '#667eea';
        const r = parseInt(color.slice(1,3), 16);
        const g = parseInt(color.slice(3,5), 16);
        const b = parseInt(color.slice(5,7), 16);
        return 'background:rgba(' + r + ',' + g + ',' + b + ',0.15);color:' + color;
    }

    // ---- Utils ----
    function showToast(msg) {
        const el = document.getElementById('toast');
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(el._hideTimer);
        el._hideTimer = setTimeout(() => el.classList.remove('show'), 2000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // ---- Scanner toggle ----
    let scannerCollapsed = false;
    function toggleScanner() {
        scannerCollapsed = !scannerCollapsed;
        document.getElementById('scanner-section').classList.toggle('collapsed', scannerCollapsed);
        document.getElementById('toggleScannerBtn').textContent = scannerCollapsed ? '展开' : '收起';
    }
    // 添加到购物车后自动收起扫描区域
    function collapseScannerAfterAdd() {
        if (!scannerCollapsed) {
            scannerCollapsed = true;
            document.getElementById('scanner-section').classList.add('collapsed');
            document.getElementById('toggleScannerBtn').textContent = '展开';
        }
    }

    // ---- Init ----
    // 尝试自动启动相机，同时监听点击（iOS 需要用户手势）
    startScanner();
    document.getElementById('camera-view').addEventListener('click', function() {
        // 如果相机还没启动，点按触发
        if (!videoStream) {
            startScanner();
        }
    }, { once: true });
    loadConditionColors();
    </script>
</body>
</html>
