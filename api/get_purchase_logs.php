<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../auth.php";

header("Content-Type: application/json");

try {
    $pdo = getDB();
    requireAuth();
    $storeId = getStoreId();

    $data = json_decode(file_get_contents("php://input"), true);

    $startDate = $data["start_date"] ?? "";
    $endDate = $data["end_date"] ?? "";
    $keyword = $data["keyword"] ?? "";
    $conditionType = $data["condition_type"] ?? "";
    $page = (int)($data["page"] ?? 1);
    $pageSize = (int)($data["page_size"] ?? 20);

    $offset = ($page - 1) * $pageSize;

    // 查所有符合条件的批次（不分页，后续 PHP 分组后再手动分页）
    $sql = "
        SELECT
            ib.id as batch_id,
            ib.batch_no,
            ib.product_id,
            ib.condition_type,
            ib.purchase_price,
            ib.suggested_price,
            ib.remaining_qty as qty,
            ib.supplier,
            ib.remark,
            COALESCE(ib.purchased_at, ib.created_at) as purchased_at,
            p.name as product_name,
            p.common_name,
            p.barcode
        FROM inventory_batches ib
        LEFT JOIN products p ON ib.product_id = p.id
        WHERE 1=1" . ($storeId ? " AND ib.store_id = ?" : "") . "
            AND ib.remaining_qty > 0
    ";

    $params = [];
    if ($storeId) $params[] = $storeId;

    if ($startDate) {
        $sql .= " AND COALESCE(ib.purchased_at, ib.created_at) >= ?";
        $params[] = $startDate . " 00:00:00";
    }

    if ($endDate) {
        $sql .= " AND COALESCE(ib.purchased_at, ib.created_at) <= ?";
        $params[] = $endDate . " 23:59:59";
    }

    if ($keyword) {
        $sql .= " AND (p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ? OR p.pinyin_initials LIKE ?)";
        $likeKeyword = "%$keyword%";
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
    }

    if ($conditionType) {
        $sql .= " AND ib.condition_type = ?";
        $params[] = $conditionType;
    }

    // ---------- 改：PHP 端按 product_id + condition_type 合并 ----------

    // 不排序 LIMIT，全部查出后在 PHP 分组
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // 按 product_id + condition_type 分组
    $groups = [];
    foreach ($records as $row) {
        $key = $row["product_id"] . "_" . $row["condition_type"];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                "product_id"    => $row["product_id"],
                "condition_type"=> $row["condition_type"],
                "product_name"  => $row["product_name"],
                "common_name"   => $row["common_name"],
                "barcode"       => $row["barcode"],
                "qty"           => 0,
                // 最新批次的信息
                "batch_id"       => null,
                "batch_no"       => null,
                "purchase_price" => null,
                "suggested_price"=> null,
                "supplier"       => null,
                "remark"         => null,
                "purchased_at"   => null,
                "latest_time"    => null,
            ];
        }

        // 累加数量
        $groups[$key]["qty"] += (int)$row["qty"];

        // 取时间最新的批次的价格等信息
        $t = $row["purchased_at"];
        if ($t !== null) {
            if ($groups[$key]["latest_time"] === null || $t > $groups[$key]["latest_time"]) {
                $groups[$key]["latest_time"]    = $t;
                $groups[$key]["batch_id"]       = $row["batch_id"];
                $groups[$key]["batch_no"]       = $row["batch_no"];
                $groups[$key]["purchase_price"] = $row["purchase_price"];
                $groups[$key]["suggested_price"]= $row["suggested_price"];
                $groups[$key]["supplier"]       = $row["supplier"];
                $groups[$key]["remark"]         = $row["remark"];
                $groups[$key]["purchased_at"]   = $t;
            }
        } elseif ($groups[$key]["latest_time"] === null) {
            // 没有时间信息也保底填上
            $groups[$key]["batch_id"]       = $row["batch_id"];
            $groups[$key]["batch_no"]       = $row["batch_no"];
            $groups[$key]["purchase_price"] = $row["purchase_price"];
            $groups[$key]["suggested_price"]= $row["suggested_price"];
            $groups[$key]["supplier"]       = $row["supplier"];
            $groups[$key]["remark"]         = $row["remark"];
        }
    }

    // 按 purchased_at DESC 排序（最新在前）
    $groupedRecords = array_values($groups);
    usort($groupedRecords, function ($a, $b) {
        return strcmp((string)($b["purchased_at"] ?? ""), (string)($a["purchased_at"] ?? ""));
    });

    // 手动分页
    $total = count($groupedRecords);
    $paged = array_slice($groupedRecords, $offset, $pageSize);

    echo json_encode([
        "success" => true,
        "data" => [
            "records"  => $paged,
            "total"    => $total,
            "page"     => $page,
            "page_size"=> $pageSize,
        ],
    ]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error"   => "数据库查询失败: " . $e->getMessage(),
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error"   => "服务器错误: " . $e->getMessage(),
    ]);
}
