<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = (int)($input['product_id'] ?? 0);
$conditionType = trim((string)($input['condition_type'] ?? ''));
$adjustQty = (int)($input['adjust_qty'] ?? 0);
$remark = $input['remark'] ?? '手动库存调整';

if ($productId <= 0 || $conditionType === '') {
    error('请提供有效的商品ID和状态类型');
}

if ($adjustQty === 0) {
    error('调整数量不能为0');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
$pdo->beginTransaction();

try {

    $stmt = $pdo->prepare('SELECT SUM(remaining_qty) FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$productId, $conditionType, $storeId]);
    $currentStock = (int)$stmt->fetchColumn();

    if ($adjustQty < 0) {
        $need = abs($adjustQty);
        if ($currentStock < $need) {
            throw new RuntimeException('库存不足，无法减少这么多数量');
        }

        $stmt = $pdo->prepare('
            SELECT id, remaining_qty
            FROM inventory_batches
            WHERE product_id = ? AND condition_type = ? AND remaining_qty > 0 AND store_id = ?
            ORDER BY purchased_at ASC, id ASC
        ');
        $stmt->execute([$productId, $conditionType, $storeId]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($batches as $batch) {
            if ($need <= 0) {
                break;
            }
            $consume = min($need, (int)$batch['remaining_qty']);
            $update = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = remaining_qty - ? WHERE id = ? AND store_id = ?');
            $update->execute([$consume, (int)$batch['id'], $storeId]);
            $need -= $consume;
        }
    } else {
        $stmt = $pdo->prepare('
            SELECT purchase_price, suggested_price
            FROM inventory_batches
            WHERE product_id = ? AND condition_type = ? AND store_id = ?
            ORDER BY purchased_at DESC, id DESC
            LIMIT 1
        ');
        $stmt->execute([$productId, $conditionType, $storeId]);
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$latest) {
            throw new RuntimeException('当前状态没有历史批次，请先走入库流程');
        }

        $batchNo = 'ADJ' . date('YmdHis') . sprintf('%04d', random_int(0, 9999));
        $insert = $pdo->prepare('
            INSERT INTO inventory_batches
            (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark, store_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([
            $productId,
            $conditionType,
            $batchNo,
            $latest['purchase_price'],
            $latest['suggested_price'],
            $adjustQty,
            $adjustQty,
            'manual-adjust',
            $remark,
            $storeId
        ]);
    }

    $stmt = $pdo->prepare('SELECT SUM(remaining_qty) FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$productId, $conditionType, $storeId]);
    $newStock = (int)$stmt->fetchColumn();

    $pdo->commit();
    success([
        'message' => '库存调整成功',
        'data' => [
            'product_id' => $productId,
            'condition_type' => $conditionType,
            'adjust_qty' => $adjustQty,
            'before_stock' => $currentStock,
            'after_stock' => $newStock
        ]
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
