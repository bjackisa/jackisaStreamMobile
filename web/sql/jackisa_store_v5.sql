-- ============================================================================
--  Jackisa Store - JPesa service fee (v5)
--  Records the mobile-money service fee percentage the Store passes on to the
--  buyer, matching the wallet top-up gross-up (/0.97). Additive and idempotent;
--  never edits the earlier migrations.
-- ============================================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

INSERT INTO `config` (`name`,`value`) SELECT 'store_jpesa_fee_percent','3' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_jpesa_fee_percent');
