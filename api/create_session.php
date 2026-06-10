<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$sessionName = $input['session_name'] ?? '';

if (empty($sessionName)) {
    error('请提供场次名称');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("UPDATE live_sessions SET status = 'ended', ended_at = NOW() WHERE status = 'active' AND store_id = ?");
    $stmt->execute([$storeId]);

    $stmt = $pdo->prepare('INSERT INTO live_sessions (session_name, status, started_at, inventory_copied, store_id) VALUES (?, ?, NOW(), 0, ?)');
    $stmt->execute([$sessionName, 'active', $storeId]);

    $sessionId = $pdo->lastInsertId();

    $stmt = $pdo->prepare('
        SELECT
            product_id,
            condition_type,
            SUM(remaining_qty) as stock,
            MAX(suggested_price) as suggested_price
        FROM inventory_batches
        WHERE remaining_qty > 0 AND store_id = ?
        GROUP BY product_id, condition_type
    ');
    $stmt->execute([$storeId]);
    $inventory = $stmt->fetchAll();

    if (!empty($inventory)) {
        $insertStmt = $pdo->prepare('
            INSERT INTO live_inventory
            (live_session_id, product_id, condition_type, initial_stock, current_stock, suggested_price, store_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($inventory as $inv) {
            $insertStmt->execute([
                $sessionId,
                $inv['product_id'],
                $inv['condition_type'],
                $inv['stock'],
                $inv['stock'],
                $inv['suggested_price'],
                $storeId
            ]);
        }
    }

    $stmt = $pdo->prepare('UPDATE live_sessions SET inventory_copied = 1 WHERE id = ?');
    $stmt->execute([$sessionId]);

    $pdo->commit();

    $stmt = $pdo->prepare('SELECT * FROM live_sessions WHERE id = ?');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    success([
        'message' => '场次创建成功，库存快照已复制',
        'data' => [
            'session_id' => (int)$sessionId,
            'session' => $session,
            'inventory_items' => count($inventory)
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
