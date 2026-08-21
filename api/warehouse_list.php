<?php
/**
 * warehouse_list.php — 仓库出库台任务列表
 * GET: 返回 pending（待出库/待回库）+ 最近 done（已处理），按场次分组所需字段
 * 权限：超管/店管/仓库；运营不可用（requireWarehouseAccess）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireWarehouseAccess();
$storeId = getStoreId();

$sql = "
SELECT wt.id, wt.source_type, wt.source_id, wt.customer_id, wt.product_id,
       wt.product_name, wt.condition_type, wt.qty, wt.is_gift, wt.type, wt.status,
       wt.done_at, wt.created_at,
       s.id AS session_id, s.session_name, s.status AS session_status,
       c.nickname, c.vip_no
FROM warehouse_task wt
LEFT JOIN live_ledger_session s ON wt.session_id = s.id
LEFT JOIN live_ledger_customer c ON wt.customer_id = c.id
WHERE 1=1";
$params = [];
if ($storeId) {
    $sql .= " AND wt.store_id = ?";
    $params[] = $storeId;
}
// 只返回 pending + 最近 7 天的 done（cancelled 撤回单不显示）
$sql .= " AND (wt.status = 'pending' OR (wt.status = 'done' AND wt.done_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)))";
// 排序：pending 在前（组内按场次活跃+session_id+时间倒序），done 在后按处理时间倒序
$sql .= " ORDER BY (wt.status = 'done') ASC, (s.status = 'active') DESC, wt.session_id ASC, (CASE WHEN wt.status = 'done' THEN wt.done_at ELSE wt.created_at END) DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// done 最多保留 200 条
$doneCount = 0;
$result = [];
foreach ($items as $item) {
    if ($item['status'] === 'done') {
        $doneCount++;
        if ($doneCount > 200) continue;
    }
    $result[] = $item;
}

success(['data' => ['items' => $result]]);
