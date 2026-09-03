<?php
// 开启输出缓冲以防止意外输出
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// 设置错误处理，不显示错误到输出
error_reporting(0);
ini_set('display_errors', 0);

// 设置执行时间限制
set_time_limit(300); // 5分钟

// 注册错误处理器
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 注册异常处理器
set_exception_handler(function($exception) {
    // 清理输出缓冲
    if (ob_get_level()) {
        ob_clean();
    }
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '服务器错误: ' . $exception->getMessage()
    ]);
    exit;
});

// 注册关闭函数
register_shutdown_function(function() {
    if (connection_status() != CONNECTION_NORMAL) {
        // 如果连接异常，尝试输出错误信息
        if (ob_get_level()) {
            ob_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => '导入过程中断，请检查文件大小或服务器配置'
        ]);
    }
});

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/pinyin_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    outputJson(['success' => false, 'message' => '仅支持POST请求']);
}

try {
    // 初始化数据库连接
    $pdo = getDB();
requireAuth(); $storeId = getStoreId();
    if ($pdo === null) {
        throw new Exception('数据库连接失败');
    }

    // 读取请求参数（FormData 或 JSON 均可）
    $rawBody = file_get_contents('php://input');
    $jsonIn = json_decode($rawBody, true);
    if (!is_array($jsonIn)) $jsonIn = [];
    $mode = trim((string)($_POST['mode'] ?? ($jsonIn['mode'] ?? '')));

    // ===== 确认入库（第二阶段）：按预览 token 执行，不重新上传 =====
    if ($mode === 'commit') {
        $token = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($jsonIn['token'] ?? ''));
        if ($token === '') {
            throw new Exception('缺少预览标识，请重新上传文件');
        }
        $planFile = impPlanPath($storeId, $token);
        if (!is_file($planFile)) {
            throw new Exception('预览数据不存在或已过期（超时/换了店铺），请重新上传文件');
        }
        $plan = json_decode((string)file_get_contents($planFile), true);
        if (!is_array($plan) || !isset($plan['rows']) || (int)($plan['store_id'] ?? 0) !== (int)$storeId) {
            throw new Exception('预览数据无效，请重新上传文件');
        }

        $errors = [];
        $pdo->beginTransaction();
        try {
            list($successCount, $details) = impCommitRows($pdo, $storeId, $plan['rows'], $errors);
            if ($successCount > 0) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

        // 把执行结果写回留档，便于备查
        $plan['status'] = 'committed';
        $plan['committed_at'] = date('Y-m-d H:i:s');
        $plan['operator'] = $_SESSION['username'] ?? null;
        $plan['success_count'] = $successCount;
        $plan['details'] = $details;
        $plan['errors'] = $errors;
        @file_put_contents($planFile, json_encode($plan, JSON_UNESCAPED_UNICODE));

        outputJson([
            'success' => true,
            'data' => [
                'mode' => 'commit',
                'success_count' => $successCount,
                'total_count' => count($plan['rows']),
                'errors' => $errors,
                'details' => $details,
                'file_bak' => $plan['file_bak'] ?? '',
            ]
        ]);
    }
    
    // 检查是否有文件上传
    if (!isset($_FILES['import_file'])) {
        throw new Exception('没有检测到上传文件');
    }
    
    if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展停止'
        ];
        $errorMsg = $uploadErrors[$_FILES['import_file']['error']] ?? '文件上传失败';
        throw new Exception($errorMsg);
    }

    $file = $_FILES['import_file'];
    $fileName = $file['tmp_name'];
    $fileType = $file['type'];
    $fileSize = $file['size'];

    // 检查文件是否存在
    if (!file_exists($fileName)) {
        throw new Exception('上传的文件不存在');
    }

    // 检查文件大小
    if ($fileSize === 0) {
        throw new Exception('上传的文件为空');
    }

    // 扩展允许的文件类型
    $allowedTypes = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'application/csv',
        'text/plain'
    ];

    // 如果MIME类型不在允许列表中，尝试通过文件扩展名判断
    if (!in_array($fileType, $allowedTypes)) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xls', 'xlsx'])) {
            throw new Exception('仅支持Excel (.xlsx, .xls) 或 CSV 格式文件');
        }
    }

    $products = [];
    $errorMessages = []; // 初始化错误消息数组
    
    // 优先使用文件扩展名判断处理方式
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($extension === 'csv') {
        // 处理CSV文件
        $handle = fopen($fileName, 'r');
        if ($handle === false) {
            throw new Exception('无法读取CSV文件');
        }
        
        // 设置UTF-8编码
        if (function_exists('stream_filter_append')) {
            stream_filter_append($handle, 'convert.iconv.UTF-8/UTF-8');
        }
        
        $header = fgetcsv($handle, 1000, ',', '"', '\\');
        if ($header === false) {
            fclose($handle);
            throw new Exception('无法读取CSV文件头');
        }
        
        // 清理表头数据（剥离 UTF-8 BOM，避免第一列名带 \uFEFF 匹配不上）
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        $header = array_map(function($value) {
            return trim($value ?? '');
        }, $header);
        
        $rowIndex = 0;
        
        while (($row = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
            $rowIndex++;
            
            // 检查行是否为空
            if (empty(array_filter($row, function($val) { return $val !== null && $val !== ''; }))) {
                continue; // 跳过空行
            }
            
            try {
                $product = parseExcelRow($header, $row, $rowIndex, $pdo);
                if ($product) {
                    $products[] = $product;
                }
            } catch (Exception $e) {
                // 记录解析错误但继续处理其他行
                $errorMessages[] = "第{$rowIndex}行：数据解析错误 - " . $e->getMessage();
            }
        }
        
        fclose($handle);
    } elseif (in_array($extension, ['xls', 'xlsx'])) {
        // 处理Excel文件 - 使用简单方法
        try {
            if ($extension === 'xlsx') {
                // 处理XLSX文件（基于XML格式）
                $products = parseXlsxFile($fileName, $pdo);
            } else {
                // 对于XLS文件，建议转换为CSV格式
                throw new Exception('不支持.xls格式，请将文件转换为.xlsx或.csv格式');
            }
        } catch (Exception $e) {
            throw new Exception('Excel文件处理失败: ' . $e->getMessage());
        }
    } else {
        throw new Exception('不支持的文件格式，请使用CSV或Excel文件');
    }

    if (empty($products)) {
        throw new Exception('文件中没有有效的商品数据');
    }

    // ===== 解析预览（第一阶段）：只解析+留档，不写库存 =====
    if ($mode === 'preview') {
        $previewErrors = $errorMessages;
        $rows = impPreviewRows($pdo, $storeId, $products, $previewErrors);

        // 留档原始文件（uploads/import_bak/<store>/<token>-原名）
        $dir = impBackupDir($storeId);
        $token = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $bakName = $token . '-' . impSafeName($file['name']) . ($ext === '' ? '' : '');
        $bakFull = $dir . '/' . $bakName;
        if (!@copy($file['tmp_name'], $bakFull)) {
            throw new Exception('文件留档失败（目录不可写），已停止预览');
        }
        @chmod($bakFull, 0666);

        $plan = [
            'token' => $token,
            'store_id' => (int)$storeId,
            'created_at' => date('Y-m-d H:i:s'),
            'original_name' => $file['name'],
            'file_bak' => 'uploads/import_bak/' . (int)$storeId . '/' . $bakName,
            'rows' => $products,
            'status' => 'preview',
        ];
        if (!@file_put_contents(impPlanPath($storeId, $token), json_encode($plan, JSON_UNESCAPED_UNICODE))) {
            throw new Exception('预览数据保存失败（目录不可写）');
        }
        @chmod(impPlanPath($storeId, $token), 0666);

        outputJson([
            'success' => true,
            'data' => [
                'mode' => 'preview',
                'token' => $token,
                'total_count' => count($products),
                'file_bak' => 'uploads/import_bak/' . (int)$storeId . '/' . $bakName,
                'rows' => $rows,
                'errors' => $previewErrors,
            ]
        ]);
    }

    // 开始事务
    $pdo->beginTransaction();

    $successCount = 0;
    $errorMessages = [];

    foreach ($products as $index => $product) {
        try {
            $productId = null;

            if (empty($product['barcode'])) {
                // 条码为空：按商品名称匹配已有商品（限定本店铺，防止跨店串数据）
                $stmt = $pdo->prepare("SELECT id, barcode FROM products WHERE name = ? AND store_id = ?");
                $stmt->execute([$product['name'], $storeId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // 匹配到已有商品，使用其ID
                    $productId = $existing['id'];
                    // 如果匹配到的商品也没有条码，自动补上
                    if (empty($existing['barcode'])) {
                        $newBarcode = generateBarcode($pdo, $_SESSION['barcode_prefix'] ?? '69414486');
                        if ($newBarcode) {
                            $stmt = $pdo->prepare("UPDATE products SET barcode = ? WHERE id = ?");
                            $stmt->execute([$newBarcode, $productId]);
                        }
                    }
                } else {
                    // 未匹配到，自动生成条码新建商品
                    $barcode = generateBarcode($pdo, $_SESSION['barcode_prefix'] ?? '69414486');
                    if (empty($barcode)) {
                        $errorMessages[] = "第{$product['row']}行：条码生成失败，请稍后重试";
                        continue;
                    }
                    $product['barcode'] = $barcode;
                }
            }

            // 如果需要插入新商品（非空条码的新商品 / 空条码未匹配到后已补全条码）
            if ($productId === null) {
                // 检查条码是否已存在
                $stmt = $pdo->prepare("SELECT id FROM products WHERE barcode = ?");
                $stmt->execute([$product['barcode']]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $errorMessages[] = "第{$product['row']}行：条码 {$product['barcode']} 已存在";
                    continue;
                }

                // 插入商品
                $pinyinInitials = generatePinyinInitials($product['name']);
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, pinyin_initials, common_name, series, brand, barcode, qiandao_price, release_date, product_description, image_url, created_at, updated_at, store_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                ");

                $stmt->execute([
                    $product['name'],
                    $pinyinInitials,
                    $product['common_name'],
                    $product['series'],
                    $product['brand'],
                    $product['barcode'],
                    $product['qiandao_price'],
                    $product['release_date'],
                    $product['product_description'],
                    $product['image_url'],
                    $storeId
                ]);

                $productId = $pdo->lastInsertId();
            }
            
            // 如果有库存数据，添加库存
            if (!empty($product['inventory_data'])) {
                foreach ($product['inventory_data'] as $condition => $data) {
                    if ($data['quantity'] > 0) {
                        $batchNo = 'B' . date('YmdHis') . rand(1000, 9999);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory_batches (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark, purchased_at, created_at, store_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                        ");
                        
                        $stmt->execute([
                            $productId,
                            $condition,
                            $batchNo,
                            $data['purchase_price'],
                            $data['suggested_price'],
                            $data['quantity'],
                            $data['quantity'],
                            $data['supplier'] ?? null,
                            $data['remark'] ?? null,
                            $storeId
                        ]);

                        $stmt = $pdo->prepare("
                            INSERT INTO purchase_log (product_id, condition_type, purchase_price, qty, supplier, remark, store_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$productId, $condition, $data['purchase_price'], $data['quantity'], $data['supplier'] ?? null, $data['remark'] ?? null, $storeId]);
                    }
                }
            }
            
            $successCount++;
            
        } catch (Exception $e) {
            $errorMessages[] = "第{$product['row']}行：" . $e->getMessage();
        }
    }
    
    if ($successCount > 0) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
    
    // 输出成功响应
outputJson([
    'success' => true,
    'data' => [
        'success_count' => $successCount,
        'total_count' => count($products),
        'errors' => $errorMessages
    ]
]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 输出错误响应
    outputJson([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function outputJson($data) {
    // 清理输出缓冲
    if (ob_get_level()) {
        ob_clean();
    }
    
    // 确保头部设置
    header('Content-Type: application/json');
    header('Connection: close');
    
    // 输出JSON
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    
    // 立即刷新输出缓冲
    if (ob_get_level()) {
        ob_end_flush();
    }
    flush();
    
    exit;
}

/**
 * 将 Excel 列字母转换为 0-based 索引（A=0, B=1, ..., Z=25, AA=26, ...）
 */
function excelColIndex($col) {
    $index = 0;
    $len = strlen($col);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function parseXlsxFile($fileName, $pdo) {
    global $errorMessages;
    $products = [];

    if (!class_exists('ZipArchive')) {
        throw new Exception('需要ZipArchive扩展来处理Excel文件，请使用CSV格式');
    }

    $zip = new ZipArchive();
    if ($zip->open($fileName) !== TRUE) {
        throw new Exception('无法打开Excel文件');
    }

    // 读取共享字符串
    $sharedStrings = [];
    if ($zip->locateName('xl/sharedStrings.xml') !== false) {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $xml = simplexml_load_string($sharedStringsXml);
        if ($xml) {
            foreach ($xml->children() as $si) {
                // 兼容富文本条目 <si><r><t>..</t></r>..</si>：
                // 简单文本直接用 <si><t>..</t></si>；
                // 富文本拼接所有 <r> 子元素下的 <t>（保持顺序，含空格 run）。
                // 旧代码只认 isset($si->t)，遇到富文本条目整个跳过，
                // 导致 sharedStrings 索引错位，后续商品名称全部串位。
                $text = '';
                if (isset($si->t)) {
                    $text = (string)$si->t;
                } else {
                    foreach ($si->children() as $child) {
                        if ($child->getName() === 'r' && isset($child->t)) {
                            $text .= (string)$child->t;
                        }
                    }
                }
                $sharedStrings[] = $text;
            }
        }
    }

    // 读取工作表数据
    $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($worksheetXml === false) {
        $zip->close();
        throw new Exception('无法读取工作表数据');
    }

    $xml = simplexml_load_string($worksheetXml);
    if (!$xml) {
        $zip->close();
        throw new Exception('工作表数据格式错误');
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $cellValue = '';
            if (isset($cell->v)) {
                if ((string)$cell['t'] === 's') {
                    $index = (int)$cell->v;
                    $cellValue = $sharedStrings[$index] ?? '';
                } else {
                    $cellValue = (string)$cell->v;
                }
            }
            // 使用单元格引用（如 "A1"、"C3"）确定列位置
            $ref = (string)$cell['r'];
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                $colIdx = excelColIndex($m[1]);
                $rowData[$colIdx] = $cellValue;
            } else {
                $rowData[] = $cellValue;
            }
        }
        // 填充空缺列为空字符串，确保列位置对齐
        if (!empty($rowData)) {
            ksort($rowData);
            $maxCol = max(array_keys($rowData));
            for ($i = 0; $i <= $maxCol; $i++) {
                if (!array_key_exists($i, $rowData)) {
                    $rowData[$i] = '';
                }
            }
            ksort($rowData);
        }
        $rows[] = $rowData;
    }
    
    $zip->close();
    
    if (empty($rows)) {
        throw new Exception('Excel文件中没有数据');
    }
    
    // 解析数据
    $header = array_shift($rows);
    $header = array_map(function($value) {
        return trim($value ?? '');
    }, $header);
    
    foreach ($rows as $rowIndex => $row) {
        // 检查行是否为空
        if (empty(array_filter($row, function($val) { return $val !== null && $val !== ''; }))) {
            continue; // 跳过空行
        }
        
        try {
            $product = parseExcelRow($header, $row, $rowIndex + 2, $pdo);
            if ($product) {
                $products[] = $product;
            }
        } catch (Exception $e) {
            // 记录解析错误但继续处理其他行
            $errorMessages[] = "第" . ($rowIndex + 2) . "行：数据解析错误 - " . $e->getMessage();
        }
    }
    
    return $products;
}

function parseExcelRow($header, $row, $rowIndex, $pdo) {
    global $storeId;
    // 检查列数是否匹配
    if (count($header) !== count($row)) {
        // 如果列数不匹配，尝试填充缺失的列或截断多余的列
        if (count($row) < count($header)) {
            // 填充缺失的列为空字符串
            $row = array_pad($row, count($header), '');
        } else {
            // 截断多余的列
            $row = array_slice($row, 0, count($header));
        }
    }
    
    $data = array_combine($header, $row);
    
    // 必填字段验证
    if (empty($data['商品名称']) && empty($data['name'])) {
        return null;
    }
    
    $product = [
        'row' => $rowIndex,
        'name' => trim($data['商品名称'] ?? $data['name'] ?? ''),
        'common_name' => trim($data['常用名称'] ?? $data['common_name'] ?? ''),
        'series' => trim($data['系列'] ?? $data['series'] ?? ''),
        'brand' => trim($data['品牌'] ?? $data['brand'] ?? ''),
        'barcode' => trim($data['条码'] ?? $data['barcode'] ?? ''),
        'qiandao_price' => !empty($data['参考价'] ?? $data['qiandao_price']) ? floatval($data['参考价'] ?? $data['qiandao_price']) : null,
        'release_date' => !empty($data['发售时间'] ?? $data['release_date']) ? date('Y-m-d', strtotime($data['发售时间'] ?? $data['release_date'])) : null,
        'product_description' => trim($data['产品介绍'] ?? $data['product_description'] ?? ''),
        'image_url' => trim($data['图片链接'] ?? $data['image_url'] ?? ''),
        'inventory_data' => []
    ];
    
    // 获取系统配置中的状态类型
    $conditionTypes = [
        ['key' => 'sealed', 'name' => '原盒未拆'],
        ['key' => 'opened', 'name' => '拆盒无瑕'],
        ['key' => 'boxless', 'name' => '无盒无瑕'],
        ['key' => 'flawed', 'name' => '微瑕']
    ];
    
    try {
        if ($storeId) {
            $stmt = $pdo->prepare("SELECT condition_types FROM stores WHERE id = ?");
            $stmt->execute([$storeId]);
            $result = $stmt->fetch();
            if ($result && $result['condition_types']) {
                $conditionTypes = json_decode($result['condition_types'], true);
            }
        } else {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types' AND store_id IS NULL");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result && $result['setting_value']) {
                $conditionTypes = json_decode($result['setting_value'], true);
            }
        }
    } catch (Exception $e) {
        // 使用默认状态类型
    }
    
    // 解析库存数据
    foreach ($conditionTypes as $condition) {
        $conditionKey = $condition['key'];
        $conditionName = $condition['name'];
        
        // 安全地获取数量
        $quantity = 0;
        if (isset($data[$conditionName . '数量'])) {
            $quantity = intval($data[$conditionName . '数量']);
        } elseif (isset($data[$conditionKey . '_qty'])) {
            $quantity = intval($data[$conditionKey . '_qty']);
        }
        
        // 安全地获取进价
        $purchasePrice = 0;
        if (isset($data[$conditionName . '进价']) && !empty($data[$conditionName . '进价'])) {
            $purchasePrice = floatval($data[$conditionName . '进价']);
        } elseif (isset($data[$conditionKey . '_purchase_price']) && !empty($data[$conditionKey . '_purchase_price'])) {
            $purchasePrice = floatval($data[$conditionKey . '_purchase_price']);
        }
        
        // 安全地获取售价（自动向上取整: 35.01 -> 36, 99.9 -> 100）
        $suggestedPrice = 0;
        if (isset($data[$conditionName . '售价']) && !empty($data[$conditionName . '售价'])) {
            $suggestedPrice = ceil(floatval($data[$conditionName . '售价']));
        } elseif (isset($data[$conditionKey . '_suggested_price']) && !empty($data[$conditionKey . '_suggested_price'])) {
            $suggestedPrice = ceil(floatval($data[$conditionKey . '_suggested_price']));
        }
        
        if ($quantity > 0) {
            $product['inventory_data'][$conditionKey] = [
                'quantity' => $quantity,
                'purchase_price' => $purchasePrice,
                'suggested_price' => $suggestedPrice,
                'supplier' => trim($data['供应商'] ?? $data['supplier'] ?? ''),
                'remark' => trim($data['备注'] ?? $data['remark'] ?? '')
            ];
        }
    }
    
    return $product;
}

/* ================= 批量导入两段式（预览缓冲 + 留档）辅助 ================= */

function impBackupDir($storeId) {
    $dir = __DIR__ . '/../uploads/import_bak/' . (int)$storeId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function impPlanPath($storeId, $token) {
    return impBackupDir($storeId) . '/' . $token . '.json';
}

function impSafeName($n) {
    $n = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/', '_', (string)$n);
    $n = trim($n, ' ._');
    return $n === '' ? 'import' : $n;
}

/** 预览：逐行给出 Excel 内容 + 预计动作（新建/匹配/跳过），不写库 */
function impPreviewRows($pdo, $storeId, $products, &$errors) {
    $rows = [];
    foreach ($products as $p) {
        $skus = [];
        foreach (($p['inventory_data'] ?? []) as $key => $d) {
            $skus[] = [
                'key' => $key,
                'qty' => (int)$d['quantity'],
                'purchase_price' => (float)$d['purchase_price'],
                'suggested_price' => (float)$d['suggested_price'],
            ];
        }
        $barcode = trim((string)($p['barcode'] ?? ''));
        $row = [
            'row' => (int)($p['row'] ?? 0),
            'name' => (string)($p['name'] ?? ''),
            'series' => (string)($p['series'] ?? ''),
            'brand' => (string)($p['brand'] ?? ''),
            'barcode' => $barcode,
            'skus' => $skus,
            'action' => 'new',
            'action_note' => '将新建商品（条码自动生成）',
            'matched_name' => null,
        ];

        if ($barcode === '') {
            $stmt = $pdo->prepare('SELECT id, name, barcode FROM products WHERE name = ? AND store_id = ?');
            $stmt->execute([$row['name'], $storeId]);
            $existing = $stmt->fetch();
            if ($existing) {
                $row['action'] = 'match';
                $row['action_note'] = '将匹配已有商品并追加库存';
                $row['matched_name'] = $existing['name'];
            }
        } else {
            $stmt = $pdo->prepare('SELECT id, name FROM products WHERE barcode = ? AND store_id = ?');
            $stmt->execute([$barcode, $storeId]);
            if ($stmt->fetch()) {
                $row['action'] = 'skip';
                $row['action_note'] = '条码已存在，不会重复创建（本行将被跳过）';
                $errors[] = "第{$row['row']}行：条码 {$barcode} 已存在，跳过";
            } else {
                $row['action_note'] = '将新建商品';
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

/** 提交入库：按预览结果实际写入商品/批次/入库记录，返回逐行比对明细 */
function impCommitRows($pdo, $storeId, $products, &$errors) {
    $successCount = 0;
    $details = [];

    foreach ($products as $product) {
        $rowNo = (int)($product['row'] ?? 0);
        $skus = [];
        foreach (($product['inventory_data'] ?? []) as $key => $d) {
            $skus[] = [
                'key' => $key,
                'qty' => (int)$d['quantity'],
                'purchase_price' => (float)$d['purchase_price'],
                'suggested_price' => (float)$d['suggested_price'],
            ];
        }
        $res = [
            'row' => $rowNo,
            'name' => (string)($product['name'] ?? ''),
            'series' => (string)($product['series'] ?? ''),
            'brand' => (string)($product['brand'] ?? ''),
            'barcode' => trim((string)($product['barcode'] ?? '')),
            'skus' => $skus,
            'action' => 'skip',
            'action_note' => '',
            'matched_name' => null,
        ];

        try {
            $productId = null;
            $matched = false;

            if ($res['barcode'] === '') {
                $stmt = $pdo->prepare('SELECT id, barcode, name FROM products WHERE name = ? AND store_id = ?');
                $stmt->execute([$res['name'], $storeId]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $productId = (int)$existing['id'];
                    $matched = true;
                    $res['matched_name'] = $existing['name'];
                    if (empty($existing['barcode'])) {
                        $newBarcode = generateBarcode($pdo, $_SESSION['barcode_prefix'] ?? '69414486');
                        if ($newBarcode) {
                            $upd = $pdo->prepare('UPDATE products SET barcode = ? WHERE id = ?');
                            $upd->execute([$newBarcode, $productId]);
                            $res['barcode'] = $newBarcode;
                        }
                    } else {
                        $res['barcode'] = $existing['barcode'];
                    }
                } else {
                    $newBarcode = generateBarcode($pdo, $_SESSION['barcode_prefix'] ?? '69414486');
                    if (empty($newBarcode)) {
                        throw new Exception('条码生成失败，请稍后重试');
                    }
                    $res['barcode'] = $newBarcode;
                    $product['barcode'] = $newBarcode;
                }
            }

            if ($productId === null) {
                $stmt = $pdo->prepare('SELECT id FROM products WHERE barcode = ?');
                $stmt->execute([$res['barcode']]);
                if ($stmt->fetch()) {
                    $errors[] = "第{$rowNo}行：条码 {$res['barcode']} 已存在，跳过";
                    $res['action_note'] = '条码已存在，未导入';
                    $details[] = $res;
                    continue;
                }
                $pinyinInitials = generatePinyinInitials($res['name']);
                $ins = $pdo->prepare("INSERT INTO products (name, pinyin_initials, common_name, series, brand, barcode, qiandao_price, release_date, product_description, image_url, created_at, updated_at, store_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
                $ins->execute([
                    $res['name'],
                    $pinyinInitials,
                    trim((string)($product['common_name'] ?? '')),
                    $res['series'],
                    $res['brand'],
                    $res['barcode'],
                    !empty($product['qiandao_price']) ? floatval($product['qiandao_price']) : null,
                    !empty($product['release_date']) ? $product['release_date'] : null,
                    trim((string)($product['product_description'] ?? '')),
                    trim((string)($product['image_url'] ?? '')),
                    $storeId,
                ]);
                $productId = (int)$pdo->lastInsertId();
            }

            foreach (($product['inventory_data'] ?? []) as $condition => $data) {
                if ((int)$data['quantity'] <= 0) continue;
                $batchNo = 'B' . date('YmdHis') . rand(1000, 9999);
                $ins = $pdo->prepare("INSERT INTO inventory_batches (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark, purchased_at, created_at, store_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
                $ins->execute([
                    $productId,
                    $condition,
                    $batchNo,
                    $data['purchase_price'],
                    $data['suggested_price'],
                    (int)$data['quantity'],
                    (int)$data['quantity'],
                    $data['supplier'] ?? null,
                    $data['remark'] ?? null,
                    $storeId,
                ]);
                $log = $pdo->prepare("INSERT INTO purchase_log (product_id, condition_type, purchase_price, qty, supplier, remark, store_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $log->execute([
                    $productId,
                    $condition,
                    $data['purchase_price'],
                    (int)$data['quantity'],
                    $data['supplier'] ?? null,
                    $data['remark'] ?? null,
                    $storeId,
                ]);
            }

            $successCount++;
            $res['action'] = $matched ? 'match' : 'new';
            $res['action_note'] = $matched ? '已匹配商品并追加库存' : '已新建商品并入库';
        } catch (Exception $e) {
            $errors[] = "第{$rowNo}行：" . $e->getMessage();
            $res['action_note'] = $e->getMessage();
        }
        $details[] = $res;
    }
    return [$successCount, $details];
}
