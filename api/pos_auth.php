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
