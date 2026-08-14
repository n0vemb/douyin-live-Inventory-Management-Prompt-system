<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
requireAuth();
$currentUser = getCurrentUser();
$storeId = getStoreId();

// 查询系统/店铺名称
$layoutSystemName = '🎪 PPMart';
$layoutLogoPath = '';
$layoutStoreName = '';
try {
    // 平台级名称/Logo：始终在左上角显示
    $stmt = getDB()->query("SELECT setting_key, setting_value FROM system_settings WHERE store_id IS NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'system_name' && !empty($row['setting_value'])) {
            $layoutSystemName = $row['setting_value'];
        }
        if ($row['setting_key'] === 'logo_path' && !empty($row['setting_value'])) {
            $layoutLogoPath = $row['setting_value'];
        }
    }

    // 店铺名 + 店铺Logo（用于右上角头像）
    $layoutStoreName = '';
    $layoutStoreLogoPath = '';
    if ($storeId) {
        $stmt = getDB()->prepare("SELECT name, logo_path FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($store) {
            $layoutStoreName = $store['name'] ?? '';
            if (!empty($store['logo_path'])) $layoutStoreLogoPath = $store['logo_path'];
        }
    }
} catch (Exception $e) {
    // 忽略查询失败，使用默认名称
}

$isSuperAdmin = ($currentUser['role'] === 'super_admin');
$isStoreAdmin = ($currentUser['role'] === 'store_admin');
$isOperator = ($currentUser['role'] === 'operator');
$allStores = [];
if ($isSuperAdmin) {
    try {
        $allStores = getDB()->query("SELECT id, name FROM stores ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
$currentViewStoreId = $_SESSION['view_store_id'] ?? null;
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
            <?php if ($currentUser['display_name'] || $currentUser['username']): ?>
            <div class="header-user" style="display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-secondary);">
                <?php if ($layoutStoreLogoPath):
                    $storeLogoUrl = $layoutStoreLogoPath;
                    if (!preg_match('/^(https?:|data:)/i', $storeLogoUrl)) {
                        $storeLogoUrl = '../' . $storeLogoUrl;
                    }
                ?>
                <img src="<?= htmlspecialchars($storeLogoUrl) ?>" alt="Logo" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:2px solid var(--border);">
                <?php else: ?>
                <span style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,#667eea,#764ba2); display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:600;">
                    <?= htmlspecialchars(mb_substr(!empty($currentUser['display_name']) ? $currentUser['display_name'] : $currentUser['username'], 0, 1)) ?>
                </span>
                <?php endif; ?>
                <span><?= htmlspecialchars(!empty($currentUser['display_name']) ? $currentUser['display_name'] : $currentUser['username']) ?></span>
                <?php if ($isSuperAdmin): ?>
                <span style="background:rgba(239,68,68,0.15); color:#ef4444; padding:2px 8px; border-radius:4px; font-size:11px;">超管</span>
                <select onchange="switchStore(this.value)" style="padding:4px 8px; border:1px solid var(--border); border-radius:4px; font-size:12px; background:var(--bg); color:var(--text); cursor:pointer; max-width:180px;">
                    <option value="" <?= empty($currentViewStoreId) ? 'selected' : '' ?>>🏪 全平台</option>
                    <?php foreach ($allStores as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $currentViewStoreId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php elseif ($currentUser['store_name']): ?>
                <span style="background:var(--bg-active); padding:2px 8px; border-radius:4px; font-size:11px;"><?= htmlspecialchars($currentUser['store_name']) ?></span>
                <?php endif; ?>
                <a href="#" onclick="event.preventDefault(); logout()" style="color:var(--text-tertiary); text-decoration:none; margin-left:4px;">退出</a>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <?php if (!$isOperator): ?>
            <div class="nav-section">
                <div class="nav-section-title">概览</div>
                <a href="index.php" class="nav-item <?= ($currentPage ?? '') === 'index' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
                    <span class="nav-label">首页</span>
                </a>
            </div>
            <?php endif; ?>
            <?php if (!$isOperator): ?>
            <div class="nav-section">
                <div class="nav-section-title">财务管理</div>
                <a href="finance.php" class="nav-item <?= ($currentPage ?? '') === 'finance' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                    <span class="nav-label">财务管理</span>
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-section">
                <div class="nav-section-title">库存管理</div>
                <a href="products.php" class="nav-item <?= ($currentPage ?? '') === 'products' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
                    <span class="nav-label">商品管理</span>
                </a>
                <a href="purchase_logs.php" class="nav-item <?= ($currentPage ?? '') === 'purchase_logs' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                    <span class="nav-label">标签打印</span>
                </a>
                <?php if (!$isOperator): ?>
                <a href="outbound.php" class="nav-item <?= ($currentPage ?? '') === 'outbound' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                    <span class="nav-label">商品出库</span>
                </a>
                <?php endif; ?>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">直播</div>
                <?php if (!$isOperator): ?>
                <a href="sales.php" class="nav-item <?= ($currentPage ?? '') === 'sales' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                    <span class="nav-label">销售记录</span>
                </a>
                <?php endif; ?>
                <a href="sessions.php" class="nav-item <?= ($currentPage ?? '') === 'sessions' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg></span>
                    <span class="nav-label">直播返送</span>
                </a>
                <a href="live_ledger.php" class="nav-item <?= ($currentPage ?? '') === 'live_ledger' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg></span>
                    <span class="nav-label">直播出库记账</span>
                </a>
                <?php if (!$isOperator): ?>
                <a href="live_ledger_history.php" class="nav-item <?= ($currentPage ?? '') === 'live_ledger_history' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><polyline points="12 7 12 12 15 15"/></svg></span>
                    <span class="nav-label">直播账本历史</span>
                </a>
                <?php endif; ?>
                <a href="vip_customers.php" class="nav-item <?= ($currentPage ?? '') === 'vip_customers' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <span class="nav-label">客户管理</span>
                </a>
                <a href="todos.php" class="nav-item <?= ($currentPage ?? '') === 'todos' ? 'active' : '' ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
                    <span class="nav-label">待办事项</span>
                </a>
            </div>
        </nav>
        <?php if ($isSuperAdmin || $isStoreAdmin): ?>
        <div class="nav-section">
            <div class="nav-section-title">账号管理</div>
            <a href="users.php" class="nav-item <?= ($currentPage ?? '') === 'users' ? 'active' : '' ?>">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <span class="nav-label">用户管理</span>
            </a>
        </div>
        <?php endif; ?>
        <?php if ($isSuperAdmin): ?>
        <div class="nav-section">
            <div class="nav-section-title">平台管理</div>
            <a href="stores.php" class="nav-item <?= ($currentPage ?? '') === 'stores' ? 'active' : '' ?>">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                <span class="nav-label">店铺管理</span>
            </a>
            <a href="mobile.php" class="nav-item <?= ($currentPage ?? '') === 'mobile' ? 'active' : '' ?>">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></span>
                <span class="nav-label">📱 移动端管理</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="sidebar-footer">
            <?php if (!$isOperator): ?>
            <a href="settings.php" class="nav-item <?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                <span class="nav-label"><?= $isSuperAdmin ? '平台设置' : '店铺设置' ?></span>
            </a>
            <?php endif; ?>
        </div>
    </aside>

    <style>
    .nav-icon svg { width:20px; height:20px; display:block; }
    </style>

    <!-- 点击遮罩关闭侧边栏（移动端） -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div id="mainContainer" class="container">

    <script>
    async function logout() {
        try {
            await fetch('../api/logout.php');
        } catch(e) {}
        window.location.href = '../login.php';
    }

    async function switchStore(storeId) {
        try {
            const res = await fetch('../api/switch_store.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ store_id: storeId ? parseInt(storeId) : null })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('切换店铺失败: ' + (data.error || '未知错误'));
            }
        } catch (err) {
            alert('切换店铺失败: ' + err.message);
        }
    }

    /**
     * 切换侧边栏展开/收起（全局函数，供 onclick 调用）
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

    // 全局模态框：点击背景关闭 + Esc 键关闭
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
            e.target.classList.remove('show');
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(function(m) {
                m.classList.remove('show');
            });
        }
    });

    // 移动端：自动包裹表格以支持横向滚动
    (function() {
        if (window.innerWidth > 768) return;
        document.querySelectorAll('table').forEach(function(t) {
            if (!t.parentNode.classList.contains('table-wrap')) {
                var w = document.createElement('div');
                w.className = 'table-wrap';
                t.parentNode.insertBefore(w, t);
                w.appendChild(t);
            }
        });
    })();
    </script>
