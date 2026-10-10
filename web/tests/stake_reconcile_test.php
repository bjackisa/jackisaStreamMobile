<?php
// Verify PT_StakeReconcileSettledDays repairs a settled day whose revenue grew
// after settlement, is idempotent, and reconciles the dividend pool to the
// accrual ledger (sum(accrual) - sum(debit) == pool).
require_once __DIR__ . '/../assets/init.php';
decryptConfigData();
global $db, $pt;
$pt->config->smtp_or_mail = 'none';

$today = strtotime('today UTC');
$d = function ($off) use ($today) { return $today - $off * 86400; };

// Reset the state we own. stake_revenue_entries is intentionally never touched:
// its DB trigger blocks all deletes, so this test avoids that source entirely.
foreach (array('stake_daily_accruals','stake_daily_runs','stake_earnings','stake_adjustments',
               'stakeholder_share_events','stakeholder_metric_values','stakeholder_periods',
               'stake_withdrawals','withdrawal_requests',
               'stake_lot_events','stake_lots','stake_redemptions','stakeholders',
               'store_ledger_entries','jpesa_payments') as $t) {
    PT_DbGuardedRun(function () use ($db, $t) { $db->rawQuery("DELETE FROM `$t`"); return true; });
}

$uid = (int) $db->getValue('users', 'id');
$db->insert('stakeholders', array('user_id'=>$uid,'role'=>'founder','status'=>'active','current_shares'=>'1000.0000000000','created_at'=>$d(40),'updated_at'=>$d(40)));
$sid = (int) $db->getValue('stakeholders', 'id');
$db->insert('stake_lots', array('stakeholder_id'=>$sid,'user_id'=>$uid,'units'=>'1000.0000000000','channel'=>'SEED','lot_class'=>'Founder','acquired_at'=>$d(40),'created_at'=>$d(40)));

// Two days settle first, while their wallet revenue does not exist yet.
PT_StakeRunDailyEngine($d(3) + 43200);
PT_StakeRunDailyEngine($d(2) + 43200);
$key3 = gmdate('Y-m-d', $d(3));
$key2 = gmdate('Y-m-d', $d(2));

$fail = 0;
// The local test DB may carry unrelated manual revenue entries (their DB
// trigger blocks deletion), so assert on deltas, not absolute totals.
$base3 = PT_StakeDec($db->where('run_key', $key3)->getValue('stake_daily_runs', 'net_revenue'));
$base2 = PT_StakeDec($db->where('run_key', $key2)->getValue('stake_daily_runs', 'net_revenue'));

// Wallet top-ups land afterwards for those same days.
foreach (array(3, 2) as $off) {
    $ts = $d($off) + 43200;
    $db->insert('jpesa_payments', array(
        'user_id'=>$uid,'transaction_id'=>'T'.$off,'merchant_transaction_id'=>'M'.$off,
        'amount'=>'10000.0000','charged_amount'=>'10309.2800','currency'=>'UGX',
        'purchase_type'=>'wallet','status'=>'paid','created_at'=>$ts,'completed_at'=>$ts,
    ));
}

// Dry run reports but writes nothing.
$before = $db->where('run_key', $key2)->getValue('stake_daily_runs', 'net_revenue');
$dry = PT_StakeReconcileSettledDays(0, 0, null, true);
$afterDry = $db->where('run_key', $key2)->getValue('stake_daily_runs', 'net_revenue');
if ($dry['fixed'] !== 2 || $before !== $afterDry) { echo "DRY-RUN FAIL\n"; $fail++; }
echo "dry-run fixed=" . $dry['fixed'] . " added_net=" . $dry['added_net'] . " unchanged=" . var_export($before === $afterDry, true) . "\n";

// Real repair distributes the shortfall.
$rep = PT_StakeReconcileSettledDays();
$ratio = '0.6';                // config stake_distributable_ratio
$feeEach = '309.2800000000';   // 10309.28 - 10000.00
echo "repair fixed=" . $rep['fixed'] . " added_net=" . $rep['added_net'] . " added_pool=" . $rep['added_pool'] . "\n";
if ($rep['fixed'] !== 2) { echo "FIXED COUNT FAIL\n"; $fail++; }
if (bccomp($rep['added_net'], bcadd($feeEach, $feeEach, 10), 9) !== 0) { echo "ADDED_NET FAIL\n"; $fail++; }

$expectAddedPool = '0';
foreach (array(array($key3, $base3), array($key2, $base2)) as $pair) {
    list($key, $base) = $pair;
    $run = $db->where('run_key', $key)->getOne('stake_daily_runs');
    $acc = $db->rawQueryOne("SELECT COALESCE(SUM(accrual),0) a, COALESCE(SUM(debit),0) b, COALESCE(SUM(paid),0) p FROM stake_daily_accruals WHERE run_key = ? OR run_key LIKE ?", array($key, $key.'~%'));
    $ledger = bcsub($acc->a, $acc->b, 10);
    // Reserve rate is whatever the day was settled with (volatility-driven).
    $keep      = bcmul($ratio, bcsub('1', $run->reserve_rate, 10), 10);
    $poolEach  = bcmul($feeEach, $keep, 10);
    $expectAddedPool = bcadd($expectAddedPool, $poolEach, 10);
    $deltaOk   = (bccomp(bcsub($run->net_revenue, $base, 10), $feeEach, 9) === 0);
    $deltaPool = bccomp(bcsub($run->dividend_pool, bcmul($base, $keep, 10), 10), $poolEach, 9) === 0;
    $ok = $deltaOk && $deltaPool
        && (bccomp($run->dividend_pool, $ledger, 9) === 0)
        && (bccomp($run->paid_total, $acc->p, 9) === 0);
    echo "$key net=" . $run->net_revenue . " (base $base, delta ok=" . var_export($deltaOk, true) . ")"
        . " pool=" . $run->dividend_pool . " ledger=" . $ledger
        . " paid=" . $run->paid_total . " accrual_paid=" . $acc->p . ($ok ? " OK" : " MISMATCH") . "\n";
    if (!$ok) { $fail++; }
}
if (bccomp($rep['added_pool'], $expectAddedPool, 9) !== 0) { echo "ADDED_POOL FAIL (" . $rep['added_pool'] . " != " . $expectAddedPool . ")\n"; $fail++; }

// Second repair is a no-op (idempotent).
$rep2 = PT_StakeReconcileSettledDays();
echo "repair again fixed=" . $rep2['fixed'] . " (expected 0)\n";
if ($rep2['fixed'] !== 0) { $fail++; }

echo $fail === 0 ? "ALL CHECKS PASSED\n" : "FAILURES: $fail\n";
exit($fail === 0 ? 0 : 1);
