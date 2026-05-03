<?php
require_once __DIR__ . '/../includes/common.php';

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

    // 开始事务
    $pdo->beginTransaction();

    // 1. 找出该出库批次的所有记录
    $stmt = $pdo->prepare("
        SELECT 
            o.id as outbound_id,
            o.batch_id,
            o.qty
        FROM outbound o
        WHERE o.batch_no = ?
    ");
    $stmt->execute([$batchNo]);
    $outbounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($outbounds)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => '未找到该出库记录']);
        exit;
    }

    // 2. 恢复库存
    foreach ($outbounds as $outbound) {
        $updateStmt = $pdo->prepare("
            UPDATE purchase_logs
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
        DELETE FROM outbound
        WHERE batch_no = ?
    ");
    $deleteStmt->execute([$batchNo]);

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
