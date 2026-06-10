-- v5: 财务管理模块
-- 新增 outbound_finance 表 + stores 财务设置列

CREATE TABLE IF NOT EXISTS outbound_finance (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    store_id          INT NOT NULL,
    outbound_batch_no VARCHAR(14) NOT NULL COMMENT '出库批次号 YmdHis',
    gmv               DECIMAL(12,2) DEFAULT NULL COMMENT '成交金额(GMV)，平台实际收入含运费',
    order_count       INT DEFAULT NULL COMMENT '订单数/快递单数',
    ad_spend          DECIMAL(12,2) DEFAULT NULL COMMENT '投入流量费用',
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_batch_store (outbound_batch_no, store_id),
    KEY idx_store_id (store_id),
    KEY idx_outbound_batch_no (outbound_batch_no),
    CONSTRAINT fk_outbound_finance_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE stores
    ADD COLUMN shipping_fee DECIMAL(8,2) NOT NULL DEFAULT 3.00 COMMENT '每单快递费(元)',
    ADD COLUMN platform_fee_rate DECIMAL(5,4) NOT NULL DEFAULT 0.0500 COMMENT '平台抽成比例';

INSERT INTO system_settings (store_id, setting_key, setting_value)
VALUES (NULL, 'default_shipping_fee', '3.00'),
       (NULL, 'default_platform_fee_rate', '0.05')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
