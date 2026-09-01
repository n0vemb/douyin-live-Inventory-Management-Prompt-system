<?php
// 货架管理：新增 / 更名 / 删除 / 排序（仅店铺管理员）
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

    switch ($action) {
        case 'add_rack': {
            $code = trim((string)($input['code'] ?? ''));
            if ($code === '') error('请输入货架号');
            if (mb_strlen($code) > 50) error('货架号过长（最多50字符）');
            $stmt = $pdo->prepare('SELECT id FROM warehouse_racks WHERE code = ?' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$code, $storeId] : [$code]);
            if ($stmt->fetch()) error('货架「' . $code . '」已存在');
            $max = (int)$pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM warehouse_racks' . ($storeId ? ' WHERE store_id = ' . (int)$storeId : ''))->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO warehouse_racks (store_id, code, sort_order) VALUES (?,?,?)');
            $stmt->execute([$storeId, $code, $max + 1]);
            success(['message' => '货架「' . $code . '」已创建']);
        }

        case 'rename': {
            $code = trim((string)($input['code'] ?? ''));
            $newCode = trim((string)($input['new_code'] ?? ''));
            if ($code === '' || $newCode === '') error('参数缺失');
            if (mb_strlen($newCode) > 50) error('货架号过长');
            if ($code === $newCode) success(['message' => '无变化']);
            $stmt = $pdo->prepare('SELECT id FROM warehouse_racks WHERE code = ? AND code <> ?' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$newCode, $code, $storeId] : [$newCode, $code]);
            if ($stmt->fetch()) error('货架「' . $newCode . '」已存在');
            $stmt = $pdo->prepare('UPDATE warehouse_racks SET code = ? WHERE code = ?' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$newCode, $code, $storeId] : [$newCode, $code]);
            success(['message' => '已更名为「' . $newCode . '」']);
        }

        case 'delete_rack': {
            $code = trim((string)($input['code'] ?? ''));
            if ($code === '') error('参数缺失');
            // 格子随货架级联删除（FK ON DELETE CASCADE）
            $stmt = $pdo->prepare('DELETE FROM warehouse_racks WHERE code = ?' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$code, $storeId] : [$code]);
            if ($stmt->rowCount() === 0) error('货架不存在');
            success(['message' => '货架「' . $code . '」已删除']);
        }

        case 'reorder': {
            $order = $input['order'] ?? [];
            if (!is_array($order)) error('参数格式错误');
            $stmt = $pdo->prepare('SELECT id, code FROM warehouse_racks WHERE 1=1' . ($storeId ? ' AND store_id = ?' : ''));
            $stmt->execute($storeId ? [$storeId] : []);
            $map = [];
            foreach ($stmt->fetchAll() as $r) $map[$r['code']] = (int)$r['id'];
            $upd = $pdo->prepare('UPDATE warehouse_racks SET sort_order = ? WHERE id = ?');
            $i = 0;
            foreach ($order as $code) {
                if (isset($map[$code])) { $upd->execute([$i, $map[$code]]); $i++; }
            }
            success(['message' => '排序已保存']);
        }

        case 'set_layout': {
            // 店铺货架布局（每店铺可不同）：{rows, big_cols}
            $rows = (int)($input['rows'] ?? 0);
            $bigCols = (int)($input['big_cols'] ?? 0);
            if ($rows < 1 || $rows > 10 || $bigCols < 1 || $bigCols > 10) {
                error('布局参数非法（层数/每层大格数需在 1-10）');
            }
            if (!$storeId) error('超管账号无店铺布局');
            $pdo->prepare('UPDATE stores SET rack_layout = ? WHERE id = ?')
                ->execute([json_encode(['rows' => $rows, 'big_cols' => $bigCols]), $storeId]);
            success(['message' => '货架布局已更新为 ' . $rows . ' 层 × ' . $bigCols . ' 大格']);
        }

        default:
            error('未知操作');
    }
} catch (Exception $e) {
    error($e->getMessage());
}
