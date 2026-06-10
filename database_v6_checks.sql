-- ============================================================
-- PPMart 数据库约束：防止负库存（MySQL 5.7 用触发器实现）
-- MySQL 8.0.16+ 可改用 CHECK(remaining_qty >= 0) 替代
-- ============================================================

-- 1. inventory_batches.remaining_qty 不能为负
-- ============================================================
DROP TRIGGER IF EXISTS trg_inventory_batches_check_update;
DELIMITER //
CREATE TRIGGER trg_inventory_batches_check_update
BEFORE UPDATE ON `inventory_batches`
FOR EACH ROW
BEGIN
    IF NEW.remaining_qty < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '库存不足：remaining_qty 不能为负数';
    END IF;
END//
DELIMITER ;

-- 2. live_inventory.current_stock 不能为负
-- ============================================================
DROP TRIGGER IF EXISTS trg_live_inventory_check_update;
DELIMITER //
CREATE TRIGGER trg_live_inventory_check_update
BEFORE UPDATE ON `live_inventory`
FOR EACH ROW
BEGIN
    IF NEW.current_stock < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '库存不足：current_stock 不能为负数';
    END IF;
END//
DELIMITER ;
