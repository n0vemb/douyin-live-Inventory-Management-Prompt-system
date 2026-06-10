<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => '请使用POST方法']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $batchNo = $input['batch_no'] ?? null;

    if (empty($batchNo)) {
        echo json_encode(['success' => false, 'error' => '缺少必要参数']);
        exit;
    }

    $pdo = getDB();
requireAuth(); $storeId = getStoreId();

    // 开始事务
    $pdo->beginTransaction();

    // 1. 找出该出库批次的所有记录（已按 store_id 过滤）
    $stmt = $pdo->prepare("
        SELECT
            o.id as outbound_id,
            o.batch_id,
            o.qty,
            o.product_id
        FROM outbound_log o
        WHERE o.outbound_batch_no = ?" . ($storeId ? " AND o.store_id = ?" : "") . "
    ");
    $stmt->execute($storeId ? [$batchNo, $storeId] : [$batchNo]);
    $outbounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($outbounds)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => '未找到该出库记录']);
        exit;
    }

    // 2. 恢复库存
    foreach ($outbounds as $outbound) {
        $updateStmt = $pdo->prepare("
            UPDATE inventory_batches
            SET remaining_qty = remaining_qty + ?
            WHERE id = ?
        ");
        $updateStmt->execute([
            $outbound['qty'],
            $outbound['batch_id']
        ]);
    }

    // 3. 删除出库记录
    $deleteStmt = $pdo->prepare("
        DELETE FROM outbound_log
        WHERE outbound_batch_no = ?" . ($storeId ? " AND store_id = ?" : "") . "
    ");
    $deleteStmt->execute($storeId ? [$batchNo, $storeId] : [$batchNo]);

    // 4. 删除财务数据
    $deleteFinanceStmt = $pdo->prepare("
        DELETE FROM outbound_finance
        WHERE outbound_batch_no = ?" . ($storeId ? " AND store_id = ?" : "") . "
    ");
    $deleteFinanceStmt->execute($storeId ? [$batchNo, $storeId] : [$batchNo]);

    // 提交事务
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'deleted_count' => count($outbounds),
            'message' => '删除成功'
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '删除失败: ' . $e->getMessage()
    ]);
}
