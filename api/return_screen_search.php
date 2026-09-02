<?php
/**
 * return_screen_search.php — 直播返送屏商品查询（只读展示，不做任何库存变更）
 *
 * 实时可用库存 = 真实库存(inventory_batches.remaining_qty - locked_qty)
 *              − 全店所有「进行中」记账场次已录入的非赠品、非临时商品数量(live_ledger_item)
 *
 * 用法：
 *   GET ?q=条码或关键词
 *       - 条码精确命中        → mode=exact + product（完整 SKU 实时库存）
 *       - 否则按 名称/常用名/条码/拼音首字母 模糊搜索 → mode=list + products（含可用合计）
 *   GET ?product_id=N      → 轮询刷新：返回该商品完整实时数据（mode=exact + product）
 *
 * 权限：所有登录角色可见；按当前店铺隔离（super_admin 按 view_store_id）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/condition_common.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

/**
 * 该商品在全店所有进行中记账场次的已录数量（非赠品/非临时），按 condition_type 聚合
 */
function rsCommittedBySku($pdo, $storeId, $productId) {
    $sql = "SELECT li.condition_type, SUM(li.qty) AS qty
            FROM live_ledger_item li
            JOIN live_ledger_session s ON s.id = li.session_id AND s.status = 'active'
            WHERE li.product_id = ? AND li.is_gift = 0 AND li.is_temp = 0";
    $params = [$productId];
    if ($storeId) {
        $sql .= " AND s.store_id = ?";
        $params[] = $storeId;
    }
    $sql .= " GROUP BY li.condition_type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[$r['condition_type']] = (int)$r['qty'];
    }
    return $out;
}

/**
 * 商品可用库存合计（列表用）
 */
function rsAvailableTotal($pdo, $storeId, $productId) {
    $sql = 'SELECT COALESCE(SUM(remaining_qty - locked_qty), 0) FROM inventory_batches WHERE product_id = ?';
    $params = [$productId];
    if ($storeId) {
        $sql .= ' AND store_id = ?';
        $params[] = $storeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $real = (int)$stmt->fetchColumn();
    $committed = 0;
    foreach (rsCommittedBySku($pdo, $storeId, $productId) as $qty) {
        $committed += $qty;
    }
    return $real - $committed;
}

/**
 * 商品完整详情：SKU 实时可用库存 + 建议价 + 进价
 */
function rsProductDetail($pdo, $storeId, $productId) {
    $sql = 'SELECT * FROM products WHERE id = ?';
    $params = [$productId];
    if ($storeId) {
        $sql .= ' AND store_id = ?';
        $params[] = $storeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $p = $stmt->fetch();
    if (!$p) return null;

    $conditionMap = conditionNames($pdo, $storeId);
    $committed = rsCommittedBySku($pdo, $storeId, $productId);

    $skuSql = "
        SELECT ib.condition_type,
               SUM(ib.remaining_qty - ib.locked_qty) AS real_stock,
               (SELECT ib2.suggested_price FROM inventory_batches ib2
                 WHERE ib2.product_id = ib.product_id AND ib2.condition_type = ib.condition_type
                   AND ib2.suggested_price > 0
                 ORDER BY ib2.purchased_at DESC, ib2.id DESC LIMIT 1) AS suggested_price,
               (SELECT GROUP_CONCAT(DISTINCT ib3.purchase_price ORDER BY ib3.purchase_price SEPARATOR '/')
                 FROM inventory_batches ib3
                 WHERE ib3.product_id = ib.product_id AND ib3.condition_type = ib.condition_type
                   AND ib3.remaining_qty - ib3.locked_qty > 0 AND ib3.purchase_price > 0) AS purchase_price
        FROM inventory_batches ib
        WHERE ib.product_id = ?";
    $params = [$productId];
    if ($storeId) {
        $skuSql .= " AND ib.store_id = ?";
        $params[] = $storeId;
    }
    $skuSql .= " GROUP BY ib.product_id, ib.condition_type";
    $stmt = $pdo->prepare($skuSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $inventory = [];
    $allPrices = [];
    foreach ($rows as $row) {
        $cond = $row['condition_type'];
        $available = (int)$row['real_stock'] - ($committed[$cond] ?? 0);
        $name = $conditionMap[$cond] ?? $cond;
        $info = [
            'stock'           => $available,
            'suggested_price' => $row['suggested_price'] !== null ? (float)$row['suggested_price'] : null,
            'purchase_price'  => $row['purchase_price'] !== null && $row['purchase_price'] !== '' ? $row['purchase_price'] : null,
        ];
        $inventory[$cond]  = $info;
        $inventory[$name]  = $info;
        if ($row['purchase_price']) {
            foreach (explode('/', $row['purchase_price']) as $pp) {
                $fp = (float)$pp;
                if ($fp > 0) $allPrices[(string)$fp] = $fp;
            }
        }
    }
    ksort($allPrices);
    $overall = !empty($allPrices)
        ? implode('/', array_map(fn($v) => number_format($v, 2, '.', ''), $allPrices))
        : null;

    return [
        'id'                  => (int)$p['id'],
        'name'                => $p['name'],
        'common_name'         => $p['common_name'] ?? null,
        'product_description' => $p['product_description'] ?? null,
        'series'              => $p['series'] ?? '',
        'barcode'             => $p['barcode'],
        'qiandao_price'       => $p['qiandao_price'],
        'image_url'           => $p['image_url'] ?? '',
        'purchase_prices'     => $overall,
        'inventory'           => $inventory,
    ];
}

// ---- 轮询刷新：按商品 ID 返回完整实时数据 ----
$productId = (int)($_GET['product_id'] ?? 0);
if ($productId > 0) {
    $product = rsProductDetail($pdo, $storeId, $productId);
    if (!$product) error('商品不存在');
    success(['data' => ['mode' => 'exact', 'product' => $product]]);
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    error('请输入条码或商品关键词');
}

// ---- 1) 条码精确命中 → 直接返回商品 ----
$stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ?' . ($storeId ? ' AND store_id = ?' : ''));
$params = [$q];
if ($storeId) $params[] = $storeId;
$stmt->execute($params);
$hit = $stmt->fetch();
if ($hit) {
    $product = rsProductDetail($pdo, $storeId, (int)$hit['id']);
    if ($product) {
        success(['data' => ['mode' => 'exact', 'product' => $product]]);
    }
}

// ---- 2) 关键词模糊搜索 → 列表（含实时可用库存合计）----
$like = '%' . $q . '%';
$sql = "SELECT p.id, p.name, p.common_name, p.series, p.barcode, p.image_url, p.qiandao_price
        FROM products p
        WHERE 1=1";
$params = [];
if ($storeId) {
    $sql .= " AND p.store_id = ?";
    $params[] = $storeId;
}
$sql .= " AND (p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ? OR p.pinyin_initials LIKE ?)
         ORDER BY p.name LIMIT 20";
$params = array_merge($params, [$like, $like, $like, $like]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$result = [];
foreach ($stmt->fetchAll() as $p) {
    $result[] = [
        'id'              => (int)$p['id'],
        'name'            => $p['name'],
        'common_name'     => $p['common_name'] ?? '',
        'series'          => $p['series'] ?? '',
        'barcode'         => $p['barcode'],
        'image_url'       => $p['image_url'] ?? '',
        'qiandao_price'   => $p['qiandao_price'],
        'available_total' => rsAvailableTotal($pdo, $storeId, (int)$p['id']),
    ];
}
// 有货优先、名称升序
usort($result, function ($a, $b) {
    if ($b['available_total'] !== $a['available_total']) {
        return $b['available_total'] - $a['available_total'];
    }
    return strcmp($a['name'], $b['name']);
});

success(['data' => ['mode' => 'list', 'products' => $result]]);
