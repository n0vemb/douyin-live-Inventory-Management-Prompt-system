<?php
/**
 * list_vip_customers.php — VIP客户列表（以 vip_customers 客户库为全量主表）
 * GET { keyword? }  按昵称/VIP编号模糊搜索
 * 返回：昵称（客户库为准）、VIP编号、累计消费（实时聚合）、出现场次数、最近场次名称
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$keyword = trim($_GET['keyword'] ?? '');

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 超管全平台（store_id=null）不显示客户列表（用户 2026-08-10 确认：切换店铺后才可见）
if (empty($storeId)) {
    success(['data' => ['customers' => []]]);
}

$sql = "
    SELECT
        vc.vip_no,
        vc.nickname,
        COALESCE((
            SELECT SUM(i.sell_price * i.qty)
            FROM live_ledger_item i
            JOIN live_ledger_customer c3 ON i.customer_id = c3.id
            JOIN live_ledger_session s2 ON i.session_id = s2.id
            WHERE c3.vip_no = vc.vip_no AND s2.store_id = ? AND i.is_gift = 0
        ), 0) AS total_spent,
        (
            SELECT COUNT(DISTINCT c2.session_id)
            FROM live_ledger_customer c2
            JOIN live_ledger_session s3 ON c2.session_id = s3.id
            WHERE c2.vip_no = vc.vip_no AND s3.store_id = ?
        ) AS session_count,
        (
            SELECT c4.session_id FROM live_ledger_customer c4
            JOIN live_ledger_session s4 ON c4.session_id = s4.id
            WHERE c4.vip_no = vc.vip_no AND s4.store_id = ?
            ORDER BY s4.created_at DESC LIMIT 1
        ) AS last_session_id,
        (
            SELECT s5.session_name FROM live_ledger_session s5
            WHERE s5.id = (
                SELECT c5.session_id FROM live_ledger_customer c5
                JOIN live_ledger_session s6 ON c5.session_id = s6.id
                WHERE c5.vip_no = vc.vip_no AND s6.store_id = ?
                ORDER BY s6.created_at DESC LIMIT 1
            )
        ) AS last_session_name,
        (
            SELECT MAX(c7.created_at) FROM live_ledger_customer c7
            JOIN live_ledger_session s7 ON c7.session_id = s7.id
            WHERE c7.vip_no = vc.vip_no AND s7.store_id = ?
        ) AS last_used_at
    FROM vip_customers vc
    WHERE vc.store_id = ?
";
$params = [$storeId, $storeId, $storeId, $storeId, $storeId, $storeId];

if ($keyword !== '') {
    $sql .= " AND (vc.vip_no LIKE ? OR vc.nickname LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}

$sql .= "
    ORDER BY CAST(vc.vip_no AS UNSIGNED) ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

// 确保字段类型
foreach ($customers as &$c) {
    $c['session_count'] = (int)$c['session_count'];
    $c['last_session_id'] = (int)$c['last_session_id'];
    $c['vip_no'] = (string)$c['vip_no'];
    $c['nickname'] = (string)($c['nickname'] ?? '');
    $c['last_session_name'] = (string)($c['last_session_name'] ?? '');
    $c['total_spent'] = (float)$c['total_spent'];
}
unset($c);

success(['data' => ['customers' => $customers]]);
