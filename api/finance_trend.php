<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$period = $_GET['period'] ?? 'day';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

try {
    // 加载店铺设置
    $shippingFee = 3.00;
    $platformFeeRate = 0.05;
    if ($storeId) {
        $stmt = $pdo->prepare('SELECT shipping_fee, platform_fee_rate FROM stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();
        if ($store) {
            $shippingFee = decimal($store['shipping_fee'] ?? 3.00);
            $platformFeeRate = decimal($store['platform_fee_rate'] ?? 0.05);
        }
    }

    // 按日/周/月分组
    switch ($period) {
        case 'week':
            $groupFormat = '%Y-%u'; // ISO week
            break;
        case 'month':
            $groupFormat = '%Y-%m';
            break;
        default:
            $groupFormat = '%Y-%m-%d';
    }

    $sql = "
        SELECT
            DATE_FORMAT(o.outbound_at, '$groupFormat') as period_label,
            MIN(o.outbound_at) as period_start,
            SUM(o.qty) as total_qty,
            SUM(o.qty * o.outbound_price) as total_amount,
            SUM(o.qty * b.purchase_price) as total_cost
        FROM outbound_log o
        LEFT JOIN inventory_batches b ON o.batch_id = b.id
        WHERE DATE(o.outbound_at) BETWEEN ? AND ?
    ";
    $params = [$dateFrom, $dateTo];
    if ($storeId) {
        $sql .= " AND o.store_id = ?";
        $params[] = $storeId;
    }
    $sql .= " GROUP BY period_label ORDER BY MIN(o.outbound_at) ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $profitData = [];

    foreach ($periods as $p) {
        $labels[] = $p['period_label'];
        // 基础利润 = 销售额 - 成本（不含GMV调整的简化版）
        $baseProfit = decimal($p['total_amount']) - decimal($p['total_cost']);
        $profitData[] = round($baseProfit, 2);
    }

    success([
        'data' => [
            'labels' => $labels,
            'profit' => $profitData,
        ]
    ]);

} catch (Exception $e) {
    error($e->getMessage());
}
