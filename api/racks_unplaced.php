<?php
// 未在货架的商品列表（右侧浮窗数据源）：本店 products − 已占用格子的商品
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getDB();
    requireAuth();
    $storeId = getStoreId();

    // 全部 intval 拼接避免子查询占位符顺序坑（$storeId 为 int 或 null）
    $sf = $storeId ? ' AND store_id = ' . (int)$storeId : '';
    $sql = "
        SELECT p.id, p.name, p.common_name, p.barcode, p.pinyin_initials,
               (SELECT COALESCE(SUM(ib.remaining_qty),0) FROM inventory_batches ib WHERE ib.product_id = p.id{$sf}) AS stock
        FROM products p
        WHERE p.id NOT IN (SELECT product_id FROM warehouse_rack_cells WHERE product_id IS NOT NULL{$sf}){$sf}
        ORDER BY (SELECT MAX(ib.purchased_at) FROM inventory_batches ib WHERE ib.product_id = p.id{$sf}) DESC, p.id DESC
    ";
    $items = $pdo->query($sql)->fetchAll();

    $out = [];
    foreach ($items as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'common_name' => $r['common_name'],
            'barcode' => $r['barcode'],
            'pinyin' => $r['pinyin_initials'],
            'stock' => (int)$r['stock']
        ];
    }
    success(['items' => $out, 'count' => count($out)]);
} catch (Exception $e) {
    error($e->getMessage());
}
