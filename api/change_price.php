<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['product_id'] ?? 0;
$conditionType = $input['condition_type'] ?? '';
$newPrice = $input['new_price'] ?? 0;
$liveSessionId = $input['live_session_id'] ?? null;

if (empty($productId) || empty($conditionType)) {
    error('请提供商品ID和状态类型');
}

if ($newPrice <= 0) {
    error('请提供有效价格');
}

if (empty($liveSessionId)) {
    error('请提供直播场次ID');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

$pdo->beginTransaction();

try {
    // 检查直播库存记录
    $stmt = $pdo->prepare('SELECT * FROM live_inventory WHERE live_session_id = ? AND product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$liveSessionId, $productId, $conditionType, $storeId]);
    $inventory = $stmt->fetch();

    if (!$inventory) {
        throw new RuntimeException('库存记录不存在');
    }

    // ===== 1. 更新 live_inventory 的直播价（标记价格已修改） =====
    // live_price != null 即表示该场次改过价，用于前端显示黄色标记
    $stmt = $pdo->prepare('UPDATE live_inventory SET live_price = ? WHERE id = ?');
    $stmt->execute([$newPrice, $inventory['id']]);

    // ===== 2. 同步更新该 SKU + 品相所有批次的 suggested_price（实际售价） =====
    $remark = '直播改价 ¥' . number_format($newPrice, 2);
    $sql = 'UPDATE inventory_batches
            SET suggested_price = ?,
                remark = CONCAT(
                    IFNULL(remark, ""),
                    IF(IFNULL(remark, "") = "", "", " | "),
                    ?
                )
            WHERE product_id = ? AND condition_type = ? AND store_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$newPrice, $remark, $productId, $conditionType, $storeId]);

    // ===== 3. 同步更新 live_inventory 的 suggested_price 引用 =====
    // 让该场次内其他同 SKU+品相的记录也拿到最新参考价
    $stmt = $pdo->prepare('UPDATE live_inventory SET suggested_price = ? WHERE product_id = ? AND condition_type = ? AND store_id = ?');
    $stmt->execute([$newPrice, $productId, $conditionType, $storeId]);

    $pdo->commit();

    success([
        'data' => [
            'new_price' => $newPrice,
            'updated_batches' => $stmt->rowCount()
        ]
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
?>