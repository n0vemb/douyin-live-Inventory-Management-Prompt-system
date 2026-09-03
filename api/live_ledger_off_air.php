<?php
/**
 * live_ledger_off_air.php — 下播（记录下播时间，商品打包出库另走 live_ledger_end）
 * POST { session_id }
 *
 * 规则：
 * - 仅 active 场次且未下播可执行（幂等，重复调用报已下播）
 * - 只记录 off_air_at = NOW()，不改 status（已录商品继续占用库存，防超卖）；
 *   打包出库(live_ledger_end)时才真正扣库存并置 ended
 * - 播出时长 = off_air_at - created_at
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
if ($sessionId <= 0) error('缺少场次ID');

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("SELECT status, off_air_at, created_at FROM live_ledger_session WHERE id = ? AND store_id = ? FOR UPDATE");
    $stmt->execute([$sessionId, $storeId]);
    $sess = $stmt->fetch();
    if (!$sess) {
        $pdo->rollBack();
        error('场次不存在');
    }
    if ($sess['status'] !== 'active') {
        $pdo->rollBack();
        error('已结束的场次不能下播');
    }
    if (!empty($sess['off_air_at'])) {
        $pdo->rollBack();
        error('本场已下播，无需重复操作');
    }

    $stmt = $pdo->prepare("UPDATE live_ledger_session SET off_air_at = NOW() WHERE id = ? AND store_id = ?");
    $stmt->execute([$sessionId, $storeId]);

    $stmt = $pdo->prepare("SELECT off_air_at, created_at FROM live_ledger_session WHERE id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch();
    $pdo->commit();

    $offAirAt = $row['off_air_at'];
    $durationSec = $row['created_at'] ? max(0, strtotime($offAirAt) - strtotime($row['created_at'])) : 0;
    success([
        'message' => '已下播',
        'data' => [
            'off_air_at' => $offAirAt,
            'duration_seconds' => $durationSec,
        ],
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error('下播失败: ' . $e->getMessage());
}
