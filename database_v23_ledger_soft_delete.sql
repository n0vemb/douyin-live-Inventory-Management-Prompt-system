-- ============================================================
-- PPMart v23：直播记账 软删除（已下播/已打包出库后删除保留记录）
-- ============================================================
-- 规则：
--   直播中(active 且未下播)：删除=硬删（现状）
--   已下播未打包出库(off_air_at 非空)：删除=软删，DOM 灰显“已删除”，记录保留
--   已打包出库(status=ended)：删除=软删，DOM 灰显“已删除”，记录保留
-- ⚠ 执行前备份；软删记录不参与打包出库/报表统计，但可在场次历史中查询。

ALTER TABLE `live_ledger_customer`
    ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=已删除(软删,保留记录)' AFTER `sort_order`,
    ADD INDEX `idx_llc_deleted` (`session_id`, `is_deleted`);

ALTER TABLE `live_ledger_item`
    ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=已删除(软删,保留记录)' AFTER `is_temp`,
    ADD INDEX `idx_lli_deleted` (`customer_id`, `is_deleted`);

ALTER TABLE `live_ledger_gift`
    ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=已删除(软删,保留记录)' AFTER `description`,
    ADD INDEX `idx_llg_deleted` (`customer_id`, `is_deleted`);
