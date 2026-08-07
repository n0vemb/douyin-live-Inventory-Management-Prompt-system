<?php
/**
 * get_vip_spent.php — 返回当前店铺全部 VIP 客户的累计消费（用于出库记账页 VIP 分档配色）
 * GET 无需参数
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 按店铺隔离：通过 live_ledger_session 关联
$sql = "SELECT c.vip_no,
            COALESCE(SUM(CASE WHEN i.is_gift = 0 THEN i.sell_price * i.qty ELSE 0 END), 0) AS total_spent
        FROM live_ledger_customer c
        JOIN live_ledger_session s ON c.session_id = s.id
        LEFT JOIN live_ledger_item i ON i.customer_id = c.id
        WHERE s.store_id = ?
        GROUP BY c.vip_no";

$stmt = $pdo->prepare($sql);
$stmt->execute([$storeId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
foreach ($rows as $r) {
    $result[$r['vip_no']] = floatval($r['total_spent']);
}

success(['data' => ['spent_map' => $result]]);
