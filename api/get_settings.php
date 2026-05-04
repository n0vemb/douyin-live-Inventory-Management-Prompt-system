<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM system_settings');
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = $row['setting_value'];
        // 尝试解析 JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $settings[$row['setting_key']] = $decoded;
        } else {
            $settings[$row['setting_key']] = $value;
        }
    }
    // 重新组织成期望的格式
    $formatted = [];
    if (isset($settings['system_name'])) {
        $formatted['system_name'] = $settings['system_name'];
    }
    if (isset($settings['condition_types'])) {
        $formatted['condition_types'] = $settings['condition_types'];
    }
    if (isset($settings['live_display'])) {
        $formatted['live_display'] = $settings['live_display'];
    }
    if (isset($settings['logo_path'])) {
        $formatted['logo_path'] = $settings['logo_path'];
    }
    // Keep both keys for backward compatibility with existing pages.
    success([
        'settings' => $formatted,
        'data' => $formatted
    ]);
} catch (Exception $e) {
    error($e->getMessage());
}
