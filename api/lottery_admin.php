<?php
/**
 * lottery_admin.php — 收银台抽奖后台管理
 * 权限：店管 / 集团管理员 / 超管（活动按店归属）
 * action=list / save / toggle / delete / prizes / save_prize / delete_prize / toggle_prize
 *        draws / void_draw / options
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/coupon_lib.php';
require_once __DIR__ . '/lottery_lib.php';
require_once __DIR__ . '/condition_common.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
if (!$storeId) error('请先选择店铺');
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'group_admin', 'super_admin'], true)) error('无权限', 403);

$method = $_SERVER['REQUEST_METHOD'];
$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$action = $input['action'] ?? 'list';

// 店级账号固定本店；集团管理员/超管按请求里的 shop_id 指定目标店
$scopeShop = getShopId();
$canPickShop = in_array($_SESSION['role'] ?? '', ['group_admin', 'super_admin'], true);
if (!$scopeShop && $canPickShop) {
    $reqShop = (int)($input['shop_id'] ?? 0);
    if ($reqShop > 0) {
        $chk = $pdo->prepare('SELECT id FROM shops WHERE id = ? AND store_id = ?');
        $chk->execute([$reqShop, $storeId]);
        if ($chk->fetch()) $scopeShop = $reqShop;
    }
}

/**
 * 同一归属（门店专属 或 集团通用）下正在生效的其它活动（方案B：同归属只允许一个进行中）
 * 只统计「在有效期时间窗内」的活动，已过期的 active 不阻塞新建
 * @return array|null ['id'=>, 'name'=>]
 */
function lotteryConflictActive(PDO $pdo, $storeId, $shopId, $excludeId = 0) {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        "SELECT id, name FROM lottery_campaigns
         WHERE store_id = ? AND status = 'active' AND id <> ? AND (shop_id <=> ?)
           AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at >= ?)
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([
        (int)$storeId, (int)$excludeId,
        $shopId === null || $shopId === '' ? null : (int)$shopId,
        $now, $now,
    ]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** 冲突响应：前端据此弹出「暂停它并启用本活动」的确认 */
function lotteryConflict(array $conflict, $scopeLabel = '该归属', $scopeKey = 'shop') {
    http_response_code(409);
    jsonResponse([
        'success' => false,
        'error' => $scopeLabel . '已有进行中的活动「' . $conflict['name'] . '」，请先暂停它',
        'conflict' => [
            'id' => (int)$conflict['id'],
            'name' => (string)$conflict['name'],
            'scope' => $scopeKey, // shop=门店专属 / group=集团通用，前端据此换文案
        ],
    ]);
}

/** 取活动并校验归属（返回 null 表示不存在/越权） */
function lotteryAdminCampaign(PDO $pdo, $campaignId, $storeId, $scopeShop) {
    $sql = 'SELECT * FROM lottery_campaigns WHERE id = ? AND store_id = ?';
    $params = [(int)$campaignId, (int)$storeId];
    if ($scopeShop) { $sql .= ' AND (shop_id = ? OR shop_id IS NULL)'; $params[] = (int)$scopeShop; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

try {
    if ($action === 'list') {
        $sql = "SELECT c.*, sh.name AS shop_name,
                    (SELECT COUNT(*) FROM lottery_prizes p WHERE p.campaign_id = c.id) AS prize_count,
                    (SELECT COUNT(*) FROM lottery_draws d WHERE d.campaign_id = c.id AND d.claim_status <> 'voided') AS draw_count,
                    (SELECT COUNT(*) FROM lottery_draws d WHERE d.campaign_id = c.id AND d.claim_status = 'claimed') AS claimed_count
                FROM lottery_campaigns c
                LEFT JOIN shops sh ON sh.id = c.shop_id
                WHERE c.store_id = ?";
        $params = [$storeId];
        if ($scopeShop) { $sql .= ' AND (c.shop_id = ? OR c.shop_id IS NULL)'; $params[] = $scopeShop; }
        $sql .= ' ORDER BY c.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // 标记「当前生效」（收银台实际会用的那一个）
        foreach ($campaigns as &$c) {
            $eff = lotteryActiveCampaign($pdo, $storeId, $c['shop_id'] !== null ? (int)$c['shop_id'] : 0);
            $c['is_effective'] = ($eff && (int)$eff['id'] === (int)$c['id']) ? 1 : 0;
        }
        unset($c);
        success(['campaigns' => $campaigns]);
    }

    if ($action === 'options') {
        // 券活动（可作奖品）
        $cpSql = "SELECT id, name, coupon_type, threshold, amount, total_count, start_at, end_at, status
                  FROM coupon_campaigns WHERE store_id = ? AND status = 'active'";
        $cpParams = [$storeId];
        if ($scopeShop) { $cpSql .= ' AND shop_id = ?'; $cpParams[] = $scopeShop; }
        $cpSql .= ' ORDER BY id DESC LIMIT 200';
        $cpStmt = $pdo->prepare($cpSql);
        $cpStmt->execute($cpParams);
        $coupons = [];
        $now = date('Y-m-d H:i:s');
        foreach ($cpStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $c['active_now'] = couponCampaignActive($c, $now) ? 1 : 0;
            $c['issued'] = couponIssuedCount($pdo, (int)$c['id']);
            $coupons[] = $c;
        }
        // 商品（含各品相可售库存）
        $condNames = conditionNames($pdo, $storeId);
        $pStmt = $pdo->prepare(
            "SELECT p.id, p.name, p.series, p.brand, b.condition_type,
                    SUM(b.remaining_qty - b.locked_qty) AS avail
             FROM products p
             JOIN inventory_batches b ON b.product_id = p.id AND b.store_id = p.store_id
             WHERE p.store_id = ? AND b.remaining_qty - b.locked_qty > 0
             GROUP BY p.id, p.name, p.series, p.brand, b.condition_type
             ORDER BY p.series, p.name"
        );
        $pStmt->execute([$storeId]);
        $products = [];
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $pid = (int)$r['id'];
            if (!isset($products[$pid])) {
                $products[$pid] = ['id' => $pid, 'name' => $r['name'], 'series' => $r['series'] ?? '', 'skus' => []];
            }
            $products[$pid]['skus'][] = [
                'condition_type' => $r['condition_type'],
                'cond_name' => $condNames[$r['condition_type']] ?? $r['condition_type'],
                'stock' => (int)$r['avail'],
            ];
        }
        // 门店列表（集团视角选归属店）
        $shops = [];
        if ($canPickShop) {
            $shStmt = $pdo->prepare('SELECT id, name FROM shops WHERE store_id = ? ORDER BY id');
            $shStmt->execute([$storeId]);
            $shops = $shStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        success([
            'coupons' => $coupons,
            'products' => array_values($products),
            'shops' => $shops,
            'can_pick_shop' => $canPickShop ? 1 : 0,
            'scope_shop' => $scopeShop ?: null,
        ]);
    }

    if ($action === 'prizes') {
        $campaignId = (int)($input['campaign_id'] ?? 0);
        $camp = lotteryAdminCampaign($pdo, $campaignId, $storeId, $scopeShop);
        if (!$camp) error('活动不存在');
        $stmt = $pdo->prepare('SELECT * FROM lottery_prizes WHERE campaign_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$campaignId]);
        $prizes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 与收银台同一套逻辑：后台看到的扇区就是转盘上的扇区，避免「配了 4 个只见 1 个」
        $campStoreId = (int)$camp['store_id'];
        $segments = lotteryBuildSegments($pdo, $camp, $campStoreId, false);
        $segIds = [];
        foreach ($segments as $sg) {
            if (!empty($sg['id'])) $segIds[(int)$sg['id']] = true;
        }
        foreach ($prizes as &$p) {
            $why = '';
            $ok = lotteryPrizeAvailability($pdo, $p, $campStoreId, $camp, false, $why);
            $onWheel = isset($segIds[(int)$p['id']]) ? 1 : 0;
            $offWhy = '';
            if ((int)$p['status'] !== 1) $offWhy = '已停用';
            elseif ((int)$p['weight'] <= 0) $offWhy = '权重为 0，不上转盘';
            elseif (!$ok) $offWhy = $why;
            elseif (!$onWheel) $offWhy = '未进入转盘';
            $p['weight'] = (int)$p['weight'];
            $p['avail'] = $ok ? 1 : 0;
            $p['avail_why'] = $ok ? '' : $why;
            $p['on_wheel'] = $onWheel;
            $p['off_why'] = $offWhy;
        }
        unset($p);
        success(['prizes' => $prizes, 'segments' => $segments]);
    }

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') error('请填写活动名称');
        $threshold = round((float)($input['threshold'] ?? 0), 2);
        if ($threshold < 0) $threshold = 0;
        $spinAgain = max(0, min(9, (int)($input['spin_again_limit'] ?? 2)));
        $startAt = trim((string)($input['start_at'] ?? ''));
        $endAt = trim((string)($input['end_at'] ?? ''));
        $remark = trim((string)($input['remark'] ?? ''));
        $guarantee = (int)($input['guarantee_prize_id'] ?? 0);

        if ($id > 0) {
            if (!lotteryAdminCampaign($pdo, $id, $storeId, $scopeShop)) error('活动不存在');
            $stmt = $pdo->prepare(
                'UPDATE lottery_campaigns SET name = ?, threshold = ?, spin_again_limit = ?, guarantee_prize_id = ?,
                        start_at = ?, end_at = ?, remark = ? WHERE id = ? AND store_id = ?'
            );
            $stmt->execute([
                $name, $threshold, $spinAgain, $guarantee ?: null,
                $startAt !== '' ? $startAt : null, $endAt !== '' ? $endAt : null,
                $remark !== '' ? $remark : null, $id, $storeId,
            ]);
            success(['id' => $id]);
        }
        // 新建：集团/超管需指定归属店（0=全部门店）
        $targetShop = $scopeShop ? (int)$scopeShop : (int)($input['shop_id'] ?? 0);
        if (!$targetShop && !$canPickShop) error('请先选择活动归属的店铺');
        if ($targetShop) {
            $chk = $pdo->prepare('SELECT id FROM shops WHERE id = ? AND store_id = ?');
            $chk->execute([$targetShop, $storeId]);
            if (!$chk->fetch()) error('归属店铺无效');
        }
        // 新建活动默认进行中：同归属只允许一个进行中的活动（方案B）
        $conflict = lotteryConflictActive($pdo, $storeId, $targetShop ?: null, 0);
        if ($conflict) {
            if (empty($input['replace_active'])) {
                lotteryConflict($conflict, $targetShop ? '该门店' : '集团通用', $targetShop ? 'shop' : 'group');
            }
            $pdo->prepare("UPDATE lottery_campaigns SET status = 'paused' WHERE id = ?")->execute([(int)$conflict['id']]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO lottery_campaigns (store_id, shop_id, name, threshold, spin_again_limit, guarantee_prize_id, start_at, end_at, remark, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $storeId, $targetShop ?: null, $name, $threshold, $spinAgain, $guarantee ?: null,
            $startAt !== '' ? $startAt : null, $endAt !== '' ? $endAt : null,
            $remark !== '' ? $remark : null, $_SESSION['user_id'] ?? null,
        ]);
        success(['id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'toggle') {
        $id = (int)($input['id'] ?? 0);
        $camp = lotteryAdminCampaign($pdo, $id, $storeId, $scopeShop);
        if (!$camp) error('活动不存在');
        $status = $camp['status'] === 'active' ? 'paused' : 'active';
        $pausedId = 0;
        if ($status === 'active') {
            $conflict = lotteryConflictActive($pdo, $storeId, $camp['shop_id'], $id);
            if ($conflict) {
                if (empty($input['replace_active'])) {
                    lotteryConflict($conflict, $camp['shop_id'] ? '该门店' : '集团通用', $camp['shop_id'] ? 'shop' : 'group');
                }
                $pdo->prepare("UPDATE lottery_campaigns SET status = 'paused' WHERE id = ?")->execute([(int)$conflict['id']]);
                $pausedId = (int)$conflict['id'];
            }
        }
        $pdo->prepare('UPDATE lottery_campaigns SET status = ? WHERE id = ?')->execute([$status, $id]);
        success(['status' => $status, 'paused_id' => $pausedId]);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if (!lotteryAdminCampaign($pdo, $id, $storeId, $scopeShop)) error('活动不存在');
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM lottery_draws WHERE campaign_id = ?');
        $cnt->execute([$id]);
        if ((int)$cnt->fetchColumn() > 0) error('已有抽奖记录，不能删除；可改为「暂停」');
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM lottery_prizes WHERE campaign_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM lottery_campaigns WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        success(['deleted' => true]);
    }

    if ($action === 'save_prize') {
        $campaignId = (int)($input['campaign_id'] ?? 0);
        $camp = lotteryAdminCampaign($pdo, $campaignId, $storeId, $scopeShop);
        if (!$camp) error('活动不存在');
        $id = (int)($input['id'] ?? 0);
        $name = trim((string)($input['name'] ?? ''));
        $type = (string)($input['prize_type'] ?? 'none');
        if (!in_array($type, ['coupon', 'product', 'custom', 'spin_again', 'none'], true)) error('奖品类型无效');
        if ($name === '') error('请填写奖品名称');
        $weight = max(0, min(1000, (int)($input['weight'] ?? 0)));
        $quota = max(0, (int)($input['quota'] ?? 0));
        $couponCampaignId = (int)($input['coupon_campaign_id'] ?? 0);
        $productId = (int)($input['product_id'] ?? 0);
        $cond = trim((string)($input['condition_type'] ?? ''));
        $qty = max(1, min(999, (int)($input['qty'] ?? 1)));
        $note = trim((string)($input['custom_note'] ?? ''));
        $sort = (int)($input['sort_order'] ?? 0);
        $status = (int)($input['status'] ?? 1) ? 1 : 0;

        if ($type === 'coupon') {
            if ($couponCampaignId <= 0) error('请选择券奖品对应的券活动');
            $chk = $pdo->prepare('SELECT id FROM coupon_campaigns WHERE id = ? AND store_id = ?');
            $chk->execute([$couponCampaignId, $storeId]);
            if (!$chk->fetch()) error('券活动不存在');
        } elseif ($type === 'product') {
            if ($productId <= 0 || $cond === '') error('请选择商品奖品的商品与品相');
            $chk = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
            $chk->execute([$productId, $storeId]);
            if (!$chk->fetch()) error('商品不存在');
        } elseif ($type === 'custom' && $note === '') {
            error('请填写自定义奖品说明（门店待出库登记用）');
        }

        $params = [
            $name, $type, $weight, $quota,
            $type === 'coupon' ? $couponCampaignId : null,
            $type === 'product' ? $productId : null,
            $type === 'product' ? $cond : null,
            $type === 'product' ? $qty : 1,
            $note !== '' ? $note : null,
            $sort, $status,
        ];
        if ($id > 0) {
            $own = $pdo->prepare('SELECT id FROM lottery_prizes WHERE id = ? AND campaign_id = ?');
            $own->execute([$id, $campaignId]);
            if (!$own->fetch()) error('奖品不存在');
            $stmt = $pdo->prepare(
                'UPDATE lottery_prizes SET name = ?, prize_type = ?, weight = ?, quota = ?, coupon_campaign_id = ?,
                        product_id = ?, condition_type = ?, qty = ?, custom_note = ?, sort_order = ?, status = ?
                 WHERE id = ? AND campaign_id = ?'
            );
            $stmt->execute(array_merge($params, [$id, $campaignId]));
            success(['id' => $id]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO lottery_prizes (campaign_id, name, prize_type, weight, quota, coupon_campaign_id,
                                         product_id, condition_type, qty, custom_note, sort_order, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array_merge([$campaignId], $params));
        success(['id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'toggle_prize') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT p.*, c.store_id, c.shop_id FROM lottery_prizes p JOIN lottery_campaigns c ON c.id = p.campaign_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $prize = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prize || (int)$prize['store_id'] !== (int)$storeId) error('奖品不存在');
        if ($scopeShop && $prize['shop_id'] && (int)$prize['shop_id'] !== (int)$scopeShop) error('无权限');
        $status = (int)$prize['status'] ? 0 : 1;
        $pdo->prepare('UPDATE lottery_prizes SET status = ? WHERE id = ?')->execute([$status, $id]);
        success(['status' => $status]);
    }

    if ($action === 'delete_prize') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT p.*, c.store_id, c.shop_id FROM lottery_prizes p JOIN lottery_campaigns c ON c.id = p.campaign_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $prize = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prize || (int)$prize['store_id'] !== (int)$storeId) error('奖品不存在');
        if ($scopeShop && $prize['shop_id'] && (int)$prize['shop_id'] !== (int)$scopeShop) error('无权限');
        if ((int)$prize['won'] > 0) error('该奖品已中出，不能删除；可改为「停用」');
        // 作为保底奖品时先解除引用
        $pdo->prepare('UPDATE lottery_campaigns SET guarantee_prize_id = NULL WHERE guarantee_prize_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM lottery_prizes WHERE id = ?')->execute([$id]);
        success(['deleted' => true]);
    }

    if ($action === 'draws') {
        $campaignId = (int)($input['campaign_id'] ?? 0);
        $phone = trim((string)($input['phone'] ?? ''));
        $sql = "SELECT d.*, sh.name AS shop_name FROM lottery_draws d
                LEFT JOIN shops sh ON sh.id = d.shop_id
                WHERE d.store_id = ?";
        $params = [$storeId];
        if ($scopeShop) { $sql .= ' AND (d.shop_id = ? OR d.shop_id IS NULL)'; $params[] = $scopeShop; }
        if ($campaignId > 0) { $sql .= ' AND d.campaign_id = ?'; $params[] = $campaignId; }
        if ($phone !== '') { $sql .= ' AND d.phone = ?'; $params[] = $phone; }
        $sql .= ' ORDER BY d.id DESC LIMIT 300';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $statSql = "SELECT COUNT(*) AS total,
                           SUM(CASE WHEN claim_status = 'voided' THEN 1 ELSE 0 END) AS voided,
                           SUM(CASE WHEN claim_status = 'pending' THEN 1 ELSE 0 END) AS pending,
                           SUM(CASE WHEN prize_type NOT IN ('none','spin_again') AND claim_status = 'claimed' THEN 1 ELSE 0 END) AS won
                    FROM lottery_draws WHERE store_id = ?";
        $statParams = [$storeId];
        if ($scopeShop) { $statSql .= ' AND (shop_id = ? OR shop_id IS NULL)'; $statParams[] = $scopeShop; }
        if ($campaignId > 0) { $statSql .= ' AND campaign_id = ?'; $statParams[] = $campaignId; }
        $st = $pdo->prepare($statSql);
        $st->execute($statParams);
        success(['draws' => $rows, 'stats' => $st->fetch(PDO::FETCH_ASSOC)]);
    }

    if ($action === 'void_draw') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM lottery_draws WHERE id = ? AND store_id = ?');
        $stmt->execute([$id, $storeId]);
        $draw = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$draw) error('抽奖记录不存在');
        if ($scopeShop && $draw['shop_id'] && (int)$draw['shop_id'] !== (int)$scopeShop) error('无权限');
        if ($draw['claim_status'] === 'voided') error('该记录已作废');
        $pdo->beginTransaction();
        try {
            if ((int)$draw['coupon_claim_id'] > 0) {
                $pdo->prepare("UPDATE coupon_claims SET status = 'expired', released_at = NOW() WHERE id = ? AND status = 'unused'")
                    ->execute([(int)$draw['coupon_claim_id']]);
            }
            if ((int)$draw['prize_order_id'] > 0) {
                $po = $pdo->prepare('SELECT id, outbound_status FROM pos_orders WHERE id = ? FOR UPDATE');
                $po->execute([(int)$draw['prize_order_id']]);
                $poRow = $po->fetch(PDO::FETCH_ASSOC);
                if ($poRow && $poRow['outbound_status'] === 'pending') {
                    lotteryReleaseOrderLocks($pdo, (int)$poRow['id']);
                    $pdo->prepare("UPDATE pos_orders SET outbound_status = 'voided', void_reason = '抽奖记录作废', completed_at = NOW() WHERE id = ?")
                        ->execute([(int)$poRow['id']]);
                }
            }
            if ((int)$draw['prize_id'] > 0 && $draw['prize_type'] !== 'spin_again') {
                $pdo->prepare('UPDATE lottery_prizes SET won = GREATEST(won - 1, 0) WHERE id = ?')->execute([(int)$draw['prize_id']]);
            }
            $pdo->prepare("UPDATE lottery_draws SET claim_status = 'voided' WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        success(['voided' => true]);
    }

    error('未知操作');
} catch (Exception $e) {
    logError($e->getMessage(), 'lottery_admin', ['action' => $action]);
    error($e->getMessage(), 400);
}
