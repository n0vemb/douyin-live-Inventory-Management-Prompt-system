<?php
/**
 * pos_auth.php — 收银台公共会话辅助（免登录，token→session）
 * 收银台 admin/pos.php?c={8位数字码}（店级码，可重置）
 * 旧链接 admin/pos.php?t={pos_token} 兼容：定位该集团“默认店”
 * API 从 session 读取店铺；不信任前端传参
 */
require_once __DIR__ . '/../config.php';

/**
 * 收银台身份上下文：优先按链接参数验证（?c=8位码 / 旧 ?t=token），
 * 无参数时回退 session。返回 store_id/shop_id/shop_name 或 null。
 */
function posAuthContext(): ?array {
    $token = $_GET['t'] ?? ($_POST['token'] ?? '');
    $code = $_GET['c'] ?? ($_POST['code'] ?? ($_POST['pos_code'] ?? ''));

    // ?c=8位数字码（店级入口）
    if ($code !== '') {
        $code = trim((string)$code);
        if (!preg_match('/^\d{8}$/', $code)) {
            return null;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT s.id AS store_id, sh.id AS shop_id, sh.name AS shop_name
                               FROM shops sh
                               JOIN stores s ON s.id = sh.store_id
                               WHERE sh.pos_code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['pos_store_id'] = (int)$row['store_id'];
            $_SESSION['pos_shop_id'] = (int)$row['shop_id'];
            $_SESSION['pos_shop_name'] = $row['shop_name'];
            return [
                'store_id' => (int)$row['store_id'],
                'shop_id' => (int)$row['shop_id'],
                'shop_name' => $row['shop_name'],
            ];
        }
        return null; // 码无效：不信任 session 残留，直接拒绝
    }

    // 旧 ?t=token：定位集团 → 默认店（兼容历史链接/二维码）
    if ($token !== '') {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE pos_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row) {
            $storeId = (int)$row['id'];
            // 旧 token 无店概念：定位该集团最早创建的店（默认店被改名也能命中）
            $shopStmt = $pdo->prepare('SELECT id, name FROM shops WHERE store_id = ? ORDER BY id ASC LIMIT 1');
            $shopStmt->execute([$storeId]);
            $shopRow = $shopStmt->fetch();
            if (!$shopRow) {
                return null;
            }
            $shopId = (int)$shopRow['id'];
            $shopName = $shopRow['name'] ?: '';
            $_SESSION['pos_store_id'] = $storeId;
            $_SESSION['pos_shop_id'] = $shopId;
            $_SESSION['pos_shop_name'] = $shopName;
            return ['store_id' => $storeId, 'shop_id' => $shopId, 'shop_name' => $shopName];
        }
        return null; // token 无效：不信任 session 残留，直接拒绝
    }

    // 无参数时回退 session（API 请求场景）
    if (!empty($_SESSION['pos_store_id']) && !empty($_SESSION['pos_shop_id'])) {
        return [
            'store_id' => (int)$_SESSION['pos_store_id'],
            'shop_id' => (int)$_SESSION['pos_shop_id'],
            'shop_name' => $_SESSION['pos_shop_name'] ?? '',
        ];
    }
    return null;
}

function posStoreId() {
    $ctx = posAuthContext();
    return $ctx ? (int)$ctx['store_id'] : null;
}

/** 当前收银台所属店ID */
function posShopId() {
    $ctx = posAuthContext();
    return $ctx ? (int)$ctx['shop_id'] : null;
}

function requirePosStore() {
    $ctx = posAuthContext();
    $storeId = $ctx ? (int)$ctx['store_id'] : null;
    if (!$storeId) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '无效的收银台链接/编码，请重新输入 8 位数字码']);
        exit;
    }
    return $storeId;
}

function requirePosShop() {
    $ctx = posAuthContext();
    if (!$ctx) {
        requirePosStore(); // 输出 401 后 exit
    }
    return (int)$ctx['shop_id'];
}

function posStaffActive() {
    return !empty($_SESSION['pos_staff']);
}

/**
 * 超时自动释放「未付款」订单（收银台多机兜底）
 * 顾客扫码下单后未点「已付款」就离开时，超过 $minutes 分钟自动释放锁定库存并作废订单。
 * 只处理 pay_status=pending 的订单；已收款/已出库订单不受影响。
 * 目录轮询（pos_catalog）每次请求会顺带调用一次，后台无需依赖人工打开页面。
 * 使用店铺级 GET_LOCK 防止多台收银机同时扫描同一批订单。
 */
function posAutoReleaseUnpaid($pdo, $storeId, $minutes = 15) {
    $storeId = (int)$storeId;
    $minutes = (int)$minutes;
    if ($storeId <= 0 || $minutes <= 0) return;
    $lockName = 'pp_pos_auto_release_' . $storeId;
    try {
        $acquired = (int)$pdo->query('SELECT GET_LOCK(' . $pdo->quote($lockName) . ', 0)')->fetchColumn();
        if ($acquired !== 1) return; // 其它请求正在处理

        $idsStmt = $pdo->prepare(
            "SELECT id FROM pos_orders
             WHERE store_id = ? AND pay_status = 'pending' AND outbound_status = 'pending'
               AND created_at < (NOW() - INTERVAL " . $minutes . " MINUTE)
             ORDER BY id ASC LIMIT 50"
        );
        $idsStmt->execute([$storeId]);
        $ids = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $oid) {
            $oid = (int)$oid;
            $pdo->beginTransaction();
            try {
                // 释放该订单的锁定批次
                $locks = $pdo->prepare('SELECT id, batch_id, qty FROM pos_order_locks WHERE order_id = ? AND status = ? FOR UPDATE');
                $locks->execute([$oid, 'locked']);
                $relBatch = $pdo->prepare('UPDATE inventory_batches SET locked_qty = GREATEST(locked_qty - ?, 0) WHERE id = ?');
                $relLock = $pdo->prepare('UPDATE pos_order_locks SET status = ? WHERE id = ?');
                foreach ($locks->fetchAll() as $lk) {
                    $relBatch->execute([(int)$lk['qty'], (int)$lk['batch_id']]);
                    $relLock->execute(['released', (int)$lk['id']]);
                }
                // 未付款订单释放：占用中的优惠券退回可用
                $pdo->prepare(
                    "UPDATE coupon_claims SET status = 'unused', released_at = NOW(), order_id = NULL, order_no = NULL
                     WHERE order_id = ? AND status = 'locked'"
                )->execute([$oid]);
                // 条件更新：若顾客恰好在释放瞬间完成付款则跳过，避免误作废已收款订单
                $upd = $pdo->prepare(
                    "UPDATE pos_orders SET outbound_status = 'voided', void_reason = '超过" . $minutes . "分钟未付款，自动释放库存', completed_at = NOW()
                     WHERE id = ? AND pay_status = 'pending' AND outbound_status = 'pending'"
                );
                $upd->execute([$oid]);
                if ($upd->rowCount() === 0) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    continue;
                }
                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                logError($e->getMessage(), 'pos_auto_release', ['order_id' => $oid]);
            }
        }
    } catch (Exception $e) {
        logError($e->getMessage(), 'pos_auto_release', ['store_id' => $storeId]);
    } finally {
        try {
            $pdo->query('SELECT RELEASE_LOCK(' . $pdo->quote($lockName) . ')');
        } catch (Exception $e) {
            // 锁会随连接关闭自动释放，忽略
        }
    }
}

/** 品相中文名（店铺配置，缺省用默认） */
function conditionNames($pdo, $storeId) {
    $defaults = ['sealed' => '原盒未拆', 'opened' => '拆盒无瑕', 'boxless' => '无盒无瑕', 'flawed' => '微瑕'];
    $stmt = $pdo->prepare('SELECT condition_types FROM stores WHERE id = ?');
    $stmt->execute([$storeId]);
    $row = $stmt->fetch();
    if ($row && $row['condition_types']) {
        $types = json_decode($row['condition_types'], true);
        if (is_array($types) && $types) {
            $map = [];
            foreach ($types as $t) {
                if (!empty($t['key'])) $map[$t['key']] = $t['name'];
            }
            if ($map) return $map;
        }
    }
    return $defaults;
}

/** 生成订单号 OFF + 时间戳 + 随机2位 */
function posOrderNo() {
    return 'OFF' . date('YmdHis') . str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);
}
