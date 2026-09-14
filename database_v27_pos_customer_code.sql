-- ============================================================
-- PPMart v27：顾客自助码与店内收银台码分离
-- Version: 27.0 (POS Customer Code)
--
-- 背景：顾客自助链接以前靠 ?as=customer 区分身份，
--   顾客把 &as=customer 删掉就能拿到带「人工确认已收款」的店员界面。
--   改为两个 8 位数字码：
--     shops.pos_code          = 店内收银台码（店员设备，有店员能力）
--     shops.pos_customer_code = 顾客自助码（只能下单/付款/抽奖，可放心发顾客）
--   身份由「进门用的哪个码」在服务端决定，链接参数改不了。
--
-- 1) shops：新增 pos_customer_code（平台内唯一，与 pos_code 共用数字空间）
--    旧数据回填由应用层生成（ensureShopCustomerCode），SQL 无法安全生成随机唯一码。
--
-- ⚠ 执行前备份。
-- 执行记录：测试库(ppmart2_test)按发布流程执行；
--   生产库(ppmart2)等上线通知后再执行。重复执行会因「列已存在」报错，属预期。
-- ============================================================

ALTER TABLE `shops`
    ADD COLUMN `pos_customer_code` VARCHAR(8) DEFAULT NULL COMMENT '顾客自助8位码（可重置，只有自助能力）' AFTER `pos_code`,
    ADD UNIQUE KEY `uk_shops_pos_customer_code` (`pos_customer_code`),
    ADD KEY `idx_shops_pos_customer_code_lookup` (`pos_customer_code`);
