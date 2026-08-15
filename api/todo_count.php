<?php
/**
 * todo_count.php — 待办未完成数（侧边栏红点徽章）
 * GET → { success: true, data: { count: N } }
 * 店铺隔离：按当前店铺统计 pending 数量；超管未选店时返回 0（写操作需先选店，读也随视图）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();

$storeId = getStoreId();
if (empty($storeId)) {
    success(['data' => ['count' => 0]]);
    return;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM todo_items WHERE store_id = ? AND status = 'pending'");
$stmt->execute([$storeId]);
$count = (int)$stmt->fetchColumn();

success(['data' => ['count' => $count]]);
