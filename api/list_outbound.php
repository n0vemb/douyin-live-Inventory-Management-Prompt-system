<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

try {
    $stmt = $pdo->query("
        SELECT 
            o.*,
            p.name as product_name,
            p.common_name,
            b.purchase_price as batch_purchase_price
        FROM outbound_log o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN inventory_batches b ON o.batch_id = b.id
        ORDER BY o.outbound_at DESC
        LIMIT 200
    ");
    $allRecords = $stmt->fetchAll();

    $grouped = [];
    foreach ($allRecords as $record) {
        $batchNo = $record['outbound_batch_no'] ?: 'legacy_' . $record['id'];
        if (!isset($grouped[$batchNo])) {
            $grouped[$batchNo] = [
                'batch_no' => $record['outbound_batch_no'],
                'outbound_at' => $record['outbound_at'],
                'order_no' => $record['order_no'],
                'remark' => $record['remark'],
                'items' => [],
                'total_qty' => 0,
                'total_amount' => 0
            ];
        }
        $grouped[$batchNo]['items'][] = $record;
        $grouped[$batchNo]['total_qty'] += $record['qty'];
        $grouped[$batchNo]['total_amount'] += $record['qty'] * $record['outbound_price'];
    }

    $outbound = array_values($grouped);

    success(['data' => ['outbound' => $outbound]]);
} catch (Exception $e) {
    error($e->getMessage());
}
