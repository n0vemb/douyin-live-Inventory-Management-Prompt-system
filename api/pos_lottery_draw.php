<?php
/**
 * pos_lottery_draw.php — 收银台抽奖（免登录，服务端定结果，前端只演动画）
 * 规则：单笔实付满 threshold 抽 1 次；中「再来一次」可续抽（spin_again_limit 上限）
 * 前端只在收到结果后把转盘转到对应扇区，结果不可被前端篡改。
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/lottery_lib.php';
$storeId = requirePosStore();
$shopId = posShopId();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$orderId = (int)($input['order_id'] ?? 0);
if (!$orderId) error('缺少订单ID');
$pdo = getDB();

$lockName = 'pp_pos_lottery_' . (int)$storeId . '_' . $orderId;
$pdo->query('SELECT GET_LOCK(' . $pdo->quote($lockName) . ', 5)');
try {
    $ordStmt = $pdo->prepare('SELECT * FROM pos_orders WHERE id = ? AND store_id = ?' . ($shopId ? ' AND shop_id = ?' : ''));
    $ordStmt->execute($shopId ? [$orderId, $storeId, $shopId] : [$orderId, $storeId]);
    $order = $ordStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) error('订单不存在');
    if ($order['outbound_status'] === 'voided') error('订单已作废，无法抽奖');
    if ($order['pay_status'] !== 'paid') error('订单未完成付款，无法抽奖');

    $campaign = lotteryActiveCampaign($pdo, $storeId, $shopId);
    if (!$campaign) error('当前没有进行中的抽奖活动');
    $threshold = round((float)$campaign['threshold'], 2);
    if (round((float)$order['payable'], 2) + 0.001 < $threshold) {
        error('本单未满 ¥' . number_format($threshold, 2) . '，无法抽奖');
    }

    $maxSpins = 1 + max(0, (int)$campaign['spin_again_limit']);
    $dStmt = $pdo->prepare('SELECT * FROM lottery_draws WHERE order_id = ? ORDER BY spin_index ASC');
    $dStmt->execute([$orderId]);
    $draws = $dStmt->fetchAll(PDO::FETCH_ASSOC);
    $spinsUsed = 0;
    $lastDraw = null;
    foreach ($draws as $d) {
        if ((int)$d['spin_index'] >= $spinsUsed) { $spinsUsed = (int)$d['spin_index']; $lastDraw = $d; }
    }
    if ($spinsUsed >= $maxSpins) error('本单抽奖次数已用完');
    if ($spinsUsed > 0 && (!$lastDraw || $lastDraw['prize_type'] !== 'spin_again')) {
        error('本单已抽过奖，不能重复抽');
    }

    $spinIndex = $spinsUsed + 1;
    $isLastSpin = $spinIndex >= $maxSpins;
    // 展示扇区固定（与收银台当前转盘一致，前端不换形）
    $segments = lotteryBuildSegments($pdo, $campaign, $storeId, false);
    $picked = lotteryPickSegment($segments);
    // 最后一把抽到「再来一次」：在其余扇区里按权重重抽，落点仍是转盘上真实存在的扇区
    if ($isLastSpin && ($picked['type'] ?? '') === 'spin_again') {
        $pool = [];
        foreach ($segments as $sg) { if (($sg['type'] ?? '') !== 'spin_again') $pool[] = $sg; }
        if ($pool) $picked = lotteryPickFromPool($pool);
    }

    // 保底奖品已由 lotteryBuildSegments 作为正常扇区参与抽取（未中奖权重改由保底奖品承接）
    $isGuarantee = (int)($picked['guarantee'] ?? 0);

    $pdo->beginTransaction();
    try {
        // 中出数量：配额内才计数（配额被抢光则降级为未中奖）
        $prizeId = (int)($picked['id'] ?? 0);
        if ($prizeId > 0) {
            $upd = $pdo->prepare('UPDATE lottery_prizes SET won = won + 1 WHERE id = ? AND (quota = 0 OR won < quota)');
            $upd->execute([$prizeId]);
            if ($upd->rowCount() === 0) {
                $picked = ['id' => 0, 'name' => '谢谢参与', 'type' => 'none', 'weight' => 0, 'qty' => 0, 'note' => '', 'guarantee' => 0];
                $prizeId = 0;
                $isGuarantee = 0;
            }
        }
        $ins = $pdo->prepare(
            "INSERT INTO lottery_draws (campaign_id, store_id, shop_id, order_id, order_no, prize_id, prize_type,
                                        prize_name, custom_note, spin_index, is_guarantee, phone, claim_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $needClaim = !in_array($picked['type'], ['none', 'spin_again'], true);
        $contactPhone = trim((string)($order['customer_phone'] ?? ''));
        $ins->execute([
            (int)$campaign['id'], $storeId, $shopId ?: null, $orderId, $order['order_no'],
            $prizeId ?: null, $picked['type'], $picked['name'], $picked['note'] ?: null,
            $spinIndex, $isGuarantee,
            $contactPhone !== '' ? $contactPhone : null,
            $needClaim ? 'pending' : 'claimed',
        ]);
        $drawId = (int)$pdo->lastInsertId();
        if (!$needClaim) {
            $pdo->prepare('UPDATE lottery_draws SET claimed_at = NOW() WHERE id = ?')->execute([$drawId]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')');

    // 前端转盘定位：扇区在序列中的下标（与 segments 顺序一致）
    $segmentIndex = 0;
    foreach ($segments as $i => $sg) {
        if ((int)$sg['id'] === (int)($picked['id'] ?? 0) && $sg['type'] === $picked['type']
            && (int)($sg['guarantee'] ?? 0) === $isGuarantee) { $segmentIndex = $i; break; }
    }
    $canSpinAgain = $picked['type'] === 'spin_again' && $spinIndex < $maxSpins;

    success([
        'draw_id' => $drawId,
        'spin_index' => $spinIndex,
        'is_guarantee' => $isGuarantee,
        'segment_index' => $segmentIndex,
        'segments' => $segments,
        'can_spin_again' => $canSpinAgain,
        'need_claim' => $needClaim,
        'prize' => [
            'id' => (int)($picked['id'] ?? 0),
            'name' => $picked['name'],
            'type' => $picked['type'],
            'note' => $picked['note'],
        ],
    ]);
} catch (Exception $e) {
    try { $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')'); } catch (Exception $ignore) {}
    logError($e->getMessage(), 'pos_lottery_draw', ['order_id' => $orderId]);
    error('抽奖失败: ' . $e->getMessage(), 500);
}
