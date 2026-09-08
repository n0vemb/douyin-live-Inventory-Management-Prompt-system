<?php
/**
 * coupon_lib.php — 优惠券共享逻辑（后台/活动页/收银台共用）
 * 券实例生命周期：unused → locked → used；取消/超时/退单回 unused
 */

function couponPhoneValid($phone) {
    return is_string($phone) && preg_match('/^1[3-9]\d{9}$/', trim($phone));
}

/** 当前时间 */
function couponNow() {
    return date('Y-m-d H:i:s');
}

/** 活动是否处于可领取/可核销窗口 */
function couponCampaignActive($camp, $now = null) {
    $now = $now ?: couponNow();
    if (!is_array($camp)) return false;
    if (($camp['status'] ?? '') !== 'active') return false;
    if (!empty($camp['start_at']) && $camp['start_at'] > $now) return false;
    if (!empty($camp['end_at']) && $camp['end_at'] < $now) return false;
    return true;
}

/** 某手机号在某活动已领取张数 */
function couponClaimedCount($pdo, $campaignId, $phone) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM coupon_claims WHERE campaign_id = ? AND phone = ?');
    $stmt->execute([$campaignId, $phone]);
    return (int)$stmt->fetchColumn();
}

/** 已发行/剩余（统计状态非 expired/refunded 之外的实例数即可；领取即占额度） */
function couponIssuedCount($pdo, $campaignId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM coupon_claims WHERE campaign_id = ?');
    $stmt->execute([$campaignId]);
    return (int)$stmt->fetchColumn();
}

/**
 * 手机号可用券列表（收银台用）
 * @param float $subtotal 优惠前合计（用于满减门槛判断）
 */
function couponUsableClaims($pdo, $storeId, $phone, $subtotal = 0, $now = null) {
    $now = $now ?: couponNow();
    if (!couponPhoneValid($phone)) return [];
    $stmt = $pdo->prepare(
        "SELECT cc.id AS claim_id, cc.phone, cc.status, cc.claimed_at,
                cp.id AS campaign_id, cp.store_id, cp.name, cp.coupon_type,
                cp.threshold, cp.amount, cp.stackable, cp.end_at, cp.remark AS campaign_remark
         FROM coupon_claims cc
         JOIN coupon_campaigns cp ON cp.id = cc.campaign_id
         WHERE cc.store_id = ? AND cc.phone = ? AND cc.status = 'unused'
           AND cp.store_id = ? AND cp.status = 'active'
           AND (cp.start_at IS NULL OR cp.start_at <= ?)
           AND (cp.end_at IS NULL OR cp.end_at >= ?)
         ORDER BY cp.end_at ASC, cc.id ASC"
    );
    $stmt->execute([$storeId, $phone, $storeId, $now, $now]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $amount = round((float)$r['amount'], 2);
        $threshold = round((float)$r['threshold'], 2);
        $usable = $threshold <= 0 || ($subtotal >= $threshold - 0.001);
        $out[] = [
            'claim_id'    => (int)$r['claim_id'],
            'campaign_id' => (int)$r['campaign_id'],
            'name'        => $r['name'],
            'type'        => $r['coupon_type'],
            'threshold'   => $threshold,
            'amount'      => $amount,
            'stackable'   => (int)$r['stackable'],
            'end_at'      => $r['end_at'],
            'usable'      => $usable,
            'reason'      => $usable ? '' : '未达满减门槛',
        ];
    }
    return $out;
}

/**
 * 核销/回退辅助
 */
function couponSetClaimsByOrder($pdo, $orderId, $toStatus) {
    if ($toStatus === 'used') {
        $upd = $pdo->prepare(
            "UPDATE coupon_claims SET status = 'used', used_at = ?
             WHERE order_id = ? AND status = 'locked'"
        );
        return $upd->execute([couponNow(), $orderId]);
    }
    // 退回（取消/超时/退单）：券恢复可用，保留领取记录
    $upd = $pdo->prepare(
        "UPDATE coupon_claims SET status = 'unused', used_at = NULL, released_at = ?,
                order_id = NULL, order_no = NULL
         WHERE order_id = ? AND status IN ('locked','used')"
    );
    return $upd->execute([couponNow(), $orderId]);
}
