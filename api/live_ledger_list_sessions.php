<?php
/**
 * live_ledger_list_sessions.php — 场次列表
 * GET （可选）status=active|ended|all
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$status = $_GET['status'] ?? 'all';
$sql = "SELECT s.id, s.session_name, s.anchor, s.operator, s.account, s.status, s.created_at, s.ended_at, s.total_qty, s.total_gmv,
    (SELECT COUNT(*) FROM live_ledger_lucky_draw ld WHERE ld.session_id = s.id AND ld.shipped = 0) AS unshipped_count
    FROM live_ledger_session s WHERE 1=1";
$params = [];
if ($storeId) { $sql .= " AND s.store_id = ?"; $params[] = $storeId; }
if ($status !== 'all') { $sql .= " AND status = ?"; $params[] = $status; }
$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

success(['data' => ['sessions' => $sessions]]);
