<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/condition_common.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$canSeeProfit = !isOperator();

$stmt = $pdo->prepare('
    SELECT
        p.id as product_id,
        p.name as product_name,
        p.common_name,
        p.series,
        p.barcode,
        p.pinyin_initials,
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
    WHERE ib.remaining_qty > 0' . ($storeId ? ' AND ib.store_id = ?' : '') . '
    ORDER BY ib.purchased_at DESC
');
$stmt->execute($storeId ? [$storeId] : []);

$stockList = $stmt->fetchAll();

// 品相中文名：统一来源（店铺配置 → 全局配置 → 默认兜底）
$conditionNames = conditionNames($pdo, $storeId);

foreach ($stockList as &$stock) {
    $stock['condition_name'] = $conditionNames[$stock['condition_type']] ?? $stock['condition_type'];
    $stock['last_purchased'] = date('Y-m-d', strtotime($stock['purchased_at']));
    // 运营不可见进价
    if (!$canSeeProfit) {
        $stock['purchase_price'] = null;
        $stock['batch_cost'] = null;
    }
}

$uniqueProducts = [];
foreach ($stockList as $s) {
    $uniqueProducts[$s['product_id']] = true;
}

$types = count($uniqueProducts);
$totalQty = array_sum(array_column($stockList, 'remaining_qty'));
$totalCost = $canSeeProfit ? array_sum(array_column($stockList, 'batch_cost')) : 0;
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
