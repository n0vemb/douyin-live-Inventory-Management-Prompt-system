<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireAuth();
$storeId = getStoreId();

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['settings']) || !is_array($input['settings'])) {
    error('请提供有效的配置数据');
}

try {
    $pdo = getDB();
    $settings = $input['settings'];

    if ($storeId) {
        // 店铺管理员：保存到 stores 表
        $allowedFields = ['system_name', 'logo_path', 'condition_types', 'live_display', 'shipping_fee', 'platform_fee_rate'];
        // store_name 映射到 name 字段
        $fieldMap = ['store_name' => 'name'];
        // 数值字段，不需要 JSON 编码
        $numericFields = ['shipping_fee', 'platform_fee_rate'];
        $updateFields = [];
        $updateParams = [];

        foreach ($allowedFields as $field) {
            if (isset($settings[$field])) {
                $updateFields[] = "{$field} = ?";
                $value = in_array($field, $numericFields) ? floatval($settings[$field]) : (is_array($settings[$field]) ? json_encode($settings[$field], JSON_UNESCAPED_UNICODE) : $settings[$field]);
                $updateParams[] = $value;
            }
        }

        // 处理字段名映射（store_name → name）
        foreach ($fieldMap as $from => $to) {
            if (isset($settings[$from])) {
                $updateFields[] = "{$to} = ?";
                $updateParams[] = $settings[$from];
            }
        }

        if (!empty($updateFields)) {
            $updateParams[] = $storeId;
            $stmt = $pdo->prepare('UPDATE stores SET ' . implode(', ', $updateFields) . ' WHERE id = ?');
            $stmt->execute($updateParams);
        }

        success(['message' => '店铺配置已保存']);
    } else {
        // 超级管理员：保存到 system_settings (store_id=NULL)
        // 注意：ON DUPLICATE KEY UPDATE 对 NULL 值不生效，需先检查再更新/插入
        foreach ($settings as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $check = $pdo->prepare('SELECT id FROM system_settings WHERE store_id IS NULL AND setting_key = ?');
            $check->execute([$key]);
            if ($check->fetch()) {
                $stmt = $pdo->prepare('UPDATE system_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE store_id IS NULL AND setting_key = ?');
                $stmt->execute([$value, $key]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO system_settings (store_id, setting_key, setting_value) VALUES (NULL, ?, ?)');
                $stmt->execute([$key, $value]);
            }
        }

        success(['message' => '平台配置已保存']);
    }
} catch (Exception $e) {
    error($e->getMessage());
}
