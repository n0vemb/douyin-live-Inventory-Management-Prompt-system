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

// 1. 入库记录（批次）——不限量，全量参与"当前库存"推算
// 入库量按实际计算：该批次 remaining + 累计出库量（历史盘点调大 remaining 后 total 不准，
// 用实际承载量才准确，2026-08-10 用户确认）
$stmt = $pdo->prepare("
    SELECT ib.id, ib.product_id, ib.condition_type, ib.batch_no, ib.total_qty, ib.remaining_qty,
           ib.purchase_price, ib.suggested_price, ib.supplier, ib.remark,
           COALESCE(ib.purchased_at, ib.created_at) AS happened_at,
           COALESCE((SELECT SUM(ob.qty) FROM outbound_log ob WHERE ob.batch_id = ib.id), 0) AS sold_qty
    FROM inventory_batches ib
    WHERE ib.product_id = ?{$storeCond}
    ORDER BY happened_at DESC
");
$stmt->execute(array_merge([$productId], $storeParams));
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
    // 入库量按实际：remaining + 累计出库（该批次当前承载 + 已卖出 = 历史实际入库量）
    // 不用 total_qty：被盘点清零的批次 total 还在但实际承载 0，会虚增
    $actualQty = (int)$b['remaining_qty'] + (int)$b['sold_qty'];
    if ($actualQty <= 0) continue; // 实际承载 0（已清零且没卖过）不显示
    $logs[] = [
        'source' => 'batch',
        'change_type' => 'purchase',
        'change_type_name' => '入库',
        'condition_type' => $b['condition_type'],
        'condition_name' => $conditionNames[$b['condition_type']] ?? $b['condition_type'],
        'qty_change' => $actualQty,
        'price' => $b['purchase_price'],
        'supplier' => $b['supplier'],
        'remark' => '批次 ' . ($b['batch_no'] ?? '#' . $b['id']) . ($b['supplier'] ? ' · ' . $b['supplier'] : '') . ($b['remark'] ? ' · ' . $b['remark'] : ''),
        'created_at' => $b['happened_at'],
    ];
}

// 2. 出库记录（销售 - sales_log）
// 排除「直播出库」产生的销售记录（live_ledger_end 双写 outbound_log+sales_log，
// 同一动作只显示 outbound_log 那条（带场次名+价格），避免流水重复）
// 匹配键用 batch_id+qty+remark：直播出库 sales/outbound 写同一 batch_id 且 qty 相同；
// 用 product+qty 会误排「同商品同 qty 的正常销售」（2026-08-10 修正）
$stmt = $pdo->prepare("
    SELECT sl.id, sl.condition_type, sl.qty, sl.sale_price, sl.purchase_cost, sl.returned_qty, sl.sold_at,
           ls.session_name, sl.live_session_id
    FROM sales_log sl
    LEFT JOIN live_sessions ls ON sl.live_session_id = ls.id
    WHERE sl.product_id = ?{$storeCondSl}
      AND NOT EXISTS (
          SELECT 1 FROM outbound_log ob
          WHERE ob.remark LIKE '直播出库(%'
            AND ob.qty = sl.qty
            AND ( (sl.batch_id IS NOT NULL AND ob.batch_id = sl.batch_id)
                  OR (sl.batch_id IS NULL AND ob.product_id = sl.product_id) )
      )
    ORDER BY sl.sold_at DESC
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

// 3. 出库记录（普通出库 - outbound_log，含直播出库 + 出库退货）
// 直播出库关联 live_ledger_session（场次），remark 带 场次名+运营+账号
$stmt = $pdo->prepare("
    SELECT ob.id, ob.condition_type, ob.qty, ob.returned_qty, ob.live_session_id, ob.remark, ob.outbound_at,
           ob.outbound_price, ob.account AS ob_account, ob.operator_username,
           ls.session_name, ls.operator, ls.account AS ls_account
    FROM outbound_log ob
    LEFT JOIN live_ledger_session ls ON ob.live_session_id = ls.id
    WHERE ob.product_id = ?{$storeCondOb}
    ORDER BY ob.outbound_at DESC
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
        'price' => $o['outbound_price'],
        'session_name' => $o['session_name'] ?? null,
        'live_session_id' => $o['live_session_id'] ?? null,
        'remark' => ($o['session_name'] ? '场次：' . $o['session_name'] : '') . ($o['remark'] ? ($o['session_name'] ? ' · ' : '') . $o['remark'] : '') . ($returned > 0 ? ' · 已退 ' . $returned . ' 件' : ''),
        'operator' => $o['operator'] ?? null,
        'account' => $o['ls_account'] ?? ($o['ob_account'] ?? null),
        'operator_username' => $o['operator_username'] ?? null,
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
");
$stmt->execute(array_merge([$productId], $storeParamsIl));
$changeTypeNames = [
    'purchase'    => '入库',
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

// 每条流水补「当前库存」= 该SKU（product+condition_type）在该事件发生后的库存
// 正向累加法（2026-08-10 用户确认）：从有记录的最早事件开始，初始 0，
// 按时间正序逐条累加，每条事件后的库存 = 累计值（clamp ≥ 0）
//   —— 入库/调整正：加；出库/清零负：减
//   —— 这样从有记录开始计算，不会出现负数
$byCond = [];
foreach ($logs as $i => $log) {
    $byCond[$log['condition_type']][] = $i;
}
foreach ($byCond as $cond => $idxList) {
    $evts = [];
    foreach ($idxList as $i) {
        $evts[] = ['idx' => $i, 'time' => $logs[$i]['created_at'] ?? '', 'delta' => (int)$logs[$i]['qty_change']];
    }
    // 时间正序；同时刻按业务顺序：先负（出库/清零）后正（入库/调整）
    usort($evts, function ($a, $b) {
        $c = strcmp($a['time'], $b['time']);
        if ($c !== 0) return $c;
        $da = $a['delta'] <=> 0;
        $db = $b['delta'] <=> 0;
        if ($da !== $db) return $da < $db ? -1 : 1; // 负(-1) 排在 正(1) 前
        return $a['idx'] <=> $b['idx'];
    });
    $running = 0;
    foreach ($evts as $evt) {
        $running += $evt['delta'];
        $logs[$evt['idx']]['current_stock'] = max($running, 0);
    }
}

$logs = array_slice($logs, 0, $limit);

// 运营不可见价格
if (!$canSeeProfit) {
    foreach ($logs as &$log) {
        $log['price'] = null;
    }
    unset($log);
}

success(['data' => ['logs' => $logs]]);
