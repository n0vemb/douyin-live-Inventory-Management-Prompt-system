<?php
/**
 * 商品出入库流水（详情抽屉"出入库流水"tab）
 * 聚合三个数据源，按时间倒序：
 *   1. inventory_batches → 入库记录（每个批次=一次入库）
 *   2. sales_log        → 出库记录（销售）
 *   3. inventory_log    → 调整/转换/退货流水
 * 超管（storeId=null）不过滤店铺。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
$canSeeProfit = !isOperator();

$input = json_decode(file_get_contents('php://input'), true);
$productId = (int)($input['product_id'] ?? 0);
$limit = min((int)($input['limit'] ?? 100), 200);

if ($productId <= 0) {
    error('请提供商品ID');
}

// 加载状态中文名
$conditionNames = [
    'sealed' => '原盒未拆',
    'opened' => '拆盒无瑕',
    'boxless' => '无盒无瑕',
    'flawed' => '微瑕',
];
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

$storeCond = $storeId ? ' AND store_id = ?' : '';
$storeParams = $storeId ? [$storeId] : [];
$storeCondSl = $storeId ? ' AND sl.store_id = ?' : '';
$storeParamsSl = $storeId ? [$storeId] : [];
$storeCondOb = $storeId ? ' AND ob.store_id = ?' : '';
$storeParamsOb = $storeId ? [$storeId] : [];
$storeCondIl = $storeId ? ' AND il.store_id = ?' : '';
$storeParamsIl = $storeId ? [$storeId] : [];

$logs = [];

// 1. 入库记录（批次）
$stmt = $pdo->prepare("
    SELECT id, product_id, condition_type, batch_no, total_qty, purchase_price, suggested_price, supplier, remark,
           COALESCE(purchased_at, created_at) AS happened_at
    FROM inventory_batches
    WHERE product_id = ?{$storeCond}
    ORDER BY happened_at DESC
    LIMIT {$limit}
");
$stmt->execute(array_merge([$productId], $storeParams));
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $logs[] = [
        'source' => 'batch',
        'change_type' => 'purchase',
        'change_type_name' => '入库',
        'condition_type' => $b['condition_type'],
        'condition_name' => $conditionNames[$b['condition_type']] ?? $b['condition_type'],
        'qty_change' => (int)$b['total_qty'],
        'price' => $b['purchase_price'],
        'remark' => '批次 ' . ($b['batch_no'] ?? '#' . $b['id']) . ($b['supplier'] ? ' · ' . $b['supplier'] : '') . ($b['remark'] ? ' · ' . $b['remark'] : ''),
        'created_at' => $b['happened_at'],
    ];
}

// 2. 出库记录（销售 - sales_log）
$stmt = $pdo->prepare("
    SELECT sl.id, sl.condition_type, sl.qty, sl.sale_price, sl.purchase_cost, sl.returned_qty, sl.sold_at,
           ls.session_name, sl.live_session_id
    FROM sales_log sl
    LEFT JOIN live_sessions ls ON sl.live_session_id = ls.id
    WHERE sl.product_id = ?{$storeCondSl}
    ORDER BY sl.sold_at DESC
    LIMIT {$limit}
");
$stmt->execute(array_merge([$productId], $storeParamsSl));
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
    $returned = (int)($s['returned_qty'] ?? 0);
    $logs[] = [
        'source' => 'sales',
        'change_type' => 'sale',
        'change_type_name' => '销售出库',
        'condition_type' => $s['condition_type'],
        'condition_name' => $conditionNames[$s['condition_type']] ?? $s['condition_type'],
        'qty_change' => -((int)$s['qty'] - $returned),
        'price' => $s['sale_price'],
        'session_name' => $s['session_name'] ?? null,
        'live_session_id' => $s['live_session_id'] ?? null,
        'remark' => ($s['session_name'] ? '场次：' . $s['session_name'] : '非直播销售') . ($returned > 0 ? ' · 已退 ' . $returned . ' 件' : ''),
        'created_at' => $s['sold_at'],
    ];
}

// 3. 出库记录（普通出库 - outbound_log，含出库退货）
$stmt = $pdo->prepare("
    SELECT ob.id, ob.condition_type, ob.qty, ob.returned_qty, ob.live_session_id, ob.remark, ob.outbound_at,
           ls.session_name
    FROM outbound_log ob
    LEFT JOIN live_sessions ls ON ob.live_session_id = ls.id
    WHERE ob.product_id = ?{$storeCondOb}
    ORDER BY ob.outbound_at DESC
    LIMIT {$limit}
");
$stmt->execute(array_merge([$productId], $storeParamsOb));
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $o) {
    $returned = (int)($o['returned_qty'] ?? 0);
    $logs[] = [
        'source' => 'outbound',
        'change_type' => 'outbound',
        'change_type_name' => '出库',
        'condition_type' => $o['condition_type'],
        'condition_name' => $conditionNames[$o['condition_type']] ?? $o['condition_type'],
        'qty_change' => -((int)$o['qty'] - $returned),
        'price' => null,
        'session_name' => $o['session_name'] ?? null,
        'live_session_id' => $o['live_session_id'] ?? null,
        'remark' => ($o['session_name'] ? '场次：' . $o['session_name'] : '') . ($o['remark'] ? $o['remark'] : '') . ($returned > 0 ? ' · 已退 ' . $returned . ' 件' : ''),
        'created_at' => $o['outbound_at'],
    ];
}

// 4. 调整/转换/退货流水
$stmt = $pdo->prepare("
    SELECT il.*, ls.session_name
    FROM inventory_log il
    LEFT JOIN live_sessions ls ON il.live_session_id = ls.id
    WHERE il.product_id = ?{$storeCondIl}
    ORDER BY il.id DESC
    LIMIT {$limit}
");
$stmt->execute(array_merge([$productId], $storeParamsIl));
$changeTypeNames = [
    'adjust'      => '调整',
    'return'      => '退货',
    'convert_out' => '转换-转出',
    'convert_in'  => '转换-转入',
];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
    $logs[] = [
        'source' => 'inventory_log',
        'change_type' => $l['change_type'],
        'change_type_name' => $changeTypeNames[$l['change_type']] ?? $l['change_type'],
        'condition_type' => $l['condition_type'],
        'condition_name' => $conditionNames[$l['condition_type']] ?? $l['condition_type'],
        'qty_change' => (int)$l['qty_change'],
        'price' => $l['price'],
        'session_name' => $l['session_name'] ?? null,
        'live_session_id' => $l['live_session_id'] ?? null,
        'remark' => ($l['session_name'] ? '场次：' . $l['session_name'] : '') . ($l['remark'] ? ($l['session_name'] ? ' · ' : '') . $l['remark'] : ''),
        'created_at' => $l['created_at'],
    ];
}

// 按时间倒序
usort($logs, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});
$logs = array_slice($logs, 0, $limit);

// 运营不可见价格
if (!$canSeeProfit) {
    foreach ($logs as &$log) {
        $log['price'] = null;
    }
    unset($log);
}

success(['data' => ['logs' => $logs]]);
