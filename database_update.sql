-- 添加新商品属性字段
ALTER TABLE `products` 
ADD COLUMN `brand` varchar(255) DEFAULT NULL COMMENT '品牌' AFTER `series`,
ADD COLUMN `release_date` date DEFAULT NULL COMMENT '发售时间' AFTER `brand`,
ADD COLUMN `product_description` text DEFAULT NULL COMMENT '产品介绍' AFTER `release_date`;
