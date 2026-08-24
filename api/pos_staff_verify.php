<?php
/**
 * pos_staff_verify.php — 店员模式密码校验（免登录）
 * 成功写 $_SESSION['pos_staff']；密码不回传
 * 兼容 password_hash 与明文存储
 */
require_once __DIR__ . '/pos_auth.php';
$storeId = requirePosStore();
$input = json_decode(file_get_contents('php://input'), true);
$pwd = $input['pwd'] ?? '';
if ($pwd === '') error('请输入店员密码');

$pdo = getDB();
try {
    $stmt = $pdo->prepare('SELECT offline_staff_pwd FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $row = $stmt->fetch();
    $stored = $row['offline_staff_pwd'] ?? '';
    if ($stored === '') error('本店未设置店员模式密码，请店长在店铺设置中配置', 403);

    $ok = password_verify($pwd, $stored);
    if (!$ok && strpos($stored, '$2y$') !== 0) {
        // 明文兼容比对
        $ok = hash_equals($stored, $pwd);
    }
    if ($ok) {
        $_SESSION['pos_staff'] = true;
        success(['staff' => true]);
    }
    error('密码错误，无法进入店员模式', 403);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_staff_verify');
    error('验证失败: ' . $e->getMessage(), 500);
}
