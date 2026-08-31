<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/label_template_table.php';

try {
    $pdo = getDB();
requireAuth(); $storeId = getStoreId();

    // 确保表存在且带 store_id 列（幂等）
    ensureLabelTemplatesTable($pdo);

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
