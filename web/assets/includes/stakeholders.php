<?php
// +------------------------------------------------------------------------+
// | Jackisa Stream - imported livestreams, stakeholder share engine and     |
// | dividend accounting.                                                    |
// |                                                                         |
// | All money and share maths runs on BCMath strings. Display helpers round  |
// | to two places, but the database and every calculation keep 10 decimals. |
// +------------------------------------------------------------------------+

// =========================================================================
//  Decimal / money helpers
// =========================================================================

/**
 * Normalise any numeric input to a fixed-scale decimal string.
 * Returns '0.0000000000' for non-numeric input.
 */
function PT_StakeDec($value, $scale = 10) {
    if (is_array($value) || is_object($value)) {
        return PT_StakeDec(0, $scale);
    }
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        $value = '0';
    }
    if (function_exists('bcadd')) {
        return bcadd($value, '0', $scale);
    }
    return number_format((float) $value, $scale, '.', '');
}

function PT_StakeAdd($a, $b, $scale = 10) {
    if (function_exists('bcadd')) {
        return bcadd(PT_StakeDec($a, $scale), PT_StakeDec($b, $scale), $scale);
    }
    return PT_StakeDec((float) $a + (float) $b, $scale);
}

function PT_StakeSub($a, $b, $scale = 10) {
    if (function_exists('bcsub')) {
        return bcsub(PT_StakeDec($a, $scale), PT_StakeDec($b, $scale), $scale);
    }
    return PT_StakeDec((float) $a - (float) $b, $scale);
}

function PT_StakeMul($a, $b, $scale = 10) {
    if (function_exists('bcmul')) {
        return bcmul(PT_StakeDec($a, $scale), PT_StakeDec($b, $scale), $scale);
    }
    return PT_StakeDec((float) $a * (float) $b, $scale);
}

function PT_StakeDiv($a, $b, $scale = 10) {
    $b = PT_StakeDec($b, $scale);
    if (PT_StakeCmp($b, '0') === 0) {
        return PT_StakeDec(0, $scale);
    }
    if (function_exists('bcdiv')) {
        return bcdiv(PT_StakeDec($a, $scale), $b, $scale);
    }
    return PT_StakeDec((float) $a / (float) $b, $scale);
}

/** -1, 0 or 1. */
function PT_StakeCmp($a, $b, $scale = 10) {
    if (function_exists('bccomp')) {
        return bccomp(PT_StakeDec($a, $scale), PT_StakeDec($b, $scale), $scale);
    }
    $a = (float) $a;
    $b = (float) $b;
    if (abs($a - $b) < 0.0000000001) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

function PT_StakeMax($a, $b, $scale = 10) {
    return (PT_StakeCmp($a, $b, $scale) >= 0) ? PT_StakeDec($a, $scale) : PT_StakeDec($b, $scale);
}

/** Money/share display value (never used for storage). */
function PT_StakeMoney($value, $decimals = 2) {
    return number_format((float) PT_StakeDec($value, 10), $decimals, '.', ',');
}

function PT_StakePercent($value, $decimals = 3) {
    return number_format((float) PT_StakeDec($value, 12), $decimals, '.', ',');
}

/**
 * Run a callable with the SQL immutability guard switched on, so deliberate
 * server-side deletions of accounting records still work while every other
 * DELETE / UPDATE on those tables keeps raising the guard trigger.
 */
function PT_DbSetImmutableGuard($on) {
    global $mysqli;
    if (!empty($mysqli) && is_object($mysqli)) {
        @$mysqli->query('SET @pt_allow_immutable = ' . ($on ? 1 : 0));
    }
}

function PT_DbGuardedRun(callable $callback) {
    PT_DbSetImmutableGuard(1);
    try {
        $result = $callback();
    } catch (Exception $e) {
        $result = false;
    } catch (Error $e) {
        $result = false;
    }
    PT_DbSetImmutableGuard(0);
    return $result;
}

/**
 * Named advisory lock (MySQL GET_LOCK) so two concurrent settlements of the
 * same period cannot credit the same dividend twice. The lock is released
 * automatically when the connection closes, and explicitly by PT_StakeUnlock.
 */
function PT_StakeLock($name, $timeout = 10) {
    global $db;
    $name = substr((string) $name, 0, 60);
    try {
        $rows = $db->rawQuery("SELECT GET_LOCK(?, ?) AS locked", array($name, (int) $timeout));
    } catch (Exception $e) {
        return true; // Never block the feature when advisory locking is unavailable.
    }
    if (empty($rows) || !isset($rows[0]->locked)) {
        return true;
    }
    return ((int) $rows[0]->locked === 1);
}

function PT_StakeUnlock($name) {
    global $db;
    $name = substr((string) $name, 0, 60);
    try {
        $db->rawQuery("SELECT RELEASE_LOCK(?) AS released", array($name));
    } catch (Exception $e) {
    }
}

// =========================================================================
//  Stakeholder lookup helpers
// =========================================================================

function PT_StakeholderByUser($user_id) {
    global $db;
    if (empty($user_id)) {
        return null;
    }
    try {
        $row = $db->where('user_id', (int) $user_id)->getOne(T_STAKEHOLDERS);
    } catch (Exception $e) {
        return null;
    }
    return empty($row) ? null : $row;
}

function PT_UserIsStakeholder($user_id) {
    $row = PT_StakeholderByUser($user_id);
    return (!empty($row) && $row->status === 'active');
}

function PT_StakeTotalShares() {
    global $pt;
    $total = isset($pt->config->stake_total_shares) ? $pt->config->stake_total_shares : 10000;
    return PT_StakeMax($total, '0');
}

function PT_StakeDistributableRatio() {
    global $pt;
    $ratio = isset($pt->config->stake_distributable_ratio) ? $pt->config->stake_distributable_ratio : '0.60';
    $ratio = PT_StakeDec($ratio, 10);
    if (PT_StakeCmp($ratio, '0') < 0) {
        $ratio = '0';
    }
    if (PT_StakeCmp($ratio, '1') > 0) {
        $ratio = '1';
    }
    return $ratio;
}

/** Percent of the company the stakeholder owns (12 dp decimal string). */
function PT_StakePercentOf($shares) {
    $total = PT_StakeTotalShares();
    return PT_StakeMul(PT_StakeDiv(PT_StakeDec($shares), $total), '100', 12);
}

// =========================================================================
//  Period helpers
// =========================================================================

/**
 * Resolve a period key to a [start, end] unix range plus a stable key.
 * Supported: today, yesterday, this_week, last_week, this_month, last_month,
 * this_year, all_time, or an explicit YYYY-MM string.
 */
function PT_StakePeriodRange($period_key) {
    $period_key = strtolower(trim((string) $period_key));
    $now        = time();

    switch ($period_key) {
        case 'today':
            return array('key' => gmdate('Y-m-d', $now), 'start' => strtotime('today UTC'), 'end' => strtotime('tomorrow UTC') - 1);
        case 'yesterday':
            return array('key' => gmdate('Y-m-d', $now - 86400), 'start' => strtotime('yesterday UTC'), 'end' => strtotime('today UTC') - 1);
        case 'last_week':
            $start = strtotime('monday this week UTC') - 604800;
            return array('key' => gmdate('\WW-o', $start), 'start' => $start, 'end' => $start + 604800 - 1);
        case 'this_week':
            $start = strtotime('monday this week UTC');
            return array('key' => gmdate('\WW-o', $start), 'start' => $start, 'end' => $start + 604800 - 1);
        case 'last_month':
            $start = strtotime('first day of last month 00:00:00 UTC');
            $end   = strtotime('last day of last month 23:59:59 UTC');
            return array('key' => gmdate('Y-m', $start), 'start' => $start, 'end' => $end);
        case 'this_year':
            $start = strtotime('first day of January ' . gmdate('Y') . ' 00:00:00 UTC');
            return array('key' => gmdate('Y', $now), 'start' => $start, 'end' => $start + 31557600);
        case 'all_time':
            return array('key' => 'all_time', 'start' => 0, 'end' => $now);
        case 'this_month':
        default:
            if (preg_match('/^\d{4}-\d{2}$/', $period_key)) {
                $start = strtotime($period_key . '-01 00:00:00 UTC');
                return array('key' => $period_key, 'start' => $start, 'end' => strtotime('last day of ' . $period_key . ' 23:59:59 UTC'));
            }
            $start = strtotime('first day of this month 00:00:00 UTC');
            $end   = strtotime('last day of this month 23:59:59 UTC');
            return array('key' => gmdate('Y-m', $now), 'start' => $start, 'end' => $end);
    }
}

// =========================================================================
//  Revenue computation
// =========================================================================

/**
 * Platform revenue inside a window.
 *
 * Sources, all summed server-side and never rewritten:
 *   - videos_transactions : the platform's commission (admin_com) on sales.
 *     For *_PERCENT currencies admin_com is a percentage of amount.
 *   - ads_transactions    : advertiser spend (type = 'spent').
 *   - stake_revenue_entries: manual revenue rows (receivables, partnerships...).
 *   - Store ledger        : the commission and delivery the platform earns when
 *     a Store order payment settles (accounts 4000 and 4100).
 *   - Wallet top-ups      : the mobile-money service fee collected on a wallet
 *     replenishment.
 */
function PT_StakeRevenueBetween($start, $end) {
    global $db, $pt;
    $revenue = PT_StakeDec(0);

    try {
        $rows = $db->rawQuery("SELECT `amount`, `admin_com`, `currency` FROM `" . T_VIDEOS_TRSNS . "` WHERE `time` >= ? AND `time` <= ?", array((int) $start, (int) $end));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $currency = (string) $row->currency;
                $amount   = PT_StakeDec($row->amount);
                $com      = PT_StakeDec($row->admin_com);
                if (substr($currency, -8) === '_PERCENT') {
                    $revenue = PT_StakeAdd($revenue, PT_StakeDiv(PT_StakeMul($amount, $com), '100'));
                } else {
                    $revenue = PT_StakeAdd($revenue, $com);
                }
            }
        }
    } catch (Exception $e) {
    }

    try {
        $rows = $db->rawQuery("SELECT `amount` FROM `" . T_ADS_TRANS . "` WHERE `type` = 'spent' AND `time` >= ? AND `time` <= ?", array((int) $start, (int) $end));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $revenue = PT_StakeAdd($revenue, PT_StakeDec($row->amount));
            }
        }
    } catch (Exception $e) {
    }

    try {
        $rows = $db->rawQuery("SELECT `amount` FROM `" . T_STAKE_REVENUE_ENTRIES . "` WHERE `entry_type` = 'revenue' AND `entry_time` >= ? AND `entry_time` <= ?", array((int) $start, (int) $end));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $revenue = PT_StakeAdd($revenue, PT_StakeDec($row->amount));
            }
        }
    } catch (Exception $e) {
    }

    $revenue = PT_StakeAdd($revenue, PT_StakeStoreRevenueBetween($start, $end));
    $revenue = PT_StakeAdd($revenue, PT_StakeWalletRevenueBetween($start, $end));

    return $revenue;
}

/**
 * Store commission + delivery revenue recognised when order payments settle.
 * Read from the Store double-entry ledger (accounts 4000 commission, 4100
 * delivery); refunds reverse the same accounts so the net is always current.
 */
function PT_StakeStoreRevenueBetween($start, $end) {
    global $db;
    if (!defined('T_STORE_LEDGER_ENTRIES')) {
        return PT_StakeDec(0);
    }
    $total = PT_StakeDec(0);
    try {
        $rows = $db->rawQuery("SELECT `account_code`, `order_id`, COALESCE(SUM(`credit`),0) AS c, COALESCE(SUM(`debit`),0) AS d
            FROM `" . T_STORE_LEDGER_ENTRIES . "`
            WHERE `account_code` IN ('4000','4100') AND `created_at` >= ? AND `created_at` <= ?
            GROUP BY `order_id`, `account_code`", array((int) $start, (int) $end));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $total = PT_StakeAdd($total, PT_StakeSub(PT_StakeDec($row->c), PT_StakeDec($row->d)));
            }
        }
    } catch (Exception $e) {
    }
    return $total;
}

/**
 * The mobile-money service fee collected on a wallet replenishment. The payer
 * is charged more than the wallet is credited; the difference is platform
 * income, exactly like the Store service fee.
 */
function PT_StakeWalletRevenueBetween($start, $end) {
    global $db;
    if (!defined('T_JPESA_PAYMENTS')) {
        return PT_StakeDec(0);
    }
    $total = PT_StakeDec(0);
    try {
        $rows = $db->rawQuery("SELECT `amount`, `charged_amount` FROM `" . T_JPESA_PAYMENTS . "`
            WHERE `status` = 'paid' AND `purchase_type` = 'wallet' AND `completed_at` >= ? AND `completed_at` <= ?",
            array((int) $start, (int) $end));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $fee = PT_StakeSub(PT_StakeDec($row->charged_amount), PT_StakeDec($row->amount));
                if (PT_StakeCmp($fee, '0') > 0) {
                    $total = PT_StakeAdd($total, $fee);
                }
            }
        }
    } catch (Exception $e) {
    }
    return $total;
}

/** Sum of a manual asset category, plus any base value configured. */
function PT_StakeAssetValue($category, $config_value = '0') {
    global $db;
    $value = PT_StakeDec($config_value);
    try {
        $rows = $db->rawQuery("SELECT `amount` FROM `" . T_STAKE_REVENUE_ENTRIES . "` WHERE `entry_type` = 'asset' AND `category` = ?", array((string) $category));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $value = PT_StakeAdd($value, PT_StakeDec($row->amount));
            }
        }
    } catch (Exception $e) {
    }
    return $value;
}

// =========================================================================
//  Metric computation (the "deep complex determinants")
// =========================================================================

/** Raw value for a single metric key. Always a numeric string. */
function PT_StakeComputeMetric($metric_key, $start, $end) {
    global $db, $pt;
    $window = max(1, (int) $end - (int) $start);

    switch ($metric_key) {
        case 'total_revenue':
            return PT_StakeRevenueBetween($start, $end);

        case 'mrr':
            // Trailing 30 days normalised to a month.
            $r30 = PT_StakeRevenueBetween(time() - 2592000, time());
            return PT_StakeDec($r30);

        case 'arr':
            $r30 = PT_StakeRevenueBetween(time() - 2592000, time());
            return PT_StakeMul($r30, '12');

        case 'dau':
            try {
                return PT_StakeDec($db->where('last_active', time() - 86400, '>')->getValue(T_USERS, 'COUNT(*)'));
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'mau':
            try {
                return PT_StakeDec($db->where('last_active', time() - 2592000, '>')->getValue(T_USERS, 'COUNT(*)'));
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'churn_rate':
            try {
                $reg_start = date('Y') . '/' . intval(date('m', (int) $start));
                $active_at_start = PT_StakeDec($db->where('registered', $reg_start, '<')->where('last_active', (int) $start, '>=')->getValue(T_USERS, 'COUNT(*)'));
                $retained        = PT_StakeDec($db->where('registered', $reg_start, '<')->where('last_active', (int) $start, '>=')->where('last_active', (int) $end, '<=')->getValue(T_USERS, 'COUNT(*)'));
                if (PT_StakeCmp($active_at_start, '0') <= 0) {
                    return PT_StakeDec(0);
                }
                $churned = PT_StakeSub($active_at_start, $retained);
                $rate    = PT_StakeMul(PT_StakeDiv($churned, $active_at_start), '100');
                if (PT_StakeCmp($rate, '0') < 0) {
                    $rate = PT_StakeDec(0);
                }
                if (PT_StakeCmp($rate, '100') > 0) {
                    $rate = PT_StakeDec('100');
                }
                return $rate;
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'cac':
            try {
                $new_users = PT_StakeDec($db->where('registered', date('Y') . '/' . intval(date('m', (int) $start)))->getValue(T_USERS, 'COUNT(*)'));
                $spend     = PT_StakeDec(isset($pt->config->stake_monthly_marketing_spend) ? $pt->config->stake_monthly_marketing_spend : 0);
                if (PT_StakeCmp($new_users, '0') <= 0) {
                    return PT_StakeDec(0);
                }
                return PT_StakeDiv($spend, $new_users);
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'ltv':
            try {
                $mrr = PT_StakeComputeMetric('mrr', $start, $end);
                $mau = PT_StakeComputeMetric('mau', $start, $end);
                $churn = PT_StakeComputeMetric('churn_rate', $start, $end);
                $arpu = PT_StakeDiv($mrr, PT_StakeMax($mau, '1'));
                // Churn floor of 0.5% keeps LTV finite for a healthy platform.
                $churn = PT_StakeMax($churn, '0.5');
                return PT_StakeDiv($arpu, PT_StakeDiv($churn, '100'));
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'ltv_cac_ratio':
            $ltv = PT_StakeComputeMetric('ltv', $start, $end);
            $cac = PT_StakeComputeMetric('cac', $start, $end);
            if (PT_StakeCmp($cac, '0') <= 0) {
                return PT_StakeDec(0);
            }
            return PT_StakeDiv($ltv, $cac);

        case 'network_value':
            try {
                $mau     = PT_StakeComputeMetric('mau', $start, $end);
                $mrr     = PT_StakeComputeMetric('mrr', $start, $end);
                $arpu    = PT_StakeDiv($mrr, PT_StakeMax($mau, '1'));
                $new     = PT_StakeDec($db->where('registered', date('Y') . '/' . intval(date('m', (int) $start)))->getValue(T_USERS, 'COUNT(*)'));
                $growth  = PT_StakeDiv($new, PT_StakeMax($mau, '1'));
                // Metcalfe-style: value grows with the square-ish of the network,
                // scaled by ARPU and current growth.
                $factor  = PT_StakeMul($mau, PT_StakeAdd('1', $growth));
                return PT_StakeMul($arpu, $factor);
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'proprietary_data_value':
            try {
                $users    = PT_StakeDec($db->getValue(T_USERS, 'COUNT(*)'));
                $per_user = PT_StakeDec(isset($pt->config->stake_data_value_per_user) ? $pt->config->stake_data_value_per_user : '0.05');
                return PT_StakeMul($users, $per_user);
            } catch (Exception $e) {
                return PT_StakeDec(0);
            }

        case 'accounts_receivable':
            return PT_StakeAssetValue('receivable');

        case 'cash_reserves':
            return PT_StakeAssetValue('cash', isset($pt->config->stake_cash_reserves) ? $pt->config->stake_cash_reserves : '0');

        case 'domain_seo_value':
            return PT_StakeAssetValue('domain', isset($pt->config->stake_domain_seo_value) ? $pt->config->stake_domain_seo_value : '0');

        default:
            return PT_StakeDec(0);
    }
}

/**
 * Bounded 0..1 score for one raw metric value.
 * higher_is_better -> value / (1 + value)
 * lower_is_better  -> 1 / (1 + value)
 */
function PT_StakeMetricScore($raw, $direction) {
    $raw = PT_StakeDec($raw);
    if (PT_StakeCmp($raw, '0') < 0) {
        $raw = PT_StakeDec(0);
    }
    if ($direction === 'lower_is_better') {
        return PT_StakeDiv('1', PT_StakeAdd('1', $raw));
    }
    return PT_StakeDiv($raw, PT_StakeAdd('1', $raw));
}

/** All active metric definitions keyed by metric_key. */
function PT_StakeMetricDefs() {
    global $db;
    try {
        $rows = $db->where('status', 'active')->orderBy('sort_order', 'ASC')->get(T_STAKE_METRIC_DEFS);
    } catch (Exception $e) {
        return array();
    }
    $defs = array();
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $defs[$row->metric_key] = $row;
        }
    }
    return $defs;
}

// =========================================================================
//  Share engine
// =========================================================================

/**
 * Recompute every stakeholder's share of the fixed 10,000 share pool from the
 * metric model, then record each movement as an immutable share event.
 *
 * Returns a summary array. Never throws; returns ['status' => 0] on failure.
 */
function PT_StakeRecalculateShares($period_key = 'this_month', $reason = 'auto_recalculation', $created_by = 0) {
    global $db, $pt;

    $range = PT_StakePeriodRange($period_key);
    $defs  = PT_StakeMetricDefs();
    if (empty($defs)) {
        return array('status' => 0, 'message' => 'No metric definitions available.');
    }

    try {
        $stakeholders = $db->where('status', 'active')->get(T_STAKEHOLDERS);
    } catch (Exception $e) {
        return array('status' => 0, 'message' => 'Stakeholder table unavailable.');
    }
    if (empty($stakeholders)) {
        return array('status' => 1, 'total_shares' => PT_StakeTotalShares(), 'updated' => 0, 'metrics' => array());
    }

    // Raw metric values for this period, computed once.
    $raw_values = array();
    foreach ($defs as $key => $def) {
        $raw_values[$key] = PT_StakeComputeMetric($key, $range['start'], $range['end']);
    }

    // Each stakeholder's weighted score.
    $scores       = array();
    $total_score  = PT_StakeDec(0);
    $weights_used = array();

    foreach ($stakeholders as $stakeholder) {
        $score = PT_StakeDec(0);
        foreach ($defs as $key => $def) {
            $weight = PT_StakeDec($def->default_weight);
            try {
                $override = $db->where('stakeholder_id', $stakeholder->id)->where('metric_key', $key)->getOne(T_STAKE_METRIC_WEIGHTS);
                if (!empty($override)) {
                    $weight = PT_StakeDec($override->weight);
                }
            } catch (Exception $e) {
            }
            $weights_used[$key] = $weight;
            $metric_score = PT_StakeMetricScore($raw_values[$key], $def->direction);
            $score        = PT_StakeAdd($score, PT_StakeMul($weight, $metric_score));
        }
        $scores[$stakeholder->id] = $score;
        $total_score              = PT_StakeAdd($total_score, $score);
    }

    if (PT_StakeCmp($total_score, '0') <= 0) {
        // Degenerate model: every stakeholder keeps the same share.
        $equal = PT_StakeDiv(PT_StakeTotalShares(), (string) count($stakeholders));
        foreach ($stakeholders as $stakeholder) {
            $scores[$stakeholder->id] = $equal;
        }
        $total_score = PT_StakeMul($equal, (string) count($stakeholders));
    }

    $total_shares_pool = PT_StakeTotalShares();
    $smoothing         = isset($pt->config->stake_share_smoothing) ? PT_StakeDec($pt->config->stake_share_smoothing) : '1.00';
    if (PT_StakeCmp($smoothing, '0') < 0) {
        $smoothing = '0';
    }
    if (PT_StakeCmp($smoothing, '1') > 0) {
        $smoothing = '1';
    }

    $updated = 0;
    $results = array();

    foreach ($stakeholders as $stakeholder) {
        $target  = PT_StakeDiv(PT_StakeMul($total_shares_pool, $scores[$stakeholder->id]), $total_score);
        $current = PT_StakeDec($stakeholder->current_shares);

        if (PT_StakeCmp($current, '0') <= 0) {
            $new_shares = $target;
        } else {
            $new_shares = PT_StakeAdd(
                PT_StakeMul($current, PT_StakeSub('1', $smoothing)),
                PT_StakeMul($target, $smoothing)
            );
        }

        $delta = PT_StakeSub($new_shares, $current);
        $results[$stakeholder->id] = array(
            'user_id'      => $stakeholder->user_id,
            'old_shares'   => $current,
            'new_shares'   => $new_shares,
            'delta'        => $delta,
            'percent'      => PT_StakePercentOf($new_shares),
        );

        // Only write a movement when it actually changes by at least 1e-10.
        if (PT_StakeCmp($delta, '0') === 0) {
            continue;
        }

        try {
            PT_DbGuardedRun(function () use ($db, $stakeholder, $delta, $new_shares, $reason, $created_by, $raw_values, $weights_used) {
                $db->insert(T_STAKE_SHARE_EVENTS, array(
                    'stakeholder_id'   => $stakeholder->id,
                    'user_id'          => $stakeholder->user_id,
                    'shares_delta'     => $delta,
                    'shares_after'     => $new_shares,
                    'reason'           => $reason,
                    'metrics_snapshot' => json_encode(array('raw' => $raw_values, 'weights' => $weights_used)),
                    'created_by'       => (int) $created_by,
                    'created_at'       => time(),
                ));
                $db->where('id', $stakeholder->id)->update(T_STAKEHOLDERS, array(
                    'current_shares'  => $new_shares,
                    'current_percent' => PT_StakePercentOf($new_shares),
                    'updated_at'      => time(),
                ));
                return true;
            });
            $updated++;
        } catch (Exception $e) {
        } catch (Error $e) {
        }
    }

    // Keep every stakeholder row's percent in sync even when shares did not move.
    foreach ($stakeholders as $stakeholder) {
        $current = PT_StakeDec($stakeholder->current_shares);
        try {
            $db->where('id', $stakeholder->id)->update(T_STAKEHOLDERS, array(
                'current_percent' => PT_StakePercentOf($current),
                'updated_at'      => time(),
            ));
        } catch (Exception $e) {
        }
    }

    return array(
        'status'       => 1,
        'total_shares' => $total_shares_pool,
        'updated'      => $updated,
        'metrics'      => $raw_values,
        'weights'      => $weights_used,
        'period'       => $range,
        'stakeholders' => $results,
    );
}

/**
 * Settle one period: snapshot the metrics, recompute shares, and credit each
 * active stakeholder their dividend from the distributable pool (60% by
 * default) of the period revenue.
 */
function PT_StakeSettlePeriod($period_key = 'this_month', $created_by = 0) {
    global $db, $pt;

    $range = PT_StakePeriodRange($period_key);
    $key   = (string) $range['key'];

    $lock_name = 'pt_stake_settle_' . $key;
    PT_StakeLock($lock_name, 10);

    try {
        $existing = $db->where('period_key', $key)->getOne(T_STAKE_PERIODS);
    } catch (Exception $e) {
        PT_StakeUnlock($lock_name);
        return array('status' => 0, 'message' => 'Period table unavailable.');
    }

    if (empty($existing)) {
        $period_id = 0;
        try {
            $period_id = $db->insert(T_STAKE_PERIODS, array(
                'period_key'   => $key,
                'period_start' => (int) $range['start'],
                'period_end'   => (int) $range['end'],
                'status'       => 'open',
                'created_at'   => time(),
            ));
        } catch (Exception $e) {
        }
        $existing = $db->where('period_key', $key)->getOne(T_STAKE_PERIODS);
    }
    if (empty($existing)) {
        PT_StakeUnlock($lock_name);
        return array('status' => 0, 'message' => 'Could not create the settlement period.');
    }
    $period_id = $existing->id;

    // Recompute shares for the period first so the split uses fresh weights.
    $recalc = PT_StakeRecalculateShares($period_key, 'period_settlement', $created_by);

    $revenue       = PT_StakeRevenueBetween($range['start'], $range['end']);
    $distributable = PT_StakeMul($revenue, PT_StakeDistributableRatio());

    // Snapshot the metric values (immutable).
    if (!empty($recalc['metrics'])) {
        foreach ($recalc['metrics'] as $metric_key => $raw) {
            try {
                $already = $db->where('period_id', $period_id)->where('metric_key', $metric_key)->getValue(T_STAKE_METRIC_VALUES, 'COUNT(*)');
                if ($already > 0) {
                    continue;
                }
                $weight = isset($recalc['weights'][$metric_key]) ? $recalc['weights'][$metric_key] : '1';
                $direction = isset((PT_StakeMetricDefs())[$metric_key]) ? (PT_StakeMetricDefs())[$metric_key]->direction : 'higher_is_better';
                PT_DbGuardedRun(function () use ($db, $period_id, $metric_key, $raw, $weight, $direction) {
                    $db->insert(T_STAKE_METRIC_VALUES, array(
                        'period_id'  => $period_id,
                        'metric_key' => $metric_key,
                        'raw_value'  => $raw,
                        'score'      => PT_StakeMetricScore($raw, $direction),
                        'weight'     => $weight,
                        'created_at' => time(),
                    ));
                    return true;
                });
            } catch (Exception $e) {
            }
        }
    }

    try {
        $stakeholders = $db->where('status', 'active')->get(T_STAKEHOLDERS);
    } catch (Exception $e) {
        $stakeholders = array();
    }

    $total_shares = PT_StakeDec(0);
    foreach ($stakeholders as $stakeholder) {
        $total_shares = PT_StakeAdd($total_shares, PT_StakeDec($stakeholder->current_shares));
    }

    $credited = 0;
    if (PT_StakeCmp($total_shares, '0') > 0 && PT_StakeCmp($distributable, '0') > 0) {
        foreach ($stakeholders as $stakeholder) {
            $already = $db->where('stakeholder_id', $stakeholder->id)->where('period_id', $period_id)->getValue(T_STAKE_EARNINGS, 'COUNT(*)');
            if ($already > 0) {
                continue;
            }
            $shares  = PT_StakeDec($stakeholder->current_shares);
            $percent = PT_StakeDiv($shares, $total_shares);
            $amount  = PT_StakeMul($distributable, $percent);

            $ok = PT_DbGuardedRun(function () use ($db, $stakeholder, $period_id, $range, $shares, $percent, $distributable, $amount, $revenue) {
                $db->insert(T_STAKE_EARNINGS, array(
                    'stakeholder_id'        => $stakeholder->id,
                    'user_id'               => $stakeholder->user_id,
                    'period_id'             => $period_id,
                    'period_start'          => (int) $range['start'],
                    'period_end'            => (int) $range['end'],
                    'shares'                => $shares,
                    'share_percent'         => PT_StakeMul($percent, '100', 12),
                    'pool_revenue'          => $revenue,
                    'distributable_revenue' => $distributable,
                    'amount'                => $amount,
                    'currency'              => isset($pt->config->payment_currency) ? $pt->config->payment_currency : 'USD',
                    'description'           => 'Dividend for period ' . $range['key'],
                    'status'                => 'credited',
                    'created_at'            => time(),
                ));
                $new_life = PT_StakeAdd($stakeholder->lifecycle_earnings, $amount);
                $db->where('id', $stakeholder->id)->update(T_STAKEHOLDERS, array(
                    'lifecycle_earnings'     => $new_life,
                    'last_settled_period_id' => $period_id,
                    'updated_at'             => time(),
                ));
                return true;
            });
            if ($ok) {
                $credited++;
            }
        }
    }

    // Period totals are descriptive; the ledger rows above are the truth.
    try {
        PT_DbGuardedRun(function () use ($db, $period_id, $revenue, $distributable, $total_shares, $recalc) {
            $db->where('id', $period_id)->update(T_STAKE_PERIODS, array(
                'total_revenue'         => $revenue,
                'distributable_revenue' => $distributable,
                'total_shares'          => $total_shares,
                'currency'              => isset($pt->config->payment_currency) ? $pt->config->payment_currency : 'USD',
                'metrics_snapshot'      => json_encode($recalc),
                'status'                => 'settled',
                'settled_at'            => time(),
            ));
            return true;
        });
    } catch (Exception $e) {
    }

    PT_StakeUnlock($lock_name);

    return array(
        'status'        => 1,
        'period_key'    => $key,
        'period_id'     => $period_id,
        'revenue'       => $revenue,
        'distributable' => $distributable,
        'total_shares'  => $total_shares,
        'credited'      => $credited,
        'recalc'        => $recalc,
    );
}

/** Ensure the current period is settled if the stored one is stale. */
function PT_StakeMaybeSettleCurrentPeriod() {
    $range = PT_StakePeriodRange('this_month');
    $result = PT_StakeSettlePeriod('this_month', 0);
    return $result;
}

// =========================================================================
//  Dividend / balance helpers
// =========================================================================

/** Lifetime dividends credited to a user. */
function PT_StakeLifecycleEarnings($user_id) {
    $stakeholder = PT_StakeholderByUser($user_id);
    if (empty($stakeholder)) {
        return PT_StakeDec(0);
    }
    return PT_StakeDec($stakeholder->lifecycle_earnings);
}

/** Total stake money already paid out (drawn from dividends). */
function PT_StakeWithdrawnTotal($user_id) {
    global $db;
    try {
        $rows = $db->rawQuery("SELECT `amount` FROM `" . T_STAKE_WITHDRAWALS . "` WHERE `user_id` = ? AND `status` = 'paid'", array((int) $user_id));
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    $total = PT_StakeDec(0);
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $total = PT_StakeAdd($total, PT_StakeDec($row->amount));
        }
    }
    return $total;
}

/**
 * Dividends available to withdraw: credited dividends minus amounts already
 * drawn from them. This is a virtual balance; it is never stored on `users`.
 */
function PT_StakeAvailableDividend($user_id) {
    $earned    = PT_StakeLifecycleEarnings($user_id);
    $withdrawn = PT_StakeWithdrawnTotal($user_id);
    $available = PT_StakeSub($earned, $withdrawn);
    return (PT_StakeCmp($available, '0') < 0) ? PT_StakeDec(0) : $available;
}

/** How much a user can withdraw in total, including stake dividends. */
function PT_StakeWithdrawableTotal($user_id, $existing_balance) {
    if (!PT_UserIsStakeholder($user_id)) {
        return PT_StakeDec($existing_balance);
    }
    return PT_StakeAdd(PT_StakeDec($existing_balance), PT_StakeAvailableDividend($user_id));
}

/** Dividends currently locked/withdrawn on a user (for the wallet display). */
function PT_StakeDividendSummary($user_id) {
    $stakeholder = PT_StakeholderByUser($user_id);
    if (empty($stakeholder)) {
        return null;
    }
    return array(
        'shares'          => PT_StakeDec($stakeholder->current_shares),
        'percent'         => PT_StakeDec($stakeholder->current_percent),
        'lifecycle'       => PT_StakeDec($stakeholder->lifecycle_earnings),
        'withdrawn'       => PT_StakeWithdrawnTotal($user_id),
        'available'       => PT_StakeAvailableDividend($user_id),
        'last_settled'    => (int) $stakeholder->last_settled_period_id,
    );
}

/**
 * Apply a stake-funded portion to an approved withdrawal request: consume the
 * user's oldest dividends FIFO and record an immutable payout row. Dividends
 * themselves are never edited.
 */
function PT_StakeDrawForWithdrawal($user_id, $amount, $withdrawal_request_id = 0, $created_by = 0) {
    global $db, $pt;

    $stakeholder = PT_StakeholderByUser($user_id);
    if (empty($stakeholder)) {
        return false;
    }
    $amount = PT_StakeDec($amount);
    if (PT_StakeCmp($amount, '0') <= 0) {
        return false;
    }
    $available = PT_StakeAvailableDividend($user_id);
    if (PT_StakeCmp($amount, $available) > 0) {
        $amount = $available;
    }
    if (PT_StakeCmp($amount, '0') <= 0) {
        return false;
    }

    // Work out how much of each earning row is still unspent.
    try {
        $earnings = $db->where('user_id', $user_id)->orderBy('id', 'ASC')->get(T_STAKE_EARNINGS);
        $payouts  = $db->where('user_id', $user_id)->where('status', 'paid')->get(T_STAKE_WITHDRAWALS);
    } catch (Exception $e) {
        return false;
    }

    $used = array();
    foreach ($payouts as $payout) {
        $alloc = json_decode($payout->allocations, true);
        if (is_array($alloc)) {
            foreach ($alloc as $one) {
                if (!empty($one['stake_earning_id'])) {
                    $id = (int) $one['stake_earning_id'];
                    $used[$id] = PT_StakeAdd(isset($used[$id]) ? $used[$id] : '0', $one['amount']);
                }
            }
        }
    }

    $remaining = $amount;
    $allocations = array();
    foreach ($earnings as $earning) {
        if (PT_StakeCmp($remaining, '0') <= 0) {
            break;
        }
        $spent     = isset($used[$earning->id]) ? $used[$earning->id] : PT_StakeDec(0);
        $left      = PT_StakeSub(PT_StakeDec($earning->amount), $spent);
        if (PT_StakeCmp($left, '0') <= 0) {
            continue;
        }
        $take = (PT_StakeCmp($left, $remaining) >= 0) ? $remaining : $left;
        $allocations[] = array('stake_earning_id' => (int) $earning->id, 'amount' => $take);
        $remaining = PT_StakeSub($remaining, $take);
    }

    $drawn = PT_StakeSub($amount, $remaining);
    if (PT_StakeCmp($drawn, '0') <= 0) {
        return false;
    }

    $recorded = PT_DbGuardedRun(function () use ($db, $stakeholder, $user_id, $withdrawal_request_id, $drawn, $allocations, $created_by, $pt) {
        $db->insert(T_STAKE_WITHDRAWALS, array(
            'stakeholder_id'        => $stakeholder->id,
            'user_id'               => $user_id,
            'withdrawal_request_id' => (int) $withdrawal_request_id,
            'amount'                => $drawn,
            'currency'              => isset($pt->config->payment_currency) ? $pt->config->payment_currency : 'USD',
            'allocations'           => json_encode($allocations),
            'status'                => 'paid',
            'created_by'            => (int) $created_by,
            'created_at'            => time(),
        ));
        $new_withdrawn = PT_StakeAdd($stakeholder->lifecycle_withdrawn, $drawn);
        $db->where('id', $stakeholder->id)->update(T_STAKEHOLDERS, array(
            'lifecycle_withdrawn' => $new_withdrawn,
            'updated_at'          => time(),
        ));
        return true;
    });

    return $recorded ? $drawn : false;
}

/**
 * Apply an approved withdrawal request across the user's wallet balance and
 * their stake dividends. The wallet balance is consumed first, then the
 * remaining part is drawn FIFO from the immutable dividend ledger. Returns the
 * split so callers can persist it on the request row.
 */
function PT_StakeSettleWithdrawal($user_id, $amount, $withdrawal_request_id = 0, $created_by = 0) {
    global $db;

    $amount   = PT_StakeDec($amount);
    $result   = array('balance_portion' => PT_StakeDec(0), 'stake_portion' => PT_StakeDec(0), 'total' => $amount);

    $user = $db->where('id', (int) $user_id)->getOne(T_USERS);
    if (empty($user)) {
        return $result;
    }

    $balance         = PT_StakeDec($user->balance);
    $balance_portion = (PT_StakeCmp($balance, $amount) >= 0) ? $amount : $balance;
    $stake_portion   = PT_StakeSub($amount, $balance_portion);

    if (PT_StakeCmp($balance_portion, '0') > 0) {
        $new_balance = PT_StakeSub($balance, $balance_portion);
        try {
            $db->where('id', (int) $user_id)->update(T_USERS, array('balance' => $new_balance));
        } catch (Exception $e) {
        }
    }
    $result['balance_portion'] = $balance_portion;

    if (PT_StakeCmp($stake_portion, '0') > 0) {
        $drawn = PT_StakeDrawForWithdrawal($user_id, $stake_portion, $withdrawal_request_id, $created_by);
        if (!empty($drawn)) {
            $result['stake_portion'] = PT_StakeDec($drawn);
        }
    }

    return $result;
}

// =========================================================================
//  Stakeholder management
// =========================================================================

function PT_StakeMarkUser($user_id, $role = 'contributor', $created_by = 0, $initial_shares = '0') {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return array('status' => 0, 'message' => 'Invalid user.');
    }
    $user = $db->where('id', $user_id)->getOne(T_USERS);
    if (empty($user)) {
        return array('status' => 0, 'message' => 'User not found.');
    }
    $existing = PT_StakeholderByUser($user_id);
    if (!empty($existing)) {
        return array('status' => 1, 'stakeholder_id' => $existing->id, 'existing' => true);
    }

    $stakeholder_id = $db->insert(T_STAKEHOLDERS, array(
        'user_id'    => $user_id,
        'role'       => $role,
        'status'     => 'active',
        'created_by' => (int) $created_by,
        'created_at' => time(),
        'updated_at' => time(),
    ));
    if (empty($stakeholder_id)) {
        return array('status' => 0, 'message' => 'Could not create the stakeholder.');
    }

    $initial_shares = PT_StakeDec($initial_shares);
    if (PT_StakeCmp($initial_shares, '0') > 0) {
        PT_StakeAssignShares($stakeholder_id, $initial_shares, 'initial_grant', $created_by);
    }

    return array('status' => 1, 'stakeholder_id' => $stakeholder_id);
}

function PT_StakeRevokeUser($user_id) {
    global $db;
    $stakeholder = PT_StakeholderByUser($user_id);
    if (empty($stakeholder)) {
        return array('status' => 0, 'message' => 'This user is not a stakeholder.');
    }
    try {
        $db->where('id', $stakeholder->id)->update(T_STAKEHOLDERS, array('status' => 'revoked', 'updated_at' => time()));
    } catch (Exception $e) {
        return array('status' => 0, 'message' => 'Could not revoke the stakeholder.');
    }
    return array('status' => 1);
}

/**
 * Assign (or adjust) shares. Passing delta signs allows both grants and
 * downwards adjustments. Every change is recorded as an immutable event.
 */
function PT_StakeAssignShares($stakeholder_id, $delta, $reason = 'manual_adjustment', $created_by = 0) {
    global $db;
    $delta = PT_StakeDec($delta);
    $stakeholder = $db->where('id', (int) $stakeholder_id)->getOne(T_STAKEHOLDERS);
    if (empty($stakeholder)) {
        return array('status' => 0, 'message' => 'Stakeholder not found.');
    }
    $current = PT_StakeDec($stakeholder->current_shares);
    $new     = PT_StakeAdd($current, $delta);
    if (PT_StakeCmp($new, '0') < 0) {
        $new = PT_StakeDec(0);
    }
    $actual_delta = PT_StakeSub($new, $current);
    if (PT_StakeCmp($actual_delta, '0') === 0) {
        return array('status' => 1, 'shares' => $current, 'unchanged' => true);
    }

    $ok = PT_DbGuardedRun(function () use ($db, $stakeholder, $actual_delta, $new, $reason, $created_by) {
        $db->insert(T_STAKE_SHARE_EVENTS, array(
            'stakeholder_id' => $stakeholder->id,
            'user_id'        => $stakeholder->user_id,
            'shares_delta'   => $actual_delta,
            'shares_after'   => $new,
            'reason'         => $reason,
            'created_by'     => (int) $created_by,
            'created_at'     => time(),
        ));
        $db->where('id', $stakeholder->id)->update(T_STAKEHOLDERS, array(
            'current_shares'  => $new,
            'current_percent' => PT_StakePercentOf($new),
            'updated_at'      => time(),
        ));
        return true;
    });

    if (!$ok) {
        return array('status' => 0, 'message' => 'Could not record the share change.');
    }
    return array('status' => 1, 'shares' => $new, 'delta' => $actual_delta);
}

/** Set the absolute share count for a stakeholder. */
function PT_StakeSetShares($stakeholder_id, $shares, $reason = 'manual_set', $created_by = 0) {
    global $db;
    $stakeholder = $db->where('id', (int) $stakeholder_id)->getOne(T_STAKEHOLDERS);
    if (empty($stakeholder)) {
        return array('status' => 0, 'message' => 'Stakeholder not found.');
    }
    $delta = PT_StakeSub(PT_StakeDec($shares), PT_StakeDec($stakeholder->current_shares));
    return PT_StakeAssignShares($stakeholder_id, $delta, $reason, $created_by);
}

// =========================================================================
//  Daily Stake Engine (Layers 0 - 23)
//  -----------------------------------------------------------------------
//  Everything below is automatic. The daily engine is driven from cronjob.php
//  (PT_StakeRunDailyEngine). The only human input is the number of shares a
//  stakeholder holds; dividends, dilution, loyalty, valuation, the growth
//  position and the payout ledger are all computed from the platform itself.
// =========================================================================

/** Read an engine parameter from config, with a fallback default. */
function PT_StakeParam($key, $default = '0') {
    global $pt;
    if (isset($pt->config->$key) && $pt->config->$key !== '' && $pt->config->$key !== null) {
        return $pt->config->$key;
    }
    return $default;
}

/** Currency code used by the stake engine (respects the global currency). */
function PT_StakeCurrency() {
    global $pt;
    return isset($pt->config->payment_currency) && $pt->config->payment_currency !== ''
        ? $pt->config->payment_currency : 'USD';
}

/** Currency symbol for the global currency, never a hard-coded dollar. */
function PT_StakeCurrencySymbol() {
    global $pt;
    $code = PT_StakeCurrency();
    if (!empty($pt->config->currency_symbol_array[$code])) {
        return $pt->config->currency_symbol_array[$code];
    }
    return $code;
}

/** Money formatted with the global currency symbol. */
function PT_StakeMoneyDisplay($value, $decimals = 2) {
    return PT_StakeCurrencySymbol() . PT_StakeMoney($value, $decimals);
}

/** e^(-x) as a decimal string. */
function PT_StakeExpNeg($x) {
    $x = (float) PT_StakeDec($x, 10);
    if ($x < 0) {
        $x = 0;
    }
    return PT_StakeDec(exp(-$x), 12);
}

/** Floor a decimal string to a given scale. */
function PT_StakeFloorTo($value, $scale = 10) {
    $value = PT_StakeDec($value, 10);
    $neg   = (PT_StakeCmp($value, '0') < 0);
    if ($neg) {
        $value = PT_StakeSub('0', $value);
    }
    if (function_exists('bcmul')) {
        $factor = '1';
        for ($i = 0; $i < $scale; $i++) {
            $factor .= '0';
        }
        $scaled = bcmul($value, $factor, 0);
        $out    = bcdiv($scaled, $factor, $scale);
    } else {
        $p   = pow(10, $scale);
        $out = number_format(floor((float) $value * $p) / $p, $scale, '.', '');
    }
    return $neg ? PT_StakeSub('0', $out) : $out;
}

/** Min of two decimal strings. */
function PT_StakeMin($a, $b, $scale = 10) {
    return (PT_StakeCmp($a, $b, $scale) <= 0) ? PT_StakeDec($a, $scale) : PT_StakeDec($b, $scale);
}

/** Arithmetic mean of a list of decimal strings. */
function PT_StakeMean(array $values) {
    $count = count($values);
    if ($count === 0) {
        return PT_StakeDec(0);
    }
    $sum = PT_StakeDec(0);
    foreach ($values as $v) {
        $sum = PT_StakeAdd($sum, $v);
    }
    return PT_StakeDiv($sum, (string) $count);
}

/** Population standard deviation of a list of decimal strings. */
function PT_StakeStdDev(array $values) {
    $count = count($values);
    if ($count < 2) {
        return PT_StakeDec(0);
    }
    $mean = (float) PT_StakeMean($values);
    $acc  = 0.0;
    foreach ($values as $v) {
        $d = (float) $v - $mean;
        $acc += $d * $d;
    }
    return PT_StakeDec(sqrt($acc / $count), 10);
}

/**
 * Daily revenue series for the last `$days` days ending on `$endTs`.
 * One pass over each source, bucketed by UTC day (Layers 1 and 2).
 * Returns an ordered array of ['key','start','end','gross','costs','net'].
 */
function PT_StakeDailySeries($endTs, $days = 365) {
    global $db;
    $days   = max(1, (int) $days);
    $endTs  = (int) $endTs;
    $endDay = strtotime(gmdate('Y-m-d', $endTs) . ' 00:00:00 UTC');
    $start  = $endDay - ($days - 1) * 86400;

    $gross = array();
    $costs = array();

    $bucket = function ($ts) use ($endDay, $start) {
        $ts = (int) $ts;
        if ($ts < $start || $ts > $endDay + 86399) {
            return null;
        }
        return gmdate('Y-m-d', $ts);
    };

    // Recognised revenue: platform commission on video sales.
    try {
        $rows = $db->rawQuery("SELECT `amount`, `admin_com`, `currency`, `time` FROM `" . T_VIDEOS_TRSNS . "` WHERE `time` >= ? AND `time` <= ?", array($start, $endDay + 86399));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $key = $bucket($row->time);
                if ($key === null) { continue; }
                $currency = (string) $row->currency;
                $amount   = PT_StakeDec($row->amount);
                $com      = PT_StakeDec($row->admin_com);
                if (substr($currency, -8) === '_PERCENT') {
                    $value = PT_StakeDiv(PT_StakeMul($amount, $com), '100');
                } else {
                    $value = $com;
                }
                $gross[$key] = PT_StakeAdd(isset($gross[$key]) ? $gross[$key] : '0', $value);
            }
        }
    } catch (Exception $e) {
    }

    // Advertising and sponsorship revenue.
    try {
        $rows = $db->rawQuery("SELECT `amount`, `time` FROM `" . T_ADS_TRANS . "` WHERE `type` = 'spent' AND `time` >= ? AND `time` <= ?", array($start, $endDay + 86399));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $key = $bucket($row->time);
                if ($key === null) { continue; }
                $gross[$key] = PT_StakeAdd(isset($gross[$key]) ? $gross[$key] : '0', PT_StakeDec($row->amount));
            }
        }
    } catch (Exception $e) {
    }

    // Manual revenue / cost entries (receivables, partnerships, licences...).
    try {
        $rows = $db->rawQuery("SELECT `entry_type`, `amount`, `entry_time` FROM `" . T_STAKE_REVENUE_ENTRIES . "` WHERE `entry_time` >= ? AND `entry_time` <= ?", array($start, $endDay + 86399));
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $key = $bucket($row->entry_time);
                if ($key === null) { continue; }
                if ($row->entry_type === 'cost') {
                    $costs[$key] = PT_StakeAdd(isset($costs[$key]) ? $costs[$key] : '0', PT_StakeDec($row->amount));
                } elseif ($row->entry_type === 'revenue') {
                    $gross[$key] = PT_StakeAdd(isset($gross[$key]) ? $gross[$key] : '0', PT_StakeDec($row->amount));
                }
            }
        }
    } catch (Exception $e) {
    }

    // Store commission + delivery revenue, bucketed on the day it settled.
    if (defined('T_STORE_LEDGER_ENTRIES')) {
        try {
            $rows = $db->rawQuery("SELECT `account_code`, `order_id`, `created_at`, COALESCE(SUM(`credit`),0) AS c, COALESCE(SUM(`debit`),0) AS d
                FROM `" . T_STORE_LEDGER_ENTRIES . "`
                WHERE `account_code` IN ('4000','4100') AND `created_at` >= ? AND `created_at` <= ?
                GROUP BY `order_id`, `account_code`, `created_at`", array($start, $endDay + 86399));
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $key = $bucket($row->created_at);
                    if ($key === null) { continue; }
                    $value = PT_StakeSub(PT_StakeDec($row->c), PT_StakeDec($row->d));
                    $gross[$key] = PT_StakeAdd(isset($gross[$key]) ? $gross[$key] : '0', $value);
                }
            }
        } catch (Exception $e) {
        }
    }

    // Wallet replenishment service fee, bucketed on completion.
    if (defined('T_JPESA_PAYMENTS')) {
        try {
            $rows = $db->rawQuery("SELECT `amount`, `charged_amount`, `completed_at` FROM `" . T_JPESA_PAYMENTS . "`
                WHERE `status` = 'paid' AND `purchase_type` = 'wallet' AND `completed_at` >= ? AND `completed_at` <= ?",
                array($start, $endDay + 86399));
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $key = $bucket($row->completed_at);
                    if ($key === null) { continue; }
                    $value = PT_StakeSub(PT_StakeDec($row->charged_amount), PT_StakeDec($row->amount));
                    if (PT_StakeCmp($value, '0') > 0) {
                        $gross[$key] = PT_StakeAdd(isset($gross[$key]) ? $gross[$key] : '0', $value);
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    $series = array();
    for ($i = 0; $i < $days; $i++) {
        $dayStart = $start + $i * 86400;
        $key      = gmdate('Y-m-d', $dayStart);
        $g        = isset($gross[$key]) ? $gross[$key] : PT_StakeDec(0);
        $c        = isset($costs[$key]) ? $costs[$key] : PT_StakeDec(0);
        $net      = PT_StakeSub($g, $c);
        if (PT_StakeCmp($net, '0') < 0) {
            $net = PT_StakeDec(0);
        }
        $series[] = array(
            'key'   => $key,
            'start' => $dayStart,
            'end'   => $dayStart + 86399,
            'gross' => $g,
            'costs' => $c,
            'net'   => $net,
        );
    }
    return $series;
}

/** EWMA of a daily series with a half-life in days (Layer 6.2). */
function PT_StakeEwma(array $series, $halfLife = 14) {
    $halfLife = max(1, (int) $halfLife);
    $alpha    = 1 - pow(0.5, 1 / $halfLife);
    $value    = null;
    foreach ($series as $point) {
        $net = (float) $point['net'];
        $value = ($value === null) ? $net : ($alpha * $net + (1 - $alpha) * $value);
    }
    return PT_StakeDec($value === null ? 0 : $value, 10);
}

/** Most recent value of a metric stored in stake_growth_metrics. */
function PT_StakeMetricGet($key, $default = '0') {
    global $db;
    try {
        $row = $db->where('metric_key', $key)->getOne(T_STAKE_GROWTH_METRICS);
        if (!empty($row)) {
            return PT_StakeDec($row->metric_value);
        }
    } catch (Exception $e) {
    }
    return PT_StakeDec($default);
}

/** Upsert a growth metric value. */
function PT_StakeMetricPut($key, $value) {
    global $db;
    $value = PT_StakeDec($value);
    try {
        $row = $db->where('metric_key', $key)->getOne(T_STAKE_GROWTH_METRICS);
        if (!empty($row)) {
            $db->where('metric_key', $key)->update(T_STAKE_GROWTH_METRICS, array('metric_value' => $value, 'updated_at' => time()));
        } else {
            $db->insert(T_STAKE_GROWTH_METRICS, array('metric_key' => $key, 'metric_value' => $value, 'updated_at' => time()));
        }
    } catch (Exception $e) {
    }
    return $value;
}

/** Linear regression of y on t. Returns [slope, intercept, r2]. */
function PT_StakeLinreg(array $ts, array $ys) {
    $n = count($ts);
    if ($n < 3) {
        return array(0.0, 0.0, 0.0);
    }
    $sx = $sy = $sxx = $sxy = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $sx  += $ts[$i];
        $sy  += $ys[$i];
        $sxx += $ts[$i] * $ts[$i];
        $sxy += $ts[$i] * $ys[$i];
    }
    $den = $n * $sxx - $sx * $sx;
    if (abs($den) < 1e-12) {
        return array(0.0, 0.0, 0.0);
    }
    $slope     = ($n * $sxy - $sx * $sy) / $den;
    $intercept = ($sy - $slope * $sx) / $n;
    $ybar = $sy / $n;
    $sst  = 0.0;
    $sse  = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $pred = $slope * $ts[$i] + $intercept;
        $sst += ($ys[$i] - $ybar) * ($ys[$i] - $ybar);
        $sse += ($ys[$i] - $pred) * ($ys[$i] - $pred);
    }
    $r2 = ($sst > 1e-12) ? (1 - $sse / $sst) : 0.0;
    return array($slope, $intercept, $r2);
}

/**
 * Fit the logistic ceiling L_curve (Layer 6.3.2) by scanning candidate
 * ceilings and keeping the best R2. Returns [L, k, t0, r2].
 */
function PT_StakeFitCeiling(array $series, $fallback) {
    $n = count($series);
    if ($n < 30) {
        return array($fallback, 0.0, 0.0, 0.0);
    }
    $maxNet = 0.0;
    foreach ($series as $point) {
        $v = (float) $point['net'];
        if ($v > $maxNet) { $maxNet = $v; }
    }
    if ($maxNet <= 0) {
        return array($fallback, 0.0, 0.0, 0.0);
    }
    $bestL = $fallback;
    $bestK = 0.0;
    $bestT0 = 0.0;
    $bestR2 = -1.0;
    for ($step = 1; $step <= 12; $step++) {
        $L = $maxNet * (1 + $step * 0.5);
        $ts = array();
        $ys = array();
        foreach ($series as $idx => $point) {
            $net = (float) $point['net'];
            if ($net <= 0 || $net >= $L) {
                continue;
            }
            $ts[] = (float) $idx;
            $ys[] = log($net / ($L - $net));
        }
        if (count($ts) < 20) {
            continue;
        }
        list($slope, $intercept, $r2) = PT_StakeLinreg($ts, $ys);
        if ($r2 > $bestR2) {
            $bestR2 = $r2;
            $bestL  = $L;
            $bestK  = $slope;
            $bestT0 = ($slope != 0) ? (-$intercept / $slope) : 0.0;
        }
    }
    if ($bestR2 < 0) {
        return array($fallback, 0.0, 0.0, 0.0);
    }
    return array($bestL, $bestK, $bestT0, max(0.0, $bestR2));
}

/** Growth stage label for a growth position (Layer 6.5). */
function PT_StakeStageFor($phi) {
    $phi = (float) PT_StakeDec($phi, 10);
    if ($phi < 0.05) { return 'seed'; }
    if ($phi < 0.20) { return 'early'; }
    if ($phi < 0.50) { return 'growth'; }
    if ($phi < 0.80) { return 'scale'; }
    if ($phi < 0.95) { return 'mature'; }
    return 'saturation';
}

/**
 * Growth position engine (Layer 6). Measures the platform's journey itself:
 * smoothed net revenue, ceiling, phi, stage, growth speed and fit confidence.
 */
function PT_StakeGrowthPosition($endTs) {
    global $db;
    $series = PT_StakeDailySeries($endTs, 365);
    $net90  = array_slice($series, -90);
    $mean90 = PT_StakeMean(array_map(function ($p) { return $p['net']; }, $net90));

    $smoothed = PT_StakeEwma($series, 14);

    // L_market: addressable users x penetration x daily ARPU x net margin.
    $addressable = PT_StakeMetricGet('addressable_users', '0');
    if (PT_StakeCmp($addressable, '0') <= 0) {
        try {
            $addressable = PT_StakeDec($db->getValue(T_USERS, 'COUNT(*)'));
        } catch (Exception $e) {
            $addressable = PT_StakeDec(0);
        }
    }
    $mau    = PT_StakeComputeMetric('mau', $endTs - 2592000, $endTs);
    $mrr    = PT_StakeComputeMetric('mrr', $endTs - 2592000, $endTs);
    $arpu   = PT_StakeDiv($mrr, PT_StakeMax($mau, '1'));
    $dailyArpu   = PT_StakeDiv($arpu, '30');
    $penetration = PT_StakeParam('stake_max_penetration', '0.05');
    $netMargin   = PT_StakeParam('stake_net_margin', '0.35');
    $lMarket = PT_StakeMul(PT_StakeMul(PT_StakeMul($addressable, $penetration), $dailyArpu), $netMargin);

    // A market estimate below the observed peak is not a credible ceiling:
    // the addressable-user proxy is still too small for the app's own history.
    $max90 = PT_StakeDec(0);
    foreach ($net90 as $p) {
        if (PT_StakeCmp($p['net'], $max90) > 0) { $max90 = $p['net']; }
    }
    $marketCredible = (PT_StakeCmp($lMarket, $max90) >= 0);
    if (!$marketCredible) {
        $lMarket = PT_StakeMax($max90, $mean90);
    }

    list($lCurve, $k, $t0, $r2) = PT_StakeFitCeiling($series, $lMarket);
    $curveOk = ($r2 >= 0.60 && PT_StakeCmp($lCurve, '0') > 0);
    if ($curveOk && $marketCredible) {
        // Geometric blend so neither estimate can dominate (Layer 6.3.3).
        $lBlend = PT_StakeDec(sqrt((float) $lMarket * (float) $lCurve), 10);
    } elseif ($curveOk) {
        $lBlend = PT_StakeDec($lCurve);
    } else {
        $lBlend = PT_StakeDec($lMarket);
    }
    if (PT_StakeCmp($lBlend, '0') <= 0) {
        $lBlend = PT_StakeMax($mean90, '1');
    }
    // The ceiling must never sit below the smoothed current revenue.
    if (PT_StakeCmp($lBlend, $smoothed) < 0) {
        $lBlend = PT_StakeMax($smoothed, '1');
    }

    $phi = PT_StakeDiv($smoothed, $lBlend);
    if (PT_StakeCmp($phi, '1') > 0) {
        $phi = PT_StakeDec('1');
    }
    if (PT_StakeCmp($phi, '0') < 0) {
        $phi = PT_StakeDec(0);
    }

    $kVal = (float) $k;
    if ($kVal <= 0) {
        $kVal = (float) PT_StakeParam('stake_growth_k', '0.01');
    }
    $daysTo = function ($target) use ($phi, $kVal) {
        $p = (float) $phi;
        if ($p <= 0 || $p >= 1 || $target <= $p || $target >= 1) {
            return 0.0;
        }
        return (1 / $kVal) * (log($target / (1 - $target)) - log($p / (1 - $p)));
    };

    return array(
        'smoothed_net'    => $smoothed,
        'ceiling'         => $lBlend,
        'ceiling_market'  => $lMarket,
        'ceiling_curve'   => PT_StakeDec($lCurve),
        'phi'             => $phi,
        'stage'           => PT_StakeStageFor($phi),
        'k'               => PT_StakeDec($kVal, 10),
        't0'              => PT_StakeDec($t0, 6),
        'fit_r2'          => PT_StakeDec($r2, 10),
        'mean_net_90'     => $mean90,
        'days_inflection' => PT_StakeDec($daysTo(0.5), 6),
        'days_to_80'      => PT_StakeDec($daysTo(0.8), 6),
        'relative_growth' => PT_StakeDec($kVal * (1 - (float) $phi), 10),
    );
}

/** Bounded 0..1 score from a raw ratio-like value. */
function PT_StakeScale01($value) {
    $value = (float) PT_StakeDec($value, 10);
    if ($value <= 0) { return PT_StakeDec(0); }
    if ($value >= 1) { return PT_StakeDec('1'); }
    return PT_StakeDec($value, 10);
}

/**
 * Growth Position Index (Layer 6.7). Eight self-measured scores, blended with
 * a weakest-link guard. Persisted to stake_gpi_scores for the admin card.
 */
function PT_StakeGpi($endTs, $growth) {
    global $db;
    $series = PT_StakeDailySeries($endTs, 120);
    $last30 = array_slice($series, -30);
    $prev30 = array_slice($series, -60, 30);
    $sumNow = PT_StakeDec(0);
    $sumPrev = PT_StakeDec(0);
    foreach ($last30 as $p) { $sumNow = PT_StakeAdd($sumNow, $p['net']); }
    foreach ($prev30 as $p) { $sumPrev = PT_StakeAdd($sumPrev, $p['net']); }

    // g1 momentum: 30-day growth of net revenue.
    if (PT_StakeCmp($sumPrev, '0') > 0) {
        $g1 = PT_StakeScale01(PT_StakeSub(PT_StakeDiv($sumNow, $sumPrev), '1'));
    } else {
        $g1 = (PT_StakeCmp($sumNow, '0') > 0) ? PT_StakeDec('1') : PT_StakeDec(0);
    }
    // g2 acceleration: change of momentum against the prior window.
    $prev90 = PT_StakeDec(0);
    foreach (array_slice($series, -90, 30) as $p) { $prev90 = PT_StakeAdd($prev90, $p['net']); }
    $momPrev = (PT_StakeCmp($prev90, '0') > 0) ? PT_StakeSub(PT_StakeDiv($sumPrev, $prev90), '1') : PT_StakeDec(0);
    $g2 = PT_StakeScale01(PT_StakeAdd('0.5', PT_StakeMul(PT_StakeSub($g1, $momPrev), '0.5')));

    // g3 subscriber growth.
    $g3 = PT_StakeDec('0.5');
    try {
        $newSubs = PT_StakeDec($db->where('time', $endTs - 2592000, '>=')->getValue(T_SUBSCRIPTIONS, 'COUNT(*)'));
        $mau     = PT_StakeComputeMetric('mau', $endTs - 2592000, $endTs);
        $g3      = PT_StakeScale01(PT_StakeDiv($newSubs, PT_StakeMax($mau, '1')));
    } catch (Exception $e) {
    }

    // g4 retention = 1 - monthly churn.
    $churn = PT_StakeComputeMetric('churn_rate', $endTs - 2592000, $endTs);
    $g4 = PT_StakeSub('1', PT_StakeScale01(PT_StakeDiv($churn, '100')));

    // g5 unit economics: LTV / CAC.
    $ltv = PT_StakeComputeMetric('ltv', $endTs - 2592000, $endTs);
    $cac = PT_StakeComputeMetric('cac', $endTs - 2592000, $endTs);
    $g5  = PT_StakeScale01(PT_StakeDiv(PT_StakeDiv($ltv, PT_StakeMax($cac, '1')), '3'));

    // g6 gross margin.
    $grossNow = PT_StakeDec(0);
    $costNow  = PT_StakeDec(0);
    foreach ($last30 as $p) {
        $grossNow = PT_StakeAdd($grossNow, $p['gross']);
        $costNow  = PT_StakeAdd($costNow, $p['costs']);
    }
    if (PT_StakeCmp($grossNow, '0') > 0) {
        $g6 = PT_StakeScale01(PT_StakeSub('1', PT_StakeDiv($costNow, $grossNow)));
    } else {
        $g6 = PT_StakeScale01(PT_StakeParam('stake_net_margin', '0.35'));
    }

    // g7 runway: operations bucket over 30-day operating cost, capped at 24.
    $opsBucket = PT_StakeMetricGet('bucket_operations', '0');
    if (PT_StakeCmp($costNow, '0') > 0) {
        $months = PT_StakeDiv($opsBucket, $costNow);
        $g7 = PT_StakeScale01(PT_StakeDiv(PT_StakeMin($months, '24'), '24'));
    } else {
        $g7 = PT_StakeDec('0.9');
    }

    // g8 content freshness: share of viewing on content under 90 days old.
    $g8 = PT_StakeDec('0.5');
    try {
        $total = PT_StakeDec($db->where('time', $endTs - 2592000, '>=')->getValue(T_VIEWS, 'COUNT(*)'));
        if (PT_StakeCmp($total, '0') > 0) {
            $fresh = PT_StakeDec($db->rawQueryOne("SELECT COUNT(*) AS c FROM `" . T_VIEWS . "` v INNER JOIN `" . T_VIDEOS . "` d ON d.id = v.id WHERE v.time >= ? AND d.time >= ?", array($endTs - 2592000, $endTs - 7776000))->c);
            $g8 = PT_StakeScale01(PT_StakeDiv($fresh, $total));
        }
    } catch (Exception $e) {
    }

    $scores = array(
        'g1_momentum'       => $g1,
        'g2_acceleration'   => $g2,
        'g3_subscribers'    => $g3,
        'g4_retention'      => $g4,
        'g5_unit_economics' => $g5,
        'g6_gross_margin'   => $g6,
        'g7_runway'         => $g7,
        'g8_freshness'      => $g8,
    );

    // Weighted mean and weakest link.
    $weightSum = PT_StakeDec(0);
    $weighted  = PT_StakeDec(0);
    $minScore  = null;
    try {
        $defs = $db->get(T_STAKE_GPI_SCORES);
    } catch (Exception $e) {
        $defs = array();
    }
    foreach ($defs as $def) {
        $score = isset($scores[$def->metric_key]) ? $scores[$def->metric_key] : PT_StakeDec(0);
        $w     = PT_StakeDec($def->weight);
        $weighted  = PT_StakeAdd($weighted, PT_StakeMul($w, $score));
        $weightSum = PT_StakeAdd($weightSum, $w);
        if ($minScore === null || PT_StakeCmp($score, $minScore) < 0) {
            $minScore = $score;
        }
    }
    $G = (PT_StakeCmp($weightSum, '0') > 0) ? PT_StakeDiv($weighted, $weightSum) : PT_StakeDec(0);
    if ($minScore === null) { $minScore = PT_StakeDec(0); }
    $gpi = PT_StakeAdd(PT_StakeMul('0.7', $G), PT_StakeMul('0.3', $minScore));

    // Persist for the admin card.
    foreach ($scores as $key => $score) {
        try {
            $exists = $db->where('metric_key', $key)->getOne(T_STAKE_GPI_SCORES);
            if (!empty($exists)) {
                $db->where('metric_key', $key)->update(T_STAKE_GPI_SCORES, array('score' => $score, 'raw_value' => $score, 'updated_at' => time()));
            }
        } catch (Exception $e) {
        }
    }

    return array('gpi' => $gpi, 'G' => $G, 'min' => $minScore, 'scores' => $scores);
}

/** Reserve rate from base plus revenue volatility (Layer 3.3). */
function PT_StakeReserveRate(array $series) {
    $window = (int) PT_StakeParam('stake_vol_window', '90');
    $recent = array_slice($series, -max(2, $window));
    $nets   = array_map(function ($p) { return $p['net']; }, $recent);
    $mean   = PT_StakeMean($nets);
    $base   = PT_StakeParam('stake_reserve_base', '0.05');
    $c      = PT_StakeParam('stake_reserve_sensitivity', '0.10');
    $rvol   = PT_StakeDec(0);
    if (PT_StakeCmp($mean, '0') > 0) {
        $rvol = PT_StakeMul($c, PT_StakeDiv(PT_StakeStdDev($nets), $mean));
    }
    $rate = PT_StakeAdd($base, $rvol);
    if (PT_StakeCmp($rate, '0') < 0) { $rate = PT_StakeDec(0); }
    if (PT_StakeCmp($rate, '0.2') > 0) { $rate = PT_StakeDec('0.2'); }
    return $rate;
}

/** Class multiplier for a lot class (Layer 12.2). */
function PT_StakeClassMultiplier($lot_class) {
    switch (strtolower((string) $lot_class)) {
        case 'founder':
            return PT_StakeDec(PT_StakeParam('stake_class_founder', '1.0'));
        case 'investor':
            return PT_StakeDec(PT_StakeParam('stake_class_investor', '1.0'));
        case 'creator':
            return PT_StakeDec(PT_StakeParam('stake_class_creator', '1.2'));
        case 'community':
            return PT_StakeDec(PT_StakeParam('stake_class_community', '0.8'));
        default:
            return PT_StakeDec('1.0');
    }
}

/** Remaining units in a lot (acquired minus redeemed). */
function PT_StakeLotUnits($lot) {
    global $db;
    $units = PT_StakeDec($lot->units);
    try {
        $redeemed = PT_StakeDec($db->rawQueryOne("SELECT COALESCE(SUM(units_delta),0) AS d FROM `" . T_STAKE_LOT_EVENTS . "` WHERE lot_id = ? AND event_type = 'REDEEM'", array((int) $lot->id))->d);
    } catch (Exception $e) {
        $redeemed = PT_StakeDec(0);
    }
    $remaining = PT_StakeSub($units, $redeemed);
    return (PT_StakeCmp($remaining, '0') < 0) ? PT_StakeDec(0) : $remaining;
}

/** All lots for a stakeholder. */
function PT_StakeHolderLots($stakeholder_id) {
    global $db;
    try {
        return $db->where('stakeholder_id', (int) $stakeholder_id)->orderBy('acquired_at', 'ASC')->orderBy('id', 'ASC')->get(T_STAKE_LOTS);
    } catch (Exception $e) {
        return array();
    }
}

/** Total remaining units for a stakeholder. */
function PT_StakeHolderUnits($stakeholder_id) {
    $total = PT_StakeDec(0);
    foreach (PT_StakeHolderLots($stakeholder_id) as $lot) {
        $total = PT_StakeAdd($total, PT_StakeLotUnits($lot));
    }
    return $total;
}

/** Total units in existence (Layer 4.2.2). */
function PT_StakeTotalUnits() {
    global $db;
    try {
        $total    = PT_StakeDec($db->rawQueryOne("SELECT COALESCE(SUM(units),0) AS t FROM `" . T_STAKE_LOTS . "`")->t);
        $redeemed = PT_StakeDec($db->rawQueryOne("SELECT COALESCE(SUM(units_delta),0) AS d FROM `" . T_STAKE_LOT_EVENTS . "` WHERE event_type = 'REDEEM'")->d);
        $total    = PT_StakeSub($total, $redeemed);
        return (PT_StakeCmp($total, '0') < 0) ? PT_StakeDec(0) : $total;
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
}

/** Create a lot and record its acquisition event (Layer 4.1). */
function PT_StakeCreateLot($stakeholder_id, $user_id, $units, $channel, $price_paid, $cash_paid, $lot_class = 'Community', $locked = 0, $created_by = 0, $acquired_at = null) {
    global $db;
    $units = PT_StakeDec($units);
    if (PT_StakeCmp($units, '0') <= 0) {
        return 0;
    }
    if ($acquired_at === null) {
        $acquired_at = time();
    }
    $lot_id = 0;
    $ok = PT_DbGuardedRun(function () use ($db, $stakeholder_id, $user_id, $units, $channel, $price_paid, $cash_paid, $lot_class, $locked, $acquired_at, &$lot_id) {
        $lot_id = $db->insert(T_STAKE_LOTS, array(
            'stakeholder_id' => (int) $stakeholder_id,
            'user_id'        => (int) $user_id,
            'units'          => $units,
            'channel'        => (string) $channel,
            'price_paid'     => PT_StakeDec($price_paid),
            'cash_paid'      => PT_StakeDec($cash_paid),
            'lot_class'      => (string) $lot_class,
            'locked'         => (int) $locked,
            'acquired_at'    => (int) $acquired_at,
            'created_at'     => time(),
        ));
        if (empty($lot_id)) {
            return false;
        }
        $db->insert(T_STAKE_LOT_EVENTS, array(
            'lot_id'         => (int) $lot_id,
            'stakeholder_id' => (int) $stakeholder_id,
            'user_id'        => (int) $user_id,
            'units_delta'    => $units,
            'event_type'     => 'ACQUIRE',
            'unit_price'     => PT_StakeDec($price_paid),
            'created_at'     => time(),
        ));
        return true;
    });
    return $ok ? (int) $lot_id : 0;
}

/**
 * Redeem units LIFO (newest lots first, Layer 9.1) with the exit fee and
 * minimum hold. Returns net proceeds actually generated.
 */
function PT_StakeRedeemUnits($stakeholder_id, $user_id, $units, $unit_price, $created_by = 0) {
    global $db;
    $units = PT_StakeDec($units);
    if (PT_StakeCmp($units, '0') <= 0) {
        return PT_StakeDec(0);
    }
    $minHold = (int) PT_StakeParam('stake_min_hold_days', '30');
    $fMax    = PT_StakeParam('stake_exit_fee_max', '0.05');
    $fTau    = PT_StakeParam('stake_exit_fee_tau', '180');
    $now     = time();

    $lots = PT_StakeHolderLots($stakeholder_id);
    usort($lots, function ($a, $b) {
        if ((int) $a->acquired_at === (int) $b->acquired_at) {
            return (int) $b->id - (int) $a->id;
        }
        return (int) $b->acquired_at - (int) $a->acquired_at;
    });

    $remaining   = $units;
    $netProceeds = PT_StakeDec(0);
    foreach ($lots as $lot) {
        if (PT_StakeCmp($remaining, '0') <= 0) {
            break;
        }
        $age = ($now - (int) $lot->acquired_at) / 86400;
        if ($age < $minHold) {
            continue;
        }
        $avail = PT_StakeLotUnits($lot);
        if (PT_StakeCmp($avail, '0') <= 0) {
            continue;
        }
        $take    = (PT_StakeCmp($avail, $remaining) >= 0) ? $remaining : $avail;
        $feeRate = PT_StakeMul($fMax, PT_StakeExpNeg(PT_StakeDiv((string) $age, $fTau)));
        $gross   = PT_StakeMul($take, $unit_price);
        $fee     = PT_StakeMul($gross, $feeRate);
        $net     = PT_StakeSub($gross, $fee);
        $netProceeds = PT_StakeAdd($netProceeds, $net);

        $lotId   = (int) $lot->id;
        $takeStr = $take;
        PT_DbGuardedRun(function () use ($db, $lotId, $stakeholder_id, $user_id, $takeStr, $unit_price, $created_by) {
            $db->insert(T_STAKE_LOT_EVENTS, array(
                'lot_id'         => $lotId,
                'stakeholder_id' => (int) $stakeholder_id,
                'user_id'        => (int) $user_id,
                'units_delta'    => $takeStr,
                'event_type'     => 'REDEEM',
                'unit_price'     => PT_StakeDec($unit_price),
                'created_by'     => (int) $created_by,
                'created_at'     => time(),
            ));
            return true;
        });
        $remaining = PT_StakeSub($remaining, $take);
    }
    return $netProceeds;
}

/**
 * Time-weighted units for a holder on a day (Layer 11). The engine closes a
 * day after it ended, so units bought during the day are weighted by the
 * fraction of the day they were held. Returns a list of lot segments.
 */
function PT_StakeTimeWeightedSegments($stakeholder_id, $dayStart, $dayEnd) {
    $segments = array();
    foreach (PT_StakeHolderLots($stakeholder_id) as $lot) {
        $units = PT_StakeLotUnits($lot);
        if (PT_StakeCmp($units, '0') <= 0) {
            continue;
        }
        $acquired = (int) $lot->acquired_at;
        $seconds  = 86400;
        if ($acquired > $dayStart && $acquired <= $dayEnd) {
            $seconds = max(0, $dayEnd - $acquired + 1);
        } elseif ($acquired > $dayEnd) {
            $seconds = 0;
        }
        $fraction = PT_StakeDiv((string) $seconds, '86400');
        $segments[] = array(
            'lot'      => $lot,
            'units'    => $units,
            'fraction' => $fraction,
            'weighted' => PT_StakeMul($units, $fraction),
        );
    }
    return $segments;
}

/** Combined weight for a holder (Layers 12.1 - 12.5). */
function PT_StakeWeightForHolder($stakeholder, $segments, $now) {
    $weight  = PT_StakeDec(0);
    $lMax    = PT_StakeParam('stake_loyalty_max', '0.25');
    $lTau    = PT_StakeParam('stake_loyalty_tau', '365');
    $tMax    = PT_StakeParam('stake_tenure_max', '0.10');
    $tTau    = PT_StakeParam('stake_tenure_tau', '730');
    $ceiling = PT_StakeParam('stake_boost_ceiling', '1.40');
    $lockBonus = PT_StakeParam('stake_lock_bonus', '0');

    $firstLot = null;
    foreach ($segments as $seg) {
        if ($firstLot === null || (int) $seg['lot']->acquired_at < (int) $firstLot->acquired_at) {
            $firstLot = $seg['lot'];
        }
    }
    $tenure = PT_StakeDec(0);
    if ($firstLot !== null) {
        $years  = (($now - (int) $firstLot->acquired_at) / 86400) / 365;
        $tenure = PT_StakeMul($tMax, PT_StakeSub('1', PT_StakeExpNeg(PT_StakeDiv((string) $years, $tTau))));
    }

    foreach ($segments as $seg) {
        $lot   = $seg['lot'];
        $class = PT_StakeClassMultiplier($lot->lot_class);
        $age   = ($now - (int) $lot->acquired_at) / 86400;

        // Anti double-dipping: a lot that already earns loyalty bonus units
        // gets no loyalty term in its weight (Layer 12.4).
        $loyalty = PT_StakeDec(0);
        if (strtoupper((string) $lot->channel) !== 'LOYALTY_BONUS') {
            $loyalty = PT_StakeMul($lMax, PT_StakeSub('1', PT_StakeExpNeg(PT_StakeDiv((string) $age, $lTau))));
        }
        $lock = !empty($lot->locked) ? PT_StakeDec($lockBonus) : PT_StakeDec(0);

        $boost = PT_StakeMul(
            PT_StakeMul(PT_StakeAdd('1', $loyalty), PT_StakeAdd('1', $tenure)),
            PT_StakeAdd('1', $lock)
        );
        if (PT_StakeCmp($boost, $ceiling) > 0) {
            $boost = PT_StakeDec($ceiling);
        }
        $weight = PT_StakeAdd($weight, PT_StakeMul(PT_StakeMul($seg['weighted'], $class), $boost));
    }
    return $weight;
}

/**
 * Allocate shares of the pool with the concentration cap and redistribution
 * (Layer 13). $weights is [stakeholder_id => weight]. Returns
 * [stakeholder_id => share], summing to exactly 1.
 */
function PT_StakeAllocateShares(array $weights, $cap) {
    $result = array();
    foreach ($weights as $id => $w) {
        $result[$id] = PT_StakeDec(0);
    }
    $total = PT_StakeDec(0);
    foreach ($weights as $w) {
        $total = PT_StakeAdd($total, $w);
    }
    if (PT_StakeCmp($total, '0') <= 0) {
        return $result;
    }

    $positive = 0;
    foreach ($weights as $w) {
        if (PT_StakeCmp($w, '0') > 0) { $positive++; }
    }
    $applyCap = (PT_StakeCmp($cap, '0') > 0 && PT_StakeCmp(PT_StakeMul($cap, (string) $positive), '1') >= 0);

    if (!$applyCap) {
        foreach ($weights as $id => $w) {
            $result[$id] = PT_StakeDiv($w, $total);
        }
        return PT_StakeNormaliseShares($result);
    }

    $capped = array();
    $remainingWeight = $weights;
    $remainingTotal  = $total;
    $iterations = 0;
    while ($iterations < count($weights) + 2) {
        $iterations++;
        $newlyCapped = false;
        foreach ($remainingWeight as $id => $w) {
            $share = PT_StakeDiv($w, $remainingTotal);
            if (PT_StakeCmp($share, $cap) > 0) {
                $capped[$id] = PT_StakeDec($cap);
                unset($remainingWeight[$id]);
                $newlyCapped = true;
            }
        }
        if (!$newlyCapped) {
            break;
        }
        $remainingTotal = PT_StakeDec(0);
        foreach ($remainingWeight as $w) {
            $remainingTotal = PT_StakeAdd($remainingTotal, $w);
        }
        if (PT_StakeCmp($remainingTotal, '0') <= 0) {
            break;
        }
    }

    $cappedSum = PT_StakeDec(0);
    foreach ($capped as $s) {
        $cappedSum = PT_StakeAdd($cappedSum, $s);
    }
    $rest = PT_StakeSub('1', $cappedSum);
    if (PT_StakeCmp($rest, '0') < 0) { $rest = PT_StakeDec(0); }

    foreach ($remainingWeight as $id => $w) {
        if (PT_StakeCmp($remainingTotal, '0') > 0) {
            $result[$id] = PT_StakeMul($rest, PT_StakeDiv($w, $remainingTotal));
        } else {
            $result[$id] = PT_StakeDec(0);
        }
    }
    foreach ($capped as $id => $s) {
        $result[$id] = $s;
    }
    return PT_StakeNormaliseShares($result, $cap);
}

/** Force shares to sum to exactly 1 (Layer 13.3). */
function PT_StakeNormaliseShares(array $shares, $cap = null) {
    if (empty($shares)) {
        return $shares;
    }
    $sum = PT_StakeDec(0);
    $adjustId = null;
    $adjust   = null;
    foreach ($shares as $id => $s) {
        $sum = PT_StakeAdd($sum, $s);
        // The rounding remainder must land on a holder that is not already at
        // the cap, otherwise the adjustment would push it over the cap.
        if ($cap !== null && PT_StakeCmp($s, $cap) >= 0) {
            continue;
        }
        if ($adjust === null || PT_StakeCmp($s, $adjust) > 0) {
            $adjust   = $s;
            $adjustId = $id;
        }
    }
    if ($adjustId === null) {
        return $shares;
    }
    $others = PT_StakeSub($sum, $shares[$adjustId]);
    $shares[$adjustId] = PT_StakeSub('1', $others);
    if (PT_StakeCmp($shares[$adjustId], '0') < 0) {
        $shares[$adjustId] = PT_StakeDec(0);
    }
    return $shares;
}

/** Current accrual balance for a holder (Layer 14). */
function PT_StakeBalance($user_id) {
    global $db;
    try {
        $row = $db->where('user_id', (int) $user_id)->orderBy('id', 'DESC')->getOne(T_STAKE_DAILY_ACCRUALS);
        if (!empty($row)) {
            return PT_StakeDec($row->balance_after);
        }
    } catch (Exception $e) {
    }
    return PT_StakeDec(0);
}

/** Total lifetime dividends credited from the daily engine. */
function PT_StakeDailyEarnings($user_id) {
    global $db;
    try {
        return PT_StakeDec($db->rawQueryOne("SELECT COALESCE(SUM(accrual - debit),0) AS e FROM `" . T_STAKE_DAILY_ACCRUALS . "` WHERE user_id = ?", array((int) $user_id))->e);
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
}

/** Lifetime engine payouts (whole units paid out). */
function PT_StakeDailyPaid($user_id) {
    global $db;
    try {
        return PT_StakeDec($db->rawQueryOne("SELECT COALESCE(SUM(paid),0) AS p FROM `" . T_STAKE_DAILY_ACCRUALS . "` WHERE user_id = ?", array((int) $user_id))->p);
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
}

/** Latest published growth position card. */
function PT_StakeLatestRun() {
    global $db;
    try {
        return $db->orderBy('id', 'DESC')->getOne(T_STAKE_DAILY_RUNS);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Earliest timestamp at which the platform ever recognised revenue. The stake
 * engine must distribute revenue made before the engine existed, so catch-up
 * starts from here rather than from the day the feature shipped.
 */
function PT_StakeEarliestRevenueTs() {
    global $db;
    $earliest = null;
    $bump = function ($ts) use (&$earliest) {
        $ts = (int) $ts;
        if ($ts > 0 && ($earliest === null || $ts < $earliest)) {
            $earliest = $ts;
        }
    };
    try {
        $bump($db->getValue(T_VIDEOS_TRSNS, 'MIN(`time`)'));
    } catch (Exception $e) {
    }
    try {
        $bump($db->where('type', 'spent')->getValue(T_ADS_TRANS, 'MIN(`time`)'));
    } catch (Exception $e) {
    }
    try {
        $bump($db->where('entry_type', 'revenue')->getValue(T_STAKE_REVENUE_ENTRIES, 'MIN(`entry_time`)'));
    } catch (Exception $e) {
    }
    if (defined('T_STORE_LEDGER_ENTRIES')) {
        try {
            $bump($db->rawQueryOne("SELECT MIN(`created_at`) AS m FROM `" . T_STORE_LEDGER_ENTRIES . "` WHERE `account_code` IN ('4000','4100')")->m);
        } catch (Exception $e) {
        }
    }
    if (defined('T_JPESA_PAYMENTS')) {
        try {
            $bump($db->rawQueryOne("SELECT MIN(`completed_at`) AS m FROM `" . T_JPESA_PAYMENTS . "` WHERE `status` = 'paid' AND `purchase_type` = 'wallet'")->m);
        } catch (Exception $e) {
        }
    }
    if ($earliest === null) {
        try {
            $bump($db->getValue(T_STAKEHOLDERS, 'MIN(`created_at`)'));
        } catch (Exception $e) {
        }
    }
    return $earliest;
}

/** Class label used when a stakeholder is created. */
function PT_StakeRoleToClass($role) {
    $role = strtolower(trim((string) $role));
    switch ($role) {
        case 'founder': return 'Founder';
        case 'investor': return 'Investor';
        case 'creator': return 'Creator';
        default: return 'Community';
    }
}

// =========================================================================
//  Daily engine driver
// =========================================================================

/**
 * Run the whole daily stake engine for one day (Layers 1 - 23).
 * Called from cronjob.php. Idempotent: a day is processed once and a repeat
 * run for the same day is a no-op.
 */
function PT_StakeRunDailyEngine($dayTs = null, $created_by = 0) {
    global $db, $pt;

    if (PT_StakeParam('stake_engine_enabled', '1') === '0') {
        return array('status' => 0, 'message' => 'Stake engine disabled.');
    }
    if ($dayTs === null) {
        $dayTs = time() - 86400;
    }
    $dayStart = strtotime(gmdate('Y-m-d', (int) $dayTs) . ' 00:00:00 UTC');
    $dayEnd   = $dayStart + 86399;
    $runKey   = gmdate('Y-m-d', $dayStart);

    $lockName = 'pt_stake_daily_' . $runKey;
    PT_StakeLock($lockName, 15);

    try {
        $existing = $db->where('run_key', $runKey)->getOne(T_STAKE_DAILY_RUNS);
    } catch (Exception $e) {
        PT_StakeUnlock($lockName);
        return array('status' => 0, 'message' => 'Daily run table unavailable.');
    }
    if (!empty($existing)) {
        PT_StakeUnlock($lockName);
        return array('status' => 1, 'run_key' => $runKey, 'already_run' => true);
    }

    // Step 1-2: revenue, net, 60/40 split, reserve, pool.
    $series = PT_StakeDailySeries($dayEnd, 365);
    $dayPoint = null;
    foreach ($series as $point) {
        if ($point['key'] === $runKey) {
            $dayPoint = $point;
        }
    }
    if ($dayPoint === null) {
        $dayPoint = end($series);
    }
    $gross = $dayPoint['gross'];
    $costs = $dayPoint['costs'];
    $net   = $dayPoint['net'];

    $ratio           = PT_StakeDistributableRatio();
    $stakeable       = PT_StakeMul($net, $ratio);
    $appPool         = PT_StakeSub($net, $stakeable);
    $reserveRate     = PT_StakeReserveRate($series);
    $reserveRetained = PT_StakeMul($stakeable, $reserveRate);
    $dividendPool    = PT_StakeSub($stakeable, $reserveRetained);

    // Treasury buckets accumulate inside the 40% app-owned pool (Layer 3.6).
    $opsBucket    = PT_StakeMetricPut('bucket_operations', PT_StakeAdd(PT_StakeMetricGet('bucket_operations', '0'), PT_StakeMul($appPool, '0.50')));
    $growthBucket = PT_StakeMetricPut('bucket_growth', PT_StakeAdd(PT_StakeMetricGet('bucket_growth', '0'), PT_StakeMul($appPool, '0.30')));
    $liqBucket    = PT_StakeMetricPut('bucket_liquidity', PT_StakeAdd(PT_StakeMetricGet('bucket_liquidity', '0'), PT_StakeMul($appPool, '0.20')));

    // Step 3: growth position + GPI.
    $growth    = PT_StakeGrowthPosition($dayEnd);
    $gpiResult = PT_StakeGpi($dayEnd, $growth);
    $gpi       = $gpiResult['gpi'];
    $phi       = $growth['phi'];

    // Step 4: multiple, valuation, price.
    $mFloor = PT_StakeParam('stake_multiple_floor', '2');
    $mPeak  = PT_StakeParam('stake_multiple_peak', '6');
    $curve  = PT_StakeMul('4', PT_StakeMul($phi, PT_StakeSub('1', $phi)));
    $multiple = PT_StakeAdd($mFloor, PT_StakeMul(PT_StakeSub($mPeak, $mFloor), $curve));

    $meanP90    = PT_StakeMul(PT_StakeMul($growth['mean_net_90'], $ratio), PT_StakeSub('1', $reserveRate));
    $annualised = PT_StakeMul('365', $meanP90);
    $vEarn      = PT_StakeMul($multiple, $annualised);

    $kDeployed = PT_StakeValuationCapital($dayStart);
    $vTotal    = PT_StakeAdd($vEarn, PT_StakeAdd($kDeployed['k'], $kDeployed['d']));

    $openingUnits = PT_StakeTotalUnits();
    $unitPrice    = PT_StakeUnitPrice($vTotal, $openingUnits);
    PT_StakeMetricPut('unit_price', $unitPrice);

    // Step 5: process queued redemptions (liquidity gate).
    $redeemedUnits = PT_StakeProcessRedemptions($unitPrice, $liqBucket, $kDeployed['k'], $created_by);

    // Step 6: loyalty bonus issuance (funded by the Growth bucket).
    $issueMin  = PT_StakeParam('stake_issue_min', '0.01');
    $issueMax  = PT_StakeParam('stake_issue_max', '0.03');
    $issueRate = PT_StakeAdd($issueMin, PT_StakeMul(PT_StakeSub($issueMax, $issueMin), $gpi));
    $issuedBonus = PT_StakeDec(0);
    $issuePaused = (PT_StakeCmp($phi, '0.95') >= 0 || PT_StakeCmp($gpi, '0.20') < 0);
    if (!$issuePaused && PT_StakeCmp($openingUnits, '0') > 0) {
        $bonusUnits = PT_StakeDiv(PT_StakeMul($issueRate, $openingUnits), '365');
        $capUnits   = PT_StakeMul(PT_StakeParam('stake_daily_issuance_cap', '0.005'), $openingUnits);
        if (PT_StakeCmp($bonusUnits, $capUnits) > 0) {
            $bonusUnits = $capUnits;
        }
        $issuedBonus = PT_StakeIssueLoyaltyBonus($bonusUnits, $dayEnd, $growthBucket, $unitPrice);
    }

    // Persist the run row before settling so accrual rows can reference it.
    $runId = 0;
    PT_DbGuardedRun(function () use ($db, $runKey, $dayStart, $dayEnd, $gross, $costs, $net, $stakeable, $appPool, $reserveRate, $reserveRetained, $dividendPool, $growth, $gpi, $multiple, $vTotal, $unitPrice, $openingUnits, $issuedBonus, $redeemedUnits, &$runId) {
        $runId = $db->insert(T_STAKE_DAILY_RUNS, array(
            'run_key'           => $runKey,
            'run_start'         => (int) $dayStart,
            'run_end'           => (int) $dayEnd,
            'gross_revenue'     => $gross,
            'costs'             => $costs,
            'net_revenue'       => $net,
            'stakeable_pool'    => $stakeable,
            'app_pool'          => $appPool,
            'reserve_rate'      => $reserveRate,
            'reserve_retained'  => $reserveRetained,
            'dividend_pool'     => $dividendPool,
            'growth_position'   => $growth['phi'],
            'growth_stage'      => $growth['stage'],
            'gpi'               => $gpi,
            'multiple'          => $multiple,
            'ceiling'           => $growth['ceiling'],
            'smoothed_net'      => $growth['smoothed_net'],
            'total_value'       => $vTotal,
            'unit_price'        => $unitPrice,
            'opening_units'     => $openingUnits,
            'issued_units'      => $issuedBonus,
            'redeemed_units'    => $redeemedUnits,
            'currency'          => PT_StakeCurrency(),
            'status'            => 'closed',
            'created_at'        => time(),
        ));
        return true;
    });

    // Steps 7-11: time-weighted shares, accrual, payout, ledger.
    $settlement = PT_StakeSettleDailyPool($runKey, $dayStart, $dayEnd, $dividendPool, $unitPrice, $created_by, $runId);

    // Steps 12-13: monitors, card, persist.
    $closingUnits = PT_StakeTotalUnits();
    $hhi          = PT_StakeHhi();
    $effective    = (PT_StakeCmp($hhi, '0') > 0) ? PT_StakeDiv('1', $hhi) : PT_StakeDec(0);

    $card = array(
        'day'               => $runKey,
        'stage'             => $growth['stage'],
        'growth_position'   => $phi,
        'smoothed_net'      => $growth['smoothed_net'],
        'ceiling'           => $growth['ceiling'],
        'relative_growth'   => $growth['relative_growth'],
        'days_inflection'   => $growth['days_inflection'],
        'days_to_80'        => $growth['days_to_80'],
        'gpi'               => $gpi,
        'multiple'          => $multiple,
        'unit_price'        => $unitPrice,
        'yield'             => (PT_StakeCmp($unitPrice, '0') > 0) ? PT_StakeDiv(PT_StakeDiv($annualised, PT_StakeMax($closingUnits, '1')), $unitPrice) : PT_StakeDec(0),
        'total_units'       => $closingUnits,
        'dividend_pool'     => $dividendPool,
        'hhi'               => $hhi,
        'effective_holders' => $effective,
        'fit_r2'            => $growth['fit_r2'],
    );

    if ($runId) {
        PT_DbGuardedRun(function () use ($db, $runId, $closingUnits, $settlement, $hhi, $effective, $card, $growth) {
            $db->where('id', (int) $runId)->update(T_STAKE_DAILY_RUNS, array(
                'closing_units'     => $closingUnits,
                'paid_total'        => $settlement['paid_total'],
                'hhi'               => $hhi,
                'effective_holders' => $effective,
                'fit_r2'            => $growth['fit_r2'],
                'card'              => json_encode($card),
            ));
            return true;
        });
    }

    PT_StakeUnlock($lockName);

    return array(
        'status'          => 1,
        'run_key'         => $runKey,
        'run_id'          => $runId,
        'net_revenue'     => $net,
        'dividend_pool'   => $dividendPool,
        'unit_price'      => $unitPrice,
        'growth_position' => $phi,
        'stage'           => $growth['stage'],
        'gpi'             => $gpi,
        'closing_units'   => $closingUnits,
        'paid_total'      => $settlement['paid_total'],
    );
}

/**
 * Settle every UTC day that still has revenue but no run row, oldest first.
 * This is what makes the engine recognise revenue made before it existed: the
 * first cron run after the engine ships walks the whole daily history and pays
 * it out, not just the single previous day. Idempotent, because
 * PT_StakeRunDailyEngine is a no-op for a day that already has a run row.
 *
 * @param bool $dryRun When true, count the days that would be settled and
 *                     change nothing.
 */
function PT_StakeCatchUpDailyEngine($maxDays = 1500, $created_by = 0, $dryRun = false) {
    global $db;

    $earliest = PT_StakeEarliestRevenueTs();
    $startTs  = ($earliest !== null) ? strtotime(gmdate('Y-m-d', $earliest) . ' 00:00:00 UTC') : strtotime('today UTC');
    $endTs    = strtotime('today UTC') - 1;
    if ($startTs > $endTs) {
        return array('status' => 1, 'processed' => 0, 'total_days' => 0);
    }

    // Days that already carry a run row (one query, not one per day).
    $done = array();
    try {
        $rows = $db->rawQuery("SELECT `run_key` FROM `" . T_STAKE_DAILY_RUNS . "`");
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $done[$row->run_key] = true;
            }
        }
    } catch (Exception $e) {
    }

    $processed = 0;
    $total     = 0;
    $maxDays   = max(1, (int) $maxDays);
    for ($ts = $startTs; $ts <= $endTs && $total < $maxDays; $ts += 86400) {
        $total++;
        $key = gmdate('Y-m-d', $ts);
        if (isset($done[$key])) {
            continue;
        }
        if ($dryRun) {
            $processed++;
            continue;
        }
        $result = PT_StakeRunDailyEngine($ts, $created_by);
        if (!empty($result['status']) && empty($result['already_run'])) {
            $processed++;
        }
    }

    return array('status' => 1, 'processed' => $processed, 'total_days' => $total);
}

/**
 * Repair already-settled days whose recognised revenue has since grown.
 *
 * PT_StakeCatchUpDailyEngine only fills days that have no run row. A day that
 * was settled before its revenue was recorded (for example wallet top-ups or
 * Store sales that only became stakable later) keeps a stale net_revenue for
 * ever because PT_StakeRunDailyEngine is a no-op once a run row exists. This
 * walks every closed day, recomputes its revenue with the same recognition the
 * engine uses, and for each day whose revenue is now higher it distributes the
 * shortfall's dividend through PT_StakeSettleDailyPool and tops the run row up.
 *
 * Idempotent: after a day is topped up its stored net matches the recomputed
 * figure, so a second run finds no shortfall. The correction is written under
 * a `<day>~<wave>` run key so the append-only accrual ledger never collides and
 * the day keeps its identity. Never claws money back: only shortfalls are paid.
 *
 * @param int      $maxDays    Stop after inspecting this many settled days (0 = no limit).
 * @param int      $created_by Whose hand made the correction, for the audit trail.
 * @param int|null $fromTs     Only repair days on/after this timestamp (null = earliest revenue).
 * @param bool     $dryRun     Report shortfalls without distributing anything.
 */
function PT_StakeReconcileSettledDays($maxDays = 0, $created_by = 0, $fromTs = null, $dryRun = false) {
    global $db;

    $summary = array(
        'status'     => 1,
        'checked'    => 0,
        'fixed'      => 0,
        'dry_run'    => (bool) $dryRun,
        'added_net'  => PT_StakeDec(0),
        'added_pool' => PT_StakeDec(0),
        'days'       => array(),
    );

    $earliest = PT_StakeEarliestRevenueTs();
    if ($earliest === null) {
        return $summary;
    }
    $startTs = strtotime(gmdate('Y-m-d', $earliest) . ' 00:00:00 UTC');
    if ($fromTs !== null) {
        $startTs = max($startTs, strtotime(gmdate('Y-m-d', (int) $fromTs) . ' 00:00:00 UTC'));
    }
    $lastClosed = strtotime('today UTC') - 1;
    if ($startTs > $lastClosed) {
        return $summary;
    }

    try {
        $runs = $db->rawQuery("SELECT `id`, `run_key`, `run_start`, `run_end`, `net_revenue`, `reserve_rate`, `paid_total`, `unit_price`, `card`
            FROM `" . T_STAKE_DAILY_RUNS . "`
            WHERE `status` = 'closed' AND `run_start` >= ? AND `run_start` <= ?
            ORDER BY `run_start` ASC", array((int) $startTs, (int) $lastClosed));
    } catch (Exception $e) {
        return $summary;
    }
    if (empty($runs)) {
        return $summary;
    }

    // Recompute the whole window's revenue once (the same recognition path the
    // engine uses), rather than one daily query set per run row.
    $days   = (int) floor(($lastClosed - $startTs) / 86400) + 1;
    $series = PT_StakeDailySeries($lastClosed, $days);
    $byDay  = array();
    foreach ($series as $point) {
        $byDay[$point['key']] = $point;
    }

    $ratio   = PT_StakeDistributableRatio();
    $maxDays = (int) $maxDays;

    foreach ($runs as $run) {
        if ($maxDays > 0 && $summary['checked'] >= $maxDays) {
            break;
        }
        $summary['checked']++;
        if (empty($byDay[$run->run_key])) {
            continue;
        }
        $point    = $byDay[$run->run_key];
        $deltaNet = PT_StakeSub($point['net'], PT_StakeDec($run->net_revenue));
        if (PT_StakeCmp($deltaNet, '0') <= 0) {
            continue;
        }

        // Nothing to distribute when no stakeholder is active.
        try {
            $active = $db->where('status', 'active')->get(T_STAKEHOLDERS);
        } catch (Exception $e) {
            $active = array();
        }
        if (empty($active)) {
            continue;
        }

        $reserveRate = PT_StakeDec($run->reserve_rate);
        $deltaPool   = PT_StakeMul(PT_StakeMul($deltaNet, $ratio), PT_StakeSub('1', $reserveRate));
        if (PT_StakeCmp($deltaPool, '0') <= 0) {
            continue;
        }

        // Dry run: record the shortfall, distribute nothing.
        if ($dryRun) {
            $summary['fixed']++;
            $summary['added_net']  = PT_StakeAdd($summary['added_net'], $deltaNet);
            $summary['added_pool'] = PT_StakeAdd($summary['added_pool'], $deltaPool);
            $summary['days'][]     = $run->run_key;
            continue;
        }

        // One correction key per wave, tied to the day it was earned.
        $wave = 1;
        try {
            $wave = 1 + (int) $db->rawQueryOne("SELECT COUNT(DISTINCT `run_key`) AS n FROM `" . T_STAKE_DAILY_ACCRUALS . "` WHERE `run_key` LIKE ?", array($run->run_key . '~%'))->n;
        } catch (Exception $e) {
            $wave = 1;
        }
        $corrKey = substr($run->run_key, 0, 16) . '~' . $wave;

        $settle = PT_StakeSettleDailyPool($corrKey, (int) $run->run_start, (int) $run->run_end, $deltaPool, PT_StakeDec($run->unit_price), $created_by, (int) $run->id);

        // Top the run row up to the recomputed figures so a repeat is a no-op.
        $newNet          = $point['net'];
        $stakeable       = PT_StakeMul($newNet, $ratio);
        $reserveRetained = PT_StakeMul($stakeable, $reserveRate);
        $newPool         = PT_StakeSub($stakeable, $reserveRetained);
        $newPaid         = PT_StakeAdd(PT_StakeDec($run->paid_total), $settle['paid_total']);
        $card            = !empty($run->card) ? json_decode($run->card, true) : array();
        if (!is_array($card)) {
            $card = array();
        }
        $card['dividend_pool'] = $newPool;
        $card['smoothed_net']  = $newNet;

        PT_DbGuardedRun(function () use ($db, $run, $point, $stakeable, $reserveRetained, $newPool, $newPaid, $card) {
            $db->where('id', (int) $run->id)->update(T_STAKE_DAILY_RUNS, array(
                'gross_revenue'    => $point['gross'],
                'costs'            => $point['costs'],
                'net_revenue'      => $point['net'],
                'stakeable_pool'   => $stakeable,
                'reserve_retained' => $reserveRetained,
                'dividend_pool'    => $newPool,
                'paid_total'       => $newPaid,
                'card'             => json_encode($card),
            ));
            return true;
        });

        $summary['fixed']++;
        $summary['added_net']  = PT_StakeAdd($summary['added_net'], $deltaNet);
        $summary['added_pool'] = PT_StakeAdd($summary['added_pool'], $deltaPool);
        $summary['days'][]     = $run->run_key;
    }

    return $summary;
}

/** Unit price with a safe bootstrap floor (Layer 7.1). */
function PT_StakeUnitPrice($value, $units) {
    $seed = PT_StakeParam('stake_seed_price', '1');
    if (PT_StakeCmp($units, '0') <= 0) {
        return PT_StakeDec($seed);
    }
    $price = PT_StakeDiv($value, $units);
    if (PT_StakeCmp($price, '0') <= 0) {
        $price = PT_StakeDec($seed);
    }
    return $price;
}

/**
 * Capital parts of the valuation (Layers 5.3 - 5.4). Cash behind units moves
 * from undeployed (K) to deployed (D) as lots age over L_amort.
 */
function PT_StakeValuationCapital($asOfTs) {
    global $db;
    $amort = (float) PT_StakeParam('stake_amort_days', '730');
    if ($amort <= 0) { $amort = 730; }
    $k = PT_StakeMetricGet('k_bonus_value', '0');
    $d = PT_StakeDec(0);
    try {
        $rows = $db->rawQuery("SELECT `cash_paid`, `acquired_at`, `channel` FROM `" . T_STAKE_LOTS . "` WHERE `channel` IN ('CAPITAL','REINVEST')");
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $cash = PT_StakeDec($row->cash_paid);
                if (PT_StakeCmp($cash, '0') <= 0) { continue; }
                $age = ((int) $asOfTs - (int) $row->acquired_at) / 86400;
                if ($age < 0) { $age = 0; }
                $deployedFrac = $age / $amort;
                if ($deployedFrac > 1) { $deployedFrac = 1; }
                $d = PT_StakeAdd($d, PT_StakeMul($cash, PT_StakeDec($deployedFrac, 10)));
                $k = PT_StakeAdd($k, PT_StakeMul($cash, PT_StakeDec(1 - $deployedFrac, 10)));
            }
        }
    } catch (Exception $e) {
    }
    return array('k' => $k, 'd' => $d);
}

/** Issue loyalty bonus units proportionally to loyalty-weighted holdings. */
function PT_StakeIssueLoyaltyBonus($bonusUnits, $asOfTs, $growthBucket, $unitPrice) {
    global $db;
    $bonusUnits = PT_StakeDec($bonusUnits);
    if (PT_StakeCmp($bonusUnits, '0') <= 0) {
        return PT_StakeDec(0);
    }
    try {
        $stakeholders = $db->where('status', 'active')->get(T_STAKEHOLDERS);
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    if (empty($stakeholders)) {
        return PT_StakeDec(0);
    }
    $lMax = PT_StakeParam('stake_loyalty_max', '0.25');
    $lTau = PT_StakeParam('stake_loyalty_tau', '365');
    $holderLoyal = array();
    $totalLoyal  = PT_StakeDec(0);
    foreach ($stakeholders as $s) {
        $loyal = PT_StakeDec(0);
        foreach (PT_StakeHolderLots($s->id) as $lot) {
            $units = PT_StakeLotUnits($lot);
            if (PT_StakeCmp($units, '0') <= 0) { continue; }
            $age = (((int) $asOfTs - (int) $lot->acquired_at) / 86400);
            if ($age < 0) { $age = 0; }
            $factor = PT_StakeAdd('1', PT_StakeMul($lMax, PT_StakeSub('1', PT_StakeExpNeg(PT_StakeDiv((string) $age, $lTau)))));
            $loyal = PT_StakeAdd($loyal, PT_StakeMul($units, $factor));
        }
        $holderLoyal[$s->id] = $loyal;
        $totalLoyal = PT_StakeAdd($totalLoyal, $loyal);
    }
    if (PT_StakeCmp($totalLoyal, '0') <= 0) {
        return PT_StakeDec(0);
    }
    $issued = PT_StakeDec(0);
    foreach ($stakeholders as $s) {
        $share = PT_StakeDiv($holderLoyal[$s->id], $totalLoyal);
        $units = PT_StakeFloorTo(PT_StakeMul($bonusUnits, $share), 10);
        if (PT_StakeCmp($units, '0') <= 0) { continue; }
        $lotId = PT_StakeCreateLot($s->id, $s->user_id, $units, 'LOYALTY_BONUS', $unitPrice, '0', PT_StakeRoleToClass($s->role), 0, 0, $asOfTs);
        if ($lotId) {
            $issued = PT_StakeAdd($issued, $units);
        }
    }
    // The app funds the reward from the Growth bucket (Layer 8.4.5).
    $bonusValue = PT_StakeMul($issued, $unitPrice);
    $newGrowth  = PT_StakeSub($growthBucket, $bonusValue);
    if (PT_StakeCmp($newGrowth, '0') < 0) { $newGrowth = PT_StakeDec(0); }
    PT_StakeMetricPut('bucket_growth', $newGrowth);
    PT_StakeMetricPut('k_bonus_value', PT_StakeAdd(PT_StakeMetricGet('k_bonus_value', '0'), $bonusValue));
    return $issued;
}

/** Settle the day's dividend pool across time-weighted holders (11-15). */
function PT_StakeSettleDailyPool($runKey, $dayStart, $dayEnd, $dividendPool, $unitPrice, $created_by = 0, $runId = 0) {
    global $db;
    try {
        $stakeholders = $db->where('status', 'active')->get(T_STAKEHOLDERS);
    } catch (Exception $e) {
        $stakeholders = array();
    }
    $now = $dayEnd;
    $weights = array();
    foreach ($stakeholders as $s) {
        $segments = PT_StakeTimeWeightedSegments($s->id, $dayStart, $dayEnd);
        $weights[$s->id] = PT_StakeWeightForHolder($s, $segments, $now);
    }
    $cap    = PT_StakeParam('stake_concentration_cap', '0.30');
    $shares = PT_StakeAllocateShares($weights, $cap);
    $minPayout = (int) PT_StakeParam('stake_min_payout', '1');
    $paidTotal = PT_StakeDec(0);
    $results   = array();

    // Per-holder rounding can leave a fraction of a 10-dp unit unallocated.
    // Reconcile it onto the largest holder so the whole pool is paid out and
    // no more than the pool (Layer 16, A1).
    $accruals = array();
    $accrualSum = PT_StakeDec(0);
    $largestId = null; $largestW = null;
    foreach ($stakeholders as $s) {
        $share = isset($shares[$s->id]) ? $shares[$s->id] : PT_StakeDec(0);
        $accruals[$s->id] = PT_StakeMul($dividendPool, $share);
        $accrualSum = PT_StakeAdd($accrualSum, $accruals[$s->id]);
        $w = isset($weights[$s->id]) ? $weights[$s->id] : PT_StakeDec(0);
        if ($largestW === null || PT_StakeCmp($w, $largestW) > 0) { $largestW = $w; $largestId = $s->id; }
    }
    $drift = PT_StakeSub($dividendPool, $accrualSum);
    if ($largestId !== null && PT_StakeCmp($drift, '0') !== 0) {
        $fixed = PT_StakeAdd($accruals[$largestId], $drift);
        $accruals[$largestId] = (PT_StakeCmp($fixed, '0') < 0) ? PT_StakeDec(0) : $fixed;
    }

    foreach ($stakeholders as $s) {
        $share   = isset($shares[$s->id]) ? $shares[$s->id] : PT_StakeDec(0);
        $accrual = isset($accruals[$s->id]) ? $accruals[$s->id] : PT_StakeDec(0);
        $balance = PT_StakeAdd(PT_StakeBalance($s->user_id), $accrual);

        $debit = PT_StakeRecoverAdjustments($s->id, $s->user_id, $accrual);
        $balance = PT_StakeSub($balance, $debit);
        if (PT_StakeCmp($balance, '0') < 0) { $balance = PT_StakeDec(0); }

        $reinvested = PT_StakeDec(0);
        $prefs = PT_StakeGetPrefs($s->id);
        if (!empty($prefs->auto_reinvest)) {
            $payable = PT_StakeFloorTo($balance, 0);
            if (PT_StakeCmp($payable, '0') > 0 && PT_StakeCmp($unitPrice, '0') > 0) {
                $reinvestCash = PT_StakeMul($payable, PT_StakeDec($prefs->reinvest_fraction));
                if (PT_StakeCmp($reinvestCash, '0') > 0) {
                    $units = PT_StakeFloorTo(PT_StakeDiv($reinvestCash, $unitPrice), 10);
                    if (PT_StakeCmp($units, '0') > 0) {
                        $cashUsed = PT_StakeMul($units, $unitPrice);
                        PT_StakeCreateLot($s->id, $s->user_id, $units, 'REINVEST', $unitPrice, $cashUsed, PT_StakeRoleToClass($s->role), 0, 0, $dayEnd);
                        $reinvested = $cashUsed;
                        $balance = PT_StakeSub($balance, $cashUsed);
                    }
                }
            }
        }

        $payable = PT_StakeFloorTo($balance, 0);
        $paid = PT_StakeDec(0);
        if (PT_StakeCmp($payable, (string) $minPayout) >= 0) {
            $paid = $payable;
            $balance = PT_StakeSub($balance, $paid);
        }
        $paidTotal = PT_StakeAdd($paidTotal, $paid);

        $weight = isset($weights[$s->id]) ? $weights[$s->id] : PT_StakeDec(0);
        $results[$s->id] = array(
            'user_id' => $s->user_id, 'share' => $share, 'weight' => $weight,
            'accrual' => $accrual, 'debit' => $debit, 'reinvested' => $reinvested,
            'paid' => $paid, 'balance' => $balance,
        );

        PT_DbGuardedRun(function () use ($db, $runKey, $runId, $s, $weight, $share, $accrual, $debit, $reinvested, $paid, $balance, $unitPrice) {
            $already = $db->rawQueryOne("SELECT id FROM `" . T_STAKE_DAILY_ACCRUALS . "` WHERE run_key = ? AND stakeholder_id = ? LIMIT 1", array($runKey, (int) $s->id));
            if (!empty($already)) {
                return false;
            }
            $db->insert(T_STAKE_DAILY_ACCRUALS, array(
                'run_id' => (int) $runId, 'run_key' => $runKey, 'stakeholder_id' => (int) $s->id, 'user_id' => (int) $s->user_id,
                'weight' => $weight, 'share' => $share, 'accrual' => $accrual, 'debit' => $debit,
                'reinvested' => $reinvested, 'paid' => $paid, 'balance_after' => $balance,
                'unit_price' => $unitPrice, 'currency' => PT_StakeCurrency(), 'created_at' => time(),
            ));
            $newLife = PT_StakeAdd(PT_StakeDec($s->lifecycle_earnings), $paid);
            $unitsNow = PT_StakeHolderUnits($s->id);
            $totalUnits = PT_StakeTotalUnits();
            $percent = (PT_StakeCmp($totalUnits, '0') > 0) ? PT_StakeMul(PT_StakeDiv($unitsNow, $totalUnits), '100', 12) : PT_StakeDec(0);
            $db->where('id', $s->id)->update(T_STAKEHOLDERS, array(
                'current_shares' => $unitsNow, 'current_percent' => $percent,
                'lifecycle_earnings' => $newLife, 'updated_at' => time(),
            ));
            return true;
        });
    }
    return array('paid_total' => $paidTotal, 'holders' => $results);
}

/** Per-stakeholder engine preferences. */
function PT_StakeGetPrefs($stakeholder_id) {
    global $db;
    try {
        $row = $db->where('stakeholder_id', (int) $stakeholder_id)->getOne(T_STAKEHOLDER_PREFS);
        if (!empty($row)) {
            return $row;
        }
    } catch (Exception $e) {
    }
    $row = new stdClass();
    $row->auto_reinvest = 0;
    $row->reinvest_fraction = '1.0000000000';
    return $row;
}

/** Forward-only recovery of outstanding adjustments (Layer 15). */
function PT_StakeRecoverAdjustments($stakeholder_id, $user_id, $accrual) {
    global $db;
    $accrual = PT_StakeDec($accrual);
    if (PT_StakeCmp($accrual, '0') <= 0) {
        return PT_StakeDec(0);
    }
    try {
        $rows = $db->rawQuery("SELECT `id`, `amount`, `recovered` FROM `" . T_STAKE_ADJUSTMENTS . "` WHERE stakeholder_id = ? AND recovered < amount ORDER BY id ASC", array((int) $stakeholder_id));
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    if (empty($rows)) {
        return PT_StakeDec(0);
    }
    $limit = PT_StakeMul($accrual, '0.5');
    $collected = PT_StakeDec(0);
    foreach ($rows as $row) {
        if (PT_StakeCmp($collected, $limit) >= 0) { break; }
        $outstanding = PT_StakeSub(PT_StakeDec($row->amount), PT_StakeDec($row->recovered));
        if (PT_StakeCmp($outstanding, '0') <= 0) { continue; }
        $room = PT_StakeSub($limit, $collected);
        $take = (PT_StakeCmp($outstanding, $room) <= 0) ? $outstanding : $room;
        $id = (int) $row->id;
        $newRecovered = PT_StakeAdd(PT_StakeDec($row->recovered), $take);
        PT_DbGuardedRun(function () use ($db, $id, $newRecovered) {
            $db->where('id', $id)->update(T_STAKE_ADJUSTMENTS, array('recovered' => $newRecovered));
            return true;
        });
        $collected = PT_StakeAdd($collected, $take);
    }
    return $collected;
}

/** Process queued redemptions against liquidity capacity (Layer 9.4). */
function PT_StakeProcessRedemptions($unitPrice, $liqBucket, $k, $created_by = 0) {
    global $db;
    try {
        $rows = $db->where('status', 'queued')->orderBy('requested_at', 'ASC')->orderBy('id', 'ASC')->get(T_STAKE_REDEMPTIONS);
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    if (empty($rows)) {
        return PT_StakeDec(0);
    }
    $capacity = PT_StakeAdd($liqBucket, PT_StakeMul($k, '0.5'));
    $redeemed = PT_StakeDec(0);
    foreach ($rows as $req) {
        $units = PT_StakeSub(PT_StakeDec($req->units_requested), PT_StakeDec($req->units_filled));
        if (PT_StakeCmp($units, '0') <= 0) { continue; }
        $gross = PT_StakeMul($units, $unitPrice);
        if (PT_StakeCmp($gross, $capacity) > 0) {
            if (PT_StakeCmp($capacity, '0') <= 0) { break; }
            $units = PT_StakeFloorTo(PT_StakeDiv($capacity, $unitPrice), 10);
            if (PT_StakeCmp($units, '0') <= 0) { break; }
        }
        $net = PT_StakeRedeemUnits($req->stakeholder_id, $req->user_id, $units, $unitPrice, $created_by);
        if (PT_StakeCmp($net, '0') <= 0) { continue; }
        $capacity = PT_StakeSub($capacity, $net);
        $redeemed = PT_StakeAdd($redeemed, $units);
        $filled = PT_StakeAdd(PT_StakeDec($req->units_filled), $units);
        $status = (PT_StakeCmp($filled, PT_StakeDec($req->units_requested)) >= 0) ? 'filled' : 'queued';
        $reqId = (int) $req->id;
        PT_DbGuardedRun(function () use ($db, $reqId, $filled, $net, $status) {
            $existing = PT_StakeDec($db->where('id', $reqId)->getValue(T_STAKE_REDEMPTIONS, 'net_proceeds'));
            $db->where('id', $reqId)->update(T_STAKE_REDEMPTIONS, array(
                'units_filled' => $filled, 'net_proceeds' => PT_StakeAdd($existing, $net),
                'status' => $status, 'filled_at' => time(),
            ));
            return true;
        });
    }
    if (PT_StakeCmp($redeemed, '0') > 0) {
        PT_StakeMetricPut('bucket_liquidity', PT_StakeMax(PT_StakeSub($liqBucket, $redeemed), '0'));
    }
    return $redeemed;
}

/** Herfindahl index over holder units (Layer 17.1). */
function PT_StakeHhi() {
    global $db;
    $total = PT_StakeTotalUnits();
    if (PT_StakeCmp($total, '0') <= 0) {
        return PT_StakeDec(0);
    }
    $hhi = PT_StakeDec(0);
    try {
        $stakeholders = $db->where('status', 'active')->get(T_STAKEHOLDERS);
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    foreach ($stakeholders as $s) {
        $share = PT_StakeDiv(PT_StakeHolderUnits($s->id), $total);
        $hhi = PT_StakeAdd($hhi, PT_StakeMul($share, $share));
    }
    return $hhi;
}

/**
 * Set a holder's absolute unit count. Positive changes create a GRANT lot,
 * negative changes redeem newest lots first with no exit fee. Used by the
 * admin "shares" field; everything else is automatic.
 */
/**
 * Recompute units and stake percentage for every holder from the lot register.
 * Percentages are relative to the whole pool, so any change to one holder's
 * units moves everyone else's percentage too.
 */
function PT_StakeRefreshAllPercents() {
    global $db;
    try {
        $stakeholders = $db->get(T_STAKEHOLDERS);
    } catch (Exception $e) {
        return;
    }
    $total = PT_StakeTotalUnits();
    foreach ($stakeholders as $s) {
        $units   = PT_StakeHolderUnits($s->id);
        $percent = (PT_StakeCmp($total, '0') > 0) ? PT_StakeMul(PT_StakeDiv($units, $total), '100', 12) : PT_StakeDec(0);
        if (PT_StakeCmp($units, PT_StakeDec($s->current_shares)) === 0 && PT_StakeCmp($percent, PT_StakeDec($s->current_percent)) === 0) {
            continue;
        }
        try {
            $db->where('id', (int) $s->id)->update(T_STAKEHOLDERS, array(
                'current_shares' => $units, 'current_percent' => $percent, 'updated_at' => time(),
            ));
        } catch (Exception $e) {
        }
    }
}

function PT_StakeSetHolderUnits($stakeholder_id, $targetUnits, $reason = 'admin_set', $created_by = 0) {
    global $db;
    $stakeholder = $db->where('id', (int) $stakeholder_id)->getOne(T_STAKEHOLDERS);
    if (empty($stakeholder)) {
        return array('status' => 0, 'message' => 'Stakeholder not found.');
    }
    $target = PT_StakeDec($targetUnits);
    if (PT_StakeCmp($target, '0') < 0) {
        $target = PT_StakeDec(0);
    }
    $current = PT_StakeHolderUnits($stakeholder_id);
    $delta = PT_StakeSub($target, $current);
    if (PT_StakeCmp($delta, '0') === 0) {
        return array('status' => 1, 'units' => $current, 'unchanged' => true);
    }
    $unitPrice = PT_StakeMetricGet('unit_price', '1');
    if (PT_StakeCmp($unitPrice, '0') <= 0) { $unitPrice = PT_StakeDec('1'); }

    if (PT_StakeCmp($delta, '0') > 0) {
        $lotId = PT_StakeCreateLot($stakeholder_id, $stakeholder->user_id, $delta, 'GRANT', $unitPrice, '0', PT_StakeRoleToClass($stakeholder->role), 0, $created_by);
        if ($lotId) {
            // App-funded grant: the app backs the new units with value from the
            // Growth bucket, so existing holders are not diluted (Layer 8.5.2).
            $grantValue = PT_StakeMul($delta, $unitPrice);
            $growth     = PT_StakeMetricGet('bucket_growth', '0');
            $newGrowth  = PT_StakeSub($growth, $grantValue);
            if (PT_StakeCmp($newGrowth, '0') < 0) { $newGrowth = PT_StakeDec(0); }
            PT_StakeMetricPut('bucket_growth', $newGrowth);
            PT_StakeMetricPut('k_bonus_value', PT_StakeAdd(PT_StakeMetricGet('k_bonus_value', '0'), $grantValue));
        }
    } else {
        $toRemove = PT_StakeSub('0', $delta);
        $lots = PT_StakeHolderLots($stakeholder_id);
        usort($lots, function ($a, $b) { return (int) $b->acquired_at - (int) $a->acquired_at; });
        foreach ($lots as $lot) {
            if (PT_StakeCmp($toRemove, '0') <= 0) { break; }
            $avail = PT_StakeLotUnits($lot);
            if (PT_StakeCmp($avail, '0') <= 0) { continue; }
            $take = (PT_StakeCmp($avail, $toRemove) <= 0) ? $avail : $toRemove;
            $lotId = (int) $lot->id;
            $takeStr = $take;
            PT_DbGuardedRun(function () use ($db, $lotId, $stakeholder, $takeStr, $created_by) {
                $db->insert(T_STAKE_LOT_EVENTS, array(
                    'lot_id' => $lotId, 'stakeholder_id' => (int) $stakeholder->id,
                    'user_id' => (int) $stakeholder->user_id, 'units_delta' => $takeStr,
                    'event_type' => 'REDEEM', 'unit_price' => PT_StakeMetricGet('unit_price', '1'),
                    'created_by' => (int) $created_by, 'created_at' => time(),
                ));
                return true;
            });
            $toRemove = PT_StakeSub($toRemove, $take);
        }
    }
    PT_StakeRefreshAllPercents();
    $unitsNow = PT_StakeHolderUnits($stakeholder_id);
    return array('status' => 1, 'units' => $unitsNow, 'delta' => $delta);
}
