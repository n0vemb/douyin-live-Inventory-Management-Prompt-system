<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('请使用POST方法');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) && !isset($input['name'])) {
    error('请提供模板ID或名称');
}

try {
    $pdo = getDB();
requireAuth(); $storeId = getStoreId();

    if (isset($input['id'])) {
        $stmt = $pdo->prepare('DELETE FROM label_templates WHERE id = ?' . ($storeId ? ' AND store_id = ?' : ''));
        $stmt->execute($storeId ? [$input['id'], $storeId] : [$input['id']]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM label_templates WHERE name = ?' . ($storeId ? ' AND store_id = ?' : ''));
        $stmt->execute($storeId ? [trim($input['name']), $storeId] : [trim($input['name'])]);
    }

    if ($stmt->rowCount() === 0) {
        error('未找到要删除的模板');
    }

    success(['message' => '模板删除成功']);
} catch (Exception $e) {
    error($e->getMessage());
}
