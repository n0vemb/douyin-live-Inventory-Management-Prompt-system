<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['product_id'] ?? 0;
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($productId)) {
    error('请提供商品ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND store_id = ?');
$stmt->execute([$productId, $storeId]);
$product = $stmt->fetch();

if (!$product) {
    error('商品未找到');
}

// 获取系统配置中的状态类型
$conditionNames = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕'
];

try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $result = $stmt->fetch();
        if ($result && $result['condition_types']) {
            $decoded = json_decode($result['condition_types'], true);
            if ($decoded && is_array($decoded)) {
                $conditionNames = [];
                foreach ($decoded as $condition) {
                    $conditionNames[$condition['key']] = $condition['name'];
                }
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result && $result['setting_value']) {
            $conditionTypes = json_decode($result['setting_value'], true);
            if ($conditionTypes && is_array($conditionTypes)) {
                $conditionNames = [];
                foreach ($conditionTypes as $condition) {
                    $conditionNames[$condition['key']] = $condition['name'];
                }
            }
        }
    }
} catch (Exception $e) {
    // 使用默认状态类型
}

$stmt = $pdo->prepare('
    SELECT * FROM inventory_batches
    WHERE product_id = ? AND remaining_qty > 0 AND store_id = ?
    ORDER BY condition_type, purchased_at ASC
');
$stmt->execute([$productId, $storeId]);
$batches = $stmt->fetchAll();

$inventoryData = [];
foreach ($conditionNames as $key => $name) {
    $conditionBatches = array_filter($batches, fn($b) => $b['condition_type'] === $key);

    $totalStock = 0;
    $totalCost = 0;
    $latestSuggestedPrice = 0;
    $batchList = [];

    foreach ($conditionBatches as $batch) {
        $totalStock += $batch['remaining_qty'];
        $totalCost += $batch['purchase_price'] * $batch['remaining_qty'];
        $latestSuggestedPrice = $batch['suggested_price'];
        $batchList[] = [
            'batch_id' => $batch['id'],
            'batch_no' => $batch['batch_no'],
            'purchase_price' => $batch['purchase_price'],
            'suggested_price' => $batch['suggested_price'],
            'remaining_qty' => $batch['remaining_qty'],
            'purchased_at' => $batch['purchased_at']
        ];
    }

    $inventoryData[$name] = [
        'stock' => $totalStock,
        'avg_cost' => $totalStock > 0 ? round($totalCost / $totalStock, 2) : 0,
        'total_cost' => $totalCost,
        'suggested_price' => $latestSuggestedPrice,
        'live_price' => null,
        'batches' => $batchList
    ];
}

$result = [
    'id' => $product['id'],
    'name' => $product['name'],
    'common_name' => $product['common_name'] ?? null,
    'product_description' => $product['product_description'] ?? null,
    'series' => $product['series'],
    'barcode' => $product['barcode'],
    'qiandao_price' => $product['qiandao_price'],
    'image_url' => $product['image_url'],
    'inventory' => $inventoryData
];

success(['data' => $result]);
