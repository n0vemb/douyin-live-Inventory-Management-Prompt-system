-- ============================================================
-- PPMart 仓库货架：未上架商品盘点留痕（临时货架 24h 绿底依据）
-- Version: 28.0 (Unplaced Product Audit)
-- 未上架但有库存的商品没有货架格位，盘点按「商品」记录；
-- 货架页临时货架读取最近 24h 记录显示绿底（与货架格一致）。
-- 记录追加保存（保留历史），前端只取每个商品最近一次。
-- ============================================================

CREATE TABLE IF NOT EXISTS product_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1 COMMENT '所属店铺',
    product_id INT NOT NULL COMMENT '盘点商品',
    result VARCHAR(20) NOT NULL DEFAULT 'same' COMMENT 'same=与线上一致 adjusted=有差异已调整',
    user_id INT DEFAULT NULL COMMENT '盘点人',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product_recent (store_id, product_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
