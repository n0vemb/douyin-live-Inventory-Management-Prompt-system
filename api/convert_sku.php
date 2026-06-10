<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$sourceProductId = (int)($input['source_product_id'] ?? 0);
$sourceConditionType = trim((string)($input['source_condition_type'] ?? ''));
$targetProductId = (int)($input['target_product_id'] ?? 0);
$targetConditionType = trim((string)($input['target_condition_type'] ?? ''));
$qty = (int)($input['qty'] ?? 0);
$remark = trim((string)($input['remark'] ?? '')) ?: 'SKU转换';

if ($sourceProductId <= 0 || $sourceConditionType === '' || $targetProductId <= 0 || $targetConditionType === '') {
    error('请提供完整的来源和目标商品/状态信息');
}

if ($qty <= 0) {
    error('转换数量必须大于0');
}

if ($sourceProductId === $targetProductId && $sourceConditionType === $targetConditionType) {
    error('来源SKU和目标SKU不能相同');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

if (!$storeId) {
    error('请先选择店铺后再进行SKU转换');
}

$pdo->beginTransaction();

try {
    // 1. 校验来源库存
    $stmt = $pdo->prepare('SELECT SUM(remaining_qty) FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$sourceProductId, $sourceConditionType, $storeId]);
    $sourceBeforeQty = (int)$stmt->fetchColumn();

    if ($sourceBeforeQty < $qty) {
        throw new RuntimeException("来源SKU库存不足，当前库存 {$sourceBeforeQty}，需要转换 {$qty}");
    }

    // 2. FIFO 锁定并扣减来源批次
    $stmt = $pdo->prepare('
        SELECT id, remaining_qty, purchase_price, suggested_price
        FROM inventory_batches
        WHERE product_id = ? AND condition_type = ? AND remaining_qty > 0 AND store_id = ?
        ORDER BY purchased_at ASC, id ASC
        FOR UPDATE
    ');
    $stmt->execute([$sourceProductId, $sourceConditionType, $storeId]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $need = $qty;
    $consumedBatches = []; // [{batch_id, qty, purchase_price, suggested_price}]

    foreach ($batches as $batch) {
        if ($need <= 0) break;
        $consume = min($need, (int)$batch['remaining_qty']);

        $update = $pdo->prepare('UPDATE inventory_batches SET remaining_qty = remaining_qty - ? WHERE id = ? AND store_id = ?');
        $update->execute([$consume, (int)$batch['id'], $storeId]);

        $consumedBatches[] = [
            'batch_id' => (int)$batch['id'],
            'qty' => $consume,
            'purchase_price' => (float)$batch['purchase_price'],
            'suggested_price' => (float)$batch['suggested_price'],
        ];

        $need -= $consume;
    }

    // 3. 来源扣减后库存
    $stmt = $pdo->prepare('SELECT SUM(remaining_qty) FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$sourceProductId, $sourceConditionType, $storeId]);
    $sourceAfterQty = (int)$stmt->fetchColumn();

    // 4. 记录 inventory_log（来源扣减）
    $stmt = $pdo->prepare('
        INSERT INTO inventory_log (product_id, condition_type, change_type, qty_change, before_qty, after_qty, remark, store_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$sourceProductId, $sourceConditionType, 'convert_out', -$qty, $sourceBeforeQty, $sourceAfterQty, $remark, $storeId]);

    // 5. 查询目标SKU最新售价（建议售价应反映目标SKU市场价）
    $stmt = $pdo->prepare('
        SELECT suggested_price
        FROM inventory_batches
        WHERE product_id = ? AND condition_type = ? AND store_id = ?
        ORDER BY purchased_at DESC, id DESC
        LIMIT 1
    ');
    $stmt->execute([$targetProductId, $targetConditionType, $storeId]);
    $targetLatest = $stmt->fetch(PDO::FETCH_ASSOC);
    $targetSuggestedPrice = $targetLatest ? (float)$targetLatest['suggested_price'] : null;

    // 6. 目标SKU扣减前库存
    $stmt = $pdo->prepare('SELECT SUM(remaining_qty) FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$targetProductId, $targetConditionType, $storeId]);
    $targetBeforeQty = (int)$stmt->fetchColumn();

    // 7. 创建目标批次（每个来源批次对应一个目标批次，保持进价追溯）
    $batchNoPrefix = 'CNV' . date('YmdHis');
    $createdBatches = [];
    $totalPurchaseValue = 0;

    foreach ($consumedBatches as $cb) {
        $batchNo = $batchNoPrefix . sprintf('%04d', random_int(0, 9999));
        $batchPurchasePrice = $cb['purchase_price'];
        // 建议售价：优先取目标SKU最新售价，否则取来源批次建议售价
        $batchSuggestedPrice = $targetSuggestedPrice ?? $cb['suggested_price'];

        $insert = $pdo->prepare('
            INSERT INTO inventory_batches
            (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark, store_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $insert->execute([
            $targetProductId,
            $targetConditionType,
            $batchNo,
            $batchPurchasePrice,
            $batchSuggestedPrice,
            $cb['qty'],
            $cb['qty'],
            'sku-convert',
            $remark,
            $storeId
        ]);

        $createdBatches[] = [
            'batch_no' => $batchNo,
            'qty' => $cb['qty'],
            'purchase_price' => $batchPurchasePrice,
            'suggested_price' => $batchSuggestedPrice,
        ];

        $totalPurchaseValue += $batchPurchasePrice * $cb['qty'];
    }

    // 8. 目标SKU扣减后库存
    $stmt = $pdo->prepare('SELECT SUM(remaining_qty) FROM inventory_batches WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$targetProductId, $targetConditionType, $storeId]);
    $targetAfterQty = (int)$stmt->fetchColumn();

    // 9. 记录 inventory_log（目标新增）
    $stmt = $pdo->prepare('
        INSERT INTO inventory_log (product_id, condition_type, change_type, qty_change, before_qty, after_qty, remark, store_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$targetProductId, $targetConditionType, 'convert_in', $qty, $targetBeforeQty, $targetAfterQty, $remark, $storeId]);

    // 10. 记录 purchase_log（目标SKU入库记录）
    $avgPurchasePrice = $qty > 0 ? round($totalPurchaseValue / $qty, 2) : 0;
    $stmt = $pdo->prepare('
        INSERT INTO purchase_log (product_id, condition_type, purchase_price, qty, supplier, remark, store_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$targetProductId, $targetConditionType, $avgPurchasePrice, $qty, 'sku-convert', $remark, $storeId]);

    $pdo->commit();

    success([
        'message' => 'SKU转换成功',
        'data' => [
            'source_product_id' => $sourceProductId,
            'source_condition_type' => $sourceConditionType,
            'source_before_qty' => $sourceBeforeQty,
            'source_after_qty' => $sourceAfterQty,
            'target_product_id' => $targetProductId,
            'target_condition_type' => $targetConditionType,
            'target_before_qty' => $targetBeforeQty,
            'target_after_qty' => $targetAfterQty,
            'qty' => $qty,
            'batches_created' => $createdBatches,
        ]
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
