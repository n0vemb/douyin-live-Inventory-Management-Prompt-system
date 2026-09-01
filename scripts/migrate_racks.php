<?php
/**
 * 迁移工具箱 racks.json 货架数据 → 中枢 warehouse_racks / warehouse_rack_cells
 *
 * 用法（CLI，服务器上运行）:
 *   php scripts/migrate_racks.php [store_id] [racks_json路径]
 *   例: php scripts/migrate_racks.php 3 /www/wwwroot/qiandao-price/toolbox/racks.json
 *
 * 说明:
 *   - 按商品名匹配中枢 products（先精确 name/common_name，再模糊 LIKE）
 *   - 匹配不上的输出清单（商品改名/下架，需人工处理）
 *   - 重复运行安全：先清空目标店铺现有货架再写入
 *   - 数据库连接走 config.php 的环境变量（PPMART_DB_*）
 */
require __DIR__ . '/../config.php';

$storeId = isset($argv[1]) ? (int)$argv[1] : 3;
$path = isset($argv[2]) ? $argv[2] : '/www/wwwroot/qiandao-price/toolbox/racks.json';

if (!file_exists($path)) {
    fwrite(STDERR, "[!] 文件不存在: $path\n");
    exit(1);
}
$data = json_decode(file_get_contents($path), true);
if (!is_array($data)) {
    fwrite(STDERR, "[!] JSON 解析失败\n");
    exit(1);
}

$pdo = getDB();
echo "[*] 目标店铺 store_id=$storeId, 货架数=" . count($data) . "\n";

// 清空目标店铺现有货架（幂等，格子随货架级联删除）
$pdo->prepare('DELETE FROM warehouse_racks WHERE store_id = ?')->execute([$storeId]);

// 商品匹配缓存
$matchCache = [];
$findProduct = function ($name) use ($pdo, $storeId, &$matchCache) {
    $name = trim($name);
    if ($name === '') return null;
    if (isset($matchCache[$name])) return $matchCache[$name];
    // 精确匹配 name 或 common_name
    $stmt = $pdo->prepare('SELECT id, name, common_name FROM products WHERE store_id = ? AND (name = ? OR common_name = ?) LIMIT 1');
    $stmt->execute([$storeId, $name, $name]);
    $r = $stmt->fetch();
    if (!$r) {
        // 模糊匹配（名称包含），优先完全同名
        $stmt = $pdo->prepare('SELECT id, name, common_name FROM products WHERE store_id = ? AND (name LIKE ? OR common_name LIKE ?) ORDER BY (name = ?) DESC, id DESC LIMIT 1');
        $like = '%' . $name . '%';
        $stmt->execute([$storeId, $like, $like, $name]);
        $r = $stmt->fetch();
    }
    $matchCache[$name] = $r ? ['id' => (int)$r['id'], 'name' => $r['name']] : null;
    return $matchCache[$name];
};

$rackStmt = $pdo->prepare('INSERT INTO warehouse_racks (store_id, code, sort_order) VALUES (?,?,?)');
$cellStmt = $pdo->prepare('INSERT INTO warehouse_rack_cells (store_id, rack_id, row_no, pos_no, span, product_id, note) VALUES (?,?,?,?,?,?,?)');

$sort = 0;
$cells = 0;
$matchedProducts = [];
$unmatched = [];
foreach ($data as $code => $rows) {
    $code = trim($code);
    if ($code === '' || $code === '__order__' || !is_array($rows)) continue; // 跳过顺序等元数据键
    $rackStmt->execute([$storeId, $code, $sort++]);
    $rackId = (int)$pdo->lastInsertId();
    foreach ($rows as $row => $cellsArr) {
        if (!is_numeric($row)) continue; // 跳过 __order__ 等元数据键
        $rowNo = (int)$row;
        if ($rowNo < 1 || $rowNo > 5 || !is_array($cellsArr)) continue;
        foreach ($cellsArr as $i => $c) {
            if (!$c || empty($c['name'])) continue;
            $name = trim($c['name']);
            $pos = $i + 1;
            $span = isset($c['span']) ? (int)$c['span'] : 1;
            $m = $findProduct($name);
            if ($m) {
                $cellStmt->execute([$storeId, $rackId, $rowNo, $pos, $span, $m['id'], isset($c['note']) ? trim($c['note']) : '']);
                $cells++;
                $matchedProducts[$name] = ($matchedProducts[$name] ?? 0) + 1;
            } else {
                $unmatched[] = "$code 第{$rowNo}层 第{$pos}格: $name";
            }
        }
    }
}

echo "[*] 迁移完成: 货架 {$sort} 个, 商品格 {$cells} 个\n";
if ($unmatched) {
    echo "[!] 未匹配 " . count($unmatched) . " 项（商品名与中枢 products 对不上，需人工在货架页补录）:\n";
    foreach ($unmatched as $u) echo "    - $u\n";
} else {
    echo "[*] 全部商品匹配成功\n";
}
