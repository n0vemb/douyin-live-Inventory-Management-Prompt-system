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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    outputJson(['success' => false, 'message' => '仅支持POST请求']);
}

try {
    // 初始化数据库连接
    $pdo = getDB();
    if ($pdo === null) {
        throw new Exception('数据库连接失败');
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
        
        // 清理表头数据
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

    // 开始事务
    $pdo->beginTransaction();
    
    $successCount = 0;
    $errorMessages = [];
    
    foreach ($products as $index => $product) {
        try {
            // 检查条码是否已存在
            $stmt = $pdo->prepare("SELECT id FROM products WHERE barcode = ?");
            $stmt->execute([$product['barcode']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $errorMessages[] = "第{$product['row']}行：条码 {$product['barcode']} 已存在";
                continue;
            }
            
            // 插入商品
            $stmt = $pdo->prepare("
                INSERT INTO products (name, common_name, series, brand, barcode, qiandao_price, release_date, product_description, image_url, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $product['name'],
                $product['common_name'],
                $product['series'],
                $product['brand'],
                $product['barcode'],
                $product['qiandao_price'],
                $product['release_date'],
                $product['product_description'],
                $product['image_url']
            ]);
            
            $productId = $pdo->lastInsertId();
            
            // 如果有库存数据，添加库存
            if (!empty($product['inventory_data'])) {
                foreach ($product['inventory_data'] as $condition => $data) {
                    if ($data['quantity'] > 0) {
                        $batchNo = 'B' . date('YmdHis') . rand(1000, 9999);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory_batches (product_id, condition_type, batch_no, purchase_price, suggested_price, total_qty, remaining_qty, supplier, remark, purchased_at, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
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
                            $data['remark'] ?? null
                        ]);
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

function parseXlsxFile($fileName, $pdo) {
    global $errorMessages; // 使用全局变量来收集错误
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
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                }
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
                    // 共享字符串
                    $index = (int)$cell->v;
                    $cellValue = $sharedStrings[$index] ?? '';
                } else {
                    // 数值或直接值
                    $cellValue = (string)$cell->v;
                }
            }
            $rowData[] = $cellValue;
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
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'condition_types'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && $result['setting_value']) {
            $conditionTypes = json_decode($result['setting_value'], true);
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
        
        // 安全地获取售价
        $suggestedPrice = 0;
        if (isset($data[$conditionName . '售价']) && !empty($data[$conditionName . '售价'])) {
            $suggestedPrice = floatval($data[$conditionName . '售价']);
        } elseif (isset($data[$conditionKey . '_suggested_price']) && !empty($data[$conditionKey . '_suggested_price'])) {
            $suggestedPrice = floatval($data[$conditionKey . '_suggested_price']);
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
