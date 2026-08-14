<?php
/**
 * live_ledger_history.php — 历史反查
 * GET 参数：
 *   start_date, end_date — 日期范围（按场次 created_at）
 *   session_id — 指定场次
 *   nickname / vip_no — 客户筛选
 *   activity_type — 活动类型
 *   keyword — 商品名模糊
 *   view = session | customer | product — 三种视图
 *   limit
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/live_ledger_common.php';
require_once __DIR__ . '/permission_helper.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$nickname = trim($_GET['nickname'] ?? '');
$vipNo = trim($_GET['vip_no'] ?? '');
$activityType = $_GET['activity_type'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');
$view = $_GET['view'] ?? 'session';
$limit = isset($_GET['limit']) ? min(200, (int)$_GET['limit']) : 100;

// ===== 基础查询：场次列表（含汇总） =====
$sql = "SELECT ls.* FROM live_ledger_session ls WHERE 1=1";
$params = [];
if ($storeId) { $sql .= " AND ls.store_id = ?"; $params[] = $storeId; }
if ($startDate) { $sql .= " AND ls.created_at >= ?"; $params[] = $startDate . ' 00:00:00'; }
if ($endDate) { $sql .= " AND ls.created_at <= ?"; $params[] = $endDate . ' 23:59:59'; }
if ($sessionId > 0) { $sql .= " AND ls.id = ?"; $params[] = $sessionId; }
if ($activityType) { $sql .= " AND ls.activity_type = ?"; $params[] = $activityType; }
$sql .= " ORDER BY ls.created_at DESC LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== 视图1：按场次（快照汇总） =====
if ($view === 'session') {
    foreach ($sessions as &$s) {
        $s['totals'] = json_decode($s['snapshot_json'] ?? '', true)['totals'] ?? [
            'customers' => (int)$s['total_customers'],
            'qty' => (int)$s['total_qty'],
            'gmv' => floatval($s['total_gmv']),
            'cost' => floatval($s['total_cost']),
            'profit_base' => floatval($s['total_profit_base']),
            'profit_with_gift' => floatval($s['total_profit_with_gift']),
            'profit_with_reduce' => floatval($s['total_profit_with_reduce']),
            'profit_both' => floatval($s['total_profit_both']),
            'gift_cost' => floatval($s['total_gift_cost']),
            'reduce_amount' => floatval($s['total_reduce_amount']),
        ];
        $s['activity_label'] = ['none' => '无活动', 'full_gift' => '满赠', 'full_reduce' => '满减', 'both' => '满减+满赠'][$s['activity_type']] ?? $s['activity_type'];
    }
    if (shouldMaskProfit()) {
        foreach ($sessions as &$sess) {
            foreach (['total_cost','total_profit_base','total_gift_cost','total_profit_with_gift','total_profit_with_reduce','total_profit_both'] as $kk) {
                if (isset($sess[$kk])) $sess[$kk] = null;
            }
            if (isset($sess['totals']) && is_array($sess['totals'])) {
                foreach (['cost','profit_base','profit_base_rate','gift_cost','profit_with_gift','profit_with_gift_rate','profit_with_reduce','profit_with_reduce_rate','profit_both','profit_both_rate'] as $kk) {
                    if (isset($sess['totals'][$kk])) $sess['totals'][$kk] = null;
                }
            }
        }
    }
    success(['data' => ['view' => 'session', 'sessions' => $sessions]]);
}

// ===== 视图2：按客户（跨场次聚合客户明细） =====
if ($view === 'customer') {
    $sessionIds = array_column($sessions, 'id');
    $result = [];
    if (!empty($sessionIds)) {
        $ph = implode(',', array_fill(0, count($sessionIds), '?'));
        $sql = "SELECT lc.*, ls.session_name, ls.created_at as session_date, ls.activity_type, ls.snapshot_json
                FROM live_ledger_customer lc
                JOIN live_ledger_session ls ON lc.session_id = ls.id
                WHERE lc.session_id IN ($ph)";
        $params = $sessionIds;
        if ($nickname) { $sql .= " AND lc.nickname LIKE ?"; $params[] = '%' . $nickname . '%'; }
        if ($vipNo) { $sql .= " AND lc.vip_no LIKE ?"; $params[] = '%' . $vipNo . '%'; }
        $sql .= " ORDER BY lc.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($customers as &$c) {
            $snap = json_decode($c['snapshot_json'] ?? '', true);
            // 优先用快照里的 metrics（历史不变）
            $found = null;
            if ($snap) {
                foreach ($snap['customers'] ?? [] as $sc) {
                    if ($sc['nickname'] === $c['nickname'] && $sc['vip_no'] === $c['vip_no']) {
                        $found = $sc;
                        break;
                    }
                }
            }
            if ($found) {
                $c['metrics'] = $found['metrics'];
                $c['snapshot_items'] = $found['items'];
                $c['snapshot_gifts'] = $found['gifts'];
            } else {
                // 无快照则实时算
                $stmt2 = $pdo->prepare("SELECT * FROM live_ledger_item WHERE customer_id = ?");
                $stmt2->execute([$c['id']]);
                $c['snapshot_items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                $stmt2 = $pdo->prepare("SELECT * FROM live_ledger_gift WHERE customer_id = ?");
                $stmt2->execute([$c['id']]);
                $c['snapshot_gifts'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                $settings = ledgerGetSettings($pdo, $c['session_id']);
                $c['metrics'] = ledgerCalcCustomer(['items' => $c['snapshot_items'], 'gifts' => $c['snapshot_gifts']], $settings);
            }
            unset($c['snapshot_json']);
        }
        if (shouldMaskProfit()) {
            foreach ($customers as &$cc) {
                if (isset($cc['metrics']) && is_array($cc['metrics'])) {
                    foreach (['cost','profit_base','profit_base_rate','gift_cost','profit_with_gift','profit_with_gift_rate','profit_with_reduce','profit_with_reduce_rate','profit_both','profit_both_rate'] as $kk) {
                        if (isset($cc['metrics'][$kk])) $cc['metrics'][$kk] = null;
                    }
                }
                if (isset($cc['snapshot_items']) && is_array($cc['snapshot_items'])) {
                    foreach ($cc['snapshot_items'] as &$it) { if (isset($it['purchase_cost'])) $it['purchase_cost'] = null; }
                }
                if (isset($cc['snapshot_gifts']) && is_array($cc['snapshot_gifts'])) {
                    foreach ($cc['snapshot_gifts'] as &$gg) { if (isset($gg['cost'])) $gg['cost'] = null; }
                }
            }
        }
        $result = $customers;
    }
    success(['data' => ['view' => 'customer', 'customers' => $result]]);
}

// ===== 视图3：按商品（跨场次聚合） =====
if ($view === 'product') {
    $sessionIds = array_column($sessions, 'id');
    $result = [];
    if (!empty($sessionIds)) {
        $ph = implode(',', array_fill(0, count($sessionIds), '?'));
        $sql = "SELECT li.product_id, li.product_name, li.qty, li.sell_price, li.purchase_cost, li.is_gift, ls.created_at as session_date
                FROM live_ledger_item li
                JOIN live_ledger_session ls ON li.session_id = ls.id
                WHERE li.session_id IN ($ph)";
        $params = $sessionIds;
        if ($keyword) { $sql .= " AND li.product_name LIKE ?"; $params[] = '%' . $keyword . '%'; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $agg = [];
        foreach ($items as $it) {
            $key = $it['product_id'];
            if (!isset($agg[$key])) {
                $agg[$key] = ['product_id' => $key, 'product_name' => $it['product_name'], 'qty' => 0, 'gmv' => 0.0, 'cost' => 0.0, 'profit' => 0.0];
            }
            if ($it['is_gift']) continue;
            $agg[$key]['qty'] += (int)$it['qty'];
            $agg[$key]['gmv'] += floatval($it['sell_price']) * $it['qty'];
            $agg[$key]['cost'] += floatval($it['purchase_cost']) * $it['qty'];
            $agg[$key]['profit'] = $agg[$key]['gmv'] - $agg[$key]['cost'];
        }
        foreach ($agg as &$a) {
            $a['gmv'] = round($a['gmv'], 2);
            $a['cost'] = round($a['cost'], 2);
            $a['profit'] = round($a['profit'], 2);
            $a['profit_rate'] = $a['gmv'] > 0 ? round($a['profit'] / $a['gmv'], 4) : 0;
            // 当前库存：该商品所有 SKU 批次 remaining_qty 之和（按店铺过滤）
            $st = $pdo->prepare("SELECT COALESCE(SUM(ib.remaining_qty), 0) FROM inventory_batches ib WHERE ib.product_id = ?" . ($storeId ? " AND ib.store_id = ?" : ""));
            $stParams = $storeId ? [$a['product_id'], $storeId] : [$a['product_id']];
            $st->execute($stParams);
            $a['stock'] = (int)$st->fetchColumn();
        }
        if (shouldMaskProfit()) {
            foreach ($agg as &$aa) { $aa['cost'] = null; $aa['profit'] = null; $aa['profit_rate'] = null; }
        }
        $result = array_values($agg);
        usort($result, fn($a, $b) => $b['qty'] - $a['qty']);
    }
    success(['data' => ['view' => 'product', 'products' => $result]]);
}

error('未知视图');
