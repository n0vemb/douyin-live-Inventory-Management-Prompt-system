<?php
/**
 * pos_coupons.php — 收银台可用优惠券（免登录，店铺隔离）
 * GET ?phone=138xxxx&subtotal=100.00
 */
require_once __DIR__ . '/pos_auth.php';
require_once __DIR__ . '/coupon_lib.php';
$storeId = requirePosStore();
$pdo = getDB();
$phone = trim((string)($_GET['phone'] ?? ''));
$subtotal = max(0, round((float)($_GET['subtotal'] ?? 0), 2));

$list = couponUsableClaims($pdo, $storeId, $phone, $subtotal, null, posShopId());
success(['phone' => $phone, 'subtotal' => $subtotal, 'coupons' => $list]);
