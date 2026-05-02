<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

$stmt = $pdo->query('
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
        ib.purchased_at,
        (ib.purchase_price * ib.remaining_qty) as batch_cost
    FROM inventory_batches ib
    JOIN products p ON ib.product_id = p.id
    WHERE ib.remaining_qty > 0
    ORDER BY ib.purchased_at DESC
');

$stockList = $stmt->fetchAll();

$conditionNames = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
];

foreach ($stockList as &$stock) {
    $stock['condition_name'] = $conditionNames[$stock['condition_type']] ?? $stock['condition_type'];
    $stock['last_purchased'] = date('Y-m-d', strtotime($stock['purchased_at']));
}

$uniqueProducts = [];
foreach ($stockList as $s) {
    $uniqueProducts[$s['product_id']] = true;
}

$types = count($uniqueProducts);
$totalQty = array_sum(array_column($stockList, 'remaining_qty'));
$totalCost = array_sum(array_column($stockList, 'batch_cost'));
$totalValue = 0;
foreach ($stockList as $s) {
    if ($s['suggested_price']) {
        $totalValue += $s['suggested_price'] * $s['remaining_qty'];
    }
}

success([
    'data' => [
        'types' => $types,
        'total_qty' => $totalQty,
        'total_cost' => $totalCost,
        'total_value' => $totalValue,
        'stock_list' => $stockList
    ]
]);
