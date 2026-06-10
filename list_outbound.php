<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

try {
    $stmt = $pdo->prepare("
        SELECT
            o.*,
            p.name as product_name,
            p.common_name,
            b.purchase_price as batch_purchase_price,
            f.gmv,
            f.order_count,
            f.ad_spend
        FROM outbound_log o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN inventory_batches b ON o.batch_id = b.id
        LEFT JOIN outbound_finance f ON f.outbound_batch_no = o.outbound_batch_no AND f.store_id = o.store_id
        WHERE 1=1" . ($storeId ? " AND o.store_id = ?" : "") . "
        ORDER BY o.outbound_at DESC
        LIMIT 200
    ");
    $stmt->execute($storeId ? [$storeId] : []);
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
                'platform' => $record['platform'],
                'account' => $record['account'],
                'gmv' => $record['gmv'],
                'order_count' => $record['order_count'],
                'ad_spend' => $record['ad_spend'],
                'items' => [],
                'total_qty' => 0,
                'total_amount' => 0,
                'total_cost' => 0
            ];
        }
        $grouped[$batchNo]['items'][] = $record;
        $grouped[$batchNo]['total_qty'] += $record['qty'];
        $grouped[$batchNo]['total_amount'] += $record['qty'] * $record['outbound_price'];
        $grouped[$batchNo]['total_cost'] += $record['qty'] * $record['batch_purchase_price'];
    }

    $outbound = array_values($grouped);

    success(['data' => ['outbound' => $outbound]]);
} catch (Exception $e) {
    error($e->getMessage());
}