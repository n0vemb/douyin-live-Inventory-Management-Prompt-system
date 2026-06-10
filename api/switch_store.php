<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

requireAuth();
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$storeId = $input['store_id'] ?? null;

$pdo = getDB();

if ($storeId === null || $storeId === 0) {
    // 切换到全平台视角
    unset($_SESSION['view_store_id'], $_SESSION['view_store_name']);
    success([
        'message' => '已切换到全平台视角',
        'data' => ['store_id' => null, 'store_name' => '全平台']
    ]);
} else {
    // 切换到指定店铺
    $stmt = $pdo->prepare('SELECT id, name FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();

    if (!$store) {
        error('店铺不存在');
    }

    $_SESSION['view_store_id'] = (int)$store['id'];
    $_SESSION['view_store_name'] = $store['name'];
    success([
        'message' => "已切换到「{$store['name']}」",
        'data' => ['store_id' => (int)$store['id'], 'store_name' => $store['name']]
    ]);
}
