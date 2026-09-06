<?php
/**
 * live_ledger_price_stock.php — 快捷查询：商品各SKU价格与库存（右侧吸附面板用）
 * GET ?q=关键词（名称/常用名/条码/拼音首字母）
 * 返回匹配商品，每个商品带 skus: [{condition_type, condition_name, total_stock, stock(可售), occupied, cost, price}]
 * stock = 在库(remaining - locked) − 本场已录 − 其他进行中场次已录（可售库存）
 * occupied = 本场已录 + 其他场次已录；进价/售价取该 SKU 最新有库存批次
 * 运营角色隐藏进价(cost=null)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/permission_helper.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$q = trim($_GET['q'] ?? '');
if ($q === '') error('请输入搜索关键词');
$limit = isset($_GET['limit']) ? min(20, (int)$_GET['limit']) : 10;
$sessionId = (int)($_GET['session_id'] ?? 0);

// 搜索商品
$sql = "SELECT DISTINCT p.id, p.name, p.common_name, p.barcode, p.series
        FROM products p
        WHERE 1=1";
$params = [];
if ($storeId) {
    $sql .= " AND p.store_id = ?";
    $params[] = $storeId;
}
$sql .= " AND (p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ? OR p.pinyin_initials LIKE ?)";
$like = '%' . $q . '%';
$params = array_merge($params, [$like, $like, $like, $like]);
$sql .= " ORDER BY p.name LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 占用统计（仅当传入当前场次时）：本场已录 + 其他进行中场次已录，按 (product, condition) 聚合
$occLocal = [];
$occOther = [];
$pids = array_column($products, 'id');
if ($sessionId > 0 && $pids) {
    $inList = implode(',', array_map('intval', $pids));
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT li.product_id, li.condition_type, SUM(li.qty) qty,
            (li.session_id = ?) AS is_local
            FROM live_ledger_item li
            JOIN live_ledger_session s ON s.id = li.session_id
            WHERE li.product_id IN ($inList) AND li.is_gift = 0 AND li.is_temp = 0
              AND s.store_id = ? AND (s.id = ? OR (s.status = 'active' AND s.id <> ?))
            GROUP BY li.product_id, li.condition_type, is_local");
        $stmt->execute([$sessionId, $storeId, $sessionId, $sessionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['product_id'] . '|' . $r['condition_type'];
            if ((int)$r['is_local'] === 1) $occLocal[$key] = (int)$r['qty'];
            else $occOther[$key] = (int)$r['qty'];
        }
    } else {
        // 超管视角（无店铺）仍可查跨场次占用
        $stmt = $pdo->prepare("SELECT li.product_id, li.condition_type, SUM(li.qty) qty,
            CASE WHEN li.session_id = ? THEN 1 ELSE 0 END is_local
            FROM live_ledger_item li
            JOIN live_ledger_session s ON s.id = li.session_id
            WHERE li.product_id IN ($inList) AND li.is_gift = 0 AND li.is_temp = 0
              AND (li.session_id = ? OR (s.status = 'active' AND s.id <> ?))
            GROUP BY li.product_id, li.condition_type, is_local");
        $stmt->execute([$sessionId, $sessionId, $sessionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $key = $r['product_id'] . '|' . $r['condition_type'];
            if ((int)$r['is_local'] === 1) $occLocal[$key] = (int)$r['qty'];
            else $occOther[$key] = (int)$r['qty'];
        }
    }
}

$maskProfit = shouldMaskProfit();

// 状态中文名
$conditionNames = ['sealed' => '原盒未拆', 'opened' => '拆盒无瑕', 'boxless' => '无盒无瑕', 'flawed' => '微瑕'];
try {
    if ($storeId) {
        $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
        $stmt->execute([$storeId]);
        $r = $stmt->fetch();
        if ($r && $r['condition_types']) {
            $types = json_decode($r['condition_types'], true);
            if (is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) $conditionNames[$t['key']] = $t['name'];
            }
        }
    }
} catch (Exception $e) {}

// 每个商品按 SKU 聚合库存/价格
foreach ($products as &$p) {
    $stmt = $pdo->prepare("
        SELECT ib.condition_type,
               SUM(ib.remaining_qty) AS total_stock,
               SUM(ib.remaining_qty - ib.locked_qty) AS stock,
               (SELECT ib2.purchase_price FROM inventory_batches ib2
                WHERE ib2.product_id = ib.product_id AND ib2.condition_type = ib.condition_type
                  AND ib2.remaining_qty > 0 AND ib2.purchase_price > 0
                ORDER BY ib2.purchased_at DESC, ib2.id DESC LIMIT 1) AS cost,
               (SELECT ib3.suggested_price FROM inventory_batches ib3
                WHERE ib3.product_id = ib.product_id AND ib3.condition_type = ib.condition_type
                  AND ib3.remaining_qty > 0 AND ib3.suggested_price > 0
                ORDER BY ib3.purchased_at DESC, ib3.id DESC LIMIT 1) AS price
        FROM inventory_batches ib
        WHERE ib.product_id = ? AND ib.remaining_qty > 0
        GROUP BY ib.condition_type
        ORDER BY ib.condition_type
    ");
    $stmt->execute([$p['id']]);
    $skus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $p['skus'] = array_map(function ($s) use ($conditionNames, $maskProfit, $occLocal, $occOther, $p) {
        $key = $p['id'] . '|' . $s['condition_type'];
        $local = $occLocal[$key] ?? 0;
        $other = $occOther[$key] ?? 0;
        $raw = (int)$s['stock'];
        $available = max(0, $raw - $local - $other);
        return [
            'condition_type' => $s['condition_type'],
            'condition_name' => $conditionNames[$s['condition_type']] ?? $s['condition_type'],
            'total_stock' => (int)$s['total_stock'],
            'stock' => $available,
            'occupied' => $local + $other,
            'local_occupied' => $local,
            'other_occupied' => $other,
            'cost' => $maskProfit ? null : (floatval($s['cost']) ?: null),
            'price' => floatval($s['price']) ?: null,
        ];
    }, $skus);
}

success(['data' => ['products' => $products]]);
