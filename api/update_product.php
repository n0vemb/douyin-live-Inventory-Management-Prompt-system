<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$productId = $input['id'] ?? 0;
$name = $input['name'] ?? '';
$commonName = $input['common_name'] ?? null;
$series = $input['series'] ?? null;
$barcode = $input['barcode'] ?? '';
$qiandaoPrice = $input['qiandao_price'] ?? null;
$imageUrl = $input['image_url'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($productId) || empty($name) || empty($barcode)) {
    error('请提供完整的商品信息');
}

$pdo = getDB();

$stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ? AND id != ?');
$stmt->execute([$barcode, $productId]);
if ($stmt->fetch()) {
    error('条码已存在');
}

$stmt = $pdo->prepare('UPDATE products SET name = ?, common_name = ?, series = ?, barcode = ?, qiandao_price = ?, image_url = ?, remark = ? WHERE id = ?');
$stmt->execute([$name, $commonName, $series, $barcode, $qiandaoPrice, $imageUrl, $remark, $productId]);

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

success(['data' => $product]);