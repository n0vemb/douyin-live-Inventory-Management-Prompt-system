<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$productId = (int)($_GET['product_id'] ?? 0);

if ($productId <= 0) {
    error('请提供有效的商品ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

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
    WHERE p.id = ? AND ib.remaining_qty > 0' . ($storeId ? ' AND ib.store_id = ?' : '') . '
    ORDER BY ib.purchased_at ASC
');
$stmt->execute($storeId ? [$productId, $storeId] : [$productId]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 加载 condition 名称
$conditionNames = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
];
try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $result = $stmt->fetch();
        if ($result && $result['condition_types']) {
            $types = json_decode($result['condition_types'], true);
            if ($types && is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) {
                    $conditionNames[$t['key']] = $t['name'];
                }
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
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
    }
} catch (Exception $e) {}

foreach ($batches as &$b) {
    $b['condition_name'] = $conditionNames[$b['condition_type']] ?? $b['condition_type'];
}

success(['data' => ['batches' => $batches]]);
