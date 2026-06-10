<?php
date_default_timezone_set('Asia/Shanghai');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function envOrDefault($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}
#mysql数据库配置在这里，只要修改这里就好了
define('DB_HOST', envOrDefault('PPMART_DB_HOST', 'localhost'));
define('DB_USER', envOrDefault('PPMART_DB_USER', 'ppmart2'));
define('DB_PASS', envOrDefault('PPMART_DB_PASS', ''));
define('DB_NAME', envOrDefault('PPMART_DB_NAME', 'ppmart2'));

// Windows 打印代理地址（启用后 direct_print.php 将发送标签到该代理打印）
// 格式：http://192.168.x.x:9188
define('WINDOWS_PRINT_PROXY_URL', envOrDefault('PPMART_PRINT_PROXY', ''));

define('CONDITION_TYPES', [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
]);

define('CONDITION_KEYS', [
    '1' => 'sealed',
    '2' => 'opened',
    '3' => 'boxless',
    '4' => 'flawed',
    'q' => 'sealed',
    'w' => 'opened',
    'e' => 'boxless',
    'r' => 'flawed'
]);

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // 清理任何输出缓冲
            if (ob_get_level()) {
                ob_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => '数据库连接失败: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 计算 EAN-13 校验位
 * @param string $digits 12位数字
 * @return int 校验位 (0-9)
 */
function calculateEAN13CheckDigit($digits) {
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $digit = intval($digits[$i]);
        // 奇数位（第1,3,5...位）权重1，偶数位（第2,4,6...位）权重3
        $sum += $digit * ($i % 2 === 0 ? 1 : 3);
    }
    return (10 - ($sum % 10)) % 10;
}

/**
 * 生成带 EAN-13 校验位的条形码，并检查数据库中是否已存在
 * 格式：{prefix} + 4位随机数 + 1位校验位 = 13位
 * @param PDO $pdo 数据库连接
 * @param string $prefix 店铺条码前缀（8位），默认 69414486
 * @return string|null 生成的条形码，失败返回 null
 */
/**
 * 原子化生成条码（使用 MySQL GET_LOCK 防止并发重复）
 * 相比 generateBarcode() 在高并发下更安全
 */
function generateBarcodeAtomic($pdo, $prefix = '69414486') {
    $lockName = 'barcode_gen_' . $prefix;
    $pdo->exec("SELECT GET_LOCK('$lockName', 5)");
    try {
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $randomPart = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $digits = $prefix . $randomPart;
            $checkDigit = calculateEAN13CheckDigit($digits);
            $barcode = $digits . $checkDigit;

            $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ?');
            $stmt->execute([$barcode]);
            if (!$stmt->fetch()) {
                return $barcode;
            }
        }
        return null;
    } finally {
        $pdo->exec("SELECT RELEASE_LOCK('$lockName')");
    }
}

function generateBarcode($pdo, $prefix = '69414486') {
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $randomPart = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $digits = $prefix . $randomPart;
        $checkDigit = calculateEAN13CheckDigit($digits);
        $barcode = $digits . $checkDigit;

        $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ?');
        $stmt->execute([$barcode]);
        if (!$stmt->fetch()) {
            return $barcode;
        }
    }
    return null;
}

/**
 * 生成唯一的店铺条码前缀（EAN-13 前8位）
 * 格式：69 + 6位随机数，确保在 stores 表中唯一
 * @param PDO $pdo 数据库连接
 * @return string 8位数字前缀
 */
function generateStoreBarcodePrefix($pdo) {
    $maxAttempts = 20;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $prefix = '69' . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE barcode_prefix = ?');
        $stmt->execute([$prefix]);
        if (!$stmt->fetch()) {
            return $prefix;
        }
    }
    // 保底：用时间戳后6位
    return '69' . substr(strrev((string)time()), 0, 6);
}

/**
 * 删除本地上传的商品图片文件
 * @param string $imageUrl 数据库中的 image_url 值（如 uploads/xxx.jpg）
 */
function deleteImageFile($imageUrl) {
    if (empty($imageUrl)) return;
    // 只处理本地 uploads/ 路径，不处理外部 URL
    if (strpos($imageUrl, 'uploads/') !== 0) return;
    $filePath = __DIR__ . '/' . $imageUrl;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

/**
 * 将系列名称转为安全的目录名
 */
function sanitizeSeriesDir($series) {
    if (empty($series)) return '';
    $name = str_replace(['/', '\\', '..', "\0"], '', $series);
    $name = trim($name);
    return $name === '' ? '_' : $name;
}

/**
 * 安全地处理货币值：转为浮点数并保留2位小数
 * PDO 返回的 DECIMAL 是字符串，直接使用可避免浮点精度问题
 * 需要做算术运算时再转为 float 并 round 到2位
 */
function decimal($value, $precision = 2) {
    return round((float)$value, $precision);
}

function success($data = []) {
    jsonResponse(array_merge(['success' => true], $data));
}

/**
 * 结构化错误日志
 * @param string $message 错误信息
 * @param string $context 上下文标签（如 api/sell_product_live）
 * @param array $extra 额外数据
 */
function logError($message, $context = 'general', $extra = []) {
    $logData = array_merge([
        'ts' => date('Y-m-d H:i:s'),
        'ctx' => $context,
        'msg' => $message,
        'user' => $_SESSION['username'] ?? 'guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
    ], $extra);
    error_log(json_encode($logData, JSON_UNESCAPED_UNICODE));
}

function error($message, $code = 400) {
    http_response_code($code);
    jsonResponse(['success' => false, 'error' => $message]);
}