<?php
/**
 * live_ledger_update_meta.php — 修改场次信息（主播/运营/账号 + 归属店铺）
 * POST { session_id, anchor?, operator?, account?, shop_id? }
 * shop_id 仅超管/集团管理员可改；店管只能改文字信息
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/session_shop_lib.php';

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
$newShopId = isset($input['shop_id']) && $input['shop_id'] !== '' ? (int)$input['shop_id'] : 0;
if ($anchor !== null && $anchor === '') $anchor = null;
if ($operator !== null && $operator === '') $operator = null;
if ($account !== null && $account === '') $account = null;

// 归属店变更：仅超管/集团管理员，且必须同集团（跨集团迁移走独立脚本）
$shopChanged = 0;
$shopChangeNeeded = $newShopId > 0 && $newShopId !== (int)($sessionRow['shop_id'] ?? 0);
if ($shopChangeNeeded && !in_array($_SESSION['role'] ?? '', ['group_admin', 'super_admin'], true)) {
    error('无权限：仅集团管理员/超管可修改场次归属店铺', 403);
}
if ($shopChangeNeeded) {
    try {
        $res = migrateLedgerSessionShop($pdo, $sessionId, $newShopId, false);
        $shopChanged = !empty($res['moved']) ? 1 : 0;
    } catch (Exception $e) {
        error($e->getMessage());
    }
    $stmt = $pdo->prepare('SELECT snapshot_json FROM live_ledger_session WHERE id = ?');
    $stmt->execute([$sessionId]);
    $snapshotJson = $stmt->fetchColumn();
}

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
    success(['message' => $shopChanged ? '场次信息与归属店铺已更新' : '场次信息已更新']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logError($e->getMessage(), 'live_ledger_update_meta', ['session_id' => $sessionId]);
    error('更新失败: ' . $e->getMessage(), 500);
}
