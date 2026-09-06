<?php
/**
 * pos_auth.php — 收银台公共会话辅助（免登录，token→session）
 * 收银台 admin/pos.php?t={pos_token} 验证 token 后写 $_SESSION['pos_store_id']
 * API 从 session 读取店铺；不信任前端传参
 */
require_once __DIR__ . '/../config.php';

function posStoreId() {
    // 支持 ?t= / token 参数直接验证（token 优先于 session：换链接即换店铺，避免 session 缓存旧店）
    $token = $_GET['t'] ?? ($_POST['token'] ?? '');
    if ($token !== '') {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM stores WHERE pos_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['pos_store_id'] = (int)$row['id'];
            return (int)$row['id'];
        }
        return null; // token 无效：不信任 session 残留，直接拒绝
    }
    // 无 token 时回退 session（API 请求场景）
    if (!empty($_SESSION['pos_store_id'])) return (int)$_SESSION['pos_store_id'];
    return null;
}

function requirePosStore() {
    $storeId = posStoreId();
    if (!$storeId) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => '无效的收银台链接，请从店铺设置重新获取']);
        exit;
    }
    return $storeId;
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
