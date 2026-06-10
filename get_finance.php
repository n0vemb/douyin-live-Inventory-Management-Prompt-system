<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo   = $_GET['date_to'] ?? date('Y-m-d');
$platformFilter = $_GET['platform'] ?? null;
$accountFilter  = $_GET['account'] ?? null;

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

try {
    // 加载店铺财务设置
    $shippingFee = 3.00;
    $platformFeeRate = 0.05;
    if ($storeId) {
        $stmt = $pdo->prepare('SELECT shipping_fee, platform_fee_rate FROM stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();
        if ($store) {
            $shippingFee = floatval($store['shipping_fee'] ?? 3.00);
            $platformFeeRate = floatval($store['platform_fee_rate'] ?? 0.05);
        }
    } else {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE store_id IS NULL AND setting_key IN ('default_shipping_fee', 'default_platform_fee_rate')");
        while ($row = $stmt->fetch()) {
            if ($row['setting_key'] === 'default_shipping_fee') $shippingFee = floatval($row['setting_value']);
            if ($row['setting_key'] === 'default_platform_fee_rate') $platformFeeRate = floatval($row['setting_value']);
        }
    }

    // Step 1: 查询批次汇总
    $sql = "
        SELECT
            o.outbound_batch_no,
            MIN(o.outbound_at) as outbound_at,
            SUM(o.qty) as total_qty,
            SUM(o.qty * o.outbound_price) as total_amount,
            SUM(o.qty * COALESCE(b.purchase_price, 0)) as total_cost
        FROM outbound_log o
        LEFT JOIN inventory_batches b ON o.batch_id = b.id
        WHERE DATE(o.outbound_at) BETWEEN ? AND ?
    ";
    $params = [$dateFrom, $dateTo];
    if ($storeId) {
        $sql .= " AND o.store_id = ?";
        $params[] = $storeId;
    }
    $sql .= " GROUP BY o.outbound_batch_no ORDER BY outbound_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($batches)) {
        success(['data' => [
            'batches' => [],
            'settings' => ['shipping_fee' => $shippingFee, 'platform_fee_rate' => $platformFeeRate],
            'summary' => ['total_gmv' => 0, 'total_cost' => 0, 'total_shipping' => 0, 'total_platform_fee' => 0, 'total_ad_spend' => 0, 'total_profit' => 0]
        ]]);
        exit;
    }

    // Step 2: 查询财务数据 + 订单号/备注（单独查询避免 GROUP BY 问题）
    $batchNos = array_column($batches, 'outbound_batch_no');
    $placeholders = implode(',', array_fill(0, count($batchNos), '?'));

    // 财务数据
    $financeMap = [];
    $stmt = $pdo->prepare("SELECT outbound_batch_no, gmv, order_count, ad_spend FROM outbound_finance WHERE outbound_batch_no IN ($placeholders)" . ($storeId ? " AND store_id = ?" : ""));
    $fParams = $batchNos;
    if ($storeId) $fParams[] = $storeId;
    $stmt->execute($fParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $financeMap[$f['outbound_batch_no']] = $f;
    }

    // 订单号、备注、平台、账号
    $metaMap = [];
    $stmt = $pdo->prepare("SELECT outbound_batch_no, order_no, remark, platform, account FROM outbound_log WHERE outbound_batch_no IN ($placeholders)" . ($storeId ? " AND store_id = ?" : "") . " GROUP BY outbound_batch_no");
    $mParams = $batchNos;
    if ($storeId) $mParams[] = $storeId;
    $stmt->execute($mParams);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $metaMap[$m['outbound_batch_no']] = $m;
    }

    // Step 3: 合并数据并计算利润
    $totalGmv = 0; $totalCost = 0; $totalShipping = 0;
    $totalPlatformFee = 0; $totalAdSpend = 0; $totalProfit = 0;

    foreach ($batches as &$b) {
        $batchNo = $b['outbound_batch_no'];
        $b['total_qty'] = (int)$b['total_qty'];
        $b['total_amount'] = (float)$b['total_amount'];
        $b['total_cost'] = (float)$b['total_cost'];

        // 财务数据
        $fin = $financeMap[$batchNo] ?? null;
        $b['gmv'] = $fin ? (float)$fin['gmv'] : null;
        $b['order_count'] = $fin ? (int)$fin['order_count'] : null;
        $b['ad_spend'] = $fin ? (float)$fin['ad_spend'] : null;
        $b['platform_fee'] = null;
        $b['shipping_cost'] = null;
        $b['profit'] = null;

        // 元数据
        $meta = $metaMap[$batchNo] ?? null;
        $b['order_no'] = $meta ? $meta['order_no'] : null;
        $b['remark'] = $meta ? $meta['remark'] : null;
        $b['platform'] = $meta ? $meta['platform'] : null;
        $b['account'] = $meta ? $meta['account'] : null;

        // 平台/账号筛选
        if ($platformFilter && ($b['platform'] ?? '') !== $platformFilter) continue;
        if ($accountFilter && ($b['account'] ?? '') !== $accountFilter) continue;

        // 利润公式
        if ($b['gmv'] !== null && $b['gmv'] > 0) {
            $platformFee = $b['gmv'] * $platformFeeRate;
            $shipping = ($b['order_count'] ?? 0) * $shippingFee;
            $recovery = ($b['total_qty'] - ($b['order_count'] ?? 0)) * $shippingFee;
            $b['platform_fee'] = round($platformFee, 2);
            $b['shipping_cost'] = round($shipping, 2);
            $b['profit'] = round(
                $b['gmv'] * (1 - $platformFeeRate)
                - ($b['order_count'] ?? 0) * $shippingFee
                - $b['total_cost']
                - ($b['ad_spend'] ?? 0)
                + $recovery,
                2
            );

            $totalGmv += $b['gmv'];
            $totalShipping += $shipping;
            $totalPlatformFee += $platformFee;
            $totalAdSpend += ($b['ad_spend'] ?? 0);
            $totalProfit += $b['profit'];
        }
        $totalCost += $b['total_cost'];
    }
    unset($b);

    $batches = array_values($batches);

    success([
        'data' => [
            'batches' => $batches,
            'settings' => [
                'shipping_fee' => $shippingFee,
                'platform_fee_rate' => $platformFeeRate,
            ],
            'summary' => [
                'total_gmv' => round($totalGmv, 2),
                'total_cost' => round($totalCost, 2),
                'total_shipping' => round($totalShipping, 2),
                'total_platform_fee' => round($totalPlatformFee, 2),
                'total_ad_spend' => round($totalAdSpend, 2),
                'total_profit' => round($totalProfit, 2),
            ]
        ]
    ]);

} catch (Exception $e) {
    error($e->getMessage());
}
