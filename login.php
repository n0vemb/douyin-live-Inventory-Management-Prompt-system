<?php
require_once __DIR__ . '/config.php';

// 如果已登录，直接跳转（运营跳直播场次，其余跳首页）
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'operator') {
        header('Location: ../admin/sessions.php');
    } else {
        header('Location: ../admin/');
    }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare(
                'SELECT u.*, s.name AS store_name
                 FROM users u
                 LEFT JOIN stores s ON u.store_id = s.id
                 WHERE u.username = ? AND u.is_active = 1'
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // 登录成功后重新生成 session ID，防止会话固定攻击
                session_regenerate_id(true);
                $_SESSION['user_id']       = (int)$user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['display_name']  = $user['display_name'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['store_id']      = $user['store_id'] ? (int)$user['store_id'] : null;
                $_SESSION['store_name']    = $user['store_name'];

                // 缓存店铺条码前缀
                if ($user['store_id']) {
                    $stmt = $pdo->prepare('SELECT barcode_prefix FROM stores WHERE id = ?');
                    $stmt->execute([$user['store_id']]);
                    $store = $stmt->fetch(PDO::FETCH_ASSOC);
                    $_SESSION['barcode_prefix'] = $store['barcode_prefix'] ?? '69414486';
                }

                $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
                    ->execute([$user['id']]);

                // 运营登录后直达直播场次页，其余角色到首页
                if (($user['role'] ?? '') === 'operator') {
                    header('Location: admin/sessions.php');
                } else {
                    header('Location: admin/');
                }
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        } catch (Exception $e) {
            $error = '登录失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - PPMart</title>
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
        .login-card {
            background: #1e1e30;
            border: 1px solid #2a2a3a;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .login-card h1 {
            color: #e8e8ed;
            font-size: 24px;
            text-align: center;
            margin-bottom: 8px;
        }
        .login-card .subtitle {
            color: #9d9daf;
            text-align: center;
            font-size: 14px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            color: #9d9daf;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
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
            margin-bottom: 20px;
            border: 1px solid rgba(239,68,68,0.2);
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #9d9daf;
        }
        .register-link a {
            color: #667eea;
            text-decoration: none;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: #10b981;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid rgba(16,185,129,0.2);
        }
    </style>
</head>
<body>
    <div class="login-card">
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

        <?php if (!empty($_GET['registered'])): ?>
            <div class="alert-success">✅ 注册成功！请登录。</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autofocus
                       placeholder="输入用户名" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required placeholder="输入密码">
            </div>
            <button type="submit" class="btn btn-primary">登 录</button>
        </form>

        <div class="register-link">
            还没有账号？<a href="register.php">注册新店铺</a>
        </div>
    </div>
</body>
</html>
