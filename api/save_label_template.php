<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('请使用POST方法');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['name']) || empty(trim($input['name']))) {
    error('请提供模板名称');
}

if (!isset($input['config']) || !is_array($input['config'])) {
    error('请提供有效的模板配置');
}

try {
    $pdo = getDB();

    // 确保表存在
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS label_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            config TEXT NOT NULL COMMENT 'JSON格式的模板配置',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $name = trim($input['name']);
    $config = json_encode([
        'canvasWidth' => $input['config']['canvasWidth'] ?? 60,
        'canvasHeight' => $input['config']['canvasHeight'] ?? 40,
        'paperType' => $input['config']['paperType'] ?? 'continuous',
        'density' => $input['config']['density'] ?? 'normal',
        'elements' => $input['config']['elements'] ?? []
    ], JSON_UNESCAPED_UNICODE);

    // 使用 INSERT ... ON DUPLICATE KEY UPDATE 来实现 upsert
    $stmt = $pdo->prepare("
        INSERT INTO label_templates (name, config)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE config = ?, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$name, $config, $config]);

    // 获取刚插入或更新的记录
    $stmt = $pdo->prepare('SELECT id, name, config, created_at, updated_at FROM label_templates WHERE name = ?');
    $stmt->execute([$name]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    $configDecoded = json_decode($template['config'], true);
    $result = [
        'id' => $template['id'],
        'name' => $template['name'],
        'canvasWidth' => $configDecoded['canvasWidth'] ?? 60,
        'canvasHeight' => $configDecoded['canvasHeight'] ?? 40,
        'paperType' => $configDecoded['paperType'] ?? 'continuous',
        'density' => $configDecoded['density'] ?? 'normal',
        'elements' => $configDecoded['elements'] ?? [],
        'createdAt' => $template['created_at'],
        'updatedAt' => $template['updated_at']
    ];

    success(['template' => $result, 'message' => '模板保存成功']);
} catch (Exception $e) {
    error($e->getMessage());
}
