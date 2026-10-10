<?php
/**
 * Stakeholder dividend engine cron.
 * Run twice a day, for example: 0 1,13 * * * php /path/to/stake_cron.php
 *
 * This is deliberately separate from cronjob.php because that one runs every
 * few minutes and the stake engine only settles one day at a time.
 *
 * The engine is idempotent: it writes at most one stake_daily_runs row per
 * UTC day, so running this twice a day (or twice in a row) is safe and the
 * second run for the same day is a no-op.
 *
 * Optional argument: a UTC day to settle, as YYYY-MM-DD, for a manual
 * backfill. With no argument it settles yesterday.
 */
require_once __DIR__ . '/assets/init.php';
decryptConfigData();

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This cron may only be run from the command line.\n");
}

if (!function_exists('PT_StakeRunDailyEngine')) {
    fwrite(STDERR, "Stake engine is not available (assets/includes/stakeholders.php missing).\n");
    exit(1);
}

$dayTs = null;
if (!empty($argv[1])) {
    $parsed = strtotime($argv[1] . ' 12:00:00 UTC');
    if ($parsed === false) {
        fwrite(STDERR, "Invalid day '{$argv[1]}'. Use YYYY-MM-DD.\n");
        exit(1);
    }
    $dayTs = $parsed;
}

$result = PT_StakeRunDailyEngine($dayTs);

if (empty($result['status'])) {
    fwrite(STDERR, 'Stake engine did not run: ' . (isset($result['message']) ? $result['message'] : 'unknown error') . PHP_EOL);
    exit(1);
}

if (!empty($result['already_run'])) {
    echo 'Stake engine: ' . $result['run_key'] . ' already settled.' . PHP_EOL;
} else {
    echo 'Stake engine settled ' . $result['run_key'] . ': ' . json_encode($result) . PHP_EOL;
}

// Then catch up every earlier unsettled day. This distributes revenue from
// before the engine existed the first time it runs, and keeps up if the cron
// missed days. Idempotent: days already settled are skipped.
$catchup = PT_StakeCatchUpDailyEngine();
echo 'Stake engine catch-up: ' . $catchup['processed'] . ' day(s) settled across ' . $catchup['total_days'] . ' day(s).' . PHP_EOL;

// Then repair any already-settled day whose recognised revenue has since grown
// (wallet top-ups or Store sales recorded after that day was settled). Without
// this a settled day keeps a stale dividend pool for ever. Idempotent.
if (function_exists('PT_StakeReconcileSettledDays')) {
    $repair = PT_StakeReconcileSettledDays(1000);
    echo 'Stake engine repair: ' . $repair['fixed'] . ' of ' . $repair['checked']
        . ' settled day(s) topped up for ' . $repair['added_net'] . ' additional net revenue.'
        . PHP_EOL;
}
exit(0);
