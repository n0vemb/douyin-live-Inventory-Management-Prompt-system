-- ============================================================
-- PPMart 仓库货架系统（货架分布查询 + 出库台拣货升级）
-- Version: 11.0 (Warehouse Racks)
-- 布局约定：默认 5 层 × 5 大格（每大格 = 2 小格，1/2 大格宽），
-- 商品可占 1 小格（半大格，span=1）或 1 大格（span=2）；所有格子等高。
-- 布局不写死：stores.rack_layout 存 JSON {rows, big_cols}，NULL 时默认 5×5。
-- ============================================================

-- 店铺级货架布局配置（每店铺可不同，NULL=默认 5×5）
ALTER TABLE stores ADD COLUMN rack_layout TEXT DEFAULT NULL COMMENT '货架布局 JSON {rows,big_cols}，NULL=默认5层×5大格' AFTER live_display;

-- 货架表
CREATE TABLE IF NOT EXISTS warehouse_racks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1 COMMENT '所属店铺',
    code VARCHAR(50) NOT NULL COMMENT '货架号，如 A / A3 / B',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '显示顺序',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rack_store_code (store_id, code),
    KEY idx_racks_store (store_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 货架格子表（pos_no 为小格位 1-10，每 2 个小格 = 1 大格）
CREATE TABLE IF NOT EXISTS warehouse_rack_cells (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL DEFAULT 1 COMMENT '所属店铺',
    rack_id INT NOT NULL COMMENT '所属货架',
    row_no TINYINT NOT NULL COMMENT '层 1-5',
    pos_no TINYINT NOT NULL COMMENT '小格位 1-10',
    span TINYINT NOT NULL DEFAULT 1 COMMENT '占格数 1=半大格 2=整大格',
    product_id INT UNSIGNED DEFAULT NULL COMMENT '关联商品；NULL=空格',
    note VARCHAR(255) DEFAULT NULL COMMENT '备注',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cell (rack_id, row_no, pos_no),
    KEY idx_cells_store (store_id),
    KEY idx_cells_product (product_id),
    CONSTRAINT fk_cells_rack FOREIGN KEY (rack_id) REFERENCES warehouse_racks(id) ON DELETE CASCADE,
    CONSTRAINT fk_cells_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
