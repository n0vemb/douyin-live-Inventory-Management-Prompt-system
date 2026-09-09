<?php
/**
 * reset_pos_code.php — 重置店铺收银台 8 位数字码
 * POST { shop_id }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$isSuper = isSuperAdmin();
$isGroup = isGroupAdmin();
if (!$isSuper && !$isGroup) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => '权限不足：仅平台超管或集团管理员可重置收银台码']);
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
if (!$isSuper && (int)$shop['store_id'] !== (int)getStoreId()) {
    error('无权操作该集团下的店铺');
}

$code = generateShopPosCode($pdo);
$stmt = $pdo->prepare('UPDATE shops SET pos_code = ? WHERE id = ?');
$stmt->execute([$code, $shopId]);

success([
    'message' => "收银台码已重置：{$code}",
    'data' => [
        'shop_id' => $shopId,
        'pos_code' => $code,
        'pos_url' => '/admin/pos.php?c=' . $code,
    ],
]);
