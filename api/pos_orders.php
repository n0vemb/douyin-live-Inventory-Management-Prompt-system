<?php
/**
 * pos_orders.php — 后台门店待出库列表（店管/超管/运营）
 * 运营可见列表与金额，但不可见成本/进价
 * ?outbound_status=pending|done|voided|all（默认 pending）
 * 触发惰性超时扫描：pending 超 24h 自动释放锁定并作废
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/pos_auth.php';
requireAuth();
$storeId = getStoreId();
$status = $_GET['outbound_status'] ?? 'pending';
if (!in_array($status, ['pending', 'done', 'voided', 'all'], true)) $status = 'pending';
$pdo = getDB();

try {
    // ---- 超时自动作废（24h）----
    $expireStmt = $pdo->prepare(
        "SELECT id FROM pos_orders
         WHERE outbound_status = 'pending' AND created_at < (NOW() - INTERVAL 24 HOUR)
         ORDER BY id ASC LIMIT 50"
    );
    $expireStmt->execute();
    $expired = $expireStmt->fetchAll();
    if ($expired) {
        foreach ($expired as $ord) {
            $oid = (int)$ord['id'];
            $pdo->beginTransaction();
            try {
                // 释放锁定
                $locks = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
                $locks->execute([$oid, 'locked']);
                $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
                $relLock = $pdo->prepare('UPDATE pos_order_locks SET status = ? WHERE id = ?');
                foreach ($locks->fetchAll() as $lk) {
                    $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
                    $relLock->execute(['released', (int)$lk['id']]);
                }
                $pdo->prepare("UPDATE pos_orders SET outbound_status = 'voided', void_reason = '超时未出库自动作废', completed_at = NOW() WHERE id = ?")
                    ->execute([$oid]);
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                logError($e->getMessage(), 'pos_auto_void', ['order_id' => $oid]);
            }
        }
    }

    // ---- 列表（仅显示已付款订单：收银台未点「已付款」的订单不进待出库）----
    $condNames = conditionNames($pdo, $storeId ?: 1);
    $where = "po.store_id" . ($storeId ? " = " . (int)$storeId : " IS NOT NULL");
    $where .= " AND po.pay_status = 'paid'";
    if ($status !== 'all') $where .= " AND po.outbound_status = '" . $status . "'";
    $orders = $pdo->query(
        "SELECT po.*, (SELECT COUNT(*) FROM pos_order_items pi WHERE pi.order_id = po.id AND pi.status = 'active') AS active_items
         FROM pos_orders po WHERE " . $where . " ORDER BY po.created_at DESC LIMIT 200"
    )->fetchAll();

    $itemStmt = $pdo->prepare(
        "SELECT pi.*, p.name, p.series
         FROM pos_order_items pi LEFT JOIN products p ON p.id = pi.product_id
         WHERE pi.order_id = ? AND pi.status = 'active' ORDER BY pi.id"
    );
    $result = [];
    $hideCost = isOperator(); // 运营不可见成本/进价
    foreach ($orders as $o) {
        $itemStmt->execute([(int)$o['id']]);
        $items = $itemStmt->fetchAll();
        $qty = 0;
        foreach ($items as $it) $qty += (int)$it['qty'];
        $result[] = [
            'id' => (int)$o['id'],
            'order_no' => $o['order_no'],
            'created_at' => $o['created_at'],
            'cashier_name' => $o['cashier_name'],
            'staff_mode' => (int)$o['staff_mode'],
            'staff_discount' => $o['staff_discount'] !== null ? floatval($o['staff_discount']) : null,
            'subtotal' => floatval($o['subtotal']),
            'discount_amount' => floatval($o['discount_amount']),
            'payable' => floatval($o['payable']),
            'pay_method' => $o['pay_method'],
            'pay_status' => $o['pay_status'],
            'paid_at' => $o['paid_at'],
            'outbound_status' => $o['outbound_status'],
            'void_reason' => $o['void_reason'],
            'item_count' => $qty,
            'items' => array_map(function ($it) use ($condNames) {
                return [
                    'id' => (int)$it['id'],
                    'product_id' => (int)$it['product_id'],
                    'name' => $it['name'],
                    'series' => $it['series'],
                    'condition_type' => $it['condition_type'],
                    'cond_name' => $condNames[$it['condition_type']] ?? $it['condition_type'],
                    'qty' => (int)$it['qty'],
                    'unit_price' => floatval($it['unit_price']),
                    'cost_price' => $hideCost ? null : ($it['cost_price'] !== null ? floatval($it['cost_price']) : null),
                    'line_total' => floatval($it['line_total'])
                ];
            }, $items)
        ];
    }

    // ---- 统计（待出库口径）----
    $statWhere = $storeId ? " AND store_id = " . (int)$storeId : "";
    $stat = $pdo->query(
        "SELECT COUNT(*) AS order_count,
                COALESCE(SUM(payable), 0) AS total_payable
         FROM pos_orders WHERE outbound_status = 'pending'" . $statWhere
    )->fetch();
    $totalQty = 0;
    if ((int)$stat['order_count'] > 0) {
        $totalQty = (int)$pdo->query(
            "SELECT COALESCE(SUM(pi.qty), 0) FROM pos_order_items pi
             JOIN pos_orders po ON po.id = pi.order_id
             WHERE po.outbound_status = 'pending' AND pi.status = 'active'" . ($storeId ? " AND po.store_id = " . (int)$storeId : "")
        )->fetchColumn();
    }

    success([
        'orders' => $result,
        'stats' => [
            'order_count' => (int)$stat['order_count'],
            'total_qty' => $totalQty,
            'total_payable' => floatval($stat['total_payable'])
        ]
    ]);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_orders');
    error('加载待出库列表失败: ' . $e->getMessage(), 500);
}
