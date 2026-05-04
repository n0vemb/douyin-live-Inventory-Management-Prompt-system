/*
 Navicat Premium Dump SQL

 Source Server         : 66.26
 Source Server Type    : MySQL
 Source Server Version : 80409 (8.4.9)
 Source Host           : 192.168.66.26:3306
 Source Schema         : ppmart

 Target Server Type    : MySQL
 Target Server Version : 80409 (8.4.9)
 File Encoding         : 65001

 Date: 04/05/2026 16:37:04
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for broadcast_messages
-- ----------------------------
DROP TABLE IF EXISTS `broadcast_messages`;
CREATE TABLE `broadcast_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `message` text NOT NULL,
  `msg_type` varchar(50) DEFAULT 'announcement',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of broadcast_messages
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for inventory_backup
-- ----------------------------
DROP TABLE IF EXISTS `inventory_backup`;
CREATE TABLE `inventory_backup` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL COMMENT '状态类型',
  `purchase_price` decimal(10,2) NOT NULL COMMENT '进价',
  `suggested_price` decimal(10,2) NOT NULL COMMENT '建议售价',
  `stock_qty` int NOT NULL DEFAULT '0' COMMENT '库存数量',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_product_condition` (`product_id`,`condition_type`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='库存状态表';

-- ----------------------------
-- Records of inventory_backup
-- ----------------------------
BEGIN;
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (1, 1, 'sealed', 180.00, 280.00, 13, '2026-04-30 20:53:53', '2026-04-30 22:31:28');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (2, 1, 'opened', 150.00, 240.00, 5, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (3, 1, 'boxless', 120.00, 200.00, 2, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (4, 1, 'flawed', 80.00, 150.00, 1, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (5, 2, 'sealed', 160.00, 250.00, 4, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (6, 2, 'opened', 130.00, 200.00, 3, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (7, 2, 'boxless', 100.00, 160.00, 2, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (8, 2, 'flawed', 60.00, 120.00, 0, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (9, 3, 'sealed', 120.00, 180.00, 6, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (10, 3, 'opened', 95.00, 145.00, 4, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (11, 3, 'boxless', 70.00, 110.00, 3, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (12, 3, 'flawed', 40.00, 80.00, 2, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (13, 4, 'sealed', 100.00, 150.00, 5, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (14, 4, 'opened', 80.00, 120.00, 4, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (15, 4, 'boxless', 55.00, 85.00, 3, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (16, 4, 'flawed', 30.00, 55.00, 1, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (17, 5, 'sealed', 140.00, 210.00, 2, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (18, 5, 'opened', 115.00, 170.00, 3, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (19, 5, 'boxless', 85.00, 130.00, 2, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (20, 5, 'flawed', 50.00, 95.00, 1, '2026-04-30 20:53:53', '2026-04-30 20:53:53');
INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES (21, 6, 'sealed', 1.00, 123.00, 199, '2026-04-30 21:47:09', '2026-04-30 21:47:09');
COMMIT;

-- ----------------------------
-- Table structure for inventory_batches
-- ----------------------------
DROP TABLE IF EXISTS `inventory_batches`;
CREATE TABLE `inventory_batches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `batch_no` varchar(50) NOT NULL COMMENT '批次号',
  `purchase_price` decimal(10,2) NOT NULL COMMENT '本批次进价',
  `suggested_price` decimal(10,2) NOT NULL COMMENT '建议售价',
  `total_qty` int NOT NULL COMMENT '本批次总入库数量',
  `remaining_qty` int NOT NULL COMMENT '本批次剩余数量',
  `supplier` varchar(255) DEFAULT NULL COMMENT '供应商',
  `remark` text,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_condition` (`product_id`,`condition_type`),
  KEY `idx_batch_no` (`batch_no`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='主库存批次表';

-- ----------------------------
-- Records of inventory_batches
-- ----------------------------
BEGIN;
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (20, 19, 'sealed', 'B202605030059567524', 42.00, 50.00, 1, 1, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (21, 20, 'sealed', 'B202605030059568397', 52.00, 56.00, 1, 1, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (22, 21, 'sealed', 'B202605030059566449', 36.00, 43.00, 1, 1, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (23, 21, 'opened', 'B202605030059565804', 35.00, 42.00, 1, 1, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (24, 22, 'sealed', 'B202605030059561409', 60.00, 69.00, 3, 3, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (25, 22, 'opened', 'B202605030059567611', 59.00, 68.00, 1, 1, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (26, 23, 'sealed', 'B202605030059562389', 49.00, 55.00, 3, 3, '千岛', '', '2026-05-03 00:59:56', '2026-05-03 00:59:56');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (27, 19, 'sealed', 'B202605030106135751', 42.00, 50.00, 10, 0, NULL, NULL, '2026-05-03 01:06:13', '2026-05-03 01:06:13');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (28, 19, 'sealed', 'B202605030106393845', 41.00, 49.00, 10, 0, '千岛', NULL, '2026-05-03 01:06:39', '2026-05-03 01:06:39');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (29, 19, 'sealed', 'B202605030106483423', 42.00, 50.00, 10, 0, '千岛', NULL, '2026-05-03 01:06:48', '2026-05-03 01:06:48');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (30, 19, 'sealed', 'B202605030109033245', 42.00, 50.00, 10, 0, NULL, NULL, '2026-05-03 01:09:03', '2026-05-03 01:09:03');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (31, 20, 'sealed', 'B202605030109530390', 52.00, 56.00, 10, 10, NULL, NULL, '2026-05-03 01:09:53', '2026-05-03 01:09:53');
INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES (32, 19, 'sealed', 'B202605030216576267', 50.00, 51.00, 20, 20, '千岛', NULL, '2026-05-03 02:16:57', '2026-05-03 02:16:57');
COMMIT;

-- ----------------------------
-- Table structure for inventory_log
-- ----------------------------
DROP TABLE IF EXISTS `inventory_log`;
CREATE TABLE `inventory_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `change_type` enum('purchase','sale','adjust','return') NOT NULL COMMENT '变动类型',
  `qty_change` int NOT NULL COMMENT '数量变化(正负)',
  `before_qty` int NOT NULL COMMENT '变动前数量',
  `after_qty` int NOT NULL COMMENT '变动后数量',
  `price` decimal(10,2) DEFAULT NULL COMMENT '关联价格',
  `live_session_id` int unsigned DEFAULT NULL COMMENT '直播场次ID',
  `remark` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='库存变动日志';

-- ----------------------------
-- Records of inventory_log
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for label_templates
-- ----------------------------
DROP TABLE IF EXISTS `label_templates`;
CREATE TABLE `label_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'JSON格式的模板配置',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------
-- Records of label_templates
-- ----------------------------
BEGIN;
INSERT INTO `label_templates` (`id`, `name`, `config`, `created_at`, `updated_at`) VALUES (1, '60*40', '{\"canvasWidth\":60,\"canvasHeight\":40,\"paperType\":\"continuous\",\"density\":\"normal\",\"elements\":[{\"type\":\"condition\",\"x\":3.6,\"y\":1.5,\"width\":47.1,\"height\":3.9,\"fontSize\":3.3,\"fontWeight\":\"normal\",\"color\":\"#000000\",\"align\":\"left\"},{\"type\":\"price\",\"x\":36,\"y\":8.2,\"width\":21.8,\"height\":8.2,\"fontSize\":7.1,\"fontWeight\":\"bold\",\"color\":\"#e53e3e\",\"align\":\"left\"},{\"type\":\"name\",\"x\":1.4,\"y\":9.2,\"width\":23.1,\"height\":6.6,\"fontSize\":5.7,\"fontWeight\":\"bold\",\"color\":\"#000000\",\"align\":\"left\"},{\"type\":\"barcode\",\"x\":2.4,\"y\":25.7,\"width\":54.5,\"height\":9.4,\"fontSize\":8.2,\"fontWeight\":\"bold\",\"color\":\"#000000\",\"align\":\"left\"}]}', '2026-05-03 23:27:49', '2026-05-03 23:27:49');
COMMIT;

-- ----------------------------
-- Table structure for live_inventory
-- ----------------------------
DROP TABLE IF EXISTS `live_inventory`;
CREATE TABLE `live_inventory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `live_session_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `initial_stock` int NOT NULL COMMENT '直播开始时库存',
  `current_stock` int NOT NULL COMMENT '当前库存',
  `suggested_price` decimal(10,2) NOT NULL COMMENT '建议售价',
  `live_price` decimal(10,2) DEFAULT NULL COMMENT '直播价（可调整）',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_product_condition` (`live_session_id`,`product_id`,`condition_type`),
  KEY `idx_session` (`live_session_id`),
  KEY `fk_live_inv_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='直播库存快照';

-- ----------------------------
-- Records of live_inventory
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for live_sessions
-- ----------------------------
DROP TABLE IF EXISTS `live_sessions`;
CREATE TABLE `live_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `session_name` varchar(255) NOT NULL COMMENT '场次名称',
  `status` enum('pending','active','ended') DEFAULT 'pending' COMMENT '状态',
  `inventory_copied` tinyint(1) DEFAULT '0' COMMENT '是否已复制库存快照',
  `started_at` timestamp NULL DEFAULT NULL COMMENT '开始时间',
  `ended_at` timestamp NULL DEFAULT NULL COMMENT '结束时间',
  `remark` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='直播场次表';

-- ----------------------------
-- Records of live_sessions
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for outbound_log
-- ----------------------------
DROP TABLE IF EXISTS `outbound_log`;
CREATE TABLE `outbound_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` int unsigned NOT NULL COMMENT '出库批次ID',
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `qty` int NOT NULL COMMENT '出库数量',
  `outbound_price` decimal(10,2) DEFAULT NULL COMMENT '出库价格',
  `live_session_id` int unsigned DEFAULT NULL COMMENT '关联直播场次',
  `order_no` varchar(100) DEFAULT NULL COMMENT '订单号',
  `outbound_batch_no` varchar(20) DEFAULT NULL COMMENT '出库批次号',
  `remark` text,
  `outbound_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch` (`batch_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_outbound_at` (`outbound_at`),
  KEY `idx_outbound_batch_no` (`outbound_batch_no`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='出库记录';

-- ----------------------------
-- Records of outbound_log
-- ----------------------------
BEGIN;
INSERT INTO `outbound_log` (`id`, `batch_id`, `product_id`, `condition_type`, `qty`, `outbound_price`, `live_session_id`, `order_no`, `outbound_batch_no`, `remark`, `outbound_at`) VALUES (6, 27, 19, 'sealed', 1, 50.00, NULL, NULL, '20260503011102', '', '2026-05-03 01:11:02');
COMMIT;

-- ----------------------------
-- Table structure for products
-- ----------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '商品名称',
  `common_name` varchar(255) DEFAULT NULL COMMENT '常用名称',
  `series` varchar(255) DEFAULT NULL COMMENT '系列名称',
  `brand` varchar(255) DEFAULT NULL COMMENT '品牌',
  `release_date` date DEFAULT NULL COMMENT '发售时间',
  `product_description` text COMMENT '产品介绍',
  `barcode` varchar(100) NOT NULL COMMENT '条码',
  `qiandao_price` decimal(10,2) DEFAULT NULL COMMENT '千岛参考价',
  `image_url` varchar(500) DEFAULT NULL COMMENT '图片URL',
  `remark` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_barcode` (`barcode`),
  KEY `idx_name` (`name`),
  KEY `idx_series` (`series`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='商品主表';

-- ----------------------------
-- Records of products
-- ----------------------------
BEGIN;
INSERT INTO `products` (`id`, `name`, `common_name`, `series`, `brand`, `release_date`, `product_description`, `barcode`, `qiandao_price`, `image_url`, `remark`, `created_at`, `updated_at`) VALUES (19, '天注定', '天注定', '温室芒草', NULL, NULL, NULL, '6941448603060', 42.00, 'uploads/img_20260503131726_69f6da66eb1e8.jpg', NULL, '2026-05-03 00:59:56', '2026-05-03 13:17:28');
INSERT INTO `products` (`id`, `name`, `common_name`, `series`, `brand`, `release_date`, `product_description`, `barcode`, `qiandao_price`, `image_url`, `remark`, `created_at`, `updated_at`) VALUES (20, '循环', '循环', '温室芒草', NULL, NULL, NULL, '6941448649387', 52.00, 'uploads/img_20260503131717_69f6da5d6cc9a.jpg', NULL, '2026-05-03 00:59:56', '2026-05-03 13:17:19');
INSERT INTO `products` (`id`, `name`, `common_name`, `series`, `brand`, `release_date`, `product_description`, `barcode`, `qiandao_price`, `image_url`, `remark`, `created_at`, `updated_at`) VALUES (21, '过载', '过载', '温室芒草', NULL, NULL, '学生党、备考党、加班党集合！这只小野温室芒草系列——过载，完全就是我们的真实写照！公式帽子、满满课表、堆积的资料，把压力拉满的状态精准还原，细节满满，共情力直接拉满！今天在姐姐家下单，现货秒发、所见所得，无瑕疵发货、一单包邮到家，没有各种游戏套路，直接选款下单。线下你抽一个99，隔壁今天走45，今天姐姐家45不开！要的话直接扣价43！听清楚，新品过载，今天43！一单包邮！这个今天只有2只，优先扣价发未拆袋的。', '6941448697461', 36.00, 'uploads/img_20260503131655_69f6da47ef744.jpg', NULL, '2026-05-03 00:59:56', '2026-05-03 13:16:57');
INSERT INTO `products` (`id`, `name`, `common_name`, `series`, `brand`, `release_date`, `product_description`, `barcode`, `qiandao_price`, `image_url`, `remark`, `created_at`, `updated_at`) VALUES (22, '温水青蛙', '温水青蛙', '温室芒草', NULL, NULL, NULL, '6941448682072', 60.00, 'uploads/img_20260503131709_69f6da5549453.jpg', NULL, '2026-05-03 00:59:56', '2026-05-03 13:17:10');
INSERT INTO `products` (`id`, `name`, `common_name`, `series`, `brand`, `release_date`, `product_description`, `barcode`, `qiandao_price`, `image_url`, `remark`, `created_at`, `updated_at`) VALUES (23, '重缚', '重缚', '温室芒草', NULL, NULL, '小野温室芒草—重缚，懂小野的都知道这款有多厉害！\n整个身体被打印纸绷带缠绕、被工作日常困住，麻木又无奈的氛围感拉满，细节做工很棒，是系列里的热门爆款！感觉打工人一眼被戳中了！\n\n今天在姐姐家下单，现货秒发、所见所得，无瑕疵发货、一单包邮到家，没有各种游戏套路，直接选款下单。\n\n线下你抽一个99，隔壁今天走60算优惠了，今天姐姐家信号开播，60都不开！直接给你炸波新品福，来吧，大家准备好，\n这个今天只有3只，要的话，等我报价大家扣数字，我叫你们的名字再去拍。听清楚价格，重缚-今天开55！\n\n拆袋有盒，卡片齐全，无暇正品一单包！收到以后任何问题随时回来找我！好吧。', '6941448658985', 49.00, 'uploads/img_20260503131643_69f6da3b27bce.jpg', NULL, '2026-05-03 00:59:56', '2026-05-03 13:16:45');
COMMIT;

-- ----------------------------
-- Table structure for purchase_log
-- ----------------------------
DROP TABLE IF EXISTS `purchase_log`;
CREATE TABLE `purchase_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL COMMENT '状态类型',
  `purchase_price` decimal(10,2) NOT NULL COMMENT '采购单价',
  `qty` int NOT NULL DEFAULT '1' COMMENT '采购数量',
  `supplier` varchar(255) DEFAULT NULL COMMENT '供应商',
  `remark` text COMMENT '备注',
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '采购时间',
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_purchased_at` (`purchased_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='采购记录表';

-- ----------------------------
-- Records of purchase_log
-- ----------------------------
BEGIN;
INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES (13, 19, 'sealed', 42.00, 10, NULL, NULL, '2026-05-03 01:06:13');
INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES (14, 19, 'sealed', 42.00, 10, '千岛', NULL, '2026-05-03 01:06:39');
INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES (15, 19, 'sealed', 42.00, 10, '千岛', NULL, '2026-05-03 01:06:48');
INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES (16, 19, 'sealed', 42.00, 10, NULL, NULL, '2026-05-03 01:09:03');
INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES (17, 20, 'sealed', 52.00, 10, NULL, NULL, '2026-05-03 01:09:53');
INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES (18, 19, 'sealed', 50.00, 20, '千岛', NULL, '2026-05-03 02:16:57');
COMMIT;

-- ----------------------------
-- Table structure for sales_log
-- ----------------------------
DROP TABLE IF EXISTS `sales_log`;
CREATE TABLE `sales_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL COMMENT '状态类型',
  `sale_price` decimal(10,2) NOT NULL COMMENT '实际售价',
  `qty` int NOT NULL DEFAULT '1' COMMENT '销售数量',
  `live_session_id` int unsigned DEFAULT NULL COMMENT '直播场次ID',
  `sold_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '销售时间',
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_live_session_id` (`live_session_id`),
  KEY `idx_sold_at` (`sold_at`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='销售记录表';

-- ----------------------------
-- Records of sales_log
-- ----------------------------
BEGIN;
COMMIT;

-- ----------------------------
-- Table structure for system_settings
-- ----------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL COMMENT '配置键',
  `setting_value` text COMMENT '配置值',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=169 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='系统配置表';

-- ----------------------------
-- Records of system_settings
-- ----------------------------
BEGIN;
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES (1, 'system_name', '直播销售系统', '2026-05-03 03:35:10');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES (2, 'condition_types', '[{\"key\":\"sealed\",\"name\":\"未拆袋\",\"color\":\"#10b981\"},{\"key\":\"opened\",\"name\":\"已拆无瑕\",\"color\":\"#3b82f6\"},{\"key\":\"boxless\",\"name\":\"微瑕\",\"color\":\"#f59e0b\"}]', '2026-05-03 03:35:10');
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES (3, 'live_display', '{\"elements\":[{\"type\":\"image\",\"enabled\":true,\"left\":18.083612040133715,\"top\":107.39799331103703,\"width\":475,\"height\":500,\"fontSize\":\"0px\",\"zIndex\":1},{\"type\":\"productName\",\"enabled\":true,\"left\":630,\"top\":20,\"width\":320,\"height\":60,\"fontSize\":\"73px\",\"zIndex\":2},{\"type\":\"productSeries\",\"enabled\":true,\"left\":430,\"top\":40,\"width\":600,\"height\":60,\"fontSize\":\"48px\",\"zIndex\":2},{\"type\":\"commonName\",\"enabled\":true,\"left\":1050,\"top\":20,\"width\":300,\"height\":50,\"fontSize\":\"68px\",\"zIndex\":2},{\"type\":\"suggestedPrice\",\"enabled\":true,\"left\":1190,\"top\":120,\"width\":500,\"height\":10,\"fontSize\":\"60px\",\"zIndex\":1},{\"type\":\"productDescription\",\"enabled\":true,\"left\":505,\"top\":125,\"width\":640,\"height\":80,\"fontSize\":\"32px\",\"zIndex\":2},{\"type\":\"condition\",\"enabled\":true,\"left\":1155,\"top\":240,\"width\":500,\"height\":10,\"fontSize\":\"30px\",\"zIndex\":2,\"itemSpacing\":20}],\"containerWidth\":\"100%\",\"containerPadding\":\"20px\"}', '2026-05-03 03:35:10');
COMMIT;

-- ----------------------------
-- Table structure for v_inventory_summary
-- ----------------------------
DROP TABLE IF EXISTS `v_inventory_summary`;
CREATE TABLE `v_inventory_summary` (
  `product_id` int unsigned DEFAULT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') DEFAULT NULL,
  `total_stock` decimal(32,0) DEFAULT NULL,
  `avg_cost` decimal(43,2) DEFAULT NULL,
  `latest_suggested_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------
-- Records of v_inventory_summary
-- ----------------------------
BEGIN;
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
