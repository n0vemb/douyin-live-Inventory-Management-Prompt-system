-- ============================================================
-- PPMart 角色：新增副店长 deputy_store_admin
-- Version: 17.0 (Deputy Store Admin)
-- ============================================================

ALTER TABLE `users`
    MODIFY COLUMN `role` ENUM('super_admin','store_admin','operator','deputy_store_admin','warehouse')
    NOT NULL DEFAULT 'store_admin' COMMENT '角色：超管/店管/运营/副店长/仓库';
