<?php
/**
 * get_product_sales.php — 商品详情抽屉「销售记录」：显示该商品卖给了哪些客户
 * 数据源：直播记账出库（live_ledger_outbound + 客户 + 场次 + 出库单），
 * 同一商品同一场次多笔按实际出库时间/顺序排列。
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
$canSeeProfit = !isOperator();

$input = json_decode(file_get_contents('php://input'), true);
$productId = (int)($input['product_id'] ?? 0);
if ($productId <= 0) error('请提供商品ID');

// 品相中文名
$conditionNames = ['sealed' => '原盒未拆', 'opened' => '拆盒无瑕', 'boxless' => '无盒无瑕', 'flawed' => '微瑕'];
try {
    if ($storeId) {
        $stmt = $pdo->prepare('SELECT condition_types FROM stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['condition_types'])) {
            $types = json_decode($row['condition_types'], true);
            if (is_array($types)) {
                $conditionNames = [];
                foreach ($types as $t) $conditionNames[$t['key']] = $t['name'];
            }
        }
    }
} catch (Exception $e) {}

$sql = "
    SELECT
        lo.id AS seq,
        lo.product_id,
        li.condition_type,
        li.sell_price,
        lo.qty,
        COALESCE(ob.outbound_at, s.ended_at, s.created_at) AS sold_at,
        s.session_name,
        s.operator AS session_operator,
        s.account AS session_account,
        ob.operator_username,
        c.nickname,
        c.vip_no
    FROM live_ledger_outbound lo
    JOIN live_ledger_item li ON li.id = lo.item_id
    JOIN live_ledger_customer c ON c.id = lo.customer_id
    JOIN live_ledger_session s ON s.id = lo.session_id
    LEFT JOIN outbound_log ob ON ob.id = lo.outbound_log_id
    WHERE lo.product_id = ? AND li.is_gift = 0";
$params = [$productId];
if ($storeId) {
    $sql .= " AND s.store_id = ?";
    $params[] = $storeId;
}
$sql .= " ORDER BY sold_at DESC, lo.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$sales = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $price = floatval($r['sell_price'] ?? 0);
    $sales[] = [
        'id'            => (int)$r['seq'],
        'condition_type' => $r['condition_type'],
        'condition_name' => $conditionNames[$r['condition_type']] ?? $r['condition_type'],
        'qty'           => (int)$r['qty'],
        'price'         => $price,
        'amount'        => round($price * (int)$r['qty'], 2),
        'sold_at'       => $r['sold_at'] ?? null,
        'session_name'  => $r['session_name'] ?? '',
        'session_operator' => $r['session_operator'] ?? '',
        'session_account'  => $r['session_account'] ?? '',
        'operator_username' => $r['operator_username'] ?? null,
        'nickname'      => $r['nickname'] ?? '',
        'vip_no'        => $r['vip_no'] ?? '',
    ];
}

// 近期销量：近 3/5/7 天售出件数（不含赠品，按实际出库时间；未出库则回退场次结束/创建时间）
// 按 v23 软删规则，统计口径排除已删除行（li.is_deleted = 0）
$soldAtSql = 'COALESCE(ob.outbound_at, s.ended_at, s.created_at)';
$recentSql = "
    SELECT
        COALESCE(SUM(CASE WHEN {$soldAtSql} >= DATE_SUB(NOW(), INTERVAL 3 DAY) THEN lo.qty ELSE 0 END), 0) AS d3,
        COALESCE(SUM(CASE WHEN {$soldAtSql} >= DATE_SUB(NOW(), INTERVAL 5 DAY) THEN lo.qty ELSE 0 END), 0) AS d5,
        COALESCE(SUM(CASE WHEN {$soldAtSql} >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN lo.qty ELSE 0 END), 0) AS d7,
        MAX({$soldAtSql}) AS last_sold_at
    FROM live_ledger_outbound lo
    JOIN live_ledger_item li ON li.id = lo.item_id
    JOIN live_ledger_session s ON s.id = lo.session_id
    LEFT JOIN outbound_log ob ON ob.id = lo.outbound_log_id
    WHERE lo.product_id = ? AND li.is_gift = 0 AND li.is_deleted = 0";
$recentParams = [$productId];
if ($storeId) {
    $recentSql .= ' AND s.store_id = ?';
    $recentParams[] = $storeId;
}
$stmt = $pdo->prepare($recentSql);
$stmt->execute($recentParams);
$recentRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$recent = [
    'd3' => (int)($recentRow['d3'] ?? 0),
    'd5' => (int)($recentRow['d5'] ?? 0),
    'd7' => (int)($recentRow['d7'] ?? 0),
    'last_sold_at' => $recentRow['last_sold_at'] ?? null,
];

success(['data' => ['sales' => $sales, 'recent' => $recent]]);
