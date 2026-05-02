<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$sessionName = $input['session_name'] ?? '';

if (empty($sessionName)) {
    error('请提供场次名称');
}

$pdo = getDB();

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("UPDATE live_sessions SET status = 'ended', ended_at = NOW() WHERE status = 'active'");
    $stmt->execute();

    $stmt = $pdo->prepare('INSERT INTO live_sessions (session_name, status, started_at, inventory_copied) VALUES (?, ?, NOW(), 0)');
    $stmt->execute([$sessionName, 'active']);

    $sessionId = $pdo->lastInsertId();

    $stmt = $pdo->prepare('
        SELECT
            product_id,
            condition_type,
            SUM(remaining_qty) as stock,
            MAX(suggested_price) as suggested_price
        FROM inventory_batches
        WHERE remaining_qty > 0
        GROUP BY product_id, condition_type
    ');
    $stmt->execute();
    $inventory = $stmt->fetchAll();

    if (!empty($inventory)) {
        $insertStmt = $pdo->prepare('
            INSERT INTO live_inventory
            (live_session_id, product_id, condition_type, initial_stock, current_stock, suggested_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        foreach ($inventory as $inv) {
            $insertStmt->execute([
                $sessionId,
                $inv['product_id'],
                $inv['condition_type'],
                $inv['stock'],
                $inv['stock'],
                $inv['suggested_price']
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
