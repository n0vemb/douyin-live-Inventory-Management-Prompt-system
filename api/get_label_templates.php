<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getDB();
requireAuth(); $storeId = getStoreId();

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

    $stmt = $pdo->prepare('SELECT id, name, config, created_at, updated_at FROM label_templates WHERE 1=1' . ($storeId ? ' AND store_id = ?' : '') . ' ORDER BY updated_at DESC');
    $stmt->execute($storeId ? [$storeId] : []);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function($t) {
        $config = json_decode($t['config'], true);
        return [
            'id' => $t['id'],
            'name' => $t['name'],
            'canvasWidth' => $config['canvasWidth'] ?? 60,
            'canvasHeight' => $config['canvasHeight'] ?? 40,
            'paperType' => $config['paperType'] ?? 'continuous',
            'density' => $config['density'] ?? 'normal',
            'elements' => $config['elements'] ?? [],
            'createdAt' => $t['created_at'],
            'updatedAt' => $t['updated_at']
        ];
    }, $templates);

    success(['templates' => $formatted]);
} catch (Exception $e) {
    error($e->getMessage());
}
