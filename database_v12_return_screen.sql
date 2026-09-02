-- ============================================================
-- PPMart 直播返送实时库存查询支持
-- Version: 12.0 (Return Screen)
-- live_ledger_item 增加 (product_id, condition_type) 复合索引，
-- 支撑返送屏「真实库存 − 进行中场次已录商品」的实时聚合查询
-- ============================================================

ALTER TABLE `live_ledger_item`
    ADD INDEX idx_lli_product_condition (`product_id`, `condition_type`);
