<?php
/**
 * pos_wish.php — 求补货心愿单记录（免登录，纯自助）
 * 无库存商品顾客点击「求补货」，每客户每商品仅一次（UNIQUE store_id+product_id+client_key）
 */
require_once __DIR__ . '/pos_auth.php';
$storeId = requirePosStore();
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($input['product_id'] ?? 0);
    $cond = trim($input['condition_type'] ?? '');
    $clientKey = trim($input['client_key'] ?? '');
    if (!$productId) error('缺少商品ID');
    if (!$clientKey) error('缺少客户标识');
    $pdo = getDB();

    try {
        // 商品存在性 + 属于本店
        $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
        $stmt->execute([$productId, $storeId]);
        if (!$stmt->fetch()) error('商品不存在');

        // 幂等：已求过则直接返回成功（不重复计数）
        $stmt = $pdo->prepare('SELECT id FROM pos_wishlist WHERE store_id = ? AND product_id = ? AND client_key = ?');
        $stmt->execute([$storeId, $productId, $clientKey]);
        if ($stmt->fetch()) {
            success(['recorded' => false, 'message' => '已求过补货']);
            return;
        }

        $pdo->prepare('INSERT INTO pos_wishlist (store_id, product_id, condition_type, client_key) VALUES (?, ?, ?, ?)')
            ->execute([$storeId, $productId, $cond, $clientKey]);
        success(['recorded' => true]);
    } catch (Exception $e) {
        // 唯一键冲突（并发双击）
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            success(['recorded' => false, 'message' => '已求过补货']);
            return;
        }
        logError($e->getMessage(), 'pos_wish');
        error('记录失败: ' . $e->getMessage(), 500);
    }
} else {
    error('仅支持POST', 405);
}
