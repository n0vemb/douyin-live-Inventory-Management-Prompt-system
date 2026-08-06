<?php
/**
 * list_vip_customers.php — VIP客户列表（按VIP编号聚合历史记录）
 * GET { keyword? }  按昵称/VIP编号模糊搜索
 * 返回：昵称（最近一次）、VIP编号、出现场次数、最近场次ID、最近场次名称
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$keyword = trim($_GET['keyword'] ?? '');

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$sql = "
    SELECT
        c.vip_no,
        (SELECT c2.nickname FROM live_ledger_customer c2
         WHERE c2.vip_no = c.vip_no AND c2.nickname != '' AND c2.session_id IN (SELECT id FROM live_ledger_session WHERE store_id = ?)
         ORDER BY c2.session_id DESC LIMIT 1) AS nickname,
        COUNT(DISTINCT c.session_id) AS session_count,
        COALESCE((SELECT SUM(i.sell_price * i.qty)
                  FROM live_ledger_item i
                  JOIN live_ledger_customer c3 ON i.customer_id = c3.id
                  JOIN live_ledger_session s2 ON i.session_id = s2.id
                  WHERE c3.vip_no = c.vip_no AND s2.store_id = ? AND i.is_gift = 0), 0) AS total_spent,
        MAX(c.session_id) AS last_session_id,
        (SELECT s.session_name FROM live_ledger_session s WHERE s.id = MAX(c.session_id)) AS last_session_name,
        MAX(c.created_at) AS last_used_at
    FROM live_ledger_customer c
    JOIN live_ledger_session s ON c.session_id = s.id
    WHERE s.store_id = ?
";
$params = [$storeId, $storeId, $storeId];

if ($keyword !== '') {
    $sql .= " AND (c.vip_no LIKE ? OR c.nickname LIKE ?)";
    $params[] = "%{$keyword}%";
    $params[] = "%{$keyword}%";
}

$sql .= "
    GROUP BY c.vip_no
    ORDER BY last_used_at DESC
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
