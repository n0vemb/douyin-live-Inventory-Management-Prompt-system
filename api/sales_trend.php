<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$period = $_GET['period'] ?? 'day';
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 从 outbound_log (出库) 汇总，不含直播销售
switch ($period) {
    case 'week':
        $dateFormat = '%x-W%v';
        $labelExpr = "DATE_FORMAT(sold_at, '%x-W%v')";
        $labelExprOut = "DATE_FORMAT(outbound_at, '%x-W%v')";
        $orderExpr = "MIN(sold_at)";
        $orderExprOut = "MIN(outbound_at)";
        $days = max($days, 60);
        break;
    case 'month':
        $dateFormat = '%Y-%m';
        $labelExpr = "DATE_FORMAT(sold_at, '%Y-%m')";
        $labelExprOut = "DATE_FORMAT(outbound_at, '%Y-%m')";
        $orderExpr = "MIN(sold_at)";
        $orderExprOut = "MIN(outbound_at)";
        $days = max($days, 365);
        break;
    default: // day
        $dateFormat = '%Y-%m-%d';
        $labelExprOut = "DATE(outbound_at)";
        $orderExprOut = "outbound_at";
        break;
}

$since = date('Y-m-d', strtotime("-{$days} days"));

// outbound_log (出库销售，不含直播)
$stmt = $pdo->prepare("
    SELECT {$labelExprOut} AS date_label, SUM(outbound_price * qty) AS amount, SUM(qty) AS qty
    FROM outbound_log
    WHERE outbound_at >= ?" . ($storeId ? " AND store_id = ?" : "") . "
    GROUP BY date_label
    ORDER BY {$orderExprOut}
");
$stmt->execute($storeId ? [$since, $storeId] : [$since]);
$outboundData = $stmt->fetchAll();

// 合并数据
$merged = [];
foreach ($outboundData as $row) {
    $label = $row['date_label'];
    if (!isset($merged[$label])) $merged[$label] = ['amount' => 0, 'qty' => 0];
    $merged[$label]['amount'] = decimal($merged[$label]['amount'] + floatval($row['amount']));
    $merged[$label]['qty'] += intval($row['qty']);
}

// 生成按 time 排序的完整序列
if ($period === 'day') {
    // 补全缺失的日期
    $result = [];
    $start = new DateTime($since);
    $end = new DateTime();
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));
    foreach ($dateRange as $d) {
        $label = $d->format('Y-m-d');
        $result[] = [
            'label' => $d->format('m-d'),
            'amount' => $merged[$label]['amount'] ?? 0,
            'qty' => $merged[$label]['qty'] ?? 0
        ];
    }
} else {
    ksort($merged);
    $result = [];
    foreach ($merged as $label => $data) {
        $result[] = [
            'label' => $label,
            'amount' => $data['amount'],
            'qty' => $data['qty']
        ];
    }
    $result = array_slice($result, -20); // 最多显示20个点
}

success(['data' => [
    'labels' => array_column($result, 'label'),
    'amounts' => array_map(function($v) { return round($v, 2); }, array_column($result, 'amount')),
    'qtys' => array_column($result, 'qty')
]]);
