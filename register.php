<?php
require_once __DIR__ . '/config.php';

// 如果已登录，直接跳转
if (!empty($_SESSION['user_id'])) {
    header('Location: admin/');
    exit;
}

// 加载系统设置
$loginSystemName = 'PPMart';
$loginLogoPath = '';
try {
    $stmt = getDB()->query("SELECT setting_key, setting_value FROM system_settings WHERE store_id IS NULL");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'system_name' && !empty($row['setting_value'])) {
            $loginSystemName = $row['setting_value'];
        }
        if ($row['setting_key'] === 'logo_path' && !empty($row['setting_value'])) {
            $loginLogoPath = $row['setting_value'];
        }
    }
} catch (Exception $e) {}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storeName   = trim($_POST['store_name'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $displayName = trim($_POST['display_name'] ?? $username);

    $error = '';
    if (empty($storeName)) {
        $error = '请输入店铺名称';
    } elseif (empty($username)) {
        $error = '请输入管理员用户名';
    } elseif (strlen($password) < 6) {
        $error = '密码至少6位';
    } elseif ($password !== $confirmPass) {
        $error = '两次密码输入不一致';
    }

    if (empty($error)) {
        try {
            $pdo = getDB();

            // 检查用户名是否已存在
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                throw new Exception('用户名已被使用');
            }

            // 生成条码前缀
            $prefix = generateStoreBarcodePrefix($pdo);
            // 生成 VIP 外部同步 Token（vip_sync.php 用，每店铺独立）
            $vipSyncToken = bin2hex(random_bytes(24));

            $pdo->beginTransaction();

            // 创建店铺
            $stmt = $pdo->prepare('INSERT INTO stores (name, barcode_prefix, vip_sync_token) VALUES (?, ?, ?)');
            $stmt->execute([$storeName, $prefix, $vipSyncToken]);
            $storeId = (int)$pdo->lastInsertId();

            // 创建管理员用户
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (username, password_hash, display_name, role, store_id, is_active)
                 VALUES (?, ?, ?, \'store_admin\', ?, 1)'
            );
            $stmt->execute([$username, $hash, $displayName, $storeId]);

            $pdo->commit();

            // 重定向到登录页
            header('Location: login.php?registered=1');
            exit;

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = '注册失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($loginSystemName) ?> - 注册</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-card {
            background: #1e1e30;
            border: 1px solid #2a2a3a;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .register-card h1 {
            color: #e8e8ed;
            font-size: 24px;
            text-align: center;
            margin-bottom: 8px;
        }
        .register-card .subtitle {
            color: #9d9daf;
            text-align: center;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            color: #9d9daf;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #2a2a3a;
            border-radius: 10px;
            background: #12121a;
            color: #e8e8ed;
            font-size: 15px;
            transition: border-color 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(102,126,234,0.35);
        }
        .error {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            border: 1px solid rgba(239,68,68,0.2);
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #9d9daf;
        }
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        .login-link a:hover { text-decoration: underline; }
        .hint {
            font-size: 12px;
            color: #6b6b80;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="register-card">
        <?php if ($loginLogoPath):
            $logoUrl = $loginLogoPath;
            if (!preg_match('/^(https?:|data:)/i', $logoUrl)) {
                $logoUrl = '../' . $logoUrl;
            }
        ?>
        <div style="display:flex; align-items:center; gap:16px; justify-content:center; margin-bottom:32px;">
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="max-height:52px; max-width:120px; flex-shrink:0;">
            <div style="text-align:center;">
                <h1 style="margin:0; font-size:22px; letter-spacing:12px;"><?= htmlspecialchars($loginSystemName) ?></h1>
                <p class="subtitle" style="margin:10px 0 0 0; letter-spacing:3px;">直播销售中控系统</p>
            </div>
        </div>
        <?php else: ?>
        <h1><?= htmlspecialchars($loginSystemName) ?></h1>
        <p class="subtitle">直播销售中控系统</p>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="store_name">店铺名称</label>
                <input type="text" id="store_name" name="store_name" required
                       placeholder="例如：我的潮玩店"
                       value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="username">管理员用户名</label>
                <input type="text" id="username" name="username" required
                       placeholder="用于登录的用户名"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="display_name">显示名称（选填）</label>
                <input type="text" id="display_name" name="display_name"
                       placeholder="显示在页面右上角的名称"
                       value="<?= htmlspecialchars($_POST['display_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required
                       placeholder="至少6位密码" minlength="6">
                <div class="hint">至少6位字符</div>
            </div>
            <div class="form-group">
                <label for="confirm_password">确认密码</label>
                <input type="password" id="confirm_password" name="confirm_password" required
                       placeholder="再次输入密码" minlength="6">
            </div>
            <button type="submit" class="btn btn-primary">注册并创建店铺</button>
        </form>

        <div class="login-link">
            已有账号？<a href="login.php">去登录</a>
        </div>
    </div>
</body>
</html>
