<?php
/**
 * coupon_admin.php — 优惠券后台管理
 * 权限：店管 / 超管（按当前店铺隔离）
 * action=list / save / toggle / manual_issue / records
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/coupon_lib.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
if (!$storeId) error('请先选择店铺');
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'super_admin'], true)) error('无权限', 403);

$method = $_SERVER['REQUEST_METHOD'];
$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$action = $input['action'] ?? 'list';

try {
    if ($action === 'list') {
        $list = $pdo->prepare(
            "SELECT cp.*,
                    (SELECT COUNT(*) FROM coupon_claims c WHERE c.campaign_id = cp.id) AS issued,
                    (SELECT COUNT(*) FROM coupon_claims c WHERE c.campaign_id = cp.id AND c.status IN ('locked','used')) AS in_use
             FROM coupon_campaigns cp
             WHERE cp.store_id = ?
             ORDER BY cp.created_at DESC, cp.id DESC"
        );
        $list->execute([$storeId]);
        success(['campaigns' => $list->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'records') {
        $campaignId = (int)($input['campaign_id'] ?? 0);
        $phone = trim((string)($input['phone'] ?? ''));
        $sql = "SELECT cc.*, cp.name AS campaign_name
                FROM coupon_claims cc JOIN coupon_campaigns cp ON cp.id = cc.campaign_id
                WHERE cc.store_id = ?";
        $params = [$storeId];
        if ($campaignId > 0) { $sql .= ' AND cc.campaign_id = ?'; $params[] = $campaignId; }
        if ($phone !== '') { $sql .= ' AND cc.phone = ?'; $params[] = $phone; }
        $sql .= ' ORDER BY cc.id DESC LIMIT 300';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success(['records' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $type = $input['coupon_type'] ?? 'threshold';
        $threshold = round((float)($input['threshold'] ?? 0), 2);
        $amount = round((float)($input['amount'] ?? 0), 2);
        $totalCount = (int)($input['total_count'] ?? 0);
        $perUser = max(1, (int)($input['per_user'] ?? 1));
        $stackable = !empty($input['stackable']) ? 1 : 0;
        $startAt = $input['start_at'] ?: null;
        $endAt = $input['end_at'] ?: null;
        $remark = trim((string)($input['remark'] ?? ''));
        if (!in_array($type, ['threshold', 'fixed'], true)) $type = 'threshold';
        if ($name === '') error('请填写活动名称');
        if ($amount <= 0) error('优惠金额必须大于 0');
        if ($type === 'threshold' && $threshold < 0) error('满减门槛不能为负');
        if ($totalCount < 0) error('发行量不能为负');
        if ($startAt && $endAt && $endAt < $startAt) error('结束时间不能早于开始时间');

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT id FROM coupon_campaigns WHERE id = ? AND store_id = ?');
            $stmt->execute([$id, $storeId]);
            if (!$stmt->fetch()) error('活动不存在或不属于当前店铺');
            $upd = $pdo->prepare(
                "UPDATE coupon_campaigns SET name=?, coupon_type=?, threshold=?, amount=?, total_count=?,
                        per_user=?, stackable=?, start_at=?, end_at=?, remark=? WHERE id=? AND store_id=?"
            );
            $upd->execute([$name, $type, $threshold, $amount, $totalCount, $perUser, $stackable, $startAt, $endAt, $remark, $id, $storeId]);
        } else {
            $token = bin2hex(random_bytes(12));
            $ins = $pdo->prepare(
                "INSERT INTO coupon_campaigns (store_id, name, coupon_type, threshold, amount, total_count,
                 per_user, stackable, start_at, end_at, claim_token, remark, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $ins->execute([$storeId, $name, $type, $threshold, $amount, $totalCount, $perUser, $stackable, $startAt, $endAt, $token, $remark, $_SESSION['user_id'] ?? null]);
            $id = (int)$pdo->lastInsertId();
        }
        success(['id' => $id, 'message' => $id ? '已保存' : '创建失败']);
    }

    if ($action === 'toggle') {
        $id = (int)($input['id'] ?? 0);
        $status = $input['status'] === 'active' ? 'active' : 'paused';
        $upd = $pdo->prepare('UPDATE coupon_campaigns SET status=? WHERE id=? AND store_id=?');
        $upd->execute([$status, $id, $storeId]);
        success(['message' => $status === 'active' ? '已启用' : '已停用']);
    }

    if ($action === 'manual_issue') {
        $campaignId = (int)($input['campaign_id'] ?? 0);
        $phone = trim((string)($input['phone'] ?? ''));
        $qty = max(1, min(20, (int)($input['qty'] ?? 1)));
        if (!couponPhoneValid($phone)) error('手机号格式不正确');
        $stmt = $pdo->prepare('SELECT * FROM coupon_campaigns WHERE id=? AND store_id=?');
        $stmt->execute([$campaignId, $storeId]);
        $camp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$camp || !couponCampaignActive($camp)) error('活动不存在或未在有效期内');
        $issued = couponIssuedCount($pdo, $campaignId);
        if ((int)$camp['total_count'] > 0 && $issued + $qty > (int)$camp['total_count']) {
            error('超出总发行量，剩余 ' . max(0, (int)$camp['total_count'] - $issued) . ' 张');
        }
        $ins = $pdo->prepare(
            "INSERT INTO coupon_claims (store_id, campaign_id, phone, status, issue_type, issued_by, remark)
             VALUES (?,?,?, 'unused', 'manual', ?, ?)"
        );
        for ($i = 0; $i < $qty; $i++) {
            $ins->execute([$storeId, $campaignId, $phone, $_SESSION['user_id'] ?? null, '后台补发']);
        }
        success(['message' => '已补发 ' . $qty . ' 张给 ' . $phone]);
    }
    error('未知操作');
} catch (Exception $e) {
    logError($e->getMessage(), 'coupon_admin', ['store_id' => $storeId, 'action' => $action]);
    error($e->getMessage(), 500);
}
