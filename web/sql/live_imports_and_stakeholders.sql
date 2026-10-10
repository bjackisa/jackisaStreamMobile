-- ============================================================================
--  Jackisa Stream - Data model additions
--  ---------------------------------------------------------------------------
--  1. Imported livestream sources (YouTube live + generic remote live sources)
--  2. Stakeholders, share engine, metric/period snapshots and dividend ledger
--  3. Immutability guards for accounting / finance records
--  4. Config keys and language strings used by the new features
--
--  Safe to run more than once: every statement is idempotent.
-- ============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================================
--  1. IMPORTED LIVESTREAMS
-- ============================================================================

-- One row per imported livestream. The livestream itself still lives in
-- `videos` (type = 'live') so it shows up in the feed exactly like an Agora
-- stream. This table only keeps the source-specific bookkeeping.
CREATE TABLE IF NOT EXISTS `live_imports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `video_id` int(11) NOT NULL DEFAULT 0,
  `owner_id` int(11) NOT NULL DEFAULT 0,
  -- youtube  : imported YouTube live, becomes a normal video when it ends
  -- remote   : any other live URL (m3u8 / mp4 / embed), gone when it ends
  `source_type` varchar(20) NOT NULL DEFAULT 'remote',
  -- provider slug (youtube, twitch, generic, ...)
  `provider` varchar(40) NOT NULL DEFAULT 'generic',
  -- the live URL the user pasted
  `source_url` varchar(2000) NOT NULL DEFAULT '',
  -- provider-native id (YouTube video id etc.)
  `source_ref` varchar(191) NOT NULL DEFAULT '',
  -- embeddable URL actually handed to the player while the stream is live
  `playback_url` varchar(2000) NOT NULL DEFAULT '',
  -- 0 = not live yet, 1 = confirmed live, 2 = ended
  `is_live` tinyint(4) NOT NULL DEFAULT 0,
  -- 1 = provider keeps the recording after the stream (YouTube), keep video
  -- 0 = nothing is kept after the stream ends, remove the placeholder
  `keep_after_end` tinyint(4) NOT NULL DEFAULT 0,
  `last_checked` int(11) NOT NULL DEFAULT 0,
  `live_started_at` int(11) NOT NULL DEFAULT 0,
  `ended_at` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_live_imports_video` (`video_id`),
  KEY `idx_live_imports_source` (`source_type`,`source_ref`),
  KEY `idx_live_imports_live` (`is_live`),
  KEY `idx_live_imports_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Source-awareness on the video row itself. Kept as small varchar columns so
-- existing queries and the feed card template stay untouched. Added through
-- information_schema so the migration is safe to re-run on MySQL/MariaDB.
SET @pt_has_live_source := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'videos' AND COLUMN_NAME = 'live_source'
);
SET @pt_alter_videos := IF(@pt_has_live_source = 0,
  'ALTER TABLE `videos`
     ADD COLUMN `live_source` varchar(20) NOT NULL DEFAULT '''',
     ADD COLUMN `live_source_ref` varchar(191) NOT NULL DEFAULT ''''',
  'SELECT 1');
PREPARE pt_stmt FROM @pt_alter_videos;
EXECUTE pt_stmt;
DEALLOCATE PREPARE pt_stmt;

-- ============================================================================
--  2. STAKEHOLDERS / SHARE ENGINE / DIVIDENDS
-- ============================================================================

-- Stakeholders. A stakeholder is a registered user marked as one in the
-- dashboard. `current_shares` is a derived cache of the sum of the immutable
-- share events (see stakeholder_share_events); it is only ever written by the
-- share engine. `current_percent` is shares / total shares * 100.
CREATE TABLE IF NOT EXISTS `stakeholders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `role` varchar(100) NOT NULL DEFAULT 'contributor',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `current_shares` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `current_percent` decimal(20,12) NOT NULL DEFAULT 0.000000000000,
  `last_settled_period_id` int(11) NOT NULL DEFAULT 0,
  `lifecycle_earnings` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `lifecycle_withdrawn` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `payout_wallet` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `payout_balance` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `notes` text,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stakeholders_user` (`user_id`),
  KEY `idx_stakeholders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only ledger of every share movement. A percentage change must never
-- rewrite history, so any adjustment is a new row. current_shares is the sum.
CREATE TABLE IF NOT EXISTS `stakeholder_share_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `shares_delta` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `shares_after` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `reason` varchar(100) NOT NULL DEFAULT '',
  `metrics_snapshot` longtext,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_share_events_stakeholder` (`stakeholder_id`),
  KEY `idx_share_events_user` (`user_id`),
  KEY `idx_share_events_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Metric catalogue. Each row describes one deep determinant, its formula and
-- its weight in the share model. Editable, so the model can grow without
-- touching code.
CREATE TABLE IF NOT EXISTS `stakeholder_metric_defs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(60) NOT NULL DEFAULT '',
  `label` varchar(160) NOT NULL DEFAULT '',
  `unit` varchar(40) NOT NULL DEFAULT 'number',
  -- higher_is_better | lower_is_better
  `direction` varchar(20) NOT NULL DEFAULT 'higher_is_better',
  -- PHP callable / formula identifier handled by PT_StakeComputeMetric()
  `formula` varchar(60) NOT NULL DEFAULT 'manual',
  `default_weight` decimal(20,10) NOT NULL DEFAULT 1.0000000000,
  `is_core` tinyint(4) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_metric_key` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-stakeholder weight profile. Lets the same metric count differently for
-- different stakeholders (e.g. an engineer vs an investor).
CREATE TABLE IF NOT EXISTS `stakeholder_metric_weights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `metric_key` varchar(60) NOT NULL DEFAULT '',
  `weight` decimal(20,10) NOT NULL DEFAULT 1.0000000000,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stakeholder_metric` (`stakeholder_id`,`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only snapshot of every metric for a settlement period.
CREATE TABLE IF NOT EXISTS `stakeholder_metric_values` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_id` int(11) NOT NULL DEFAULT 0,
  `metric_key` varchar(60) NOT NULL DEFAULT '',
  `raw_value` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `score` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `weight` decimal(20,10) NOT NULL DEFAULT 1.0000000000,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period_metric` (`period_id`,`metric_key`),
  KEY `idx_metric_values_key` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One settlement period = one share recalculation + one dividend split of the
-- period's revenue. 60% of the period revenue is distributed (see config
-- stake_distributable_ratio).
CREATE TABLE IF NOT EXISTS `stakeholder_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_key` varchar(60) NOT NULL DEFAULT '',
  `period_start` int(11) NOT NULL DEFAULT 0,
  `period_end` int(11) NOT NULL DEFAULT 0,
  `total_revenue` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `distributable_revenue` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `total_shares` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(11) NOT NULL DEFAULT 'USD',
  `metrics_snapshot` longtext,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_at` int(11) NOT NULL DEFAULT 0,
  `settled_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_period_key` (`period_key`),
  KEY `idx_period_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only dividend ledger. Every amount the platform's 60% pool grants to
-- a stakeholder. Rewriting history is blocked by a trigger below.
CREATE TABLE IF NOT EXISTS `stake_earnings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `period_id` int(11) NOT NULL DEFAULT 0,
  `period_start` int(11) NOT NULL DEFAULT 0,
  `period_end` int(11) NOT NULL DEFAULT 0,
  -- share and percent locked at the moment the earning was recorded
  `shares` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `share_percent` decimal(20,12) NOT NULL DEFAULT 0.000000000000,
  `pool_revenue` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `distributable_revenue` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(11) NOT NULL DEFAULT 'USD',
  `description` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'credited',
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stake_period` (`stakeholder_id`,`period_id`),
  KEY `idx_stake_earnings_user` (`user_id`),
  KEY `idx_stake_earnings_time` (`period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Manual / automatic revenue & asset entries. Lets the model pick up money
-- that never passes through videos_transactions: ad receivables, corporate
-- bank cash, premium domains, SEO value, partnership income, ...
CREATE TABLE IF NOT EXISTS `stake_revenue_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  -- revenue   : counts toward the distributable pool
  -- asset     : counts toward company value (metric drivers), not the pool
  `entry_type` varchar(20) NOT NULL DEFAULT 'revenue',
  `category` varchar(60) NOT NULL DEFAULT 'other',
  `label` varchar(200) NOT NULL DEFAULT '',
  `amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(11) NOT NULL DEFAULT 'USD',
  `entry_time` int(11) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_revenue_entries_type` (`entry_type`,`entry_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only ledger of stake money actually paid out. Each row records the
-- dividend rows it consumes, so withdrawing never mutates stake_earnings.
CREATE TABLE IF NOT EXISTS `stake_withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `withdrawal_request_id` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(11) NOT NULL DEFAULT 'USD',
  -- JSON array of {stake_earning_id, amount} consumed by this withdrawal
  `allocations` longtext,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_stake_withdrawals_user` (`user_id`),
  KEY `idx_stake_withdrawals_request` (`withdrawal_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  3. SEED METRIC DEFINITIONS
-- ============================================================================

INSERT INTO `stakeholder_metric_defs`
  (`metric_key`, `label`, `unit`, `direction`, `formula`, `default_weight`, `is_core`, `sort_order`, `updated_at`)
SELECT * FROM (
  SELECT 'total_revenue'        AS metric_key, 'Platform Revenue'               AS label, 'money'  AS unit, 'higher_is_better' AS direction, 'total_revenue'      AS formula, 2.0000000000 AS default_weight, 1 AS is_core, 10 AS sort_order, UNIX_TIMESTAMP() AS updated_at UNION ALL
  SELECT 'mrr', 'Monthly Recurring Revenue', 'money', 'higher_is_better', 'mrr', 2.0000000000, 1, 20, UNIX_TIMESTAMP() UNION ALL
  SELECT 'arr', 'Annual Recurring Revenue', 'money', 'higher_is_better', 'arr', 2.0000000000, 1, 30, UNIX_TIMESTAMP() UNION ALL
  SELECT 'dau', 'Daily Active Users', 'number', 'higher_is_better', 'dau', 1.5000000000, 1, 40, UNIX_TIMESTAMP() UNION ALL
  SELECT 'mau', 'Monthly Active Users', 'number', 'higher_is_better', 'mau', 1.5000000000, 1, 50, UNIX_TIMESTAMP() UNION ALL
  SELECT 'churn_rate', 'Churn Rate', 'percent', 'lower_is_better', 'churn_rate', 1.5000000000, 1, 60, UNIX_TIMESTAMP() UNION ALL
  SELECT 'cac', 'Customer Acquisition Cost', 'money', 'lower_is_better', 'cac', 1.2000000000, 1, 70, UNIX_TIMESTAMP() UNION ALL
  SELECT 'ltv', 'Lifetime Value', 'money', 'higher_is_better', 'ltv', 1.2000000000, 1, 80, UNIX_TIMESTAMP() UNION ALL
  SELECT 'ltv_cac_ratio', 'LTV / CAC Ratio', 'ratio', 'higher_is_better', 'ltv_cac_ratio', 1.5000000000, 1, 90, UNIX_TIMESTAMP() UNION ALL
  SELECT 'network_value', 'Compounding Platform Value', 'money', 'higher_is_better', 'network_value', 1.3000000000, 1, 100, UNIX_TIMESTAMP() UNION ALL
  SELECT 'proprietary_data_value', 'Proprietary Data Value', 'money', 'higher_is_better', 'proprietary_data_value', 1.0000000000, 0, 110, UNIX_TIMESTAMP() UNION ALL
  SELECT 'accounts_receivable', 'Accounts Receivable', 'money', 'higher_is_better', 'accounts_receivable', 1.0000000000, 0, 120, UNIX_TIMESTAMP() UNION ALL
  SELECT 'cash_reserves', 'Corporate Cash Reserves', 'money', 'higher_is_better', 'cash_reserves', 1.0000000000, 0, 130, UNIX_TIMESTAMP() UNION ALL
  SELECT 'domain_seo_value', 'Domain & SEO Value', 'money', 'higher_is_better', 'domain_seo_value', 0.8000000000, 0, 140, UNIX_TIMESTAMP()
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `stakeholder_metric_defs` LIMIT 1);

INSERT INTO `config` (`name`, `value`)
SELECT 'stake_total_shares', '10000'
WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_total_shares');

INSERT INTO `config` (`name`, `value`)
SELECT 'stake_distributable_ratio', '0.60'
WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_distributable_ratio');

INSERT INTO `config` (`name`, `value`)
SELECT 'stake_share_smoothing', '1.00'
WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_share_smoothing');

INSERT INTO `config` (`name`, `value`)
SELECT 'stake_cash_reserves', '0'
WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_cash_reserves');

INSERT INTO `config` (`name`, `value`)
SELECT 'stake_domain_seo_value', '0'
WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_domain_seo_value');

INSERT INTO `config` (`name`, `value`)
SELECT 'stake_monthly_marketing_spend', '0'
WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_monthly_marketing_spend');

-- ============================================================================
--  4. LANGUAGE STRINGS
-- ============================================================================

-- Each key is inserted only when missing, so new keys keep working even if
-- some already exist. Ids come from the table AUTO_INCREMENT.
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stakeholders', '', 'Stakeholders' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stakeholders');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake', '', 'Stake' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'dividends', '', 'Dividends' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'dividends');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'dividend', '', 'Dividend' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'dividend');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'your_dividend_share', '', 'Earnings from {percent} stake in {sitename}.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'your_dividend_share');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'your_stake_balance', '', 'Stake Balance' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'your_stake_balance');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_shares', '', 'Shares' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_shares');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_percentage', '', 'Stake %' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_percentage');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_earnings', '', 'Stake Earnings' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_earnings');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_lifetime_earnings', '', 'Lifetime Stake Earnings' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_lifetime_earnings');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_withdrawn', '', 'Withdrawn From Stake' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_withdrawn');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_available', '', 'Available From Stake' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_available');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_recalculate', '', 'Recalculate' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_recalculate');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_settle_period', '', 'Settle Period' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_settle_period');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_mark_user', '', 'Mark as Stakeholder' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_mark_user');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_unmark_user', '', 'Revoke Stakeholder' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_unmark_user');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_assign_shares', '', 'Assign Shares' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_assign_shares');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_user_not_stakeholder', '', 'This user is not a stakeholder.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_user_not_stakeholder');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_invalid_shares', '', 'Please enter a valid share amount.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_invalid_shares');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_no_stakeholders', '', 'No stakeholders yet.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_no_stakeholders');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_total_shares', '', 'Total Shares' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_total_shares');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_distributable_pool', '', 'Distributable Pool (60%)' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_distributable_pool');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_revenue', '', 'Revenue' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_revenue');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_breakdown', '', 'Breakdown per Stakeholder' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_breakdown');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_settings', '', 'Stakeholder Settings' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_settings');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_adds_to_withdrawable', '', 'Your stake dividends are added to the amount you can withdraw.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_adds_to_withdrawable');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_withdrawable_note', '', 'You can withdraw up to {amount} including your stake dividends.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_withdrawable_note');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_settled_period', '', 'Last Settled Period' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_settled_period');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'import_live_stream', '', 'Import Livestream' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'import_live_stream');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'import_live_desc', '', 'Paste a live stream link (YouTube live, HLS/m3u8, mp4) and it plays in the app.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'import_live_desc');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_source_url', '', 'Live Stream URL' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_source_url');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_source_type', '', 'Source Type' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_source_type');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_source_youtube', '', 'YouTube Live' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_source_youtube');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_source_remote', '', 'Other Live Source' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_source_remote');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_source_youtube_help', '', 'When the YouTube stream ends, the recording stays on the platform as a video.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_source_youtube_help');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_source_remote_help', '', 'Remote sources leave nothing behind: when the stream ends the video is removed.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_source_remote_help');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_import_success', '', 'Livestream imported. It is now live on the platform.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_import_success');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_import_not_live', '', 'That link is not live right now.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_import_not_live');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_import_invalid', '', 'Please enter a valid live stream link.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_import_invalid');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_import_gone', '', 'This livestream has ended and is no longer available.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_import_gone');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'live_imports', '', 'Imported Livestreams' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'live_imports');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'my_livestreams', '', 'My Livestreams' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'my_livestreams');

-- ============================================================================
--  5. DAILY STAKE ENGINE (layered, fully automatic)
--  ---------------------------------------------------------------------------
--  The daily engine runs from cronjob.php. Nobody enters dividends, growth
--  position, valuation or loyalty by hand: the system measures the platform
--  every day, issues/redeems units, computes time-weighted shares and credits
--  whole-unit payouts. The only human input is the number of shares a
--  stakeholder holds (and the automatic reinvest toggle).
-- ============================================================================

-- One row per day the engine processed. This is the published Growth Position
-- Card (Layer 20) plus the 60/40 split and valuation that drove the day.
CREATE TABLE IF NOT EXISTS `stake_daily_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_key` varchar(20) NOT NULL DEFAULT '',
  `run_start` int(11) NOT NULL DEFAULT 0,
  `run_end` int(11) NOT NULL DEFAULT 0,
  `gross_revenue` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `costs` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `net_revenue` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `stakeable_pool` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `app_pool` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `reserve_rate` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `reserve_retained` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `dividend_pool` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `growth_position` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `growth_stage` varchar(20) NOT NULL DEFAULT 'seed',
  `gpi` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `multiple` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `ceiling` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `smoothed_net` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `total_value` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `unit_price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `opening_units` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `issued_units` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `redeemed_units` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `closing_units` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `paid_total` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `hhi` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `effective_holders` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `fit_r2` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(11) NOT NULL DEFAULT 'USD',
  `status` varchar(20) NOT NULL DEFAULT 'closed',
  `card` longtext,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stake_daily_run` (`run_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only lot register (Layer 4). Loyalty belongs to the units, so every
-- acquisition is its own lot with its own age, channel and price.
CREATE TABLE IF NOT EXISTS `stake_lots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `units` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  -- GRANT | CAPITAL | REINVEST | LOYALTY_BONUS
  `channel` varchar(20) NOT NULL DEFAULT 'GRANT',
  `price_paid` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `cash_paid` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `lot_class` varchar(20) NOT NULL DEFAULT 'Community',
  `locked` tinyint(4) NOT NULL DEFAULT 0,
  `acquired_at` int(11) NOT NULL DEFAULT 0,
  `last_checked` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_stake_lots_stakeholder` (`stakeholder_id`),
  KEY `idx_stake_lots_user` (`user_id`),
  KEY `idx_stake_lots_channel` (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only lot movements. Redemptions sell the newest lots first (LIFO),
-- so a redemption is a negative row against specific lots.
CREATE TABLE IF NOT EXISTS `stake_lot_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lot_id` int(11) NOT NULL DEFAULT 0,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `units_delta` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  -- ACQUIRE | REDEEM
  `event_type` varchar(20) NOT NULL DEFAULT 'ACQUIRE',
  `unit_price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lot_events_lot` (`lot_id`),
  KEY `idx_lot_events_stakeholder` (`stakeholder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily accrual ledger (Layer 14). Append-only. `run_id` is the daily run that
-- produced the row, so re-running a day is idempotent.
CREATE TABLE IF NOT EXISTS `stake_daily_accruals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL DEFAULT 0,
  `run_key` varchar(20) NOT NULL DEFAULT '',
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `weight` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `share` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `accrual` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `debit` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `reinvested` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `paid` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `balance_after` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `unit_price` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `currency` varchar(11) NOT NULL DEFAULT 'USD',
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daily_accrual` (`run_key`,`stakeholder_id`),
  KEY `idx_daily_accrual_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Growth Position Index inputs (Layer 6.7), measured daily. Editable weights so
-- the health model can be tuned without code changes.
CREATE TABLE IF NOT EXISTS `stake_gpi_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(60) NOT NULL DEFAULT '',
  `label` varchar(160) NOT NULL DEFAULT '',
  `score` decimal(20,10) NOT NULL DEFAULT 0.0000000000,
  `weight` decimal(20,10) NOT NULL DEFAULT 1.0000000000,
  `raw_value` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gpi_metric` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Append-only adjustments (Layer 15). A late refund/chargeback is attributed
-- forward, never applied to past days.
CREATE TABLE IF NOT EXISTS `stake_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `origin_run_id` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `recovered` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `reason` varchar(160) NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_adjustments_stakeholder` (`stakeholder_id`),
  KEY `idx_adjustments_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Redemption requests queued by the liquidity gate (Layer 9.4). The engine
-- fills them first-in, first-served when capacity allows.
CREATE TABLE IF NOT EXISTS `stake_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `units_requested` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `units_filled` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `net_proceeds` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `status` varchar(20) NOT NULL DEFAULT 'queued',
  `requested_at` int(11) NOT NULL DEFAULT 0,
  `filled_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_redemptions_status` (`status`),
  KEY `idx_redemptions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Growth-journey telemetry written by the daily engine. Config values
-- (marketing spend, addressable users, ...) are measured here, not typed in.
CREATE TABLE IF NOT EXISTS `stake_growth_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(60) NOT NULL DEFAULT '',
  `metric_value` decimal(40,10) NOT NULL DEFAULT 0.0000000000,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_growth_metric` (`metric_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-stakeholder engine preferences. The only thing a user may toggle.
CREATE TABLE IF NOT EXISTS `stakeholder_prefs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` int(11) NOT NULL DEFAULT 0,
  `auto_reinvest` tinyint(4) NOT NULL DEFAULT 0,
  `reinvest_fraction` decimal(20,10) NOT NULL DEFAULT 1.0000000000,
  `updated_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stakeholder_prefs` (`stakeholder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Engine parameters (Layer 21). Seeded once; the daily engine reads them.
INSERT INTO `config` (`name`, `value`) SELECT 'stake_engine_enabled', '1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_engine_enabled');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_reserve_base', '0.05' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_reserve_base');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_reserve_sensitivity', '0.10' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_reserve_sensitivity');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_multiple_floor', '2' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_multiple_floor');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_multiple_peak', '6' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_multiple_peak');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_amort_days', '730' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_amort_days');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_issue_min', '0.01' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_issue_min');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_issue_max', '0.03' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_issue_max');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_loyalty_max', '0.25' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_loyalty_max');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_loyalty_tau', '365' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_loyalty_tau');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_tenure_max', '0.10' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_tenure_max');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_tenure_tau', '730' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_tenure_tau');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_boost_ceiling', '1.40' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_boost_ceiling');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_exit_fee_max', '0.05' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_exit_fee_max');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_exit_fee_tau', '180' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_exit_fee_tau');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_min_hold_days', '30' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_min_hold_days');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_concentration_cap', '0.30' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_concentration_cap');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_daily_issuance_cap', '0.005' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_daily_issuance_cap');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_min_payout', '1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_min_payout');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_reserve_target_days', '14' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_reserve_target_days');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_vol_window', '90' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_vol_window');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_growth_k', '0.01' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_growth_k');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_max_penetration', '0.05' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_max_penetration');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_net_margin', '0.35' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_net_margin');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_deferral_days', '0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_deferral_days');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_seed_price', '1' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_seed_price');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_lock_bonus', '0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_lock_bonus');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_class_founder', '1.0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_class_founder');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_class_investor', '1.0' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_class_investor');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_class_creator', '1.2' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_class_creator');
INSERT INTO `config` (`name`, `value`) SELECT 'stake_class_community', '0.8' WHERE NOT EXISTS (SELECT 1 FROM `config` WHERE `name` = 'stake_class_community');

-- Growth Position Index definitions (Layer 6.7).
INSERT INTO `stake_gpi_scores` (`metric_key`, `label`, `score`, `weight`, `raw_value`, `updated_at`)
SELECT * FROM (
  SELECT 'g1_momentum'    AS metric_key, 'Revenue momentum'      AS label, 0.0000000000 AS score, 0.2000000000 AS weight, 0.0000000000 AS raw_value, UNIX_TIMESTAMP() AS updated_at UNION ALL
  SELECT 'g2_acceleration', 'Acceleration',               0.0000000000, 0.1000000000, 0.0000000000, UNIX_TIMESTAMP() UNION ALL
  SELECT 'g3_subscribers',  'Subscriber growth',          0.0000000000, 0.1500000000, 0.0000000000, UNIX_TIMESTAMP() UNION ALL
  SELECT 'g4_retention',    'Retention',                  0.0000000000, 0.1500000000, 0.0000000000, UNIX_TIMESTAMP() UNION ALL
  SELECT 'g5_unit_economics','Unit economics',            0.0000000000, 0.1500000000, 0.0000000000, UNIX_TIMESTAMP() UNION ALL
  SELECT 'g6_gross_margin', 'Gross margin',               0.0000000000, 0.1000000000, 0.0000000000, UNIX_TIMESTAMP() UNION ALL
  SELECT 'g7_runway',       'Runway',                     0.0000000000, 0.1000000000, 0.0000000000, UNIX_TIMESTAMP() UNION ALL
  SELECT 'g8_freshness',    'Content freshness',          0.0000000000, 0.0500000000, 0.0000000000, UNIX_TIMESTAMP()
) AS gpi_seed
WHERE NOT EXISTS (SELECT 1 FROM `stake_gpi_scores` LIMIT 1);

-- Language strings for the automatic engine.
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_growth_position', '', 'Growth Position' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_growth_position');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_gpi', '', 'Growth Position Index' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_gpi');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_unit_price', '', 'Unit Price' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_unit_price');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_dividend_pool', '', 'Dividend Pool Today' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_dividend_pool');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_stage', '', 'Stage' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_stage');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_user_lookup', '', 'Look up a user' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_user_lookup');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_user_found', '', 'User found' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_user_found');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_user_missing', '', 'No user with that username.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_user_missing');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_shares_only_note', '', 'Enter the number of shares. The engine calculates everything else automatically.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_shares_only_note');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_auto_reinvest', '', 'Automatically reinvest dividends' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_auto_reinvest');
INSERT INTO `langs` (`lang_key`, `type`, `english`) SELECT 'stake_auto_note', '', 'Dividends, share dilution, loyalty and valuation are computed by the daily engine.' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `langs` WHERE `lang_key` = 'stake_auto_note');

-- ============================================================================
--  6. IMMUTABILITY GUARDS (accounting & finance records)
--  ---------------------------------------------------------------------------
--  Every trigger raises SQLSTATE 45000 unless the caller explicitly opts in
--  with:  SET @pt_allow_immutable = 1;
--  PT_DbSetImmutableGuard() in assets/includes/stakeholders.php does this.
-- ============================================================================

DROP TRIGGER IF EXISTS `trg_videos_block_delete`;
DROP TRIGGER IF EXISTS `trg_users_block_delete`;
DROP TRIGGER IF EXISTS `trg_vt_block_update`;
DROP TRIGGER IF EXISTS `trg_vt_block_delete`;
DROP TRIGGER IF EXISTS `trg_payments_block_update`;
DROP TRIGGER IF EXISTS `trg_payments_block_delete`;
DROP TRIGGER IF EXISTS `trg_ads_trans_block_update`;
DROP TRIGGER IF EXISTS `trg_ads_trans_block_delete`;
DROP TRIGGER IF EXISTS `trg_wr_block_delete`;
DROP TRIGGER IF EXISTS `trg_bank_block_update`;
DROP TRIGGER IF EXISTS `trg_bank_block_delete`;
DROP TRIGGER IF EXISTS `trg_share_events_block_update`;
DROP TRIGGER IF EXISTS `trg_share_events_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_earnings_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_earnings_block_delete`;
DROP TRIGGER IF EXISTS `trg_metric_values_block_update`;
DROP TRIGGER IF EXISTS `trg_metric_values_block_delete`;
DROP TRIGGER IF EXISTS `trg_periods_block_update`;
DROP TRIGGER IF EXISTS `trg_periods_block_delete`;
DROP TRIGGER IF EXISTS `trg_revenue_entries_block_update`;
DROP TRIGGER IF EXISTS `trg_revenue_entries_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_withdrawals_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_withdrawals_block_delete`;
DROP TRIGGER IF EXISTS `trg_stakeholders_block_delete`;
DROP TRIGGER IF EXISTS `trg_live_imports_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_daily_runs_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_daily_runs_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_lots_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_lots_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_lot_events_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_lot_events_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_daily_accruals_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_daily_accruals_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_adjustments_block_update`;
DROP TRIGGER IF EXISTS `trg_stake_adjustments_block_delete`;
DROP TRIGGER IF EXISTS `trg_stake_redemptions_block_delete`;

DELIMITER $$

-- videos / users may not be deleted unless a guarded server-side helper asked
-- for it (PT_DeleteVideo / PT_DeleteUser / ended remote imports). Natural
-- account and content deletion flows keep working through those helpers.
CREATE TRIGGER `trg_videos_block_delete` BEFORE DELETE ON `videos`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a video is blocked unless performed through the guarded delete helper.';
  END IF;
END$$
CREATE TRIGGER `trg_users_block_delete` BEFORE DELETE ON `users`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a user is blocked unless performed through the guarded delete helper.';
  END IF;
END$$

-- videos_transactions : earnings ledger, append-only.
CREATE TRIGGER `trg_vt_block_update` BEFORE UPDATE ON `videos_transactions`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a transaction is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_vt_block_delete` BEFORE DELETE ON `videos_transactions`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a transaction is blocked: the ledger is immutable.';
  END IF;
END$$

-- payments : append-only.
CREATE TRIGGER `trg_payments_block_update` BEFORE UPDATE ON `payments`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a payment is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_payments_block_delete` BEFORE DELETE ON `payments`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a payment is blocked: the ledger is immutable.';
  END IF;
END$$

-- ads_transactions : append-only.
CREATE TRIGGER `trg_ads_trans_block_update` BEFORE UPDATE ON `ads_transactions`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing an ad transaction is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_ads_trans_block_delete` BEFORE DELETE ON `ads_transactions`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting an ad transaction is blocked: the ledger is immutable.';
  END IF;
END$$

-- withdrawal_requests : never deleted, only their status may move.
CREATE TRIGGER `trg_wr_block_delete` BEFORE DELETE ON `withdrawal_requests`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a withdrawal request is blocked: the ledger is immutable.';
  END IF;
END$$

-- bank_receipts : append-only.
CREATE TRIGGER `trg_bank_block_update` BEFORE UPDATE ON `bank_receipts`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a bank receipt is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_bank_block_delete` BEFORE DELETE ON `bank_receipts`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a bank receipt is blocked: the ledger is immutable.';
  END IF;
END$$

-- Stakeholder ledgers : append-only.
CREATE TRIGGER `trg_share_events_block_update` BEFORE UPDATE ON `stakeholder_share_events`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a share event is blocked: the share ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_share_events_block_delete` BEFORE DELETE ON `stakeholder_share_events`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a share event is blocked: the share ledger is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_stake_earnings_block_update` BEFORE UPDATE ON `stake_earnings`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a dividend is blocked: the dividend ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_earnings_block_delete` BEFORE DELETE ON `stake_earnings`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a dividend is blocked: the dividend ledger is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_metric_values_block_update` BEFORE UPDATE ON `stakeholder_metric_values`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a metric snapshot is blocked: snapshots are immutable.';
END$$
CREATE TRIGGER `trg_metric_values_block_delete` BEFORE DELETE ON `stakeholder_metric_values`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a metric snapshot is blocked: snapshots are immutable.';
END$$

CREATE TRIGGER `trg_periods_block_update` BEFORE UPDATE ON `stakeholder_periods`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a settlement period is blocked.';
  END IF;
END$$
CREATE TRIGGER `trg_periods_block_delete` BEFORE DELETE ON `stakeholder_periods`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a settlement period is blocked.';
END$$

CREATE TRIGGER `trg_revenue_entries_block_update` BEFORE UPDATE ON `stake_revenue_entries`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a revenue entry is blocked: the ledger is immutable.';
END$$
CREATE TRIGGER `trg_revenue_entries_block_delete` BEFORE DELETE ON `stake_revenue_entries`
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a revenue entry is blocked: the ledger is immutable.';
END$$

CREATE TRIGGER `trg_stake_withdrawals_block_update` BEFORE UPDATE ON `stake_withdrawals`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a stake payout is blocked: the payout ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_withdrawals_block_delete` BEFORE DELETE ON `stake_withdrawals`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a stake payout is blocked: the payout ledger is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_stakeholders_block_delete` BEFORE DELETE ON `stakeholders`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a stakeholder is blocked: revoke the stake instead.';
  END IF;
END$$

CREATE TRIGGER `trg_live_imports_block_delete` BEFORE DELETE ON `live_imports`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a live import record is blocked.';
  END IF;
END$$

-- Daily engine records : append-only. A day is never edited or replayed.
CREATE TRIGGER `trg_stake_daily_runs_block_update` BEFORE UPDATE ON `stake_daily_runs`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a daily stake run is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_daily_runs_block_delete` BEFORE DELETE ON `stake_daily_runs`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a daily stake run is blocked: the ledger is immutable.';
  END IF;
END$$

-- Lots : units are never rewritten. A redemption is a separate lot event.
CREATE TRIGGER `trg_stake_lots_block_update` BEFORE UPDATE ON `stake_lots`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a lot is blocked: the lot register is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_lots_block_delete` BEFORE DELETE ON `stake_lots`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a lot is blocked: the lot register is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_stake_lot_events_block_update` BEFORE UPDATE ON `stake_lot_events`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing a lot event is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_lot_events_block_delete` BEFORE DELETE ON `stake_lot_events`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a lot event is blocked: the ledger is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_stake_daily_accruals_block_update` BEFORE UPDATE ON `stake_daily_accruals`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing an accrual is blocked: the dividend ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_daily_accruals_block_delete` BEFORE DELETE ON `stake_daily_accruals`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting an accrual is blocked: the dividend ledger is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_stake_adjustments_block_update` BEFORE UPDATE ON `stake_adjustments`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Editing an adjustment is blocked: the ledger is immutable.';
  END IF;
END$$
CREATE TRIGGER `trg_stake_adjustments_block_delete` BEFORE DELETE ON `stake_adjustments`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting an adjustment is blocked: the ledger is immutable.';
  END IF;
END$$

CREATE TRIGGER `trg_stake_redemptions_block_delete` BEFORE DELETE ON `stake_redemptions`
FOR EACH ROW
BEGIN
  IF @pt_allow_immutable IS NULL OR @pt_allow_immutable <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deleting a redemption is blocked: the ledger is immutable.';
  END IF;
END$$

DELIMITER ;

-- ============================================================================
--  END
-- ============================================================================
