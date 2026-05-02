-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-05-02 01:49:15
-- 服务器版本： 5.7.44-log
-- PHP 版本： 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `ppmart`
--

-- --------------------------------------------------------

--
-- 表的结构 `broadcast_messages`
--

CREATE TABLE `broadcast_messages` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `msg_type` varchar(50) DEFAULT 'announcement',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `broadcast_messages`
--

INSERT INTO `broadcast_messages` (`id`, `session_id`, `message`, `msg_type`, `created_at`) VALUES
(11, 9, '提示一下下单流程', 'announcement', '2026-05-02 01:10:11');

-- --------------------------------------------------------

--
-- 替换视图以便查看 `inventory`
-- （参见下面的实际视图）
--
CREATE TABLE `inventory` (
`id` binary(0)
,`product_id` int(10) unsigned
,`condition_type` enum('sealed','opened','boxless','flawed')
,`purchase_price` decimal(10,2)
,`suggested_price` decimal(10,2)
,`stock_qty` decimal(32,0)
);

-- --------------------------------------------------------

--
-- 表的结构 `inventory_backup`
--

CREATE TABLE `inventory_backup` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL COMMENT '状态类型',
  `purchase_price` decimal(10,2) NOT NULL COMMENT '进价',
  `suggested_price` decimal(10,2) NOT NULL COMMENT '建议售价',
  `stock_qty` int(11) NOT NULL DEFAULT '0' COMMENT '库存数量',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='库存状态表';

--
-- 转存表中的数据 `inventory_backup`
--

INSERT INTO `inventory_backup` (`id`, `product_id`, `condition_type`, `purchase_price`, `suggested_price`, `stock_qty`, `created_at`, `updated_at`) VALUES
(1, 1, 'sealed', 180.00, 280.00, 13, '2026-04-30 12:53:53', '2026-04-30 14:31:28'),
(2, 1, 'opened', 150.00, 240.00, 5, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(3, 1, 'boxless', 120.00, 200.00, 2, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(4, 1, 'flawed', 80.00, 150.00, 1, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(5, 2, 'sealed', 160.00, 250.00, 4, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(6, 2, 'opened', 130.00, 200.00, 3, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(7, 2, 'boxless', 100.00, 160.00, 2, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(8, 2, 'flawed', 60.00, 120.00, 0, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(9, 3, 'sealed', 120.00, 180.00, 6, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(10, 3, 'opened', 95.00, 145.00, 4, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(11, 3, 'boxless', 70.00, 110.00, 3, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(12, 3, 'flawed', 40.00, 80.00, 2, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(13, 4, 'sealed', 100.00, 150.00, 5, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(14, 4, 'opened', 80.00, 120.00, 4, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(15, 4, 'boxless', 55.00, 85.00, 3, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(16, 4, 'flawed', 30.00, 55.00, 1, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(17, 5, 'sealed', 140.00, 210.00, 2, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(18, 5, 'opened', 115.00, 170.00, 3, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(19, 5, 'boxless', 85.00, 130.00, 2, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(20, 5, 'flawed', 50.00, 95.00, 1, '2026-04-30 12:53:53', '2026-04-30 12:53:53'),
(21, 6, 'sealed', 1.00, 123.00, 199, '2026-04-30 13:47:09', '2026-04-30 13:47:09');

-- --------------------------------------------------------

--
-- 表的结构 `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `batch_no` varchar(50) NOT NULL COMMENT '批次号',
  `purchase_price` decimal(10,2) NOT NULL COMMENT '本批次进价',
  `suggested_price` decimal(10,2) NOT NULL COMMENT '建议售价',
  `total_qty` int(11) NOT NULL COMMENT '本批次总入库数量',
  `remaining_qty` int(11) NOT NULL COMMENT '本批次剩余数量',
  `supplier` varchar(255) DEFAULT NULL COMMENT '供应商',
  `remark` text,
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='主库存批次表';

--
-- 转存表中的数据 `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `product_id`, `condition_type`, `batch_no`, `purchase_price`, `suggested_price`, `total_qty`, `remaining_qty`, `supplier`, `remark`, `purchased_at`, `created_at`) VALUES
(1, 6, 'sealed', 'B202604302256497010', 35.00, 42.00, 5, 2, '千岛', NULL, '2026-04-30 14:56:49', '2026-04-30 14:56:49'),
(2, 6, 'opened', 'B202604302257141307', 28.00, 40.00, 2, 1, '老二', NULL, '2026-04-30 14:57:14', '2026-04-30 14:57:14'),
(3, 1, 'boxless', 'B202604302257300476', 2.00, 254.15, 1, 1, NULL, NULL, '2026-04-30 14:57:30', '2026-04-30 14:57:30'),
(4, 6, 'sealed', 'B202604302306577994', 40.00, 43.00, 1, 1, NULL, NULL, '2026-04-30 15:06:57', '2026-04-30 15:06:57'),
(5, 6, 'flawed', 'B202604302343297042', 12.00, 15.00, 1, 1, NULL, NULL, '2026-04-30 15:43:29', '2026-04-30 15:43:29'),
(6, 6, 'sealed', 'B202605011442199540', 35.00, 42.00, 10, 9, NULL, NULL, '2026-05-01 06:42:19', '2026-05-01 06:42:19'),
(7, 5, 'sealed', 'B202605012021073987', 100.00, 197.10, 10, 10, NULL, NULL, '2026-05-01 12:21:07', '2026-05-01 12:21:07'),
(8, 5, 'opened', 'B202605012021079406', 80.00, 175.20, 10, 10, NULL, NULL, '2026-05-01 12:21:07', '2026-05-01 12:21:07'),
(9, 5, 'boxless', 'B202605012021073662', 60.00, 153.30, 10, 10, NULL, NULL, '2026-05-01 12:21:07', '2026-05-01 12:21:07'),
(10, 5, 'flawed', 'B202605012021082989', 40.00, 109.50, 10, 10, NULL, NULL, '2026-05-01 12:21:08', '2026-05-01 12:21:08'),
(11, 6, 'boxless', 'B202605020010376829', 32.00, 39.00, 10, 10, NULL, NULL, '2026-05-01 16:10:37', '2026-05-01 16:10:37');

-- --------------------------------------------------------

--
-- 表的结构 `inventory_log`
--

CREATE TABLE `inventory_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `change_type` enum('purchase','sale','adjust','return') NOT NULL COMMENT '变动类型',
  `qty_change` int(11) NOT NULL COMMENT '数量变化(正负)',
  `before_qty` int(11) NOT NULL COMMENT '变动前数量',
  `after_qty` int(11) NOT NULL COMMENT '变动后数量',
  `price` decimal(10,2) DEFAULT NULL COMMENT '关联价格',
  `live_session_id` int(10) UNSIGNED DEFAULT NULL COMMENT '直播场次ID',
  `remark` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='库存变动日志';

--
-- 转存表中的数据 `inventory_log`
--

INSERT INTO `inventory_log` (`id`, `product_id`, `condition_type`, `change_type`, `qty_change`, `before_qty`, `after_qty`, `price`, `live_session_id`, `remark`, `created_at`) VALUES
(1, 6, 'sealed', 'purchase', 199, 0, 199, 1.00, NULL, NULL, '2026-04-30 13:47:09'),
(5, 1, 'sealed', 'purchase', 1, 0, 1, NULL, NULL, '直播退还', '2026-04-30 14:23:08'),
(6, 1, 'sealed', 'purchase', 1, 1, 2, NULL, NULL, '直播退还', '2026-04-30 14:23:08'),
(7, 1, 'sealed', 'purchase', 1, 2, 3, NULL, NULL, '直播退还', '2026-04-30 14:23:09'),
(8, 1, 'sealed', 'purchase', 1, 3, 4, NULL, NULL, '直播退还', '2026-04-30 14:23:09'),
(9, 1, 'sealed', 'purchase', 1, 4, 5, NULL, NULL, '直播退还', '2026-04-30 14:23:09'),
(10, 1, 'sealed', 'purchase', 1, 5, 6, NULL, NULL, '直播退还', '2026-04-30 14:23:10'),
(11, 1, 'sealed', 'purchase', 1, 6, 7, NULL, NULL, '直播退还', '2026-04-30 14:23:11'),
(12, 1, 'sealed', 'purchase', 1, 7, 8, NULL, NULL, '直播退还', '2026-04-30 14:23:11'),
(13, 1, 'sealed', 'purchase', 1, 8, 9, NULL, NULL, '直播退还', '2026-04-30 14:23:12'),
(14, 1, 'sealed', 'purchase', 1, 9, 10, NULL, NULL, '直播退还', '2026-04-30 14:23:12'),
(15, 1, 'sealed', 'purchase', 1, 10, 11, NULL, NULL, '直播退还', '2026-04-30 14:23:12'),
(16, 1, 'sealed', 'purchase', 1, 11, 12, NULL, NULL, '直播退还', '2026-04-30 14:23:13'),
(17, 1, 'sealed', 'purchase', 1, 12, 13, NULL, NULL, '直播退还', '2026-04-30 14:23:13'),
(18, 1, 'sealed', 'purchase', 1, 13, 14, NULL, NULL, '直播退还', '2026-04-30 14:23:13'),
(19, 1, 'sealed', 'purchase', 1, 14, 15, NULL, NULL, '直播退还', '2026-04-30 14:23:13'),
(20, 1, 'sealed', 'purchase', 1, 15, 16, NULL, NULL, '直播退还', '2026-04-30 14:23:14'),
(24, 6, 'sealed', 'return', 1, 5, 6, NULL, 7, '直播退还', '2026-04-30 16:23:29'),
(25, 6, 'opened', 'return', 1, 0, 1, NULL, 7, '直播退还', '2026-04-30 16:27:18'),
(26, 6, 'opened', 'return', 1, 1, 2, NULL, 7, '直播退还', '2026-04-30 16:27:18'),
(27, 6, 'flawed', 'return', 1, 0, 1, NULL, 7, '直播退还', '2026-04-30 16:27:19'),
(28, 6, 'sealed', 'return', 1, 5, 6, NULL, 7, '直播退还', '2026-04-30 16:30:46'),
(29, 6, 'sealed', 'return', 1, 11, 12, NULL, 8, '直播退还', '2026-05-01 16:03:44'),
(30, 6, 'sealed', 'return', 1, 11, 12, NULL, 8, '直播退还', '2026-05-01 16:08:03'),
(31, 6, 'sealed', 'return', 1, 11, 12, NULL, 9, '直播退还', '2026-05-01 17:03:55');

-- --------------------------------------------------------

--
-- 表的结构 `live_inventory`
--

CREATE TABLE `live_inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `live_session_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `initial_stock` int(11) NOT NULL COMMENT '直播开始时库存',
  `current_stock` int(11) NOT NULL COMMENT '当前库存',
  `suggested_price` decimal(10,2) NOT NULL COMMENT '建议售价',
  `live_price` decimal(10,2) DEFAULT NULL COMMENT '直播价（可调整）',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='直播库存快照';

--
-- 转存表中的数据 `live_inventory`
--

INSERT INTO `live_inventory` (`id`, `live_session_id`, `product_id`, `condition_type`, `initial_stock`, `current_stock`, `suggested_price`, `live_price`, `created_at`, `updated_at`) VALUES
(8, 7, 1, 'boxless', 1, 1, 254.15, NULL, '2026-04-30 16:12:33', '2026-04-30 16:12:33'),
(9, 7, 6, 'sealed', 6, 6, 43.00, 43.00, '2026-04-30 16:12:33', '2026-04-30 16:30:46'),
(10, 7, 6, 'opened', 2, 1, 40.00, 40.00, '2026-04-30 16:12:33', '2026-04-30 16:30:43'),
(11, 7, 6, 'flawed', 1, 1, 15.00, 15.00, '2026-04-30 16:12:33', '2026-04-30 16:27:19'),
(12, 8, 1, 'boxless', 1, 1, 254.15, NULL, '2026-05-01 12:20:25', '2026-05-01 12:20:25'),
(13, 8, 6, 'sealed', 12, 12, 43.00, 43.00, '2026-05-01 12:20:25', '2026-05-01 16:08:03'),
(14, 8, 6, 'opened', 1, 1, 40.00, NULL, '2026-05-01 12:20:25', '2026-05-01 12:20:25'),
(15, 8, 6, 'flawed', 1, 1, 15.00, NULL, '2026-05-01 12:20:25', '2026-05-01 12:20:25'),
(16, 9, 1, 'boxless', 1, 1, 254.15, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(17, 9, 5, 'sealed', 10, 10, 197.10, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(18, 9, 5, 'opened', 10, 10, 175.20, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(19, 9, 5, 'boxless', 10, 10, 153.30, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(20, 9, 5, 'flawed', 10, 10, 109.50, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(21, 9, 6, 'sealed', 12, 12, 43.00, 42.00, '2026-05-01 16:11:00', '2026-05-01 17:08:27'),
(22, 9, 6, 'opened', 1, 1, 40.00, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(23, 9, 6, 'boxless', 10, 10, 39.00, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00'),
(24, 9, 6, 'flawed', 1, 1, 15.00, NULL, '2026-05-01 16:11:00', '2026-05-01 16:11:00');

-- --------------------------------------------------------

--
-- 表的结构 `live_sessions`
--

CREATE TABLE `live_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_name` varchar(255) NOT NULL COMMENT '场次名称',
  `status` enum('pending','active','ended') DEFAULT 'pending' COMMENT '状态',
  `inventory_copied` tinyint(1) DEFAULT '0' COMMENT '是否已复制库存快照',
  `started_at` timestamp NULL DEFAULT NULL COMMENT '开始时间',
  `ended_at` timestamp NULL DEFAULT NULL COMMENT '结束时间',
  `remark` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='直播场次表';

--
-- 转存表中的数据 `live_sessions`
--

INSERT INTO `live_sessions` (`id`, `session_name`, `status`, `inventory_copied`, `started_at`, `ended_at`, `remark`, `created_at`) VALUES
(7, '5月1日上午场', 'ended', 1, '2026-04-30 16:12:33', '2026-05-01 03:14:50', NULL, '2026-04-30 16:12:33'),
(8, '5月1日晚间场', 'ended', 1, '2026-05-01 12:20:25', '2026-05-01 16:10:56', NULL, '2026-05-01 12:20:25'),
(9, '5月2日上午场', 'active', 1, '2026-05-01 16:11:00', NULL, NULL, '2026-05-01 16:11:00');

-- --------------------------------------------------------

--
-- 表的结构 `outbound_log`
--

CREATE TABLE `outbound_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL COMMENT '出库批次ID',
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL,
  `qty` int(11) NOT NULL COMMENT '出库数量',
  `outbound_price` decimal(10,2) DEFAULT NULL COMMENT '出库价格',
  `live_session_id` int(10) UNSIGNED DEFAULT NULL COMMENT '关联直播场次',
  `order_no` varchar(100) DEFAULT NULL COMMENT '订单号',
  `outbound_batch_no` varchar(20) DEFAULT NULL COMMENT '出库批次号',
  `remark` text,
  `outbound_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='出库记录';

--
-- 转存表中的数据 `outbound_log`
--

INSERT INTO `outbound_log` (`id`, `batch_id`, `product_id`, `condition_type`, `qty`, `outbound_price`, `live_session_id`, `order_no`, `outbound_batch_no`, `remark`, `outbound_at`) VALUES
(1, 1, 6, 'sealed', 1, 42.00, NULL, NULL, NULL, '', '2026-05-01 06:01:49'),
(2, 1, 6, 'sealed', 1, 42.00, NULL, NULL, NULL, '', '2026-05-01 06:17:51'),
(3, 2, 6, 'opened', 1, 40.00, NULL, NULL, NULL, '', '2026-05-01 06:21:09'),
(4, 1, 6, 'sealed', 1, 42.00, NULL, NULL, NULL, '', '2026-05-01 06:21:09'),
(5, 6, 6, 'sealed', 1, 42.00, NULL, NULL, '20260501144402', '', '2026-05-01 06:44:02');

-- --------------------------------------------------------

--
-- 表的结构 `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL COMMENT '商品名称',
  `common_name` varchar(255) DEFAULT NULL COMMENT '常用名称',
  `series` varchar(255) DEFAULT NULL COMMENT '系列名称',
  `barcode` varchar(100) NOT NULL COMMENT '条码',
  `qiandao_price` decimal(10,2) DEFAULT NULL COMMENT '千岛参考价',
  `image_url` varchar(500) DEFAULT NULL COMMENT '图片URL',
  `remark` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品主表';

--
-- 转存表中的数据 `products`
--

INSERT INTO `products` (`id`, `name`, `common_name`, `series`, `barcode`, `qiandao_price`, `image_url`, `remark`, `created_at`, `updated_at`) VALUES
(1, 'LABUBU 秘境夜游', NULL, 'LABUBU', '6901234567001', 299.00, 'uploads/img_20260430214820_69f35da42974f.png', NULL, '2026-04-30 12:53:53', '2026-04-30 13:48:21'),
(2, 'SKULLPANDA 黑管家', NULL, 'SKULLPANDA', '6901234567002', 259.00, 'uploads/img_20260501150739_69f4513bbd7fc.jpg', NULL, '2026-04-30 12:53:53', '2026-05-01 07:07:41'),
(3, 'HIRONO 周末日常', NULL, 'HIRONO', '6901234567003', 189.00, 'uploads/img_20260501150747_69f45143c10ec.jpg', NULL, '2026-04-30 12:53:53', '2026-05-01 07:07:49'),
(4, 'MOLLY 职业系列', NULL, 'MOLLY', '6901234567004', 159.00, 'uploads/img_20260501150755_69f4514b961cc.jpg', NULL, '2026-04-30 12:53:53', '2026-05-01 07:07:56'),
(5, 'DIMOO 海岸线', NULL, 'DIMOO', '6901234567005', 219.00, 'uploads/img_20260501150803_69f451531269d.jpg', NULL, '2026-04-30 12:53:53', '2026-05-01 07:08:04'),
(6, '535486', '姥爷', NULL, '535486', NULL, 'uploads/img_20260430214614_69f35d2665f54.png', NULL, '2026-04-30 13:46:19', '2026-05-01 14:59:18'),
(7, '时间使者', NULL, NULL, '123456', NULL, 'uploads/img_20260501013707_69f39343cc995.jpg', NULL, '2026-04-30 18:00:10', '2026-04-30 18:00:10');

-- --------------------------------------------------------

--
-- 表的结构 `purchase_log`
--

CREATE TABLE `purchase_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL COMMENT '状态类型',
  `purchase_price` decimal(10,2) NOT NULL COMMENT '采购单价',
  `qty` int(11) NOT NULL DEFAULT '1' COMMENT '采购数量',
  `supplier` varchar(255) DEFAULT NULL COMMENT '供应商',
  `remark` text COMMENT '备注',
  `purchased_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '采购时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='采购记录表';

--
-- 转存表中的数据 `purchase_log`
--

INSERT INTO `purchase_log` (`id`, `product_id`, `condition_type`, `purchase_price`, `qty`, `supplier`, `remark`, `purchased_at`) VALUES
(1, 6, 'sealed', 1.00, 199, NULL, NULL, '2026-04-30 13:47:09'),
(2, 6, 'sealed', 35.00, 5, '千岛', NULL, '2026-04-30 14:56:49'),
(3, 6, 'opened', 28.00, 2, '老二', NULL, '2026-04-30 14:57:14'),
(4, 1, 'boxless', 2.00, 1, NULL, NULL, '2026-04-30 14:57:30'),
(5, 6, 'sealed', 40.00, 1, NULL, NULL, '2026-04-30 15:06:57'),
(6, 6, 'flawed', 12.00, 1, NULL, NULL, '2026-04-30 15:43:29'),
(7, 6, 'sealed', 35.00, 10, NULL, NULL, '2026-05-01 06:42:19'),
(8, 5, 'sealed', 100.00, 10, NULL, NULL, '2026-05-01 12:21:07'),
(9, 5, 'opened', 80.00, 10, NULL, NULL, '2026-05-01 12:21:07'),
(10, 5, 'boxless', 60.00, 10, NULL, NULL, '2026-05-01 12:21:07'),
(11, 5, 'flawed', 40.00, 10, NULL, NULL, '2026-05-01 12:21:08'),
(12, 6, 'boxless', 32.00, 10, NULL, NULL, '2026-05-01 16:10:37');

-- --------------------------------------------------------

--
-- 表的结构 `sales_log`
--

CREATE TABLE `sales_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `condition_type` enum('sealed','opened','boxless','flawed') NOT NULL COMMENT '状态类型',
  `sale_price` decimal(10,2) NOT NULL COMMENT '实际售价',
  `qty` int(11) NOT NULL DEFAULT '1' COMMENT '销售数量',
  `live_session_id` int(10) UNSIGNED DEFAULT NULL COMMENT '直播场次ID',
  `sold_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '销售时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销售记录表';

--
-- 转存表中的数据 `sales_log`
--

INSERT INTO `sales_log` (`id`, `product_id`, `condition_type`, `sale_price`, `qty`, `live_session_id`, `sold_at`) VALUES
(9, 6, 'sealed', 43.00, 1, 7, '2026-04-30 16:23:28'),
(10, 6, 'flawed', 15.00, 1, 7, '2026-04-30 16:27:07'),
(11, 6, 'opened', 40.00, 1, 7, '2026-04-30 16:27:07'),
(12, 6, 'opened', 40.00, 1, 7, '2026-04-30 16:27:14'),
(13, 6, 'sealed', 43.00, 1, 7, '2026-04-30 16:30:43'),
(14, 6, 'opened', 40.00, 1, 7, '2026-04-30 16:30:43'),
(15, 6, 'sealed', 43.00, 1, 8, '2026-05-01 16:02:52'),
(16, 6, 'sealed', 43.00, 1, 8, '2026-05-01 16:08:02'),
(17, 6, 'sealed', 43.00, 1, 9, '2026-05-01 17:03:53');

-- --------------------------------------------------------

--
-- 表的结构 `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL COMMENT '配置键',
  `setting_value` text COMMENT '配置值',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

--
-- 转存表中的数据 `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'system_name', '直播销售系统', '2026-05-01 17:04:55'),
(2, 'condition_types', '[{\"key\":\"sealed\",\"name\":\"原盒未拆\",\"color\":\"#10b981\"},{\"key\":\"opened\",\"name\":\"拆盒无瑕\",\"color\":\"#3b82f6\"},{\"key\":\"boxless\",\"name\":\"无盒无瑕\",\"color\":\"#f59e0b\"},{\"key\":\"flawed\",\"name\":\"微瑕\",\"color\":\"#ef4444\"}]', '2026-05-01 17:04:55'),
(3, 'live_display', '{\"elements\":[{\"type\":\"image\",\"enabled\":true,\"left\":143.08361204013372,\"top\":217.39799331103703,\"width\":500,\"height\":500,\"fontSize\":\"0px\",\"zIndex\":1},{\"type\":\"productName\",\"enabled\":true,\"left\":700,\"top\":20,\"width\":300,\"height\":60,\"fontSize\":\"73px\",\"zIndex\":2},{\"type\":\"commonName\",\"enabled\":true,\"left\":1000,\"top\":20,\"width\":300,\"height\":50,\"fontSize\":\"68px\",\"zIndex\":2},{\"type\":\"suggestedPrice\",\"enabled\":true,\"left\":1070,\"top\":120,\"width\":500,\"height\":10,\"fontSize\":\"60px\",\"zIndex\":1},{\"type\":\"condition\",\"enabled\":true,\"left\":1000,\"top\":230,\"width\":550,\"height\":10,\"fontSize\":\"30px\",\"zIndex\":2,\"itemSpacing\":20}],\"containerWidth\":\"100%\",\"containerPadding\":\"20px\"}', '2026-05-01 17:04:55');

-- --------------------------------------------------------

--
-- 替换视图以便查看 `v_inventory_summary`
-- （参见下面的实际视图）
--
CREATE TABLE `v_inventory_summary` (
`product_id` int(10) unsigned
,`condition_type` enum('sealed','opened','boxless','flawed')
,`total_stock` decimal(32,0)
,`avg_cost` decimal(43,2)
,`latest_suggested_price` decimal(10,2)
);

--
-- 转储表的索引
--

--
-- 表的索引 `broadcast_messages`
--
ALTER TABLE `broadcast_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `inventory_backup`
--
ALTER TABLE `inventory_backup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_product_condition` (`product_id`,`condition_type`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- 表的索引 `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_condition` (`product_id`,`condition_type`),
  ADD KEY `idx_batch_no` (`batch_no`);

--
-- 表的索引 `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `live_inventory`
--
ALTER TABLE `live_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_session_product_condition` (`live_session_id`,`product_id`,`condition_type`),
  ADD KEY `idx_session` (`live_session_id`),
  ADD KEY `fk_live_inv_product` (`product_id`);

--
-- 表的索引 `live_sessions`
--
ALTER TABLE `live_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started_at` (`started_at`);

--
-- 表的索引 `outbound_log`
--
ALTER TABLE `outbound_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_outbound_at` (`outbound_at`),
  ADD KEY `idx_outbound_batch_no` (`outbound_batch_no`);

--
-- 表的索引 `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_barcode` (`barcode`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_series` (`series`);

--
-- 表的索引 `purchase_log`
--
ALTER TABLE `purchase_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_purchased_at` (`purchased_at`);

--
-- 表的索引 `sales_log`
--
ALTER TABLE `sales_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_live_session_id` (`live_session_id`),
  ADD KEY `idx_sold_at` (`sold_at`);

--
-- 表的索引 `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `broadcast_messages`
--
ALTER TABLE `broadcast_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- 使用表AUTO_INCREMENT `inventory_backup`
--
ALTER TABLE `inventory_backup`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- 使用表AUTO_INCREMENT `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- 使用表AUTO_INCREMENT `inventory_log`
--
ALTER TABLE `inventory_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- 使用表AUTO_INCREMENT `live_inventory`
--
ALTER TABLE `live_inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- 使用表AUTO_INCREMENT `live_sessions`
--
ALTER TABLE `live_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `outbound_log`
--
ALTER TABLE `outbound_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用表AUTO_INCREMENT `purchase_log`
--
ALTER TABLE `purchase_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- 使用表AUTO_INCREMENT `sales_log`
--
ALTER TABLE `sales_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- 使用表AUTO_INCREMENT `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

-- --------------------------------------------------------

--
-- 视图结构 `inventory`
--
DROP TABLE IF EXISTS `inventory`;

CREATE ALGORITHM=UNDEFINED DEFINER=`ppmart`@`localhost` SQL SECURITY DEFINER VIEW `inventory`  AS SELECT NULL AS `id`, `ib`.`product_id` AS `product_id`, `ib`.`condition_type` AS `condition_type`, (select `ib2`.`purchase_price` from `inventory_batches` `ib2` where ((`ib2`.`product_id` = `ib`.`product_id`) and (`ib2`.`condition_type` = `ib`.`condition_type`)) order by `ib2`.`purchased_at` desc limit 1) AS `purchase_price`, `ib`.`latest_suggested_price` AS `suggested_price`, `ib`.`total_stock` AS `stock_qty` FROM `v_inventory_summary` AS `ib` ;

-- --------------------------------------------------------

--
-- 视图结构 `v_inventory_summary`
--
DROP TABLE IF EXISTS `v_inventory_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`ppmart`@`localhost` SQL SECURITY DEFINER VIEW `v_inventory_summary`  AS SELECT `inventory_batches`.`product_id` AS `product_id`, `inventory_batches`.`condition_type` AS `condition_type`, sum(`inventory_batches`.`remaining_qty`) AS `total_stock`, round((sum((`inventory_batches`.`purchase_price` * `inventory_batches`.`remaining_qty`)) / nullif(sum(`inventory_batches`.`remaining_qty`),0)),2) AS `avg_cost`, max(`inventory_batches`.`suggested_price`) AS `latest_suggested_price` FROM `inventory_batches` WHERE (`inventory_batches`.`remaining_qty` > 0) GROUP BY `inventory_batches`.`product_id`, `inventory_batches`.`condition_type` ;

--
-- 限制导出的表
--

--
-- 限制表 `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `fk_batch_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- 限制表 `live_inventory`
--
ALTER TABLE `live_inventory`
  ADD CONSTRAINT `fk_live_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_live_inv_session` FOREIGN KEY (`live_session_id`) REFERENCES `live_sessions` (`id`) ON DELETE CASCADE;

--
-- 限制表 `outbound_log`
--
ALTER TABLE `outbound_log`
  ADD CONSTRAINT `fk_outbound_batch` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`) ON DELETE CASCADE;

--
-- 限制表 `sales_log`
--
ALTER TABLE `sales_log`
  ADD CONSTRAINT `fk_sales_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sales_session` FOREIGN KEY (`live_session_id`) REFERENCES `live_sessions` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
