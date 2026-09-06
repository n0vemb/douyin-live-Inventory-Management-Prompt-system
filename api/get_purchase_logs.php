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
    $sortBy = $data["sort_by"] ?? "date";
    $sortDir = strtolower($data["sort_dir"] ?? "desc") === "asc" ? "asc" : "desc";
    // diff=仅看价差；same=仅看无价差；空=不过滤（兼容旧字段 price_diff）
    $diffMode = $data["price_diff_mode"] ?? "";
    if ($diffMode !== "diff" && $diffMode !== "same") {
        $diffMode = !empty($data["price_diff"]) ? "diff" : "";
    }

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
            p.series,
            p.barcode
        FROM inventory_batches ib
        LEFT JOIN products p ON ib.product_id = p.id
        WHERE 1=1" . ($storeId ? " AND ib.store_id = ?" : "") . "
            AND ib.remaining_qty > 0
    ";

    $params = [];
    if ($storeId) $params[] = $storeId;

    if ($keyword) {
        $sql .= " AND (p.name LIKE ? OR p.common_name LIKE ? OR p.barcode LIKE ? OR p.pinyin_initials LIKE ?)";
        $likeKeyword = "%$keyword%";
        for ($i = 0; $i < 4; $i++) $params[] = $likeKeyword;
    }

    if ($conditionType) {
        $sql .= " AND ib.condition_type = ?";
        $params[] = $conditionType;
    }

    // 日期筛选语义：圈定「该日期内有在库批次的 SKU」，但该 SKU 的库存/均价仍按
    // 全部在库批次计算（近1天入库的商品，与在库所有库存售价做价差比对，不因窗口截断均价）
    if ($startDate || $endDate) {
        $sub = "EXISTS (SELECT 1 FROM inventory_batches ib2
                WHERE ib2.product_id = ib.product_id AND ib2.condition_type = ib.condition_type
                  AND ib2.remaining_qty > 0";
        if ($storeId) {
            $sub .= " AND ib2.store_id = ?";
            $params[] = $storeId;
        }
        if ($startDate) {
            $sub .= " AND COALESCE(ib2.purchased_at, ib2.created_at) >= ?";
            $params[] = $startDate . " 00:00:00";
        }
        if ($endDate) {
            $sub .= " AND COALESCE(ib2.purchased_at, ib2.created_at) <= ?";
            $params[] = $endDate . " 23:59:59";
        }
        $sub .= ")";
        $sql .= " AND " . $sub;
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
                "series"        => $row["series"] ?? "",
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
                "price_history"  => [],
                "avg_sum_pq"     => 0.0,  // Σ(售价×在库数量)，用于SKU均价
                "avg_qty"        => 0,    // 参与均价计算的在库总数
                "avg_price"      => null, // SKU均价 = 满0.01进1取整
            ];
        }

        // 累加数量
        $groups[$key]["qty"] += (int)$row["qty"];

        // 收集该 SKU 所有批次的售价历史（按时间，用于 DOM 显示历史价格参考，不打印）
        $ph = ["t" => $row["purchased_at"], "p" => (float)$row["suggested_price"]];
        $groups[$key]["price_history"][] = $ph;

        // SKU均价 = Σ(批次售价×批次在库数量) ÷ 该SKU在库总数量（仅统计有售价的在库批次），满0.01进1
        if ((float)$row["suggested_price"] > 0) {
            $groups[$key]["avg_sum_pq"] += (float)$row["suggested_price"] * (int)$row["qty"];
            $groups[$key]["avg_qty"] += (int)$row["qty"];
        }

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

    $groupedRecords = array_values($groups);
    foreach ($groupedRecords as &$g) {
        $g["avg_price"] = $g["avg_qty"] > 0 ? (int)ceil($g["avg_sum_pq"] / $g["avg_qty"]) : null;
        unset($g["avg_sum_pq"], $g["avg_qty"]);
    }
    unset($g);
    // 仅看价差 / 仅看无价差：均价与最新批次售价差异 > 0.01 视为有价差
    if ($diffMode === "diff" || $diffMode === "same") {
        $groupedRecords = array_values(array_filter($groupedRecords, function ($g) use ($diffMode) {
            if ($g["avg_price"] === null) return false;
            if ($g["suggested_price"] === null) return false;
            $hasDiff = abs((float)$g["avg_price"] - (float)$g["suggested_price"]) > 0.01;
            return $diffMode === "diff" ? $hasDiff : !$hasDiff;
        }));
    }
    $sortCmp = function ($a, $b) use ($sortBy, $sortDir) {
        $va = null; $vb = null;
        switch ($sortBy) {
            case "batch_no": $va = (string)($a["batch_no"] ?? ""); $vb = (string)($b["batch_no"] ?? ""); break;
            case "barcode":  $va = (string)($a["barcode"] ?? "");  $vb = (string)($b["barcode"] ?? "");  break;
            case "name":     $va = (string)($a["product_name"] ?? $a["common_name"] ?? ""); $vb = (string)($b["product_name"] ?? $b["common_name"] ?? ""); break;
            case "sku":      $va = (string)($a["condition_type"] ?? ""); $vb = (string)($b["condition_type"] ?? ""); break;
            case "qty":      $va = (int)($a["qty"] ?? 0); $vb = (int)($b["qty"] ?? 0); break;
            case "price":    $va = (float)($a["suggested_price"] ?? 0); $vb = (float)($b["suggested_price"] ?? 0); break;
            case "date":
            default:         $va = (string)($a["purchased_at"] ?? ""); $vb = (string)($b["purchased_at"] ?? ""); break;
        }
        if (is_string($va)) {
            $cmp = strcmp($va, $vb);
        } else {
            $cmp = $va <=> $vb;
        }
        return $sortDir === "asc" ? $cmp : -$cmp;
    };
    usort($groupedRecords, $sortCmp);

    // 排序 price_history：时间倒序（最新在前），只保留价格不同的（去重连续相同价）
    foreach ($groupedRecords as &$g) {
        usort($g["price_history"], function ($a, $b) {
            $ta = (string)($a["t"] ?? ""); $tb = (string)($b["t"] ?? "");
            if ($ta !== $tb) return strcmp($tb, $ta); // 时间倒序
            return ($b["p"] ?? 0) <=> ($a["p"] ?? 0);
        });
        $dedup = [];
        foreach ($g["price_history"] as $ph) {
            $last = end($dedup);
            if ($last === false || abs(($last["p"] ?? -1) - $ph["p"]) > 0.001) {
                $dedup[] = ["t" => $ph["t"], "p" => $ph["p"]];
            }
        }
        $g["price_history"] = array_slice($dedup, 0, 5); // 最多保留 5 个历史价
    }
    unset($g);

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
