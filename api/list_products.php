<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/condition_common.php';

$keyword = $_GET['keyword'] ?? '';
$series = $_GET['series'] ?? '';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$canSeeProfit = !isOperator();

$conditionTypes = conditionNames($pdo, $storeId);

$sql = "SELECT p.* FROM products p";
$params = [];
$conditions = [];

if ($storeId) {
    $conditions[] = 'p.store_id = ?';
    $params[] = $storeId;
}

if (!empty($keyword)) {
    $conditions[] = '(p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ? OR p.pinyin_initials LIKE ?)';
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}

if (!empty($series)) {
    $conditions[] = 'p.series = ?';
    $params[] = $series;
}

if (!empty($conditions)) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY (SELECT MAX(ib.purchased_at) FROM inventory_batches ib WHERE ib.product_id = p.id) DESC, p.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$productIds = array_column($products, 'id');

$batchesData = [];
$inventoryData = [];
if (!empty($productIds)) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    // 批次查询限定当前店铺，防止跨店批次串入库存统计
    $batchParams = $productIds;
    $storeFilter = '';
    if ($storeId) {
        $storeFilter = ' AND store_id = ?';
        $batchParams[] = $storeId;
    }
    $stmt = $pdo->prepare("
        SELECT
            id,
            product_id,
            condition_type,
            batch_no,
            total_qty,
            remaining_qty,
            purchase_price,
            suggested_price,
            supplier,
            purchased_at
        FROM inventory_batches
        WHERE product_id IN ({$placeholders})
        {$storeFilter}
        ORDER BY purchased_at DESC, id DESC
    ");
    $stmt->execute($batchParams);
    $allBatches = $stmt->fetchAll();

    // 每 (商品, SKU) 聚合：在库总数 / 最新在库批次售价 / 批次加权均价(SKU均价)
    // SKU均价 = Σ(在库批次售价 × 该批次在库数量) ÷ 该SKU在库总数量（仅统计有售价的在库批次）
    $skuAgg = [];
    foreach ($allBatches as $b) {
        if (!isset($batchesData[$b['product_id']])) {
            $batchesData[$b['product_id']] = [];
        }
        $batchesData[$b['product_id']][] = $b;

        $key = $b['product_id'] . '_' . $b['condition_type'];
        if (!isset($inventoryData[$key])) {
            $inventoryData[$key] = [
                'total_stock' => 0,
                'batch_count' => 0,
                'suggested_price' => $b['suggested_price'],
                'purchase_price' => $b['purchase_price'],
                'latest_price' => null,
                'avg_price' => null,
            ];
        }
        $inventoryData[$key]['total_stock'] += $b['remaining_qty'];
        $inventoryData[$key]['batch_count']++;

        // 最新在库批次售价：批次已按 purchased_at DESC,id DESC 排序，首个「有库存且有售价」即最新
        if ($b['remaining_qty'] > 0 && !empty($b['suggested_price'])) {
            $aggKey = $key;
            if (!isset($skuAgg[$aggKey])) {
                $skuAgg[$aggKey] = ['latest' => null, 'sum_pq' => 0.0, 'priced_qty' => 0];
            }
            if ($skuAgg[$aggKey]['latest'] === null) {
                $skuAgg[$aggKey]['latest'] = (float)$b['suggested_price'];
            }
            $qty = (int)$b['remaining_qty'];
            $skuAgg[$aggKey]['sum_pq'] += (float)$b['suggested_price'] * $qty;
            $skuAgg[$aggKey]['priced_qty'] += $qty;
        }
    }
    foreach ($skuAgg as $key => $agg) {
        if (isset($inventoryData[$key])) {
            $inventoryData[$key]['latest_price'] = $agg['latest'];
            $inventoryData[$key]['avg_price'] = $agg['priced_qty'] > 0
                ? round($agg['sum_pq'] / $agg['priced_qty'], 2)
                : null;
        }
    }

    // 每个商品的最新入库时间（批次按 purchased_at DESC 排序，第一个即最新）
    $latestPurchaseAt = [];
    foreach ($allBatches as $b) {
        $pid = $b['product_id'];
        if (!isset($latestPurchaseAt[$pid])) {
            $latestPurchaseAt[$pid] = $b['purchased_at'];
        }
    }
}

foreach ($products as &$p) {
    $p['latest_purchase_at'] = $latestPurchaseAt[$p['id']] ?? null;
    $p['inventory_summary'] = [];
    foreach (['sealed', 'opened', 'boxless', 'flawed'] as $ct) {
        $key = $p['id'] . '_' . $ct;
        if (isset($inventoryData[$key])) {
            $p['inventory_summary'][$ct] = $inventoryData[$key];
            // 运营不可见进价
            if (!$canSeeProfit) {
                $p['inventory_summary'][$ct]['purchase_price'] = null;
            }
        }
    }
    $p['batches'] = $batchesData[$p['id']] ?? [];
    // 运营不可见批次进价
    if (!$canSeeProfit) {
        foreach ($p['batches'] as &$b) { $b['purchase_price'] = null; }
        unset($b);
    }

    // 从有库存的状态中聚合进价和售价（取最小价）
    $purchasePrices = [];
    $suggestedPrices = [];
    foreach ($p['inventory_summary'] as $ct => $data) {
        if ($data['total_stock'] > 0) {
            if ($canSeeProfit && !empty($data['purchase_price'])) $purchasePrices[] = $data['purchase_price'];
            if (!empty($data['suggested_price'])) $suggestedPrices[] = $data['suggested_price'];
        }
    }
    $p['overall_purchase_price'] = !empty($purchasePrices) ? min($purchasePrices) : null;
    $p['overall_suggested_price'] = !empty($suggestedPrices) ? min($suggestedPrices) : null;
}

$stmt = $pdo->prepare('SELECT DISTINCT series FROM products WHERE series IS NOT NULL AND series != ""' . ($storeId ? ' AND store_id = ?' : '') . ' ORDER BY series');
$stmt->execute($storeId ? [$storeId] : []);
$seriesList = $stmt->fetchAll(PDO::FETCH_COLUMN);

success(['data' => ['products' => $products, 'series_list' => $seriesList, 'inventory_data' => $inventoryData]]);
