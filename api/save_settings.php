<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['settings']) || !is_array($input['settings'])) {
    error('请提供有效的配置数据');
}

try {
    $pdo = getDB();
    
    foreach ($input['settings'] as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        $stmt = $pdo->prepare('
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute([$key, $value, $value]);
    }
    
    success(['message' => '配置已保存']);
} catch (Exception $e) {
    error($e->getMessage());
}
