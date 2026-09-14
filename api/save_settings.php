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
        $allowedFields = ['system_name', 'logo_path', 'condition_types', 'live_display', 'shipping_fee', 'actual_shipping_fee', 'platform_fee_rate', 'offline_price_ratio', 'offline_pay_qr_wx', 'offline_pay_qr_ali', 'pos_enabled', 'pos_screensaver_img', 'pos_screensaver_sec', 'pos_hide_price', 'pos_ad_lines', 'pos_pay_mode', 'epay_api_url', 'epay_pid', 'epay_sign_type'];
        // store_name 映射到 name 字段
        $fieldMap = ['store_name' => 'name'];
        // 数值字段，不需要 JSON 编码
        $numericFields = ['shipping_fee', 'platform_fee_rate', 'offline_price_ratio', 'pos_enabled', 'pos_screensaver_sec', 'pos_hide_price'];
        $updateFields = [];
        $updateParams = [];

        // 收款方式与易支付配置：金额/开关之外的字段先做白名单与格式归一化
        if (isset($settings['pos_pay_mode'])) {
            $settings['pos_pay_mode'] = ($settings['pos_pay_mode'] === 'epay') ? 'epay' : 'static';
        }
        if (isset($settings['epay_api_url'])) {
            $u = rtrim(trim((string)$settings['epay_api_url']), '/');
            $settings['epay_api_url'] = preg_match('#^https?://#i', $u) ? $u : 'https://www.ezfpy.cn';
        }
        if (isset($settings['epay_pid'])) {
            $settings['epay_pid'] = trim((string)$settings['epay_pid']);
        }
        if (isset($settings['epay_sign_type'])) {
            $settings['epay_sign_type'] = 'MD5';
        }
        // 商户密钥：非空才更新（前端留空=不修改），且只在 stores 上维护（门店不单独覆盖）
        if (isset($settings['epay_mch_key'])) {
            $epayKey = trim((string)$settings['epay_mch_key']);
            if ($epayKey !== '' && $epayKey !== '******') {
                $pdo->prepare('UPDATE stores SET epay_mch_key = ? WHERE id = ?')->execute([$epayKey, $storeId]);
            }
            unset($settings['epay_mch_key']);
        }

        // 店管/超管处于店视角时：收银台相关设置写 shops 表（每店独立）
        $shopId = getShopId();
        if ($shopId) {
            $shopFields = [];
            $shopParams = [];
            if (isset($settings['offline_staff_pwd']) && $settings['offline_staff_pwd'] !== '') {
                $shopFields[] = 'offline_staff_pwd = ?';
                $shopParams[] = password_hash($settings['offline_staff_pwd'], PASSWORD_DEFAULT);
            }
            if (!empty($settings['offline_reset_token'])) {
                $shopFields[] = 'pos_code = ?';
                $shopParams[] = generateShopPosCode($pdo);
            }
            foreach (['offline_price_ratio', 'offline_pay_qr_wx', 'offline_pay_qr_ali', 'pos_enabled', 'pos_screensaver_img', 'pos_screensaver_sec', 'pos_hide_price', 'pos_ad_lines'] as $f) {
                if (isset($settings[$f])) {
                    $shopFields[] = "{$f} = ?";
                    $val = in_array($f, ['offline_price_ratio', 'pos_enabled', 'pos_screensaver_sec', 'pos_hide_price'], true)
                        ? floatval($settings[$f])
                        : $settings[$f];
                    $shopParams[] = $val;
                }
            }
            if (!empty($shopFields)) {
                $shopParams[] = $shopId;
                $pdo->prepare('UPDATE shops SET ' . implode(', ', $shopFields) . ' WHERE id = ?')
                    ->execute($shopParams);
            }
            // 店级保存完成后，从 stores 更新里剔除这些字段
            foreach (['offline_price_ratio', 'offline_pay_qr_wx', 'offline_pay_qr_ali', 'pos_enabled', 'pos_screensaver_img', 'pos_screensaver_sec', 'offline_staff_pwd', 'offline_reset_token', 'pos_hide_price', 'pos_ad_lines'] as $f) {
                unset($settings[$f]);
            }
        } else {
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
