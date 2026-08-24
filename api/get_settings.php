<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireAuth();
$storeId = getStoreId();

try {
    $pdo = getDB();

    if ($storeId) {
        // 店铺管理员：从 stores 表读取店铺级配置
        $stmt = $pdo->prepare('SELECT name, system_name, logo_path, condition_types, live_display, shipping_fee, actual_shipping_fee, platform_fee_rate, offline_price_ratio, offline_staff_pwd, offline_pay_qr_wx, offline_pay_qr_ali, pos_token FROM stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();

        $formatted = [];
        if ($store) {
            $formatted['store_name'] = $store['name'] ?? '';
            // system_name 保留作为可选的自定义标题，优先用店铺名
            if (!empty($store['system_name'])) $formatted['system_name'] = $store['system_name'];
            if (!empty($store['logo_path'])) $formatted['logo_path'] = $store['logo_path'];
            if ($store['condition_types']) {
                $decoded = json_decode($store['condition_types'], true);
                if (is_array($decoded)) $formatted['condition_types'] = $decoded;
            }
            if ($store['live_display']) {
                $decoded = json_decode($store['live_display'], true);
                if (is_array($decoded)) $formatted['live_display'] = $decoded;
            }
            // 线下收银台配置（店员密码绝不回传，只给是否已设置）
            $formatted['offline_price_ratio'] = $store['offline_price_ratio'] !== null ? floatval($store['offline_price_ratio']) : 1.80;
            $formatted['offline_staff_pwd_set'] = !empty($store['offline_staff_pwd']);
            $formatted['offline_pay_qr_wx'] = $store['offline_pay_qr_wx'] ?? '';
            $formatted['offline_pay_qr_ali'] = $store['offline_pay_qr_ali'] ?? '';
            $formatted['pos_token'] = $store['pos_token'] ?? '';
        }

        // 填充默认值
        if (!isset($formatted['condition_types'])) {
            $formatted['condition_types'] = [
                ['key' => 'sealed', 'name' => '原盒未拆', 'color' => '#10b981'],
                ['key' => 'opened', 'name' => '拆盒无瑕', 'color' => '#3b82f6'],
                ['key' => 'boxless', 'name' => '无盒无瑕', 'color' => '#f59e0b'],
                ['key' => 'flawed', 'name' => '微瑕', 'color' => '#ef4444']
            ];
        }
        if (!isset($formatted['live_display'])) {
            $formatted['live_display'] = ['elements' => []];
        }
        $formatted['shipping_fee'] = decimal($store['shipping_fee'] ?? 3.00);
        $formatted['actual_shipping_fee'] = decimal($store['actual_shipping_fee'] ?? 3.00);
        $formatted['platform_fee_rate'] = decimal($store['platform_fee_rate'] ?? 0.05);

        $formatted['server_time'] = date('Y-m-d H:i:s');
        success(['settings' => $formatted, 'data' => $formatted]);
    } else {
        // 超级管理员：从 system_settings 读取平台级配置
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM system_settings WHERE store_id IS NULL');
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['setting_value'];
            $decoded = json_decode($value, true);
            $settings[$row['setting_key']] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
        }

        $formatted = [];
        if (isset($settings['system_name'])) $formatted['system_name'] = $settings['system_name'];
        if (isset($settings['condition_types'])) $formatted['condition_types'] = $settings['condition_types'];
        if (isset($settings['live_display'])) $formatted['live_display'] = $settings['live_display'];
        if (isset($settings['logo_path'])) $formatted['logo_path'] = $settings['logo_path'];
        $formatted['shipping_fee'] = decimal($settings['default_shipping_fee'] ?? 3.00);
        $formatted['platform_fee_rate'] = decimal($settings['default_platform_fee_rate'] ?? 0.05);

        $formatted['server_time'] = date('Y-m-d H:i:s');
        success(['settings' => $formatted, 'data' => $formatted]);
    }
} catch (Exception $e) {
    error($e->getMessage());
}
