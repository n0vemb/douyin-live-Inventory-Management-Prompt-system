-- ============================================================
-- PPMart v24：收银台顶部滚动广告词 + 线下收银台抽奖（大转盘）
-- Version: 24.0 (POS Ad Lines & Lottery)
-- 1) stores/shops 增加 pos_ad_lines（多条用 | 分隔），用于收银台顶部滚动广告
-- 2) 抽奖：活动 → 奖品（权重） → 抽奖记录；满额可抽，每单 1 次，
--    中「再来一次」可追加（上限可配）；可配保底奖品（抽到「未中奖」改发保底）
-- 3) 奖品落地：券 → coupon_claims(issue_type=lottery)；在库商品 → 0 元出库单
--    （占用批次，随门店待出库正常出库扣库存）；自定义/无库存 → 0 元单登记（不扣库存）
-- ⚠ 执行前备份。
-- 执行记录：生产库(ppmart2)/测试库(ppmart2_test)已于 2026-09-14 按本文件的 DDL 以
--   幂等脚本（information_schema 先判列/表/索引是否存在）执行完毕。
--   本文件是三处 ALTER/CREATE 的原始记录，重复直接执行会因「列/表已存在」报错，属预期。
-- ============================================================

-- ---------- 1. 收银台滚动广告词 ----------
ALTER TABLE `stores`
    ADD COLUMN `pos_ad_lines` VARCHAR(1000) DEFAULT NULL COMMENT '收银台顶部滚动广告词，多条用 | 分隔' AFTER `pos_hide_price`;

ALTER TABLE `shops`
    ADD COLUMN `pos_ad_lines` VARCHAR(1000) DEFAULT NULL COMMENT '收银台顶部滚动广告词（本店覆盖，多条用 | 分隔）' AFTER `pos_hide_price`;

-- ---------- 2. 抽奖活动 ----------
CREATE TABLE IF NOT EXISTS `lottery_campaigns` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`          INT UNSIGNED NOT NULL COMMENT '归属集团',
    `shop_id`           INT DEFAULT NULL COMMENT '归属门店（NULL=全部门店）',
    `name`              VARCHAR(80) NOT NULL COMMENT '活动名称',
    `status`            VARCHAR(12) NOT NULL DEFAULT 'active' COMMENT 'active=进行中; paused=已暂停',
    `threshold`         DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '单笔实付满多少元可抽奖',
    `spin_again_limit`  TINYINT NOT NULL DEFAULT 2 COMMENT '「再来一次」最多追加次数',
    `guarantee_prize_id` INT UNSIGNED DEFAULT NULL COMMENT '保底奖品ID（抽到「未中奖」时改发该奖品，NULL=不保底）',
    `start_at`          DATETIME DEFAULT NULL COMMENT '开始时间',
    `end_at`            DATETIME DEFAULT NULL COMMENT '结束时间',
    `remark`            VARCHAR(255) DEFAULT NULL,
    `created_by`        INT DEFAULT NULL,
    `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lc_store_shop` (`store_id`, `shop_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收银台抽奖活动';

-- ---------- 3. 抽奖奖品（权重法） ----------
CREATE TABLE IF NOT EXISTS `lottery_prizes` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`        INT UNSIGNED NOT NULL,
    `name`               VARCHAR(80) NOT NULL COMMENT '奖品名称（转盘上显示）',
    `prize_type`         VARCHAR(12) NOT NULL DEFAULT 'none' COMMENT 'coupon=优惠券; product=在库商品; custom=自定义商品; spin_again=再来一次; none=未中奖',
    `weight`             INT NOT NULL DEFAULT 0 COMMENT '权重（权重法；全部权重之和不足 100 时，剩余视为未中奖）',
    `quota`              INT NOT NULL DEFAULT 0 COMMENT '可中数量（0=不限）',
    `won`                INT NOT NULL DEFAULT 0 COMMENT '已中出数量',
    `coupon_campaign_id` INT DEFAULT NULL COMMENT '券奖品对应的券活动ID',
    `product_id`         INT DEFAULT NULL COMMENT '商品奖品对应的商品ID',
    `condition_type`     VARCHAR(50) DEFAULT NULL COMMENT '商品奖品的品相',
    `qty`                INT NOT NULL DEFAULT 1 COMMENT '商品奖品数量',
    `custom_note`        VARCHAR(255) DEFAULT NULL COMMENT '自定义奖品说明（门店待出库登记用）',
    `sort_order`         INT NOT NULL DEFAULT 0,
    `status`             TINYINT NOT NULL DEFAULT 1 COMMENT '1=启用; 0=停用',
    `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lp_campaign` (`campaign_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收银台抽奖奖品';

-- ---------- 4. 抽奖记录 ----------
CREATE TABLE IF NOT EXISTS `lottery_draws` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`     INT UNSIGNED NOT NULL,
    `store_id`        INT UNSIGNED NOT NULL,
    `shop_id`         INT DEFAULT NULL,
    `order_id`        INT UNSIGNED NOT NULL COMMENT '来源收银订单（满额那笔）',
    `order_no`        VARCHAR(32) DEFAULT NULL,
    `prize_id`        INT UNSIGNED DEFAULT NULL COMMENT '0/NULL=未中奖',
    `prize_type`      VARCHAR(12) NOT NULL DEFAULT 'none',
    `prize_name`      VARCHAR(80) DEFAULT NULL,
    `custom_note`     VARCHAR(255) DEFAULT NULL,
    `spin_index`      TINYINT NOT NULL DEFAULT 1 COMMENT '本单第几次抽（1 起）',
    `is_guarantee`    TINYINT NOT NULL DEFAULT 0 COMMENT '1=保底奖品',
    `phone`           VARCHAR(20) DEFAULT NULL COMMENT '领取手机号（券必填）',
    `claim_status`    VARCHAR(12) NOT NULL DEFAULT 'pending' COMMENT 'pending=待领取; claimed=已领取/已登记; voided=已作废',
    `claimed_at`      DATETIME DEFAULT NULL,
    `coupon_claim_id` INT DEFAULT NULL COMMENT '券奖品发放的券实例ID',
    `prize_order_id`  INT UNSIGNED DEFAULT NULL COMMENT '商品/自定义奖品生成的 0 元出库单ID',
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ld_order_spin` (`order_id`, `spin_index`),
    KEY `idx_ld_campaign` (`campaign_id`, `created_at`),
    KEY `idx_ld_phone` (`phone`),
    KEY `idx_ld_claim` (`claim_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收银台抽奖记录';

-- ---------- 5. 奖品出库单标记 ----------
ALTER TABLE `pos_orders`
    ADD COLUMN `source` VARCHAR(16) NOT NULL DEFAULT 'pos' COMMENT 'pos=收银台订单; lottery=抽奖奖品单' AFTER `shop_id`,
    ADD INDEX `idx_po_source` (`store_id`, `source`);

ALTER TABLE `pos_order_items`
    ADD COLUMN `item_name` VARCHAR(255) DEFAULT NULL COMMENT '展示名（奖品/自定义商品用；product_id=0 时取此名）' AFTER `condition_type`,
    ADD COLUMN `is_prize` TINYINT NOT NULL DEFAULT 0 COMMENT '1=抽奖奖品行' AFTER `line_total`,
    ADD COLUMN `inventory_tracked` TINYINT NOT NULL DEFAULT 1 COMMENT '0=不占库存（自定义/无库存奖品，仅登记）' AFTER `is_prize`;
