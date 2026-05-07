<?php
require_once __DIR__ . '/../config.php';

$barcode = $_GET['barcode'] ?? '';

if (empty($barcode)) {
    error('请提供条码');
}

try {
    $pdo = getDB();

    $stmt = $pdo->prepare('
        SELECT
            p.id as product_id,
            p.name as product_name,
            p.common_name,
            p.series,
            p.barcode,
            ib.id as batch_id,
            ib.condition_type,
            ib.batch_no,
            ib.purchase_price,
            ib.suggested_price,
            ib.remaining_qty,
            ib.purchased_at
        FROM inventory_batches ib
        JOIN products p ON ib.product_id = p.id
        WHERE p.barcode = ? AND ib.remaining_qty > 0
        ORDER BY ib.purchased_at ASC
    ');
    $stmt->execute([$barcode]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conditionNames = [
        'sealed' => '原盒未拆',
        'opened' => '拆盒无瑕',
        'boxless' => '无盒无瑕',
        'flawed' => '微瑕'
    ];

    // 从数据库加载状态名称
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types'");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && $result['setting_value']) {
            $types = json_decode($result['setting_value'], true);
            if ($types && is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) {
                    $conditionNames[$t['key']] = $t['name'];
                }
            }
        }
    } catch (Exception $e) {}

    foreach ($batches as &$batch) {
        $batch['condition_name'] = $conditionNames[$batch['condition_type']] ?? $batch['condition_type'];
    }

    // 附带所有状态类型列表供前端使用
    $conditionTypes = array_keys($conditionNames);

    success(['data' => $batches, 'condition_types' => $conditionTypes]);

} catch (Exception $e) {
    error($e->getMessage(), 500);
}
