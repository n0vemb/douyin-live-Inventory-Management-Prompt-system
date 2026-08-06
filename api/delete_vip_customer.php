<?php
/**
 * delete_vip_customer.php — 删除VIP客户（该VIP编号的全部历史记录）
 * POST { vip_no }
 * 只删 live_ledger_customer 记录，不动场次/商品/赠品数据
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$vipNo = trim($input['vip_no'] ?? '');

if ($vipNo === '') {
    error('缺少VIP编号');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

// 确认存在
$stmt = $pdo->prepare("SELECT COUNT(*) FROM live_ledger_customer c JOIN live_ledger_session s ON c.session_id = s.id WHERE c.vip_no = ? AND s.store_id = ?");
$stmt->execute([$vipNo, $storeId]);
if ((int)$stmt->fetchColumn() === 0) {
    error('未找到该VIP编号的客户');
}

// 删除该VIP在本店的全部客户记录
$stmt = $pdo->prepare("DELETE c FROM live_ledger_customer c JOIN live_ledger_session s ON c.session_id = s.id WHERE c.vip_no = ? AND s.store_id = ?");
$stmt->execute([$vipNo, $storeId]);

success(['message' => '客户已删除']);
