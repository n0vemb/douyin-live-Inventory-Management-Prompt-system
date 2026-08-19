<?php
/**
 * live_ledger_lucky_draw_ship.php — 标记福袋为已寄出
 * POST { id: 福袋记录ID }
 * 不校验场次 status：福袋寄出不受直播结束影响，已结束场次仍可标记
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    error('无效请求');
}

$id = isset($input['id']) ? (int)$input['id'] : 0;
if ($id <= 0) error('缺少福袋记录ID');

// 校验归属：福袋 -> 场次 -> 店铺
$stmt = $pdo->prepare("
    SELECT ld.id, ld.shipped, ld.shipped_at
    FROM live_ledger_lucky_draw ld
    JOIN live_ledger_session s ON ld.session_id = s.id
    WHERE ld.id = ? AND s.store_id = ?
");
$stmt->execute([$id, $storeId]);
$draw = $stmt->fetch();
if (!$draw) error('福袋记录不存在');

// 已寄出则幂等返回
if ((int)$draw['shipped'] === 1) {
    success(['data' => ['id' => $id, 'shipped' => 1, 'shipped_at' => $draw['shipped_at']]]);
}

$stmt = $pdo->prepare("UPDATE live_ledger_lucky_draw SET shipped = 1, shipped_at = NOW() WHERE id = ?");
$stmt->execute([$id]);

success(['data' => ['id' => $id, 'shipped' => 1, 'shipped_at' => date('Y-m-d H:i:s')]]);
