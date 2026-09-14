-- ============================================================
-- PPMart v26：线下收银台收款方式可选「静态收款码 / 易支付」
-- Version: 26.0 (POS Payment Mode)
--
-- 背景：收银台原本只支持「上传静态收款码 + 顾客自己点已付款」。
--   现按店铺设置二选一：
--     static = 沿用静态收款码（原行为，完全不变）
--     epay   = 按订单金额动态生成收款码，收到平台异步回调才置「已收款」
--   易支付商户配置放在 stores（一个店铺一个商户号；门店不再单独覆盖）。
--
-- 1) stores     : pos_pay_mode + 易支付商户配置
-- 2) pos_orders : 记录实际支付渠道 / 平台订单号 / 支付串（便于重复打开收款码）
-- 3) epay_logs  : 易支付出入站原始报文（正式功能日志，与测试站沙箱表分开）
--
-- ⚠ 执行前备份。
-- 执行记录：测试库(ppmart2_test)已于 2026-09-14 以幂等脚本执行完毕；
--   生产库(ppmart2)按发布流程等通知后再执行。
--   本文件是三处 ALTER/CREATE 的原始记录，重复执行会因「列/表已存在」报错，属预期。
-- ============================================================

-- ---------- 1. 收款方式与易支付商户配置 ----------
ALTER TABLE `stores`
    ADD COLUMN `pos_pay_mode` VARCHAR(10) NOT NULL DEFAULT 'static' COMMENT '收银台收款方式：static=静态收款码; epay=易支付动态码' AFTER `pos_ad_lines`,
    ADD COLUMN `epay_api_url` VARCHAR(120) NOT NULL DEFAULT 'https://www.ezfpy.cn' COMMENT '易支付接口地址（不带末尾斜杠）' AFTER `pos_pay_mode`,
    ADD COLUMN `epay_pid` VARCHAR(32) DEFAULT NULL COMMENT '易支付商户ID' AFTER `epay_api_url`,
    ADD COLUMN `epay_mch_key` VARCHAR(128) DEFAULT NULL COMMENT '易支付商户密钥（签名用，绝不回传前端）' AFTER `epay_pid`,
    ADD COLUMN `epay_sign_type` VARCHAR(10) NOT NULL DEFAULT 'MD5' COMMENT '签名方式，本平台仅 MD5' AFTER `epay_mch_key`;

-- ---------- 2. 收银订单记录支付渠道 ----------
ALTER TABLE `pos_orders`
    ADD COLUMN `pay_channel` VARCHAR(16) DEFAULT NULL COMMENT '实际支付渠道：wechat/alipay（易支付动态码下单时写入）' AFTER `pay_method`,
    ADD COLUMN `pay_trade_no` VARCHAR(64) DEFAULT NULL COMMENT '易支付平台订单号' AFTER `pay_channel`,
    ADD COLUMN `epay_qr` VARCHAR(500) DEFAULT NULL COMMENT '易支付支付串（重开收款码时复用，不重复下单）' AFTER `pay_trade_no`,
    ADD INDEX `idx_po_pay_trade` (`pay_trade_no`);

-- ---------- 3. 易支付出入站日志 ----------
CREATE TABLE IF NOT EXISTS `epay_logs` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `store_id`     INT UNSIGNED DEFAULT NULL COMMENT '归属店铺（按 pid/订单号定位）',
    `out_trade_no` VARCHAR(64)  DEFAULT NULL COMMENT '商户订单号',
    `direction`    VARCHAR(6)   NOT NULL COMMENT 'out=我们请求平台; in=平台回调我们',
    `endpoint`     VARCHAR(255) DEFAULT NULL COMMENT '请求地址或回调动作',
    `http_code`    INT DEFAULT NULL,
    `ok`           TINYINT NOT NULL DEFAULT 0 COMMENT '1=成功',
    `note`         VARCHAR(255) DEFAULT NULL,
    `payload`      TEXT COMMENT '原始报文（截断 20000 字节）',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_el_out` (`out_trade_no`, `created_at`),
    KEY `idx_el_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='易支付出入站日志（收银台正式功能）';
