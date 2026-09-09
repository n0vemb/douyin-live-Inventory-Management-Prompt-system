-- ============================================================
-- PPMart 收银台按店 + 8位数字码 数据库迁移（v21 草案）
-- ============================================================
-- 背景：集团下有几个店就有几个线下收银台入口；
--   收银台从「集团长 token(?t=)」改为「店级 8 位数字码(?c=)，可重置」。
--   优惠券活动/领取/核销按店；VIP 客户仍归属集团（store_id 级，不做 shop 拆分）。
--
-- ⚠ 本文件未在测试库执行过，执行前先备份并按既有流程验证。
--   先跑 v20（shops 表），再跑本文件。
-- ============================================================

-- ------------------------------------------------------------
-- 1. shops.pos_code：店级收银台 8 位数字码（可重置，平台内唯一）
--    兼容旧链接：stores.pos_token 保留，t= 仍可进该集团“默认店”。
-- ------------------------------------------------------------
ALTER TABLE `shops`
    ADD COLUMN `pos_code` VARCHAR(8) DEFAULT NULL COMMENT '收银台8位数字码（可重置，平台内唯一）' AFTER `remark`,
    ADD UNIQUE KEY `uk_shops_pos_code` (`pos_code`),
    ADD KEY `idx_shops_pos_code_lookup` (`pos_code`);

-- 旧数据回填由应用层生成（ensureShopPosCode），SQL 无法安全生成随机唯一码。

-- ------------------------------------------------------------
-- 2. POS 订单按店
-- ------------------------------------------------------------
ALTER TABLE `pos_orders`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '收银台所属店（下单时盖章）' AFTER `store_id`,
    ADD INDEX `idx_pos_orders_shop` (`shop_id`);

ALTER TABLE `pos_order_items`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '冗余店标，随订单' AFTER `store_id`,
    ADD INDEX `idx_pos_order_items_shop` (`shop_id`);

-- 历史 POS 订单回填：归入本集团“默认店”
UPDATE `pos_orders` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `pos_order_items` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

-- ------------------------------------------------------------
-- 3. 优惠券按店
-- ------------------------------------------------------------
ALTER TABLE `coupon_campaigns`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店；NULL=集团通用活动' AFTER `store_id`,
    ADD INDEX `idx_campaign_store_shop` (`store_id`, `shop_id`, `status`);

ALTER TABLE `coupon_claims`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '冗余店标（从活动带出）' AFTER `store_id`,
    ADD INDEX `idx_claim_store_shop_phone` (`store_id`, `shop_id`, `phone`, `status`);

ALTER TABLE `pos_order_coupons`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '冗余店标（随核销订单）' AFTER `store_id`,
    ADD INDEX `idx_oc_store_shop` (`store_id`, `shop_id`);

-- 历史券数据回填：按 campaign 归属店；无 campaign 的按默认店
UPDATE `coupon_claims` t
JOIN `coupon_campaigns` c ON c.`id` = t.`campaign_id`
JOIN `shops` d ON d.`store_id` = c.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `coupon_campaigns` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `pos_order_coupons` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

-- ------------------------------------------------------------
-- 4. 店级 POS 设置（每店独立：收款码/比例/屏保/店员密码等）
--    新店默认从集团设置复制；老默认店从 stores 同步一次
-- ------------------------------------------------------------
ALTER TABLE `shops`
    ADD COLUMN `offline_price_ratio` DECIMAL(6,2) DEFAULT NULL COMMENT '线下加价比例；NULL=沿用集团设置' AFTER `pos_code`,
    ADD COLUMN `offline_staff_pwd` VARCHAR(64) DEFAULT NULL COMMENT '店员模式密码(hash)；NULL=未设置' AFTER `offline_price_ratio`,
    ADD COLUMN `offline_pay_qr_wx` VARCHAR(255) DEFAULT NULL AFTER `offline_staff_pwd`,
    ADD COLUMN `offline_pay_qr_ali` VARCHAR(255) DEFAULT NULL AFTER `offline_pay_qr_wx`,
    ADD COLUMN `pos_enabled` TINYINT(1) DEFAULT 1 AFTER `offline_pay_qr_ali`,
    ADD COLUMN `pos_screensaver_img` VARCHAR(255) DEFAULT NULL AFTER `pos_enabled`,
    ADD COLUMN `pos_screensaver_sec` INT DEFAULT 30 AFTER `pos_screensaver_img`,
    ADD COLUMN `pos_hide_price` TINYINT(1) DEFAULT 0 AFTER `pos_screensaver_sec`;

UPDATE `shops` sh
JOIN `stores` s ON s.`id` = sh.`store_id` AND sh.`name` = '默认店'
SET sh.`offline_price_ratio`   = s.`offline_price_ratio`,
    sh.`offline_staff_pwd`     = s.`offline_staff_pwd`,
    sh.`offline_pay_qr_wx`     = s.`offline_pay_qr_wx`,
    sh.`offline_pay_qr_ali`    = s.`offline_pay_qr_ali`,
    sh.`pos_enabled`           = s.`pos_enabled`,
    sh.`pos_screensaver_img`   = s.`pos_screensaver_img`,
    sh.`pos_screensaver_sec`   = s.`pos_screensaver_sec`,
    sh.`pos_hide_price`        = s.`pos_hide_price`
WHERE sh.`offline_price_ratio` IS NULL
  OR sh.`offline_staff_pwd` IS NULL
  OR sh.`offline_pay_qr_wx` IS NULL;

-- ------------------------------------------------------------
-- 5. SKU 级线下售价按店（product_offline_prices）
-- ------------------------------------------------------------
ALTER TABLE `product_offline_prices`
    ADD COLUMN `store_id` INT DEFAULT NULL COMMENT '所属集团' AFTER `id`,
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店；每店可不同定价' AFTER `store_id`;

UPDATE `product_offline_prices` t
JOIN `products` p ON p.`id` = t.`product_id`
SET t.`store_id` = p.`store_id`
WHERE t.`store_id` IS NULL;

UPDATE `product_offline_prices` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

ALTER TABLE `product_offline_prices`
    DROP INDEX `uk_product_cond`,
    ADD UNIQUE KEY `uk_store_shop_product_cond` (`store_id`, `shop_id`, `product_id`, `condition_type`);

-- ------------------------------------------------------------
-- 6. 代码侧待办（不在本脚本内）
-- ------------------------------------------------------------
-- a) config/auth：generateShopPosCode() + ensureShopPosCode()
--    create_shop / register / 重置接口 自动赋码；查询前惰性补齐
-- b) api/pos_auth.php：支持 ?c=8位数字码 → shops.pos_code 定位店，
--    会话写 pos_shop_id；旧 ?t= 兼容进默认店
-- c) api/pos_checkout.php / pos_outbound / pos 报表：订单盖章 shop_id，按店过滤
-- d) 券 API：活动创建必选店；领取/核销按 campaign.shop_id 收口
-- e) admin/shops.php：展示/复制店收银台链接 + 8 位码 + 重置按钮
-- f) admin/settings.php 旧收银台入口保留（集团/默认店），新增店入口在店管理里
-- g) VIP 维持 store_id（集团）不变：集团内 VIP 唯一，两店共享客户档案
