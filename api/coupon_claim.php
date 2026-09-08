<?php
/**
 * coupon_claim.php — 公开活动领券（免登录）
 * GET ?token=xxx → 活动信息
 * POST {token, phone} → 领取
 * 手机号手动填写，无短信验证；防刷：限频 + 每人限领 + 总发行量
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/coupon_lib.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$token = $method === 'POST'
    ? trim((string)(json_decode(file_get_contents('php://input'), true)['token'] ?? ''))
    : trim((string)($_GET['token'] ?? ''));
if ($token === '') error('缺少活动参数');

try {
    $stmt = $pdo->prepare('SELECT * FROM coupon_campaigns WHERE claim_token = ?');
    $stmt->execute([$token]);
    $camp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$camp) error('活动不存在或链接已失效');

    $issued = couponIssuedCount($pdo, (int)$camp['id']);
    $remain = (int)$camp['total_count'] > 0 ? max(0, (int)$camp['total_count'] - $issued) : null;
    if ($method === 'GET') {
        success([
            'campaign' => [
                'id' => (int)$camp['id'],
                'name' => $camp['name'],
                'type' => $camp['coupon_type'],
                'threshold' => round((float)$camp['threshold'], 2),
                'amount' => round((float)$camp['amount'], 2),
                'end_at' => $camp['end_at'],
                'per_user' => (int)$camp['per_user'],
                'remain' => $remain,
            ],
            'store' => ['id' => (int)$camp['store_id']],
        ]);
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $phone = trim((string)($input['phone'] ?? ''));
        if (!couponPhoneValid($phone)) error('请输入正确的 11 位手机号');
        if (!couponCampaignActive($camp)) error('活动未开始或已结束');
        if ($remain !== null && $remain <= 0) error('手慢了，活动券已领完');

        // 防刷：同一手机号 10 秒内只能提交一次
        $last = $pdo->prepare('SELECT claimed_at FROM coupon_claims WHERE phone = ? ORDER BY id DESC LIMIT 1');
        $last->execute([$phone]);
        $lastAt = $last->fetchColumn();
        if ($lastAt && (strtotime(couponNow()) - strtotime($lastAt)) < 10) {
            error('操作太快，请稍后再试');
        }
        $cnt = couponClaimedCount($pdo, (int)$camp['id'], $phone);
        if ($cnt >= (int)$camp['per_user']) {
            error('每个手机号限领 ' . (int)$camp['per_user'] . ' 张');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ins = $pdo->prepare(
            "INSERT INTO coupon_claims (store_id, campaign_id, phone, status, issue_type, ip, remark)
             VALUES (?,?,?, 'unused', 'claim', ?, '活动领取')"
        );
        $ins->execute([(int)$camp['store_id'], (int)$camp['id'], $phone, $ip]);
        success(['message' => '领取成功', 'claim_id' => (int)$pdo->lastInsertId()]);
    }
} catch (Exception $e) {
    logError($e->getMessage(), 'coupon_claim', ['token' => $token]);
    error($e->getMessage(), 500);
}
