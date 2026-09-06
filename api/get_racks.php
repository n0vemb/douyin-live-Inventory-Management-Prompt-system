<?php
// 货架分布读取：登录即可（含商品名/库存，供货架页与出库台使用）
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getDB();
    requireAuth();
    $storeId = getStoreId();

    // 货架（按 sort_order, id 排序）
    $stmt = $pdo->prepare('SELECT * FROM warehouse_racks WHERE 1=1' . ($storeId ? ' AND store_id = ?' : '') . ' ORDER BY sort_order, id');
    $stmt->execute($storeId ? [$storeId] : []);
    $racks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rackIds = array_map(function ($r) { return (int)$r['id']; }, $racks);

    // 清理幽灵格：商品被删除后外键把 product_id 置 NULL，旧数据会残留空格位并卡住页面渲染
    if ($rackIds) {
        $inList = implode(',', array_map('intval', $rackIds));
        $pdo->exec("DELETE FROM warehouse_rack_cells WHERE product_id IS NULL AND rack_id IN ($inList)");
    }

    // 格子（只存有商品的格）+ 商品名 + 实时库存
    $cells = [];
    if ($rackIds) {
        // 注意：库存子查询的 ? 在 SELECT 列表（SQL 文本顺序先于 WHERE 的 IN），
        // 原生 prepare 按文本顺序绑定会错位，因此 IN 用 intval 拼接（rackIds 已 int 化，无注入风险）
        $inList = implode(',', array_map('intval', $rackIds));
        $params = [];
        $sql = "
            SELECT c.rack_id, c.row_no, c.pos_no, c.span, c.note, c.product_id,
                   p.name AS product_name, p.common_name, p.barcode, p.pinyin_initials,
                   (SELECT COALESCE(SUM(ib.remaining_qty),0) FROM inventory_batches ib WHERE ib.product_id = c.product_id" . ($storeId ? " AND ib.store_id = ?" : "") . ") AS stock
            FROM warehouse_rack_cells c
            LEFT JOIN products p ON p.id = c.product_id
            WHERE c.rack_id IN ($inList)
            ORDER BY c.rack_id, c.row_no, c.pos_no
        ";
        if ($storeId) $params[] = $storeId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $cells[$r['rack_id']][$r['row_no']][$r['pos_no']] = [
                'span' => (int)$r['span'],
                'note' => $r['note'],
                'product' => $r['product_id'] ? [
                    'id' => (int)$r['product_id'],
                    'name' => $r['product_name'],
                    'common_name' => $r['common_name'],
                    'barcode' => $r['barcode'],
                    'pinyin' => $r['pinyin_initials'],
                    'stock' => (int)$r['stock']
                ] : null
            ];
        }
    }

    $out = ['order' => [], 'racks' => []];
    $meta = [];
    foreach ($racks as $r) {
        $out['order'][] = $r['code'];
        $out['racks'][$r['code']] = $cells[$r['id']] ?? [];
        $meta[$r['code']] = [
            'rows'     => max(1, min(10, (int)($r['row_count'] ?? 5))),
            'big_cols' => max(1, min(10, (int)($r['big_col_count'] ?? 5))),
        ];
    }
    $out['meta'] = $meta;
    $out['admin'] = in_array($_SESSION['role'] ?? '', ['store_admin', 'super_admin']);

    // 店铺货架布局（不写死 5×5）：stores.rack_layout JSON {rows, big_cols}，NULL=默认
    $layout = ['rows' => 5, 'big_cols' => 5];
    if ($storeId) {
        $stmt = $pdo->prepare('SELECT rack_layout FROM stores WHERE id = ?');
        $stmt->execute([$storeId]);
        $rl = $stmt->fetchColumn();
        if ($rl) {
            $d = json_decode($rl, true);
            if (is_array($d)) {
                if (!empty($d['rows'])) $layout['rows'] = max(1, min(10, (int)$d['rows']));
                if (!empty($d['big_cols'])) $layout['big_cols'] = max(1, min(10, (int)$d['big_cols']));
            }
        }
    }
    $out['layout'] = $layout;

    success($out);
} catch (Exception $e) {
    error($e->getMessage());
}
