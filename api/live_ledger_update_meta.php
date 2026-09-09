<?php
/**
 * live_ledger_update_meta.php — 修改场次主播/运营/账号（限店管/超管）
 * POST { session_id, anchor?, operator?, account? }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'group_admin', 'super_admin'], true)) {
    error('无权限：仅店管/集团管理员/超管可修改场次信息', 403);
}
if (!$storeId) error('请先选择店铺');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$sessionId = (int)($input['session_id'] ?? 0);
if ($sessionId <= 0) error('缺少场次ID');

$sessionRow = requireLedgerSessionRow($pdo, $sessionId);
$snapshotJson = $sessionRow['snapshot_json'] ?? null;

$anchor = isset($input['anchor']) ? trim((string)$input['anchor']) : null;
$operator = isset($input['operator']) ? trim((string)$input['operator']) : null;
$account = isset($input['account']) ? trim((string)$input['account']) : null;
if ($anchor !== null && $anchor === '') $anchor = null;
if ($operator !== null && $operator === '') $operator = null;
if ($account !== null && $account === '') $account = null;

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare('UPDATE live_ledger_session SET anchor = ?, operator = ?, account = ? WHERE id = ? AND store_id = ?');
    $cur = $pdo->prepare('SELECT anchor, operator, account FROM live_ledger_session WHERE id = ? AND store_id = ?');
    $cur->execute([$sessionId, $storeId]);
    $old = $cur->fetch(PDO::FETCH_ASSOC);
    $newAnchor = $anchor !== null ? $anchor : $old['anchor'];
    $newOperator = $operator !== null ? $operator : $old['operator'];
    $newAccount = $account !== null ? $account : $old['account'];
    $upd->execute([$newAnchor, $newOperator, $newAccount, $sessionId, $storeId]);

    // 同步快照里的 settings（历史场次打开时展示用）
    if ($snapshotJson) {
        $snap = json_decode((string)$snapshotJson, true);
        if (is_array($snap)) {
            $snap['settings']['anchor'] = $newAnchor;
            $snap['settings']['operator'] = $newOperator;
            $snap['settings']['account'] = $newAccount;
            $pdo->prepare('UPDATE live_ledger_session SET snapshot_json = ? WHERE id = ?')
                ->execute([json_encode($snap, JSON_UNESCAPED_UNICODE), $sessionId]);
        }
    }
    $pdo->commit();
    success(['message' => '场次信息已更新']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logError($e->getMessage(), 'live_ledger_update_meta', ['session_id' => $sessionId]);
    error('更新失败: ' . $e->getMessage(), 500);
}
