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
        // 确保 returned_qty 字段存在（兼容旧数据）
        if (!isset($record['returned_qty'])) $record['returned_qty'] = 0;
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
        $record['_actual_qty'] = $record['qty'] - $record['returned_qty'];
        $grouped[$batchNo]['items'][] = $record;
        $actualQty = $record['qty'] - $record['returned_qty'];
        $grouped[$batchNo]['total_qty'] += $actualQty;
        $grouped[$batchNo]['total_amount'] += $actualQty * $record['outbound_price'];
        $grouped[$batchNo]['total_cost'] += $actualQty * $record['batch_purchase_price'];
        // 退回数量单独标记（前端展示用：实际出库 = qty - returned_qty）
        $grouped[$batchNo]['total_returned_qty'] = ($grouped[$batchNo]['total_returned_qty'] ?? 0) + $record['returned_qty'];
    }

    $outbound = array_values($grouped);

    success(['data' => ['outbound' => $outbound]]);
} catch (Exception $e) {
    error($e->getMessage());
}