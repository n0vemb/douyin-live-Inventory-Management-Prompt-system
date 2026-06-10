<?php
/**
 * 删除待出库清单中的单条记录
 * POST: { id }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$id = intval($input['id'] ?? 0);

if ($id <= 0) error('参数无效');

$pdo = getDB();
requireAuth();
$storeId = getStoreId();

$stmt = $pdo->prepare('DELETE FROM pending_outbound WHERE id = ?' . ($storeId ? ' AND store_id = ?' : ''));
$params = [$id];
if ($storeId) $params[] = $storeId;
$stmt->execute($params);

success(['message' => '已删除']);
