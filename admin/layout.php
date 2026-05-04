<?php
require_once __DIR__ . '/../config.php';

// 查询系统名称
$layoutSystemName = '🎪 泡泡玛特进销存';
try {
    $stmt = getDB()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'system_name' LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) {
        $layoutSystemName = $row['setting_value'];
    }
} catch (Exception $e) {
    // 忽略查询失败，使用默认名称
}

// 查询 Logo
$layoutLogoPath = '';
try {
    $stmt = getDB()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'logo_path' LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['setting_value'])) {
        $layoutLogoPath = $row['setting_value'];
    }
} catch (Exception $e) {
    // 忽略查询失败
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? '管理后台') ?> - <?= htmlspecialchars($layoutSystemName) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-brand">
            <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="切换侧边栏">☰</button>
            <?php if ($layoutLogoPath):
                $logoUrl = $layoutLogoPath;
                if (!preg_match('/^(https?:|data:)/i', $logoUrl)) {
                    $logoUrl = '../' . $logoUrl;
                }
            ?>
            <img class="header-logo" src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo">
            <?php endif; ?>
            <h1 id="headerTitle"><?= htmlspecialchars($layoutSystemName) ?></h1>
        </div>
        <div class="header-actions">
            <a href="../live.php" target="_blank" class="live-badge">
                <span class="live-dot"></span>
                直播
            </a>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">概览</div>
                <a href="index.php" class="nav-item <?= ($currentPage ?? '') === 'index' ? 'active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-label">首页</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">库存管理</div>
                <a href="products.php" class="nav-item <?= ($currentPage ?? '') === 'products' ? 'active' : '' ?>">
                    <span class="nav-icon">🏷️</span>
                    <span class="nav-label">商品管理</span>
                </a>
                <a href="purchase_logs.php" class="nav-item <?= ($currentPage ?? '') === 'purchase_logs' ? 'active' : '' ?>">
                    <span class="nav-icon">🏷️</span>
                    <span class="nav-label">标签打印</span>
                </a>
                <a href="outbound.php" class="nav-item <?= ($currentPage ?? '') === 'outbound' ? 'active' : '' ?>">
                    <span class="nav-icon">📦</span>
                    <span class="nav-label">商品出库</span>
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">直播</div>
                <a href="sales.php" class="nav-item <?= ($currentPage ?? '') === 'sales' ? 'active' : '' ?>">
                    <span class="nav-icon">💰</span>
                    <span class="nav-label">销售记录</span>
                </a>
                <a href="sessions.php" class="nav-item <?= ($currentPage ?? '') === 'sessions' ? 'active' : '' ?>">
                    <span class="nav-icon">📺</span>
                    <span class="nav-label">直播场次</span>
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="settings.php" class="nav-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙️</span>
                <span class="nav-label">系统设置</span>
            </a>
        </div>
    </aside>

    <!-- 点击遮罩关闭侧边栏（移动端） -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div id="mainContainer" class="container">

    <script>
    (function() {
        /**
         * 切换侧边栏展开/收起
         */
        function toggleSidebar() {
            var body = document.body;
            var isHidden = body.classList.contains('sidebar-hidden');
            body.classList.remove('sidebar-hidden', 'sidebar-open');
            if (isHidden) {
                body.classList.add('sidebar-open');
            } else {
                var isOpen = body.classList.contains('sidebar-open');
                if (isOpen) {
                    body.classList.add('sidebar-hidden');
                } else {
                    body.classList.add('sidebar-open');
                }
            }
        }

        // 点击侧边栏外部区域（移动端）自动关闭
        document.addEventListener('click', function(e) {
            var sidebar = document.getElementById('sidebar');
            var toggle = document.querySelector('.sidebar-toggle');
            if (document.body.classList.contains('sidebar-open') &&
                sidebar &&
                !sidebar.contains(e.target) &&
                toggle &&
                !toggle.contains(e.target)) {
                document.body.classList.remove('sidebar-open');
                document.body.classList.add('sidebar-hidden');
            }
        });
    })();
    </script>
