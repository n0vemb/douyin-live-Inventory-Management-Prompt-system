<?php
/**
 * live_ledger_lucky_draw_save.php — 保存场次福袋记录（一行一条，可多条）
 * POST { session_id, draws: [{winner, prize, cost}] }  全量替换
 * 校验：场次属于当前店铺
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

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
if ($sessionId <= 0) error('缺少场次ID');

// 校验场次归属
$stmt = $pdo->prepare("SELECT id, status FROM live_ledger_session WHERE id = ? AND store_id = ?");
$stmt->execute([$sessionId, $storeId]);
$session = $stmt->fetch();
if (!$session) error('场次不存在');

// 全量替换：先删后插（保持事务）
$draws = [];
if (isset($input['draws']) && is_array($input['draws'])) {
    foreach ($input['draws'] as $d) {
        $winner = trim($d['winner'] ?? '');
        $prize  = trim($d['prize'] ?? '');
        $cost   = floatval($d['cost'] ?? 0);
        $shipped = !empty($d['shipped']) ? 1 : 0;
        $shippedAt = $shipped ? date('Y-m-d H:i:s') : null;
        if ($winner === '' || $prize === '') continue;
        if ($cost < 0) $cost = 0;
        $draws[] = ['winner' => $winner, 'prize' => $prize, 'cost' => round($cost, 2), 'shipped' => $shipped, 'shipped_at' => $shippedAt];
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("DELETE FROM live_ledger_lucky_draw WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    if (count($draws) > 0) {
        $stmt = $pdo->prepare("INSERT INTO live_ledger_lucky_draw (session_id, winner, prize, cost, shipped, shipped_at) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($draws as $d) {
            $stmt->execute([$sessionId, $d['winner'], $d['prize'], $d['cost'], $d['shipped'], $d['shipped_at']]);
        }
    }

    $pdo->commit();
    success(['data' => ['count' => count($draws)]]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('保存失败: ' . $e->getMessage());
}
