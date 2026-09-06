<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$input = json_decode(file_get_contents('php://input'), true);

// 运营不可删除商品（2026-08-08 重构时新增权限保护）
requireNonOperator();

// 支持批量删除：product_ids 数组，也兼容原有的 product_id 单个删除
$productIds = $input['product_ids'] ?? [];
if (empty($productIds)) {
    $productId = $input['product_id'] ?? 0;
    if (empty($productId)) {
        error('请提供商品ID');
    }
    $productIds = [$productId];
} elseif (!is_array($productIds)) {
    error('参数格式错误');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();
if (empty($storeId)) {
    error('请先选择店铺后再操作');
}

// 先收集所有要删除的商品的图片路径
$imagesToDelete = [];
foreach ($productIds as $productId) {
    $productId = intval($productId);
    if ($productId <= 0) continue;
    $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = ? AND store_id = ?');
    $stmt->execute([$productId, $storeId]);
    $row = $stmt->fetch();
    if ($row && !empty($row['image_url'])) {
        $imagesToDelete[] = $row['image_url'];
    }
}

$pdo->beginTransaction();

try {
    foreach ($productIds as $productId) {
        $productId = intval($productId);
        if ($productId <= 0) continue;

        $stmt = $pdo->prepare('DELETE FROM purchase_log WHERE product_id = ? AND store_id = ?');
        $stmt->execute([$productId, $storeId]);

        $stmt = $pdo->prepare('DELETE FROM inventory_log WHERE product_id = ? AND store_id = ?');
        $stmt->execute([$productId, $storeId]);

        $stmt = $pdo->prepare('DELETE FROM inventory_batches WHERE product_id = ? AND store_id = ?');
        $stmt->execute([$productId, $storeId]);

        // 货架格子上已摆放的位置一并释放（外键 ON DELETE SET NULL 会留下幽灵格，须先删）
        $stmt = $pdo->prepare('DELETE FROM warehouse_rack_cells WHERE product_id = ? AND store_id = ?');
        $stmt->execute([$productId, $storeId]);

        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ? AND store_id = ?');
        $stmt->execute([$productId, $storeId]);
    }

    $pdo->commit();

    // 清理所有商品的本地图片文件
    foreach ($imagesToDelete as $url) {
        deleteImageFile($url);
    }

    success();

} catch (Exception $e) {
    $pdo->rollBack();
    error($e->getMessage());
}
