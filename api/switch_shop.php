<?php
/**
 * switch_shop.php — 超管在选定集团内切换店视角（空 = 看集团汇总）
 * POST { shop_id }  shop_id 为空/null 表示集团汇总视图
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

requireAuth();
requireSuperAdmin();

$storeId = getStoreId();
if (!$storeId) {
    error('请先选择集团');
}

$input = json_decode(file_get_contents('php://input'), true);
$shopId = isset($input['shop_id']) && $input['shop_id'] !== '' ? (int)$input['shop_id'] : null;

if ($shopId === null || $shopId === 0) {
    unset($_SESSION['view_shop_id'], $_SESSION['view_shop_name']);
    success([
        'message' => '已切换到集团汇总视角',
        'data' => ['shop_id' => null, 'shop_name' => '']
    ]);
}

$stmt = getDB()->prepare('SELECT * FROM shops WHERE id = ? AND store_id = ?');
$stmt->execute([$shopId, $storeId]);
$shop = $stmt->fetch();
if (!$shop) {
    error('店铺不存在或不属于当前集团');
}

$_SESSION['view_shop_id'] = (int)$shop['id'];
$_SESSION['view_shop_name'] = $shop['name'];

success([
    'message' => "已切换到「{$shop['name']}」视角",
    'data' => ['shop_id' => (int)$shop['id'], 'shop_name' => $shop['name']]
]);
