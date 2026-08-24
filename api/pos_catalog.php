<?php
/**
 * pos_catalog.php — 收银台商品目录（免登录）
 * 售价 = MAX(在库批次进价) × 店铺加价比例；比例不出前端
 * 库存 = remaining_qty - locked_qty（POS锁定量视为不可用）
 */
require_once __DIR__ . '/pos_auth.php';
$storeId = requirePosStore();
$pdo = getDB();

try {
    $stmt = $pdo->prepare('SELECT name, offline_price_ratio FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    $storeName = $store['name'] ?? '';
    $ratio = decimal($store['offline_price_ratio'] ?? 1.80);
    if ($ratio <= 0) $ratio = 1.80;
    $condNames = conditionNames($pdo, $storeId);

    // 商品 + 品相聚合：可用库存 = Σ(remaining-locked)，售价 = MAX(可用批次的进价)×ratio
    $sql = "SELECT p.id, p.name, p.series, p.brand, p.image_url,
                   b.condition_type,
                   SUM(b.remaining_qty - b.locked_qty) AS avail_stock,
                   MAX(CASE WHEN b.remaining_qty - b.locked_qty > 0 THEN b.purchase_price ELSE 0 END) AS max_cost
            FROM products p
            LEFT JOIN inventory_batches b
                   ON b.product_id = p.id AND b.store_id = p.store_id
            WHERE p.store_id = ?
            GROUP BY p.id, p.name, p.series, p.brand, p.image_url, b.condition_type
            ORDER BY p.series, p.id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$storeId]);
    $rows = $stmt->fetchAll();

    $products = [];
    foreach ($rows as $r) {
        $pid = (int)$r['id'];
        if (!isset($products[$pid])) {
            $products[$pid] = [
                'id' => $pid,
                'name' => $r['name'],
                'series' => $r['series'] ?? '',
                'brand' => $r['brand'] ?? '',
                'image_url' => $r['image_url'] ?? '',
                'skus' => []
            ];
        }
        $cond = $r['condition_type'];
        if (!$cond) continue;
        $avail = max(0, (int)$r['avail_stock']);
        $price = $avail > 0 ? round($r['max_cost'] * $ratio, 2) : 0;
        $products[$pid]['skus'][] = [
            'condition_type' => $cond,
            'cond_name' => $condNames[$cond] ?? $cond,
            'stock' => $avail,
            'price' => $price
        ];
    }

    success([
        'store_name' => $storeName,
        'products' => array_values($products)
    ]);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_catalog');
    error('加载商品目录失败: ' . $e->getMessage(), 500);
}
