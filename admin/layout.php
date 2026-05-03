<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '管理后台' ?> - 泡泡玛特进销存</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0 30px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 22px;
            font-weight: 600;
        }
        .header-nav {
            display: flex;
            gap: 5px;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.3s;
        }
        .header-nav a:hover, .header-nav a.active {
            background: rgba(255,255,255,0.2);
        }
        .container {
            padding: 80px 30px 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .card-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a70d9;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #0ea472;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #5b6270;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 6px;
        }
        .form-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        td {
            font-size: 14px;
            color: #666;
        }
        tr:hover td {
            background: #f9fafb;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .text-success { color: #10b981; }
        .text-danger { color: #ef4444; }
        .text-warning { color: #f59e0b; }
        .text-muted { color: #9ca3af; }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .modal-title {
            font-size: 20px;
            color: #333;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }
        .modal-close:hover {
            color: #666;
        }
        .pagination {
            display: flex;
            gap: 8px;
            margin-top: 20px;
            justify-content: center;
        }
        .pagination button {
            padding: 8px 14px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .pagination button:hover {
            background: #f9fafb;
        }
        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .stock-low {
            color: #ef4444;
            font-weight: bold;
        }
        .stock-out {
            color: #9ca3af;
            text-decoration: line-through;
        }
        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar input, .search-bar select {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-bar input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        .condition-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin: 2px;
        }
        .condition-sealed { background: #dbeafe; color: #1e40af; }
        .condition-opened { background: #d1fae5; color: #065f46; }
        .condition-boxless { background: #fef3c7; color: #92400e; }
        .condition-flawed { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <header class="header">
        <h1 id="headerTitle">🎪 泡泡玛特进销存</h1>
        <nav class="header-nav">
            <a href="index.php" <?= ($currentPage ?? '') === 'index' ? 'class="active"' : '' ?>>首页</a>
            <a href="products.php" <?= ($currentPage ?? '') === 'products' ? 'class="active"' : '' ?>>商品管理</a>
            <a href="purchase_logs.php" <?= ($currentPage ?? '') === 'purchase_logs' ? 'class="active"' : '' ?>>标签打印</a>
            <a href="outbound.php" <?= ($currentPage ?? '') === 'outbound' ? 'class="active"' : '' ?>>商品出库</a>
            <a href="sales.php" <?= ($currentPage ?? '') === 'sales' ? 'class="active"' : '' ?>>直播销售记录</a>
            <a href="sessions.php" <?= ($currentPage ?? '') === 'sessions' ? 'class="active"' : '' ?>>直播场次</a>
            <a href="settings.php" <?= ($currentPage ?? '') === 'settings' ? 'class="active"' : '' ?>>设置</a>
            <a href="../live.php" target="_blank">📺 直播</a>
        </nav>
    </header>
    <div class="container">
    <script>
    (function() {
        fetch('../api/get_settings.php')
            .then(r => r.json())
            .then(data => {
                const settings = data.settings || data.data;
                if (data.success && settings && settings.system_name) {
                    document.getElementById('headerTitle').textContent = settings.system_name;
                }
            })
            .catch(() => {});
    })();
    </script>
