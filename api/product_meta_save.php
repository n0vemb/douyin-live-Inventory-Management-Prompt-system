<?php
/**
 * product_meta_save.php — 应用品牌/IP · 系列修正（仅改 brand/series 两列）
 * POST { items:[{id, brand?, series?}] } 或单商品 { product_id, brand?, series? }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$pdo = getDB();
requireAuth();
$storeId = getStoreId();
if (!$storeId) error('请先选择店铺');
if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'super_admin'], true)) error('无权限', 403);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$items = $input['items'] ?? [];
if (empty($items) && !empty($input['product_id'])) {
    $items = [[
        'id'     => (int)$input['product_id'],
        'brand'  => $input['brand'] ?? null,
        'series' => $input['series'] ?? null,
    ]];
}
if (!is_array($items) || !$items) error('请提供要保存的商品');
if (count($items) > 500) error('单次最多 500 条');

try {
    $check = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
    $upd = $pdo->prepare('UPDATE products SET brand = ?, series = ? WHERE id = ? AND store_id = ?');
    $done = 0;
    $fail = [];
    foreach ($items as $it) {
        $id = (int)($it['id'] ?? 0);
        if ($id <= 0) continue;
        $check->execute([$id, $storeId]);
        if (!$check->fetch()) { $fail[] = $id; continue; }
        $brand = array_key_exists('brand', $it) ? trim((string)$it['brand']) : null;
        $series = array_key_exists('series', $it) ? trim((string)$it['series']) : null;
        $upd->execute([$brand !== '' ? $brand : null, $series !== '' ? $series : null, $id, $storeId]);
        $done++;
    }
    success(['applied' => $done, 'failed' => $fail, 'message' => '已保存 ' . $done . ' 条' . ($fail ? '，失败 ' . count($fail) . ' 条' : '')]);
} catch (Exception $e) {
    logError($e->getMessage(), 'product_meta_save', ['store_id' => $storeId]);
    error('保存失败: ' . $e->getMessage(), 500);
}
