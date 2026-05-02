<?php
require_once __DIR__ . '/../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$name = $input['name'] ?? '';
$commonName = $input['common_name'] ?? null;
$series = $input['series'] ?? null;
$barcode = trim($input['barcode'] ?? '');
$qiandaoPrice = $input['qiandao_price'] ?? null;
$imageUrl = $input['image_url'] ?? null;
$remark = $input['remark'] ?? null;

if (empty($name)) {
    error('请提供商品名称');
}

$pdo = getDB();

if (empty($barcode) && $barcode !== '0') {
    $maxAttempts = 10;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $randomNum = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
        $newBarcode = '69414486' . $randomNum;

        $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ?');
        $stmt->execute([$newBarcode]);
        if (!$stmt->fetch()) {
            $barcode = $newBarcode;
            break;
        }
    }

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

$stmt = $pdo->prepare('INSERT INTO products (name, common_name, series, barcode, qiandao_price, image_url, remark) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->execute([$name, $commonName, $series, $barcode, $qiandaoPrice, $imageUrl, $remark]);
$productId = $pdo->lastInsertId();

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();

success(['data' => $product]);
