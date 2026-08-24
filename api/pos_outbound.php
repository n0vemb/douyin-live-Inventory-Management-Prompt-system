<?php
/**
 * pos_outbound.php — 门店待出库订单操作（店管/超管/运营）
 * action=outbound     出库：按锁定批次扣库存，回填进价快照，置 done
 * action=void         整单作废（=已退款）：释放全部锁定，置 voided
 * action=void_item    单品删除：释放该品锁定，item→voided，重算订单金额
 * action=delete_order 彻底删除订单：释放锁定，物理删除（仅店管/超管）
 * 运营可出库/作废/单品删；删除订单仅店管/超管
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireAuth();
$storeId = getStoreId();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$orderId = intval($input['order_id'] ?? 0);
$itemId = intval($input['item_id'] ?? 0);
if (!$orderId) error('缺少订单ID');
$pdo = getDB();

try {
    // 取订单（店铺隔离）
    $orderStmt = $pdo->prepare('SELECT * FROM pos_orders WHERE id = ?');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order) error('订单不存在');
    if ($storeId && (int)$order['store_id'] !== $storeId) error('订单不属于当前店铺', 403);

    if ($action === 'outbound') {
        if ($order['outbound_status'] !== 'pending') error('仅待出库订单可出库');
        if ($order['pay_status'] !== 'paid') error('订单未收款，不可出库');

        $pdo->beginTransaction();
        try {
            // 锁定批次 → 扣库存
            $lockStmt = $pdo->prepare('SELECT * FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
            $lockStmt->execute([$orderId, 'locked']);
            $locks = $lockStmt->fetchAll();
            if (!$locks) throw new Exception('订单无锁定库存，可能已释放');

            $deductBatch = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = remaining_qty - ?, locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
            $setLockDone = $pdo->prepare("UPDATE pos_order_locks SET status = 'deducted' WHERE id = ?");
            // 写 outbound_log（商品库存流水）：按锁定批次一条，remark 带线下订单号，可追溯
            $insLog = $pdo->prepare(
                "INSERT INTO outbound_log (batch_id, product_id, condition_type, qty, outbound_price, order_no, outbound_batch_no, remark, platform, account, live_session_id, store_id, shipping_fee, operator_username)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, '线下订单出库', 'pos', NULL, NULL, ?, NULL, ?)"
            );
            // 订单明细 map（unit_price）
            $itemMap = [];
            $itStmt = $pdo->prepare("SELECT id, product_id, condition_type, unit_price FROM pos_order_items WHERE order_id = ? AND status = 'active'");
            $itStmt->execute([$orderId]);
            foreach ($itStmt->fetchAll() as $it) $itemMap[(int)$it['id']] = $it;

            // 按 item 归集实际成本（加权）
            $costByItem = [];
            foreach ($locks as $lk) {
                $bid = (int)$lk['batch_id'];
                $bStmt = $pdo->prepare('SELECT * FROM inventory_batches WHERE id = ? FOR UPDATE');
                $bStmt->execute([$bid]);
                $b = $bStmt->fetch();
                if (!$b) throw new Exception('锁定批次不存在，数据异常');
                if ((int)$b['remaining_qty'] < (int)$lk['qty']) throw new Exception('批次库存不足，可能已被其他订单占用');
                $deductBatch->execute([(int)$lk['qty'], (int)$lk['qty'], $bid]);
                $setLockDone->execute([(int)$lk['id']]);
                $oid = (int)$lk['order_item_id'];
                if (!isset($costByItem[$oid])) $costByItem[$oid] = ['cost' => 0, 'qty' => 0];
                $costByItem[$oid]['cost'] += (float)$b['purchase_price'] * (int)$lk['qty'];
                $costByItem[$oid]['qty'] += (int)$lk['qty'];
                // 库存流水：售价取明细单价
                $unitPrice = isset($itemMap[$oid]) ? (float)$itemMap[$oid]['unit_price'] : 0;
                $insLog->execute([
                    $bid,
                    (int)$b['product_id'],
                    $b['condition_type'],
                    (int)$lk['qty'],
                    $unitPrice,
                    $order['order_no'],
                    (int)$order['store_id'],
                    $_SESSION['username'] ?? null
                ]);
            }
            // 回填进价快照
            $setCost = $pdo->prepare('UPDATE pos_order_items SET cost_price = ? WHERE id = ?');
            foreach ($costByItem as $oid => $c) {
                $setCost->execute([round($c['cost'] / $c['qty'], 2), $oid]);
            }
            $pdo->prepare("UPDATE pos_orders SET outbound_status = 'done', completed_at = NOW() WHERE id = ?")->execute([$orderId]);
            $pdo->commit();
            success(['outbound_status' => 'done']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'void') {
        if ($order['outbound_status'] !== 'pending') error('仅待出库订单可作废');
        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
            $lockStmt->execute([$orderId, 'locked']);
            $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
            $relLock = $pdo->prepare("UPDATE pos_order_locks SET status = 'released' WHERE id = ?");
            foreach ($lockStmt->fetchAll() as $lk) {
                $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
                $relLock->execute([(int)$lk['id']]);
            }
            $reason = trim($input['reason'] ?? '整单作废（已退款）');
            $pdo->prepare("UPDATE pos_orders SET outbound_status = 'voided', void_reason = ?, completed_at = NOW() WHERE id = ?")
                ->execute([$reason, $orderId]);
            $pdo->commit();
            success(['outbound_status' => 'voided']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'void_item') {
        if (!$itemId) error('缺少商品ID');
        if ($order['outbound_status'] !== 'pending') error('仅待出库订单可删除商品');
        $pdo->beginTransaction();
        try {
            // item 归属校验
            $itemStmt = $pdo->prepare('SELECT id, order_id FROM pos_order_items WHERE id = ? AND order_id = ?');
            $itemStmt->execute([$itemId, $orderId]);
            if (!$itemStmt->fetch()) throw new Exception('商品不属于该订单');
            // 释放该 item 的锁定
            $lockStmt = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_item_id = ? AND status = ? FOR UPDATE');
            $lockStmt->execute([$itemId, 'locked']);
            $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
            $relLock = $pdo->prepare("UPDATE pos_order_locks SET status = 'released' WHERE id = ?");
            foreach ($lockStmt->fetchAll() as $lk) {
                $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
                $relLock->execute([(int)$lk['id']]);
            }
            $pdo->prepare("UPDATE pos_order_items SET status = 'voided' WHERE id = ?")->execute([$itemId]);

            // 重算订单金额（仅 active items）
            $active = $pdo->prepare("SELECT id, line_total FROM pos_order_items WHERE order_id = ? AND status = 'active'");
            $active->execute([$orderId]);
            $actives = $active->fetchAll();
            if (!$actives) {
                // 全部删空 → 自动作废整单
                $pdo->prepare("UPDATE pos_orders SET outbound_status = 'voided', void_reason = '商品全部删除', completed_at = NOW() WHERE id = ?")->execute([$orderId]);
            } else {
                $subtotal = 0;
                foreach ($actives as $a) $subtotal += (float)$a['line_total'];
                $disc = $order['staff_discount'] !== null ? floatval($order['staff_discount']) : 1;
                $discountAmount = $disc < 1 ? round($subtotal * (1 - $disc), 2) : 0;
                $payable = round($subtotal - $discountAmount, 2);
                $pdo->prepare('UPDATE pos_orders SET subtotal = ?, discount_amount = ?, payable = ? WHERE id = ?')
                    ->execute([round($subtotal, 2), $discountAmount, $payable, $orderId]);
            }
            $pdo->commit();
            success(['status' => 'ok']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'delete_order') {
        // 彻底删除（仅店管/超管，运营无权限）
        if (isOperator()) error('权限不足：运营账号不可删除订单', 403);
        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
            $lockStmt->execute([$orderId, 'locked']);
            $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
            foreach ($lockStmt->fetchAll() as $lk) {
                $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
            }
            $pdo->prepare('DELETE FROM pos_orders WHERE id = ?')->execute([$orderId]); // 级联删 items/locks
            $pdo->commit();
            success(['deleted' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } else {
        error('未知操作');
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_outbound', ['action' => $action, 'order_id' => $orderId]);
    error($e->getMessage(), 400);
}
