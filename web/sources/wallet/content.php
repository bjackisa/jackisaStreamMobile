<?php
if (IS_LOGGED == false) {
    header("Location: " . PT_Link('login'));
    exit();
}
$currency         = !empty($pt->config->currency_symbol_array[$pt->config->payment_currency]) ? $pt->config->currency_symbol_array[$pt->config->payment_currency] : '$';
$countries = '';
foreach ($countries_name as $key => $value) {
    $selected = ($key == $pt->user->country_id) ? 'selected' : '';
    $countries .= '<option value="' . $key . '" ' . $selected . '>' . $value . '</option>';
}

$pt->page        = 'wallet';
$pt->description = $pt->config->description;
$pt->keyword     = $pt->config->keyword;
$pt->page_url_ = $pt->config->site_url.'/wallet';
$pt->title = $pt->config->name . ' | ' . $pt->config->title;

$dividend_block = '';
if (function_exists('PT_StakeDividendSummary') && PT_UserIsStakeholder($pt->user->id)) {
    $dividend = PT_StakeDividendSummary($pt->user->id);
    if (!empty($dividend)) {
        $percent       = PT_StakePercent($dividend['percent'], 3);
        $dividend_line = str_replace(
            array('{percent}', '{sitename}'),
            array($percent . '%', $pt->config->name),
            $lang_array['your_dividend_share']
        );
        $dividend_block = '<div class="tag_wallet_balance my-5">'
            . '<div class="h6">' . $lang_array['dividends'] . '</div>'
            . '<div class="h1 mb-0"><sup>' . $currency . '</sup>' . PT_StakeMoney($dividend['available']) . '</div>'
            . '<small class="d-block form-text text-muted mb-4 mb-md-5">' . $dividend_line . '</small>'
            . '</div>';
    }
}

$pt->content  = PT_LoadPage('wallet/content',array(
	'CURRENCY'   => $currency,
	'DIVIDEND_BLOCK' => $dividend_block,
    'COUNTRIES' => $countries));