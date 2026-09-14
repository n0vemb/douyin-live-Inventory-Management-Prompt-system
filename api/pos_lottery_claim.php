<?php
/**
 * pos_lottery_claim.php — 抽奖结果领取（免登录）
 * 结果页补录手机号后领取：
 *   券奖品   → 必填手机号，发放券实例（unused，收银台输入手机号即可核销）
 *   商品奖品 → 生成 0 元出库单进「门店待出库」（有库存则占用批次，无库存仅登记）
 *   自定义奖品 → 生成 0 元出库单登记（不占库存）
 *   未中奖/再来一次 → 仅记录手机号
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/lottery_lib.php';
$storeId = requirePosStore();
$shopId = posShopId();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$drawId = (int)($input['draw_id'] ?? 0);
$phone = trim((string)($input['phone'] ?? ''));
if (!$drawId) error('缺少抽奖记录ID');
$pdo = getDB();

$lockName = 'pp_pos_lottery_' . (int)$storeId . '_d' . $drawId;
$pdo->query('SELECT GET_LOCK(' . $pdo->quote($lockName) . ', 5)');
try {
    $stmt = $pdo->prepare(
        'SELECT d.*, o.order_no AS src_order_no, o.customer_phone AS src_phone, o.payable, o.outbound_status AS src_outbound
         FROM lottery_draws d JOIN pos_orders o ON o.id = d.order_id
         WHERE d.id = ? AND d.store_id = ?' . ($shopId ? ' AND d.shop_id = ?' : '')
    );
    $stmt->execute($shopId ? [$drawId, $storeId, $shopId] : [$drawId, $storeId]);
    $draw = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$draw) error('抽奖记录不存在');
    if ($draw['claim_status'] === 'voided') error('该奖励已作废');

    $type = (string)$draw['prize_type'];
    // 券奖品必须留手机号，其余选填
    if ($type === 'coupon' && !couponPhoneValid($phone)) {
        error('请输入正确的手机号领取优惠券');
    }
    if ($phone !== '' && !couponPhoneValid($phone)) {
        error('手机号格式不正确');
    }

    if ($draw['claim_status'] === 'claimed') {
        // 已有领取记录：仅在补录手机号时更新，接口幂等
        if ($phone !== '' && $phone !== ($draw['phone'] ?? '')) {
            $pdo->prepare('UPDATE lottery_draws SET phone = ? WHERE id = ?')->execute([$phone, $drawId]);
        }
        success([
            'claim_status' => 'claimed',
            'prize_type' => $type,
            'prize_name' => (string)$draw['prize_name'],
            'phone' => (string)($draw['phone'] ?? ''),
            'coupon_claim_id' => $draw['coupon_claim_id'] ? (int)$draw['coupon_claim_id'] : null,
            'prize_order_no' => null,
            'message' => '奖励已领取',
        ]);
    }

    $couponClaimId = null;
    $prizeOrderNo = null;
    $message = '';

    $pdo->beginTransaction();
    try {
        if ($type === 'coupon') {
            $prize = lotteryPrizeById($pdo, (int)$draw['prize_id']);
            if (!$prize || (int)$prize['coupon_campaign_id'] <= 0) throw new Exception('奖品配置已失效，请联系店员');
            $cStmt = $pdo->prepare('SELECT * FROM coupon_campaigns WHERE id = ?');
            $cStmt->execute([(int)$prize['coupon_campaign_id']]);
            $camp = $cStmt->fetch(PDO::FETCH_ASSOC);
            if (!$camp || !couponCampaignActive($camp)) throw new Exception('券活动已结束，请联系店员');
            $issued = couponIssuedCount($pdo, (int)$camp['id']);
            if ((int)$camp['total_count'] > 0 && $issued >= (int)$camp['total_count']) {
                throw new Exception('券已发完，请联系店员更换奖品');
            }
            $ins = $pdo->prepare(
                "INSERT INTO coupon_claims (store_id, shop_id, campaign_id, phone, status, issue_type, remark)
                 VALUES (?, ?, ?, ?, 'unused', 'lottery', ?)"
            );
            $ins->execute([
                $storeId,
                $shopId ?: null,
                (int)$camp['id'],
                $phone,
                '收银台抽奖奖品（订单 ' . $draw['src_order_no'] . '）',
            ]);
            $couponClaimId = (int)$pdo->lastInsertId();
            $message = '优惠券已发放到手机号 ' . $phone . '，收银台输入手机号即可使用';
        } elseif (in_array($type, ['product', 'custom'], true)) {
            $prize = lotteryPrizeById($pdo, (int)$draw['prize_id']);
            if (!$prize) throw new Exception('奖品配置已失效，请联系店员');
            $srcOrder = ['store_id' => $storeId, 'shop_id' => $shopId ?: null];
            $created = lotteryCreatePrizeOrder($pdo, $srcOrder, $prize, $phone);
            $prizeOrderNo = $created['order_no'];
            $pdo->prepare('UPDATE lottery_draws SET prize_order_id = ? WHERE id = ?')->execute([(int)$created['order_id'], $drawId]);
            $message = $created['tracked']
                ? '奖品已登记，凭手机号到仓库领取'
                : '奖品已登记（库存不足或无库存，由门店补货后发放）';
        } else {
            $message = '谢谢参与，欢迎下次再来';
        }

        $pdo->prepare(
            "UPDATE lottery_draws SET phone = ?, claim_status = 'claimed', claimed_at = NOW(), coupon_claim_id = ?
             WHERE id = ?"
        )->execute([$phone !== '' ? $phone : null, $couponClaimId, $drawId]);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')');
    success([
        'claim_status' => 'claimed',
        'prize_type' => $type,
        'prize_name' => (string)$draw['prize_name'],
        'phone' => $phone,
        'coupon_claim_id' => $couponClaimId,
        'prize_order_no' => $prizeOrderNo,
        'message' => $message,
    ]);
} catch (Exception $e) {
    try { $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')'); } catch (Exception $ignore) {}
    logError($e->getMessage(), 'pos_lottery_claim', ['draw_id' => $drawId]);
    error($e->getMessage(), 400);
}
