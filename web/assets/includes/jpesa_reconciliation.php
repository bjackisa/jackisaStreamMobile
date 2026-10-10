<?php

function PT_JPesaLog($message)
{
    file_put_contents(__DIR__ . '/../logs/jpesa_reconciliation.log', date('c') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function PT_JPesaRetryDelay($attempts)
{
    global $jpesa_reconciliation_base_delay, $jpesa_reconciliation_max_delay;

    $attempts = max(0, (int) $attempts);
    return min($jpesa_reconciliation_max_delay, $jpesa_reconciliation_base_delay * (1 << min($attempts, 20)));
}

function PT_JPesaExpirePayment($payment, $now = null)
{
    global $db, $jpesa_pending_expiry_seconds, $jpesa_reconciliation_max_attempts;

    $now = $now ?: time();
    $created_at = (int) $payment->created_at;
    $attempts = (int) ($payment->reconciliation_attempts ?? 0);
    if (($created_at > 0 && $created_at + $jpesa_pending_expiry_seconds <= $now) || $attempts >= $jpesa_reconciliation_max_attempts) {
        $db->where('id', $payment->id)->where('status', 'pending')->update(T_JPESA_PAYMENTS, array(
            'status' => 'failed',
            'provider_status' => 'expired',
            'provider_message' => 'Payment was not completed before the reconciliation deadline.',
            'completed_at' => $now
        ));
        return true;
    }
    return false;
}

function PT_JPesaClaimReconciliation($payment, $now = null)
{
    global $db, $jpesa_reconciliation_max_attempts;

    $now = $now ?: time();
    $attempts = (int) ($payment->reconciliation_attempts ?? 0);
    $last_checked_at = (int) ($payment->last_checked_at ?? 0);
    $retry_after = PT_JPesaRetryDelay($attempts);
    if ($last_checked_at > 0 && $last_checked_at + $retry_after > $now) {
        return false;
    }

    // This conditional update is the cross-request lock: cron, a customer check, and
    // repeated callbacks cannot all spend a provider request for the same payment.
    return (bool) $db->where('id', $payment->id)
        ->where('status', 'pending')
        ->where('reconciliation_attempts', $jpesa_reconciliation_max_attempts, '<')
        ->where('last_checked_at', $now - $retry_after, '<=')
        ->update(T_JPESA_PAYMENTS, array('last_checked_at' => $now, 'reconciliation_attempts' => $db->inc(1)));
}

function PT_JPesaQueryTransaction($transaction_id)
{
    global $jpesa_api_url, $jpesa_api_key;

    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $transaction_id)) {
        PT_JPesaLog('tid=' . $transaction_id . ' rejected: invalid transaction id format');
        return array();
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?><g7bill><_key_>' . htmlspecialchars($jpesa_api_key, ENT_XML1) . '</_key_><cmd>account</cmd><action>info</action><tid>' . htmlspecialchars($transaction_id, ENT_XML1) . '</tid></g7bill>';
    $ch = curl_init($jpesa_api_url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => array('Content-Type: text/xml'),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    if (!empty($curl_error)) {
        PT_JPesaLog('tid=' . $transaction_id . ' curl_error=' . $curl_error);
    }
    return is_array($result) ? $result : array();
}

function PT_JPesaReconcilePayment($payment, $callback_status = '')
{
    global $db;

    if (empty($payment)) {
        return 'missing';
    }
    if (PT_JPesaExpirePayment($payment)) {
        return 'failed';
    }
    if (!PT_JPesaClaimReconciliation($payment)) {
        return 'throttled';
    }

    // Callback status is intentionally not trusted. A callback only accelerates one
    // provider verification; all balance changes require JPesa's verified response.
    $result = PT_JPesaQueryTransaction($payment->transaction_id);
    $api_status = strtolower(trim((string) ($result['api_status'] ?? $result['APIStatus'] ?? $result['apiStatus'] ?? '')));
    $provider_status = strtolower(trim((string) ($result['status'] ?? $result['transaction_status'] ?? '')));
    $audit = array('provider_status' => $provider_status, 'provider_message' => substr((string) ($result['msg'] ?? $result['message'] ?? ''), 0, 500));
    $db->where('id', $payment->id)->update(T_JPESA_PAYMENTS, $audit);
    $success_statuses = array('closed', 'approved', 'paid', 'success', 'successful', 'complete', 'completed');
    $failed_statuses = array('failed', 'failure', 'cancelled', 'canceled', 'rejected', 'declined', 'expired', 'error');

    if (in_array($provider_status, $failed_statuses, true)) {
        $db->where('id', $payment->id)->where('status', 'pending')->update(T_JPESA_PAYMENTS, array('status' => 'failed', 'completed_at' => time()));
        return 'failed';
    }
    if ($api_status != 'success' || !in_array($provider_status, $success_statuses, true)) {
        PT_JPesaLog('tid=' . $payment->transaction_id . ' result=pending api_status=' . $api_status . ' provider_status=' . $provider_status . ' callback_status=' . $callback_status . ' message=' . ($result['msg'] ?? $result['message'] ?? ''));
        return 'pending';
    }
    $provider_amount = $result['amount'] ?? $result['paid_amount'] ?? $result['total'] ?? 0;
    $provider_currency = $result['cur'] ?? $result['currency'] ?? '';
    $expected_provider_amount = !empty($payment->charged_amount) ? $payment->charged_amount : $payment->amount;
    if (round((float) $provider_amount, 2) != round((float) $expected_provider_amount, 2) || strtoupper((string) $provider_currency) != strtoupper((string) $payment->currency)) {
        PT_JPesaLog('tid=' . $payment->transaction_id . ' result=mismatch expected=' . $expected_provider_amount . ' ' . $payment->currency . ' received=' . $provider_amount . ' ' . $provider_currency);
        return 'pending';
    }

    $updated = $db->where('id', $payment->id)->where('status', 'pending')->update(T_JPESA_PAYMENTS, array('status' => 'paid', 'completed_at' => time()));
    if ($updated) {
        // A Store purchase is settled against its order through the Store
        // double-entry ledger instead of a plain wallet top-up.
        if ((isset($payment->purchase_type) && $payment->purchase_type === 'store') && !empty($payment->order_id) && function_exists('PT_StoreSettlePaidOrder')) {
            $stored = PT_StoreSettlePaidOrder((int) $payment->order_id, $payment->amount, 'jpesa', $payment->transaction_id);
            $db->where('id', $payment->id)->update(T_JPESA_PAYMENTS, array('fulfilled' => $stored ? 1 : 0));
            PT_JPesaLog('tid=' . $payment->transaction_id . ' result=paid store_order=' . $payment->order_id . ' fulfilled=' . ($stored ? 1 : 0));
            return 'paid';
        }
        $db->where('id', $payment->user_id)->update(T_USERS, array('wallet' => $db->inc($payment->amount)));
        $db->insert(T_VIDEOS_TRSNS, array('user_id' => $payment->user_id, 'paid_id' => $payment->user_id, 'admin_com' => 0, 'currency' => $payment->currency, 'time' => time(), 'amount' => $payment->amount, 'type' => 'ad'));
        $db->where('id', $payment->id)->update(T_JPESA_PAYMENTS, array('fulfilled' => 1));
    }
    PT_JPesaLog('tid=' . $payment->transaction_id . ' result=paid amount=' . $payment->amount . ' currency=' . $payment->currency);
    return 'paid';
}

function PT_JPesaReconcilePending()
{
    global $db;

    $payments = $db->where('status', 'pending')->get(T_JPESA_PAYMENTS);
    $summary = array('checked' => 0, 'paid' => 0, 'failed' => 0, 'pending' => 0, 'throttled' => 0);
    foreach ($payments as $payment) {
        $result = PT_JPesaReconcilePayment($payment);
        if ($result != 'throttled') {
            $summary['checked']++;
        }
        if (isset($summary[$result])) {
            $summary[$result]++;
        }
    }
    PT_JPesaLog('cron ' . json_encode($summary));
    return $summary;
}