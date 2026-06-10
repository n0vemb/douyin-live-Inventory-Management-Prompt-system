<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/pinyin_helper.php';

$input = json_decode(file_get_contents('php://input'), true);

$name = $input['name'] ?? '';
$commonName = $input['common_name'] ?? null;
$series = $input['series'] ?? null;
$brand = $input['brand'] ?? null;
$barcode = trim($input['barcode'] ?? '');
$qiandaoPrice = $input['qiandao_price'] ?? null;
$releaseDate = $input['release_date'] ?? null;
$productDescription = $input['product_description'] ?? null;
$imageUrl = $input['image_url'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($name)) {
    error('请提供商品名称');
}

$pdo = getDB();
requireAuth(); $storeId = getStoreId();

if (empty($barcode) && $barcode !== '0') {
    $barcode = generateBarcode($pdo, $_SESSION['barcode_prefix'] ?? '69414486');
    if (empty($barcode)) {
        error('条码生成失败，请稍后重试');
    }
} else {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ?');
    $stmt->execute([$barcode]);
    if ($stmt->fetch()) {
        error('条码已存在');
    }
}

$pinyinInitials = generatePinyinInitials($name);

$stmt = $pdo->prepare('INSERT INTO products (name, pinyin_initials, common_name, series, brand, barcode, qiandao_price, release_date, product_description, image_url, remark, store_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$name, $pinyinInitials, $commonName, $series, $brand, $barcode, $qiandaoPrice, $releaseDate, $productDescription, $imageUrl, $remark, $storeId]);
$productId = $pdo->lastInsertId();

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

success(['data' => $product]);
