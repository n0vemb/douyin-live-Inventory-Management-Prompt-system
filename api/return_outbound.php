<?php
/**
 * 退单 API
 * 
 * 从已出库记录中退还商品回库存
 * 影响：库存恢复，出库记录的成本/毛利重算
 * 不影响：GMV / 订单数 / 投流
 * 
 * POST: { outbound_log_id: int, batch_id: int, qty: int }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    jsonResponse(['success' => false, 'error' => '请使用POST方法']);
}

$input = json_decode(file_get_contents('php://input'), true);
$logId = intval($input['outbound_log_id'] ?? 0);
$batchId = intval($input['batch_id'] ?? 0);
$qty = intval($input['qty'] ?? 0);

if ($logId <= 0 || $batchId <= 0 || $qty <= 0) {
    error('参数无效：需要 outbound_log_id, batch_id, qty');
}

$pdo = getDB();
requireAuth();
$storeId = getStoreId();

$pdo->beginTransaction();

try {
    // 1. 获取出库记录（包含 store_id 过滤）
    $logStmt = $pdo->prepare("
        SELECT o.*, b.purchase_price
        FROM outbound_log o
        JOIN inventory_batches b ON o.batch_id = b.id
        WHERE o.id = ? AND o.batch_id = ?" . ($storeId ? " AND o.store_id = ?" : "") . "
        FOR UPDATE
    ");
    $params = [$logId, $batchId];
    if ($storeId) $params[] = $storeId;
    $logStmt->execute($params);
    $log = $logStmt->fetch();

    if (!$log) {
        throw new Exception('出库记录不存在');
    }

    // 2. 验证可退数量
    $actualOut = $log['qty'] - $log['returned_qty'];
    if ($qty > $actualOut) {
        throw new Exception("最多可退 {$actualOut} 件（已退 {$log['returned_qty']} / 原出库 {$log['qty']}）");
    }

    // 3. 增加库存
    $updateBatch = $pdo->prepare("
        UPDATE inventory_batches SET remaining_qty = remaining_qty + ? WHERE id = ?" . ($storeId ? " AND store_id = ?" : "") . "
    ");
    $bParams = [$qty, $batchId];
    if ($storeId) $bParams[] = $storeId;
    $updateBatch->execute($bParams);

    // 4. 更新出库记录的退货数
    $updateLog = $pdo->prepare("
        UPDATE outbound_log SET returned_qty = returned_qty + ? WHERE id = ?
    ");
    $updateLog->execute([$qty, $logId]);

    $pdo->commit();

    success([
        'data' => [
            'outbound_log_id' => $logId,
            'returned_qty' => $qty,
            'total_returned' => $log['returned_qty'] + $qty,
            'remaining_out' => $log['qty'] - ($log['returned_qty'] + $qty),
            'message' => "退单成功，{$qty} 件已退回库存"
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
