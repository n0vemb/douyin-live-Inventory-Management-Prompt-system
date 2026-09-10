<?php
/**
 * session_shop_lib.php — 台账场次改归属店时的联动更新
 *
 * 场次归属 = live_ledger_session.shop_id；同店下游数据（仓库任务/出库明细/销售/出库财务）
 * 都是从场次盖章 shop_id，因此改店时必须一起搬，避免报表串店。
 */

/**
 * 把一场台账场次迁移到目标店（默认仅限同集团；跨集团需显式允许）
 * @return array 统计信息
 * @throws Exception 目标店不合法、批次财务冲突等
 */
function migrateLedgerSessionShop(PDO $pdo, int $sessionId, int $targetShopId, bool $allowCrossStore = false): array {
    $stmt = $pdo->prepare('SELECT id, store_id, shop_id, session_name FROM live_ledger_session WHERE id = ?');
    $stmt->execute([$sessionId]);
    $sess = $stmt->fetch();
    if (!$sess) {
        throw new Exception('场次不存在');
    }

    $stmt = $pdo->prepare('SELECT id, store_id, name FROM shops WHERE id = ?');
    $stmt->execute([$targetShopId]);
    $shop = $stmt->fetch();
    if (!$shop) {
        throw new Exception('目标店铺不存在');
    }

    $oldStoreId = (int)$sess['store_id'];
    $oldShopId = $sess['shop_id'] !== null ? (int)$sess['shop_id'] : null;
    $newStoreId = (int)$shop['store_id'];

    if (!$allowCrossStore && $newStoreId !== $oldStoreId) {
        throw new Exception('目标店铺不属于该场次所在集团');
    }
    if ($oldShopId === $targetShopId && $oldStoreId === $newStoreId) {
        return ['moved' => false, 'session_id' => $sessionId, 'shop_id' => $targetShopId];
    }

    // 先做批次财务冲突检查（同批次在目标集团+目标店已有对账记录时不能搬）
    $stmt = $pdo->prepare('SELECT DISTINCT outbound_batch_no FROM outbound_log WHERE live_session_id = ? AND outbound_batch_no IS NOT NULL');
    $stmt->execute([$sessionId]);
    $batches = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'outbound_batch_no');
    foreach ($batches as $bn) {
        $chk = $pdo->prepare('SELECT COUNT(*) FROM outbound_finance WHERE store_id = ? AND outbound_batch_no = ? AND shop_id = ?');
        $chk->execute([$newStoreId, $bn, $targetShopId]);
        if ((int)$chk->fetchColumn() > 0) {
            throw new Exception("目标店已存在批次 {$bn} 的财务记录，请先处理该批次后再迁移");
        }
    }

    $counts = [];
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE live_ledger_session SET store_id = ?, shop_id = ? WHERE id = ?')
            ->execute([$newStoreId, $targetShopId, $sessionId]);

        // 仓库出库台任务
        $st = $pdo->prepare('UPDATE warehouse_task SET store_id = ?, shop_id = ? WHERE session_id = ?');
        $st->execute([$newStoreId, $targetShopId, $sessionId]);
        $counts['warehouse_task'] = $st->rowCount();

        // 出库明细
        $st = $pdo->prepare('UPDATE outbound_log SET store_id = ?, shop_id = ? WHERE live_session_id = ?');
        $st->execute([$newStoreId, $targetShopId, $sessionId]);
        $counts['outbound_log'] = $st->rowCount();

        // 销售记录：新数据带 ledger_session_id；旧台账数据 live_session_id 为 NULL，只能靠 v22 回填
        $st = $pdo->prepare('UPDATE sales_log SET store_id = ?, shop_id = ? WHERE ledger_session_id = ? OR live_session_id = ?');
        $st->execute([$newStoreId, $targetShopId, $sessionId, $sessionId]);
        $counts['sales_log'] = $st->rowCount();

        // 出库财务（按本场次的每个批次）
        $fin = 0;
        foreach ($batches as $bn) {
            $st = $pdo->prepare('UPDATE outbound_finance SET store_id = ?, shop_id = ? WHERE store_id = ? AND outbound_batch_no = ?' . ($oldShopId !== null ? ' AND (shop_id = ? OR shop_id IS NULL)' : ''));
            $params = [$newStoreId, $targetShopId, $oldStoreId, $bn];
            if ($oldShopId !== null) $params[] = $oldShopId;
            $st->execute($params);
            $fin += $st->rowCount();
        }
        $counts['outbound_finance'] = $fin;

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return array_merge([
        'moved' => true,
        'session_id' => $sessionId,
        'from_store_id' => $oldStoreId,
        'from_shop_id' => $oldShopId,
        'to_store_id' => $newStoreId,
        'to_shop_id' => $targetShopId,
    ], $counts);
}
