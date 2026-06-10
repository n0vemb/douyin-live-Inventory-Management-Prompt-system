-- 将 condition_type 从 ENUM 改为 VARCHAR(50)，支持自定义 SKU 类型
-- 需要先删除依赖该列的视图，再重建

DROP VIEW IF EXISTS inventory;
DROP VIEW IF EXISTS v_inventory_summary;

ALTER TABLE inventory_backup MODIFY condition_type VARCHAR(50) NOT NULL;
ALTER TABLE inventory_batches MODIFY condition_type VARCHAR(50) NOT NULL;
ALTER TABLE inventory_log MODIFY condition_type VARCHAR(50) NOT NULL;
ALTER TABLE live_inventory MODIFY condition_type VARCHAR(50) NOT NULL;
ALTER TABLE outbound_log MODIFY condition_type VARCHAR(50) NOT NULL;
ALTER TABLE purchase_log MODIFY condition_type VARCHAR(50) NOT NULL;
ALTER TABLE sales_log MODIFY condition_type VARCHAR(50) NOT NULL;

CREATE VIEW v_inventory_summary AS
SELECT
    product_id,
    condition_type,
    SUM(remaining_qty) AS total_stock,
    ROUND(SUM(purchase_price * remaining_qty) / NULLIF(SUM(remaining_qty), 0), 2) AS avg_cost,
    MAX(suggested_price) AS latest_suggested_price
FROM inventory_batches
WHERE remaining_qty > 0
GROUP BY product_id, condition_type;

CREATE VIEW inventory AS
SELECT NULL AS id,
       ib.product_id,
       ib.condition_type,
       (SELECT ib2.purchase_price
        FROM inventory_batches ib2
        WHERE ib2.product_id = ib.product_id
          AND ib2.condition_type = ib.condition_type
        ORDER BY ib2.purchased_at DESC
        LIMIT 1) AS purchase_price,
       ib.latest_suggested_price AS suggested_price,
       ib.total_stock AS stock_qty
FROM v_inventory_summary ib;
