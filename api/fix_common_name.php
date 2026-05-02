<?php
require_once __DIR__ . '/../config.php';

// Protect maintenance endpoint in normal operation.
$enabled = getenv('PPMART_ENABLE_MAINTENANCE_API');
if ($enabled !== '1') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "❌ 维护接口已禁用（设置 PPMART_ENABLE_MAINTENANCE_API=1 后可执行）";
    exit;
}

$pdo = getDB();

try {
    $pdo->exec("ALTER TABLE products ADD COLUMN common_name VARCHAR(255) DEFAULT NULL COMMENT '常用名称' AFTER name");
    echo "✅ common_name 字段添加成功";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⚠️ common_name 字段已存在";
    } else {
        echo "❌ 错误: " . $e->getMessage();
    }
}
