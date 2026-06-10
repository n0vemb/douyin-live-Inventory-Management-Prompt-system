<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$outboundBatchNo = trim((string)($input['outbound_batch_no'] ?? ''));
$gmv = isset($input['gmv']) ? floatval($input['gmv']) : null;
$orderCount = isset($input['order_count']) ? intval($input['order_count']) : null;
$adSpend = isset($input['ad_spend']) ? floatval($input['ad_spend']) : null;
$platform = $input['platform'] ?? null;
$account = $input['account'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($outboundBatchNo)) {
    error('缺少批次号');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

if (!$storeId) {
    error('请先选择店铺');
}

try {
    // 更新财务表
    $stmt = $pdo->prepare('
        INSERT INTO outbound_finance (store_id, outbound_batch_no, gmv, order_count, ad_spend)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            gmv = VALUES(gmv),
            order_count = VALUES(order_count),
            ad_spend = VALUES(ad_spend)
    ');
    $stmt->execute([$storeId, $outboundBatchNo, $gmv, $orderCount, $adSpend]);

    // 更新出库记录的备注/平台/账号
    $updateFields = [];
    $updateParams = [];
    if ($remark !== null) { $updateFields[] = 'remark = ?'; $updateParams[] = $remark; }
    if ($platform !== null) { $updateFields[] = 'platform = ?'; $updateParams[] = $platform; }
    if ($account !== null) { $updateFields[] = 'account = ?'; $updateParams[] = $account; }
    if (!empty($updateFields)) {
        $updateParams[] = $outboundBatchNo;
        $updateParams[] = $storeId;
        $stmt = $pdo->prepare('UPDATE outbound_log SET ' . implode(', ', $updateFields) . ' WHERE outbound_batch_no = ? AND store_id = ?');
        $stmt->execute($updateParams);
    }

    success(['message' => '财务数据已保存']);
} catch (Exception $e) {
    error($e->getMessage());
}
