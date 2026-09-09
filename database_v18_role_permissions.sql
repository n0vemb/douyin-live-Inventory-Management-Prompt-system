-- ============================================================
-- PPMart 角色细粒度权限
-- Version: 18.0 (Role Permissions)
-- role_permissions：按 角色+权限点 覆盖系统默认值（不写=用代码默认）
-- 后续可扩展 users.custom_role / roles 表支持自定义角色
-- ============================================================

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role`       VARCHAR(40) NOT NULL,
    `perm`       VARCHAR(80) NOT NULL,
    `allowed`    TINYINT NOT NULL DEFAULT 1 COMMENT '1=允许 0=拒绝',
    `updated_by` VARCHAR(100) DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`role`, `perm`),
    KEY `idx_rp_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色细粒度权限覆盖';
