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

    foreach ($batches as &$batch) {
        $batch['condition_name'] = $conditionNames[$batch['condition_type']] ?? $batch['condition_type'];
    }

    success(['data' => $batches]);

} catch (Exception $e) {
    error($e->getMessage(), 500);
}
