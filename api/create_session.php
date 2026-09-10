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
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

// 店级账号固定本店；集团管理员/超管建旧链路场次需传 shop_id
$shopId = getShopId();
if (!$shopId) {
    $inputShop = isset($input['shop_id']) ? (int)$input['shop_id'] : 0;
    if ($inputShop <= 0) {
        if (in_array($_SESSION['role'] ?? '', ['store_admin', 'deputy_store_admin', 'operator'], true)) {
            error('你的账号未绑定门店，请让集团管理员在「用户管理」里设置所属店');
        }
        error('请选择所属店（场次必须归属 A店/B店）');
    }
    $stmt = $pdo->prepare('SELECT id FROM shops WHERE id = ? AND store_id = ?');
    $stmt->execute([$inputShop, $storeId]);
    if (!$stmt->fetch()) {
        error('所选店铺不属于当前集团');
    }
    $shopId = $inputShop;
}

$pdo->beginTransaction();

try {
    // 业务规则：一个店允许同时开多个 active 场次，创建时不结束其它进行中场次
    // （该规则由台账链路沿用，旧链路同样不再强制“同店唯一 active”）

    $stmt = $pdo->prepare('INSERT INTO live_sessions (session_name, status, started_at, inventory_copied, store_id, shop_id) VALUES (?, ?, NOW(), 0, ?, ?)');
    $stmt->execute([$sessionName, 'active', $storeId, $shopId]);

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
            (live_session_id, product_id, condition_type, initial_stock, current_stock, suggested_price, store_id, shop_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($inventory as $inv) {
            $insertStmt->execute([
                $sessionId,
                $inv['product_id'],
                $inv['condition_type'],
                $inv['stock'],
                $inv['stock'],
                $inv['suggested_price'],
                $storeId,
                $shopId
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
