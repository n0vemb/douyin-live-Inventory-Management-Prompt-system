<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/pinyin_helper.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['id'] ?? 0;
$name = $input['name'] ?? '';
$commonName = $input['common_name'] ?? null;
$series = $input['series'] ?? null;
$brand = $input['brand'] ?? null;
$barcode = $input['barcode'] ?? '';
$qiandaoPrice = $input['qiandao_price'] ?? null;
$releaseDate = $input['release_date'] ?? null;
$productDescription = $input['product_description'] ?? null;
$imageUrl = $input['image_url'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($productId) || empty($name) || empty($barcode)) {
    error('请提供完整的商品信息');
}

$pdo = getDB();

// 读取旧的 image_url，用于后续清理旧文件
$stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
$stmt->execute([$productId]);
$oldProduct = $stmt->fetch();
$oldImageUrl = $oldProduct ? $oldProduct['image_url'] : null;

$stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ? AND id != ?');
$stmt->execute([$barcode, $productId]);
if ($stmt->fetch()) {
    error('条码已存在');
}

$pinyinInitials = generatePinyinInitials($name);

$stmt = $pdo->prepare('UPDATE products SET name = ?, pinyin_initials = ?, common_name = ?, series = ?, brand = ?, barcode = ?, qiandao_price = ?, release_date = ?, product_description = ?, image_url = ?, remark = ? WHERE id = ?');
$stmt->execute([$name, $pinyinInitials, $commonName, $series, $brand, $barcode, $qiandaoPrice, $releaseDate, $productDescription, $imageUrl, $remark, $productId]);

// 清理旧图片：如果 image_url 发生变化且旧图片是本地上传的
if ($oldImageUrl !== null && $oldImageUrl !== $imageUrl) {
    deleteImageFile($oldImageUrl);
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

success(['data' => $product]);