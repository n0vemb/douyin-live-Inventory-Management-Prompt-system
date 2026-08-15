-- 福袋记录表 v9
CREATE TABLE IF NOT EXISTS `live_ledger_lucky_draw` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`  INT UNSIGNED NOT NULL,
  `winner`      VARCHAR(100) NOT NULL COMMENT '中奖人',
  `prize`       VARCHAR(255) NOT NULL COMMENT '奖品',
  `cost`        DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT '成本',
  `created_at`  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `fk_lucky_session` FOREIGN KEY (`session_id`) REFERENCES `live_ledger_session`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
