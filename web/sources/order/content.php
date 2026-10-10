<?php
// Jackisa Store: an order / booking detail with its documents and ledger.
if (!function_exists('PT_StoreEnabled') || !PT_StoreEnabled()) {
    header("Location: " . PT_Link('404'));
    exit();
}
if (!IS_LOGGED) {
    header("Location: " . PT_Link('login?red=' . urlencode(PT_Link('store'))));
    exit();
}

$order = null;
if (!empty($_GET['id'])) {
    $order = $db->where('order_number', PT_Secure($_GET['id']))->getOne(T_STORE_ORDERS);
}
if (empty($order) || ((int) $order->buyer_id !== (int) $pt->user->id && (int) $order->seller_user_id !== (int) $pt->user->id && !PT_IsAdmin())) {
    header("Location: " . PT_Link('404'));
    exit();
}

$currency  = PT_StoreCurrencySymbol();
$is_seller = (int) $order->seller_user_id === (int) $pt->user->id;
$is_buyer  = (int) $order->buyer_id === (int) $pt->user->id;
$payable   = !in_array($order->status, array('paid', 'closed', 'cancelled', 'refunded'), true);
$jpesa_fee = PT_StoreJPesaFeeSplit($order->total);
// The JPesa service fee only applies to a buyer paying their own order; the
// summary then totals what is actually charged (order total + fee).
$pay_fee_applies = ($is_buyer && $payable && PT_StakeCmp($jpesa_fee['fee'], '0') > 0);
$display_total   = $pay_fee_applies ? $jpesa_fee['charged'] : PT_StakeDec($order->total);

$items_html = '';
foreach (PT_StoreOrderItems($order->id) as $item) {
    $items_html .= '<tr><td>' . htmlspecialchars($item->title) . ($item->variant_label ? ' <span class="text-muted">(' . htmlspecialchars($item->variant_label) . ')</span>' : '')
        . '</td><td>' . (int) $item->qty . '</td><td>' . $currency . ' ' . PT_StoreMoney($item->line_total) . '</td>'
        . '<td>' . $currency . ' ' . PT_StoreMoney($item->delivery_fee) . '</td>'
        . '<td class="text-muted">' . number_format($item->commission_bps / 100, 0) . '%</td></tr>';
}

$documents_html = '';
foreach (PT_StoreOrderDocuments($order->id) as $doc) {
    $documents_html .= '<tr><td><span class="store_status">' . str_replace('_', ' ', $doc->doc_type) . '</span></td><td>' . htmlspecialchars($doc->number) . '</td>'
        . '<td>' . $currency . ' ' . PT_StoreMoney($doc->total) . '</td><td>' . htmlspecialchars($doc->status) . '</td>'
        . '<td>' . gmdate('d M Y', $doc->created_at) . '</td>'
        // The main-session hash must ride along, otherwise ajax.php answers
        // with a bare bad-request before the Store handler ever runs.
        . '<td class="text-right"><a class="btn btn-sm btn-light" href="' . htmlspecialchars(PT_Link('aj/store/document?id=' . (int) $doc->id . '&hash=' . PT_CreateMainSession())) . '" target="_blank">View</a> '
        . '<a class="btn btn-sm btn-main-outline" href="' . htmlspecialchars(PT_Link('aj/store/document?id=' . (int) $doc->id . '&download=1&hash=' . PT_CreateMainSession())) . '">Download</a></td></tr>';
}

$bookings_html = '';
if ($order->kind === 'booking') {
    foreach (PT_StoreBookingsForOrder($order->id) as $booking) {
        $bookings_html .= '<div class="content p-3 mb-2">' . gmdate('D j M Y, H:i', $booking->slot_start) . ' &ndash; ' . gmdate('H:i', $booking->slot_end) . ' <span class="store_status ml-2">' . htmlspecialchars($booking->status) . '</span></div>';
    }
}

$escrow = null;
try { $escrow = $db->where('order_id', $order->id)->getOne(T_STORE_EARNINGS); } catch (Exception $e) {}

$buyer = $db->where('id', $order->buyer_id)->getOne(T_USERS, array('id', 'username', 'first_name', 'last_name'));
$seller_user = $db->where('id', $order->seller_user_id)->getOne(T_USERS, array('id', 'username', 'first_name', 'last_name'));

$pt->page        = 'order';
$pt->page_url_   = $pt->config->site_url . '/order/' . $order->order_number;
$pt->title       = 'Order ' . $order->order_number . ' | ' . $pt->config->title;
$pt->description = $pt->config->description;
$pt->keyword     = $pt->config->keyword;

$pt->content = PT_LoadPage('store/order', array(
    'CURRENCY'        => $currency,
    'O_NUMBER'        => htmlspecialchars($order->order_number),
    'O_ID'            => $order->id,
    'O_KIND'          => $order->kind,
    'O_STATUS'        => htmlspecialchars($order->status),
    'O_DATE'          => gmdate('d M Y, H:i', $order->created_at),
    'O_SUBTOTAL'      => $currency . ' ' . PT_StoreMoney($order->subtotal),
    'O_SUBTOTAL_RAW'  => PT_StoreMoney($order->subtotal),
    'O_DISCOUNT'      => $currency . ' ' . PT_StoreMoney($order->discount_total),
    'O_DISCOUNT_RAW'  => PT_StoreMoney($order->discount_total),
    'O_DELIVERY'      => $currency . ' ' . PT_StoreMoney($order->delivery_total),
    'O_DELIVERY_RAW'  => PT_StoreMoney($order->delivery_total),
    'O_TAX'           => $currency . ' ' . PT_StoreMoney($order->tax_total),
    'O_TAX_RAW'       => PT_StoreMoney($order->tax_total),
    'O_FEE'           => $currency . ' ' . PT_StoreMoney($jpesa_fee['fee']),
    'O_FEE_RAW'       => PT_StoreMoney($jpesa_fee['fee']),
    'O_TOTAL'         => $currency . ' ' . PT_StoreMoney($display_total),
    'O_TOTAL_RAW'     => PT_StoreMoney($display_total),
    'O_PAID'          => $currency . ' ' . PT_StoreMoney($order->paid_total),
    'O_BALANCE'       => $currency . ' ' . PT_StoreMoney(PT_StakeSub(PT_StakeDec($order->total), PT_StakeDec($order->paid_total))),
    'O_BUYER'         => htmlspecialchars(!empty($buyer) ? $buyer->username : ''),
    'O_SELLER'        => htmlspecialchars(!empty($seller_user) ? $seller_user->username : ''),
    'O_SELLER_TOTAL'  => $currency . ' ' . PT_StoreMoney($order->seller_total),
    'O_COMMISSION'    => $currency . ' ' . PT_StoreMoney($order->commission_total),
    'ITEMS_HTML'      => $items_html,
    'DOCUMENTS_HTML'  => $documents_html,
    'BOOKINGS_HTML'   => $bookings_html,
    'IS_BOOKING'      => $order->kind === 'booking' ? 1 : 0,
    'IS_SELLER'       => $is_seller ? 1 : 0,
    'IS_BUYER'        => $is_buyer ? 1 : 0,
    // Only the buyer can pay their own order (JPesa and wallet both debit the
    // buyer), so the button is never shown to the seller or an admin.
    'CAN_PAY'         => ($is_buyer && $payable) ? 1 : 0,
    'SHOW_PAY_FEE'    => $pay_fee_applies ? 1 : 0,
    'ESCROW_STATUS'   => !empty($escrow) ? htmlspecialchars($escrow->status) : '',
    'ESCROW_RELEASE'  => (!empty($escrow) && !empty($escrow->release_at)) ? gmdate('d M Y', $escrow->release_at) : '',
));
