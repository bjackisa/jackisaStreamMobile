-- ============================================================================
--  Jackisa Store - new feature migration
--  ---------------------------------------------------------------------------
--  A self-contained marketplace bolted onto Jackisa Stream:
--    * sellers (member storefronts) with versioned policy consent
--    * products (physical / digital) and services (bookable)
--    * immutable double-entry ledger + sequential business documents
--      (quotation -> invoice -> payment credit note -> receipt, plus
--       cancellation and refund credit notes)
--    * reviews, per-channel storefront, subscriber membership benefits
--    * escrow releases and store earnings feeding the existing wallet balance
--
--  This file only ADDS. It never touches an existing table's definition or
--  data except through additive, idempotent column guards. It is safe to run
--  more than once.
--
--  Accounting rows (ledger, documents, order lines, earnings) are append-only
--  and guarded by BEFORE UPDATE/DELETE triggers, exactly like the stakeholder
--  ledger. Server code that must legitimately write them wraps the call in
--  PT_DbGuardedRun(), which sets @pt_allow_immutable = 1 for the duration.
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================================
--  1. SELLERS, POLICY CONSENT
-- ============================================================================

-- One storefront per member. `status` gates whether the store is publicly
-- browsable; a seller must accept every policy below before this row is
-- written (see store_terms_acceptances).
CREATE TABLE IF NOT EXISTS `store_sellers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `store_name` varchar(150) NOT NULL DEFAULT '',
  `store_slug` varchar(160) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `logo` varchar(500) NOT NULL DEFAULT '',
  `banner` varchar(500) NOT NULL DEFAULT '',
  `contact_email` varchar(150) NOT NULL DEFAULT '',
  `contact_phone` varchar(50) NOT NULL DEFAULT '',
  `location` varchar(200) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  -- Optional platform override of the category commission, in basis points.
  `commission_override_bps` int(11) NOT NULL DEFAULT 0,
  `payout_note` varchar(500) NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_sellers_user` (`user_id`),
  UNIQUE KEY `uq_store_sellers_slug` (`store_slug`),
  KEY `idx_store_sellers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Versioned, append-only consent record. A member must hold one row covering
-- the three policies for a context before they can sell or buy.
CREATE TABLE IF NOT EXISTS `store_terms_acceptances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  -- seller : before adding merch / services
  -- buyer  : before ordering / booking
  `context` varchar(20) NOT NULL DEFAULT 'seller',
  `store_terms_version` varchar(30) NOT NULL DEFAULT '',
  `stream_terms_version` varchar(30) NOT NULL DEFAULT '',
  `refund_policy_version` varchar(30) NOT NULL DEFAULT '',
  `privacy_policy_version` varchar(30) NOT NULL DEFAULT '',
  `ip_address` varchar(150) NOT NULL DEFAULT '',
  `user_agent` varchar(300) NOT NULL DEFAULT '',
  `accepted_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_terms_user` (`user_id`,`context`),
  KEY `idx_store_terms_time` (`accepted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  2. CATEGORIES (commission + return policy live here)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(150) NOT NULL DEFAULT '',
  `slug` varchar(160) NOT NULL DEFAULT '',
  -- product | service | both
  `kind` varchar(20) NOT NULL DEFAULT 'product',
  -- Default commission for this category, in basis points (300..1500 = 3%..15%).
  `commission_bps` int(11) NOT NULL DEFAULT 800,
  -- 1 = items in this category may be returned (and so funds stay in escrow
  -- for `return_days`); 0 = final sale, escrow uses the platform default.
  `requires_return` tinyint(4) NOT NULL DEFAULT 0,
  `return_days` int(11) NOT NULL DEFAULT 7,
  `requires_approval` tinyint(4) NOT NULL DEFAULT 1,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_categories_slug` (`slug`),
  KEY `idx_store_categories_kind` (`kind`),
  KEY `idx_store_categories_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  3. PRODUCTS / SERVICES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `category_id` int(11) NOT NULL DEFAULT 0,
  -- product | service
  `kind` varchar(20) NOT NULL DEFAULT 'product',
  -- physical | digital (products only)
  `product_type` varchar(20) NOT NULL DEFAULT 'physical',
  `title` varchar(200) NOT NULL DEFAULT '',
  `slug` varchar(220) NOT NULL DEFAULT '',
  `description` longtext,
  `sku` varchar(80) NOT NULL DEFAULT '',
  `price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `compare_price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(10) NOT NULL DEFAULT '',
  -- none | percent | amount
  `discount_type` varchar(10) NOT NULL DEFAULT 'none',
  `discount_value` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `discount_start` int(11) NOT NULL DEFAULT 0,
  `discount_end` int(11) NOT NULL DEFAULT 0,
  `track_stock` tinyint(4) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `delivery_fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `delivery_note` varchar(500) NOT NULL DEFAULT '',
  -- draft | pending | active | rejected | archived
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `reject_reason` varchar(500) NOT NULL DEFAULT '',
  `requires_approval` tinyint(4) NOT NULL DEFAULT 1,
  `is_featured` tinyint(4) NOT NULL DEFAULT 0,
  -- services
  `booking_duration_minutes` int(11) NOT NULL DEFAULT 60,
  `booking_capacity` int(11) NOT NULL DEFAULT 1,
  `booking_buffer_minutes` int(11) NOT NULL DEFAULT 0,
  `booking_lead_days` int(11) NOT NULL DEFAULT 0,
  -- subscriber membership benefits
  `member_free_delivery` tinyint(4) NOT NULL DEFAULT 0,
  `member_discount_pct` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `member_addon` varchar(300) NOT NULL DEFAULT '',
  `views` int(11) NOT NULL DEFAULT 0,
  `sales` int(11) NOT NULL DEFAULT 0,
  `rating_sum` int(11) NOT NULL DEFAULT 0,
  `rating_count` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_products_slug` (`slug`),
  KEY `idx_store_products_seller` (`seller_id`),
  KEY `idx_store_products_user` (`user_id`),
  KEY `idx_store_products_category` (`category_id`),
  KEY `idx_store_products_status` (`status`,`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `image` varchar(500) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_product_images_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_product_variants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(150) NOT NULL DEFAULT '',
  -- JSON list of option values, e.g. ["Red","Blue"] or {"Size":["S","M"]}
  `options` text DEFAULT NULL,
  `sku` varchar(80) NOT NULL DEFAULT '',
  `price_delta` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `stock` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_variants_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Weekly availability template for a bookable service. The engine expands
-- these into concrete date/time slots between `valid_from` and `valid_to`.
CREATE TABLE IF NOT EXISTS `store_product_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL DEFAULT 0,
  -- 0 = Sunday .. 6 = Saturday
  `weekday` tinyint(4) NOT NULL DEFAULT 1,
  `start_time` time NOT NULL DEFAULT '09:00:00',
  `end_time` time NOT NULL DEFAULT '10:00:00',
  `capacity` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_store_slots_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-off closed windows (holidays, breaks) removed from a service calendar.
CREATE TABLE IF NOT EXISTS `store_blackouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `start_ts` int(11) NOT NULL DEFAULT 0,
  `end_ts` int(11) NOT NULL DEFAULT 0,
  `reason` varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_store_blackouts_product` (`product_id`,`start_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  4. ORDERS AND BOOKINGS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(40) NOT NULL DEFAULT '',
  `buyer_id` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `seller_user_id` int(11) NOT NULL DEFAULT 0,
  -- order | booking
  `kind` varchar(20) NOT NULL DEFAULT 'order',
  -- pending | confirmed | approved | paid | closed | cancelled | refunded | error
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `currency` varchar(10) NOT NULL DEFAULT '',
  `subtotal` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `discount_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `delivery_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `tax_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `commission_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `seller_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `paid_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `refund_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `payment_method` varchar(40) NOT NULL DEFAULT '',
  `payment_ref` varchar(120) NOT NULL DEFAULT '',
  `jpesa_payment_id` int(11) NOT NULL DEFAULT 0,
  `shipping` text DEFAULT NULL,
  `buyer_note` varchar(1000) NOT NULL DEFAULT '',
  `seller_note` varchar(1000) NOT NULL DEFAULT '',
  `cancel_reason` varchar(500) NOT NULL DEFAULT '',
  -- covers the longest return window on the order; funds stay in escrow until this time passes
  `escrow_release_at` int(11) NOT NULL DEFAULT 0,
  `placed_at` int(11) NOT NULL DEFAULT 0,
  `confirmed_at` int(11) NOT NULL DEFAULT 0,
  `approved_at` int(11) NOT NULL DEFAULT 0,
  `paid_at` int(11) NOT NULL DEFAULT 0,
  `closed_at` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_orders_number` (`order_number`),
  KEY `idx_store_orders_buyer` (`buyer_id`,`status`),
  KEY `idx_store_orders_seller` (`seller_id`,`status`),
  KEY `idx_store_orders_status` (`status`,`escrow_release_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `variant_id` int(11) NOT NULL DEFAULT 0,
  `kind` varchar(20) NOT NULL DEFAULT 'product',
  `title` varchar(200) NOT NULL DEFAULT '',
  `variant_label` varchar(200) NOT NULL DEFAULT '',
  `qty` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `line_discount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `line_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `delivery_fee` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `commission_bps` int(11) NOT NULL DEFAULT 0,
  `commission_amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `seller_amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `requires_return` tinyint(4) NOT NULL DEFAULT 0,
  `return_days` int(11) NOT NULL DEFAULT 7,
  `meta` text DEFAULT NULL,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_order_items_order` (`order_id`),
  KEY `idx_store_order_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One booking row per bookable line on a service order.
CREATE TABLE IF NOT EXISTS `store_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `order_item_id` int(11) NOT NULL DEFAULT 0,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `buyer_id` int(11) NOT NULL DEFAULT 0,
  `slot_start` int(11) NOT NULL DEFAULT 0,
  `slot_end` int(11) NOT NULL DEFAULT 0,
  -- reserved | confirmed | completed | cancelled
  `status` varchar(20) NOT NULL DEFAULT 'reserved',
  `notes` varchar(1000) NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_bookings_product` (`product_id`,`slot_start`),
  KEY `idx_store_bookings_order` (`order_id`),
  KEY `idx_store_bookings_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Links a product/service to the content it is merchandised on. `target_type`
-- is one of video, live, article; `target_id` is the videos/post id.
CREATE TABLE IF NOT EXISTS `store_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `target_type` varchar(20) NOT NULL DEFAULT 'video',
  `target_id` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_attachment` (`product_id`,`target_type`,`target_id`),
  KEY `idx_store_attach_target` (`target_type`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  5. REVIEWS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `title` varchar(200) NOT NULL DEFAULT '',
  `body` text DEFAULT NULL,
  -- published | hidden
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_review_order_product` (`order_id`,`product_id`,`user_id`),
  KEY `idx_store_reviews_product` (`product_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  6. DOUBLE-ENTRY LEDGER
--  ---------------------------------------------------------------------------
--  Every accounting event posts balanced debits and credits sharing a txn_id.
--  The chart of accounts below is seeded; per-user sub-accounts are keyed by
--  (code, user_id) so each member has a receivable/payable position.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_ledger_accounts` (
  `code` varchar(40) NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  -- asset | liability | equity | revenue | expense
  `type` varchar(20) NOT NULL DEFAULT 'asset',
  `is_user_account` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only. Never updated or deleted (triggers below). A superadmin may
-- only flag a whole transaction as an error, and that flag lives in
-- store_ledger_flags so this table stays physically immutable.
CREATE TABLE IF NOT EXISTS `store_ledger_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `txn_id` varchar(40) NOT NULL DEFAULT '',
  `account_code` varchar(40) NOT NULL DEFAULT '',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `document_id` int(11) NOT NULL DEFAULT 0,
  `debit` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `credit` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(10) NOT NULL DEFAULT '',
  `memo` varchar(500) NOT NULL DEFAULT '',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_ledger_txn` (`txn_id`),
  KEY `idx_store_ledger_account` (`account_code`,`user_id`),
  KEY `idx_store_ledger_order` (`order_id`),
  KEY `idx_store_ledger_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_ledger_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `txn_id` varchar(40) NOT NULL DEFAULT '',
  `is_error` tinyint(4) NOT NULL DEFAULT 0,
  `reason` varchar(500) NOT NULL DEFAULT '',
  `flagged_by` int(11) NOT NULL DEFAULT 0,
  `flagged_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_ledger_flags_txn` (`txn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  7. BUSINESS DOCUMENTS
--  ---------------------------------------------------------------------------
--  quotation         : raised at order status pending
--  invoice           : confirmed / approved, debits the buyer's ledger
--  payment_credit_note : each payment against the invoice
--  receipt           : invoice fully paid
--  cancellation_credit_note : order cancelled
--  refund_credit_note: approved refund inside the escrow window
-- ============================================================================

CREATE TABLE IF NOT EXISTS `store_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  -- quotation | invoice | payment_credit_note | receipt | cancellation_credit_note | refund_credit_note
  `doc_type` varchar(40) NOT NULL DEFAULT '',
  `number` varchar(40) NOT NULL DEFAULT '',
  `order_id` int(11) NOT NULL DEFAULT 0,
  -- for credit notes, the invoice they apply to
  `invoice_id` int(11) NOT NULL DEFAULT 0,
  `buyer_id` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `seller_user_id` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `currency` varchar(10) NOT NULL DEFAULT '',
  `subtotal` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `discount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `tax_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `balance` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `payment_reference` varchar(120) NOT NULL DEFAULT '',
  `payment_method` varchar(40) NOT NULL DEFAULT '',
  `reason` varchar(500) NOT NULL DEFAULT '',
  `verify_code` varchar(40) NOT NULL DEFAULT '',
  `meta` text DEFAULT NULL,
  `emailed_at` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_documents_number` (`number`),
  KEY `idx_store_documents_order` (`order_id`,`doc_type`),
  KEY `idx_store_documents_type` (`doc_type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_document_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL DEFAULT 0,
  `description` varchar(300) NOT NULL DEFAULT '',
  `type` varchar(20) NOT NULL DEFAULT 'product',
  `qty` decimal(40,10) NOT NULL DEFAULT 1.0000000000,
  `unit_price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `service_date` varchar(120) NOT NULL DEFAULT '',
  `duration` varchar(120) NOT NULL DEFAULT '',
  `location` varchar(200) NOT NULL DEFAULT '',
  `specialist` varchar(150) NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_document_items_doc` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Monotonic per-type document sequence. Written under its own guard so
-- two concurrent issues cannot reuse a number.
CREATE TABLE IF NOT EXISTS `store_counters` (
  `name` varchar(60) NOT NULL,
  `value` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  8. EARNINGS, ESCROW, NOTIFICATIONS
-- ============================================================================

-- One row per settled order capturing the seller's net (post-commission)
-- amount and when it becomes withdrawable. Released to users.balance by the
-- store cron once escrow_release_at passes.
CREATE TABLE IF NOT EXISTS `store_earnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `seller_user_id` int(11) NOT NULL DEFAULT 0,
  `currency` varchar(10) NOT NULL DEFAULT '',
  `gross` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `commission` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `net` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `status` varchar(20) NOT NULL DEFAULT 'escrow',
  `release_at` int(11) NOT NULL DEFAULT 0,
  `released_at` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_earnings_order` (`order_id`),
  KEY `idx_store_earnings_status` (`status`,`release_at`),
  KEY `idx_store_earnings_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `store_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  -- buyer | seller | admin
  `role` varchar(20) NOT NULL DEFAULT 'buyer',
  `order_id` int(11) NOT NULL DEFAULT 0,
  `type` varchar(60) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL DEFAULT '',
  `body` varchar(1000) NOT NULL DEFAULT '',
  `seen` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_store_notifications_user` (`user_id`,`seen`),
  KEY `idx_store_notifications_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscriber membership benefits, one row per seller.
CREATE TABLE IF NOT EXISTS `store_membership_benefits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL DEFAULT 0,
  `free_delivery` tinyint(4) NOT NULL DEFAULT 0,
  `discount_pct` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `addon_text` varchar(300) NOT NULL DEFAULT '',
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_membership_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  9. IDEMPOTENT COLUMN GUARDS ON EXISTING TABLES
-- ============================================================================

-- JPesa rows carry the store context so the reconciliation cron can settle a
-- store payment without disturbing the existing wallet/pro/rent/subscribe flow.
SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `charged_amount` decimal(16,4) NOT NULL DEFAULT 0.0000',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'charged_amount'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `purchase_type` varchar(30) NOT NULL DEFAULT ''''',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'purchase_type'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `video_id` int(11) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'video_id'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `target_user_id` int(11) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'target_user_id'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `order_id` int(11) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'order_id'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `fulfilled` tinyint(4) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'fulfilled'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `provider_status` varchar(60) NOT NULL DEFAULT ''''',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'provider_status'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `provider_message` varchar(500) NOT NULL DEFAULT ''''',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'provider_message'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `reconciliation_attempts` int(11) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'reconciliation_attempts'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

SET @pt_add := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `jpesa_payments` ADD COLUMN `last_checked_at` int(11) NOT NULL DEFAULT 0',
    'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jpesa_payments' AND COLUMN_NAME = 'last_checked_at'
);
PREPARE pt_stmt FROM @pt_add; EXECUTE pt_stmt; DEALLOCATE PREPARE pt_stmt;

-- ============================================================================
--  10. CHART OF ACCOUNTS
-- ============================================================================

INSERT INTO `store_ledger_accounts` (`code`,`name`,`type`,`is_user_account`,`created_at`) VALUES
  ('1100','Accounts Receivable (Buyers)','asset',1,UNIX_TIMESTAMP()),
  ('1200','Cash / JPesa Clearing','asset',0,UNIX_TIMESTAMP()),
  ('1300','Escrow - Seller Funds Held','liability',0,UNIX_TIMESTAMP()),
  ('2100','Customer Credits / Advance Payments','liability',1,UNIX_TIMESTAMP()),
  ('2200','Seller Payable','liability',1,UNIX_TIMESTAMP()),
  ('2300','Refunds Payable','liability',0,UNIX_TIMESTAMP()),
  ('2400','Tax Payable','liability',0,UNIX_TIMESTAMP()),
  ('2500','Commission Payable','liability',0,UNIX_TIMESTAMP()),
  ('2600','Delivery Fees Payable','liability',0,UNIX_TIMESTAMP()),
  ('4000','Platform Commission Revenue','revenue',0,UNIX_TIMESTAMP()),
  ('4100','Platform Delivery Revenue','revenue',0,UNIX_TIMESTAMP()),
  ('5000','Refunds Granted','expense',0,UNIX_TIMESTAMP()),
  ('5100','Chargebacks / Bad Debt','expense',0,UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `type` = VALUES(`type`), `is_user_account` = VALUES(`is_user_account`);

-- ============================================================================
--  11. CATEGORY SEED (commission 3%..15%, return policy, approval default)
-- ============================================================================

INSERT INTO `store_categories`
  (`parent_id`,`name`,`slug`,`kind`,`commission_bps`,`requires_return`,`return_days`,`requires_approval`,`is_active`,`sort_order`,`created_at`)
SELECT 0, s.name, s.slug, s.kind, s.bps, s.ret, s.days, s.appr, 1, s.ord, UNIX_TIMESTAMP()
FROM (
  -- Physical / digital goods
  SELECT 'Electronics & Accessories' AS name, 'electronics-accessories' AS slug, 'product' AS kind, 500 AS bps, 1 AS ret, 7 AS days, 1 AS appr, 10 AS ord
  UNION ALL SELECT 'Mobile Phones & Tablets','mobile-phones-tablets','product',400,1,7,1,20
  UNION ALL SELECT 'Computers & Laptops','computers-laptops','product',400,1,7,1,30
  UNION ALL SELECT 'Home & Kitchen','home-kitchen','product',800,1,7,1,40
  UNION ALL SELECT 'Fashion & Apparel','fashion-apparel','product',1000,1,14,1,50
  UNION ALL SELECT 'Shoes & Footwear','shoes-footwear','product',1000,1,14,1,60
  UNION ALL SELECT 'Beauty & Personal Care','beauty-personal-care','product',1200,1,7,1,70
  UNION ALL SELECT 'Health & Wellness','health-wellness','product',1200,1,7,1,80
  UNION ALL SELECT 'Baby & Kids','baby-kids','product',800,1,14,1,90
  UNION ALL SELECT 'Groceries & Food','groceries-food','product',1000,0,0,0,100
  UNION ALL SELECT 'Beverages','beverages','product',1000,0,0,0,110
  UNION ALL SELECT 'Books & Stationery','books-stationery','product',800,1,14,1,120
  UNION ALL SELECT 'Music & Instruments','music-instruments','product',800,1,7,1,130
  UNION ALL SELECT 'Sports & Outdoors','sports-outdoors','product',800,1,7,1,140
  UNION ALL SELECT 'Toys & Games','toys-games','product',800,1,14,1,150
  UNION ALL SELECT 'Furniture & Decor','furniture-decor','product',800,1,7,1,160
  UNION ALL SELECT 'Automotive & Parts','automotive-parts','product',500,1,7,1,170
  UNION ALL SELECT 'Tools & Hardware','tools-hardware','product',600,1,7,1,180
  UNION ALL SELECT 'Art & Collectibles','art-collectibles','product',1200,0,0,1,190
  UNION ALL SELECT 'Jewellery & Watches','jewellery-watches','product',1200,0,0,1,200
  UNION ALL SELECT 'Pet Supplies','pet-supplies','product',800,1,7,1,210
  UNION ALL SELECT 'Garden & Outdoor','garden-outdoor','product',800,0,0,1,220
  UNION ALL SELECT 'Office & Business','office-business','product',800,1,7,1,230
  UNION ALL SELECT 'Photography & Video Gear','photography-video-gear','product',500,1,7,1,240
  UNION ALL SELECT 'Gaming & Consoles','gaming-consoles','product',500,1,7,1,250
  UNION ALL SELECT 'Handmade & Crafts','handmade-crafts','product',1200,1,7,1,260
  UNION ALL SELECT 'Digital Downloads','digital-downloads','product',1500,0,0,0,270
  UNION ALL SELECT 'Software & Licences','software-licences','product',1500,0,0,0,280
  UNION ALL SELECT 'Gift Cards & Vouchers','gift-cards-vouchers','product',1000,0,0,0,290
  -- Services (bookable)
  UNION ALL SELECT 'Software Development & IT','software-development-it','service',500,0,0,1,300
  UNION ALL SELECT 'Web & App Development','web-app-development','service',500,0,0,1,310
  UNION ALL SELECT 'Design & Branding','design-branding','service',800,0,0,1,320
  UNION ALL SELECT 'Digital Marketing & SEO','digital-marketing-seo','service',800,0,0,1,330
  UNION ALL SELECT 'Writing & Translation','writing-translation','service',1000,0,0,1,340
  UNION ALL SELECT 'Photography & Videography','photography-videography','service',800,0,0,1,350
  UNION ALL SELECT 'Music & Audio Production','music-audio-production','service',800,0,0,1,360
  UNION ALL SELECT 'Tutoring & Education','tutoring-education','service',800,0,0,0,370
  UNION ALL SELECT 'Coaching & Consulting','coaching-consulting','service',800,0,0,1,380
  UNION ALL SELECT 'Events & Entertainment','events-entertainment','service',1000,0,0,1,390
  UNION ALL SELECT 'Health & Medical Services','health-medical-services','service',1000,0,0,1,400
  UNION ALL SELECT 'Wellness & Spa','wellness-spa','service',1000,0,0,1,410
  UNION ALL SELECT 'Beauty & Salon','beauty-salon','service',1200,0,0,1,420
  UNION ALL SELECT 'Fitness & Personal Training','fitness-personal-training','service',1000,0,0,1,430
  UNION ALL SELECT 'Home & Repair Services','home-repair-services','service',600,0,0,1,440
  UNION ALL SELECT 'Cleaning Services','cleaning-services','service',1000,0,0,1,450
  UNION ALL SELECT 'Delivery & Errands','delivery-errands','service',800,0,0,1,460
  UNION ALL SELECT 'Legal & Professional','legal-professional','service',500,0,0,1,470
  UNION ALL SELECT 'Accounting & Tax','accounting-tax','service',500,0,0,1,480
  UNION ALL SELECT 'Real Estate Services','real-estate-services','service',500,0,0,1,490
  UNION ALL SELECT 'Transportation & Logistics','transportation-logistics','service',500,0,0,1,500
  UNION ALL SELECT 'Food & Catering Services','food-catering-services','service',1000,0,0,1,510
  UNION ALL SELECT 'Travel & Tours','travel-tours','service',800,0,0,1,520
  UNION ALL SELECT 'Pet Services','pet-services','service',800,0,0,1,530
  UNION ALL SELECT 'Childcare & Babysitting','childcare-babysitting','service',1000,0,0,1,540
  UNION ALL SELECT 'Marketing & Advertising','marketing-advertising','service',800,0,0,1,550
) AS s
WHERE NOT EXISTS (SELECT 1 FROM `store_categories` c WHERE c.slug = s.slug);

-- ============================================================================
--  12. CONFIG KEYS
-- ============================================================================

INSERT INTO `config` (`name`,`value`) SELECT 'store_enabled','1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_enabled');
INSERT INTO `config` (`name`,`value`) SELECT 'store_currency','' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_currency');
INSERT INTO `config` (`name`,`value`) SELECT 'store_commission_bps','800' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_commission_bps');
INSERT INTO `config` (`name`,`value`) SELECT 'store_commission_min_bps','300' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_commission_min_bps');
INSERT INTO `config` (`name`,`value`) SELECT 'store_commission_max_bps','1500' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_commission_max_bps');
INSERT INTO `config` (`name`,`value`) SELECT 'store_default_escrow_days','7' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_default_escrow_days');
INSERT INTO `config` (`name`,`value`) SELECT 'store_require_seller_approval','1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_require_seller_approval');
INSERT INTO `config` (`name`,`value`) SELECT 'store_require_product_approval','1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_require_product_approval');
INSERT INTO `config` (`name`,`value`) SELECT 'store_terms_url','https://jackisa.com/terms/terms' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_terms_url');
INSERT INTO `config` (`name`,`value`) SELECT 'store_terms_version','1.0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_terms_version');
INSERT INTO `config` (`name`,`value`) SELECT 'store_stream_terms_version','1.0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_stream_terms_version');
INSERT INTO `config` (`name`,`value`) SELECT 'store_refund_version','1.0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_refund_version');
INSERT INTO `config` (`name`,`value`) SELECT 'store_privacy_version','1.0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_privacy_version');
INSERT INTO `config` (`name`,`value`) SELECT 'store_tax_rate','0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_tax_rate');
INSERT INTO `config` (`name`,`value`) SELECT 'store_refund_url','https://jackisa.com/terms/refund-policy' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_refund_url');
INSERT INTO `config` (`name`,`value`) SELECT 'store_privacy_url','https://jackisa.com/terms/privacy-policy' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_privacy_url');
INSERT INTO `config` (`name`,`value`) SELECT 'store_company_name','Jackisa Technologies Limited' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_company_name');
INSERT INTO `config` (`name`,`value`) SELECT 'store_company_tin','' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_company_tin');
INSERT INTO `config` (`name`,`value`) SELECT 'store_company_address','Kampala, Uganda' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_company_address');
INSERT INTO `config` (`name`,`value`) SELECT 'store_company_email','' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_company_email');
INSERT INTO `config` (`name`,`value`) SELECT 'store_company_phone','' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_company_phone');
INSERT INTO `config` (`name`,`value`) SELECT 'store_seller_notify_orders','1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_seller_notify_orders');
INSERT INTO `config` (`name`,`value`) SELECT 'store_doc_validity_days','7' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name`='store_doc_validity_days');

-- ============================================================================
--  13. LANGUAGE STRINGS
-- ============================================================================

INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store','','Store' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_products','','Products' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_products');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_services','','Services' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_services');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_my_store','','My Store' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_my_store');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_orders','','Orders' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_orders');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_bookings','','Bookings' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_bookings');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_add_product','','Add Product' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_add_product');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_add_service','','Add Service' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_add_service');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_order_now','','Order Now' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_order_now');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_book_now','','Book Now' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_book_now');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_reviews','','Reviews' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_reviews');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_write_review','','Write a review' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_write_review');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_agree_terms','','I agree to the Store Terms, Refund Policy, Stream Terms and Privacy Policy' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_agree_terms');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_accept_terms_first','','Accept the Store policies before continuing.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_accept_terms_first');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_login_required','','You must be logged in to order or book.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_login_required');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_attach','','Attach merchandise' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_attach');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_shop_this','','Shop this video' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_shop_this');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_free_delivery','','Free delivery for subscribers' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_free_delivery');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_out_of_stock','','Out of stock' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_out_of_stock');
INSERT INTO `langs` (`lang_key`,`type`,`english`) SELECT 'store_delivery_fee','','Delivery fee' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key`='store_delivery_fee');

-- ============================================================================
--  14. IMMUTABILITY GUARDS
--  ---------------------------------------------------------------------------
--  Accounting records may not be updated or deleted unless the caller opts in
--  with:  SET @pt_allow_immutable = 1;   (PT_DbGuardedRun in PHP).
--  Orders may transition status, so only their deletion is blocked.
-- ============================================================================

DROP TRIGGER IF EXISTS `trg_store_ledger_block_update`;
DROP TRIGGER IF EXISTS `trg_store_ledger_block_delete`;
DROP TRIGGER IF EXISTS `trg_store_documents_block_update`;
DROP TRIGGER IF EXISTS `trg_store_documents_block_delete`;
DROP TRIGGER IF EXISTS `trg_store_doc_items_block_update`;
DROP TRIGGER IF EXISTS `trg_store_doc_items_block_delete`;
DROP TRIGGER IF EXISTS `trg_store_earnings_block_update`;
DROP TRIGGER IF EXISTS `trg_store_earnings_block_delete`;
DROP TRIGGER IF EXISTS `trg_store_order_items_block_delete`;
DROP TRIGGER IF EXISTS `trg_store_orders_block_delete`;
DROP TRIGGER IF EXISTS `trg_store_reviews_block_delete`;

DELIMITER $$

CREATE TRIGGER `trg_store_ledger_block_update` BEFORE UPDATE ON `store_ledger_entries`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The store ledger is immutable; entries cannot be edited.';
  END IF;
END$$

CREATE TRIGGER `trg_store_ledger_block_delete` BEFORE DELETE ON `store_ledger_entries`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'The store ledger is immutable; entries cannot be deleted.';
  END IF;
END$$

CREATE TRIGGER `trg_store_documents_block_update` BEFORE UPDATE ON `store_documents`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store business documents are immutable; issue a new document instead.';
  END IF;
END$$

CREATE TRIGGER `trg_store_documents_block_delete` BEFORE DELETE ON `store_documents`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store business documents are immutable; they cannot be deleted.';
  END IF;
END$$

CREATE TRIGGER `trg_store_doc_items_block_update` BEFORE UPDATE ON `store_document_items`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store document lines are immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_store_doc_items_block_delete` BEFORE DELETE ON `store_document_items`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store document lines are immutable.';
  END IF;
END$$

-- Earnings are only ever flagged released; that is an intentional, guarded
-- state change. Physical deletion is never allowed.
CREATE TRIGGER `trg_store_earnings_block_delete` BEFORE DELETE ON `store_earnings`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store earnings are append-only and cannot be deleted.';
  END IF;
END$$

-- Orders legitimately move through statuses (that is guarded and logged), but
-- an order is never destroyed.
CREATE TRIGGER `trg_store_orders_block_delete` BEFORE DELETE ON `store_orders`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store orders cannot be deleted; cancel them instead.';
  END IF;
END$$

CREATE TRIGGER `trg_store_order_items_block_delete` BEFORE DELETE ON `store_order_items`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store order lines cannot be deleted.';
  END IF;
END$$

CREATE TRIGGER `trg_store_reviews_block_delete` BEFORE DELETE ON `store_reviews`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Store reviews are immutable; hide them instead of deleting.';
  END IF;
END$$

DELIMITER ;

-- ============================================================================
--  END
-- ============================================================================