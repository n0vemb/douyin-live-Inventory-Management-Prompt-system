<?php
/**
 * live_ledger_lookup_vip.php — 按VIP编号查询历史昵称
 * GET { vip_no }
 * 在该店铺全部历史场次的客户记录中匹配VIP编号，返回最近一次使用的昵称
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$vipNo = trim($_GET['vip_no'] ?? '');
if ($vipNo === '') {
    error('请输入VIP编号');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 跨所有历史场次查该VIP的最新昵称（按场次ID倒序取最近）
// 超管全平台（store_id=null）时跨所有店铺匹配
$sql = "SELECT c.nickname
        FROM live_ledger_customer c
        JOIN live_ledger_session s ON c.session_id = s.id
        WHERE c.vip_no = ? AND c.nickname != ''";
$params = [$vipNo];
if (!empty($storeId)) {
    $sql .= " AND s.store_id = ?";
    $params[] = $storeId;
}
$sql .= " ORDER BY s.id DESC LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch();

$nickname = $row ? $row['nickname'] : null;

// 直播场次无记录时，查客户管理表（vip_customers，本店）
if ($nickname === null) {
    if (!empty($storeId)) {
        $stmt = $pdo->prepare("SELECT nickname FROM vip_customers WHERE store_id = ? AND vip_no = ? LIMIT 1");
        $stmt->execute([$storeId, $vipNo]);
    } else {
        // 超管全平台：vip_no 现在按店隔离，同号可能多店——取任一条（本店优先不可行，取最新）
        $stmt = $pdo->prepare("SELECT nickname FROM vip_customers WHERE vip_no = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$vipNo]);
    }
    $nickname = $stmt->fetchColumn() ?: null;
}

success(['data' => ['nickname' => $nickname]]);
