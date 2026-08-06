<?php
/**
 * 合并出库批次
 *
 * 将多个已出库批次合并到主批次中
 * - 同商品+同SKU+同价格 → 合并数量
 * - 不同价格 → 各自保留
 * - 财务数据（GMV/订单数/投流）汇总到主批次
 * - 库存不受影响（已扣减）
 *
 * POST: { main_batch_no, merged_batch_nos: [...] }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    jsonResponse(['success' => false, 'error' => '请使用POST方法']);
}

$input = json_decode(file_get_contents('php://input'), true);
$mainBatchNo = trim($input['main_batch_no'] ?? '');
$mergedBatchNos = $input['merged_batch_nos'] ?? [];

if (empty($mainBatchNo) || empty($mergedBatchNos) || !is_array($mergedBatchNos)) {
    error('参数无效：需要 main_batch_no 和 merged_batch_nos 数组');
}

// 去重并排除主批次
$mergedBatchNos = array_unique($mergedBatchNos);
$mergedBatchNos = array_values(array_filter($mergedBatchNos, fn($b) => trim($b) !== $mainBatchNo));

if (empty($mergedBatchNos)) {
    error('没有有效的被合并批次');
}

$pdo = getDB();
requireAuth();
$storeId = getStoreId();

$pdo->beginTransaction();

try {
    // 1. 验证主批次存在
    $mainStmt = $pdo->prepare("
        SELECT COUNT(*) FROM outbound_log
        WHERE outbound_batch_no = ?" . ($storeId ? " AND store_id = ?" : "") . "
        LIMIT 1
    ");
    $mainParams = [$mainBatchNo];
    if ($storeId) $mainParams[] = $storeId;
    $mainStmt->execute($mainParams);
    if ($mainStmt->fetchColumn() == 0) {
        throw new Exception("主批次 {$mainBatchNo} 不存在或不属于当前店铺");
    }

    // 校验被合并批次是否存在
    $placeholders = implode(',', array_fill(0, count($mergedBatchNos), '?'));
    $checkStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT outbound_batch_no)
        FROM outbound_log
        WHERE outbound_batch_no IN ({$placeholders})" . ($storeId ? " AND store_id = ?" : "") . "
    ");
    $checkParams = $mergedBatchNos;
    if ($storeId) $checkParams[] = $storeId;
    $checkStmt->execute($checkParams);
    $found = (int) $checkStmt->fetchColumn();

    if ($found !== count($mergedBatchNos)) {
        throw new Exception('部分被合并批次不存在或不属于当前店铺');
    }

    // 2. 移动出库记录：将 outbound_batch_no 更新为主批次号
    $updateParams = array_merge([$mainBatchNo], $mergedBatchNos);
    if ($storeId) {
        $updateSql = "UPDATE outbound_log SET outbound_batch_no = ? WHERE outbound_batch_no IN ({$placeholders}) AND store_id = ?";
        $updateParams[] = $storeId;
    } else {
        $updateSql = "UPDATE outbound_log SET outbound_batch_no = ? WHERE outbound_batch_no IN ({$placeholders})";
    }
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute($updateParams);
    $movedCount = $updateStmt->rowCount();

    // 3. 合并同商品+同SKU+同价格的记录
    $dupStmt = $pdo->prepare("
        SELECT id, product_id, condition_type, outbound_price, qty
        FROM outbound_log
        WHERE outbound_batch_no = ?" . ($storeId ? " AND store_id = ?" : "") . "
        ORDER BY id
    ");
    $dupParams = [$mainBatchNo];
    if ($storeId) $dupParams[] = $storeId;
    $dupStmt->execute($dupParams);
    $rows = $dupStmt->fetchAll(PDO::FETCH_ASSOC);

    $groups = [];
    foreach ($rows as $row) {
        $key = $row['product_id'] . '|' . $row['condition_type'] . '|' . $row['outbound_price'];
        $groups[$key][] = $row;
    }

    $mergedRows = 0;
    foreach ($groups as $group) {
        if (count($group) > 1) {
            $keepId = $group[0]['id'];
            $totalQty = array_sum(array_column($group, 'qty'));
            $deleteIds = array_column(array_slice($group, 1), 'id');

            $pdo->prepare("UPDATE outbound_log SET qty = ? WHERE id = ?")
                ->execute([$totalQty, $keepId]);

            $delPlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $pdo->prepare("DELETE FROM outbound_log WHERE id IN ({$delPlaceholders})")
                ->execute($deleteIds);

            $mergedRows += count($deleteIds);
        }
    }

    // 4. 合并财务数据：汇总 GMV/订单数/投流到主批次
    // 先把主批次+被合并批次的旧 finance 记录全删掉
    $allBatchNos = array_merge([$mainBatchNo], $mergedBatchNos);
    $allPlaceholders = implode(',', array_fill(0, count($allBatchNos), '?'));

    $sumFinanceStmt = $pdo->prepare("
        SELECT SUM(COALESCE(gmv, 0)) as total_gmv,
               SUM(COALESCE(order_count, 0)) as total_orders,
               SUM(COALESCE(ad_spend, 0)) as total_ad_spend
        FROM outbound_finance
        WHERE outbound_batch_no IN ({$allPlaceholders})" . ($storeId ? " AND store_id = ?" : "") . "
    ");
    $sumFinanceParams = $allBatchNos;
    if ($storeId) $sumFinanceParams[] = $storeId;
    $sumFinanceStmt->execute($sumFinanceParams);
    $financeSum = $sumFinanceStmt->fetch(PDO::FETCH_ASSOC);

    // 删除全部旧 finance 记录（主批次 + 被合并批次）
    $delFinanceAllStmt = $pdo->prepare("
        DELETE FROM outbound_finance
        WHERE outbound_batch_no IN ({$allPlaceholders})" . ($storeId ? " AND store_id = ?" : "") . "
    ");
    $delFinanceAllParams = $allBatchNos;
    if ($storeId) $delFinanceAllParams[] = $storeId;
    $delFinanceAllStmt->execute($delFinanceAllParams);

    // 插入汇总后的一条
    $hasFinance = $financeSum['total_gmv'] > 0 || $financeSum['total_orders'] > 0 || $financeSum['total_ad_spend'] > 0;
    if ($hasFinance) {
        $mainStoreId = $storeId;
        if (!$mainStoreId) {
            $storeStmt = $pdo->prepare("SELECT store_id FROM outbound_log WHERE outbound_batch_no = ? LIMIT 1");
            $storeStmt->execute([$mainBatchNo]);
            $mainStoreId = $storeStmt->fetchColumn() ?: 0;
        }

        $pdo->prepare("
            INSERT INTO outbound_finance (store_id, outbound_batch_no, gmv, order_count, ad_spend)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $mainStoreId,
            $mainBatchNo,
            $financeSum['total_gmv'],
            $financeSum['total_orders'],
            $financeSum['total_ad_spend']
        ]);
    }

    $pdo->commit();

    success([
        'data' => [
            'main_batch_no' => $mainBatchNo,
            'merged_batches' => $mergedBatchNos,
            'moved_records' => $movedCount,
            'merged_duplicates' => $mergedRows,
            'has_finance' => $hasFinance,
            'message' => "合并成功：{$movedCount} 条记录合并到 {$mainBatchNo}" . ($mergedRows > 0 ? "，合并 {$mergedRows} 条重复记录" : "")
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
