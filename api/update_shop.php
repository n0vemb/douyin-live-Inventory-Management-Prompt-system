<?php
/**
 * update_shop.php — 修改店名/备注
 * POST { shop_id, name?, remark? }
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
if (!$isSuper && (int)$shop['store_id'] !== (int)getStoreId()) {
    error('无权修改该集团下的店铺');
}

$updates = [];
$params = [];

if (array_key_exists('name', $input)) {
    $name = trim((string)$input['name']);
    if ($name === '') {
        error('店名不能为空');
    }
    if ($name !== $shop['name']) {
        $stmt = $pdo->prepare('SELECT id FROM shops WHERE store_id = ? AND name = ? AND id != ?');
        $stmt->execute([$shop['store_id'], $name, $shopId]);
        if ($stmt->fetch()) {
            error('该集团下已存在同名店铺');
        }
    }
    $updates[] = 'name = ?';
    $params[] = $name;
}

if (array_key_exists('remark', $input)) {
    $remark = trim((string)$input['remark']);
    $updates[] = 'remark = ?';
    $params[] = $remark !== '' ? $remark : null;
}

if (empty($updates)) {
    error('没有需要更新的字段');
}

$params[] = $shopId;
$stmt = $pdo->prepare('UPDATE shops SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

success(['message' => '店铺已更新']);
