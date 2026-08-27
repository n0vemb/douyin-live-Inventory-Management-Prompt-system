<?php
/**
 * condition_common.php — 品相(condition_type)统一来源
 * 唯一入口：读取店铺配置 stores.condition_types；无配置读全局 system_settings；再无则默认兜底
 * 各业务文件统一 require 本文件调用 conditionNames()，禁止各自写死品相映射
 */

/**
 * 品相中文名映射 [key => name]
 * @param PDO $pdo
 * @param int|null $storeId 店铺ID；null/0 时读全局配置
 * @return array
 */
function conditionNames($pdo, $storeId = null)
{
    $defaults = ['sealed' => '原盒未拆', 'opened' => '拆盒无瑕', 'boxless' => '无盒无瑕', 'flawed' => '微瑕'];

    // 1. 店铺配置优先
    if (!empty($storeId)) {
        $stmt = $pdo->prepare('SELECT condition_types FROM stores WHERE id = ?');
        $stmt->execute([(int)$storeId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['condition_types'])) {
            $map = [];
            $types = json_decode($row['condition_types'], true);
            if (is_array($types)) {
                foreach ($types as $t) {
                    if (!empty($t['key'])) $map[$t['key']] = $t['name'];
                }
            }
            if ($map) return $map;
        }
    }

    // 2. 全局配置兜底
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && !empty($row['setting_value'])) {
            $map = [];
            $types = json_decode($row['setting_value'], true);
            if (is_array($types)) {
                foreach ($types as $t) {
                    if (!empty($t['key'])) $map[$t['key']] = $t['name'];
                }
            }
            if ($map) return $map;
        }
    } catch (Exception $e) {
        // 忽略，走默认
    }

    return $defaults;
}

/**
 * 品相 key 列表（顺序按店铺配置；无配置返回默认4个）
 */
function conditionKeys($pdo, $storeId = null)
{
    return array_keys(conditionNames($pdo, $storeId));
}
