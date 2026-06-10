<?php
/**
 * 清空待出库清单（出库成功后调用）
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();

$stmt = $pdo->prepare('DELETE FROM pending_outbound WHERE 1=1' . ($storeId ? ' AND store_id = ?' : ''));
$stmt->execute($storeId ? [$storeId] : []);

success(['message' => '待出库清单已清空']);
