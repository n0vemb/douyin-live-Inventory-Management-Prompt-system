-- ============================================================
-- PPMart 集团-店铺 两级组织 数据库迁移（v20 草案，待评审）
-- ============================================================
-- 背景：一个集团 = 现有 stores 的一行（客户/租户）。
--   集团下辖多个店（shops，如 A店/B店），各店有自己店管和运营团队。
--
-- 已确认的业务边界：
--   商品档案 / 实物库存 / 批次 / 货架 / 盘点   -> 集团共享（本脚本不改动）
--   直播场次 / 销售 / 出库 / 财务             -> 按店隔离（加 shop_id）
--      场次对店间互不可见：A店管/A店运营看不到 B店场次（列表/详情/台账）
--      集团管理员可跨店看场次汇总
--      进行中场次规则：一个店可同时开多个 active 场次（旧链路与台账一致）
--   待办 / 交接                              -> 按店隔离（同店可见可派）
--   集团管理员(总部)                          -> 可跨店看汇总；待办只读不派
--   仓库账号                                 -> 集团级（一个总部仓，两店订单都可见）
--
-- 存量兼容：每个已有集团自动生成一个“默认店”，历史业务数据归入默认店，
--   行为与现在完全一致；新集团可先开默认店，再开 A店/B店。
--
-- ⚠ 本文件是设计草案，未在测试库执行过：
--   1) 执行前在测试库核对实际表结构（列名/索引名/外键约束名）；
--   2) 遵循既有“备份 -> 执行 -> 验证”流程，勿直接在生产执行；
--   3) live_sessions 为旧直播链路，live_ledger_session 为台账链路，
--      两条链路都加 shop_id，实现阶段确认哪条仍在生产使用。
-- ============================================================

-- ------------------------------------------------------------
-- 1. shops：店（业务核算单元，挂在集团 store_id 下）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shops` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `store_id`   INT NOT NULL COMMENT '所属集团(=现有stores.id)',
    `name`       VARCHAR(255) NOT NULL COMMENT '店名，如 A店/B店/默认店',
    `remark`     VARCHAR(500) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_shop_name_in_store` (`store_id`, `name`),
    KEY `idx_shop_store` (`store_id`),
    CONSTRAINT `fk_shop_store` FOREIGN KEY (`store_id`) REFERENCES `stores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='店：集团下的业务核算单元';

-- ------------------------------------------------------------
-- 2. 为每个已有集团补一个“默认店”，供历史数据回填
-- ------------------------------------------------------------
INSERT INTO `shops` (`store_id`, `name`, `remark`)
SELECT s.`id`, '默认店', '存量数据自动归属店'
FROM `stores` s
WHERE NOT EXISTS (
    SELECT 1 FROM `shops` x
    WHERE x.`store_id` = s.`id` AND x.`name` = '默认店'
);

-- ------------------------------------------------------------
-- 3. users：加店维度 + 新角色 group_admin
-- ------------------------------------------------------------
-- 归属规则：
--   store_admin / deputy_store_admin / operator  -> 必填 shop_id（店管/副店长/运营）
--   warehouse / group_admin / super_admin       -> shop_id 为 NULL（集团/平台级）
ALTER TABLE `users`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店ID；店管/副店长/运营必填，集团级角色为NULL' AFTER `store_id`;

ALTER TABLE `users`
    ADD INDEX `idx_users_shop` (`shop_id`),
    ADD CONSTRAINT `fk_user_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- 注：fk_user_shop 用 CASCADE 与 users→stores 的级联保持一致（删整个集团时账号随删）；
--     单独删“店”前，api/delete_shop.php 必须先校验该店无账号/无业务数据，避免误删账号。

-- 存量账号：店管/副店长/运营归入本集团默认店；仓库/超管保持集团级(NULL)
UPDATE `users` u
JOIN `shops` d ON d.`store_id` = u.`store_id` AND d.`name` = '默认店'
SET u.`shop_id` = d.`id`
WHERE u.`shop_id` IS NULL
  AND u.`role` IN ('store_admin', 'deputy_store_admin', 'operator');

-- 角色枚举追加 group_admin（追加在末尾，不改变既有枚举序号）
ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('super_admin','store_admin','operator','deputy_store_admin','warehouse','group_admin')
    NOT NULL DEFAULT 'store_admin'
    COMMENT '角色：超管/店管(店级)/运营(店级)/副店长(店级)/仓库(集团级)/集团管理员(集团级)';

-- ------------------------------------------------------------
-- 4. 业务数据加 shop_id（按店隔离的链路）
-- ------------------------------------------------------------
-- 说明：店级数据的删除跟随外键 CASCADE（与现状“删店连删数据”一致）；
--       单独删店由 API 层先做账号/数据校验，外键 CASCADE 只作为删整个集团时的兜底。

-- 4.1 旧直播链路：场次/直播库存
ALTER TABLE `live_sessions`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店；NULL=待回填' AFTER `store_id`,
    ADD INDEX `idx_live_sessions_shop` (`shop_id`),
    ADD CONSTRAINT `fk_live_sessions_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

ALTER TABLE `live_inventory`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店（冗余，随场次）' AFTER `store_id`,
    ADD INDEX `idx_live_inventory_shop` (`shop_id`);

-- 4.2 台账链路：场次
ALTER TABLE `live_ledger_session`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店；NULL=待回填' AFTER `store_id`,
    ADD INDEX `idx_ledger_session_shop` (`shop_id`),
    ADD CONSTRAINT `fk_ledger_session_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- 4.3 销售记录
ALTER TABLE `sales_log`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店（落单时盖章，报表不靠JOIN推导）' AFTER `store_id`,
    ADD INDEX `idx_sales_log_shop` (`shop_id`),
    ADD CONSTRAINT `fk_sales_log_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- 4.4 出库明细
ALTER TABLE `outbound_log`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店（随场次/结算单盖章）' AFTER `store_id`,
    ADD INDEX `idx_outbound_log_shop` (`shop_id`),
    ADD CONSTRAINT `fk_outbound_log_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- 4.5 出库财务（对账结算）
ALTER TABLE `outbound_finance`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店（一个出库批次只归属一个店结算）' AFTER `store_id`,
    ADD INDEX `idx_outbound_finance_shop` (`shop_id`),
    ADD CONSTRAINT `fk_outbound_finance_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- 唯一键从 (batch_no, store_id) 扩为 (batch_no, store_id, shop_id)
ALTER TABLE `outbound_finance`
    DROP INDEX `uk_batch_store`,
    ADD UNIQUE KEY `uk_batch_store_shop` (`outbound_batch_no`, `store_id`, `shop_id`);

-- 4.6 仓库出库台任务（冗余 shop_id：仓库台按集团汇总、按店显示来源）
ALTER TABLE `warehouse_task`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '来源店（随场次盖章）' AFTER `store_id`,
    ADD INDEX `idx_warehouse_task_shop` (`shop_id`),
    ADD CONSTRAINT `fk_warehouse_task_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- 4.7 待办（同店传递：可见/可派仅限同店；集团管理员只读跨店视图）
ALTER TABLE `todo_items`
    ADD COLUMN `shop_id` INT DEFAULT NULL COMMENT '所属店；同店可见可派' AFTER `store_id`,
    ADD INDEX `idx_todo_items_shop` (`shop_id`),
    ADD CONSTRAINT `fk_todo_items_shop` FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE;

-- ------------------------------------------------------------
-- 5. 历史数据回填：全部归入本集团“默认店”
-- ------------------------------------------------------------
UPDATE `live_sessions` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `live_inventory` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `live_ledger_session` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `sales_log` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `outbound_log` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `outbound_finance` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `warehouse_task` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

UPDATE `todo_items` t
JOIN `shops` d ON d.`store_id` = t.`store_id` AND d.`name` = '默认店'
SET t.`shop_id` = d.`id`
WHERE t.`shop_id` IS NULL;

-- 回填确认无 NULL 后可收紧 NOT NULL（建议逐表验证后分步执行，勿一次全跑）
-- ⚠ outbound_log / sales_log 收紧前必须先确认所有写入路径都盖章 shop_id：
--   POS / 旧 outbound_batch 等非直播链路暂未定店归属，保持 NULL 即可，
--   直播台账链路（live_ledger_save/end、sell_product_live）已盖章。
-- ALTER TABLE `live_sessions`       MODIFY `shop_id` INT NOT NULL;
-- ALTER TABLE `live_ledger_session` MODIFY `shop_id` INT NOT NULL;
-- ALTER TABLE `sales_log`           MODIFY `shop_id` INT NOT NULL;
-- ALTER TABLE `outbound_log`        MODIFY `shop_id` INT NOT NULL;
-- ALTER TABLE `outbound_finance`    MODIFY `shop_id` INT NOT NULL;
-- ALTER TABLE `warehouse_task`      MODIFY `shop_id` INT NOT NULL;
-- ALTER TABLE `todo_items`          MODIFY `shop_id` INT NOT NULL;

-- ------------------------------------------------------------
-- 6. 权限与代码侧待办（不在本脚本内）
-- ------------------------------------------------------------
-- a) auth.php defaultPermMap 增加 group_admin 默认值：
--    finance.report / finance.view_cost / user.manage(集团内) / todo.cross_shop_view(只读)
-- b) 新权限点 todo.cross_shop_view：集团管理员可跨店看待办、不可增派/完成
--    （增派/完成仍受 users.shop_id 作用域限制）
-- c) 超管会话加 view_shop_id（在 view_store_id 内再选店）
-- d) 新建 API/页面：
--    admin/shops.php + api/list_shops.php + api/create_shop.php + api/update_shop.php
--    api/delete_shop.php（删除前校验无店级数据/账号，有则拒绝并提示先迁移）
-- e) 店级作用域 Helper：getShopId()，规则
--    super_admin -> view_shop_id（可空=看全集团）
--    group_admin / warehouse -> NULL（集团级）
--    store_admin / deputy_store_admin / operator -> users.shop_id
-- f) 直播场次创建/列表、销售、出库、财务、待办各 API 均按 getShopId() 过滤；
--    场次归属店在创建时写入并盖章到销售/出库明细。
--    场次列表/详情/台账按店隔离（店间互不可见）。
--    场次创建不结束其它进行中场次（同店可多场次并行）。
-- g) POS / 优惠券 / VIP 会员的作用域是否也按店，业务确认后单独迁移，不混入本脚本。
