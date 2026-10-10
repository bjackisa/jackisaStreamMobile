-- ============================================================================
--  Jackisa Store - industry-ready upgrade migration (v2)
--  ---------------------------------------------------------------------------
--  This file ADDS to the Jackisa Store introduced in sql/jackisa_store.sql.
--  It never edits the earlier migration. Like the original it only adds
--  tables, additive columns (guarded) and seed data, and is safe to run more
--  than once.
--
--  What it adds:
--    * store verification (legal store vs personal store) with documents
--    * locations, delivery zones and pickup points (system-priced delivery)
--    * category-specific attributes and a catalogue of subscriber add-ons
--    * system-generated, globally unique SKUs
--    * tax-inclusive pricing support
--    * product/service attributes and variants enrichment
--    * admin rejection reasons
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================================
--  1. LOCATIONS (regions / districts / cities) - shared reference data
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_locations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  -- country | region | district | city | area
  `type` varchar(20) NOT NULL DEFAULT 'district',
  `name` varchar(150) NOT NULL DEFAULT '',
  `slug` varchar(160) NOT NULL DEFAULT '',
  -- ISO country code, useful for delivery pricing lookups
  `country_code` varchar(4) NOT NULL DEFAULT 'UG',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_locations_slug` (`slug`),
  KEY `idx_store_locations_parent` (`parent_id`),
  KEY `idx_store_locations_type` (`type`,`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  2. DELIVERY ZONES AND PICKUP POINTS
--  ---------------------------------------------------------------------------
--  A seller defines zones per location (region/district) with a base fee, a
--  per-kilogram component and a minimum. The engine prices delivery from the
--  buyer's chosen location + the order weight, on top of the mode (deliver vs
--  pickup vs service at the customer vs at the provider).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_delivery_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `label` varchar(150) NOT NULL DEFAULT '',
  `base_fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `per_kg_fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `min_fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `max_fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  -- 0 = disabled; free delivery once the line total reaches this amount
  `free_over` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `eta_min_days` int(11) NOT NULL DEFAULT 1,
  `eta_max_days` int(11) NOT NULL DEFAULT 3,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_zone_seller_location` (`seller_id`,`location_id`),
  KEY `idx_store_zones_seller` (`seller_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_pickup_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(150) NOT NULL DEFAULT '',
  `address` varchar(300) NOT NULL DEFAULT '',
  `contact_phone` varchar(50) NOT NULL DEFAULT '',
  `fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_pickups_seller` (`seller_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  3. VERIFICATION DOCUMENTS
--  ---------------------------------------------------------------------------
--  A legal store submits a PDF of its certificate of incorporation / trading
--  licence; a personal store submits a national ID. Kept append-only-ish for
--  the audit trail (no delete guard so an admin can purge on request).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_verification_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  -- business_licence | certificate_of_incorporation | tin_certificate | national_id | other
  `doc_type` varchar(40) NOT NULL DEFAULT 'other',
  `file` varchar(500) NOT NULL DEFAULT '',
  `original_name` varchar(300) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'submitted',
  `note` varchar(500) NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_verif_seller` (`seller_id`),
  KEY `idx_store_verif_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  4. ADD-ON CATALOGUE (subscriber perks picked from a dropdown)
--  ---------------------------------------------------------------------------
--  Sellers never type a free-text add-on. They choose from a catalogue that is
--  scoped by item kind (product / service / both) and category, grouped into
--  sub-categories.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_addon_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL DEFAULT '',
  `slug` varchar(160) NOT NULL DEFAULT '',
  `kind` varchar(20) NOT NULL DEFAULT 'both',
  `description` varchar(300) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_addon_cat_slug` (`slug`),
  KEY `idx_store_addon_cat_kind` (`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  -- 0 = available to every category of the matching kind
  `category_id` int(11) NOT NULL DEFAULT 0,
  `addon_category_id` int(11) NOT NULL DEFAULT 0,
  `kind` varchar(20) NOT NULL DEFAULT 'both',
  `code` varchar(80) NOT NULL DEFAULT '',
  `name` varchar(200) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  -- free | percent | amount | extended
  `value_type` varchar(20) NOT NULL DEFAULT 'free',
  `value` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  -- when set, an "extended" add-on adds this many days to a service or warranty
  `extends_days` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_addon_code` (`code`),
  KEY `idx_store_addons_category` (`category_id`,`kind`),
  KEY `idx_store_addons_group` (`addon_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  5. CATEGORY-SPECIFIC ATTRIBUTE DEFINITIONS
--  ---------------------------------------------------------------------------
--  A description is not enough: each category declares the specs it wants
--  captured (colour, size, material, power, duration, ...). The seller fills
--  them in and they render on the product page.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_attribute_defs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL DEFAULT 0,
  `kind` varchar(20) NOT NULL DEFAULT 'product',
  `field_key` varchar(80) NOT NULL DEFAULT '',
  `label` varchar(150) NOT NULL DEFAULT '',
  -- text | number | select | multiselect | boolean | date
  `field_type` varchar(20) NOT NULL DEFAULT 'text',
  -- JSON array of options for select types
  `options` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT '',
  `placeholder` varchar(150) NOT NULL DEFAULT '',
  `is_required` tinyint(4) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_attr_key` (`category_id`,`field_key`),
  KEY `idx_store_attr_category` (`category_id`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  6. ADMIN REJECTION REASONS (selectable, keeps messaging consistent)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_rejection_reasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  -- seller | product | both
  `scope` varchar(20) NOT NULL DEFAULT 'both',
  `code` varchar(80) NOT NULL DEFAULT '',
  `label` varchar(300) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_reject_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  7. ADDITIVE COLUMNS ON EXISTING STORE TABLES
-- ============================================================================

-- store_sellers: store type, verification workflow and structured location.
SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `store_type` varchar(20) NOT NULL DEFAULT ''personal''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'store_type');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `verification_status` varchar(20) NOT NULL DEFAULT ''unverified''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'verification_status');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `rejection_reason` varchar(500) NOT NULL DEFAULT ''''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'rejection_reason');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `reviewed_at` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'reviewed_at');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `reviewed_by` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'reviewed_by');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `reg_number` varchar(80) NOT NULL DEFAULT ''''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'reg_number');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `tax_id` varchar(80) NOT NULL DEFAULT ''''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'tax_id');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `location_id` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'location_id');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `address` varchar(300) NOT NULL DEFAULT ''''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'address');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `latitude` decimal(11,7) NOT NULL DEFAULT 0.0000000', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'latitude');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `longitude` decimal(11,7) NOT NULL DEFAULT 0.0000000', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'longitude');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `offers_delivery` tinyint(4) NOT NULL DEFAULT 1', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'offers_delivery');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_sellers` ADD COLUMN `offers_pickup` tinyint(4) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_sellers' AND COLUMN_NAME = 'offers_pickup');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

-- store_products: tax-inclusive, delivery mode, weight, structured attributes,
-- and the subscriber add-on reference (chosen from the catalogue, not typed).
SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD COLUMN `tax_inclusive` tinyint(4) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND COLUMN_NAME = 'tax_inclusive');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD COLUMN `delivery_mode` varchar(20) NOT NULL DEFAULT ''deliver''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND COLUMN_NAME = 'delivery_mode');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD COLUMN `service_location_mode` varchar(20) NOT NULL DEFAULT ''''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND COLUMN_NAME = 'service_location_mode');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD COLUMN `weight_grams` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND COLUMN_NAME = 'weight_grams');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD COLUMN `attributes` text DEFAULT NULL', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND COLUMN_NAME = 'attributes');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD COLUMN `member_addon_id` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND COLUMN_NAME = 'member_addon_id');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

-- store_orders: how the buyer chose to receive the order, plus their location.
SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_orders` ADD COLUMN `delivery_mode` varchar(20) NOT NULL DEFAULT ''deliver''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_orders' AND COLUMN_NAME = 'delivery_mode');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_orders` ADD COLUMN `buyer_location_id` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_orders' AND COLUMN_NAME = 'buyer_location_id');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_orders` ADD COLUMN `buyer_address` varchar(500) NOT NULL DEFAULT ''''', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_orders' AND COLUMN_NAME = 'buyer_address');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

-- store_membership_benefits: the subscriber add-on is now a catalogue choice.
SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_membership_benefits` ADD COLUMN `addon_id` int(11) NOT NULL DEFAULT 0', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_membership_benefits' AND COLUMN_NAME = 'addon_id');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

-- Ledger account used to hold tax actually collected and owed onward.
INSERT INTO `store_ledger_accounts` (`code`,`name`,`type`,`is_user_account`,`created_at`)
SELECT '4200', 'Tax Collected (owed)', 'liability', 0, UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `store_ledger_accounts` a WHERE a.code = '4200');

-- ============================================================================
--  8. SYSTEM-GENERATED, GLOBALLY UNIQUE SKUs
--  ---------------------------------------------------------------------------
--  The sku column becomes nullable (a NULL is allowed many times under a
--  unique index, an empty string is not). Existing blank / duplicated SKUs are
--  back-filled with a generated unique value, then a unique index is added.
--  New SKUs are allocated from store_counters('sku').
-- ============================================================================

ALTER TABLE `store_products` MODIFY COLUMN `sku` varchar(80) DEFAULT NULL;

UPDATE `store_products` SET `sku` = CONCAT('PT-P-', LPAD(`id`, 8, '0'))
WHERE `sku` = '' OR `sku` IS NULL;

-- De-duplicate any pre-existing repeated SKUs (keep the lowest id per value).
UPDATE `store_products` p
JOIN (
  SELECT `sku`, MIN(`id`) AS keep_id
  FROM `store_products`
  WHERE `sku` IS NOT NULL AND `sku` <> ''
  GROUP BY `sku` HAVING COUNT(*) > 1
) d ON d.`sku` = p.`sku` AND p.`id` <> d.`keep_id`
SET p.`sku` = CONCAT('PT-P-', LPAD(p.`id`, 8, '0'));

-- Seed the SKU counter past any back-filled value so new SKUs never collide.
INSERT INTO `store_counters` (`name`,`value`)
SELECT 'sku', COALESCE(MAX(`id`), 0) FROM `store_products`
ON DUPLICATE KEY UPDATE `value` = GREATEST(`value`, VALUES(`value`));

SET @pt_add := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `store_products` ADD UNIQUE KEY `uq_store_products_sku` (`sku`)', 'SELECT 1')
  FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_products' AND INDEX_NAME = 'uq_store_products_sku');
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

-- Platform tax: 8% by default, withheld from the buyer's amount (never added
-- to the seller payout nor counted as commission). Only seed when unset/zero.
INSERT INTO `config` (`name`,`value`)
SELECT 'store_tax_rate', '8'
WHERE NOT EXISTS (SELECT 1 FROM `config` c WHERE c.name = 'store_tax_rate')
   OR EXISTS (SELECT 1 FROM `config` c WHERE c.name = 'store_tax_rate' AND c.value IN ('', '0'));

-- ============================================================================
--  9. SEED: LOCATIONS (Uganda, matching the default company country)
-- ============================================================================

INSERT INTO `store_locations` (`parent_id`,`type`,`name`,`slug`,`country_code`,`sort_order`,`is_active`,`created_at`)
SELECT 0, s.type, s.name, s.slug, 'UG', s.ord, 1, UNIX_TIMESTAMP()
FROM (
  SELECT 'country' AS type, 'Uganda' AS name, 'uganda' AS slug, 1 AS ord
  UNION ALL SELECT 'region','Central Uganda','ug-central',20
  UNION ALL SELECT 'region','Eastern Uganda','ug-eastern',30
  UNION ALL SELECT 'region','Northern Uganda','ug-northern',40
  UNION ALL SELECT 'region','Western Uganda','ug-western',50
  UNION ALL SELECT 'region','Kampala Metropolitan','ug-kampala-metro',60
  UNION ALL SELECT 'district','Kampala','ug-kampala',70
  UNION ALL SELECT 'district','Wakiso','ug-wakiso',80
  UNION ALL SELECT 'district','Mukono','ug-mukono',90
  UNION ALL SELECT 'district','Entebbe','ug-entebbe',100
  UNION ALL SELECT 'district','Jinja','ug-jinja',110
  UNION ALL SELECT 'district','Mbale','ug-mbale',120
  UNION ALL SELECT 'district','Gulu','ug-gulu',130
  UNION ALL SELECT 'district','Lira','ug-lira',140
  UNION ALL SELECT 'district','Mbarara','ug-mbarara',150
  UNION ALL SELECT 'district','Fort Portal','ug-fort-portal',160
  UNION ALL SELECT 'district','Kasese','ug-kasese',170
  UNION ALL SELECT 'district','Masaka','ug-masaka',180
  UNION ALL SELECT 'district','Soroti','ug-soroti',190
  UNION ALL SELECT 'district','Arua','ug-arua',200
  UNION ALL SELECT 'district','Kabale','ug-kabale',210
  UNION ALL SELECT 'city','Nakawa','ug-nakawa',220
  UNION ALL SELECT 'city','Kawempe','ug-kawempe',230
  UNION ALL SELECT 'city','Makindye','ug-makindye',240
  UNION ALL SELECT 'city','Rubaga','ug-rubaga',250
  UNION ALL SELECT 'city','Nansana','ug-nansana',260
  UNION ALL SELECT 'city','Kira','ug-kira',270
  UNION ALL SELECT 'city','Najjera','ug-najjera',280
  UNION ALL SELECT 'city','Bukoto','ug-bukoto',290
  UNION ALL SELECT 'city','Ntinda','ug-ntinda',300
  UNION ALL SELECT 'city','Kololo','ug-kololo',310
) AS s
WHERE NOT EXISTS (SELECT 1 FROM `store_locations` l WHERE l.slug = s.slug);

-- Link each district/city to its region where obvious (Kampala metro).
UPDATE `store_locations` c
JOIN `store_locations` p ON p.slug = 'ug-kampala-metro'
SET c.parent_id = p.id
WHERE c.slug IN ('ug-kampala','ug-wakiso','ug-mukono','ug-entebbe','ug-nakawa','ug-kawempe','ug-makindye','ug-rubaga','ug-nansana','ug-kira','ug-najjera','ug-bukoto','ug-ntinda','ug-kololo')
  AND c.parent_id = 0;

-- ============================================================================
--  10. SEED: ADD-ON SUB-CATEGORIES
-- ============================================================================

INSERT INTO `store_addon_categories` (`name`,`slug`,`kind`,`description`,`sort_order`,`is_active`)
SELECT s.name, s.slug, s.kind, s.descr, s.ord, 1
FROM (
  SELECT 'Order perks' AS name, 'order-perks' AS slug, 'both' AS kind, 'Free or discounted extras attached to an order' AS descr, 10 AS ord
  UNION ALL SELECT 'Volume bundles','volume-bundles','both','Buy-more bundles such as buy one get one free',20
  UNION ALL SELECT 'Service extensions','service-extensions','service','Extend a session, retainer or subscription',30
  UNION ALL SELECT 'Priority & speed','priority-speed','service','Priority booking, express or same-day service',40
  UNION ALL SELECT 'Delivery upgrades','delivery-upgrades','both','Express or scheduled delivery upgrades',50
  UNION ALL SELECT 'Warranty & support','warranty-support','both','Extended warranty and after-sales support',60
  UNION ALL SELECT 'Value additions','value-additions','both','Complimentary value adds (gift wrap, install, setup)',70
  UNION ALL SELECT 'Loyalty rewards','loyalty-rewards','both','Points, cashback and repeat-buyer rewards',80
) AS s
WHERE NOT EXISTS (SELECT 1 FROM `store_addon_categories` c WHERE c.slug = s.slug);

-- ============================================================================
--  11. SEED: CATEGORY-SPECIFIC ATTRIBUTE DEFINITIONS
--  ---------------------------------------------------------------------------
--  Enough specs to be useful without turning the form into a wall of fields.
-- ============================================================================

INSERT INTO `store_attribute_defs` (`category_id`,`kind`,`field_key`,`label`,`field_type`,`options`,`unit`,`placeholder`,`is_required`,`sort_order`,`is_active`)
SELECT c.id, s.kind, s.fkey, s.label, s.ftype, s.opts, s.unit, s.ph, s.req, s.ord, 1
FROM (
  -- Electronics / phones / computers / gaming / photography
  SELECT 'electronics-accessories' AS slug, 'product' AS kind, 'brand' AS fkey, 'Brand' AS label, 'text' AS ftype, NULL AS opts, '' AS unit, 'e.g. Samsung' AS ph, 1 AS req, 10 AS ord
  UNION ALL SELECT 'electronics-accessories','product','model','Model','text',NULL,'','',0,20
  UNION ALL SELECT 'electronics-accessories','product','warranty_months','Warranty','number',NULL,'months','12',0,30
  UNION ALL SELECT 'electronics-accessories','product','power_source','Power source','select','["Mains","Battery","USB","Solar"]','','',0,40
  UNION ALL SELECT 'electronics-accessories','product','condition','Condition','select','["New","Refurbished","Used"]','','',1,50
  UNION ALL SELECT 'mobile-phones-tablets','product','brand','Brand','text',NULL,'','',1,10
  UNION ALL SELECT 'mobile-phones-tablets','product','storage_gb','Storage','number',NULL,'GB','128',1,20
  UNION ALL SELECT 'mobile-phones-tablets','product','ram_gb','RAM','number',NULL,'GB','8',0,30
  UNION ALL SELECT 'mobile-phones-tablets','product','screen_inches','Screen size','number',NULL,'in','6.1',0,40
  UNION ALL SELECT 'mobile-phones-tablets','product','battery_mah','Battery','number',NULL,'mAh','5000',0,50
  UNION ALL SELECT 'mobile-phones-tablets','product','network','Network','select','["2G","3G","4G","5G"]','','',0,60
  UNION ALL SELECT 'mobile-phones-tablets','product','colour','Colour','text',NULL,'','',0,70
  UNION ALL SELECT 'mobile-phones-tablets','product','condition','Condition','select','["New","Refurbished","Used"]','','',1,80
  UNION ALL SELECT 'computers-laptops','product','brand','Brand','text',NULL,'','',1,10
  UNION ALL SELECT 'computers-laptops','product','processor','Processor','text',NULL,'','',0,20
  UNION ALL SELECT 'computers-laptops','product','ram_gb','RAM','number',NULL,'GB','8',0,30
  UNION ALL SELECT 'computers-laptops','product','storage_gb','Storage','number',NULL,'GB','512',0,40
  UNION ALL SELECT 'computers-laptops','product','screen_inches','Screen size','number',NULL,'in','15.6',0,50
  UNION ALL SELECT 'computers-laptops','product','os','Operating system','select','["Windows","macOS","Linux","ChromeOS"]','','',0,60
  UNION ALL SELECT 'fashion-apparel','product','brand','Brand','text',NULL,'','',0,10
  UNION ALL SELECT 'fashion-apparel','product','material','Material','text',NULL,'','',0,20
  UNION ALL SELECT 'fashion-apparel','product','size','Size','select','["XS","S","M","L","XL","XXL"]','','',1,30
  UNION ALL SELECT 'fashion-apparel','product','colour','Colour','text',NULL,'','',0,40
  UNION ALL SELECT 'fashion-apparel','product','gender','For','select','["Unisex","Men","Women","Kids"]','','',0,50
  UNION ALL SELECT 'shoes-footwear','product','brand','Brand','text',NULL,'','',0,10
  UNION ALL SELECT 'shoes-footwear','product','size','Size (EU)','number',NULL,'EU','42',1,20
  UNION ALL SELECT 'shoes-footwear','product','material','Material','text',NULL,'','',0,30
  UNION ALL SELECT 'shoes-footwear','product','colour','Colour','text',NULL,'','',0,40
  UNION ALL SELECT 'home-kitchen','product','brand','Brand','text',NULL,'','',0,10
  UNION ALL SELECT 'home-kitchen','product','material','Material','text',NULL,'','',0,20
  UNION ALL SELECT 'home-kitchen','product','dimensions','Dimensions','text',NULL,'','',0,30
  UNION ALL SELECT 'furniture-decor','product','material','Material','text',NULL,'','',0,10
  UNION ALL SELECT 'furniture-decor','product','dimensions','Dimensions (LxWxH)','text',NULL,'cm','',1,20
  UNION ALL SELECT 'furniture-decor','product','assembly_required','Assembly required','boolean',NULL,'','',0,30
  UNION ALL SELECT 'beauty-personal-care','product','brand','Brand','text',NULL,'','',0,10
  UNION ALL SELECT 'beauty-personal-care','product','volume','Volume','number',NULL,'ml','',0,20
  UNION ALL SELECT 'beauty-personal-care','product','skin_type','Skin type','select','["All","Oily","Dry","Combination","Sensitive"]','','',0,30
  UNION ALL SELECT 'groceries-food','product','weight','Weight','number',NULL,'g','',0,10
  UNION ALL SELECT 'groceries-food','product','expiry','Best before','date',NULL,'','',0,20
  UNION ALL SELECT 'groceries-food','product','dietary','Dietary','multiselect','["Vegetarian","Vegan","Gluten-free","Halal","Kosher"]','','',0,30
  UNION ALL SELECT 'books-stationery','product','author','Author','text',NULL,'','',0,10
  UNION ALL SELECT 'books-stationery','product','isbn','ISBN','text',NULL,'','',0,20
  UNION ALL SELECT 'books-stationery','product','pages','Pages','number',NULL,'','',0,30
  UNION ALL SELECT 'books-stationery','product','language','Language','text',NULL,'','',0,40
  UNION ALL SELECT 'automotive-parts','product','brand','Brand','text',NULL,'','',0,10
  UNION ALL SELECT 'automotive-parts','product','part_number','Part number','text',NULL,'','',1,20
  UNION ALL SELECT 'automotive-parts','product','fits_make','Fits make','text',NULL,'','',0,30
  UNION ALL SELECT 'automotive-parts','product','fits_model','Fits model','text',NULL,'','',0,40
  UNION ALL SELECT 'jewellery-watches','product','material','Material','text',NULL,'','',1,10
  UNION ALL SELECT 'jewellery-watches','product','stone','Stone','text',NULL,'','',0,20
  UNION ALL SELECT 'jewellery-watches','product','size','Size','text',NULL,'','',0,30
  -- Services
  UNION ALL SELECT 'software-development-it','service','stack','Technology stack','text',NULL,'','',1,10
  UNION ALL SELECT 'software-development-it','service','delivery_model','Engagement','select','["Fixed price","Hourly","Retainer","Milestones"]','','',1,20
  UNION ALL SELECT 'software-development-it','service','experience_years','Years of experience','number',NULL,'yrs','',0,30
  UNION ALL SELECT 'software-development-it','service','portfolio_url','Portfolio URL','text',NULL,'','',0,40
  UNION ALL SELECT 'design-branding','service','deliverable','Deliverables','multiselect','["Logo","Brand guide","Social kit","Website","Packaging"]','','',1,10
  UNION ALL SELECT 'design-branding','service','revisions','Revisions included','number',NULL,'','',0,20
  UNION ALL SELECT 'design-branding','service','file_formats','File formats','text',NULL,'','',0,30
  UNION ALL SELECT 'photography-videography','service','deliverable','Deliverables','multiselect','["Edited photos","Raw files","Highlight video","Full video"]','','',1,10
  UNION ALL SELECT 'photography-videography','service','duration_hours','Session length','number',NULL,'hrs','',0,20
  UNION ALL SELECT 'photography-videography','service','editing_days','Editing turnaround','number',NULL,'days','',0,30
  UNION ALL SELECT 'music-audio-production','service','genre','Genre','text',NULL,'','',0,10
  UNION ALL SELECT 'music-audio-production','service','deliverable','Deliverable','select','["Beat","Full song","Mix","Master","Jingle"]','','',1,20
  UNION ALL SELECT 'music-audio-production','service','revisions','Revisions included','number',NULL,'','',0,30
  UNION ALL SELECT 'tutoring-education','service','subject','Subject','text',NULL,'','',1,10
  UNION ALL SELECT 'tutoring-education','service','level','Level','select','["Primary","Secondary","University","Adult"]','','',1,20
  UNION ALL SELECT 'tutoring-education','service','session_type','Session type','select','["One-on-one","Group","Online","In person"]','','',0,30
  UNION ALL SELECT 'coaching-consulting','service','focus','Focus area','text',NULL,'','',1,10
  UNION ALL SELECT 'coaching-consulting','service','session_type','Session type','select','["One-on-one","Group","Online","In person"]','','',1,20
  UNION ALL SELECT 'health-medical-services','service','speciality','Speciality','text',NULL,'','',1,10
  UNION ALL SELECT 'health-medical-services','service','license_number','Practitioner licence no.','text',NULL,'','',0,20
  UNION ALL SELECT 'health-medical-services','service','modality','Modality','select','["In person","Telehealth","Home visit"]','','',1,30
  UNION ALL SELECT 'wellness-spa','service','treatment','Treatment','text',NULL,'','',1,10
  UNION ALL SELECT 'wellness-spa','service','duration_minutes','Duration','number',NULL,'min','',1,20
  UNION ALL SELECT 'beauty-salon','service','treatment','Service','text',NULL,'','',1,10
  UNION ALL SELECT 'beauty-salon','service','at_home','Mobile service available','boolean',NULL,'','',0,20
  UNION ALL SELECT 'fitness-personal-training','service','focus','Goal','select','["Weight loss","Strength","Endurance","Rehab","General"]','','',1,10
  UNION ALL SELECT 'fitness-personal-training','service','session_type','Session type','select','["One-on-one","Group","Online","In person"]','','',1,20
  UNION ALL SELECT 'home-repair-services','service','speciality','Speciality','text',NULL,'','',1,10
  UNION ALL SELECT 'home-repair-services','service','service_call','Call-out fee','select','["Included","Extra"]','','',0,20
  UNION ALL SELECT 'cleaning-services','service','property_type','Property type','select','["Home","Office","Apartment","Commercial"]','','',1,10
  UNION ALL SELECT 'cleaning-services','service','frequency','Frequency','select','["One-off","Weekly","Fortnightly","Monthly"]','','',0,20
  UNION ALL SELECT 'delivery-errands','service','vehicle','Vehicle','select','["Boda","Car","Van","Truck","Bicycle"]','','',1,10
  UNION ALL SELECT 'delivery-errands','service','max_weight_kg','Max weight','number',NULL,'kg','',0,20
  UNION ALL SELECT 'legal-professional','service','matter_area','Matter area','text',NULL,'','',1,10
  UNION ALL SELECT 'legal-professional','service','license_number','Advocate licence no.','text',NULL,'','',0,20
  UNION ALL SELECT 'accounting-tax','service','service_scope','Scope','multiselect','["Bookkeeping","Payroll","Tax filing","Audit","Advisory"]','','',1,10
  UNION ALL SELECT 'events-entertainment','service','event_type','Event type','text',NULL,'','',1,10
  UNION ALL SELECT 'events-entertainment','service','duration_hours','Duration','number',NULL,'hrs','',0,20
  UNION ALL SELECT 'food-catering-services','service','cuisine','Cuisine','text',NULL,'','',0,10
  UNION ALL SELECT 'food-catering-services','service','serves','Serves up to','number',NULL,'people','',0,20
  UNION ALL SELECT 'travel-tours','service','destination','Destination','text',NULL,'','',1,10
  UNION ALL SELECT 'travel-tours','service','duration_days','Duration','number',NULL,'days','',0,20
  UNION ALL SELECT 'transportation-logistics','service','cargo_type','Cargo type','text',NULL,'','',0,10
  UNION ALL SELECT 'transportation-logistics','service','max_weight_kg','Max weight','number',NULL,'kg','',0,20
  UNION ALL SELECT 'pet-services','service','pet_type','Pet type','select','["Dog","Cat","Bird","Fish","Other"]','','',1,10
  UNION ALL SELECT 'pet-services','service','at_home','At your home','boolean',NULL,'','',0,20
  UNION ALL SELECT 'childcare-babysitting','service','age_range','Age range','text',NULL,'','',1,10
  UNION ALL SELECT 'childcare-babysitting','service','at_home','At your home','boolean',NULL,'','',0,20
  UNION ALL SELECT 'digital-marketing-seo','service','channels','Channels','multiselect','["SEO","Google Ads","Meta Ads","TikTok","Email","Influencer"]','','',1,10
  UNION ALL SELECT 'marketing-advertising','service','channels','Channels','multiselect','["Print","Radio","TV","Outdoor","Digital"]','','',1,10
  UNION ALL SELECT 'writing-translation','service','service_scope','Scope','select','["Writing","Editing","Proofreading","Translation","Transcription"]','','',1,10
  UNION ALL SELECT 'writing-translation','service','language_pair','Language pair','text',NULL,'','',0,20
  UNION ALL SELECT 'real-estate-services','service','service_scope','Scope','multiselect','["Sales","Rentals","Valuation","Property management","Legal"]','','',1,10
  UNION ALL SELECT 'software-development-it','service','support_days','Support period','number',NULL,'days','',0,50
) AS s
JOIN `store_categories` c ON c.slug = s.slug
WHERE NOT EXISTS (SELECT 1 FROM `store_attribute_defs` a WHERE a.category_id = c.id AND a.field_key = s.fkey);

-- ============================================================================
--  12. SEED: ADD-ON CATALOGUE (one dropdown's worth of options per category)
-- ============================================================================

INSERT INTO `store_addons` (`category_id`,`addon_category_id`,`kind`,`code`,`name`,`description`,`value_type`,`value`,`extends_days`,`is_active`,`sort_order`,`created_at`)
SELECT c.id, ac.id, s.kind, s.code, s.name, s.descr, s.vtype, s.val, s.days, 1, s.ord, UNIX_TIMESTAMP()
FROM (
  SELECT 'photography-videography' AS cat_slug, 'service' AS kind, 'service-extensions' AS grp, 'photo_extra_hour' AS code, 'Extra hour of coverage' AS name, 'Add one more hour to the shoot' AS descr, 'amount' AS vtype, 50000 AS val, 0 AS days, 10 AS ord
  UNION ALL SELECT 'photography-videography','service','service-extensions','photo_second_shooter','Second photographer','A second shooter for the session','amount',80000,0,20
  UNION ALL SELECT 'photography-videography','service','service-extensions','photo_extended_edit','Extended editing','Advanced retouching and colour grading','amount',40000,0,30
  UNION ALL SELECT 'photography-videography','service','value-additions','photo_online_gallery','Online gallery','Private online gallery for sharing','free',0,0,40
  UNION ALL SELECT 'photography-videography','service','priority-speed','photo_express_edit','Express 48h editing','Priority editing turnaround','percent',25,0,50
  UNION ALL SELECT 'music-audio-production','service','service-extensions','music_extra_revision','Extra revision round','One additional revision','amount',30000,0,10
  UNION ALL SELECT 'music-audio-production','service','service-extensions','music_stems','Stems / multitrack','Receive separate track files','amount',25000,0,20
  UNION ALL SELECT 'music-audio-production','service','value-additions','music_full_song','Full song instead of beat','Upgrade a beat to a full song','amount',120000,0,30
  UNION ALL SELECT 'software-development-it','service','service-extensions','it_maintenance_30','30-day maintenance','A month of post-launch support','amount',200000,0,10
  UNION ALL SELECT 'software-development-it','service','service-extensions','it_support_ext','Extended support','Add 30 days of support','extended',0,30,20
  UNION ALL SELECT 'software-development-it','service','priority-speed','it_priority','Priority queue','Jump the queue, faster delivery','percent',20,0,30
  UNION ALL SELECT 'software-development-it','service','value-additions','it_source_code','Full source code','Hand over the full source','free',0,0,40
  UNION ALL SELECT 'web-app-development','service','service-extensions','web_hosting_year','1 year hosting','Managed hosting for a year','amount',250000,0,10
  UNION ALL SELECT 'web-app-development','service','service-extensions','web_maintenance','Care plan','Monthly maintenance plan','extended',0,30,20
  UNION ALL SELECT 'web-app-development','service','value-additions','web_seo_setup','Basic SEO setup','On-page SEO foundations','amount',90000,0,30
  UNION ALL SELECT 'design-branding','service','service-extensions','design_extra_concept','Extra concept','One additional design concept','amount',40000,0,10
  UNION ALL SELECT 'design-branding','service','service-extensions','design_social_kit','Social media kit','Templates sized for social','amount',50000,0,20
  UNION ALL SELECT 'design-branding','service','priority-speed','design_rush','Rush delivery','Deliver within 48 hours','percent',30,0,30
  UNION ALL SELECT 'design-branding','service','value-additions','design_source_files','Source files','Editable source files','free',0,0,40
  UNION ALL SELECT 'digital-marketing-seo','service','service-extensions','seo_extra_month','Extra month of work','Add one month to the campaign','amount',180000,0,10
  UNION ALL SELECT 'digital-marketing-seo','service','value-additions','seo_report','Monthly reporting pack','Detailed performance report','free',0,0,20
  UNION ALL SELECT 'writing-translation','service','service-extensions','writing_extra_words','Extra 500 words','Add length to the piece','amount',25000,0,10
  UNION ALL SELECT 'writing-translation','service','service-extensions','writing_rush','48h turnaround','Priority delivery','percent',25,0,20
  UNION ALL SELECT 'tutoring-education','service','service-extensions','tutor_extra_session','Extra session','Add one tutoring session','amount',30000,0,10
  UNION ALL SELECT 'tutoring-education','service','volume-bundles','tutor_bundle5','5-session bundle','Buy 5 sessions at a discount','percent',10,0,20
  UNION ALL SELECT 'tutoring-education','service','value-additions','tutor_notes','Lesson notes / recording','Shared notes after each session','free',0,0,30
  UNION ALL SELECT 'coaching-consulting','service','service-extensions','coach_extra_session','Extra session','Add a coaching session','amount',60000,0,10
  UNION ALL SELECT 'coaching-consulting','service','volume-bundles','coach_bundle4','4-session package','Four sessions at a discount','percent',12,0,20
  UNION ALL SELECT 'health-medical-services','service','priority-speed','health_priority','Priority appointment','Front-of-queue appointment','amount',30000,0,10
  UNION ALL SELECT 'health-medical-services','service','value-additions','health_followup','Free follow-up','One complimentary follow-up','free',0,0,20
  UNION ALL SELECT 'wellness-spa','service','service-extensions','spa_extension','30-minute extension','Lengthen the treatment','amount',35000,0,10
  UNION ALL SELECT 'wellness-spa','service','value-additions','spa_aromatherapy','Complimentary aromatherapy','Included essential oils','free',0,0,20
  UNION ALL SELECT 'beauty-salon','service','service-extensions','salon_treatment_add','Add a treatment','Add a facial / treatment','amount',40000,0,10
  UNION ALL SELECT 'beauty-salon','service','value-additions','salon_aftercare','Aftercare kit','Take-home care products','amount',20000,0,20
  UNION ALL SELECT 'fitness-personal-training','service','volume-bundles','fit_bundle10','10-session bundle','Ten sessions at a discount','percent',15,0,10
  UNION ALL SELECT 'fitness-personal-training','service','service-extensions','fit_nutrition','Nutrition plan','Personalised meal plan','amount',70000,0,20
  UNION ALL SELECT 'home-repair-services','service','warranty-support','repair_warranty6','6-month workmanship warranty','Extend the warranty on the work','extended',0,180,10
  UNION ALL SELECT 'home-repair-services','service','value-additions','repair_materials','Materials included','Cover consumables','amount',50000,0,20
  UNION ALL SELECT 'cleaning-services','service','delivery-upgrades','clean_same_day','Same-day cleaning','Priority same-day slot','percent',20,0,10
  UNION ALL SELECT 'cleaning-services','service','service-extensions','clean_extra_room','Extra room / area','Add another room to the clean','amount',25000,0,20
  UNION ALL SELECT 'delivery-errands','service','priority-speed','errand_express','Express errand','Priority pickup and drop','percent',30,0,10
  UNION ALL SELECT 'delivery-errands','service','delivery-upgrades','errand_wait','Wait-and-return','Courier waits and returns','amount',15000,0,20
  UNION ALL SELECT 'legal-professional','service','service-extensions','legal_extra_doc','Extra document','Draft an additional document','amount',100000,0,10
  UNION ALL SELECT 'accounting-tax','service','service-extensions','acct_extra_month','Extra month of bookkeeping','One more month of books','amount',120000,0,10
  UNION ALL SELECT 'accounting-tax','service','value-additions','acct_tax_advice','Tax advisory call','A complimentary advisory call','free',0,0,20
  UNION ALL SELECT 'events-entertainment','service','service-extensions','event_extra_hour','Extra hour','Extend the event by an hour','amount',80000,0,10
  UNION ALL SELECT 'events-entertainment','service','value-additions','event_mc','MC included','Master of ceremonies included','amount',120000,0,20
  UNION ALL SELECT 'food-catering-services','service','volume-bundles','cater_bogo','Buy one platter get one free','Applicable to selected platters','free',0,0,10
  UNION ALL SELECT 'food-catering-services','service','delivery-upgrades','cater_service_staff','Serving staff','Waiters to serve on-site','amount',100000,0,20
  UNION ALL SELECT 'travel-tours','service','service-extensions','tour_extra_day','Extra day','Add a day to the tour','amount',150000,0,10
  UNION ALL SELECT 'travel-tours','service','value-additions','tour_airport_pickup','Airport pickup','Complimentary airport transfer','free',0,0,20
  UNION ALL SELECT 'transportation-logistics','service','delivery-upgrades','logistics_express','Express route','Priority dispatch','percent',25,0,10
  UNION ALL SELECT 'transportation-logistics','service','service-extensions','logistics_insurance','Cargo insurance','Insure the shipment','percent',5,0,20
  UNION ALL SELECT 'pet-services','service','service-extensions','pet_extra_walk','Extra walk','Add a walk session','amount',15000,0,10
  UNION ALL SELECT 'pet-services','service','value-additions','pet_grooming','Grooming included','A complimentary groom','amount',25000,0,20
  UNION ALL SELECT 'childcare-babysitting','service','service-extensions','child_extra_hour','Extra hour','One more hour of care','amount',20000,0,10
  UNION ALL SELECT 'childcare-babysitting','service','value-additions','child_meals','Meals provided','Meals during care','amount',15000,0,20
  -- Products: generic perks available to every product category
  UNION ALL SELECT '0','product','order-perks','prod_gift_wrap','Gift wrap','Present it beautifully','amount',5000,0,10
  UNION ALL SELECT '0','product','order-perks','prod_gift_note','Handwritten note','A personal message','free',0,0,20
  UNION ALL SELECT '0','product','volume-bundles','prod_bogo','Buy one get one free','Applies to selected items','free',0,0,30
  UNION ALL SELECT '0','product','volume-bundles','prod_bundle3','Buy 2 get 1 free','Applies to selected items','free',0,0,40
  UNION ALL SELECT '0','product','delivery-upgrades','prod_express_delivery','Express delivery','Delivered sooner','percent',20,0,50
  UNION ALL SELECT '0','product','warranty-support','prod_warranty6','Extended warranty','6 months extra cover','extended',0,180,60
  UNION ALL SELECT '0','product','warranty-support','prod_support','Priority after-sales support','Direct support line','free',0,0,70
  UNION ALL SELECT '0','product','value-additions','prod_free_sample','Free sample','A small extra sample','free',0,0,80
  UNION ALL SELECT '0','product','loyalty-rewards','prod_cashback5','5% cashback points','Loyalty points on purchase','percent',5,0,90
  -- Services: generic perks available to every service category
  UNION ALL SELECT '0','service','order-perks','svc_free_consult','Free consultation','A short pre-service consult','free',0,0,10
  UNION ALL SELECT '0','service','order-perks','svc_gogo','Get one get one free','A second session free','free',0,0,20
  UNION ALL SELECT '0','service','priority-speed','svc_priority','Priority booking','Preferred scheduling','free',0,0,30
  UNION ALL SELECT '0','service','service-extensions','svc_extension30','30-day extension','Extend the engagement','extended',0,30,40
  UNION ALL SELECT '0','service','service-extensions','svc_extension90','90-day extension','Extend the engagement further','extended',0,90,50
  UNION ALL SELECT '0','service','warranty-support','svc_warranty','Satisfaction guarantee','Free redo if not satisfied','free',0,0,60
  UNION ALL SELECT '0','service','loyalty-rewards','svc_cashback5','5% loyalty points','Loyalty points on booking','percent',5,0,70
) AS s
LEFT JOIN `store_categories` c ON c.slug = s.cat_slug
LEFT JOIN `store_addon_categories` ac ON ac.slug = s.grp
WHERE NOT EXISTS (SELECT 1 FROM `store_addons` a WHERE a.code = s.code);

-- ============================================================================
--  13. SEED: REJECTION REASONS
-- ============================================================================

INSERT INTO `store_rejection_reasons` (`scope`,`code`,`label`,`sort_order`,`is_active`)
SELECT s.scope, s.code, s.label, s.ord, 1
FROM (
  SELECT 'seller' AS scope, 'seller_docs_unclear' AS code, 'Verification documents are unclear or unreadable' AS label, 10 AS ord
  UNION ALL SELECT 'seller','seller_docs_invalid','The submitted document is not a valid licence or ID',20
  UNION ALL SELECT 'seller','seller_name_mismatch','Store name does not match the registered business or ID',30
  UNION ALL SELECT 'seller','seller_prohibited','The store sells prohibited or restricted goods',40
  UNION ALL SELECT 'seller','seller_incomplete','Store details are incomplete or inaccurate',50
  UNION ALL SELECT 'seller','seller_contact','We could not verify your contact details',60
  UNION ALL SELECT 'product','product_images','Product images are missing, low quality or misleading',10
  UNION ALL SELECT 'product','product_category','The listing is in the wrong category',20
  UNION ALL SELECT 'product','product_prohibited','The item is prohibited or restricted',30
  UNION ALL SELECT 'product','product_price','The price or delivery details are not credible',40
  UNION ALL SELECT 'product','product_description','The description is too vague or inaccurate',50
  UNION ALL SELECT 'product','product_specs','Mandatory specifications are missing',60
  UNION ALL SELECT 'both','other','Other (explained in the note)',99
) AS s
WHERE NOT EXISTS (SELECT 1 FROM `store_rejection_reasons` r WHERE r.code = s.code);

-- ============================================================================
--  14. SUBCATEGORIES FOR SERVICE CATEGORIES
--  ---------------------------------------------------------------------------
--  parent_id points at the seeded top-level category; sub-categories inherit
--  the parent's commission / return policy unless overridden in admin.
-- ============================================================================

INSERT INTO `store_categories` (`parent_id`,`name`,`slug`,`kind`,`commission_bps`,`requires_return`,`return_days`,`requires_approval`,`is_active`,`sort_order`,`created_at`)
SELECT p.id, s.name, s.slug, 'service', p.commission_bps, p.requires_return, p.return_days, p.requires_approval, 1, s.ord, UNIX_TIMESTAMP()
FROM (
  SELECT 'software-development-it' AS parent_slug, 'Mobile App Development' AS name, 'svc-mobile-app-development' AS slug, 10 AS ord
  UNION ALL SELECT 'software-development-it','Website Development','svc-website-development',20
  UNION ALL SELECT 'software-development-it','API & Integrations','svc-api-integrations',30
  UNION ALL SELECT 'software-development-it','DevOps & Cloud','svc-devops-cloud',40
  UNION ALL SELECT 'software-development-it','IT Support','svc-it-support',50
  UNION ALL SELECT 'photography-videography','Wedding Photography','svc-wedding-photography',10
  UNION ALL SELECT 'photography-videography','Event Photography','svc-event-photography',20
  UNION ALL SELECT 'photography-videography','Product Photography','svc-product-photography',30
  UNION ALL SELECT 'photography-videography','Videography','svc-videography',40
  UNION ALL SELECT 'photography-videography','Drone Coverage','svc-drone-coverage',50
  UNION ALL SELECT 'beauty-salon','Hair Styling','svc-hair-styling',10
  UNION ALL SELECT 'beauty-salon','Nails','svc-nails',20
  UNION ALL SELECT 'beauty-salon','Makeup','svc-makeup',30
  UNION ALL SELECT 'beauty-salon','Spa & Massage','svc-spa-massage',40
  UNION ALL SELECT 'beauty-salon','Barbering','svc-barbering',50
  UNION ALL SELECT 'home-repair-services','Plumbing','svc-plumbing',10
  UNION ALL SELECT 'home-repair-services','Electrical','svc-electrical',20
  UNION ALL SELECT 'home-repair-services','Carpentry','svc-carpentry',30
  UNION ALL SELECT 'home-repair-services','Painting','svc-painting',40
  UNION ALL SELECT 'home-repair-services','Appliance Repair','svc-appliance-repair',50
  UNION ALL SELECT 'events-entertainment','Weddings & Introductions','svc-weddings',10
  UNION ALL SELECT 'events-entertainment','Corporate Events','svc-corporate-events',20
  UNION ALL SELECT 'events-entertainment','DJ & Sound','svc-dj-sound',30
  UNION ALL SELECT 'events-entertainment','MCs & Hosts','svc-mcs-hosts',40
  UNION ALL SELECT 'events-entertainment','Decor & Setup','svc-decor-setup',50
  UNION ALL SELECT 'tutoring-education','Primary School','svc-primary-tutoring',10
  UNION ALL SELECT 'tutoring-education','Secondary School','svc-secondary-tutoring',20
  UNION ALL SELECT 'tutoring-education','University','svc-university-tutoring',30
  UNION ALL SELECT 'tutoring-education','Languages','svc-language-tutoring',40
  UNION ALL SELECT 'tutoring-education','Music Lessons','svc-music-lessons',50
  UNION ALL SELECT 'food-catering-services','Event Catering','svc-event-catering',10
  UNION ALL SELECT 'food-catering-services','Meal Prep','svc-meal-prep',20
  UNION ALL SELECT 'food-catering-services','Baking & Pastry','svc-baking-pastry',30
  UNION ALL SELECT 'food-catering-services','Private Chef','svc-private-chef',40
  UNION ALL SELECT 'cleaning-services','Home Cleaning','svc-home-cleaning',10
  UNION ALL SELECT 'cleaning-services','Office Cleaning','svc-office-cleaning',20
  UNION ALL SELECT 'cleaning-services','Deep Cleaning','svc-deep-cleaning',30
  UNION ALL SELECT 'cleaning-services','Laundry & Ironing','svc-laundry-ironing',40
  UNION ALL SELECT 'cleaning-services','Fumigation & Pest Control','svc-fumigation',50
  UNION ALL SELECT 'wellness-spa','Massage Therapy','svc-massage-therapy',10
  UNION ALL SELECT 'wellness-spa','Facial Treatments','svc-facial-treatments',20
  UNION ALL SELECT 'wellness-spa','Sauna & Steam','svc-sauna-steam',30
  UNION ALL SELECT 'wellness-spa','Aromatherapy','svc-aromatherapy',40
  UNION ALL SELECT 'fitness-personal-training','Strength Training','svc-strength-training',10
  UNION ALL SELECT 'fitness-personal-training','Weight Loss','svc-weight-loss',20
  UNION ALL SELECT 'fitness-personal-training','Yoga & Pilates','svc-yoga-pilates',30
  UNION ALL SELECT 'fitness-personal-training','Sports Coaching','svc-sports-coaching',40
  UNION ALL SELECT 'childcare-babysitting','Daytime Nanny','svc-daytime-nanny',10
  UNION ALL SELECT 'childcare-babysitting','Evening Babysitting','svc-evening-babysitting',20
  UNION ALL SELECT 'childcare-babysitting','Live-out Nanny','svc-liveout-nanny',30
  UNION ALL SELECT 'childcare-babysitting','Special Needs Care','svc-special-needs',40
  UNION ALL SELECT 'pet-services','Dog Walking','svc-dog-walking',10
  UNION ALL SELECT 'pet-services','Pet Grooming','svc-pet-grooming',20
  UNION ALL SELECT 'pet-services','Pet Boarding','svc-pet-boarding',30
  UNION ALL SELECT 'pet-services','Veterinary Visits','svc-vet-visits',40
  UNION ALL SELECT 'travel-tours','Domestic Tours','svc-domestic-tours',10
  UNION ALL SELECT 'travel-tours','Safari Packages','svc-safari-packages',20
  UNION ALL SELECT 'travel-tours','Airport Transfers','svc-airport-transfers',30
  UNION ALL SELECT 'travel-tours','Hotel Bookings','svc-hotel-bookings',40
  UNION ALL SELECT 'transportation-logistics','Boda Delivery','svc-boda-delivery',10
  UNION ALL SELECT 'transportation-logistics','Truck Haulage','svc-truck-haulage',20
  UNION ALL SELECT 'transportation-logistics','Courier & Parcel','svc-courier-parcel',30
  UNION ALL SELECT 'transportation-logistics','Moving & Relocation','svc-moving-relocation',40
  UNION ALL SELECT 'health-medical-services','General Practice','svc-general-practice',10
  UNION ALL SELECT 'health-medical-services','Dental Care','svc-dental-care',20
  UNION ALL SELECT 'health-medical-services','Optometry','svc-optometry',30
  UNION ALL SELECT 'health-medical-services','Physiotherapy','svc-physiotherapy',40
  UNION ALL SELECT 'coaching-consulting','Business Coaching','svc-business-coaching',10
  UNION ALL SELECT 'coaching-consulting','Career Coaching','svc-career-coaching',20
  UNION ALL SELECT 'coaching-consulting','Life Coaching','svc-life-coaching',30
  UNION ALL SELECT 'coaching-consulting','Leadership Training','svc-leadership-training',40
  UNION ALL SELECT 'legal-professional','Conveyancing','svc-conveyancing',10
  UNION ALL SELECT 'legal-professional','Company Registration','svc-company-registration',20
  UNION ALL SELECT 'legal-professional','Contracts & Agreements','svc-contracts',30
  UNION ALL SELECT 'legal-professional','Litigation','svc-litigation',40
  UNION ALL SELECT 'accounting-tax','Bookkeeping','svc-bookkeeping',10
  UNION ALL SELECT 'accounting-tax','Tax Filing','svc-tax-filing',20
  UNION ALL SELECT 'accounting-tax','Payroll','svc-payroll',30
  UNION ALL SELECT 'accounting-tax','Audit & Assurance','svc-audit',40
  UNION ALL SELECT 'real-estate-services','Property Sales','svc-property-sales',10
  UNION ALL SELECT 'real-estate-services','Rentals & Lettings','svc-rentals',20
  UNION ALL SELECT 'real-estate-services','Property Management','svc-property-management',30
  UNION ALL SELECT 'real-estate-services','Valuation','svc-valuation',40
  UNION ALL SELECT 'digital-marketing-seo','Search Engine Optimization','svc-seo',10
  UNION ALL SELECT 'digital-marketing-seo','Paid Ads','svc-paid-ads',20
  UNION ALL SELECT 'digital-marketing-seo','Social Media Management','svc-social-media',30
  UNION ALL SELECT 'digital-marketing-seo','Email Marketing','svc-email-marketing',40
  UNION ALL SELECT 'design-branding','Logo Design','svc-logo-design',10
  UNION ALL SELECT 'design-branding','Brand Identity','svc-brand-identity',20
  UNION ALL SELECT 'design-branding','Print & Packaging','svc-print-packaging',30
  UNION ALL SELECT 'design-branding','UI/UX Design','svc-ui-ux-design',40
  UNION ALL SELECT 'writing-translation','Copywriting','svc-copywriting',10
  UNION ALL SELECT 'writing-translation','Proofreading & Editing','svc-proofreading',20
  UNION ALL SELECT 'writing-translation','Translation','svc-translation',30
  UNION ALL SELECT 'writing-translation','Transcription','svc-transcription',40
  UNION ALL SELECT 'web-app-development','E-commerce Stores','svc-ecommerce-stores',10
  UNION ALL SELECT 'web-app-development','Landing Pages','svc-landing-pages',20
  UNION ALL SELECT 'web-app-development','Web Applications','svc-web-applications',30
  UNION ALL SELECT 'web-app-development','Maintenance & Support','svc-web-maintenance',40
  UNION ALL SELECT 'software-development-it','Cybersecurity','svc-cybersecurity',60
  UNION ALL SELECT 'software-development-it','Data & Analytics','svc-data-analytics',70
  UNION ALL SELECT 'marketing-advertising','Brand Campaigns','svc-brand-campaigns',10
  UNION ALL SELECT 'marketing-advertising','Influencer Marketing','svc-influencer-marketing',20
  UNION ALL SELECT 'marketing-advertising','Media Buying','svc-media-buying',30
  UNION ALL SELECT 'marketing-advertising','Market Research','svc-market-research',40
  UNION ALL SELECT 'transportation-logistics','Warehousing','svc-warehousing',50
) AS s
JOIN `store_categories` p ON p.slug = s.parent_slug
WHERE NOT EXISTS (SELECT 1 FROM `store_categories` c WHERE c.slug = s.slug);

-- ============================================================================
--  END
-- ============================================================================