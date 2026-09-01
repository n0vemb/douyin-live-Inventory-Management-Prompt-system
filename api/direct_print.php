<?php
// 安全缓冲：只在无缓冲时启动，避免与 config.php 的 ob_clean 冲突
if (!ob_get_level()) ob_start();
header('Content-Type: application/json');

// 致命错误兜底
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'error' => 'PHP: ' . $err['message']), JSON_UNESCAPED_UNICODE);
    }
});

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../auth.php';
    require_once __DIR__ . '/condition_common.php';

    // 检查 GD 库
    if (!function_exists('imagecreatetruecolor')) {
        error('服务器未安装 PHP GD 库，无法生成标签图片');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['template'])) {
        error('缺少标签数据或模板配置');
    }

    // 支持 batch_ids 模式：从数据库重新查最新数据，避免前端缓存导致打印错误
    if (isset($input['batch_ids']) && is_array($input['batch_ids'])) {
        $pdo = getDB();
requireAuth(); $storeId = getStoreId();
        $placeholders = implode(',', array_fill(0, count($input['batch_ids']), '?'));
        $stmt = $pdo->prepare("
            SELECT ib.id AS batch_id, ib.batch_no, ib.remaining_qty, ib.suggested_price, ib.condition_type,
                   p.barcode, COALESCE(p.common_name, p.name) AS product_name, p.common_name, p.series,
                   COALESCE(ib.purchased_at, ib.created_at) AS purchased_at
            FROM inventory_batches ib
            JOIN products p ON ib.product_id = p.id
            WHERE ib.id IN ({$placeholders})" . ($storeId ? " AND ib.store_id = ?" : "") . "
        ");
        $params = array_map('intval', $input['batch_ids']);
        if ($storeId) $params[] = $storeId;
        $stmt->execute($params);
        $batchRows = $stmt->fetchAll();

        $condMap = conditionNames($pdo, $storeId);

        $batchQtyMap = isset($input['batch_qty']) ? $input['batch_qty'] : array();
        $labels = array();
        foreach ($batchRows as $row) {
            $labels[] = array(
                'barcode'       => $row['barcode'],
                'productName'   => $row['product_name'],
                'commonName'    => $row['common_name'],
                'series'        => $row['series'] ?? '',
                'batchNo'       => $row['batch_no'],
                'purchasedAt'   => $row['purchased_at'],
                'price'         => $row['suggested_price'],
                'conditionType' => $row['condition_type'],
                'qty'           => isset($batchQtyMap[$row['batch_id']]) ? intval($batchQtyMap[$row['batch_id']]) : intval($row['remaining_qty']),
            );
        }
    } else {
        $labels = $input['labels'];
        $condMap = CONDITION_TYPES;
    }
    $template = $input['template'];
    $printer = isset($input['printer']) ? $input['printer'] : '';

    $canvasWidth = floatval(isset($template['canvasWidth']) ? $template['canvasWidth'] : 60);
    $canvasHeight = floatval(isset($template['canvasHeight']) ? $template['canvasHeight'] : 40);
    $elements = isset($template['elements']) ? $template['elements'] : array();

    // 热敏打印机标准 203 DPI
    $dpi = 203;
    $pxPerMm = $dpi / 25.4;

    $imageWidth = (int)round($canvasWidth * $pxPerMm);
    $imageHeight = (int)round($canvasHeight * $pxPerMm);

    // 字体：优先项目内置 CJK 字体（fonts/ 目录，Windows/macOS/Linux 通用，常规+真粗体）。
    // 粗体一律用真粗体字库渲染（不用描边模拟）；内置缺失时按平台回退系统字体。
    $fontsDir = __DIR__ . '/../fonts';
    $fontPath = '';
    $fontBoldPath = '';
    $bundledRegular = $fontsDir . '/NotoSansSC-Regular.otf';
    $bundledBold = $fontsDir . '/NotoSansSC-Bold.otf';
    if (file_exists($bundledRegular)) { $fontPath = $bundledRegular; }
    if (file_exists($bundledBold)) { $fontBoldPath = $bundledBold; }

    if ($fontPath === '') {
        // 系统字体回退：中文字体在前，纯拉丁字体兜底（无法渲染中文，仅保证英文标签可用）
        $regularCandidates = array(
            'C:/Windows/Fonts/msyh.ttc',
            'C:/Windows/Fonts/simhei.ttf',
            '/System/Library/Fonts/PingFang.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/noto-cjk/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc',
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/HelveticaNeue.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        );
        foreach ($regularCandidates as $f) {
            if (file_exists($f)) { $fontPath = $f; break; }
        }
    }
    if ($fontBoldPath === '') {
        // 真粗体系统字体（Windows 微软雅黑 Bold / 黑体 / Noto Bold）；macOS 无独立粗体文件可用
        $boldCandidates = array(
            'C:/Windows/Fonts/msyhbd.ttc',
            'C:/Windows/Fonts/simhei.ttf',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc',
            '/usr/share/fonts/noto-cjk/NotoSansCJK-Bold.ttc',
        );
        foreach ($boldCandidates as $f) {
            if (file_exists($f)) { $fontBoldPath = $f; break; }
        }
    }

    $tempFiles = array();

    foreach ($labels as $item) {
        $qty = isset($item['qty']) ? intval($item['qty']) : 1;
        if ($qty < 1) $qty = 1;
        for ($i = 0; $i < $qty; $i++) {
            $img = imagecreatetruecolor($imageWidth, $imageHeight);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $white);

            foreach ($elements as $el) {
                $type = isset($el['type']) ? $el['type'] : '';
                $content = getElementContent($type, $item, $condMap);
                $ex = (int)round(floatval(isset($el['x']) ? $el['x'] : 0) * $pxPerMm);
                $ey = (int)round(floatval(isset($el['y']) ? $el['y'] : 0) * $pxPerMm);

                if ($type === 'barcode') {
                    $ew = (int)round(floatval(isset($el['width']) ? $el['width'] : 30) * $pxPerMm);
                    $eh = (int)round(floatval(isset($el['height']) ? $el['height'] : 10) * $pxPerMm);
                    renderBarcode($img, $content, $ex, $ey, $ew, $eh, $black, $white);
                } else {
                    $fontSizeMm = floatval(isset($el['fontSize']) ? $el['fontSize'] : 3);
                    $fontSizePx = $fontSizeMm * $pxPerMm;
                    $fontSizePx = min($fontSizePx, $imageHeight * 0.4);
                    $color = isset($el['color']) ? $el['color'] : '#000000';
                    $col = parseColor($img, $color, $black);

                    if ($fontPath) {
                        // 粗体与浏览器打印路径保持一致：name/price 恒为粗体，其余按元素 fontWeight；
                        // 只切真粗体字库文件渲染，不做描边/叠加模拟
                        $fw = strtolower(trim(isset($el['fontWeight']) ? $el['fontWeight'] : ''));
                        $isBold = ($type === 'name' || $type === 'price'
                            || $fw === 'bold' || $fw === 'bolder'
                            || (is_numeric($fw) && (int)$fw >= 600));
                        $useFont = ($isBold && $fontBoldPath !== '') ? $fontBoldPath : $fontPath;

                        // 超宽自动缩小字号（与前端 fitFontSize / canvas 打印一致）：
                        // 商品名称下限 50%，系列下限 60%；用实际字库测量，比例换算与测量尺度无关
                        $ewPx = floatval(isset($el['width']) ? $el['width'] : 50) * $pxPerMm;
                        // 字号=用户设置值（无隐藏折扣），与前端一致
                        if (($type === 'name' || $type === 'series') && $ewPx > 0) {
                            $minRatio = ($type === 'name') ? 0.5 : 0.6;
                            $probe = max(4, $fontSizePx * 72 / $dpi);
                            $bbox0 = imagettfbbox($probe, 0, $useFont, $content);
                            $textW = $bbox0[2] - $bbox0[0];
                            if ($textW > 0 && $textW > $ewPx) {
                                $fontSizePx = max($fontSizePx * $minRatio, $fontSizePx * $ewPx / $textW);
                            }
                        }

                        $ptSize = $fontSizePx * 72 / $dpi;
                        if ($ptSize < 4) $ptSize = 4;
                        $bbox = imagettfbbox($ptSize, 0, $useFont, $content);
                        // verticalAlign：文字在元素框内上下位置（top/middle/bottom），与前端三链一致
                        $ehPx = floatval(isset($el['height']) ? $el['height'] : 4) * $pxPerMm;
                        $textH = $bbox[1] - $bbox[7];
                        $va = isset($el['verticalAlign']) ? $el['verticalAlign'] : 'top';
                        $eyT = $ey;
                        if ($va === 'middle') $eyT = $ey + ($ehPx - $textH) / 2;
                        elseif ($va === 'bottom') $eyT = $ey + $ehPx - $textH;
                        $baseline = $eyT - $bbox[7];
                        $textW = $bbox[2] - $bbox[0];
                        if ($type === 'name' && $ewPx > 0 && $textW > $ewPx * 1.02) {
                            // 商品名称缩小到下限仍超宽：逐字符换行（行高 1.2，与浏览器 canvas 路径一致）
                            $lineH = $fontSizePx * 1.2;
                            $chars = preg_split('//u', $content, -1, PREG_SPLIT_NO_EMPTY);
                            if ($chars) {
                                $line = '';
                                $lineIdx = 0;
                                foreach ($chars as $ch) {
                                    $test = $line . $ch;
                                    $tb = imagettfbbox($ptSize, 0, $useFont, $test);
                                    if ($line !== '' && ($tb[2] - $tb[0]) > $ewPx) {
                                        imagettftext($img, $ptSize, 0, $ex, $baseline + $lineIdx * $lineH, $col, $useFont, $line);
                                        $line = $ch;
                                        $lineIdx++;
                                    } else {
                                        $line = $test;
                                    }
                                }
                                if ($line !== '') {
                                    imagettftext($img, $ptSize, 0, $ex, $baseline + $lineIdx * $lineH, $col, $useFont, $line);
                                }
                            }
                        } else {
                            imagettftext($img, $ptSize, 0, $ex, $baseline, $col, $useFont, $content);
                        }
                    } else {
                        $gdSize = max(1, min(5, (int)($fontSizePx / 8)));
                        imagestring($img, $gdSize, $ex, $ey, $content, $col);
                    }
                }
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'ppl_') . '.png';
            imagepng($img, $tempFile);
            imagedestroy($img);
            $tempFiles[] = $tempFile;
        }
    }

    // 打印
    if (empty($tempFiles)) {
        error('没有生成任何标签');
    }

    // 前端可传 proxy 覆盖（设置弹窗填写的代理地址优先），否则用服务端配置
    $proxyOverride = isset($input['proxy']) ? trim($input['proxy']) : '';
    $proxyUrl = $proxyOverride !== ''
        ? $proxyOverride
        : (defined('WINDOWS_PRINT_PROXY_URL') && WINDOWS_PRINT_PROXY_URL !== ''
            ? WINDOWS_PRINT_PROXY_URL
            : '');

    if ($proxyUrl !== '') {
        // ---- Windows 打印代理模式 ----
        $images = array();
        foreach ($tempFiles as $file) {
            $images[] = base64_encode(file_get_contents($file));
        }

        $payload = json_encode(array(
            'images'     => $images,
            'printer'    => $printer,
            'pageWidth'  => $canvasWidth,
            'pageHeight' => $canvasHeight,
        ));

        $ch = curl_init(rtrim($proxyUrl, '/') . '/print');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        foreach ($tempFiles as $file) {
            if (file_exists($file)) @unlink($file);
        }

        if ($curlErr) {
            throw new Exception('打印代理连接失败: ' . $curlErr);
        }
        if ($httpCode !== 200) {
            $body = json_decode($response, true);
            $msg = $body && isset($body['error']) ? $body['error'] : 'HTTP ' . $httpCode;
            throw new Exception('打印代理返回错误: ' . $msg);
        }

        success(array('message' => "已发送 " . count($images) . " 个标签到 Windows 打印机"));
    } else {
        // ---- 本地 CUPS (lp/lpr) 模式（需要命令执行函数） ----
        $disabled_fns = explode(',', str_replace(' ', '', ini_get('disable_functions')));
        $disabled_fns = array_flip($disabled_fns);
        $hasProcOpen = function_exists('proc_open') && !isset($disabled_fns['proc_open']);
        $hasShellExec = function_exists('shell_exec') && !isset($disabled_fns['shell_exec']);
        $hasExec = function_exists('exec') && !isset($disabled_fns['exec']);
        if (!$hasProcOpen && !$hasShellExec && !$hasExec) {
            throw new Exception('服务器禁用了所有命令执行函数（exec/proc_open/shell_exec），且未配置打印代理地址，无法本地打印');
        }
        $printed = 0;
        foreach ($tempFiles as $file) {
            $err = '';
            $ok = false;

            // 尝试 lp 命令
            $cmd = 'lp';
            if ($printer !== '') {
                $cmd .= ' -d ' . escapeshellarg($printer);
            }
            $cmd .= ' ' . escapeshellarg($file);
            $output = array();
            $returnCode = -1;
            runCommand($cmd, $output, $returnCode);
            if ($returnCode === 0) {
                $ok = true;
            } else {
                $err = implode("\n", $output);
                // lp 失败，尝试 lpr
                $cmd2 = 'lpr';
                if ($printer !== '') {
                    $cmd2 .= ' -P ' . escapeshellarg($printer);
                }
                $cmd2 .= ' ' . escapeshellarg($file);
                $output2 = array();
                $returnCode2 = -1;
                runCommand($cmd2, $output2, $returnCode2);
                if ($returnCode2 === 0) {
                    $ok = true;
                } else {
                    $err .= "\n" . implode("\n", $output2);
                }
            }

            if (!$ok) {
                throw new Exception('打印失败，请确认服务器已安装 CUPS（sudo apt install cups），并配置好打印机。错误: ' . $err);
            }
            $printed++;
        }

        // 清理
        foreach ($tempFiles as $file) {
            if (file_exists($file)) @unlink($file);
        }

        success(array('message' => "已发送 {$printed} 个标签到打印机"));
    }

} catch (Exception $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}

// ---------- 辅助函数 ----------

function getElementContent($type, $item, $condMap = null) {
    switch ($type) {
        case 'barcode':
        case 'barcodeText':
            return isset($item['barcode']) ? $item['barcode'] : '';
        case 'name':
            return isset($item['productName']) ? $item['productName'] : '';
        case 'common':
            return isset($item['commonName']) ? $item['commonName'] : '';
        case 'series':
            return isset($item['series']) ? $item['series'] : '';
        case 'batch':
            return isset($item['batchNo']) ? $item['batchNo'] : '';
        case 'date':
            $t = isset($item['purchasedAt']) ? $item['purchasedAt'] : '';
            return $t !== '' ? substr($t, 0, 10) : '';
        case 'condition':
            $ct = isset($item['conditionType']) ? $item['conditionType'] : '';
            // 品相来源与前端一致：优先店铺配置（condition_common.php），回退默认常量
            $map = (is_array($condMap) && $condMap) ? $condMap : CONDITION_TYPES;
            $parts = array();
            foreach ($map as $key => $name) {
                $parts[] = ($key === $ct ? '☑' : '□') . ' ' . $name;
            }
            return implode('  ', $parts);
        case 'price':
            $price = floatval(isset($item['price']) ? $item['price'] : 0);
            return '¥' . number_format($price, 2);
        default:
            return isset($item[$type]) ? $item[$type] : '';
    }
}

function parseColor($img, $hex, $default) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 && strlen($hex) !== 3) return $default;
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return imagecolorallocate($img, $r, $g, $b);
}

function renderBarcode($img, $barcode, $x, $y, $width, $height, $black, $white) {
    $barcode = trim($barcode);
    if ($barcode === '') return;
    if (preg_match('/^\d{8}$/', $barcode)) {
        renderEAN8($img, $barcode, $x, $y, $width, $height, $black, $white);
    } elseif (preg_match('/^\d{12,13}$/', $barcode)) {
        if (strlen($barcode) === 12) {
            $check = calculateEAN13CheckDigit($barcode);
            $barcode = $barcode . $check;
        }
        renderEAN13($img, $barcode, $x, $y, $width, $height, $black, $white);
    } else {
        renderCode128($img, $barcode, $x, $y, $width, $height, $black, $white);
    }
}

// ========== EAN-13 ==========

function renderEAN13($img, $barcode, $x, $y, $width, $height, $black, $white) {
    $L = array('0001101','0011001','0010011','0111101','0100011',
               '0110001','0101111','0111011','0110111','0001011');
    $G = array('0100111','0110011','0011011','0100001','0011101',
               '0111001','0000101','0010001','0001001','0010111');
    $R = array('1110010','1100110','1101100','1000010','1011100',
               '1001110','1010000','1000100','1001000','1110100');
    $parity = array('LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG',
                    'LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGLLLG');
    $first = (int)$barcode[0];
    $p = $parity[$first];
    $enc = '101';
    for ($i = 1; $i <= 6; $i++) {
        $d = (int)$barcode[$i];
        $enc .= ($p[$i - 1] === 'L') ? $L[$d] : $G[$d];
    }
    $enc .= '01010';
    for ($i = 7; $i <= 12; $i++) {
        $enc .= $R[(int)$barcode[$i]];
    }
    $enc .= '101';
    drawBars($img, $enc, $x, $y, $width, $height, $black);
}

// ========== EAN-8 ==========

function renderEAN8($img, $barcode, $x, $y, $width, $height, $black, $white) {
    $Lpar = array('0001101','0011001','0010011','0111101','0100011',
                  '0110001','0101111','0111011','0110111','0001011');
    $Rpar = array('1110010','1100110','1101100','1000010','1011100',
                  '1001110','1010000','1000100','1001000','1110100');
    $enc = '101';
    for ($i = 0; $i < 4; $i++) {
        $enc .= $Lpar[(int)$barcode[$i]];
    }
    $enc .= '01010';
    for ($i = 4; $i < 8; $i++) {
        $enc .= $Rpar[(int)$barcode[$i]];
    }
    $enc .= '101';
    drawBars($img, $enc, $x, $y, $width, $height, $black);
}

// ========== CODE128 ==========

function renderCode128($img, $barcode, $x, $y, $width, $height, $black, $white) {
    $P = array(
        0=>'11011001100',1=>'11001101100',2=>'11001100110',3=>'10010011000',4=>'10010001100',
        5=>'10001001100',6=>'10011001000',7=>'10011000100',8=>'10001100100',9=>'11001001000',
        10=>'11001000100',11=>'11000100100',12=>'10110011100',13=>'10011011100',14=>'10011001110',
        15=>'10111001100',16=>'10011101100',17=>'10011100110',18=>'11001110010',19=>'11001011100',
        20=>'11001001110',21=>'11011100100',22=>'11001110100',23=>'11101101110',24=>'11101001100',
        25=>'11100101100',26=>'11100100110',27=>'11101100100',28=>'11100110100',29=>'11100110010',
        30=>'11011011000',31=>'11011000110',32=>'11000110110',33=>'10100011000',34=>'10001011000',
        35=>'10001000110',36=>'10110001000',37=>'10001101000',38=>'10001100010',39=>'11010001000',
        40=>'11000101000',41=>'11000100010',42=>'10110111000',43=>'10110001110',44=>'10001101110',
        45=>'10111011000',46=>'10111000110',47=>'10001110110',48=>'11101110110',49=>'11010001110',
        50=>'11000101110',51=>'11011101000',52=>'11011100010',53=>'11011101110',54=>'11101011000',
        55=>'11101000110',56=>'11100010110',57=>'11101101000',58=>'11101100010',59=>'11100011010',
        60=>'11101111010',61=>'11001000010',62=>'11110001010',63=>'10100110000',64=>'10100001100',
        65=>'10010110000',66=>'10010000110',67=>'10000101100',68=>'10000100110',69=>'10110010000',
        70=>'10110000100',71=>'10011010000',72=>'10011000010',73=>'10000110100',74=>'10000110010',
        75=>'11000010010',76=>'11001010000',77=>'11110111010',78=>'11000010100',79=>'10001111010',
        80=>'10100111100',81=>'10010111100',82=>'10010011110',83=>'10111100100',84=>'10011110100',
        85=>'10011110010',86=>'11110100100',87=>'11110010100',88=>'11110010010',89=>'11011011110',
        90=>'11011110110',91=>'11110110110',92=>'10101111000',93=>'10100011110',94=>'10001011110',
        95=>'10111101000',96=>'10111100010',97=>'10001111010',98=>'10111011110',99=>'10111101110',
        100=>'11101011010',101=>'11101011010',102=>'11010101000',
        103=>'11010100110',104=>'11010010000',105=>'11010001000',
    );
    $STOP = '1100011101011';

    $enc = $P[104];
    $checksum = 104;
    $len = strlen($barcode);
    for ($i = 0; $i < $len; $i++) {
        $char = $barcode[$i];
        $ord = ord($char);
        $val = ($ord >= 32 && $ord <= 126) ? ($ord - 32) : 0;
        $enc .= $P[$val];
        $checksum += $val * ($i + 1);
    }
    $checksum %= 103;
    $enc .= $P[$checksum];
    $enc .= $STOP;
    drawBars($img, $enc, $x, $y, $width, $height, $black);
}

// ========== 通用绘制 ==========

function drawBars($img, $enc, $x, $y, $width, $height, $black) {
    $modules = strlen($enc);
    if ($modules === 0) return;
    for ($i = 0; $i < $modules; $i++) {
        if ($enc[$i] === '1') {
            $x1 = (int)round($x + ($i / $modules) * $width);
            $x2 = (int)round($x + (($i + 1) / $modules) * $width) - 1;
            if ($x2 >= $x1) {
                imagefilledrectangle($img, $x1, $y, $x2, $y + $height - 1, $black);
            }
        }
    }
}

function runCommand($cmd, &$output, &$returnCode) {
    global $hasProcOpen, $hasShellExec, $hasExec;
    $output = array();
    $returnCode = -1;

    if ($hasProcOpen) {
        $proc = proc_open($cmd, array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        ), $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $returnCode = proc_close($proc);
            if ($stdout !== '') $output[] = $stdout;
            if ($stderr !== '') $output[] = $stderr;
            return;
        }
    }

    if ($hasShellExec) {
        $result = shell_exec($cmd);
        $returnCode = 0;
        if ($result !== null) $output[] = $result;
        return;
    }

    if ($hasExec) {
        exec($cmd, $output, $returnCode);
        return;
    }
}
