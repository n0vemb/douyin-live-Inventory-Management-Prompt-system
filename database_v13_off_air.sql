-- ============================================================
-- PPMart 直播记账：下播时间（用于计算播出时长）
-- Version: 13.0 (Off Air)
-- 下播 = 直播结束但商品未打包出库；status 仍为 active（已录商品继续占用库存），
-- 打包出库(live_ledger_end)时才置 ended；结束时若未下播则回填 off_air_at=NOW()
-- 播出时长 = off_air_at - created_at
-- ============================================================

ALTER TABLE `live_ledger_session`
    ADD COLUMN `off_air_at` DATETIME DEFAULT NULL COMMENT '下播时间（计算播出时长）' AFTER `created_at`;
