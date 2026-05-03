<?php
require_once '../config.php';

header('Content-Type: application/json');

try {
    $pdo = getDB();

    $data = json_decode(file_get_contents('php://input'), true);
    
    $startDate = $data['start_date'] ?? '';
    $endDate = $data['end_date'] ?? '';
    $keyword = $data['keyword'] ?? '';
    $conditionType = $data['condition_type'] ?? '';
    $page = (int)($data['page'] ?? 1);
    $pageSize = (int)($data['page_size'] ?? 20);

    $offset = ($page - 1) * $pageSize;

    $sql = "
        SELECT 
            ib.id as batch_id,
            ib.batch_no,
            ib.product_id,
            ib.condition_type,
            ib.purchase_price,
            ib.suggested_price,
            ib.total_qty as qty,
            ib.supplier,
            ib.remark,
            COALESCE(ib.purchased_at, ib.created_at) as purchased_at,
            p.name as product_name,
            p.common_name,
            p.barcode
        FROM inventory_batches ib
        LEFT JOIN products p ON ib.product_id = p.id
        WHERE 1=1
    ";

    $params = [];

    if ($startDate) {
        $sql .= " AND COALESCE(ib.purchased_at, ib.created_at) >= ?";
        $params[] = $startDate . ' 00:00:00';
    }

    if ($endDate) {
        $sql .= " AND COALESCE(ib.purchased_at, ib.created_at) <= ?";
        $params[] = $endDate . ' 23:59:59';
    }

    if ($keyword) {
        $sql .= " AND (p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ?)";
        $likeKeyword = "%$keyword%";
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
    }

    if ($conditionType) {
        $sql .= " AND ib.condition_type = ?";
        $params[] = $conditionType;
    }

    $countSql = "SELECT COUNT(*) as total FROM ($sql) as temp";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    $sql .= " ORDER BY COALESCE(ib.purchased_at, ib.created_at) DESC LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $pageSize;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => [
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize
        ]
    ]);

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '数据库查询失败: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => '服务器错误: ' . $e->getMessage()
    ]);
}
?>