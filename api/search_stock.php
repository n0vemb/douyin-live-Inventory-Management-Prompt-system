<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/condition_common.php';

$barcode = $_GET['barcode'] ?? '';

if (empty($barcode)) {
    error('请提供条码');
}

try {
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
        WHERE p.barcode = ? AND ib.remaining_qty > 0' . ($storeId ? ' AND ib.store_id = ?' : '') . '
        ORDER BY ib.purchased_at ASC
    ');
    $stmt->execute($storeId ? [$barcode, $storeId] : [$barcode]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 品相中文名：统一来源（店铺配置 → 全局配置 → 默认兜底）
    $conditionNames = conditionNames($pdo, $storeId);

    foreach ($batches as &$batch) {
        $batch['condition_name'] = $conditionNames[$batch['condition_type']] ?? $batch['condition_type'];
    }

    // 附带所有状态类型列表供前端使用
    $conditionTypes = array_keys($conditionNames);

    success(['data' => $batches, 'condition_types' => $conditionTypes]);

} catch (Exception $e) {
    error($e->getMessage(), 500);
}
