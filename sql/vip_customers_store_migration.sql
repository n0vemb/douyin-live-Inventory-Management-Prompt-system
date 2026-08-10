-- ============================================================
-- vip_customers 按店铺隔离迁移（2026-08-10）
-- 1. vip_customers 加 store_id（存量归 store_id=1 Mi姐店）
-- 2. UNIQUE (vip_no) → UNIQUE (store_id, vip_no)
-- 3. stores 加 vip_sync_token（每店铺独立外部同步 token）
-- ============================================================

-- 1. 备份
-- CREATE TABLE vip_customers_bak_20260810 AS SELECT * FROM vip_customers;
-- CREATE TABLE stores_bak_20260810 AS SELECT * FROM stores;

-- 2. vip_customers 加 store_id（先允许 NULL，存量回填后再改 NOT NULL）
ALTER TABLE `vip_customers` ADD COLUMN `store_id` int(10) DEFAULT NULL AFTER `id`;

-- 3. 存量客户归 store_id=1（Mi姐店）
UPDATE `vip_customers` SET `store_id` = 1 WHERE `store_id` IS NULL;

-- 4. 删旧唯一键，建 (store_id, vip_no) 唯一键（先删重复行防建键失败）
--    （若存在重复 vip_no 需先清理，生产执行前先查 SELECT vip_no, COUNT(*) ... HAVING COUNT(*)>1）
ALTER TABLE `vip_customers` DROP INDEX `uk_vip_no`;
ALTER TABLE `vip_customers` ADD UNIQUE KEY `uk_store_vip` (`store_id`, `vip_no`);

-- 5. 改 NOT NULL
ALTER TABLE `vip_customers` MODIFY COLUMN `store_id` int(10) NOT NULL;

-- 6. stores 加 vip_sync_token（外部同步接口用，每店铺独立）
ALTER TABLE `stores` ADD COLUMN `vip_sync_token` varchar(64) DEFAULT NULL COMMENT 'VIP外部同步Token' AFTER `barcode_prefix`;

-- 7. 已有店铺回填 token（不含已存在的；新店铺由 register_store.php 生成）
--    生产执行前先给现有店铺逐个生成唯一 token
-- UPDATE `stores` SET `vip_sync_token` = '<openssl rand -hex 24>' WHERE `id` = 1;

-- 8. vip_sync_log 加 store_id（审计按店铺区分）
ALTER TABLE `vip_sync_log` ADD COLUMN `store_id` int(10) DEFAULT NULL AFTER `id`;
