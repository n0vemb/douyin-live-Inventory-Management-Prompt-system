<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
    error('请提供盘点数据');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}
$operatorId = $_SESSION['user_id'] ?? null;
$pdo->beginTransaction();

try {
    $updated = 0;
    $created = 0;

    foreach ($input['items'] as $item) {
        $productId = intval($item['product_id'] ?? 0);
        $conditionType = $item['condition_type'] ?? '';
        $qty = intval($item['qty'] ?? 0);
        $purchasePrice = isset($item['purchase_price']) && $item['purchase_price'] !== '' && $item['purchase_price'] !== null ? decimal($item['purchase_price']) : null;
        $suggestedPrice = isset($item['suggested_price']) && $item['suggested_price'] !== '' && $item['suggested_price'] !== null ? decimal($item['suggested_price']) : null;

        if ($productId <= 0 || empty($conditionType)) continue;

        // 该商品该状态的全部批次（可能多个，盘点以「商品+状态」为粒度合并成一条流水）
        $stmt = $pdo->prepare("
            SELECT id, remaining_qty, purchase_price, suggested_price
            FROM inventory_batches
            WHERE product_id = ? AND condition_type = ? AND store_id = ?
            ORDER BY purchased_at DESC, id DESC
        ");
        $stmt->execute([$productId, $conditionType, $storeId]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($batches)) {
            $main = $batches[0]; // 最新批次
            $currentTotalQty = 0;
            foreach ($batches as $b) $currentTotalQty += (int)$b['remaining_qty'];
            $diff = $qty - $currentTotalQty;

            // 主批次设为盘点数量
            $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = ? WHERE id = ? AND store_id = ?");
            $stmt->execute([$qty, $main['id'], $storeId]);

            // 其余批次清零（不逐批写流水，合并进一条盘点流水，避免一次操作多条流水）
            foreach (array_slice($batches, 1) as $ob) {
                $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = 0 WHERE id = ? AND store_id = ?");
                $stmt->execute([(int)$ob['id'], $storeId]);
            }

            // 数量有变化 → 只写一条盘点调整流水（含操作人）
            if ($diff !== 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_log (store_id, user_id, product_id, condition_type, change_type, qty_change, before_qty, after_qty, price, remark)
                    VALUES (?, ?, ?, ?, 'adjust', ?, ?, ?, ?, '盘点调整')
                ");
                $stmt->execute([$storeId, $operatorId, $productId, $conditionType, $diff, $currentTotalQty, $qty, $purchasePrice ?? null]);
                $updated++;
            }

            // 更新价格（仅超管界面才会传价格；前端盘点保存只传 qty，这里保留能力）
            $updateFields = [];
            $updateParams = [];
            if ($purchasePrice !== null && $purchasePrice != $main['purchase_price']) {
                $updateFields[] = 'purchase_price = ?';
                $updateParams[] = $purchasePrice;
            }
            if ($suggestedPrice !== null && $suggestedPrice != $main['suggested_price']) {
                $updateFields[] = 'suggested_price = ?';
                $updateParams[] = $suggestedPrice;
            }
            if (!empty($updateFields)) {
                $updateParams[] = $main['id'];
                $updateParams[] = $storeId;
                $stmt = $pdo->prepare("UPDATE inventory_batches SET " . implode(', ', $updateFields) . " WHERE id = ? AND store_id = ?");
                $stmt->execute($updateParams);
                if ($diff === 0) $updated++;
            }
        } elseif ($qty > 0) {
            // 不存在批次但 qty > 0 → 创建新批次（一条入库流水 + 一条采购记录）
            $batchNo = 'PD' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            $stmt = $pdo->prepare("
                INSERT INTO inventory_batches (product_id, condition_type, batch_no, total_qty, remaining_qty, purchase_price, suggested_price, purchased_at, remark, store_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), '盘点新增', ?)
            ");
            $stmt->execute([
                $productId, $conditionType, $batchNo, $qty, $qty,
                $purchasePrice ?? 0, $suggestedPrice ?? 0, $storeId
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO inventory_log (store_id, user_id, product_id, condition_type, change_type, qty_change, before_qty, after_qty, price, remark)
                VALUES (?, ?, ?, ?, 'purchase', ?, 0, ?, ?, '盘点新增')
            ");
            $stmt->execute([$storeId, $operatorId, $productId, $conditionType, $qty, $qty, $purchasePrice ?? null]);

            $stmt = $pdo->prepare("
                INSERT INTO purchase_log (product_id, condition_type, purchase_price, qty, supplier, remark, store_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$productId, $conditionType, $purchasePrice ?? 0, $qty, null, '盘点新增', $storeId]);

            $created++;
        }
    }

    $pdo->commit();

    success([
        'data' => [
            'message' => "盘点更新完成：{$updated} 条更新，{$created} 条新增",
            'updated' => $updated,
            'created' => $created,
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    // 超管未选店铺时 store_id 为 null，友好提示
    if (strpos($e->getMessage(), 'store_id') !== false) {
        error('请先选择店铺后再操作');
    }
    error('盘点更新失败: ' . $e->getMessage());
}
