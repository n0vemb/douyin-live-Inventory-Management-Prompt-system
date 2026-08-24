<?php
/**
 * pos_report.php — 线下销售报表（店管/超管，运营隐藏）
 * GET ?from=Y-m-d&to=Y-m-d
 * 销售额 = pay_status='paid' 且非 voided 订单
 * 毛利 = 已出库(done)订单的实际成本快照差额（未出库无成本）
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireNonOperator();
$storeId = getStoreId();
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');
$pdo = getDB();

try {
    $where = "po.created_at >= ? AND po.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $params = [$from . ' 00:00:00', $to . ' 00:00:00'];
    if ($storeId) {
        $where .= " AND po.store_id = ?";
        $params[] = $storeId;
    }

    // 按日：订单数/销售额（仅查 pos_orders，避免 JOIN 放大）
    $salesSql = "SELECT DATE(po.created_at) AS d,
                        COUNT(*) AS order_count,
                        COALESCE(SUM(po.payable),0) AS sales
                 FROM pos_orders po
                 WHERE po.pay_status = 'paid' AND po.outbound_status != 'voided' AND " . $where . "
                 GROUP BY DATE(po.created_at) ORDER BY d";
    $stmt = $pdo->prepare($salesSql);
    $stmt->execute($params);
    $salesRows = [];
    foreach ($stmt->fetchAll() as $r) {
        $salesRows[$r['d']] = [
            'order_count' => (int)$r['order_count'],
            'qty' => 0,
            'sales' => floatval($r['sales']),
            'profit' => 0.0
        ];
    }

    // 件数：单独 JOIN items（active 明细）
    $qtySql = "SELECT DATE(po.created_at) AS d, COALESCE(SUM(pi.qty),0) AS qty
               FROM pos_orders po
               JOIN pos_order_items pi ON pi.order_id = po.id AND pi.status = 'active'
               WHERE po.pay_status = 'paid' AND po.outbound_status != 'voided' AND " . $where . "
               GROUP BY DATE(po.created_at)";
    $stmt = $pdo->prepare($qtySql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        if (isset($salesRows[$r['d']])) $salesRows[$r['d']]['qty'] = (int)$r['qty'];
    }

    // 毛利：done 订单（有成本快照）
    $profitSql = "SELECT DATE(po.created_at) AS d,
                         COALESCE(SUM(pi.line_total - pi.cost_price * pi.qty),0) AS profit
                  FROM pos_order_items pi
                  JOIN pos_orders po ON po.id = pi.order_id
                  WHERE po.outbound_status = 'done' AND pi.status = 'active' AND pi.cost_price IS NOT NULL AND " . $where . "
                  GROUP BY DATE(po.created_at)";
    $stmt = $pdo->prepare($profitSql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) {
        if (isset($salesRows[$r['d']])) $salesRows[$r['d']]['profit'] = floatval($r['profit']);
    }

    // 支付方式拆分
    $paySql = "SELECT DATE(po.created_at) AS d, po.pay_method, COUNT(*) AS cnt, COALESCE(SUM(po.payable),0) AS amt
               FROM pos_orders po
               WHERE po.pay_status = 'paid' AND po.outbound_status != 'voided' AND " . $where . "
               GROUP BY DATE(po.created_at), po.pay_method";
    $stmt = $pdo->prepare($paySql);
    $stmt->execute($params);
    $paySplit = [];
    foreach ($stmt->fetchAll() as $r) {
        if (!isset($paySplit[$r['d']])) $paySplit[$r['d']] = ['cash_orders' => 0, 'cash_sales' => 0, 'scan_orders' => 0, 'scan_sales' => 0];
        if ($r['pay_method'] === 'cash') {
            $paySplit[$r['d']]['cash_orders'] = (int)$r['cnt'];
            $paySplit[$r['d']]['cash_sales'] = floatval($r['amt']);
        } else {
            $paySplit[$r['d']]['scan_orders'] = (int)$r['cnt'];
            $paySplit[$r['d']]['scan_sales'] = floatval($r['amt']);
        }
    }

    $rows = [];
    foreach ($salesRows as $d => $v) {
        $rows[] = array_merge(['date' => $d], $v, $paySplit[$d] ?? ['cash_orders' => 0, 'cash_sales' => 0, 'scan_orders' => 0, 'scan_sales' => 0]);
    }

    // 合计
    $total = ['order_count' => 0, 'qty' => 0, 'sales' => 0, 'profit' => 0, 'cash_orders' => 0, 'cash_sales' => 0, 'scan_orders' => 0, 'scan_sales' => 0];
    foreach ($rows as $r) {
        $total['order_count'] += $r['order_count'];
        $total['qty'] += $r['qty'];
        $total['sales'] += $r['sales'];
        $total['profit'] += $r['profit'];
        $total['cash_orders'] += $r['cash_orders'];
        $total['cash_sales'] += $r['cash_sales'];
        $total['scan_orders'] += $r['scan_orders'];
        $total['scan_sales'] += $r['scan_sales'];
    }

    success(['rows' => $rows, 'total' => $total, 'from' => $from, 'to' => $to]);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_report');
    error('报表加载失败: ' . $e->getMessage(), 500);
}
