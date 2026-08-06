<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

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

$conditionNames = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
];

// 从数据库加载状态名称（店铺或全局）
try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $result = $stmt->fetch();
        if ($result && $result['condition_types']) {
            $types = json_decode($result['condition_types'], true);
            if ($types && is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) {
                    $conditionNames[$t['key']] = $t['name'];
                }
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && $result['setting_value']) {
            $types = json_decode($result['setting_value'], true);
            if ($types && is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) {
                    $conditionNames[$t['key']] = $t['name'];
                }
            }
        }
    }
} catch (Exception $e) {}

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
