<?php
/**
 * pos_lottery_status.php — 收银台抽奖状态（免登录）
 * 返回：当前门店生效活动、转盘扇区、本单已抽次数与是否还能抽
 * ?order_id=NNN（付款成功后调用）
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/lottery_lib.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$storeId = requirePosStore();
$shopId = posShopId();
$orderId = (int)($_GET['order_id'] ?? 0);
$pdo = getDB();

try {
    $campaign = lotteryActiveCampaign($pdo, $storeId, $shopId);
    if (!$campaign) {
        success(['active' => false]);
    }
    $maxSpins = 1 + max(0, (int)$campaign['spin_again_limit']);

    $spinsUsed = 0;
    $lastDraw = null;
    $pendingDraw = null;
    $order = null;
    if ($orderId > 0) {
        $ordStmt = $pdo->prepare('SELECT id, order_no, payable, pay_status, outbound_status, customer_phone
                                  FROM pos_orders WHERE id = ? AND store_id = ?' . ($shopId ? ' AND shop_id = ?' : ''));
        $ordStmt->execute($shopId ? [$orderId, $storeId, $shopId] : [$orderId, $storeId]);
        $order = $ordStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $dStmt = $pdo->prepare('SELECT * FROM lottery_draws WHERE order_id = ? ORDER BY spin_index ASC');
        $dStmt->execute([$orderId]);
        $draws = $dStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($draws as $d) {
            $spinsUsed = max($spinsUsed, (int)$d['spin_index']);
            if ((int)$d['spin_index'] === $spinsUsed) $lastDraw = $d;
            // 待补录手机号/待领取的中奖记录（刷新后可恢复领取表单）
            if ($d['claim_status'] === 'pending' && !in_array($d['prize_type'], ['none', 'spin_again'], true) && !$pendingDraw) {
                $pendingDraw = $d;
            }
        }
    }

    $canSpin = false;
    if ($order && $order['pay_status'] === 'paid' && $order['outbound_status'] !== 'voided' && $spinsUsed < $maxSpins) {
        // 每单 1 次；只有上一次中的是「再来一次」才可续抽
        $canSpin = $spinsUsed === 0 || ($lastDraw && $lastDraw['prize_type'] === 'spin_again');
    }
    // 展示用扇区固定：最后一把也照常显示全部扇区，避免转盘中途换形（抽奖时再排除「再来一次」）
    $segments = lotteryBuildSegments($pdo, $campaign, $storeId, false);

    $threshold = round((float)$campaign['threshold'], 2);
    $qualified = $order ? (round((float)$order['payable'], 2) + 0.001 >= $threshold) : false;

    success([
        'active' => true,
        'campaign' => [
            'id' => (int)$campaign['id'],
            'name' => (string)$campaign['name'],
            'threshold' => $threshold,
            'spin_again_limit' => max(0, (int)$campaign['spin_again_limit']),
        ],
        'segments' => $segments,
        'max_spins' => $maxSpins,
        'spins_used' => $spinsUsed,
        'can_spin' => $canSpin && $qualified,
        'qualified' => $qualified,
        'order' => $order ? [
            'id' => (int)$order['id'],
            'order_no' => $order['order_no'],
            'payable' => round((float)$order['payable'], 2),
            'phone' => $order['customer_phone'] ?? '',
        ] : null,
        'pending_draw' => $pendingDraw ? lotteryDrawPublic($pendingDraw) : null,
    ]);
} catch (Exception $e) {
    logError($e->getMessage(), 'pos_lottery_status');
    error('加载抽奖活动失败: ' . $e->getMessage(), 500);
}

/** 抽奖记录对外字段（不暴露内部 id 关联） */
function lotteryDrawPublic($d) {
    return [
        'draw_id' => (int)$d['id'],
        'spin_index' => (int)$d['spin_index'],
        'prize_type' => (string)$d['prize_type'],
        'prize_name' => (string)($d['prize_name'] ?? ''),
        'is_guarantee' => (int)$d['is_guarantee'],
        'claim_status' => (string)$d['claim_status'],
        'phone' => (string)($d['phone'] ?? ''),
    ];
}
