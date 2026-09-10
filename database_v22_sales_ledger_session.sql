-- ============================================================
-- PPMart v22：销售记录关联台账场次（支持场次改归属店时联动销售记录）
-- ============================================================
-- 背景：台账出库时 sales_log.live_session_id 置 NULL（外键指向旧 live_sessions），
--   导致场次改店时无法定位其销售记录。新增 ledger_session_id 并回填。
-- ⚠ 执行前备份；回填只处理“能唯一匹配到一场台账场次”的记录。

ALTER TABLE `sales_log`
    ADD COLUMN `ledger_session_id` INT DEFAULT NULL COMMENT '台账场次ID（live_ledger_session.id）' AFTER `live_session_id`,
    ADD INDEX `idx_sales_ledger_session` (`ledger_session_id`);

-- 回填：用该场次出库明细(outbound_log)的 商品/品相/批次/数量/单价 + 时间(±5s) 唯一匹配
UPDATE `sales_log` s
JOIN (
    SELECT s2.`id` AS sid, MIN(o.`live_session_id`) AS lsid
    FROM `sales_log` s2
    JOIN `outbound_log` o
      ON o.`store_id` = s2.`store_id`
     AND o.`batch_id` = s2.`batch_id`
     AND o.`product_id` = s2.`product_id`
     AND o.`condition_type` = s2.`condition_type`
     AND o.`qty` = s2.`qty`
     AND ABS(o.`outbound_price` - s2.`sale_price`) < 0.01
     AND o.`live_session_id` IS NOT NULL
     AND ABS(TIMESTAMPDIFF(SECOND, o.`outbound_at`, s2.`sold_at`)) <= 5
    WHERE s2.`live_session_id` IS NULL
    GROUP BY s2.`id`
    HAVING COUNT(DISTINCT o.`live_session_id`) = 1
) m ON m.sid = s.`id`
SET s.`ledger_session_id` = m.lsid;
