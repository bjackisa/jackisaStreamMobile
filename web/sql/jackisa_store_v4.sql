-- ============================================================================
--  Jackisa Store - fulfilment status columns (v4)
--  Adds the shipped_at / delivered_at timestamps used by the seller fulfilment
--  workflow (pending -> confirmed -> approved -> shipped -> delivered -> paid ->
--  closed). Additive and idempotent; never edits the earlier migrations.
-- ============================================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

SET @ddl = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_orders' AND COLUMN_NAME = 'shipped_at') = 0,
  'ALTER TABLE `store_orders` ADD COLUMN `shipped_at` int(11) NOT NULL DEFAULT 0',
  'SELECT 1'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_orders' AND COLUMN_NAME = 'delivered_at') = 0,
  'ALTER TABLE `store_orders` ADD COLUMN `delivered_at` int(11) NOT NULL DEFAULT 0',
  'SELECT 1'));
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
