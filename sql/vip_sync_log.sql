-- vip_sync_log — 外部系统 VIP 同步审计日志
-- 每次推送记录一条：新增(inserted)/昵称更新(updated)/已存在跳过(skipped)/字段非法(invalid)
CREATE TABLE IF NOT EXISTS `vip_sync_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vip_no` varchar(50) NOT NULL DEFAULT '',
  `nickname` varchar(100) NOT NULL DEFAULT '',
  `result` enum('inserted','updated','skipped','invalid') NOT NULL DEFAULT 'invalid',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vip_no` (`vip_no`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
