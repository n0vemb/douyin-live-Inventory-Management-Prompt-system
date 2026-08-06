<?php
/**
 * live_ledger_get_session.php — 加载场次全部数据
 * GET ?session_id=X
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/live_ledger_common.php';
require_once __DIR__ . '/permission_helper.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
if ($sessionId <= 0) error('缺少场次ID');

$data = ledgerLoadSession($pdo, $sessionId);
if (!$data) error('场次不存在');

// 校验店铺归属
$stmt = $pdo->prepare("SELECT store_id FROM live_ledger_session WHERE id = ?");
$stmt->execute([$sessionId]);
$owner = $stmt->fetchColumn();
if ($owner != $storeId) error('无权访问该场次');

// 运营角色：隐藏成本/毛利
maskLedgerData($data);

success(['data' => $data]);
