-- ============================================================
-- PPMart 仓库货架：每架独立布局（层数 / 每层大格数）
-- Version: 14.0 (Per-Rack Layout)
-- 之前布局存店铺级 stores.rack_layout，所有货架同尺寸；
-- 现改为每个货架可不同（匹配不同类型货架），新建货架时手动设置。
-- 历史数据按店铺原 rack_layout 回填，无配置默认 5×5。
-- ============================================================

ALTER TABLE `warehouse_racks`
    ADD COLUMN `row_count` TINYINT NOT NULL DEFAULT 5 COMMENT '货架层数' AFTER `sort_order`,
    ADD COLUMN `big_col_count` TINYINT NOT NULL DEFAULT 5 COMMENT '每层大格数' AFTER `row_count`;

UPDATE `warehouse_racks` wr
LEFT JOIN `stores` s ON s.id = wr.store_id
SET
    wr.row_count     = COALESCE(NULLIF(CAST(JSON_EXTRACT(s.rack_layout, '$.rows') AS UNSIGNED), 0), 5),
    wr.big_col_count = COALESCE(NULLIF(CAST(JSON_EXTRACT(s.rack_layout, '$.big_cols') AS UNSIGNED), 0), 5);
