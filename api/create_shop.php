<?php
/**
 * create_shop.php — 集团下新建店（A店/B店）
 * POST { store_id?, name, remark? }
 * 权限：平台超管；集团管理员仅限本集团
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
    echo json_encode(['success' => false, 'error' => '权限不足：仅平台超管或集团管理员可建店']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$storeId = isset($input['store_id']) && $input['store_id'] !== '' ? (int)$input['store_id'] : null;
$name = trim($input['name'] ?? '');
$remark = trim($input['remark'] ?? '');

if (!$isSuper) {
    $storeId = (int)getStoreId(); // 集团管理员只能在本集团建店
}
if (!$storeId) {
    error('请指定所属集团');
}
if ($name === '') {
    error('请输入店名');
}
if ($name === '默认店') {
    error('“默认店”为系统自动生成，请使用其他店名（如 A店/B店）');
}

$stmt = $pdo->prepare('SELECT id FROM stores WHERE id = ?');
$stmt->execute([$storeId]);
if (!$stmt->fetch()) {
    error('集团不存在');
}

$stmt = $pdo->prepare('SELECT id FROM shops WHERE store_id = ? AND name = ?');
$stmt->execute([$storeId, $name]);
if ($stmt->fetch()) {
    error('该集团下已存在同名店铺');
}

$stmt = $pdo->prepare('INSERT INTO shops (store_id, name, remark) VALUES (?, ?, ?)');
$stmt->execute([$storeId, $name, $remark !== '' ? $remark : null]);
$newShopId = (int)$pdo->lastInsertId();
ensureShopPosCode($pdo, $newShopId);

success([
    'message' => "店铺「{$name}」创建成功",
    'data' => ['shop_id' => $newShopId]
]);
