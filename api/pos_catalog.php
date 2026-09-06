<?php
/**
 * pos_catalog.php — 收银台商品目录（免登录）
 * 售价 = MAX(在库批次进价) × 店铺加价比例；比例不出前端
 * 库存 = remaining_qty - locked_qty - 直播active场次占用（可售口径）
 */
require_once __DIR__ . '/pos_auth.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$storeId = requirePosStore();

// 补全相对路径为完整 URL（库中存 uploads/... 相对路径）
function posCatAssetUrl($path) {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}
$pdo = getDB();

// 目录轮询顺带执行 15 分钟未付款订单自动释放（多台收银机都能触发，无需后台打开页面）
posAutoReleaseUnpaid($pdo, $storeId, 15);

try {
    $stmt = $pdo->prepare('SELECT name, offline_price_ratio, pos_enabled, pos_screensaver_img, pos_screensaver_sec FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $store = $stmt->fetch();
    $storeName = $store['name'] ?? '';
    $posEnabled = (int)($store['pos_enabled'] ?? 1);
    $ssImg = $store['pos_screensaver_img'] ?? '';
    $ssSec = (int)($store['pos_screensaver_sec'] ?? 30);
    $ratio = decimal($store['offline_price_ratio'] ?? 1.80);
    // ratio=0 表示隐藏价格（前端价格全部显示 0.00）；负数视为异常重置为默认
    if ($ratio < 0) $ratio = 1.80;
    $condNames = conditionNames($pdo, $storeId);

    // 线下售价配置（SKU级手动定价，仅店长/超管可设；收银台始终生效）
    $offlinePrices = [];
    $opStmt = $pdo->query('SELECT product_id, condition_type, offline_price FROM product_offline_prices');
    if ($opStmt) {
        foreach ($opStmt->fetchAll() as $op) {
            $offlinePrices[$op['product_id'] . '|' . $op['condition_type']] = round(floatval($op['offline_price']), 2);
        }
    }

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

    // 直播占用：所有未结束(active)记账场次已录的非赠品商品数量（未打包出库前物理库存仍含这些）
    $liveOcc = [];
    $occStmt = $pdo->prepare("SELECT li.product_id, li.condition_type, SUM(li.qty) qty
        FROM live_ledger_item li
        JOIN live_ledger_session s ON s.id = li.session_id
        WHERE s.store_id = ? AND s.status = 'active' AND li.is_gift = 0 AND li.is_temp = 0
        GROUP BY li.product_id, li.condition_type");
    $occStmt->execute([$storeId]);
    foreach ($occStmt->fetchAll() as $o) {
        $liveOcc[$o['product_id'] . '|' . $o['condition_type']] = (int)$o['qty'];
    }

    $products = [];
    foreach ($rows as $r) {
        $pid = (int)$r['id'];
        if (!isset($products[$pid])) {
            $products[$pid] = [
                'id' => $pid,
                'name' => $r['name'],
                'series' => $r['series'] ?? '',
                'brand' => $r['brand'] ?? '',
                'image_url' => posCatAssetUrl($r['image_url'] ?? ''),
                'skus' => []
            ];
        }
        $cond = $r['condition_type'];
        if (!$cond) continue;
        $occupied = $liveOcc[$pid . '|' . $cond] ?? 0;
        $avail = max(0, (int)$r['avail_stock'] - $occupied);
        // 售价：优先 SKU 级手动线下售价（product_offline_prices）；未配置则 最高在库进价 × 加价比例
        $manual = $offlinePrices[$pid . '|' . $cond] ?? 0;
        $price = $avail > 0
            ? ($manual > 0 ? $manual : round($r['max_cost'] * $ratio, 2))
            : 0;
        $products[$pid]['skus'][] = [
            'condition_type' => $cond,
            'cond_name' => $condNames[$cond] ?? $cond,
            'stock' => $avail,
            'occupied_live' => $occupied,
            'price' => $price
        ];
    }

    success([
        'store_name' => $storeName,
        'pos_enabled' => $posEnabled,
        'screensaver_img' => $ssImg ? posCatAssetUrl($ssImg) : '',
        'screensaver_sec' => $ssSec,
        'products' => array_values($products)
    ]);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_catalog');
    error('加载商品目录失败: ' . $e->getMessage(), 500);
}
