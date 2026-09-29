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
        ORDER BY (SELECT COALESCE(SUM(ib.remaining_qty),0) FROM inventory_batches ib WHERE ib.product_id = p.id{$sf}) DESC,
                 (SELECT MAX(ib.purchased_at) FROM inventory_batches ib WHERE ib.product_id = p.id{$sf}) DESC,
                 p.id DESC
    ";
    $items = $pdo->query($sql)->fetchAll();

    // 24h 内已盘过的未上架商品（临时货架绿底依据），按商品最新一条记录
    $auditMap = [];
    try {
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $auditSql = "SELECT a.product_id, a.result, a.created_at, u.display_name
                     FROM product_audits a
                     LEFT JOIN users u ON u.id = a.user_id
                     WHERE a.created_at >= ?" . ($storeId ? " AND a.store_id = ?" : "") . "
                     ORDER BY a.created_at DESC";
        $stmt = $pdo->prepare($auditSql);
        $stmt->execute($storeId ? [$cutoff, $storeId] : [$cutoff]);
        foreach ($stmt->fetchAll() as $a) {
            $pid = (int)$a['product_id'];
            if (!isset($auditMap[$pid])) $auditMap[$pid] = $a;
        }
    } catch (Exception $e) {
        // product_audits 表尚未创建时仅无绿底，不影响未上架列表
    }

    $out = [];
    foreach ($items as $r) {
        $aud = $auditMap[(int)$r['id']] ?? null;
        $out[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'common_name' => $r['common_name'],
            'barcode' => $r['barcode'],
            'pinyin' => $r['pinyin_initials'],
            'stock' => (int)$r['stock'],
            'audited_at' => $aud['created_at'] ?? null,
            'audit_result' => $aud['result'] ?? null,
            'audit_by' => $aud['display_name'] ?? null,
        ];
    }
    success(['items' => $out, 'count' => count($out)]);
} catch (Exception $e) {
    error($e->getMessage());
}
