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

// ===== 跨场次占用（防超卖）：其他 active 场次对同一商品+SKU 的已记账数量 =====
// 多运营账户可同时开多个 active 场次，同一商品可能被两边都记账，
// 结束出库 FIFO 只扣实际库存，先结束的扣走，后结束的就会库存不足。
// 前端 getReservedBySku 必须把其他 active 场次的占用也算进去，才能提前拦截。
$otherReserved = [];
$stmt = $pdo->prepare("
    SELECT i.product_id, i.condition_type, SUM(i.qty) AS qty
    FROM live_ledger_item i
    JOIN live_ledger_session s ON s.id = i.session_id
    WHERE s.store_id = ? AND s.status = 'active' AND s.id != ? AND i.is_gift = 0
    GROUP BY i.product_id, i.condition_type
");
$stmt->execute([$storeId, $sessionId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $otherReserved[$row['product_id'] . '|' . ($row['condition_type'] ?? '')] = (int)$row['qty'];
}
$data['other_reserved'] = $otherReserved;

success(['data' => $data]);
