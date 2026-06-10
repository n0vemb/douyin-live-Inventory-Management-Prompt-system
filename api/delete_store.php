<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
requireSuperAdmin();

$input = json_decode(file_get_contents('php://input'), true);
$storeId = (int)($input['store_id'] ?? 0);

if ($storeId <= 0) {
    error('请提供店铺ID');
}

if ($storeId === 1) {
    error('默认店铺不可删除');
}

$pdo = getDB();

// 检查店铺是否有用户
$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE store_id = ?');
$stmt->execute([$storeId]);
if ((int)$stmt->fetchColumn() > 0) {
    error('该店铺下还有用户，无法删除');
}

$pdo->beginTransaction();
try {
    // 删除店铺相关数据
    $tables = ['products', 'inventory_batches', 'sales_log', 'outbound_log', 'purchase_log', 'inventory_log', 'live_inventory', 'live_sessions', 'broadcast_messages', 'label_templates', 'system_settings'];
    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM {$table} WHERE store_id = {$storeId}");
    }
    $pdo->exec("DELETE FROM stores WHERE id = {$storeId}");
    $pdo->commit();
    success(['message' => '店铺已删除']);
} catch (Exception $e) {
    $pdo->rollBack();
    error('删除失败: ' . $e->getMessage());
}
