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
$sql = "SELECT id, session_name, status, created_at, ended_at, total_qty, total_gmv FROM live_ledger_session WHERE 1=1";
$params = [];
if ($storeId) { $sql .= " AND store_id = ?"; $params[] = $storeId; }
if ($status !== 'all') { $sql .= " AND status = ?"; $params[] = $status; }
$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

success(['data' => ['sessions' => $sessions]]);
