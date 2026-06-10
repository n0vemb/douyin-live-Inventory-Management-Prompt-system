<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>直播辅助系统</title>
    <style>
        :root {
            --bg: #0a0a0f;
            --bg-card: #12121a;
            --bg-elevated: #1a1a26;
            --border: #2a2a3a;
            --text: #e8e8ed;
            --text-secondary: #9d9daf;
            --text-tertiary: #6b6b80;
            --primary: #5e5ce6;
            --primary-hover: #7b79f0;
            --primary-light: rgba(94, 92, 230, 0.12);
            --primary-glow: rgba(94, 92, 230, 0.3);
            --success: #34d399;
            --danger: #f87171;
            --warning: #fbbf24;
            --info: #60a5fa;
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            overflow: hidden;
            color: var(--text);
        }

        .search-bar-container {
            position: fixed;
            bottom: 60px;
            left: 50%;
            transform: translateX(-50%);
            width: 520px;
            max-width: 90vw;
            z-index: 100;
        }
        .search-bar {
            display: flex;
            align-items: center;
            background: rgba(30, 30, 50, 0.92);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 0 16px;
            backdrop-filter: blur(12px);
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .search-bar:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--primary-glow);
        }
        .search-bar .search-icon {
            font-size: 18px;
            margin-right: 10px;
            opacity: 0.5;
            flex-shrink: 0;
        }
        .search-bar input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: var(--text);
            font-size: 18px;
            height: 48px;
            font-family: inherit;
        }
        .search-bar input::placeholder {
            color: var(--text-tertiary);
        }
        .search-mode-badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            background: var(--primary-light);
            color: var(--primary);
            flex-shrink: 0;
            display: none;
        }
        .search-mode-badge.show {
            display: inline;
        }
        .kb-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 10px;
            flex-shrink: 0;
            cursor: pointer;
            transition: all 0.3s;
            user-select: none;
            margin-left: 6px;
            white-space: nowrap;
        }
        .kb-mode-badge.input-mode {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.45);
        }
        .kb-mode-badge.shortcut-mode {
            background: var(--primary);
            color: #fff;
            animation: mode-pulse 1.5s ease-in-out infinite;
        }
        @keyframes mode-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(94,92,230,0.4); }
            50% { box-shadow: 0 0 0 8px rgba(94,92,230,0); }
        }
        .search-results {
            position: absolute;
            bottom: calc(100% + 8px);
            left: 0;
            right: 0;
            background: rgba(26, 26, 38, 0.96);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            display: none;
            max-height: 320px;
            overflow-y: auto;
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .search-results.show {
            display: block;
        }
        .search-results::-webkit-scrollbar {
            width: 4px;
        }
        .search-results::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }
        .search-result-item {
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(42, 42, 58, 0.5);
            transition: background 0.15s;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item:hover,
        .search-result-item.active {
            background: rgba(94, 92, 230, 0.15);
        }
        .search-result-item .result-name {
            font-size: 16px;
            font-weight: 500;
            color: var(--text);
        }
        .search-result-item .result-barcode {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-top: 2px;
        }
        .search-result-item .result-stock {
            margin-left: auto;
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
        }
        .search-result-empty {
            padding: 20px;
            text-align: center;
            color: var(--text-tertiary);
            font-size: 14px;
        }

        .standby {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: radial-gradient(circle at 50% 40%, #1a1a2e 0%, #0a0a0f 100%);
            color: var(--text);
            position: relative;
            overflow: hidden;
        }
        .standby::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            animation: scanLine 3s ease-in-out infinite;
            z-index: 1;
            pointer-events: none;
        }
        @keyframes scanLine {
            0% { top: 0; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 100%; opacity: 0; }
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
            font-size: 42px;
            margin-bottom: 20px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .standby p {
            font-size: 24px;
            color: var(--text-secondary);
        }
        .live-status {
            margin-top: 30px;
            padding: 15px 30px;
            background: rgba(18, 18, 26, 0.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: 30px;
            font-size: 18px;
            color: var(--text);
            animation: livePulse 2s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.25); }
            50% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
        }

        .product-display {
            display: none;
            width: 100vw;
            height: 100vh;
            background: var(--bg);
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
            font-size: 38px;
            font-weight: 800;
            color: var(--text);
            text-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }
        .product-series {
            position: absolute;
            font-size: 48px;
            color: var(--primary);
            font-weight: 600;
        }
        .product-common-name {
            font-size: 28px;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        .qiandao-price {
            font-size: 48px;
            color: var(--text-secondary);
            margin-top: 20px;
        }
        .qiandao-price span {
            color: var(--text);
            font-weight: bold;
        }
        .product-description {
            font-size: 32px;
            color: var(--text);
            line-height: 1.4;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 10px;
            border-left: 4px solid var(--primary);
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
            background: var(--bg-card);
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
            color: var(--text-tertiary);
        }

        .product-prices {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .price-row {
            background: var(--bg-elevated);
            padding: 30px;
            border-radius: 16px;
            color: var(--text);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .price-row::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(94, 92, 230, 0.06) 0%, transparent 50%);
            border-radius: 16px;
            pointer-events: none;
        }
        .price-row:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            border-color: var(--primary);
        }
        .price-row.low-stock {
            background: linear-gradient(135deg, rgba(251, 191, 36, 0.15), rgba(217, 119, 6, 0.1));
            border-color: rgba(251, 191, 36, 0.3);
        }
        .price-row.low-stock::before {
            display: none;
        }
        .price-row.out-of-stock {
            background: rgba(108, 108, 120, 0.12);
            opacity: 0.5;
            border-color: transparent;
        }
        .price-row.out-of-stock::before {
            display: none;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        .condition-info {
            flex: 1;
        }
        .condition-number {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 5px;
        }
        .condition-name {
            font-size: 28px;
            color: var(--text-secondary);
        }
        .price-info {
            text-align: center;
        }
        .suggested-price {
            font-size: 24px;
            color: var(--text-tertiary);
            text-decoration: line-through;
            margin-bottom: 5px;
        }
        .live-price {
            font-size: 46px;
            font-weight: 800;
            color: var(--success);
        }
        .live-price.changed {
            color: var(--warning);
        }
        .stock-info {
            text-align: center;
            min-width: 120px;
        }
        .stock-number {
            font-size: 52px;
            font-weight: 800;
            line-height: 1;
        }
        .stock-label {
            font-size: 20px;
            color: var(--text-tertiary);
            margin-top: 5px;
        }
        .price-adjust {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-left: 12px;
        }
        .btn-adjust {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text);
            font-size: 22px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
            user-select: none;
            line-height: 1;
        }
        .btn-adjust:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }
        .btn-adjust-minus:hover {
            background: rgba(248, 113, 113, 0.2);
            border-color: var(--danger);
            color: var(--danger);
        }
        .btn-adjust-plus:hover {
            background: rgba(52, 211, 153, 0.2);
            border-color: var(--success);
            color: var(--success);
        }

        .keyboard-hint {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-elevated);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid var(--border);
            color: var(--text);
            padding: 10px 20px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
        }
        .keyboard-hint.show {
            opacity: 1;
        }
        .keyboard-hint span {
            color: var(--text-secondary);
            white-space: nowrap;
        }
        .keyboard-hint kbd {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            color: var(--text);
        }

        .operation-toast {
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--bg-elevated);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 1000;
            display: none;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .operation-toast.show {
            display: block;
            opacity: 1;
        }

        .price-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        .price-modal.show {
            display: flex;
        }
        .price-modal-content {
            background: var(--bg-card);
            padding: 40px;
            border-radius: var(--radius-xl);
            text-align: center;
            min-width: 450px;
            border: 1px solid var(--border);
            box-shadow: 0 24px 80px rgba(0,0,0,0.6);
        }
        .price-modal h3 {
            font-size: 32px;
            color: var(--text);
            margin-bottom: 30px;
        }
        .price-modal input {
            width: 100%;
            padding: 20px;
            font-size: 48px;
            text-align: center;
            background: var(--bg);
            border: 2px solid var(--border);
            color: var(--text);
            border-radius: var(--radius-lg);
            margin-bottom: 30px;
            outline: none;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .price-modal input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
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
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        .btn-confirm {
            background: var(--success);
            color: #000;
        }
        .btn-confirm:hover {
            background: #2dd48f;
        }
        .btn-cancel {
            background: var(--bg-elevated);
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-cancel:hover {
            background: var(--border);
        }

        .voice-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            z-index: 100;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .voice-toggle.muted {
            opacity: 0.5;
        }

        .broadcast-overlay {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(239, 68, 68, 0.12);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: var(--text);
            padding: 120px 180px;
            border-radius: 48px;
            font-size: 96px;
            text-align: center;
            z-index: 1000;
            display: none;
            animation: broadcastIn 0.3s ease;
            max-width: 80%;
            word-wrap: break-word;
            border: 1px solid rgba(239, 68, 68, 0.2);
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

        @media (max-width: 768px) {
            .standby h1 {
                font-size: 28px;
            }
            .standby p {
                font-size: 18px;
            }
            .standby-icon {
                font-size: 80px;
            }
            .live-status {
                font-size: 14px;
                padding: 10px 20px;
            }
            .product-name {
                font-size: 32px !important;
            }
            .product-series {
                font-size: 24px !important;
            }
            .product-common-name {
                font-size: 18px !important;
            }
            .product-description {
                font-size: 22px !important;
            }
            .qiandao-price {
                font-size: 28px !important;
            }
            .condition-number {
                font-size: 24px !important;
            }
            .live-price {
                font-size: 32px !important;
            }
            .stock-number {
                font-size: 36px !important;
            }
            .price-row {
                padding: 20px;
                flex-wrap: wrap;
            }
            .price-modal-content {
                min-width: unset;
                width: 90%;
                padding: 24px;
            }
            .price-modal input {
                font-size: 32px;
                padding: 14px;
            }
            .broadcast-overlay {
                padding: 60px 40px;
                font-size: 48px;
                border-radius: 24px;
            }
            .keyboard-hint {
                font-size: 11px;
                padding: 10px;
                max-width: 300px;
            }
            .stock-info {
                min-width: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="search-bar-container">
        <div class="search-bar">
            <span class="search-icon">🔍</span>
            <input type="text" id="barcodeInput" autocomplete="off" placeholder="扫码或输入拼音首字母搜索...">
            <span class="search-mode-badge" id="searchModeBadge">条码</span>
            <span class="kb-mode-badge input-mode" id="kbModeBadge" title="点击切换">🔤 输入</span>
        </div>
        <div class="search-results" id="searchResults"></div>
    </div>

    <div class="standby" id="standbyScreen">
        <div class="standby-icon" id="standbyIcon" style="display:none;">
            <img id="standbyLogo" src="" alt="" style="max-width:100px; max-height:100px; border-radius:16px;">
        </div>
        <h1 id="standbyTitle">直播辅助系统</h1>
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

            <!-- 进货价 -->
            <div class="qiandao-price" id="purchasePriceElement">进价: <span id="purchasePrice"></span></div>

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

    <div class="keyboard-hint" id="keyboardHint">
        <span><kbd>Num 1</kbd>-<kbd>4</kbd> 减</span>
        <span><kbd>Shift</kbd>+<kbd>Num</kbd> 加</span>
        <span>左键减 · 中键加</span>
        <span><kbd>Q</kbd><kbd>W</kbd><kbd>E</kbd><kbd>R</kbd> 改价</span>
        <span><kbd>Space</kbd> 关闭</span>
        <span><kbd>ESC</kbd> 切换模式</span>
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
        let searchDebounceTimer = null;
        let searchResults = [];
        let searchSelectedIndex = -1;
        let isPinyinSearch = false;
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
                    // 显示系统 logo
                    const standbyIcon = document.getElementById('standbyIcon');
                    const standbyLogo = document.getElementById('standbyLogo');
                    if (systemSettings.logo_path) {
                        standbyLogo.src = systemSettings.logo_path;
                        standbyIcon.style.display = 'flex';
                    } else {
                        standbyIcon.style.display = 'none';
                    }

                    // 如果已有商品在显示，用新的配置重新渲染
                    if (currentProduct) {
                        displayProduct();
                    }
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
                suggestedPrice: { enabled: true, left: 60, top: 310, width: 500, height: 100, fontSize: '72px', zIndex: 2, color: '#e8e8ed' },
                purchasePrice: { enabled: true, left: 60, top: 420, width: 500, height: 60, fontSize: '28px', zIndex: 2, color: '#9d9daf' },
                productDescription: { enabled: true, left: 60, top: 430, width: 800, height: 80, fontSize: '32px', zIndex: 2 },
                image: { enabled: true, left: 60, top: 540, width: 600, height: 600, fontSize: '0px', zIndex: 1 },
                condition: { enabled: true, left: 750, top: 450, width: 1100, height: 600, fontSize: '40px', zIndex: 1, itemSpacing: 30, statusFontSize: '28px', statusColor: '#9d9daf', priceFontSize: '46px', priceColor: '#34d399', priceOffsetX: 0, stockOffsetX: 0 }
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
            const qiandaoPrice = document.getElementById('qiandaoPrice');

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
                        qiandaoPrice.style.color = el.color || '#e8e8ed';
                    }
                });
            }
        }

        async function init() {
            await loadSettings();

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

            if (!currentProduct) document.getElementById('barcodeInput').focus();

            document.getElementById('barcodeInput').addEventListener('input', handleInputChange);
            document.getElementById('barcodeInput').addEventListener('keydown', handleSearchKeydown);
            document.getElementById('barcodeInput').addEventListener('click', function() {
                if (keyboardMode === 'shortcut') {
                    setKeyboardMode('input');
                    return;
                }
                if (currentProduct && !isPinyinSearch) {
                    this.select();
                }
            });
        }

        let keyboardMode = 'input';
        let shortcutModeTimer = null;

        function setKeyboardMode(mode) {
            keyboardMode = mode;
            const badge = document.getElementById('kbModeBadge');
            const input = document.getElementById('barcodeInput');
            clearTimeout(shortcutModeTimer);

            if (mode === 'shortcut') {
                badge.className = 'kb-mode-badge shortcut-mode';
                badge.innerHTML = '⌨️ 快捷键';
                input.blur();
                shortcutModeTimer = setTimeout(() => setKeyboardMode('input'), 10000);
            } else {
                badge.className = 'kb-mode-badge input-mode';
                badge.innerHTML = '🔤 输入';
                input.focus();
            }
        }

        // 点击模式标签切换
        document.addEventListener('click', function(e) {
            if (e.target.closest('#kbModeBadge')) {
                setKeyboardMode(keyboardMode === 'input' ? 'shortcut' : 'input');
            }
        });

        function handleInputChange(e) {
            clearTimeout(scanDebounceTimer);
            clearTimeout(searchDebounceTimer);
            const value = e.target.value;

            if (!value) {
                isPinyinSearch = false;
                document.getElementById('searchModeBadge').classList.remove('show');
                hideSearchResults();
                return;
            }

            if (/^\d+$/.test(value)) {
                // Barcode mode — 纯数字，走扫码流程
                isPinyinSearch = false;
                document.getElementById('searchModeBadge').textContent = '条码';
                document.getElementById('searchModeBadge').classList.add('show');
                hideSearchResults();

                scanDebounceTimer = setTimeout(() => {
                    if (value.length >= 5) {
                        processBarcode(value);
                        e.target.value = '';
                        document.getElementById('searchModeBadge').classList.remove('show');
                    }
                }, 150);
            } else if (/[a-zA-Z]/.test(value)) {
                // Pinyin search mode — 包含字母，走拼音搜索
                isPinyinSearch = true;
                document.getElementById('searchModeBadge').textContent = '拼音';
                document.getElementById('searchModeBadge').classList.add('show');

                const keyword = value.toLowerCase().trim();
                searchDebounceTimer = setTimeout(() => {
                    searchByPinyin(keyword);
                }, 200);
            } else {
                // 其他字符（中文等），不处理
                isPinyinSearch = false;
                document.getElementById('searchModeBadge').classList.remove('show');
                hideSearchResults();
            }
        }

        function handleSearchKeydown(e) {
            const value = e.target.value;

            if (e.key === 'Enter') {
                if (isPinyinSearch) {
                    e.preventDefault();
                    if (searchResults.length > 0) {
                        const idx = searchSelectedIndex >= 0 ? searchSelectedIndex : 0;
                        selectSearchResult(idx);
                    }
                } else if (/^\d+$/.test(value) && value.length >= 5) {
                    e.preventDefault();
                    processBarcode(value);
                    e.target.value = '';
                    document.getElementById('searchModeBadge').classList.remove('show');
                }
                return;
            }

            if (isPinyinSearch && searchResults.length > 0) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    searchSelectedIndex = Math.min(searchSelectedIndex + 1, searchResults.length - 1);
                    highlightSearchItem();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    searchSelectedIndex = Math.max(searchSelectedIndex - 1, -1);
                    highlightSearchItem();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    hideSearchResults();
                    // ESC handled by global keydown (mode toggle)
                    // stopPropagation prevents double-firing
                }
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
            showToast('🟢查询中');

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

        /* ---- 拼音搜索 ---- */
        function searchByPinyin(keyword) {
            if (!liveSessionId) return;

            fetch('api/search_product_by_pinyin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    keyword: keyword,
                    live_session_id: liveSessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data) {
                    searchResults = data.data;
                    showSearchResults();
                } else {
                    searchResults = [];
                    showSearchResults();
                }
            })
            .catch(() => {
                searchResults = [];
                showSearchResults();
            });
        }

        function showSearchResults() {
            const container = document.getElementById('searchResults');
            searchSelectedIndex = -1;

            if (!searchResults || searchResults.length === 0) {
                container.innerHTML = '<div class="search-result-empty">未找到匹配商品</div>';
                container.classList.add('show');
                return;
            }

            container.innerHTML = '';
            searchResults.forEach((product, index) => {
                // 计算总库存
                let totalStock = 0;
                if (product.inventory) {
                    Object.values(product.inventory).forEach(info => {
                        totalStock += parseInt(info.stock || 0);
                    });
                }

                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.dataset.index = index;
                item.innerHTML = `
                    <div>
                        <div class="result-name">${escapeHtml(product.name)}</div>
                        <div class="result-barcode">${escapeHtml(product.barcode)} ${product.series ? '· ' + escapeHtml(product.series) : ''}</div>
                    </div>
                    <div class="result-stock">库存 ${totalStock}</div>
                `;
                item.addEventListener('click', () => selectSearchResult(index));
                item.addEventListener('mousemove', () => {
                    searchSelectedIndex = index;
                    highlightSearchItem();
                });
                container.appendChild(item);
            });

            container.classList.add('show');
        }

        function hideSearchResults() {
            document.getElementById('searchResults').classList.remove('show');
            searchResults = [];
            searchSelectedIndex = -1;
        }

        function highlightSearchItem() {
            const container = document.getElementById('searchResults');
            const items = container.querySelectorAll('.search-result-item');
            items.forEach((item, index) => {
                item.classList.toggle('active', index === searchSelectedIndex);
                if (index === searchSelectedIndex) {
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function selectSearchResult(index) {
            const product = searchResults[index];
            if (!product) return;

            hideSearchResults();
            document.getElementById('searchModeBadge').classList.remove('show');
            document.getElementById('barcodeInput').value = '';

            // 通过扫码接口加载完整商品数据（复用现有流程）
            processBarcode(product.barcode);
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        /* ---- 拼音搜索结束 ---- */

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
                qiandaoPriceEl.style.color = suggestedPriceConfig.color || '#e8e8ed';
                suggestedPriceElement.style.display = 'flex';
                suggestedPriceElement.style.alignItems = 'center';
            } else {
                suggestedPriceElement.style.display = 'none';
            }

            // 进货价显示
            const purchasePriceElement = document.getElementById('purchasePriceElement');
            const purchasePriceEl = document.getElementById('purchasePrice');
            const purchasePriceConfig = getElementConfig('purchasePrice');

            if (purchasePriceConfig.enabled && p.purchase_prices) {
                purchasePriceEl.textContent = '¥' + String(p.purchase_prices).split('/').map(p => parseFloat(p).toFixed(2)).join('/¥');
                purchasePriceElement.style.position = 'absolute';
                purchasePriceElement.style.left = purchasePriceConfig.left + 'px';
                purchasePriceElement.style.top = purchasePriceConfig.top + 'px';
                purchasePriceElement.style.width = purchasePriceConfig.width + 'px';
                purchasePriceElement.style.minHeight = purchasePriceConfig.height + 'px';
                purchasePriceElement.style.zIndex = purchasePriceConfig.zIndex || 1;
                purchasePriceElement.style.fontSize = purchasePriceConfig.fontSize || '28px';
                purchasePriceEl.style.color = purchasePriceConfig.color || '#9d9daf';
                purchasePriceElement.style.display = 'flex';
                purchasePriceElement.style.alignItems = 'center';
            } else {
                purchasePriceElement.style.display = 'none';
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

                const livePrice = info.live_price || info.suggested_price;
                const priceChanged = info.live_price && info.live_price !== info.suggested_price;

                if (info.stock > 0 && priceChanged) {
                    row.style.background = 'linear-gradient(135deg, rgba(251, 191, 36, 0.12), rgba(217, 119, 6, 0.08))';
                    row.style.borderColor = 'rgba(251, 191, 36, 0.3)';
                }

                row.innerHTML = `
                    <div class="condition-info">
                        <div class="condition-number">${CONDITION_NUMBERS[index]}</div>
                        <div class="condition-name" style="font-size:${conditionConfig.statusFontSize || '28px'};color:${conditionConfig.statusColor || '#9d9daf'}">${condition.name}</div>
                    </div>
                    <div class="price-info" style="transform:translateX(${conditionConfig.priceOffsetX || 0}px)">
                        ${info.suggested_price && priceChanged ?
                            `<div class="suggested-price">¥${parseFloat(info.suggested_price).toFixed(2)}</div>` : ''}
                        <div class="live-price ${priceChanged ? 'changed' : ''}" style="font-size:${conditionConfig.priceFontSize || '46px'};color:${priceChanged ? '#fbbf24' : (conditionConfig.priceColor || '#34d399')}">
                            ¥${parseFloat(livePrice || 0).toFixed(2)}
                        </div>
                    </div>
                    <div class="stock-info" style="transform:translateX(${conditionConfig.stockOffsetX || 0}px)">
                        <div class="stock-number">${info.stock}</div>
                        <div class="stock-label">库存</div>
                    </div>
                    <div class="price-adjust">
                        <button class="btn-adjust btn-adjust-minus" onclick="event.stopPropagation();adjustPrice('${condition.name}',-1)" title="减1元">−</button>
                        <button class="btn-adjust btn-adjust-plus" onclick="event.stopPropagation();adjustPrice('${condition.name}',1)" title="加1元">+</button>
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

            // 保持当前键盘模式（input = 继续聚焦, shortcut = 已失焦）
        }

        document.addEventListener('keydown', function(e) {
            if (!currentProduct) return;

            // newPriceInput: only handle ESC to close modal, skip everything else
            if (e.target.id === 'newPriceInput') {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closePriceModal();
                }
                return;
            }

            // Space: always close product regardless of mode
            if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                closeProduct();
                return;
            }

            // ESC: close price modal → hide search → toggle mode
            if (e.key === 'Escape') {
                e.preventDefault();
                if (document.getElementById('priceModal').classList.contains('show')) {
                    closePriceModal();
                    return;
                }
                if (document.getElementById('searchResults').classList.contains('show')) {
                    hideSearchResults();
                    return;
                }
                // Toggle keyboard mode
                setKeyboardMode(keyboardMode === 'input' ? 'shortcut' : 'input');
                return;
            }

            // Input mode: no shortcuts (except Space/ESC above)
            if (keyboardMode === 'input') return;

            // Shortcut mode: process shortcuts (Numpad 1-4, QWER)
            const isNumpad = e.location === 3;

            if (isNumpad && ['1', '2', '3', '4'].includes(e.key)) {
                e.preventDefault();
                clearTimeout(shortcutModeTimer);
                shortcutModeTimer = setTimeout(() => setKeyboardMode('input'), 10000);
                const num = parseInt(e.key);
                if (e.shiftKey) {
                    addItem(CONDITION_TYPES_CN[num - 1]);
                } else {
                    sellItem(CONDITION_TYPES_CN[num - 1]);
                }
                return;
            }

            if (['q', 'w', 'e', 'r'].includes(e.key.toLowerCase())) {
                e.preventDefault();
                clearTimeout(shortcutModeTimer);
                shortcutModeTimer = setTimeout(() => setKeyboardMode('input'), 10000);
                const index = CONDITION_KEYS[e.key.toLowerCase()];
                if (index !== undefined && CONDITION_TYPES_CN[index]) {
                    openPriceModal(CONDITION_TYPES_CN[index]);
                }
                return;
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
            if (keyboardMode === 'input') {
                document.getElementById('barcodeInput').focus();
            }
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

        function adjustPrice(condition, delta) {
            const info = currentProduct.inventory[condition];
            if (!info) return;
            const currentPrice = info.live_price || info.suggested_price;
            const newPrice = Math.round((parseFloat(currentPrice) + delta) * 100) / 100;
            if (newPrice < 0) return;

            fetch('api/change_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    product_id: currentProduct.id,
                    condition_type: getConditionKey(condition),
                    new_price: newPrice,
                    live_session_id: liveSessionId
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (newPrice == info.suggested_price) {
                        info.live_price = null;
                    } else {
                        info.live_price = newPrice;
                    }
                    displayProduct();
                    showToast(`${condition} ¥${newPrice.toFixed(2)}`);
                } else {
                    showToast('❌ ' + (data.error || '调价失败'));
                }
            })
            .catch(err => {
                console.error(err);
                showToast('❌ 调价失败');
            });
        }

        function closeProduct() {
            document.getElementById('productDisplay').classList.remove('show');
            document.getElementById('standbyScreen').style.display = 'flex';
            document.getElementById('keyboardHint').classList.remove('show');
            currentProduct = null;
            lastScannedBarcode = '';
            hideSearchResults();
            document.getElementById('searchModeBadge').classList.remove('show');
            document.getElementById('barcodeInput').value = '';
            setKeyboardMode('input');
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
                            if (newSettings.logo_path !== undefined) {
                                systemSettings.logo_path = newSettings.logo_path;
                                const standbyIcon = document.getElementById('standbyIcon');
                                const standbyLogo = document.getElementById('standbyLogo');
                                if (newSettings.logo_path) {
                                    standbyLogo.src = newSettings.logo_path;
                                    standbyIcon.style.display = 'flex';
                                } else {
                                    standbyIcon.style.display = 'none';
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
            if (keyboardMode === 'input' && document.activeElement.id !== 'newPriceInput') {
                document.getElementById('barcodeInput').focus();
            }
        }, 1000);

        document.addEventListener('click', function(e) {
            if (e.target.closest('.price-modal') || e.target.closest('.voice-toggle') || e.target.closest('.search-results') || e.target.closest('#kbModeBadge')) return;
            if (keyboardMode === 'input' && document.activeElement.id !== 'newPriceInput') {
                document.getElementById('barcodeInput').focus();
            }
        });
    </script>
</body>
</html>