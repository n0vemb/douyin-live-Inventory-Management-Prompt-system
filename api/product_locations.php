<?php
// 商品 → 货架位置映射（商品列表悬停提示用）：一次拉全量，前端缓存复用
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getDB();
    requireAuth();
    $storeId = getStoreId();

    // 只跟货架/格子两张小表，按店铺过滤（超管未选店铺时返回全部）
    $sf = $storeId ? ' AND r.store_id = ' . (int)$storeId : '';
    $sql = "
        SELECT c.product_id, r.code AS rack, c.row_no, c.pos_no, c.span, r.sort_order
        FROM warehouse_rack_cells c
        JOIN warehouse_racks r ON r.id = c.rack_id
        WHERE c.product_id IS NOT NULL{$sf}
        ORDER BY r.sort_order, r.id, c.row_no, c.pos_no
    ";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $r) {
        $pid = (int)$r['product_id'];
        $pos = (int)$r['pos_no'];
        $span = (int)$r['span'];
        $posTxt = $span > 1 ? '第' . $pos . '-' . ($pos + 1) . '格' : '第' . $pos . '格';
        if (!isset($map[$pid])) $map[$pid] = [];
        $map[$pid][] = [
            'rack'  => $r['rack'],
            'row'   => (int)$r['row_no'],
            'pos'   => $pos,
            'span'  => $span,
            'label' => $r['rack'] . ' · 第' . (int)$r['row_no'] . '层 · ' . $posTxt
        ];
    }

    success(['map' => $map, 'count' => count($map)]);
} catch (Exception $e) {
    error($e->getMessage());
}
