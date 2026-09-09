<?php
/**
 * list_shops.php — 集团下店铺列表
 * GET 可选 store_id（超管用，可看任意集团；其他角色只看本集团）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$isSuper = isSuperAdmin();
$storeId = getStoreId();

$reqStore = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : null;
if ($reqStore && !$isSuper && $reqStore !== $storeId) {
    error('无权查看该集团下的店铺');
}
if (!$isSuper && !$storeId) {
    error('请先选择集团');
}

$sql = 'SELECT sh.*, st.name AS store_name
        FROM shops sh
        JOIN stores st ON st.id = sh.store_id
        WHERE 1=1';
$params = [];
if ($isSuper && $reqStore) {
    $sql .= ' AND sh.store_id = ?';
    $params[] = $reqStore;
} elseif (!$isSuper) {
    $sql .= ' AND sh.store_id = ?';
    $params[] = $storeId;
}
$sql .= ' ORDER BY sh.store_id, sh.id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$shops = $stmt->fetchAll();

// 收银台 8 位码惰性补齐（迁移/老数据兜底）
foreach ($shops as &$sh) {
    $code = ensureShopPosCode($pdo, (int)$sh['id']);
    $sh['pos_code'] = $code;
    $sh['pos_url'] = $code ? '/admin/pos.php?c=' . $code : null;
    // 店员密码不回传明文/哈希，只给“是否已设置”
    $sh['offline_staff_pwd_set'] = !empty($sh['offline_staff_pwd']);
    unset($sh['offline_staff_pwd']);
}
unset($sh);

success(['data' => ['shops' => $shops]]);
