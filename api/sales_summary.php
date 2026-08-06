<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getDB();
    requireAuth(); $storeId = getStoreId();
    $canSeeProfit = !isOperator();
    
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01 00:00:00');

    $stmt = $pdo->prepare('SELECT COUNT(*) as count, SUM(total_qty) as total_qty FROM inventory_batches WHERE purchased_at >= ?' . ($storeId ? ' AND store_id = ?' : ''));
    $stmt->execute($storeId ? [$monthStart, $storeId] : [$monthStart]);
    $monthPurchase = $stmt->fetch() ?: ['count' => 0, 'total_qty' => 0];

    $stmt = $pdo->prepare('SELECT o.qty, o.outbound_price, o.outbound_at, b.purchase_price as batch_purchase_price FROM outbound_log o LEFT JOIN inventory_batches b ON o.batch_id = b.id WHERE o.outbound_at >= ?' . ($storeId ? ' AND o.store_id = ?' : ''));
    $stmt->execute($storeId ? [$monthStart, $storeId] : [$monthStart]);
    $monthOutbound = $stmt->fetchAll() ?: [];

    $todaySalesAmount = 0;
    $todayProfit = 0;
    $monthSalesAmount = 0;
    $monthProfit = 0;

    foreach ($monthOutbound as $o) {
        $qty = intval($o['qty']);
        $price = decimal($o['outbound_price']);
        $cost = decimal($o['batch_purchase_price'] ?? 0);
        $amount = $qty * $price;
        $profit = $qty * ($price - $cost);

        $monthSalesAmount += $amount;
        $monthProfit += $profit;

        if (strpos($o['outbound_at'], $today) === 0) {
            $todaySalesAmount += $amount;
            $todayProfit += $profit;
        }
    }
    success([
        'data' => [
            'month_purchase_count' => intval($monthPurchase['count']),
            'month_purchase_qty' => intval($monthPurchase['total_qty']),
            'month_sales_amount' => floatval($monthSalesAmount),
            'month_profit' => $canSeeProfit ? floatval($monthProfit) : 0,
            'today_sales_amount' => floatval($todaySalesAmount),
            'today_profit' => $canSeeProfit ? floatval($todayProfit) : 0
        ]
    ]);
} catch (PDOException $e) {
    error('数据库错误: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error($e->getMessage(), 500);
}
?>