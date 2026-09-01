<?php
// 盘点批量调整：逐条独立处理（成功/失败分别返回），支持自定义备注写入 inventory_log
// 与工具箱 /api/audit/adjust 行为一致：单条失败不影响其他条目
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
$remark = trim((string)($input['remark'] ?? '')) ?: '盘点调整';

$results = [];
$successCount = 0;
$failedCount = 0;

foreach ($input['items'] as $item) {
    $productId = intval($item['product_id'] ?? 0);
    $conditionType = (string)($item['condition_type'] ?? '');
    $qty = intval($item['qty'] ?? 0);
    $name = (string)($item['name'] ?? '');
    $tname = (string)($item['tname'] ?? $conditionType);

    if ($productId <= 0 || empty($conditionType)) {
        $failedCount++;
        $results[] = ['ok' => false, 'name' => $name, 'condition_type' => $conditionType, 'msg' => '参数缺失'];
        continue;
    }

    try {
        // 该商品该状态的全部批次（多个批次合并成一条流水）
        $stmt = $pdo->prepare("
            SELECT id, remaining_qty, purchase_price, suggested_price
            FROM inventory_batches
            WHERE product_id = ? AND condition_type = ? AND store_id = ?
            ORDER BY purchased_at DESC, id DESC
        ");
        $stmt->execute([$productId, $conditionType, $storeId]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $before = 0;
        foreach ($batches as $b) $before += (int)$b['remaining_qty'];

        if (!empty($batches)) {
            $main = $batches[0];
            $diff = $qty - $before;

            // 主批次设为盘点数量，其余批次清零
            $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = ? WHERE id = ? AND store_id = ?");
            $stmt->execute([$qty, $main['id'], $storeId]);
            foreach (array_slice($batches, 1) as $ob) {
                $stmt = $pdo->prepare("UPDATE inventory_batches SET remaining_qty = 0 WHERE id = ? AND store_id = ?");
                $stmt->execute([(int)$ob['id'], $storeId]);
            }

            if ($diff !== 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_log (store_id, user_id, product_id, condition_type, change_type, qty_change, before_qty, after_qty, price, remark)
                    VALUES (?, ?, ?, ?, 'adjust', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$storeId, $operatorId, $productId, $conditionType, $diff, $before, $qty, null, $remark]);
            }
        } elseif ($qty > 0) {
            // 无批次但盘点有货 → 新建批次
            $batchNo = 'PD' . date('Ymd') . strtoupper(substr(uniqid(), -6));
            $stmt = $pdo->prepare("
                INSERT INTO inventory_batches (product_id, condition_type, batch_no, total_qty, remaining_qty, purchase_price, suggested_price, purchased_at, remark, store_id)
                VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), '盘点新增', ?)
            ");
            $stmt->execute([$productId, $conditionType, $batchNo, $qty, $qty, $storeId]);

            $stmt = $pdo->prepare("
                INSERT INTO inventory_log (store_id, user_id, product_id, condition_type, change_type, qty_change, before_qty, after_qty, price, remark)
                VALUES (?, ?, ?, ?, 'purchase', ?, 0, ?, 0, ?)
            ");
            $stmt->execute([$storeId, $operatorId, $productId, $conditionType, $qty, $qty, $remark]);
        }

        $successCount++;
        $results[] = ['ok' => true, 'name' => $name, 'condition_type' => $conditionType, 'before' => $before, 'after' => $qty];
    } catch (Exception $e) {
        $failedCount++;
        $results[] = ['ok' => false, 'name' => $name, 'condition_type' => $conditionType, 'msg' => $e->getMessage()];
    }
}

success([
    'data' => [
        'results' => $results,
        'success' => $successCount,
        'failed' => $failedCount,
    ]
]);
