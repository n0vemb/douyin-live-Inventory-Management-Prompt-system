<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireNonOperator();
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
        $allowedFields = ['system_name', 'logo_path', 'condition_types', 'live_display', 'shipping_fee', 'actual_shipping_fee', 'platform_fee_rate', 'offline_price_ratio', 'offline_pay_qr_wx', 'offline_pay_qr_ali', 'pos_enabled', 'pos_screensaver_img', 'pos_screensaver_sec'];
        // store_name 映射到 name 字段
        $fieldMap = ['store_name' => 'name'];
        // 数值字段，不需要 JSON 编码
        $numericFields = ['shipping_fee', 'platform_fee_rate', 'offline_price_ratio', 'pos_enabled', 'pos_screensaver_sec'];
        $updateFields = [];
        $updateParams = [];

        // 店员密码：非空才更新，password_hash 存储；空值不修改
        if (isset($settings['offline_staff_pwd']) && $settings['offline_staff_pwd'] !== '') {
            $updateFields[] = 'offline_staff_pwd = ?';
            $updateParams[] = password_hash($settings['offline_staff_pwd'], PASSWORD_DEFAULT);
        }
        // 重置收银台 token
        if (!empty($settings['offline_reset_token'])) {
            $updateFields[] = 'pos_token = ?';
            $updateParams[] = bin2hex(random_bytes(16));
        }

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
