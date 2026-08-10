<?php
/**
 * update_vip_customer.php — 修改VIP客户（昵称 / VIP编号）
 * POST { old_vip_no, nickname?, new_vip_no? }
 * - 只给 nickname：更新该VIP所有历史记录的昵称
 * - 只给 new_vip_no：把该VIP所有记录改到新编号（合并）
 * - 两者都给：同时更新
 * 校验：新编号不能与其它VIP冲突（除非目标就是自己）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$oldVipNo = trim($input['old_vip_no'] ?? '');
$newVipNo = isset($input['new_vip_no']) ? trim($input['new_vip_no']) : null;
$nickname = isset($input['nickname']) ? trim($input['nickname']) : null;

if ($oldVipNo === '') {
    error('缺少VIP编号');
}
if ($nickname === null && $newVipNo === null) {
    error('没有需要更新的字段');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 确认该VIP在客户库（本店）或历史记录中存在
$stmt = $pdo->prepare("SELECT COUNT(*) FROM vip_customers WHERE store_id = ? AND vip_no = ?");
$stmt->execute([$storeId, $oldVipNo]);
$inLib = (int)$stmt->fetchColumn() > 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM live_ledger_customer c JOIN live_ledger_session s ON c.session_id = s.id WHERE c.vip_no = ? AND s.store_id = ?");
$stmt->execute([$oldVipNo, $storeId]);
$inHistory = (int)$stmt->fetchColumn() > 0;

if (!$inLib && !$inHistory) {
    error('未找到该VIP编号的客户');
}

// 换编号时检查目标编号冲突（本店客户库或历史已有且不是自己）
if ($newVipNo !== null && $newVipNo !== '' && $newVipNo !== $oldVipNo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vip_customers WHERE store_id = ? AND vip_no = ?");
    $stmt->execute([$storeId, $newVipNo]);
    if ((int)$stmt->fetchColumn() > 0) {
        error('新VIP编号已存在，如需合并请先删除目标客户');
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_ledger_customer c JOIN live_ledger_session s ON c.session_id = s.id WHERE c.vip_no = ? AND s.store_id = ?");
    $stmt->execute([$newVipNo, $storeId]);
    if ((int)$stmt->fetchColumn() > 0) {
        error('新VIP编号已存在，如需合并请先删除目标客户');
    }
}

// 更新昵称：客户库（本店） + 该VIP的所有历史记录
if ($nickname !== null && $nickname !== '') {
    if ($inLib) {
        $stmt = $pdo->prepare("UPDATE vip_customers SET nickname = ? WHERE store_id = ? AND vip_no = ?");
        $stmt->execute([$nickname, $storeId, $oldVipNo]);
    }
    $stmt = $pdo->prepare("UPDATE live_ledger_customer SET nickname = ? WHERE vip_no = ? AND session_id IN (SELECT id FROM live_ledger_session WHERE store_id = ?)");
    $stmt->execute([$nickname, $oldVipNo, $storeId]);
}

// 更新编号
if ($newVipNo !== null && $newVipNo !== '' && $newVipNo !== $oldVipNo) {
    if ($inLib) {
        $stmt = $pdo->prepare("UPDATE vip_customers SET vip_no = ? WHERE store_id = ? AND vip_no = ?");
        $stmt->execute([$newVipNo, $storeId, $oldVipNo]);
    }
    $stmt = $pdo->prepare("UPDATE live_ledger_customer SET vip_no = ? WHERE vip_no = ? AND session_id IN (SELECT id FROM live_ledger_session WHERE store_id = ?)");
    $stmt->execute([$newVipNo, $oldVipNo, $storeId]);
}

success(['message' => '客户已更新']);
