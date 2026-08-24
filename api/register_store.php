<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$storeName   = trim($input['store_name'] ?? '');
$username    = trim($input['username'] ?? '');
$password    = $input['password'] ?? '';
$displayName = trim($input['display_name'] ?? $username);

if (empty($storeName)) {
    error('请输入店铺名称');
}
if (empty($username)) {
    error('请输入用户名');
}
if (strlen($password) < 6) {
    error('密码至少6位');
}

try {
    $pdo = getDB();

    // 检查用户名
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        error('用户名已存在');
    }

    // 生成条码前缀
    $prefix = generateStoreBarcodePrefix($pdo);
    // 生成 VIP 外部同步 Token（vip_sync.php 用，每店铺独立）
    $vipSyncToken = bin2hex(random_bytes(24));
    // 生成收银台免登录 Token（pos.php 用，每店铺独立）
    $posToken = bin2hex(random_bytes(16));

    $pdo->beginTransaction();

    // 创建店铺
    $stmt = $pdo->prepare('INSERT INTO stores (name, barcode_prefix, vip_sync_token, pos_token) VALUES (?, ?, ?, ?)');
    $stmt->execute([$storeName, $prefix, $vipSyncToken, $posToken]);
    $storeId = (int)$pdo->lastInsertId();

    // 创建管理员用户
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, display_name, role, store_id, is_active)
         VALUES (?, ?, ?, \'store_admin\', ?, 1)'
    );
    $stmt->execute([$username, $hash, $displayName, $storeId]);

    $pdo->commit();

    success([
        'message' => '店铺注册成功',
        'data' => [
            'store_id' => $storeId,
            'store_name' => $storeName,
            'barcode_prefix' => $prefix,
            'vip_sync_token' => $vipSyncToken
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error($e->getMessage());
}
