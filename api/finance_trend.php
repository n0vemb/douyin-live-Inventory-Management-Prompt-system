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
    $shippingFee = 4.00;       // 售价中含的快递费（用于退货回收）
    $actualShippingFee = 3.00; // 实际快递成本（用于成本计算）
    $platformFeeRate = 0.05;
    if ($storeId) {
        $stmt = $pdo->prepare('SELECT shipping_fee, actual_shipping_fee, platform_fee_rate FROM stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();
        if ($store) {
            $shippingFee = decimal($store['shipping_fee'] ?? 4.00);
            $actualShippingFee = decimal($store['actual_shipping_fee'] ?? $shippingFee);
            $platformFeeRate = decimal($store['platform_fee_rate'] ?? 0.05);
        }
    }

    // 按日/周/月分组格式
    switch ($period) {
        case 'week':  $groupFormat = '%Y-%u';  break;
        case 'month': $groupFormat = '%Y-%m';  break;
        default:      $groupFormat = '%Y-%m-%d';
    }

    // 按批次汇总（一个批次一个时间点）
    $sql = "
        SELECT
            MIN(o.outbound_at) as outbound_at,
            SUM(o.qty) as total_qty,
            SUM(o.qty * b.purchase_price) as total_cost,
            MAX(f.gmv) as gmv,
            MAX(f.order_count) as order_count,
            MAX(f.ad_spend) as ad_spend
        FROM outbound_log o
        LEFT JOIN inventory_batches b ON o.batch_id = b.id
        LEFT JOIN outbound_finance f ON o.outbound_batch_no = f.outbound_batch_no
        WHERE DATE(o.outbound_at) BETWEEN ? AND ?
    ";
    $params = [$dateFrom, $dateTo];
    if ($storeId) {
        $sql .= " AND o.store_id = ?";
        $params[] = $storeId;
    }
    $sql .= " GROUP BY o.outbound_batch_no ORDER BY outbound_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 按时间分段聚合
    $periodMap = [];
    foreach ($batches as $b) {
        $ts = strtotime($b['outbound_at']);
        switch ($period) {
            case 'week':  $label = date('o-\WW', $ts); break;
            case 'month': $label = date('Y-m', $ts); break;
            default:      $label = date('Y-m-d', $ts);
        }
        if (!isset($periodMap[$label])) {
            $periodMap[$label] = ['qty' => 0, 'cost' => 0, 'gmv' => 0, 'orders' => 0, 'ads' => 0];
        }
        $periodMap[$label]['qty']    += (int)$b['total_qty'];
        $periodMap[$label]['cost']   += (float)$b['total_cost'];
        $periodMap[$label]['gmv']    += $b['gmv'] ? (float)$b['gmv'] : 0;
        $periodMap[$label]['orders'] += $b['order_count'] ? (int)$b['order_count'] : 0;
        $periodMap[$label]['ads']    += $b['ad_spend'] ? (float)$b['ad_spend'] : 0;
    }

    $labels = [];
    $profitData = [];

    foreach ($periodMap as $label => $d) {
        $labels[] = $label;
        $gmv = $d['gmv'];
        $orders = $d['orders'];
        $cost = $d['cost'];
        $ads = $d['ads'];
        $qty = $d['qty'];

        if ($gmv > 0) {
            // 与财务页面一致的完整利润公式
            $shipping = $orders * $actualShippingFee;
            $profit = round(
                $gmv * (1 - $platformFeeRate) - $shipping - $cost - $ads,
                2
            );
        } else {
            $profit = 0;
        }
        $profitData[] = $profit;
    }

    success([
        'data' => ['labels' => $labels, 'profit' => $profitData]
    ]);

} catch (Exception $e) {
    error($e->getMessage());
}
