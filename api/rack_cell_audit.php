<?php
// 盘点留痕：一致 / 有差异调整后都算“盘过”，驱动货架页 24h 绿框
// scope=cell（默认）：按货架格记录；scope=product：未上架商品（临时货架）按商品记录
// result: same=与线上一致, adjusted=有差异已调整
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('请使用POST方法');
}
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? '';
if ($action !== 'record') {
    error('未知操作');
}

try {
    $pdo = getDB();
    requirePerm('audit.rack');
    $storeId = getStoreId();
    if (empty($storeId)) {
        error('请先选择店铺后再操作');
    }
    $scope = (string)($input['scope'] ?? 'cell');
    $result = (string)($input['result'] ?? '');
    $productId = (int)($input['product_id'] ?? 0);
    if (!in_array($scope, ['cell', 'product'], true)) {
        error('scope 参数错误');
    }
    if ($productId <= 0) {
        error('参数错误');
    }
    if (!in_array($result, ['same', 'adjusted'], true)) {
        error('result 参数错误');
    }

    // 商品必须存在且属于本店
    $stmt = $pdo->prepare('SELECT id FROM products WHERE id = ? AND store_id = ?');
    $stmt->execute([$productId, $storeId]);
    if (!$stmt->fetch()) {
        error('商品不存在或不属于本店铺');
    }
    $userId = $_SESSION['user_id'] ?? null;

    // 未上架商品（临时货架）：按商品留痕，商品必须仍不在任何货架格
    if ($scope === 'product') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_audits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL DEFAULT 1 COMMENT '所属店铺',
            product_id INT NOT NULL COMMENT '盘点商品',
            result VARCHAR(20) NOT NULL DEFAULT 'same' COMMENT 'same=与线上一致 adjusted=有差异已调整',
            user_id INT DEFAULT NULL COMMENT '盘点人',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_product_recent (store_id, product_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt = $pdo->prepare('SELECT 1 FROM warehouse_rack_cells WHERE product_id = ? AND store_id = ? LIMIT 1');
        $stmt->execute([$productId, $storeId]);
        if ($stmt->fetch()) {
            error('该商品已上架，请在货架上盘对应格子');
        }
        $stmt = $pdo->prepare("
            INSERT INTO product_audits (store_id, product_id, result, user_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$storeId, $productId, $result, $userId]);
        success(['message' => '已记录盘过']);
    }

    // 货架格盘点
    $code = trim((string)($input['rack'] ?? ''));
    $row = (int)($input['row'] ?? 0);
    $pos = (int)($input['pos'] ?? 0);
    if ($code === '' || $row <= 0 || $pos <= 0) {
        error('参数错误');
    }

    // 兼容尚未手动执行迁移的情况：幂等建表（与 database_v19_rack_cell_audit.sql 一致）
    $pdo->exec("CREATE TABLE IF NOT EXISTS rack_cell_audits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL DEFAULT 1 COMMENT '所属店铺',
        rack_id INT NOT NULL COMMENT '所属货架',
        row_no TINYINT NOT NULL COMMENT '层',
        pos_no TINYINT NOT NULL COMMENT '小格位',
        product_id INT NOT NULL COMMENT '盘点时该格商品',
        result VARCHAR(20) NOT NULL DEFAULT 'same' COMMENT 'same=与线上一致 adjusted=有差异已调整',
        user_id INT DEFAULT NULL COMMENT '盘点人',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_cell_recent (store_id, rack_id, row_no, pos_no, created_at),
        KEY idx_product_recent (store_id, product_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 校验该格当前商品仍是盘点时的商品（中途换商品则旧记录不生效，需重盘）
    $stmt = $pdo->prepare("
        SELECT c.id, c.product_id, r.id AS rack_id
        FROM warehouse_rack_cells c
        JOIN warehouse_racks r ON r.id = c.rack_id
        WHERE r.code = ? AND c.row_no = ? AND c.pos_no = ? AND c.store_id = ?
    ");
    $stmt->execute([$code, $row, $pos, $storeId]);
    $cell = $stmt->fetch();
    if (!$cell) {
        error('该格当前没有商品');
    }
    if ((int)$cell['product_id'] !== $productId) {
        error('该格商品已变动，请重新盘点');
    }

    $stmt = $pdo->prepare("
        INSERT INTO rack_cell_audits (store_id, rack_id, row_no, pos_no, product_id, result, user_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$storeId, (int)$cell['rack_id'], $row, $pos, $productId, $result, $userId]);
    success(['message' => '已记录盘过']);
} catch (Exception $e) {
    error($e->getMessage());
}
