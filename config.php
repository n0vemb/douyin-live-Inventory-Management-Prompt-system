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
define('DB_HOST', envOrDefault('PPMART_DB_HOST', 'localhost'));
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
            http_response_code(500);
            die(json_encode(['success' => false, 'error' => '数据库连接失败: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

function jsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function success($data = []) {
    jsonResponse(array_merge(['success' => true], $data));
}

function error($message, $code = 400) {
    http_response_code($code);
    jsonResponse(['success' => false, 'error' => $message]);
}
