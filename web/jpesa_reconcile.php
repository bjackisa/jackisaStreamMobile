<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once './assets/init.php';
decryptConfigData();
require_once './assets/includes/jpesa_config.php';
require_once './assets/includes/jpesa_reconciliation.php';

if ($pt->config->jpesa_payment != 'yes') {
    fwrite(STDERR, "JPesa is disabled in the database configuration.\n");
    exit(1);
}

$summary = PT_JPesaReconcilePending();

echo 'JPesa reconciliation: ' . json_encode($summary) . PHP_EOL;