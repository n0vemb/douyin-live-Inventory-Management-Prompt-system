<?php
/**
 * label_template_table.php — label_templates 表结构统一入口
 * 各模板 CRUD API 统一 require 本文件调用 ensureLabelTemplatesTable()，禁止各自写建表语句。
 *
 * 幂等保证：
 * 1. 新建表直接带 store_id 列 + (store_id, name) 复合唯一键（多租户自洽，
 *    避免旧版全局 unique_name(name) 导致不同店铺同名模板互相覆盖）；
 * 2. 已存在的早期表（API 自动建表版本，缺 store_id 列）自动补列，
 *    否则 INSERT/SELECT/DELETE 带 store_id 会报 Unknown column 'store_id'。
 *
 * 注：老表的 UNIQUE KEY unique_name(name) 不会在此删除（涉及存量数据，
 * 跨店同名覆盖问题建议手工迁移：ALTER TABLE label_templates DROP INDEX unique_name,
 * ADD UNIQUE KEY uk_store_name (store_id, name);）
 */

/**
 * 确保 label_templates 表存在且结构可用（幂等，可每次请求调用）
 * @param PDO $pdo
 */
function ensureLabelTemplatesTable($pdo)
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS label_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL DEFAULT 1,
            name VARCHAR(255) NOT NULL,
            config TEXT NOT NULL COMMENT 'JSON格式的模板配置',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_store_name (store_id, name),
            KEY idx_label_templates_store_id (store_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 老表补列：早期版本 API 自动建的表没有 store_id（v3 多租户迁移也可能未覆盖该表）
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'label_templates' AND COLUMN_NAME = 'store_id'
    ");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE label_templates
            ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
            ADD INDEX idx_label_templates_store_id (store_id)");
    }
}
