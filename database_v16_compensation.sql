-- ============================================================
-- PPMart 直播场次：退货/撤单运费补偿
-- Version: 16.0 (Refund Shipping Compensation)
-- 独立记录：撤单/退货时可选填自定义补偿金额，客户删除后记录仍保留
-- ============================================================

CREATE TABLE IF NOT EXISTS `live_ledger_compensation` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `session_id`  INT NOT NULL,
    `customer_id` INT DEFAULT NULL COMMENT '原客户（可能已随撤单删除）',
    `customer_label` VARCHAR(100) DEFAULT NULL COMMENT '客户昵称/VIP 快照',
    `amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `remark`      VARCHAR(255) DEFAULT NULL,
    `operator_username` VARCHAR(100) DEFAULT NULL,
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_comp_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='直播撤单/退货运费补偿';
