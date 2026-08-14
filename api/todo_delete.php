<?php
/**
 * todo_delete.php — 删除待办（硬删）
 * POST { id }
 * 校验归属店铺后 DELETE
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id    = (int)($input['id'] ?? 0);

if ($id <= 0) {
    error('参数错误');
}

requireAuth();
$storeId = getStoreId();
if ($storeId === null) {
    error('请先选择店铺再操作');
}

$pdo = getDB();

$stmt = $pdo->prepare("SELECT id FROM todo_items WHERE id = ? AND store_id = ?");
$stmt->execute([$id, $storeId]);
if (!$stmt->fetch()) {
    error('未找到该待办');
}

$stmt = $pdo->prepare("DELETE FROM todo_items WHERE id = ? AND store_id = ?");
$stmt->execute([$id, $storeId]);

success(['message' => '已删除']);
