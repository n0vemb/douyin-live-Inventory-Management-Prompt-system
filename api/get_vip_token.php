<?php
/**
 * get_vip_token.php — 返回当前视角店铺的 VIP 外部同步 Token
 * GET 无需参数
 * 超管全平台（store_id=null）返回空；店铺视角返回该店 token
 * 超管/店管/运营都可调用（只读 token，无敏感数据）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

if (empty($storeId)) {
    success(['data' => ['token' => null, 'store_name' => '']]);
}

$stmt = $pdo->prepare("SELECT id, name, vip_sync_token FROM stores WHERE id = ?");
$stmt->execute([$storeId]);
$store = $stmt->fetch();
if (!$store) {
    success(['data' => ['token' => null, 'store_name' => '']]);
}

success([
    'data' => [
        'token'      => $store['vip_sync_token'] ?: null,
        'store_name' => $store['name'],
    ]
]);
