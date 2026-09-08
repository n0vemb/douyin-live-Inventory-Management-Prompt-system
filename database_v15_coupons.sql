-- ============================================================
-- PPMart 线下收银台优惠券体系
-- Version: 15.0 (Coupons)
-- 券按店铺隔离；活动页按手机号领取；收银台输入手机号选券；
-- 生命周期：unused → locked(下单占用) → used(已付款核销)
--          取消/超时/退单 → unused 退回
-- ============================================================

ALTER TABLE `pos_orders`
    ADD COLUMN `coupon_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠券抵扣金额（应付已扣减）' AFTER `discount_amount`;

-- 券活动（后台配置/生成）
CREATE TABLE IF NOT EXISTS `coupon_campaigns` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `store_id`    INT NOT NULL COMMENT '归属店铺',
    `name`        VARCHAR(80) NOT NULL COMMENT '活动名称',
    `coupon_type` VARCHAR(16) NOT NULL DEFAULT 'threshold' COMMENT 'threshold=满减; fixed=无门槛立减',
    `threshold`   DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '满减门槛（无门槛=0）',
    `amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '优惠金额',
    `total_count` INT NOT NULL DEFAULT 0 COMMENT '总发行量（0=不限）',
    `per_user`    INT NOT NULL DEFAULT 1 COMMENT '每人限领张数',
    `stackable`   TINYINT NOT NULL DEFAULT 0 COMMENT '是否允许与其它券叠加（每单同券种最多1张）',
    `start_at`    DATETIME DEFAULT NULL COMMENT '可领取/可使用开始时间',
    `end_at`      DATETIME DEFAULT NULL COMMENT '有效期截止',
    `status`      VARCHAR(12) NOT NULL DEFAULT 'active' COMMENT 'active/paused/archived',
    `claim_token` CHAR(32) DEFAULT NULL COMMENT '活动页公开链接 token',
    `remark`      VARCHAR(255) DEFAULT NULL,
    `created_by`  INT DEFAULT NULL,
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_campaign_token` (`claim_token`),
    KEY `idx_campaign_store` (`store_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券活动配置';

-- 用户已领取的券（一人可多张，按手机号归属）
CREATE TABLE IF NOT EXISTS `coupon_claims` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `store_id`   INT NOT NULL,
    `campaign_id`INT NOT NULL,
    `phone`      VARCHAR(20) NOT NULL COMMENT '领取手机号',
    `status`     VARCHAR(12) NOT NULL DEFAULT 'unused' COMMENT 'unused/locked/used/refunded/expired',
    `order_id`   INT DEFAULT NULL COMMENT '当前占用/核销订单',
    `order_no`   VARCHAR(40) DEFAULT NULL,
    `issue_type` VARCHAR(10) NOT NULL DEFAULT 'claim' COMMENT 'claim=活动领取; manual=后台补发',
    `issued_by`  INT DEFAULT NULL,
    `claimed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `used_at`    DATETIME DEFAULT NULL,
    `released_at` DATETIME DEFAULT NULL,
    `ip`         VARCHAR(45) DEFAULT NULL,
    `remark`     VARCHAR(255) DEFAULT NULL,
    KEY `idx_claim_store_phone` (`store_id`, `phone`, `status`),
    KEY `idx_claim_campaign` (`campaign_id`, `status`),
    KEY `idx_claim_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户优惠券实例';

-- 订单用券记录（锁券/核销都以此为准）
CREATE TABLE IF NOT EXISTS `pos_order_coupons` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`   INT NOT NULL,
    `claim_id`   INT NOT NULL,
    `campaign_id`INT NOT NULL,
    `store_id`   INT NOT NULL,
    `phone`      VARCHAR(20) DEFAULT NULL,
    `amount_off` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_order_claim` (`order_id`, `claim_id`),
    KEY `idx_oc_order` (`order_id`),
    KEY `idx_oc_claim` (`claim_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单-优惠券关系';
