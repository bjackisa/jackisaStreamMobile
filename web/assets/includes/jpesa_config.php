<?php

$jpesa_api_url = 'https://my.jpesa.com/api/';
$jpesa_api_key = $jpesa_api_key ?? '';
$jpesa_currency = $jpesa_currency ?? 'UGX';

// Keep completion fast while bounding status lookups for abandoned payment prompts.
$jpesa_pending_expiry_seconds = $jpesa_pending_expiry_seconds ?? 3600;
$jpesa_reconciliation_base_delay = $jpesa_reconciliation_base_delay ?? 60;
$jpesa_reconciliation_max_delay = $jpesa_reconciliation_max_delay ?? 900;
$jpesa_reconciliation_max_attempts = $jpesa_reconciliation_max_attempts ?? 7;