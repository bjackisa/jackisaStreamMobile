<?php
/**
 * Stakeholder full-history repair.
 *
 * stake_cron.php settles the previous UTC day and fills days that have no run
 * row. It cannot fix a day that was already settled with stale (usually zero)
 * revenue - for example wallet top-ups or Store sales that were recorded before
 * or after that day's settlement. Because the engine is idempotent per day, such
 * a day keeps its old net_revenue and a Shs 0.00 dividend pool for ever.
 *
 * Run this script to walk every closed day from the earliest recognised revenue
 * to yesterday, top up each day whose revenue has since grown, and distribute
 * the shortfall's dividend. It also runs the normal catch-up first so any day
 * that was never settled at all is settled too.
 *
 *   php stake_backfill.php             # repair every day, from the beginning
 *   php stake_backfill.php 2026-09-01  # repair days on/after 2026-09-01
 *   php stake_backfill.php --dry-run   # report only, write nothing
 *
 * Safe to run repeatedly: a second run finds every day already up to date and
 * changes nothing. It never reclaims money already distributed.
 */
require_once __DIR__ . '/assets/init.php';
decryptConfigData();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script may only be run from the command line.\n");
}

if (!function_exists('PT_StakeReconcileSettledDays')) {
    fwrite(STDERR, "Stake engine is not available (assets/includes/stakeholders.php missing).\n");
    exit(1);
}

$fromDay = null;
$dryRun  = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run' || $arg === '-n') {
        $dryRun = true;
        continue;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
        $fromDay = $arg;
        continue;
    }
    fwrite(STDERR, "Unknown argument '{$arg}'. Use an optional YYYY-MM-DD and/or --dry-run.\n");
    exit(1);
}

// First make sure nothing is missing at all (days with no run row yet).
$catchup = PT_StakeCatchUpDailyEngine(1500, 0, $dryRun);
$catchVerb = $dryRun ? 'would fill' : 'filled';
echo ucfirst($catchVerb) . ' ' . $catchup['processed'] . ' previously unsettled day(s) (checked up to '
    . $catchup['total_days'] . ' across the calendar).' . PHP_EOL;

// Then repair the days that were settled with stale revenue.
$fromTs  = ($fromDay !== null) ? strtotime($fromDay . ' 00:00:00 UTC') : null;
$summary = PT_StakeReconcileSettledDays(0, 0, $fromTs, $dryRun);

if ($dryRun) {
    echo '[dry-run] ' . $summary['checked'] . " settled day(s) inspected; "
        . $summary['fixed'] . " would be topped up.\n";
} else {
    echo $summary['checked'] . ' settled day(s) inspected; '
        . $summary['fixed'] . ' topped up for ' . $summary['added_net']
        . ' additional net revenue / ' . $summary['added_pool'] . " dividend pool.\n";
}

if (!empty($summary['days'])) {
    echo 'Corrected day(s): ' . implode(', ', $summary['days']) . PHP_EOL;
}

exit(0);
