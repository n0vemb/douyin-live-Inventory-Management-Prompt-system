-- ============================================================
-- PPMart 仓库货架：格子盘点留痕（24h 绿框依据）
-- Version: 19.0 (Rack Cell Audit)
-- 规则：一格盘完（与线上一致，或有差异调整后）即算“盘过”；
-- 货架页查询最近 24h 的盘点记录，同格同商品显示绿框。
-- 记录按格追加（保留历史）；前端只取每格最近一次。
-- ============================================================

CREATE TABLE IF NOT EXISTS rack_cell_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1 COMMENT '所属店铺',
    rack_id INT NOT NULL COMMENT '所属货架',
    row_no TINYINT NOT NULL COMMENT '层',
    pos_no TINYINT NOT NULL COMMENT '小格位',
    product_id INT NOT NULL COMMENT '盘点时该格商品',
    result VARCHAR(20) NOT NULL DEFAULT 'same' COMMENT 'same=与线上一致 adjusted=有差异已调整',
    user_id INT DEFAULT NULL COMMENT '盘点人',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cell_recent (store_id, rack_id, row_no, pos_no, created_at),
    KEY idx_product_recent (store_id, product_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
