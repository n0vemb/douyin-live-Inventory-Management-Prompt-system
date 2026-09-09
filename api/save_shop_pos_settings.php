<?php
/**
 * save_shop_pos_settings.php — 保存店级收银台设置（每店独立）
 * POST { shop_id, offline_price_ratio?, offline_staff_pwd?, offline_pay_qr_wx?,
 *        offline_pay_qr_ali?, pos_enabled?, pos_screensaver_img?, pos_screensaver_sec?, pos_hide_price? }
 * 店员密码：传空=不修改；传 '******'=不修改；其它=重置
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$isSuper = isSuperAdmin();
$isGroup = isGroupAdmin();
$isShop = isStoreAdmin();
if (!$isSuper && !$isGroup && !$isShop) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => '权限不足']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$shopId = (int)($input['shop_id'] ?? 0);
if ($shopId <= 0) {
    error('请提供店铺ID');
}

$stmt = $pdo->prepare('SELECT * FROM shops WHERE id = ?');
$stmt->execute([$shopId]);
$shop = $stmt->fetch();
if (!$shop) {
    error('店铺不存在');
}

$myStore = (int)getStoreId();
if (!$isSuper && (int)$shop['store_id'] !== $myStore) {
    error('无权修改该集团下的店铺');
}
if ($isShop && (int)getShopId() !== $shopId) {
    error('店管只能修改本店收银台设置');
}

$updates = [];
$params = [];

$ratio = $input['offline_price_ratio'] ?? null;
if ($ratio !== null) {
    $ratio = $ratio === '' ? null : round(floatval($ratio), 2);
    if ($ratio !== null && ($ratio <= 0 || $ratio > 100)) {
        error('加价比例需在 0~100 之间');
    }
    $updates[] = 'offline_price_ratio = ?';
    $params[] = $ratio;
}

$pwd = $input['offline_staff_pwd'] ?? null;
if (is_string($pwd) && $pwd !== '' && $pwd !== '******') {
    $updates[] = 'offline_staff_pwd = ?';
    $params[] = password_hash($pwd, PASSWORD_DEFAULT);
}

foreach (['offline_pay_qr_wx', 'offline_pay_qr_ali', 'pos_screensaver_img'] as $imgField) {
    if (array_key_exists($imgField, $input)) {
        $val = trim((string)$input[$imgField]);
        $updates[] = "{$imgField} = ?";
        $params[] = $val !== '' ? $val : null;
    }
}

if (array_key_exists('pos_enabled', $input)) {
    $updates[] = 'pos_enabled = ?';
    $params[] = !empty($input['pos_enabled']) ? 1 : 0;
}
if (array_key_exists('pos_hide_price', $input)) {
    $updates[] = 'pos_hide_price = ?';
    $params[] = !empty($input['pos_hide_price']) ? 1 : 0;
}
if (array_key_exists('pos_screensaver_sec', $input)) {
    $sec = max(5, (int)$input['pos_screensaver_sec']);
    $updates[] = 'pos_screensaver_sec = ?';
    $params[] = $sec;
}

if (empty($updates)) {
    error('没有需要保存的设置');
}

$params[] = $shopId;
$stmt = $pdo->prepare('UPDATE shops SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

success(['message' => '店级收银台设置已保存']);
