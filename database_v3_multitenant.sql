-- ============================================================
-- PPMart 多租户用户/店铺系统 数据库迁移
-- Version: 3.0 (Multi-Tenant)
-- =============================================

-- 1. 创建 stores 表（店铺）
CREATE TABLE IF NOT EXISTS stores (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL COMMENT '店铺名称',
    barcode_prefix  VARCHAR(8) NOT NULL COMMENT '条码前缀（EAN-13前8位），注册时自动生成',
    system_name     VARCHAR(255) DEFAULT NULL COMMENT '店铺级系统名称',
    logo_path       VARCHAR(500) DEFAULT NULL COMMENT '店铺级Logo',
    condition_types TEXT DEFAULT NULL COMMENT 'SKU配置 JSON',
    live_display    TEXT DEFAULT NULL COMMENT '直播页面布局配置 JSON',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_barcode_prefix (barcode_prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 创建 users 表（用户）
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(255) DEFAULT NULL COMMENT '显示名',
    role          ENUM('super_admin','store_admin') NOT NULL DEFAULT 'store_admin',
    store_id      INT DEFAULT NULL COMMENT '所属店铺ID，超管为NULL',
    is_active     TINYINT(1) DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username),
    KEY idx_store_id (store_id),
    CONSTRAINT fk_user_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 插入默认店铺（ID=1，对应现有数据）
INSERT INTO stores (id, name, barcode_prefix)
SELECT 1, '默认店铺', '69414486'
WHERE NOT EXISTS (SELECT 1 FROM stores WHERE id = 1);

-- 复制现有配置到默认店铺
UPDATE stores s
JOIN (
    SELECT
        MAX(CASE WHEN setting_key = 'system_name' THEN setting_value END) AS system_name,
        MAX(CASE WHEN setting_key = 'logo_path' THEN setting_value END) AS logo_path,
        MAX(CASE WHEN setting_key = 'condition_types' THEN setting_value END) AS condition_types,
        MAX(CASE WHEN setting_key = 'live_display' THEN setting_value END) AS live_display
    FROM system_settings
) t ON 1=1
SET
    s.system_name     = t.system_name,
    s.logo_path       = t.logo_path,
    s.condition_types = t.condition_types,
    s.live_display    = t.live_display
WHERE s.id = 1;

-- 4. 所有数据表加 store_id 列
ALTER TABLE products          ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_products_store_id (store_id),
                              ADD CONSTRAINT fk_products_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE inventory_batches ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_inventory_batches_store_id (store_id),
                              ADD CONSTRAINT fk_inventory_batches_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE sales_log         ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_sales_log_store_id (store_id),
                              ADD CONSTRAINT fk_sales_log_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE outbound_log      ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_outbound_log_store_id (store_id),
                              ADD CONSTRAINT fk_outbound_log_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE purchase_log      ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_purchase_log_store_id (store_id),
                              ADD CONSTRAINT fk_purchase_log_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE inventory_log     ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_inventory_log_store_id (store_id),
                              ADD CONSTRAINT fk_inventory_log_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE live_inventory    ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_live_inventory_store_id (store_id),
                              ADD CONSTRAINT fk_live_inventory_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE live_sessions     ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_live_sessions_store_id (store_id),
                              ADD CONSTRAINT fk_live_sessions_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE broadcast_messages ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_broadcast_messages_store_id (store_id),
                              ADD CONSTRAINT fk_broadcast_messages_store FOREIGN KEY (store_id) REFERENCES stores(id);

ALTER TABLE label_templates   ADD COLUMN store_id INT NOT NULL DEFAULT 1 AFTER id,
                              ADD INDEX idx_label_templates_store_id (store_id),
                              ADD CONSTRAINT fk_label_templates_store FOREIGN KEY (store_id) REFERENCES stores(id);

-- 5. system_settings 表改造
ALTER TABLE system_settings ADD COLUMN store_id INT DEFAULT NULL AFTER id,
                            ADD INDEX idx_syssettings_store_id (store_id);

-- 删除旧的 setting_key UNIQUE KEY
ALTER TABLE system_settings DROP INDEX setting_key;

-- 添加复合 UNIQUE
ALTER TABLE system_settings ADD UNIQUE KEY uk_store_setting (store_id, setting_key);

-- 平台级设置保留 store_id=NULL（不归入任何店铺）

-- 6. 插入默认用户
-- 密码: admin123 (super_admin) / store123 (store_admin)
INSERT INTO users (username, password_hash, display_name, role, store_id, is_active)
SELECT 'admin', '$2y$12$UxdOflRvlfphkUj2a7sNx.QgDFH1btC1XBsbhjvXcrifzP0iYK4w2', '系统管理员', 'super_admin', NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

INSERT INTO users (username, password_hash, display_name, role, store_id, is_active)
SELECT 'store1', '$2y$12$5ZLp9lu.CSLDKJRgiZdA2uWgmb18FJuBZEJbw5jg.9T2ed8RRWC0y', '默认店铺管理员', 'store_admin', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'store1');
