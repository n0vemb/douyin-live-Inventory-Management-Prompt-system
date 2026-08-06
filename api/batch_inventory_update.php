<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
    error('请提供盘点数据');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
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

        // 查找该商品该状态的最新批次
        $stmt = $pdo->prepare("
            SELECT id, remaining_qty, purchase_price, suggested_price
            FROM inventory_batches
            WHERE product_id = ? AND condition_type = ? AND store_id = ?
            ORDER BY purchased_at DESC
            LIMIT 1
        ");
        $stmt->execute([$productId, $conditionType, $storeId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($batch) {
            $currentQty = intval($batch['remaining_qty']);
            $diff = $qty - $currentQty;

            // 清零同商品同状态的其他批次，避免库存重复计算
            // 必须放在 if($diff) 外面：即使 qty 恰好等于最新批次的库存，
            // 其他批次也可能有剩余库存需要清零
            $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = 0 WHERE product_id = ? AND condition_type = ? AND store_id = ? AND id != ?");
            $stmt->execute([$productId, $conditionType, $storeId, $batch['id']]);

            // 更新数量
            if ($diff !== 0) {
                $beforeQty = $currentQty;
                $afterQty = $qty;

                $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = ? WHERE id = ? AND store_id = ?");
                $stmt->execute([$qty, $batch['id'], $storeId]);

                // 记录调整日志
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_log (product_id, condition_type, change_type, qty_change, before_qty, after_qty, remark, store_id)
                    VALUES (?, ?, 'adjust', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$productId, $conditionType, $diff, $beforeQty, $afterQty, '盘点调整', $storeId]);

                $updated++;
            }

            // 更新价格
            $updateFields = [];
            $updateParams = [];
            if ($purchasePrice !== null && $purchasePrice != $batch['purchase_price']) {
                $updateFields[] = 'purchase_price = ?';
                $updateParams[] = $purchasePrice;
            }
            if ($suggestedPrice !== null && $suggestedPrice != $batch['suggested_price']) {
                $updateFields[] = 'suggested_price = ?';
                $updateParams[] = $suggestedPrice;
            }
            if (!empty($updateFields)) {
                $updateParams[] = $batch['id'];
                $updateParams[] = $storeId;
                $stmt = $pdo->prepare("UPDATE inventory_batches SET " . implode(', ', $updateFields) . " WHERE id = ? AND store_id = ?");
                $stmt->execute($updateParams);
                if ($diff === 0) $updated++;
            }
        } elseif ($qty > 0) {
            // 不存在批次但 qty > 0 → 创建新批次
            $batchNo = 'PD' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            $stmt = $pdo->prepare("
                INSERT INTO inventory_batches (product_id, condition_type, batch_no, total_qty, remaining_qty, purchase_price, suggested_price, purchased_at, remark, store_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), '盘点新增', ?)
            ");
            $stmt->execute([
                $productId, $conditionType, $batchNo, $qty, $qty,
                $purchasePrice ?? 0, $suggestedPrice ?? 0, $storeId
            ]);

            // 记录入库日志
            $stmt = $pdo->prepare("
                INSERT INTO inventory_log (product_id, condition_type, change_type, qty_change, before_qty, after_qty, remark, store_id)
                VALUES (?, ?, 'purchase', ?, 0, ?, ?, ?)
            ");
            $stmt->execute([$productId, $conditionType, $qty, $qty, '盘点新增', $storeId]);

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
    error('盘点更新失败: ' . $e->getMessage());
}
