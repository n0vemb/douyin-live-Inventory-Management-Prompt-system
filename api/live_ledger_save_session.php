<?php
/**
 * live_ledger_save_session.php — 创建或更新直播记账场次
 * POST { session_id?, session_name, activity_type, gift_every_n, reduce_threshold,
 *        reduce_amount, platform_fee_rate, packing_cost, shipping_fee_8, shipping_fee_9 }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    error('无效请求');
}

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
$sessionName = trim($input['session_name'] ?? '');
$anchor = trim($input['anchor'] ?? '');
$operator = trim($input['operator'] ?? '');
$account = trim($input['account'] ?? '');
$activityType = $input['activity_type'] ?? 'none';
$giftEveryN = isset($input['gift_every_n']) ? (int)$input['gift_every_n'] : 3;
$reduceThreshold = isset($input['reduce_threshold']) ? floatval($input['reduce_threshold']) : 30;
$reduceAmount = isset($input['reduce_amount']) ? floatval($input['reduce_amount']) : 1;
$platformFeeRate = isset($input['platform_fee_rate']) ? floatval($input['platform_fee_rate']) : 5;
$packingCost = isset($input['packing_cost']) ? floatval($input['packing_cost']) : 1;
$shippingFee8 = isset($input['shipping_fee_8']) ? floatval($input['shipping_fee_8']) : 3;
$shippingFee9 = isset($input['shipping_fee_9']) ? floatval($input['shipping_fee_9']) : 4;
// 赠品预设：[{name, price, qty}]，校验后存 JSON
$giftPresets = [];
if (isset($input['gift_presets']) && is_array($input['gift_presets'])) {
    foreach ($input['gift_presets'] as $gp) {
        $name = trim($gp['name'] ?? '');
        $price = floatval($gp['price'] ?? 0);
        $qty = max(1, (int)($gp['qty'] ?? 1));
        if ($name !== '' && $price >= 0) {
            $giftPresets[] = ['name' => $name, 'price' => $price, 'qty' => $qty];
        }
    }
}
$giftPresetsJson = json_encode($giftPresets, JSON_UNESCAPED_UNICODE);

if (empty($sessionName)) {
    error('请输入场次名称');
}

// 平台扣点 5% 存 0.05
$platformFeeRate = $platformFeeRate / 100;

if ($sessionId > 0) {
    // 更新已有场次（仅 active 状态可改配置）
    $stmt = $pdo->prepare("SELECT status, anchor, operator, account FROM live_ledger_session WHERE id = ? AND store_id = ?");
    $stmt->execute([$sessionId, $storeId]);
    $existing = $stmt->fetch();
    if (!$existing) error('场次不存在');
    if ($existing['status'] !== 'active') error('已结束的场次不能修改');

    // 前端未传 anchor/operator/account 时保留原值（防止设置保存误清空）
    if (!array_key_exists('anchor', $input))     $anchor = $existing['anchor'] ?? '';
    if (!array_key_exists('operator', $input))   $operator = $existing['operator'] ?? '';
    if (!array_key_exists('account', $input))    $account = $existing['account'] ?? '';

    $stmt = $pdo->prepare("UPDATE live_ledger_session SET
        session_name = ?, anchor = ?, operator = ?, account = ?, activity_type = ?, gift_every_n = ?, reduce_threshold = ?,
        reduce_amount = ?, platform_fee_rate = ?, packing_cost = ?,
        shipping_fee_8 = ?, shipping_fee_9 = ?, gift_presets_json = ?
        WHERE id = ? AND store_id = ?");
    $stmt->execute([$sessionName, $anchor, $operator, $account, $activityType, $giftEveryN, $reduceThreshold, $reduceAmount,
        $platformFeeRate, $packingCost, $shippingFee8, $shippingFee9, $giftPresetsJson, $sessionId, $storeId]);
    success(['data' => ['session_id' => $sessionId]]);
} else {
    // 创建新场次
    $stmt = $pdo->prepare("INSERT INTO live_ledger_session
        (store_id, session_name, anchor, operator, account, activity_type, gift_every_n, reduce_threshold, reduce_amount,
         platform_fee_rate, packing_cost, shipping_fee_8, shipping_fee_9, gift_presets_json, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$storeId, $sessionName, $anchor, $operator, $account, $activityType, $giftEveryN, $reduceThreshold, $reduceAmount,
        $platformFeeRate, $packingCost, $shippingFee8, $shippingFee9, $giftPresetsJson]);
    $newId = (int)$pdo->lastInsertId();
    success(['data' => ['session_id' => $newId]]);
}
