<?php
// 货架格子操作：录入商品 put / 移除 remove / 拆分大格 split（仅店铺管理员）
// 布局：每层 10 小格位（pos 1-10），大格 = (1,2)(3,4)(5,6)(7,8)(9,10)
// span=2 整大格（pos 必须为奇数），span=1 半大格；cells 表只存有商品的格
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('请使用POST方法');
}
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';

try {
    $pdo = getDB();
    requireAuth();
    $storeId = getStoreId();
    if (!in_array($_SESSION['role'] ?? '', ['store_admin', 'super_admin'])) {
        error('无权限：仅店铺管理员可管理货架');
    }

    // 取货架 id（校验归属）
    $getRackId = function ($code) use ($pdo, $storeId) {
        $stmt = $pdo->prepare('SELECT id FROM warehouse_racks WHERE code = ?' . ($storeId ? ' AND store_id = ?' : ''));
        $stmt->execute($storeId ? [$code, $storeId] : [$code]);
        $id = $stmt->fetchColumn();
        if (!$id) error('货架「' . $code . '」不存在');
        return (int)$id;
    };

    // 校验该行指定区间是否与已占用格冲突（返回冲突描述或 null）
    $checkConflict = function ($rackId, $row, $from, $to) use ($pdo) {
        $stmt = $pdo->prepare('SELECT pos_no, span, product_id FROM warehouse_rack_cells WHERE rack_id = ? AND row_no = ?');
        $stmt->execute([$rackId, $row]);
        foreach ($stmt->fetchAll() as $c) {
            $cFrom = (int)$c['pos_no'];
            $cTo = $cFrom + (int)$c['span'] - 1;
            if ($from <= $cTo && $to >= $cFrom) {
                return '第' . $row . '层 第' . $cFrom . ($cTo > $cFrom ? '-' . $cTo : '') . ' 格已被占用';
            }
        }
        return null;
    };

    // 店铺布局（不写死 5×5）：stores.rack_layout，NULL=默认 5层×5大格（10小格）
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
    $maxPos = $layout['big_cols'] * 2;

    switch ($action) {
        case 'put': {
            $code = trim((string)($input['rack'] ?? ''));
            $row = (int)($input['row'] ?? 0);
            $pos = (int)($input['pos'] ?? 0);
            $span = (int)($input['span'] ?? 1);
            $productId = (int)($input['product_id'] ?? 0);
            $note = trim((string)($input['note'] ?? ''));
            if ($code === '' || $row < 1 || $row > $layout['rows']) error('货架/层参数错误（本店 ' . $layout['rows'] . ' 层）');
            if ($pos < 1 || $pos > $maxPos) error('格位需在 1-' . $maxPos);
            if ($span !== 1 && $span !== 2) error('占格数只能为 1 或 2');
            if ($span === 2 && $pos % 2 !== 1) error('整大格（占2格）必须从奇数格位开始（1/3/5/7/9）');
            if ($productId <= 0) error('请选择商品');
            // 商品必须存在且属于本店
            $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ?' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$productId, $storeId] : [$productId]);
            if (!$stmt->fetch()) error('商品不存在或不属于本店铺');
            $rackId = $getRackId($code);

            $to = $pos + $span - 1;
            $conflict = $checkConflict($rackId, $row, $pos, $to);
            if ($conflict) error('格位冲突：' . $conflict);

            $stmt = $pdo->prepare('INSERT INTO warehouse_rack_cells (store_id, rack_id, row_no, pos_no, span, product_id, note) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$storeId, $rackId, $row, $pos, $span, $productId, $note]);
            success(['message' => '已录入']);
        }

        case 'remove': {
            $code = trim((string)($input['rack'] ?? ''));
            $row = (int)($input['row'] ?? 0);
            $pos = (int)($input['pos'] ?? 0);
            if ($code === '' || $row < 1 || $row > $layout['rows'] || $pos < 1 || $pos > $maxPos) error('参数错误');
            $rackId = $getRackId($code);
            $stmt = $pdo->prepare('DELETE FROM warehouse_rack_cells WHERE rack_id = ? AND row_no = ? AND pos_no = ?');
            $stmt->execute([$rackId, $row, $pos]);
            if ($stmt->rowCount() === 0) error('该格无商品');
            success(['message' => '已移除']);
        }

        case 'split': {
            $code = trim((string)($input['rack'] ?? ''));
            $row = (int)($input['row'] ?? 0);
            $pos = (int)($input['pos'] ?? 0);
            if ($code === '' || $row < 1 || $row > $layout['rows'] || $pos < 1 || $pos > $maxPos) error('参数错误');
            $rackId = $getRackId($code);
            $stmt = $pdo->prepare('SELECT id, span FROM warehouse_rack_cells WHERE rack_id = ? AND row_no = ? AND pos_no = ?');
            $stmt->execute([$rackId, $row, $pos]);
            $cell = $stmt->fetch();
            if (!$cell) error('该格无商品');
            if ((int)$cell['span'] !== 2) error('该格不是合并大格，无需拆分');
            $stmt = $pdo->prepare('UPDATE warehouse_rack_cells SET span = 1 WHERE id = ?');
            $stmt->execute([$cell['id']]);
            success(['message' => '已拆分为两格（商品保留在第 ' . $pos . ' 格）']);
        }

        case 'replace': {
            // 拖拽替换：目标格已有商品则替换（保持原格 span），返回被替换的 product_id
            $code = trim((string)($input['rack'] ?? ''));
            $row = (int)($input['row'] ?? 0);
            $pos = (int)($input['pos'] ?? 0);
            $productId = (int)($input['product_id'] ?? 0);
            if ($code === '' || $row < 1 || $row > $layout['rows'] || $pos < 1 || $pos > $maxPos) error('参数错误');
            if ($productId <= 0) error('请选择商品');
            $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ?' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$productId, $storeId] : [$productId]);
            if (!$stmt->fetch()) error('商品不存在或不属于本店铺');
            $rackId = $getRackId($code);

            $stmt = $pdo->prepare('SELECT id, product_id FROM warehouse_rack_cells WHERE rack_id = ? AND row_no = ? AND pos_no = ?');
            $stmt->execute([$rackId, $row, $pos]);
            $cell = $stmt->fetch();
            $replaced = $cell ? (int)$cell['product_id'] : null;
            if ($cell) {
                // 保持原格 span，替换商品
                $stmt = $pdo->prepare('UPDATE warehouse_rack_cells SET product_id = ?, note = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$productId, $cell['id']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO warehouse_rack_cells (store_id, rack_id, row_no, pos_no, span, product_id) VALUES (?,?,?,?,1,?)');
                $stmt->execute([$storeId, $rackId, $row, $pos, $productId]);
            }
            success(['message' => '已放入' . ($replaced ? '（原商品已移出货架）' : ''), 'replaced' => $replaced]);
        }

        default:
            error('未知操作');
    }
} catch (Exception $e) {
    error($e->getMessage());
}
