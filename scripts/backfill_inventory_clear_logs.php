<?php
/**
 * backfill_inventory_clear_logs.php — 回填历史盘点清零流水
 *
 * 背景：2026-08-07 之前的盘点代码只写"盘点调整"（最新批次），
 * 清零其他批次时不写 inventory_log → 出入库流水缺"盘点清零"记录，
 * 导致流水倒推"当时库存"出现负数。
 *
 * 判定规则（2026-08-10 数据验证）：
 *   有盘点记录的 SKU（该SKU存在"盘点调整"流水）→ 该商品该SKU的
 *      remaining_qty=0 批次 = 被盘点清零，一律补（即使有出库记录，
 *      补的是"剩余被清零部分" = total - sold_qty）
 *   无盘点记录的 SKU → 仅无出库记录（手工清零）的补；有出库（纯卖完）的不补
 *   清零点：有盘点记录 → 该SKU最近一次盘点时间；无 → 批次 created_at
 *
 * 用法：php backfill_inventory_clear_logs.php [--dry-run] [--limit N]
 * 环境变量：PPMART_DB_HOST / PPMART_DB_USER / PPMART_DB_PASS / PPMART_DB_NAME
 */
$pdo = new PDO(
    'mysql:host=' . (getenv('PPMART_DB_HOST') ?: 'localhost') . ';dbname=' . getenv('PPMART_DB_NAME') . ';charset=utf8mb4',
    getenv('PPMART_DB_USER'), getenv('PPMART_DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$dryRun = in_array('--dry-run', $argv);
$limit = null;
foreach ($argv as $a) {
    if (strpos($a, '--limit=') === 0) $limit = (int)substr($a, 8);
}

// 找出"被清零"的批次：
//   remaining_qty=0 且 缺该SKU的"盘点清零"流水
$stmt = $pdo->query("
    SELECT ib.id, ib.product_id, ib.store_id, ib.condition_type, ib.total_qty, ib.remaining_qty,
           ib.created_at AS batch_created_at,
           COALESCE((SELECT SUM(ob.qty) FROM outbound_log ob WHERE ob.batch_id = ib.id), 0) AS sold_qty,
           (SELECT MAX(il.created_at) FROM inventory_log il
            WHERE il.product_id=ib.product_id AND il.condition_type=ib.condition_type
              AND il.change_type='adjust' AND il.remark='盘点调整') AS last_pandian
    FROM inventory_batches ib
    WHERE ib.remaining_qty = 0
      AND NOT EXISTS (SELECT 1 FROM inventory_log il
                      WHERE il.product_id=ib.product_id AND il.condition_type=ib.condition_type
                        AND il.qty_change<0 AND il.remark='盘点清零')
    ORDER BY ib.product_id, ib.purchased_at
");
$batches = $stmt->fetchAll();

$toInsert = [];
foreach ($batches as $b) {
    $hasPandian = $b['last_pandian'] !== null;
    $sold = (int)$b['sold_qty'];
    if ($hasPandian) {
        // 有盘点记录：剩余被清零的部分 = total - sold（可能卖过一部分）
        $clearQty = (int)$b['total_qty'] - $sold;
        if ($clearQty <= 0) continue; // 全卖完了，没清零
        $clearTime = $b['last_pandian'];
    } else {
        // 无盘点记录：仅手工清零（无出库）的补；纯卖完（有出库）不补
        if ($sold > 0) continue;
        $clearQty = (int)$b['total_qty'];
        $clearTime = $b['batch_created_at'];
    }
    $toInsert[] = [
        'product_id' => $b['product_id'],
        'condition_type' => $b['condition_type'],
        'qty' => $clearQty,
        'store_id' => $b['store_id'],
        'time' => $clearTime,
    ];
}

echo "待补批次: " . count($toInsert) . "\n";
if ($limit) $toInsert = array_slice($toInsert, 0, $limit);

$inserted = 0;
foreach ($toInsert as $r) {
    if ($dryRun) {
        echo "  [dry] product={$r['product_id']} cond={$r['condition_type']} -{$r['qty']} @ {$r['time']}\n";
        continue;
    }
    $stmt2 = $pdo->prepare("
        INSERT INTO inventory_log (product_id, condition_type, change_type, qty_change, before_qty, after_qty, remark, store_id, created_at)
        VALUES (?, ?, 'adjust', ?, ?, 0, '盘点清零', ?, ?)
    ");
    $stmt2->execute([$r['product_id'], $r['condition_type'], -$r['qty'], $r['qty'], $r['store_id'], $r['time']]);
    $inserted++;
}

echo "完成: 插入 {$inserted} 条" . ($dryRun ? " (dry-run)" : "") . "\n";
