<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

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

// 先收集所有要删除的商品的图片路径
$imagesToDelete = [];
foreach ($productIds as $productId) {
    $productId = intval($productId);
    if ($productId <= 0) continue;
    $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
    $stmt->execute([$productId]);
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

        $stmt = $pdo->prepare('DELETE FROM purchase_log WHERE product_id = ?');
        $stmt->execute([$productId]);

        $stmt = $pdo->prepare('DELETE FROM inventory_log WHERE product_id = ?');
        $stmt->execute([$productId]);

        $stmt = $pdo->prepare('DELETE FROM inventory_batches WHERE product_id = ?');
        $stmt->execute([$productId]);

        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$productId]);
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
