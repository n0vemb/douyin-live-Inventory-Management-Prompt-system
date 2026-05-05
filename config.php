<?php
date_default_timezone_set('Asia/Shanghai');

function envOrDefault($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}
#mysql数据库配置在这里，只要修改这里就好了
define('DB_HOST', envOrDefault('PPMART_DB_HOST', '172.18.0.2'));
define('DB_USER', envOrDefault('PPMART_DB_USER', 'ppmart'));
define('DB_PASS', envOrDefault('PPMART_DB_PASS', ''));
define('DB_NAME', envOrDefault('PPMART_DB_NAME', 'ppmart'));

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
 * 格式：69414486 + 4位随机数 + 1位校验位 = 13位
 * @param PDO $pdo 数据库连接
 * @return string|null 生成的条形码，失败返回 null
 */
function generateBarcode($pdo) {
    $prefix = '69414486';
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

function success($data = []) {
    jsonResponse(array_merge(['success' => true], $data));
}

function error($message, $code = 400) {
    http_response_code($code);
    jsonResponse(['success' => false, 'error' => $message]);
}