<?php
// 拣货清单：汇总待出库订单商品，按货架位置排序（仓库拣货用）
// 有货架位置的按 货架顺序→层(高到低)→格位 排序，未录入货架的排在最后
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

try {
    $pdo = getDB();
    requireAuth();
    $storeId = getStoreId();

    // 待出库订单（已收款 pending）的 active 商品
    $sql = "
        SELECT po.id AS order_id, po.order_no, po.created_at,
               pi.product_id, pi.qty, p.name AS product_name, p.common_name, p.barcode
        FROM pos_order_items pi
        JOIN pos_orders po ON po.id = pi.order_id
        LEFT JOIN products p ON p.id = pi.product_id
        WHERE po.outbound_status = 'pending' AND pi.status = 'active' AND po.pay_status = 'paid'
    ";
    $params = [];
    if ($storeId) { $sql .= ' AND po.store_id = ?'; $params[] = $storeId; }
    $sql .= ' ORDER BY po.created_at DESC, pi.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 按商品聚合：数量 + 涉及订单
    $agg = []; // product_id => {name, common_name, barcode, qty, orders:[]}
    foreach ($rows as $r) {
        $pid = (int)$r['product_id'];
        if (!isset($agg[$pid])) {
            $agg[$pid] = [
                'product_id' => $pid,
                'name' => $r['product_name'],
                'common_name' => $r['common_name'],
                'barcode' => $r['barcode'],
                'qty' => 0,
                'orders' => []
            ];
        }
        $agg[$pid]['qty'] += (int)$r['qty'];
        if (!in_array($r['order_no'], $agg[$pid]['orders'])) {
            $agg[$pid]['orders'][] = $r['order_no'];
        }
    }

    // 查货架位置
    $locMap = [];
    $pids = array_keys($agg);
    if ($pids) {
        $ph = implode(',', array_fill(0, count($pids), '?'));
        $sql2 = "
            SELECT c.product_id, r.code, c.row_no, c.pos_no, c.span, r.sort_order
            FROM warehouse_rack_cells c
            JOIN warehouse_racks r ON r.id = c.rack_id
            WHERE c.product_id IN ($ph)
        ";
        $params2 = $pids;
        if ($storeId) { $sql2 .= ' AND r.store_id = ?'; $params2[] = $storeId; }
        $stmt = $pdo->prepare($sql2);
        $stmt->execute($params2);
        foreach ($stmt->fetchAll() as $l) {
            $locMap[(int)$l['product_id']] = [
                'rack' => $l['code'],
                'row' => (int)$l['row_no'],
                'pos' => (int)$l['pos_no'],
                'span' => (int)$l['span'],
                'sort' => (int)$l['sort_order'],
                'label' => $l['code'] . ' · 第' . (int)$l['row_no'] . '层 · 第' . (int)$l['pos_no'] . ($l['span'] > 1 ? '-' . ((int)$l['pos_no'] + 1) : '') . '格'
            ];
        }
    }

    // 组装 + 排序：有位置按 (sort, row desc, pos)，无位置排最后
    $items = [];
    $unlocated = [];
    foreach ($agg as $pid => $a) {
        $a['loc'] = $locMap[$pid] ?? null;
        if ($a['loc']) $items[] = $a;
        else $unlocated[] = $a;
    }
    usort($items, function ($x, $y) {
        $lx = $x['loc']; $ly = $y['loc'];
        if ($lx['sort'] !== $ly['sort']) return $lx['sort'] <=> $ly['sort'];
        if ($lx['row'] !== $ly['row']) return $ly['row'] <=> $lx['row']; // 高层先拣
        return $lx['pos'] <=> $ly['pos'];
    });

    success([
        'orders_count' => count(array_unique(array_map(function ($r) { return $r['order_id']; }, $rows))),
        'items' => $items,
        'unlocated' => $unlocated
    ]);
} catch (Exception $e) {
    error($e->getMessage());
}
