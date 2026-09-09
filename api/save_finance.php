<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$outboundBatchNo = trim((string)($input['outbound_batch_no'] ?? ''));
$gmv = isset($input['gmv']) ? decimal($input['gmv']) : null;
$orderCount = isset($input['order_count']) ? intval($input['order_count']) : null;
$adSpend = isset($input['ad_spend']) ? decimal($input['ad_spend']) : null;
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
    // 批次必须归属单一店铺（一个批次只对一家店结算）
    $stmt = $pdo->prepare("SELECT DISTINCT shop_id FROM outbound_log WHERE outbound_batch_no = ? AND store_id = ?");
    $stmt->execute([$outboundBatchNo, $storeId]);
    $batchShops = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($batchShops) === 0) {
        error('该批次没有出库明细，请先确认批次号');
    }
    if (count($batchShops) > 1) {
        error('该出库批次包含多个店铺的订单，请按店铺拆批后分别结算');
    }
    $shopId = $batchShops[0];

    // 店管只能保存本店批次的财务
    $myShop = getShopId();
    if ($myShop && $myShop !== $shopId) {
        error('无权操作其他店铺的出库批次');
    }

    // 更新财务表
    $stmt = $pdo->prepare('
        INSERT INTO outbound_finance (store_id, shop_id, outbound_batch_no, gmv, order_count, ad_spend)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            gmv = VALUES(gmv),
            order_count = VALUES(order_count),
            ad_spend = VALUES(ad_spend)
    ');
    $stmt->execute([$storeId, $shopId, $outboundBatchNo, $gmv, $orderCount, $adSpend]);

    // 更新出库记录的备注/平台/账号（空字符串也更新，允许清空）
    $updateFields = [];
    $updateParams = [];
    $remarkVal = $input['remark'] ?? null;
    $platformVal = $input['platform'] ?? null;
    $accountVal = $input['account'] ?? null;
    if ($remarkVal !== null) { $updateFields[] = 'remark = ?'; $updateParams[] = $remarkVal; }
    if ($platformVal !== null) { $updateFields[] = 'platform = ?'; $updateParams[] = $platformVal; }
    if ($accountVal !== null) { $updateFields[] = 'account = ?'; $updateParams[] = $accountVal; }
    if (!empty($updateFields)) {
        $updateParams[] = $outboundBatchNo;
        $updateParams[] = $storeId;
        $updateParams[] = $shopId;
        $stmt = $pdo->prepare('UPDATE outbound_log SET ' . implode(', ', $updateFields) . ' WHERE outbound_batch_no = ? AND store_id = ? AND shop_id = ?');
        $stmt->execute($updateParams);
    }

    success(['message' => '财务数据已保存']);
} catch (Exception $e) {
    error($e->getMessage());
}
