<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$response = ['success' => false, 'error' => '', 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['error'] = '请使用POST方法';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $response['error'] = '请选择图片文件';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制(php.ini)',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE => '没有文件上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时目录',
            UPLOAD_ERR_CANT_WRITE => '磁盘写入失败',
            UPLOAD_ERR_EXTENSION => 'PHP扩展阻止了上传'
        ];
        $response['error'] = $errors[$file['error']] ?? '上传错误代码: ' . $file['error'];
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    // finfo 可能不可用，优先使用 mime_content_type 做备选
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    }
    if (empty($mime) || !in_array($mime, $allowedTypes)) {
        // 降级：通过扩展名判断
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
        $mime = $extMap[$ext] ?? '';
        if (empty($mime)) {
            $response['error'] = '文件类型不支持';
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $fileSize = $file['size'];
    if ($fileSize > 10 * 1024 * 1024) {
        $response['error'] = '图片大小不能超过10MB';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadDir = __DIR__ . '/../uploads/';
    $subDir = '';

    // 支持按系列分目录存储
    $series = isset($_POST['series']) ? trim($_POST['series']) : '';
    if ($series !== '') {
        $subDir = sanitizeSeriesDir($series) . '/';
        $uploadDir .= $subDir;
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];
    $ext = $extMap[$mime] ?? 'jpg';

    $fileName = 'img_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        chmod($filePath, 0644);
        $response['success'] = true;
        $response['data'] = [
            'url' => 'uploads/' . $subDir . $fileName,
            'name' => $fileName
        ];
    } else {
        $response['error'] = '文件保存失败，请检查目录权限';
    }

} catch (Exception $e) {
    $response['error'] = '系统错误: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);