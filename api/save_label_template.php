<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/label_template_table.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('请使用POST方法');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['name']) || empty(trim($input['name']))) {
    error('请提供模板名称');
}

if (!isset($input['config']) || !is_array($input['config'])) {
    error('请提供有效的模板配置');
}

try {
    $pdo = getDB();
requireAuth(); $storeId = getStoreId();

    // 确保表存在且带 store_id 列（幂等）
    ensureLabelTemplatesTable($pdo);

    $name = trim($input['name']);
    $config = json_encode([
        'canvasWidth' => $input['config']['canvasWidth'] ?? 60,
        'canvasHeight' => $input['config']['canvasHeight'] ?? 40,
        'paperType' => $input['config']['paperType'] ?? 'continuous',
        'density' => $input['config']['density'] ?? 'normal',
        'elements' => $input['config']['elements'] ?? []
    ], JSON_UNESCAPED_UNICODE);

    // 先按 (name, store_id) 更新本店记录；没有则插入。
    // 不用 INSERT ... ON DUPLICATE KEY UPDATE：老表的全局 unique_name(name)
    // 会命中别店同名模板并误改其配置，这里按店铺定位避免跨店串改。
    // 注意：不能依赖 UPDATE 的 rowCount 判断存在性（MySQL 值未变化时 affected=0）。
    $stmt = $pdo->prepare('SELECT id FROM label_templates WHERE name = ?' . ($storeId ? ' AND store_id = ?' : ''));
    $stmt->execute($storeId ? [$name, $storeId] : [$name]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($exists) {
        $stmt = $pdo->prepare('UPDATE label_templates SET config = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$config, $exists['id']]);
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO label_templates (name, config, store_id) VALUES (?, ?, ?)');
            $stmt->execute([$name, $config, $storeId]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errno = (int)($e->errorInfo[1] ?? 0);
                // 1062=唯一键冲突（名称重复）；1048=NOT NULL 约束（超管无店铺，列不允许 NULL）
                if ($errno === 1062) {
                    error('模板名称「' . $name . '」已被占用，请换一个名称');
                }
                if ($errno === 1048) {
                    error('当前账号未绑定店铺，无法保存模板；请使用店铺管理员账号操作');
                }
            }
            throw $e;
        }
    }

    // 获取刚插入或更新的记录
    $stmt = $pdo->prepare('SELECT id, name, config, created_at, updated_at FROM label_templates WHERE name = ?' . ($storeId ? ' AND store_id = ?' : ''));
    $stmt->execute($storeId ? [$name, $storeId] : [$name]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    $configDecoded = json_decode($template['config'], true);
    $result = [
        'id' => $template['id'],
        'name' => $template['name'],
        'canvasWidth' => $configDecoded['canvasWidth'] ?? 60,
        'canvasHeight' => $configDecoded['canvasHeight'] ?? 40,
        'paperType' => $configDecoded['paperType'] ?? 'continuous',
        'density' => $configDecoded['density'] ?? 'normal',
        'elements' => $configDecoded['elements'] ?? [],
        'createdAt' => $template['created_at'],
        'updatedAt' => $template['updated_at']
    ];

    success(['template' => $result, 'message' => '模板保存成功']);
} catch (Exception $e) {
    error($e->getMessage());
}
