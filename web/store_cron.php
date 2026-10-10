<?php
/**
 * Jackisa Store continuous cron.
 * Run every few minutes, for example: * /5 * * * * php /path/to/store_cron.php
 *
 * It does three idempotent things:
 *   1. reconciles pending JPesa store payments (same verified-response flow
 *      as the wallet)
 *   2. releases seller escrow into the withdrawable balance once the return
 *      window (7 days by default, per category) has elapsed
 *   3. closes paid orders whose escrow has been fully released
 *
 * The released balance lands in users.balance, which is exactly the balance
 * the existing withdrawal request system already pays out from.
 */
require_once __DIR__ . '/assets/init.php';
decryptConfigData();
require_once __DIR__ . '/assets/includes/jpesa_config.php';
require_once __DIR__ . '/assets/includes/jpesa_reconciliation.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This cron may only be run from the command line.\n");
}

if (!function_exists('PT_StoreRunCron')) {
    fwrite(STDERR, "Store engine is not available (assets/includes/store.php missing).\n");
    exit(1);
}

$summary = PT_StoreRunCron();
echo 'Store cron: ' . json_encode($summary) . PHP_EOL;
exit(0);
