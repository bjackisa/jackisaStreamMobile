<?php
// +------------------------------------------------------------------------+
// | Jackisa Store - marketplace engine.                                     |
// |                                                                         |
// | Products/services, bookable services, a double-entry ledger, sequential |
// | business documents (quotation, invoice, credit notes, receipt), escrow  |
// | releases feeding the existing wallet balance, reviews and membership    |
// | benefits.                                                               |
// |                                                                         |
// | All money maths runs on BCMath decimal strings (PT_StakeDec/Add/Sub/    |
// | Mul/Div/Cmp). Nothing here uses float for accounting.                   |
// +------------------------------------------------------------------------+

if (!defined('T_STORE_SELLERS')) {
    define('T_STORE_SELLERS', 'store_sellers');
    define('T_STORE_TERMS', 'store_terms_acceptances');
    define('T_STORE_CATEGORIES', 'store_categories');
    define('T_STORE_PRODUCTS', 'store_products');
    define('T_STORE_PRODUCT_IMAGES', 'store_product_images');
    define('T_STORE_PRODUCT_VARIANTS', 'store_product_variants');
    define('T_STORE_PRODUCT_SLOTS', 'store_product_slots');
    define('T_STORE_BLACKOUTS', 'store_blackouts');
    define('T_STORE_ORDERS', 'store_orders');
    define('T_STORE_ORDER_ITEMS', 'store_order_items');
    define('T_STORE_BOOKINGS', 'store_bookings');
    define('T_STORE_ATTACHMENTS', 'store_attachments');
    define('T_STORE_REVIEWS', 'store_reviews');
    define('T_STORE_LEDGER_ACCOUNTS', 'store_ledger_accounts');
    define('T_STORE_LEDGER_ENTRIES', 'store_ledger_entries');
    define('T_STORE_LEDGER_FLAGS', 'store_ledger_flags');
    define('T_STORE_DOCUMENTS', 'store_documents');
    define('T_STORE_DOCUMENT_ITEMS', 'store_document_items');
    define('T_STORE_COUNTERS', 'store_counters');
    define('T_STORE_EARNINGS', 'store_earnings');
    define('T_STORE_NOTIFICATIONS', 'store_notifications');
    define('T_STORE_MEMBERSHIP', 'store_membership_benefits');
    define('T_STORE_LOCATIONS', 'store_locations');
    define('T_STORE_DELIVERY_ZONES', 'store_delivery_zones');
    define('T_STORE_PICKUP_POINTS', 'store_pickup_points');
    define('T_STORE_VERIFICATION_DOCS', 'store_verification_documents');
    define('T_STORE_ADDON_CATEGORIES', 'store_addon_categories');
    define('T_STORE_ADDONS', 'store_addons');
    define('T_STORE_ATTRIBUTE_DEFS', 'store_attribute_defs');
    define('T_STORE_REJECTION_REASONS', 'store_rejection_reasons');
}

// =========================================================================
//  Config / currency
// =========================================================================

function PT_StoreParam($key, $default = '') {
    global $pt;
    if (!empty($pt->config) && isset($pt->config->$key) && $pt->config->$key !== '') {
        return $pt->config->$key;
    }
    return $default;
}

function PT_StoreEnabled() {
    return PT_StoreParam('store_enabled', '1') == '1';
}

/** Store currency always follows the platform currency unless explicitly set. */
function PT_StoreCurrency() {
    $explicit = PT_StoreParam('store_currency', '');
    if ($explicit !== '') {
        return $explicit;
    }
    return PT_StoreParam('payment_currency', 'UGX');
}

function PT_StoreCurrencySymbol() {
    global $pt;
    $currency = PT_StoreCurrency();
    if (!empty($pt->config->currency_symbol_array) && !empty($pt->config->currency_symbol_array[$currency])) {
        return $pt->config->currency_symbol_array[$currency];
    }
    return PT_StakeCurrencySymbol();
}

function PT_StoreMoney($value, $decimals = 2) {
    return PT_StakeMoney($value, $decimals);
}

// =========================================================================
//  Document PDF rendering
// =========================================================================

/**
 * Path to the bundled pure-PHP PDF renderer (dompdf). It ships with the app so
 * documents convert on any shared host, with no headless browser, shell access
 * or system package required.
 */
function PT_StorePdfAutoload() {
    return __DIR__ . '/../libs/dompdf/vendor/autoload.php';
}

/**
 * Is the bundled renderer usable? Returns 'dompdf' when the library is present
 * and the PHP runtime is new enough, or '' when it is missing or the host runs
 * an older PHP (the caller then falls back to the HTML document). dompdf 3.x
 * needs PHP 7.4+ (spl_object_id, mb_str_split and its html5 dependency), so a
 * PHP 7.1-7.3 host keeps the previous HTML behaviour instead of erroring.
 */
function PT_StorePdfBrowser() {
    static $available = null;
    if ($available === null) {
        $available = (is_file(PT_StorePdfAutoload()) && version_compare(PHP_VERSION, '7.4.0', '>=')) ? 'dompdf' : '';
    }
    return $available;
}

/**
 * Adapt the shared document HTML for dompdf. dompdf has no flexbox engine, so
 * the three flex rows the templates use (letterhead, party boxes, authorisation
 * block) would stack. A PDF-only stylesheet injected last swaps them for the
 * equivalent table layout; the same HTML still renders with real flexbox in an
 * email client.
 */
function PT_StorePdfHtml($html) {
    $shim = '<style>'
        . '.head{display:table;width:100%}'
        . '.head>.brand{display:table-cell;vertical-align:top;padding-right:24px}'
        . '.head>.title{display:table-cell;vertical-align:top;text-align:right}'
        . '.parties{display:table;width:100%;border-collapse:collapse}'
        . '.parties>div{display:table-cell;vertical-align:top;width:33.33%}'
        . '.auth{display:table;width:100%}'
        . '.auth>div{display:table-cell;vertical-align:top;padding-right:24px}'
        . '</style>';
    if (stripos($html, '</head>') !== false) {
        return preg_replace('#</head>#i', $shim . '</head>', $html, 1);
    }
    return $shim . $html;
}

/**
 * Render document HTML into a PDF file with the bundled pure-PHP library.
 * Returns the path, or false when the library is unavailable or rendering
 * fails (the caller then falls back to HTML).
 */
function PT_StoreHtmlToPdf($html, $basename = 'document') {
    if (PT_StorePdfBrowser() === '') {
        PT_StoreLog('pdf: bundled dompdf library unavailable (missing or PHP < 7.4)');
        return false;
    }
    $autoload = PT_StorePdfAutoload();
    require_once $autoload;
    if (!class_exists('\Dompdf\Dompdf')) {
        PT_StoreLog('pdf: dompdf class not autoloadable');
        return false;
    }
    $dir = __DIR__ . '/../store_pdf';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    // The rendered documents are private business records; never serve them
    // directly. They are only ever streamed by the authorised AJAX endpoint.
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    @file_put_contents($dir . '/index.html', 'index.html');
    // dompdf caches generated font metrics; give it a writable directory.
    $fonts = $dir . '/fonts';
    if (!is_dir($fonts)) {
        @mkdir($fonts, 0777, true);
    }
    $token = preg_replace('/[^A-Za-z0-9]/', '', (string) $basename) . '-' . bin2hex(random_bytes(8));
    $pdf_file = $dir . '/' . $token . '.pdf';
    try {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', $dir);
        $options->set('chroot', dirname(__DIR__, 2));
        if (is_dir($fonts) && is_writable($fonts)) {
            $options->set('fontDir', $fonts);
            $options->set('fontCache', $fonts);
        }
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(PT_StorePdfHtml($html), 'UTF-8');
        $dompdf->render();
        $pdf = $dompdf->output();
    } catch (\Throwable $e) {
        PT_StoreLog('pdf: render failed: ' . $e->getMessage());
        return false;
    }
    if (strncmp((string) $pdf, '%PDF-', 5) !== 0 || strlen($pdf) < 100) {
        PT_StoreLog('pdf: renderer produced no usable output');
        return false;
    }
    if (@file_put_contents($pdf_file, $pdf) === false) {
        PT_StoreLog('pdf: could not write ' . $pdf_file);
        return false;
    }
    return $pdf_file;
}

/** Human display: symbol + formatted amount (never used for storage). */
function PT_StoreMoneyDisplay($value, $decimals = 2) {
    return PT_StoreCurrencySymbol() . PT_StakeMoney($value, $decimals);
}

/**
 * Round a non-negative decimal string to whole cents, half-up. Money is never
 * handled as a float, so the wallet's round(amount / 0.97, 2) is reproduced in
 * bcmath instead of PHP's round().
 */
function PT_StoreRoundCents($value) {
    $scaled = PT_StakeAdd(PT_StakeMul($value, '100'), '0.5');
    $whole = strstr($scaled, '.', true);
    if ($whole === false) {
        $whole = $scaled;
    }
    return PT_StakeDiv($whole, '100');
}

/**
 * The JPesa mobile-money fee is passed on to the payer exactly like a wallet
 * refill: the provider charges a service fee on top, so the amount pushed to
 * JPesa is grossed up from the amount the buyer must settle. Returns
 * array('amount' => what the order keeps, 'fee' => the service fee,
 * 'charged' => what is requested from JPesa). The gross-up mirrors the
 * wallet/subscription refill (amount / (1 - rate/100), rounded to whole
 * cents); the rate is a config value (store_jpesa_fee_percent, default 3%)
 * so it can track JPesa's pricing.
 */
function PT_StoreJPesaFeeSplit($amount) {
    $amount = PT_StakeDec($amount);
    $rate = PT_StakeDec(PT_StoreParam('store_jpesa_fee_percent', '3'));
    if (PT_StakeCmp($rate, '0') <= 0 || PT_StakeCmp($rate, '100') >= 0) {
        return array('amount' => $amount, 'fee' => PT_StakeDec(0), 'charged' => $amount);
    }
    // charged = amount / (1 - rate/100), rounded to cents like the wallet.
    $charged = PT_StoreRoundCents(PT_StakeDiv(PT_StakeMul($amount, '100'), PT_StakeSub('100', $rate)));
    $fee = PT_StakeSub($charged, $amount);
    return array('amount' => $amount, 'fee' => $fee, 'charged' => $charged);
}

// =========================================================================
//  Terms of service consent
// =========================================================================

function PT_StorePolicyVersions() {
    return array(
        'store'  => PT_StoreParam('store_terms_version', '1.0'),
        'stream' => PT_StoreParam('store_stream_terms_version', '1.0'),
        'refund' => PT_StoreParam('store_refund_version', '1.0'),
        'privacy'=> PT_StoreParam('store_privacy_version', '1.0'),
    );
}

/**
 * Has this member accepted the current policy set?
 *
 * Consent is granted once and for all: accepting the Store policies in any
 * context (seller or buyer) covers every future selling and buying action, so
 * a member is never asked to approve the same terms again. Only a version
 * bump of one of the policies (managed in admin) needs a fresh acceptance.
 */
function PT_StoreHasAcceptedTerms($user_id, $context = 'seller') {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $versions = PT_StorePolicyVersions();
    try {
        $rows = $db->where('user_id', $user_id)
                   ->orderBy('id', 'DESC')
                   ->get(T_STORE_TERMS);
    } catch (Exception $e) {
        return false;
    }
    if (empty($rows)) {
        return false;
    }
    foreach ($rows as $row) {
        if ($row->store_terms_version === $versions['store']
            && $row->stream_terms_version === $versions['stream']
            && $row->refund_policy_version === $versions['refund']
            && $row->privacy_policy_version === $versions['privacy']) {
            return true;
        }
    }
    return false;
}

/** The context under which the member first consented (for display only). */
function PT_StoreTermsContext($user_id) {
    global $db;
    try {
        $row = $db->where('user_id', (int) $user_id)->orderBy('id', 'ASC')->getOne(T_STORE_TERMS);
    } catch (Exception $e) {
        return '';
    }
    return !empty($row) ? $row->context : '';
}

function PT_StoreAcceptTerms($user_id, $context = 'seller') {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    // Already accepted and current: never record the same consent twice.
    if (PT_StoreHasAcceptedTerms($user_id, $context)) {
        return true;
    }
    $context = in_array($context, array('seller', 'buyer'), true) ? $context : 'seller';
    $versions = PT_StorePolicyVersions();
    $ip = function_exists('PT_SecurityClientIp') ? PT_SecurityClientIp() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 300) : '';
    $id = $db->insert(T_STORE_TERMS, array(
        'user_id'               => $user_id,
        'context'               => $context,
        'store_terms_version'   => $versions['store'],
        'stream_terms_version'  => $versions['stream'],
        'refund_policy_version' => $versions['refund'],
        'privacy_policy_version'=> $versions['privacy'],
        'ip_address'            => $ip,
        'user_agent'            => $ua,
        'accepted_at'           => time(),
    ));
    return !empty($id);
}

// =========================================================================
//  Sellers / storefronts
// =========================================================================

function PT_StoreSellerByUser($user_id) {
    global $db;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }
    try {
        return $db->where('user_id', $user_id)->getOne(T_STORE_SELLERS);
    } catch (Exception $e) {
        return null;
    }
}

function PT_StoreSellerById($seller_id) {
    global $db;
    try {
        return $db->where('id', (int) $seller_id)->getOne(T_STORE_SELLERS);
    } catch (Exception $e) {
        return null;
    }
}

/** Owner username of a seller, used to build the canonical @user storefront URL. */
function PT_StoreSellerUsername($seller) {
    global $db;
    if (empty($seller) || empty($seller->user_id)) {
        return '';
    }
    try {
        $user = $db->where('id', (int) $seller->user_id)->getOne(T_USERS, array('username'));
        return !empty($user) ? (string) $user->username : '';
    } catch (Exception $e) {
        return '';
    }
}

function PT_StoreCreateSeller($user_id, $data = array()) {
    global $db, $pt;
    $user_id = (int) $user_id;
    if ($user_id <= 0 || !PT_StoreHasAcceptedTerms($user_id, 'seller')) {
        return array('status' => false, 'message' => 'You must accept the Store policies first.');
    }
    if (!empty(PT_StoreSellerByUser($user_id))) {
        return array('status' => false, 'message' => 'You already have a store.');
    }
    $user = $db->where('id', $user_id)->getOne(T_USERS, array('username', 'email', 'phone_number', 'first_name', 'last_name'));
    $name = !empty($data['store_name']) ? trim($data['store_name']) : '';
    if ($name === '' && !empty($user)) {
        $name = $user->username . "'s Store";
    }
    $slug = PT_StoreSlug($name, $user_id);
    $store_type = (!empty($data['store_type']) && $data['store_type'] === 'business') ? 'business' : 'personal';
    $status = (PT_StoreParam('store_require_seller_approval', '1') == '1') ? 'pending' : 'active';
    $location_id = !empty($data['location_id']) ? (int) $data['location_id'] : 0;
    $location_name = '';
    if ($location_id > 0) {
        $loc = PT_StoreLocation($location_id);
        if (!empty($loc)) {
            $location_name = $loc->name;
        }
    }
    if ($location_name === '' && !empty($data['location'])) {
        $location_name = trim($data['location']);
    }
    $id = $db->insert(T_STORE_SELLERS, array(
        'user_id'       => $user_id,
        'store_name'    => PT_Secure($name),
        'store_slug'    => $slug,
        'description'   => !empty($data['description']) ? PT_Secure($data['description']) : '',
        'contact_email' => !empty($data['contact_email']) ? PT_Secure($data['contact_email']) : (!empty($user) ? $user->email : ''),
        'contact_phone' => !empty($data['contact_phone']) ? PT_Secure($data['contact_phone']) : (!empty($user) ? $user->phone_number : ''),
        'location'      => PT_Secure($location_name),
        'location_id'   => $location_id,
        'address'       => !empty($data['address']) ? PT_Secure($data['address']) : '',
        'store_type'    => $store_type,
        'reg_number'    => !empty($data['reg_number']) ? PT_Secure($data['reg_number']) : '',
        'tax_id'        => !empty($data['tax_id']) ? PT_Secure($data['tax_id']) : '',
        'verification_status' => 'unverified',
        'offers_delivery' => isset($data['offers_delivery']) ? (!empty($data['offers_delivery']) ? 1 : 0) : 1,
        'offers_pickup'   => !empty($data['offers_pickup']) ? 1 : 0,
        'status'        => $status,
        'created_at'    => time(),
        'updated_at'    => time(),
    ));
    if (empty($id)) {
        return array('status' => false, 'message' => 'Could not create the store.');
    }
    $db->insert(T_STORE_MEMBERSHIP, array('seller_id' => $id, 'updated_at' => time()));
    return array('status' => true, 'seller_id' => $id, 'store_type' => $store_type, 'status' => $status);
}

/** Update a storefront's editable profile fields. */
function PT_StoreUpdateSeller($user_id, $data = array()) {
    global $db;
    $seller = PT_StoreSellerByUser($user_id);
    if (empty($seller)) {
        return array('status' => false, 'message' => 'Store not found.');
    }
    $fields = array('updated_at' => time());
    if (isset($data['store_name']) && trim($data['store_name']) !== '') {
        $fields['store_name'] = PT_Secure(trim($data['store_name']));
    }
    if (isset($data['description'])) {
        $fields['description'] = PT_Secure($data['description']);
    }
    if (isset($data['contact_email'])) {
        $fields['contact_email'] = PT_Secure($data['contact_email']);
    }
    if (isset($data['contact_phone'])) {
        $fields['contact_phone'] = PT_Secure($data['contact_phone']);
    }
    if (isset($data['address'])) {
        $fields['address'] = PT_Secure($data['address']);
    }
    if (!empty($data['location_id'])) {
        $fields['location_id'] = (int) $data['location_id'];
        $loc = PT_StoreLocation((int) $data['location_id']);
        if (!empty($loc)) {
            $fields['location'] = PT_Secure($loc->name);
        }
    } elseif (isset($data['location'])) {
        $fields['location'] = PT_Secure($data['location']);
    }
    if (isset($data['offers_delivery'])) {
        $fields['offers_delivery'] = !empty($data['offers_delivery']) ? 1 : 0;
    }
    if (isset($data['offers_pickup'])) {
        $fields['offers_pickup'] = !empty($data['offers_pickup']) ? 1 : 0;
    }
    if (!empty($data['logo'])) {
        $fields['logo'] = $data['logo'];
    }
    if (!empty($data['banner'])) {
        $fields['banner'] = $data['banner'];
    }
    $db->where('id', $seller->id)->update(T_STORE_SELLERS, $fields);
    return array('status' => true);
}

function PT_StoreSlug($name, $id = 0) {
    global $db;
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $name), '-'));
    if ($base === '') {
        $base = 'store';
    }
    $base = substr($base, 0, 140);
    $slug = $base;
    $i = 1;
    while (true) {
        $exists = $db->where('store_slug', $slug)->getValue(T_STORE_SELLERS, 'COUNT(*)');
        if (empty($exists)) {
            break;
        }
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}

function PT_StoreSellerActive($seller) {
    return !empty($seller) && $seller->status === 'active';
}

/**
 * Every state a storefront can be in, from the seller's point of view. Drives
 * the seller dashboard screens (under review, empty, rejected, live).
 */
function PT_StoreSellerState($seller) {
    if (empty($seller)) {
        return 'none';
    }
    if ($seller->status === 'rejected') {
        return 'rejected';
    }
    if ($seller->status === 'pending') {
        return 'review';
    }
    if ($seller->status === 'suspended') {
        return 'suspended';
    }
    return 'active';
}

// =========================================================================
//  Locations
// =========================================================================

function PT_StoreLocations($type = null, $parent_id = null) {
    global $db;
    try {
        $q = $db->where('is_active', 1);
        if (!empty($type)) {
            $q = $q->where('type', $type);
        }
        if ($parent_id !== null) {
            $q = $q->where('parent_id', (int) $parent_id);
        }
        return $q->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get(T_STORE_LOCATIONS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreLocation($id) {
    global $db;
    try {
        return $db->where('id', (int) $id)->getOne(T_STORE_LOCATIONS);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * id => "Country > Region > District/City > Area" labels for every location, so
 * a country is never listed beside the cities inside it.
 */
function PT_StoreLocationLabels() {
    $by_id = array();
    $country_name = array();
    foreach (PT_StoreLocations() as $loc) {
        $by_id[(int) $loc->id] = $loc;
        if ((string) $loc->type === 'country' && (string) $loc->name !== '') {
            $country_name[strtoupper((string) $loc->country_code)] = $loc->name;
        }
    }
    $labels = array();
    foreach ($by_id as $id => $loc) {
        $parts = array();
        $node  = $loc;
        $guard = 0;
        while (!empty($node) && $guard++ < 8) {
            if ((string) $node->name !== '') {
                array_unshift($parts, $node->name);
            }
            if (empty($node->parent_id) || empty($by_id[(int) $node->parent_id])) {
                break;
            }
            $node = $by_id[(int) $node->parent_id];
        }
        // Make the country the explicit root so a region or city is never
        // listed as a sibling of the country that contains it.
        $cc = strtoupper((string) $loc->country_code);
        if (!empty($country_name[$cc]) && (empty($parts) || $parts[0] !== $country_name[$cc])) {
            array_unshift($parts, $country_name[$cc]);
        }
        $labels[$id] = implode(' > ', $parts);
    }
    return $labels;
}

/**
 * Flat <option> list of locations, optionally filtered to delivery regions.
 */
function PT_StoreLocationOptions($selected = 0, $country_code = 'UG') {
    $labels = PT_StoreLocationLabels();
    $out = '';
    foreach (PT_StoreLocations() as $loc) {
        $out .= '<option value="' . (int) $loc->id . '"' . ((int) $selected === (int) $loc->id ? ' selected' : '') . '>'
            . htmlspecialchars(isset($labels[(int) $loc->id]) ? $labels[(int) $loc->id] : $loc->name) . '</option>';
    }
    return $out;
}

// =========================================================================
//  Delivery pricing
// =========================================================================

function PT_StoreDeliveryZones($seller_id, $active_only = true) {
    global $db;
    try {
        $q = $db->where('seller_id', (int) $seller_id);
        if ($active_only) {
            $q = $q->where('is_active', 1);
        }
        return $q->orderBy('id', 'ASC')->get(T_STORE_DELIVERY_ZONES);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StorePickupPoints($seller_id, $active_only = true) {
    global $db;
    try {
        $q = $db->where('seller_id', (int) $seller_id);
        if ($active_only) {
            $q = $q->where('is_active', 1);
        }
        return $q->orderBy('id', 'ASC')->get(T_STORE_PICKUP_POINTS);
    } catch (Exception $e) {
        return array();
    }
}

/**
 * The buyer's last-used delivery location, so we never make a returning buyer
 * re-enter a location they already gave us. Falls back to 0 when unknown.
 */
function PT_StoreBuyerLocationId($buyer_id) {
    global $db;
    $buyer_id = (int) $buyer_id;
    if ($buyer_id <= 0) {
        return 0;
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['store_buyer_location'])) {
        return (int) $_SESSION['store_buyer_location'];
    }
    try {
        $row = $db->where('buyer_id', $buyer_id)
                  ->where('buyer_location_id', 0, '>')
                  ->orderBy('id', 'DESC')
                  ->getOne(T_STORE_ORDERS, array('buyer_location_id'));
    } catch (Exception $e) {
        $row = null;
    }
    return !empty($row) ? (int) $row->buyer_location_id : 0;
}

/** Remember the buyer's location for their next order. */
function PT_StoreRememberBuyerLocation($buyer_id, $location_id) {
    $location_id = (int) $location_id;
    if ($location_id <= 0) {
        return;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['store_buyer_location'] = $location_id;
    }
}

/**
 * Price delivery for one seller's basket.
 *
 * The fee is built from the seller's zone for the buyer's location: a base fee
 * plus a per-kilogram component, clamped to the zone's min/max and waived over
 * its free-over threshold. Pickup orders pay the pickup point's handling fee;
 * digital goods and services delivered at the provider are free.
 *
 * $lines is a list of array('product' => row, 'qty' => n).
 * Returns array('fee' => decimal string, 'mode' => string, 'zone' => row|null,
 *               'weight_grams' => int, 'reason' => string).
 */
function PT_StoreQuoteDelivery($seller, $lines, $buyer_location_id = 0, $mode = 'deliver', $pickup_id = 0) {
    $result = array('fee' => PT_StakeDec(0), 'mode' => $mode, 'zone' => null, 'weight_grams' => 0, 'reason' => '');
    if (empty($seller) || empty($lines)) {
        return $result;
    }
    // Nothing to ship for services rendered at the provider or digital goods.
    $shippable = array();
    $weight = 0;
    $goods_total = PT_StakeDec(0);
    foreach ($lines as $line) {
        $product = $line['product'];
        $qty = max(1, (int) $line['qty']);
        if ($product->kind === 'service') {
            if (empty($mode) || $mode === 'service_location') {
                $result['mode'] = 'at_provider';
            }
            continue;
        }
        if ($product->product_type !== 'physical') {
            continue;
        }
        if ($mode === 'pickup') {
            continue;
        }
        $shippable[] = $line;
        $weight += max(0, (int) $product->weight_grams) * $qty;
        $goods_total = PT_StakeAdd($goods_total, PT_StakeMul(PT_StoreEffectivePrice($product, false), PT_StakeDec($qty)));
    }
    $result['weight_grams'] = $weight;

    if ($mode === 'pickup') {
        $result['mode'] = 'pickup';
        $point = null;
        if ($pickup_id > 0) {
            foreach (PT_StorePickupPoints($seller->id) as $p) {
                if ((int) $p->id === (int) $pickup_id) {
                    $point = $p;
                    break;
                }
            }
        }
        if (empty($point)) {
            $points = PT_StorePickupPoints($seller->id);
            $point = !empty($points) ? $points[0] : null;
        }
        if (!empty($point)) {
            $result['fee'] = PT_StakeDec($point->fee);
        }
        $result['reason'] = $point ? ('Pickup at ' . $point->name) : 'Pickup at the seller';
        return $result;
    }

    if (empty($shippable)) {
        $result['mode'] = $result['mode'] ?: 'digital';
        return $result;
    }
    if (empty($seller->offers_delivery)) {
        $result['mode'] = 'pickup_only';
        $result['reason'] = 'This store does not deliver. Choose pickup.';
        return $result;
    }

    $zone = null;
    $zone_id = 0;
    if ($buyer_location_id > 0) {
        foreach (PT_StoreDeliveryZones($seller->id) as $z) {
            if ((int) $z->location_id === (int) $buyer_location_id) {
                $zone = $z;
                break;
            }
        }
    }
    if (empty($zone)) {
        // Fall back to the seller's default zone, if any, then a flat fee.
        $zones = PT_StoreDeliveryZones($seller->id);
        foreach ($zones as $z) {
            if ((int) $z->location_id === 0) {
                $zone = $z;
                break;
            }
        }
    }
    if (!empty($zone)) {
        // Free over a threshold the seller set.
        if (PT_StakeCmp(PT_StakeDec($zone->free_over), '0') > 0 && PT_StakeCmp($goods_total, PT_StakeDec($zone->free_over)) >= 0) {
            $result['fee'] = PT_StakeDec(0);
            $result['reason'] = 'Free delivery on this zone order';
            $result['zone'] = $zone;
            return $result;
        }
        $per_kg = PT_StakeMul(PT_StakeDec($zone->per_kg_fee), PT_StakeDiv(PT_StakeDec($weight), '1000'));
        $fee = PT_StakeAdd(PT_StakeDec($zone->base_fee), $per_kg);
        if (PT_StakeCmp(PT_StakeDec($zone->min_fee), '0') > 0 && PT_StakeCmp($fee, PT_StakeDec($zone->min_fee)) < 0) {
            $fee = PT_StakeDec($zone->min_fee);
        }
        if (PT_StakeCmp(PT_StakeDec($zone->max_fee), '0') > 0 && PT_StakeCmp($fee, PT_StakeDec($zone->max_fee)) > 0) {
            $fee = PT_StakeDec($zone->max_fee);
        }
        $result['fee'] = $fee;
        $result['zone'] = $zone;
        $result['reason'] = $zone->label !== '' ? $zone->label : 'Zone delivery';
        return $result;
    }

    // No zone configured: use the per-product shipping fee as a base.
    $fee = PT_StakeDec(0);
    foreach ($shippable as $line) {
        $fee = PT_StakeAdd($fee, PT_StakeMul(PT_StakeDec($line['product']->delivery_fee), PT_StakeDec(max(1, (int) $line['qty']))));
    }
    $result['fee'] = $fee;
    $result['reason'] = 'Standard delivery';
    return $result;
}

// =========================================================================
//  System-generated, globally unique SKUs
// =========================================================================

/**
 * Allocate the next system SKU. Every SKU is unique across the whole store
 * (the store_products.sku column is unique) and is never typed by a seller.
 */
function PT_StoreGenerateSku($kind = 'product') {
    $prefix = ($kind === 'service') ? 'SVC' : 'PRD';
    // Prefix + random alphanumeric (no dash, no date), like every other store
    // reference. Retry on the rare collision against the unique sku column.
    for ($i = 0; $i < 8; $i++) {
        $sku = PT_StoreRef($prefix, 8);
        if (!PT_StoreSkuExists($sku)) {
            return $sku;
        }
    }
    return PT_StoreRef($prefix, 12);
}

/** True when a SKU is already in use by another listing. */
function PT_StoreSkuExists($sku, $except_id = 0) {
    global $db;
    $sku = trim((string) $sku);
    if ($sku === '') {
        return false;
    }
    try {
        $q = $db->where('sku', $sku);
        if ((int) $except_id > 0) {
            $q = $q->where('id', (int) $except_id, '!=');
        }
        return (int) $q->getValue(T_STORE_PRODUCTS, 'COUNT(*)') > 0;
    } catch (Exception $e) {
        return false;
    }
}

// =========================================================================
//  Add-on catalogue (subscriber perks chosen from a dropdown)
// =========================================================================

function PT_StoreAddonCategories($kind = null) {
    global $db;
    try {
        $q = $db->where('is_active', 1);
        if (!empty($kind) && in_array($kind, array('product', 'service'), true)) {
            $q = $q->where('kind', array($kind, 'both'), 'IN');
        }
        return $q->orderBy('sort_order', 'ASC')->get(T_STORE_ADDON_CATEGORIES);
    } catch (Exception $e) {
        return array();
    }
}

/**
 * Add-ons available for a kind/category, grouped by their sub-category so the
 * form can render an optgroup dropdown.
 */
function PT_StoreAddons($kind = 'product', $category_id = 0) {
    global $db;
    try {
        $kind = in_array($kind, array('product', 'service'), true) ? $kind : 'product';
        $rows = $db->where('is_active', 1)
                   ->where('kind', array($kind, 'both'), 'IN')
                   ->where('category_id', array(0, (int) $category_id), 'IN')
                   ->orderBy('sort_order', 'ASC')
                   ->get(T_STORE_ADDONS);
    } catch (Exception $e) {
        return array();
    }
    return $rows ?: array();
}

function PT_StoreAddon($id) {
    global $db;
    try {
        return $db->where('id', (int) $id)->getOne(T_STORE_ADDONS);
    } catch (Exception $e) {
        return null;
    }
}

/** Human description of an add-on's value for a price display. */
function PT_StoreAddonValueText($addon) {
    if (empty($addon)) {
        return '';
    }
    switch ($addon->value_type) {
        case 'percent':
            return number_format((float) $addon->value, 0) . '% off';
        case 'amount':
            return PT_StoreMoneyDisplay($addon->value);
        case 'extended':
            return '+' . (int) $addon->extends_days . ' days';
        default:
            return 'Free';
    }
}

// =========================================================================
//  Category-specific attributes
// =========================================================================

function PT_StoreAttributeDefs($category_id, $kind = 'product') {
    global $db;
    try {
        $kind = in_array($kind, array('product', 'service'), true) ? $kind : 'product';
        return $db->where('category_id', (int) $category_id)
                  ->where('is_active', 1)
                  ->where('kind', $kind)
                  ->orderBy('sort_order', 'ASC')
                  ->get(T_STORE_ATTRIBUTE_DEFS);
    } catch (Exception $e) {
        return array();
    }
}

/**
 * Decode a product's stored attribute JSON into label/value pairs based on the
 * category definitions, ready for display.
 */
function PT_StoreProductAttributes($product) {
    $out = array();
    if (empty($product) || empty($product->attributes)) {
        return $out;
    }
    $values = json_decode($product->attributes, true);
    if (!is_array($values)) {
        return $out;
    }
    $defs = PT_StoreAttributeDefs($product->category_id, $product->kind === 'service' ? 'service' : 'product');
    $labels = array();
    foreach ($defs as $def) {
        $labels[$def->field_key] = $def;
    }
    // Preserve definition order; then append any orphan keys.
    foreach ($defs as $def) {
        if (!isset($values[$def->field_key])) {
            continue;
        }
        $val = $values[$def->field_key];
        if (is_array($val)) {
            $val = implode(', ', array_map('strval', $val));
        }
        if ($val === '' || $val === null) {
            continue;
        }
        $out[] = array(
            'key'   => $def->field_key,
            'label' => $def->label,
            'unit'  => $def->unit,
            'value' => (string) $val,
        );
    }
    foreach ($values as $key => $val) {
        if (isset($labels[$key])) {
            continue;
        }
        if (is_array($val)) {
            $val = implode(', ', array_map('strval', $val));
        }
        if ($val === '' || $val === null) {
            continue;
        }
        $out[] = array('key' => $key, 'label' => ucwords(str_replace('_', ' ', $key)), 'unit' => '', 'value' => (string) $val);
    }
    return $out;
}

// =========================================================================
//  Tax
// =========================================================================

/**
 * Split a tax-inclusive or tax-exclusive amount into its net and tax parts.
 *
 * The platform tax (8% by default) is withheld from the amount the buyer pays;
 * it is never added to the seller's payout nor counted as platform commission.
 */
function PT_StoreTaxRate() {
    return PT_StakeDec(PT_StoreParam('store_tax_rate', '8'));
}

/**
 * For a line amount, return array('net' => ..., 'tax' => ..., 'gross' => ...).
 * When $inclusive is true the amount already contains tax; otherwise tax is
 * added on top to produce the gross.
 */
function PT_StoreTaxSplit($amount, $inclusive = false) {
    $amount = PT_StakeDec($amount);
    $rate = PT_StoreTaxRate();
    if (PT_StakeCmp($rate, '0') <= 0) {
        return array('net' => $amount, 'tax' => PT_StakeDec(0), 'gross' => $amount);
    }
    if ($inclusive) {
        // net = amount / (1 + rate/100); tax = amount - net
        $divisor = PT_StakeAdd('1', PT_StakeDiv($rate, '100'));
        $net = PT_StakeDiv($amount, $divisor);
        $tax = PT_StakeSub($amount, $net);
        return array('net' => $net, 'tax' => $tax, 'gross' => $amount);
    }
    $tax = PT_StakeDiv(PT_StakeMul($amount, $rate), '100');
    return array('net' => $amount, 'tax' => $tax, 'gross' => PT_StakeAdd($amount, $tax));
}

// =========================================================================
//  Verification
// =========================================================================

function PT_StoreVerificationDocuments($seller_id) {
    global $db;
    try {
        return $db->where('seller_id', (int) $seller_id)->orderBy('id', 'DESC')->get(T_STORE_VERIFICATION_DOCS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreAddVerificationDocument($seller_id, $user_id, $doc_type, $file, $original_name = '') {
    global $db;
    $allowed = array('business_licence', 'certificate_of_incorporation', 'tin_certificate', 'national_id', 'other');
    if (!in_array($doc_type, $allowed, true)) {
        $doc_type = 'other';
    }
    return $db->insert(T_STORE_VERIFICATION_DOCS, array(
        'seller_id'     => (int) $seller_id,
        'user_id'       => (int) $user_id,
        'doc_type'      => $doc_type,
        'file'          => $file,
        'original_name' => substr((string) $original_name, 0, 300),
        'status'        => 'submitted',
        'created_at'    => time(),
    ));
}

/** Required document types for a store type. */
function PT_StoreRequiredDocs($store_type) {
    if ($store_type === 'business') {
        return array(
            'certificate_of_incorporation' => 'Certificate of incorporation / business registration',
            'business_licence'             => 'Trading licence',
        );
    }
    return array('national_id' => 'National ID');
}

/**
 * Approve or reject a storefront's verification. On rejection the reason is
 * stored and returned to the seller (who may resubmit); either way the seller
 * is notified in-app and by email.
 */
function PT_StoreSetSellerVerification($seller_id, $status, $reason_code = '', $by_user = 0) {
    global $db;
    $seller = PT_StoreSellerById($seller_id);
    if (empty($seller) || !in_array($status, array('approved', 'rejected', 'submitted', 'unverified'), true)) {
        return false;
    }
    $fields = array(
        'verification_status' => $status,
        'rejection_reason'    => ($status === 'rejected') ? PT_Secure($reason_code) : '',
        'reviewed_at'         => time(),
        'reviewed_by'         => (int) $by_user,
        'updated_at'          => time(),
    );
    // Approval also makes the storefront live (or rejected), so a verified
    // seller is not left looking at an "under review" store.
    if ($status === 'approved') {
        $fields['status'] = 'active';
    } elseif ($status === 'rejected') {
        $fields['status'] = 'rejected';
    }
    $db->where('id', $seller_id)->update(T_STORE_SELLERS, $fields);

    if ($status === 'approved') {
        PT_StoreNotifyUser($seller->user_id, 'seller', 0, 'store_approved', 'Your store is approved', 'Your Jackisa Store "' . $seller->store_name . '" has been approved. You can now list and sell.');
    } elseif ($status === 'rejected') {
        $label = PT_StoreRejectionReasonLabel($reason_code);
        PT_StoreNotifyUser($seller->user_id, 'seller', 0, 'store_rejected', 'Your store was not approved', 'Reason: ' . $label . '. Please update and resubmit.');
    }
    PT_StoreEmailSellerStatus($seller, $status, $reason_code);
    return true;
}

/** Email a seller about a store/product review outcome. */
function PT_StoreEmailSellerStatus($seller, $status, $reason_code = '') {
    global $db;
    if (empty($seller)) {
        return false;
    }
    $user = $db->where('id', $seller->user_id)->getOne(T_USERS, array('email', 'username', 'first_name'));
    if (empty($user) || empty($user->email)) {
        return false;
    }
    if ($status === 'approved') {
        $subject = 'Jackisa Store - store approved';
        $body = '<p>Good news — your store <b>' . htmlspecialchars($seller->store_name) . '</b> has been approved.</p><p>You can now list products and services.</p>';
    } elseif ($status === 'rejected') {
        $subject = 'Jackisa Store - store needs changes';
        $body = '<p>Your store <b>' . htmlspecialchars($seller->store_name) . '</b> was not approved.</p><p><b>Reason:</b> ' . htmlspecialchars(PT_StoreRejectionReasonLabel($reason_code)) . '</p><p>Please update your store and resubmit.</p>';
    } else {
        return false;
    }
    return PT_StoreSendMail($user->email, $subject, $body, $user->username);
}

function PT_StoreRejectionReasons($scope = null) {
    global $db;
    try {
        $q = $db->where('is_active', 1);
        if (!empty($scope) && in_array($scope, array('seller', 'product'), true)) {
            $q = $q->where('scope', array($scope, 'both'), 'IN');
        }
        return $q->orderBy('sort_order', 'ASC')->get(T_STORE_REJECTION_REASONS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreRejectionReasonLabel($code) {
    global $db;
    try {
        $row = $db->where('code', $code)->getOne(T_STORE_REJECTION_REASONS);
    } catch (Exception $e) {
        $row = null;
    }
    return !empty($row) ? $row->label : (string) $code;
}

// =========================================================================
//  Categories
// =========================================================================

function PT_StoreCategories($kind = null) {
    global $db;
    try {
        if ($kind === 'product' || $kind === 'service') {
            $db->where('kind', array($kind, 'both'), 'IN');
        }
        $rows = $db->where('is_active', 1)->orderBy('sort_order', 'ASC')->get(T_STORE_CATEGORIES);
    } catch (Exception $e) {
        return array();
    }
    return $rows ?: array();
}

function PT_StoreCategory($id) {
    global $db;
    try {
        return $db->where('id', (int) $id)->getOne(T_STORE_CATEGORIES);
    } catch (Exception $e) {
        return null;
    }
}

/** Direct children of a category. */
function PT_StoreChildCategories($parent_id, $kind = null) {
    global $db;
    try {
        $q = $db->where('parent_id', (int) $parent_id)->where('is_active', 1);
        if (!empty($kind) && in_array($kind, array('product', 'service'), true)) {
            $q = $q->where('kind', array($kind, 'both'), 'IN');
        }
        return $q->orderBy('sort_order', 'ASC')->get(T_STORE_CATEGORIES);
    } catch (Exception $e) {
        return array();
    }
}

/** Top-level categories (parent_id = 0). */
function PT_StoreTopCategories($kind = null) {
    global $db;
    try {
        $q = $db->where('parent_id', 0)->where('is_active', 1);
        if (!empty($kind) && in_array($kind, array('product', 'service'), true)) {
            $q = $q->where('kind', array($kind, 'both'), 'IN');
        }
        return $q->orderBy('sort_order', 'ASC')->get(T_STORE_CATEGORIES);
    } catch (Exception $e) {
        return array();
    }
}

/**
 * A category <select> built as optgroups: top-level categories with their
 * sub-categories grouped underneath. Commissions are never shown to sellers.
 */
function PT_StoreCategoryOptions($selected = 0, $kind = null) {
    $selected = (int) $selected;
    $html = '';
    foreach (PT_StoreTopCategories($kind) as $top) {
        $children = PT_StoreChildCategories($top->id, $kind);
        if (empty($children)) {
            $html .= '<option value="' . (int) $top->id . '" data-kind="' . htmlspecialchars($top->kind) . '"'
                . ($selected === (int) $top->id ? ' selected' : '') . '>' . htmlspecialchars($top->name) . '</option>';
            continue;
        }
        $html .= '<optgroup label="' . htmlspecialchars($top->name) . '">';
        $html .= '<option value="' . (int) $top->id . '" data-kind="' . htmlspecialchars($top->kind) . '"'
            . ($selected === (int) $top->id ? ' selected' : '') . '>' . htmlspecialchars($top->name) . ' (general)</option>';
        foreach ($children as $child) {
            $html .= '<option value="' . (int) $child->id . '" data-kind="' . htmlspecialchars($child->kind) . '"'
                . ($selected === (int) $child->id ? ' selected' : '') . '>' . htmlspecialchars($child->name) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

/** Walk up to the root category that owns a (possibly nested) category. */
function PT_StoreCategoryRoot($category_id) {
    $category = PT_StoreCategory($category_id);
    $guard = 0;
    while (!empty($category) && (int) $category->parent_id > 0 && $guard < 8) {
        $category = PT_StoreCategory($category->parent_id);
        $guard++;
    }
    return $category;
}

/** Commission for a product, clamped to the configured 3%..15% band. */
function PT_StoreCommissionBps($product, $seller = null) {
    if ($seller === null && !empty($product->seller_id)) {
        $seller = PT_StoreSellerById($product->seller_id);
    }
    $bps = (int) PT_StoreParam('store_commission_bps', 800);
    if (!empty($seller) && !empty($seller->commission_override_bps)) {
        $bps = (int) $seller->commission_override_bps;
    }
    if (!empty($product->category_id)) {
        $category = PT_StoreCategory($product->category_id);
        if (!empty($category) && $category->commission_bps > 0) {
            $bps = (int) $category->commission_bps;
        }
    }
    $min = (int) PT_StoreParam('store_commission_min_bps', 300);
    $max = (int) PT_StoreParam('store_commission_max_bps', 1500);
    if ($bps < $min) { $bps = $min; }
    if ($bps > $max) { $bps = $max; }
    return $bps;
}

/** Escrow window in days for a product (0 = platform default). */
function PT_StoreEscrowDays($product) {
    $category = !empty($product->category_id) ? PT_StoreCategory($product->category_id) : null;
    if (!empty($category) && !empty($category->requires_return) && (int) $category->return_days > 0) {
        return (int) $category->return_days;
    }
    return (int) PT_StoreParam('store_default_escrow_days', 7);
}

// =========================================================================
//  Products
// =========================================================================

function PT_StoreProduct($id) {
    global $db;
    try {
        return $db->where('id', (int) $id)->getOne(T_STORE_PRODUCTS);
    } catch (Exception $e) {
        return null;
    }
}

function PT_StoreProductBySlug($slug) {
    global $db;
    try {
        return $db->where('slug', PT_Secure($slug))->getOne(T_STORE_PRODUCTS);
    } catch (Exception $e) {
        return null;
    }
}

/** Active discount applied to a product right now. */
function PT_StoreEffectivePrice($product, $member = false) {
    $price = PT_StakeDec($product->price);
    $now = time();
    $type = isset($product->discount_type) ? $product->discount_type : 'none';
    $value = isset($product->discount_value) ? PT_StakeDec($product->discount_value) : PT_StakeDec(0);
    $start = isset($product->discount_start) ? (int) $product->discount_start : 0;
    $end = isset($product->discount_end) ? (int) $product->discount_end : 0;
    $active = ($type !== 'none') && ($start == 0 || $start <= $now) && ($end == 0 || $end >= $now);
    if ($active && $type === 'percent' && PT_StakeCmp($value, '0') > 0) {
        $cut = PT_StakeDiv(PT_StakeMul($price, $value), '100');
        $price = PT_StakeSub($price, $cut);
    } elseif ($active && $type === 'amount' && PT_StakeCmp($value, '0') > 0) {
        $price = PT_StakeSub($price, $value);
    }
    if ($member && !empty($product->member_discount_pct) && PT_StakeCmp($product->member_discount_pct, '0') > 0) {
        $cut = PT_StakeDiv(PT_StakeMul($price, PT_StakeDec($product->member_discount_pct)), '100');
        $price = PT_StakeSub($price, $cut);
    }
    if (PT_StakeCmp($price, '0') < 0) {
        $price = PT_StakeDec(0);
    }
    return $price;
}

function PT_StoreProductImages($product_id) {
    global $db;
    try {
        return $db->where('product_id', (int) $product_id)->orderBy('sort_order', 'ASC')->get(T_STORE_PRODUCT_IMAGES);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreProductVariants($product_id) {
    global $db;
    try {
        return $db->where('product_id', (int) $product_id)->where('is_active', 1)->orderBy('id', 'ASC')->get(T_STORE_PRODUCT_VARIANTS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreProductRating($product_id) {
    $product = PT_StoreProduct($product_id);
    if (empty($product) || empty($product->rating_count)) {
        return array('average' => 0, 'count' => 0);
    }
    $avg = PT_StakeDiv(PT_StakeDec($product->rating_sum), PT_StakeDec($product->rating_count));
    return array('average' => (float) $avg, 'count' => (int) $product->rating_count);
}

/** A buyer is a subscriber of the seller when they follow the seller's channel. */
function PT_StoreBuyerIsMember($buyer_id, $seller_user_id) {
    global $db;
    $buyer_id = (int) $buyer_id;
    $seller_user_id = (int) $seller_user_id;
    if ($buyer_id <= 0 || $seller_user_id <= 0) {
        return false;
    }
    try {
        $sub = $db->where('user_id', $seller_user_id)->where('subscriber_id', $buyer_id)->where('active', 1)->getValue(T_SUBSCRIPTIONS, 'COUNT(*)');
    } catch (Exception $e) {
        return false;
    }
    if (!empty($sub)) {
        return true;
    }
    return false;
}

function PT_StoreMembershipBenefit($seller_id) {
    global $db;
    try {
        return $db->where('seller_id', (int) $seller_id)->getOne(T_STORE_MEMBERSHIP);
    } catch (Exception $e) {
        return null;
    }
}

// =========================================================================
//  Public catalog helpers
// =========================================================================

/** Published reviews for a product (newest first), with the reviewer. */
function PT_StoreProductReviews($product_id, $limit = 50) {
    global $db;
    try {
        $rows = $db->where('product_id', (int) $product_id)->where('status', 'published')->orderBy('id', 'DESC')->get(T_STORE_REVIEWS, (int) $limit);
    } catch (Exception $e) {
        return array();
    }
    foreach ($rows as $row) {
        $row->user = $db->where('id', $row->user_id)->getOne(T_USERS, array('id', 'username', 'first_name', 'last_name', 'avatar'));
    }
    return $rows;
}

/** The active storefront for a channel, or null. */
function PT_StoreStorefront($seller_user_id) {
    global $db;
    $seller = PT_StoreSellerByUser($seller_user_id);
    if (empty($seller) || $seller->status !== 'active') {
        return null;
    }
    return $seller;
}

/**
 * Store data for an account-level page (settings or profile dashboard).
 * Returns template-ready HTML for a seller's catalogue and incoming orders.
 */
function PT_StoreDashboardData($user_id) {
    global $db;
    $data = array(
        'IS_SELLER' => 0, 'HAS_SELLER_TERMS' => 0, 'HAS_BUYER_TERMS' => 0,
        'STORE_NAME' => '', 'MY_PRODUCTS_HTML' => '', 'SELLER_ORDERS_HTML' => '',
        'CATEGORIES_HTML' => '', 'SALES_TOTAL' => PT_StoreMoney('0'), 'SALES_PENDING' => PT_StoreMoney('0'),
        'STORE_STATUS' => '', 'VERIFICATION_STATUS' => '', 'STORE_BANNER' => '',
        'LOCATIONS_HTML' => '', 'ADDONS_HTML' => '', 'REJECTION_REASONS_HTML' => '',
        'PRODUCT_CATEGORIES' => '', 'STORE_TYPE' => 'personal', 'PRODUCTS_JSON' => '[]',
        'STORE_LOCATION_ID' => 0, 'STORE_ADDRESS' => '', 'STORE_DESCRIPTION' => '',
        'STORE_CONTACT_EMAIL' => '', 'STORE_CONTACT_PHONE' => '', 'OFFERS_DELIVERY' => 1, 'OFFERS_PICKUP' => 0,
        'STORE_SELLER_EMAIL' => '', 'DELIVERY_ZONES_JSON' => '[]', 'PICKUP_POINTS_JSON' => '[]',
        'VERIFICATION_DOCS' => '[]', 'BENEFIT_JSON' => '{}', 'HAS_VERIFICATION_DOCS' => 0,
        'REQUIRED_DOCS_JSON' => '{}', 'LOCATIONS_JSON' => '[]',
    );
    if (empty($data['STORE_CONTACT_EMAIL']) && $user_id) {
        $u = $db->where('id', (int) $user_id)->getOne(T_USERS, array('email'));
        if (!empty($u)) {
            $data['STORE_CONTACT_EMAIL'] = htmlspecialchars((string) $u->email);
            $data['STORE_SELLER_EMAIL'] = htmlspecialchars((string) $u->email);
        }
    }
    $currency = PT_StoreCurrencySymbol();
    $seller = PT_StoreSellerByUser($user_id);
    if (!empty($seller)) {
        $data['IS_SELLER'] = 1;
        $data['STORE_NAME'] = htmlspecialchars($seller->store_name);
        $data['STORE_TYPE'] = $seller->store_type;
        $data['STORE_STATUS'] = htmlspecialchars((string) $seller->status);
        $data['VERIFICATION_STATUS'] = htmlspecialchars((string) $seller->verification_status);
        $data['STORE_LOCATION_ID'] = (int) $seller->location_id;
        $data['STORE_ADDRESS'] = htmlspecialchars((string) $seller->address);
        $data['STORE_DESCRIPTION'] = htmlspecialchars((string) $seller->description);
        $data['STORE_CONTACT_EMAIL'] = htmlspecialchars((string) $seller->contact_email);
        $data['STORE_CONTACT_PHONE'] = htmlspecialchars((string) $seller->contact_phone);
        $data['OFFERS_DELIVERY'] = (int) $seller->offers_delivery;
        $data['OFFERS_PICKUP'] = (int) $seller->offers_pickup;
        $sales_total = '0';
        $sales_pending = '0';
        $product_json = array();
        $product_cards = '';
        foreach (PT_StoreSellerProducts($seller->id, null, 200) as $product) {
            $product_cards .= PT_LoadPage('store/partials/my-product-card', array(
                'P_ID'      => $product->id,
                'P_SLUG'    => htmlspecialchars($product->slug),
                'P_TITLE'   => htmlspecialchars($product->title),
                'P_KIND'    => $product->kind,
                'P_STATUS'  => $product->status,
                'P_SKU'     => htmlspecialchars((string) $product->sku),
                'P_IMAGE'   => htmlspecialchars(PT_StorePrimaryImage($product)),
                'P_CURRENCY' => $currency,
                'P_REJECT'  => !empty($product->reject_reason) ? PT_StoreRejectionReasonLabel($product->reject_reason) : '',
                'P_PRICE'   => PT_StoreMoney($product->price),
                'P_URL'     => PT_Link('product/' . $product->slug),
            ));
            $product_json[] = array(
                'id' => (int) $product->id, 'title' => $product->title, 'kind' => $product->kind,
                'product_type' => $product->product_type, 'category_id' => (int) $product->category_id,
                'price' => PT_StoreMoney($product->price), 'status' => $product->status,
            );
        }
        $data['MY_PRODUCTS_HTML'] = $product_cards;
        $data['PRODUCTS_JSON'] = json_encode($product_json);
        foreach (PT_StoreSellerOrders($user_id, 100) as $order) {
            $buyer = $db->where('id', $order->buyer_id)->getOne(T_USERS, array('id', 'username'));
            $data['SELLER_ORDERS_HTML'] .= PT_LoadPage('store/partials/order-row', array(
                'O_ID'          => $order->id,
                'O_NUMBER'      => htmlspecialchars($order->order_number),
                'O_URL'         => PT_Link('order/' . $order->order_number),
                'O_KIND'        => $order->kind,
                'O_STATUS'      => $order->status,
                'O_TOTAL'       => $currency . ' ' . PT_StoreMoney($order->total),
                'O_DATE'        => gmdate('d M Y', $order->created_at),
                'O_COUNTERPART' => htmlspecialchars(!empty($buyer) ? $buyer->username : ''),
                'O_SELLER_VIEW' => 1,
                'O_ACTIONS'     => PT_StoreSellerOrderActions($order),
            ));
            if (in_array($order->status, array('paid', 'closed'), true)) {
                $sales_total = PT_StakeAdd($sales_total, $order->seller_total);
            } else {
                $sales_pending = PT_StakeAdd($sales_pending, $order->seller_total);
            }
        }
        $data['SALES_TOTAL'] = PT_StoreMoney($sales_total);
        $data['SALES_PENDING'] = PT_StoreMoney($sales_pending);

        if ($seller->status === 'pending' || $seller->verification_status === 'submitted') {
            $data['STORE_BANNER'] = '<div class="store_review_banner is-review mb-4"><div><h5 class="mb-1">Your store is under review</h5><p class="mb-0 text-muted">We are checking your details. We will email you when it is approved or if anything needs changing.</p></div></div>';
        } elseif ($seller->verification_status === 'rejected' || $seller->status === 'rejected') {
            $reason = !empty($seller->rejection_reason) ? PT_StoreRejectionReasonLabel($seller->rejection_reason) : '';
            $data['STORE_BANNER'] = '<div class="store_review_banner is-rejected mb-4"><div><h5 class="mb-1">Your store needs changes</h5><p class="mb-1 text-muted">' . ($reason !== '' ? 'Reason: ' . htmlspecialchars($reason) : 'Please review your details.') . '</p><button type="button" class="btn btn-sm btn-main mt-2 store_verify_open">Resubmit documents</button></div></div>';
        }
    }
    $data['HAS_SELLER_TERMS'] = PT_StoreHasAcceptedTerms($user_id, 'seller') ? 1 : 0;
    $data['HAS_BUYER_TERMS'] = PT_StoreHasAcceptedTerms($user_id, 'buyer') ? 1 : 0;
    $categories = '';
    foreach (PT_StoreCategories() as $cat) {
        $categories .= '<option value="' . $cat->id . '" data-kind="' . $cat->kind . '">' . htmlspecialchars($cat->name) . '</option>';
    }
    $data['CATEGORIES_HTML'] = $categories;
    $data['PRODUCT_CATEGORIES'] = $categories;
    $data['LOCATIONS_HTML'] = PT_StoreLocationOptions($seller ? (int) $seller->location_id : 0);
    $addons = '<option value="0">No add-on</option>';
    foreach (PT_StoreAddons('both') as $a) {
        $addons .= '<option value="' . (int) $a->id . '">' . htmlspecialchars($a->name) . '</option>';
    }
    $data['ADDONS_HTML'] = $addons;
    $reasons = '';
    foreach (PT_StoreRejectionReasons('seller') as $r) {
        $reasons .= '<option value="' . htmlspecialchars($r->code) . '">' . htmlspecialchars($r->label) . '</option>';
    }
    $data['REJECTION_REASONS_HTML'] = $reasons;
    $terms_url = PT_StoreParam('store_terms_url', 'https://jackisa.com/terms/terms');
    $data['CURRENCY']        = $currency;
    $data['STORE_TERMS_URL'] = htmlspecialchars($terms_url);
    $data['REFUND_URL']      = htmlspecialchars(PT_StoreParam('store_refund_url', 'https://jackisa.com/terms/refund-policy'));
    $data['STREAM_TERMS_URL'] = htmlspecialchars($terms_url);
    $data['PRIVACY_URL']     = htmlspecialchars(PT_StoreParam('store_privacy_url', 'https://jackisa.com/terms/privacy-policy'));
    $data['REQUIRED_DOCS_JSON'] = json_encode(PT_StoreRequiredDocs($seller ? $seller->store_type : 'personal'));

    $zones = array();
    $pickups = array();
    $docs = array();
    $benefit = null;
    if (!empty($seller)) {
        try { $zrow = $db->where('seller_id', $seller->id)->orderBy('id', 'ASC')->get(T_STORE_DELIVERY_ZONES); } catch (Exception $e) { $zrow = array(); }
        foreach ($zrow as $z) {
            $zones[] = array('location_id' => (int) $z->location_id, 'base_fee' => PT_StoreMoney($z->base_fee), 'per_kg_fee' => PT_StoreMoney($z->per_kg_fee), 'free_over' => PT_StoreMoney($z->free_over));
        }
        try { $prow = $db->where('seller_id', $seller->id)->orderBy('id', 'ASC')->get(T_STORE_PICKUP_POINTS); } catch (Exception $e) { $prow = array(); }
        foreach ($prow as $p) {
            $pickups[] = array('name' => $p->name, 'address' => $p->address, 'contact_phone' => $p->contact_phone, 'fee' => PT_StoreMoney($p->fee), 'location_id' => (int) $p->location_id);
        }
        foreach (PT_StoreVerificationDocuments($seller->id) as $d) {
            $docs[] = array('doc_type' => $d->doc_type, 'status' => $d->status, 'file' => PT_Link($d->file), 'date' => gmdate('d M Y', $d->created_at));
        }
        $benefit = PT_StoreMembershipBenefit($seller->id);
    }
    $data['DELIVERY_ZONES_JSON'] = json_encode($zones);
    $data['PICKUP_POINTS_JSON'] = json_encode($pickups);
    $data['VERIFICATION_DOCS'] = json_encode($docs);
    $data['BENEFIT_JSON'] = json_encode(array(
        'free_delivery' => !empty($benefit) && !empty($benefit->free_delivery) ? 1 : 0,
        'discount_pct'  => !empty($benefit) ? PT_StoreMoney($benefit->discount_pct) : '0',
        'addon_id'      => !empty($benefit) ? (int) $benefit->addon_id : 0,
    ));
    $data['HAS_VERIFICATION_DOCS'] = empty($docs) ? 0 : 1;
    $locs = array();
    $loc_labels = PT_StoreLocationLabels();
    foreach (PT_StoreLocations() as $loc) {
        $locs[] = array('id' => (int) $loc->id, 'name' => isset($loc_labels[(int) $loc->id]) ? $loc_labels[(int) $loc->id] : $loc->name);
    }
    $data['LOCATIONS_JSON'] = json_encode($locs);
    return $data;
}

/** A seller's publicly browsable products/services. */
function PT_StoreSellerProducts($seller_id, $kind = null, $limit = 60, $offset = 0) {
    global $db;
    try {
        $q = $db->where('seller_id', (int) $seller_id)->where('status', 'active');
        if (!empty($kind) && in_array($kind, array('product', 'service'), true)) {
            $q = $q->where('kind', $kind);
        }
        return $q->orderBy('is_featured', 'DESC')->orderBy('id', 'DESC')->get(T_STORE_PRODUCTS, array((int) $offset, (int) $limit));
    } catch (Exception $e) {
        return array();
    }
}

/**
 * Everything a template needs to render one product: gallery, variants,
 * rating, active discount price, membership benefit and review list.
 */
function PT_StorePublicProduct($product, $buyer_id = 0) {
    global $db;
    if (empty($product)) {
        return null;
    }
    $seller = PT_StoreSellerById($product->seller_id);
    $member = $buyer_id ? PT_StoreBuyerIsMember($buyer_id, $product->user_id) : false;
    $images = PT_StoreProductImages($product->id);
    if (empty($images) && !empty($product->thumbnail)) {
        $images = array();
    }
    $seller_user = $db->where('id', $product->user_id)->getOne(T_USERS, array('id', 'username', 'first_name', 'last_name', 'avatar'));
    return array(
        'product'   => $product,
        'seller'    => $seller,
        'seller_user' => $seller_user,
        'images'    => $images,
        'variants'  => PT_StoreProductVariants($product->id),
        'rating'    => PT_StoreProductRating($product->id),
        'reviews'   => PT_StoreProductReviews($product->id),
        'price'     => PT_StoreEffectivePrice($product, $member),
        'base_price'=> PT_StakeDec($product->price),
        'member'    => $member,
        'benefit'   => PT_StoreMembershipBenefit($product->seller_id),
        'attributes'=> PT_StoreProductAttributes($product),
        'addon'     => !empty($product->member_addon_id) ? PT_StoreAddon($product->member_addon_id) : null,
        'categories'=> PT_StoreCategories(),
    );
}

/** A buyer may review a product once their order/booking settled. */
function PT_StoreBuyerCanReview($buyer_id, $product_id) {
    global $db;
    try {
        $order = $db->rawQueryOne(
            "SELECT o.id FROM `" . T_STORE_ORDERS . "` o JOIN `" . T_STORE_ORDER_ITEMS . "` i ON i.order_id = o.id
             WHERE o.buyer_id = ? AND i.product_id = ? AND o.status IN ('paid','closed','refunded') LIMIT 1",
            array((int) $buyer_id, (int) $product_id)
        );
    } catch (Exception $e) {
        return false;
    }
    return !empty($order);
}

function PT_StoreSaveReview($buyer_id, $product_id, $rating, $title, $body) {
    global $db;
    $product = PT_StoreProduct($product_id);
    if (empty($product)) {
        return array('status' => false, 'message' => 'Product not found.');
    }
    if (!PT_StoreHasAcceptedTerms($buyer_id, 'buyer')) {
        return array('status' => false, 'message' => 'Accept the Store policies first.');
    }
    if (!PT_StoreBuyerCanReview($buyer_id, $product_id)) {
        return array('status' => false, 'message' => 'You can review this after a completed order.');
    }
    $rating = max(1, min(5, (int) $rating));
    $order = $db->rawQueryOne(
        "SELECT o.id FROM `" . T_STORE_ORDERS . "` o JOIN `" . T_STORE_ORDER_ITEMS . "` i ON i.order_id = o.id
         WHERE o.buyer_id = ? AND i.product_id = ? AND o.status IN ('paid','closed','refunded') ORDER BY o.id DESC LIMIT 1",
        array((int) $buyer_id, (int) $product_id)
    );
    $order_id = !empty($order) ? (int) $order->id : 0;
    try {
        $existing = $db->where('order_id', $order_id)->where('product_id', (int) $product_id)->where('user_id', (int) $buyer_id)->getOne(T_STORE_REVIEWS);
        if (empty($existing)) {
            $db->insert(T_STORE_REVIEWS, array(
                'product_id' => (int) $product_id,
                'seller_id'  => (int) $product->seller_id,
                'user_id'    => (int) $buyer_id,
                'order_id'   => $order_id,
                'rating'     => $rating,
                'title'      => substr((string) $title, 0, 200),
                'body'       => substr((string) $body, 0, 4000),
                'status'     => 'published',
                'created_at' => time(),
                'updated_at' => time(),
            ));
        }
    } catch (Exception $e) {
        return array('status' => false, 'message' => 'Could not save the review.');
    }
    PT_StoreRecalcProductRating($product_id);
    return array('status' => true);
}

function PT_StoreRecalcProductRating($product_id) {
    global $db;
    try {
        $row = $db->rawQueryOne("SELECT COUNT(*) AS c, COALESCE(SUM(rating),0) AS s FROM `" . T_STORE_REVIEWS . "` WHERE product_id = ? AND status = 'published'", array((int) $product_id));
        if (!empty($row)) {
            PT_DbGuardedRun(function () use ($db, $product_id, $row) {
                $db->where('id', (int) $product_id)->update(T_STORE_PRODUCTS, array('rating_count' => (int) $row->c, 'rating_sum' => (int) $row->s));
            });
        }
    } catch (Exception $e) {
    }
}

function PT_StoreBuyerOrders($buyer_id, $limit = 50) {
    global $db;
    try {
        return $db->where('buyer_id', (int) $buyer_id)->orderBy('id', 'DESC')->get(T_STORE_ORDERS, (int) $limit);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreSellerOrders($seller_user_id, $limit = 100) {
    global $db;
    try {
        return $db->where('seller_user_id', (int) $seller_user_id)->orderBy('id', 'DESC')->get(T_STORE_ORDERS, (int) $limit);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreOrdersForDocument($order_id) {
    global $db;
    try {
        return $db->where('order_id', (int) $order_id)->orderBy('id', 'ASC')->get(T_STORE_DOCUMENTS);
    } catch (Exception $e) {
        return array();
    }
}

// =========================================================================
//  Seller catalog writers
// =========================================================================

function PT_StoreSaveProduct($seller_user_id, $data, $product_id = 0) {
    global $db, $pt;
    $seller = PT_StoreSellerByUser($seller_user_id);
    if (empty($seller)) {
        return array('status' => false, 'message' => 'Create your store first.');
    }
    if (!PT_StoreHasAcceptedTerms($seller_user_id, 'seller')) {
        return array('status' => false, 'message' => 'Accept the Store policies before adding merchandise.');
    }
    $title = trim(isset($data['title']) ? $data['title'] : '');
    if ($title === '') {
        return array('status' => false, 'message' => 'A title is required.');
    }
    $kind = (isset($data['kind']) && $data['kind'] === 'service') ? 'service' : 'product';
    $category = !empty($data['category_id']) ? PT_StoreCategory((int) $data['category_id']) : null;
    $requires_approval = PT_StoreParam('store_require_product_approval', '1') === '1' ? 1 : 0;
    if (!empty($category) && !empty($category->requires_approval)) {
        $requires_approval = 1;
    }
    $status = (!empty($data['status']) && in_array($data['status'], array('draft', 'pending', 'active', 'archived'), true))
        ? $data['status']
        : ($requires_approval ? 'pending' : 'active');
    $price = PT_StakeDec(isset($data['price']) ? $data['price'] : 0);
    $discount_type = (isset($data['discount_type']) && in_array($data['discount_type'], array('none', 'percent', 'amount'), true)) ? $data['discount_type'] : 'none';

    // SKU is system-generated and globally unique; a seller never types it.
    // A product keeps the SKU it was first assigned.
    $existing = ((int) $product_id > 0) ? PT_StoreProduct($product_id) : null;
    if (!empty($existing) && !empty($existing->sku)) {
        $sku = $existing->sku;
    } else {
        $sku = PT_StoreGenerateSku($kind);
    }

    // Structured, category-specific attributes.
    $attributes = null;
    if (isset($data['attributes'])) {
        $attributes = is_array($data['attributes']) ? json_encode($data['attributes']) : (string) $data['attributes'];
    }

    // Subscriber benefits are configured once in Store settings and inherited
    // by every listing, so a seller is never asked the same question again.
    // A per-listing override is still accepted when explicitly supplied.
    $benefit = PT_StoreMembershipBenefit($seller->id);
    $member_addon_id = 0;
    if (!empty($data['member_addon_id'])) {
        $addon = PT_StoreAddon((int) $data['member_addon_id']);
        // The add-on must match the listing kind.
        if (!empty($addon) && ($addon->kind === 'both' || $addon->kind === $kind)) {
            $member_addon_id = (int) $addon->id;
        }
    } elseif (!empty($benefit) && !empty($benefit->addon_id)) {
        $addon = PT_StoreAddon((int) $benefit->addon_id);
        if (!empty($addon) && ($addon->kind === 'both' || $addon->kind === $kind)) {
            $member_addon_id = (int) $addon->id;
        }
    }
    $member_discount_pct = array_key_exists('member_discount_pct', $data)
        ? PT_StakeDec($data['member_discount_pct'])
        : (!empty($benefit) ? PT_StakeDec($benefit->discount_pct) : PT_StakeDec(0));
    $member_free_delivery = array_key_exists('member_free_delivery', $data)
        ? (!empty($data['member_free_delivery']) ? 1 : 0)
        : ((!empty($benefit) && !empty($benefit->free_delivery)) ? 1 : 0);

    $fields = array(
        'seller_id'      => (int) $seller->id,
        'user_id'        => (int) $seller_user_id,
        'category_id'    => (int) (isset($data['category_id']) ? $data['category_id'] : 0),
        'kind'           => $kind,
        'product_type'   => ($kind === 'service') ? 'service' : ((isset($data['product_type']) && $data['product_type'] === 'digital') ? 'digital' : 'physical'),
        'title'          => substr($title, 0, 200),
        'slug'           => PT_StoreSlug($title, (int) $product_id),
        'description'    => isset($data['description']) ? $data['description'] : '',
        'sku'            => $sku,
        'price'          => $price,
        'compare_price'  => PT_StakeDec(isset($data['compare_price']) ? $data['compare_price'] : 0),
        'currency'       => PT_StoreCurrency(),
        'tax_inclusive'  => !empty($data['tax_inclusive']) ? 1 : 0,
        'discount_type'  => $discount_type,
        'discount_value' => PT_StakeDec(isset($data['discount_value']) ? $data['discount_value'] : 0),
        'discount_start' => !empty($data['discount_start']) ? (int) strtotime($data['discount_start']) : 0,
        'discount_end'   => !empty($data['discount_end']) ? (int) strtotime($data['discount_end']) : 0,
        'track_stock'    => !empty($data['track_stock']) ? 1 : 0,
        'stock'          => (int) (isset($data['stock']) ? $data['stock'] : 0),
        'weight_grams'   => max(0, (int) (isset($data['weight_grams']) ? $data['weight_grams'] : 0)),
        'delivery_fee'   => PT_StakeDec(isset($data['delivery_fee']) ? $data['delivery_fee'] : 0),
        'delivery_note'  => substr(isset($data['delivery_note']) ? $data['delivery_note'] : '', 0, 500),
        'delivery_mode'  => (isset($data['delivery_mode']) && in_array($data['delivery_mode'], array('deliver', 'pickup', 'both', 'none'), true)) ? $data['delivery_mode'] : 'deliver',
        'service_location_mode' => (isset($data['service_location_mode']) && in_array($data['service_location_mode'], array('at_provider', 'at_customer', 'online'), true)) ? $data['service_location_mode'] : '',
        'status'         => $status,
        'requires_approval' => $requires_approval,
        'booking_duration_minutes' => (int) (isset($data['booking_duration_minutes']) ? $data['booking_duration_minutes'] : 60),
        'booking_capacity' => (int) (isset($data['booking_capacity']) ? $data['booking_capacity'] : 1),
        'booking_buffer_minutes' => (int) (isset($data['booking_buffer_minutes']) ? $data['booking_buffer_minutes'] : 0),
        'member_free_delivery' => $member_free_delivery,
        'member_discount_pct'  => $member_discount_pct,
        'member_addon_id'      => $member_addon_id,
        'member_addon'   => '',
        'attributes'     => $attributes,
        'updated_at'     => time(),
    );
    if ($kind === 'service') {
        $fields['track_stock'] = 0;
        $fields['product_type'] = 'service';
    }
    if ((int) $product_id > 0) {
        if (empty($existing) || (int) $existing->user_id !== (int) $seller_user_id) {
            return array('status' => false, 'message' => 'You do not own that listing.');
        }
        $db->where('id', (int) $product_id)->update(T_STORE_PRODUCTS, $fields);
        $id = (int) $product_id;
    } else {
        $fields['created_at'] = time();
        $id = $db->insert(T_STORE_PRODUCTS, $fields);
    }
    if (empty($id)) {
        return array('status' => false, 'message' => 'Could not save the listing.');
    }
    if (array_key_exists('images', $data) && is_array($data['images'])) {
        $db->where('product_id', $id)->delete(T_STORE_PRODUCT_IMAGES);
        $i = 0;
        foreach ($data['images'] as $image) {
            $image = trim((string) $image);
            if ($image === '') {
                continue;
            }
            $db->insert(T_STORE_PRODUCT_IMAGES, array('product_id' => $id, 'image' => $image, 'sort_order' => $i++, 'created_at' => time()));
        }
    }
    if (isset($data['variants']) && is_array($data['variants'])) {
        PT_StoreSaveVariants($id, $data['variants']);
    }
    if ($kind === 'service' && !empty($data['slots']) && is_array($data['slots'])) {
        PT_StoreSaveSlots($id, $data['slots']);
    }
    return array('status' => true, 'product_id' => $id, 'listing_status' => $status, 'sku' => $sku, 'requires_approval' => $requires_approval);
}

/** Replace a seller's delivery zones in one go. */
function PT_StoreSaveDeliveryZones($seller_id, $zones) {
    global $db;
    try {
        $db->where('seller_id', (int) $seller_id)->delete(T_STORE_DELIVERY_ZONES);
    } catch (Exception $e) {
        return false;
    }
    foreach ((array) $zones as $zone) {
        $location_id = (int) (isset($zone['location_id']) ? $zone['location_id'] : 0);
        $base = PT_StakeDec(isset($zone['base_fee']) ? $zone['base_fee'] : 0);
        if ($location_id <= 0 && PT_StakeCmp($base, '0') <= 0) {
            continue;
        }
        $db->insert(T_STORE_DELIVERY_ZONES, array(
            'seller_id'    => (int) $seller_id,
            'location_id'  => $location_id,
            'label'        => substr(isset($zone['label']) ? $zone['label'] : '', 0, 150),
            'base_fee'     => $base,
            'per_kg_fee'   => PT_StakeDec(isset($zone['per_kg_fee']) ? $zone['per_kg_fee'] : 0),
            'min_fee'      => PT_StakeDec(isset($zone['min_fee']) ? $zone['min_fee'] : 0),
            'max_fee'      => PT_StakeDec(isset($zone['max_fee']) ? $zone['max_fee'] : 0),
            'free_over'    => PT_StakeDec(isset($zone['free_over']) ? $zone['free_over'] : 0),
            'eta_min_days' => max(0, (int) (isset($zone['eta_min_days']) ? $zone['eta_min_days'] : 1)),
            'eta_max_days' => max(0, (int) (isset($zone['eta_max_days']) ? $zone['eta_max_days'] : 3)),
            'is_active'    => 1,
            'created_at'   => time(),
            'updated_at'   => time(),
        ));
    }
    return true;
}

/** Replace a seller's pickup points in one go. */
function PT_StoreSavePickupPoints($seller_id, $points) {
    global $db;
    try {
        $db->where('seller_id', (int) $seller_id)->delete(T_STORE_PICKUP_POINTS);
    } catch (Exception $e) {
        return false;
    }
    foreach ((array) $points as $point) {
        $name = trim(isset($point['name']) ? $point['name'] : '');
        if ($name === '') {
            continue;
        }
        $db->insert(T_STORE_PICKUP_POINTS, array(
            'seller_id'     => (int) $seller_id,
            'location_id'   => (int) (isset($point['location_id']) ? $point['location_id'] : 0),
            'name'          => substr($name, 0, 150),
            'address'       => substr(isset($point['address']) ? $point['address'] : '', 0, 300),
            'contact_phone' => substr(isset($point['contact_phone']) ? $point['contact_phone'] : '', 0, 50),
            'fee'           => PT_StakeDec(isset($point['fee']) ? $point['fee'] : 0),
            'is_active'     => 1,
            'created_at'    => time(),
        ));
    }
    return true;
}

function PT_StoreSaveVariants($product_id, $variants) {
    global $db;
    try {
        $db->where('product_id', (int) $product_id)->delete(T_STORE_PRODUCT_VARIANTS);
    } catch (Exception $e) {
        return false;
    }
    foreach ($variants as $variant) {
        $name = trim(isset($variant['name']) ? $variant['name'] : '');
        if ($name === '') {
            continue;
        }
        $db->insert(T_STORE_PRODUCT_VARIANTS, array(
            'product_id'  => (int) $product_id,
            'name'        => substr($name, 0, 150),
            'options'     => !empty($variant['options']) ? (is_array($variant['options']) ? json_encode($variant['options']) : $variant['options']) : null,
            'sku'         => substr(isset($variant['sku']) ? $variant['sku'] : '', 0, 80),
            'price_delta' => PT_StakeDec(isset($variant['price_delta']) ? $variant['price_delta'] : 0),
            'stock'       => (int) (isset($variant['stock']) ? $variant['stock'] : 0),
            'is_active'   => 1,
            'created_at'  => time(),
        ));
    }
    return true;
}

function PT_StoreSaveSlots($product_id, $slots) {
    global $db;
    try {
        $db->where('product_id', (int) $product_id)->delete(T_STORE_PRODUCT_SLOTS);
    } catch (Exception $e) {
        return false;
    }
    foreach ($slots as $slot) {
        $weekday = (int) (isset($slot['weekday']) ? $slot['weekday'] : 1);
        if ($weekday < 0 || $weekday > 6) {
            continue;
        }
        $db->insert(T_STORE_PRODUCT_SLOTS, array(
            'product_id' => (int) $product_id,
            'weekday'    => $weekday,
            'start_time' => !empty($slot['start_time']) ? $slot['start_time'] : '09:00:00',
            'end_time'   => !empty($slot['end_time']) ? $slot['end_time'] : '10:00:00',
            'capacity'   => max(1, (int) (isset($slot['capacity']) ? $slot['capacity'] : 1)),
            'is_active'  => 1,
        ));
    }
    return true;
}

function PT_StoreDeleteProduct($seller_user_id, $product_id) {
    global $db;
    $product = PT_StoreProduct($product_id);
    if (empty($product) || (int) $product->user_id !== (int) $seller_user_id) {
        return false;
    }
    // Archived rather than deleted: products are referenced by immutable orders.
    $db->where('id', (int) $product_id)->update(T_STORE_PRODUCTS, array('status' => 'archived', 'updated_at' => time()));
    return true;
}

function PT_StoreSetProductStatus($product_id, $status, $reason = '', $by_user = 0) {
    global $db;
    if (!in_array($status, array('active', 'rejected', 'pending', 'archived', 'draft'), true)) {
        return false;
    }
    $product = PT_StoreProduct($product_id);
    if (empty($product)) {
        return false;
    }
    $db->where('id', (int) $product_id)->update(T_STORE_PRODUCTS, array(
        'status' => $status,
        'reject_reason' => substr((string) $reason, 0, 500),
        'reviewed_at' => time(),
        'reviewed_by' => (int) $by_user,
        'updated_at' => time(),
    ));
    // Tell the seller what happened so a hidden listing never puzzles them.
    if ($status === 'active') {
        PT_StoreNotifyUser($product->user_id, 'seller', 0, 'product_approved', 'Listing approved', '"' . $product->title . '" is now live in your store.');
    } elseif ($status === 'rejected') {
        PT_StoreNotifyUser($product->user_id, 'seller', 0, 'product_rejected', 'Listing not approved', '"' . $product->title . '" was not approved. Reason: ' . PT_StoreRejectionReasonLabel($reason) . '.');
    }
    PT_StoreEmailProductStatus($product, $status, $reason);
    return true;
}

/** Email a seller about a listing review outcome. */
function PT_StoreEmailProductStatus($product, $status, $reason_code = '') {
    global $db;
    if (empty($product)) {
        return false;
    }
    $user = $db->where('id', $product->user_id)->getOne(T_USERS, array('email', 'username'));
    if (empty($user) || empty($user->email)) {
        return false;
    }
    if ($status === 'active') {
        $subject = 'Jackisa Store - listing approved';
        $body = '<p>Your listing <b>' . htmlspecialchars($product->title) . '</b> is now live.</p>';
    } elseif ($status === 'rejected') {
        $subject = 'Jackisa Store - listing needs changes';
        $body = '<p>Your listing <b>' . htmlspecialchars($product->title) . '</b> was not approved.</p><p><b>Reason:</b> ' . htmlspecialchars(PT_StoreRejectionReasonLabel($reason_code)) . '</p>';
    } else {
        return false;
    }
    return PT_StoreSendMail($user->email, $subject, $body, $user->username);
}

// =========================================================================
//  Admin helpers
// =========================================================================

function PT_StoreAdminStats() {
    global $db;
    $stats = array('orders' => 0, 'revenue' => '0', 'commission' => '0', 'sellers' => 0, 'products' => 0, 'pending_products' => 0, 'paid_orders' => 0);
    try {
        $stats['sellers'] = (int) $db->getValue(T_STORE_SELLERS, 'COUNT(*)');
        $stats['products'] = (int) $db->where('kind', 'product')->getValue(T_STORE_PRODUCTS, 'COUNT(*)');
        $stats['pending_products'] = (int) $db->where('status', 'pending')->getValue(T_STORE_PRODUCTS, 'COUNT(*)');
        $stats['orders'] = (int) $db->getValue(T_STORE_ORDERS, 'COUNT(*)');
        $stats['paid_orders'] = (int) $db->where('status', 'paid')->getValue(T_STORE_ORDERS, 'COUNT(*)');
        $stats['revenue'] = (string) $db->getValue(T_STORE_ORDERS, 'SUM(paid_total)');
        $stats['commission'] = (string) $db->getValue(T_STORE_EARNINGS, 'SUM(commission)');
    } catch (Exception $e) {
    }
    if ($stats['revenue'] === '' || $stats['revenue'] === null) {
        $stats['revenue'] = '0';
    }
    if ($stats['commission'] === '' || $stats['commission'] === null) {
        $stats['commission'] = '0';
    }
    return $stats;
}

function PT_StoreSetCategoryRate($category_id, $commission_bps, $requires_return, $return_days) {
    global $db;
    $min = (int) PT_StoreParam('store_commission_min_bps', '300');
    $max = (int) PT_StoreParam('store_commission_max_bps', '1500');
    $commission_bps = max($min, min($max, (int) $commission_bps));
    $db->where('id', (int) $category_id)->update(T_STORE_CATEGORIES, array(
        'commission_bps'  => $commission_bps,
        'requires_return' => $requires_return ? 1 : 0,
        'return_days'     => max(0, (int) $return_days),
    ));
    return true;
}

// =========================================================================
//  Attachments (merch on a video / live / article)
// =========================================================================

function PT_StoreAttachProduct($product_id, $target_type, $target_id, $seller_id = 0, $sort_order = 0) {
    global $db;
    $product = PT_StoreProduct($product_id);
    if (empty($product)) {
        return false;
    }
    $target_type = in_array($target_type, array('video', 'live', 'article'), true) ? $target_type : 'video';
    $target_id = (int) $target_id;
    if ($target_id <= 0) {
        return false;
    }
    try {
        $existing = $db->where('product_id', (int) $product_id)
                       ->where('target_type', $target_type)
                       ->where('target_id', $target_id)
                       ->getOne(T_STORE_ATTACHMENTS);
        if (!empty($existing)) {
            return true;
        }
        $db->insert(T_STORE_ATTACHMENTS, array(
            'product_id'  => (int) $product_id,
            'seller_id'   => (int) ($seller_id ?: $product->seller_id),
            'target_type' => $target_type,
            'target_id'   => $target_id,
            'sort_order'  => (int) $sort_order,
            'created_at'  => time(),
        ));
    } catch (Exception $e) {
        return false;
    }
    return true;
}

/** Products attached to a piece of content, active only, seller-approved. */
function PT_StoreAttachmentsFor($target_type, $target_id, $limit = 8) {
    global $db;
    $target_type = in_array($target_type, array('video', 'live', 'article'), true) ? $target_type : 'video';
    try {
        $rows = $db->where('target_type', $target_type)->where('target_id', (int) $target_id)->orderBy('sort_order', 'ASC')->get(T_STORE_ATTACHMENTS, (int) $limit);
    } catch (Exception $e) {
        return array();
    }
    $out = array();
    foreach ($rows as $row) {
        $product = PT_StoreProduct($row->product_id);
        if (!empty($product) && $product->status === 'active') {
            $out[] = $product;
        }
    }
    return $out;
}

/** Remove an attachment, only for the owning seller. */
function PT_StoreDetachProduct($user_id, $product_id, $target_type, $target_id) {
    global $db;
    $product = PT_StoreProduct($product_id);
    if (empty($product) || (int) $product->user_id !== (int) $user_id) {
        return false;
    }
    $target_type = in_array($target_type, array('video', 'live', 'article'), true) ? $target_type : 'video';
    try {
        $db->where('product_id', (int) $product_id)->where('target_type', $target_type)->where('target_id', (int) $target_id)->delete(T_STORE_ATTACHMENTS);
    } catch (Exception $e) {
        return false;
    }
    return true;
}

/** Primary/thumbnail image URL for a product (first gallery image). */
function PT_StorePrimaryImage($product) {
    $images = PT_StoreProductImages($product->id);
    if (!empty($images)) {
        return $images[0]->image;
    }
    return '';
}

/** Seller-side action buttons for an order row. */
function PT_StoreSellerOrderActions($order) {
    $buttons = '';
    // Goods walk the fulfilment ladder; a booking (service) skips the shipping
    // steps and is marked paid once it has been provided.
    $is_booking = isset($order->kind) && $order->kind === 'booking';
    if ($is_booking) {
        $next = array(
            'pending'   => array('confirmed', 'Confirm'),
            'confirmed' => array('approved', 'Mark ready'),
            'approved'  => array('paid', 'Mark provided'),
        );
    } else {
        $next = array(
            'pending'   => array('confirmed', 'Confirm'),
            'confirmed' => array('approved', 'Mark ready'),
            'approved'  => array('shipped', 'Mark shipped'),
            'shipped'   => array('delivered', 'Mark delivered'),
            'delivered' => array('paid', 'Mark paid'),
        );
    }
    if (isset($next[$order->status])) {
        $buttons .= '<button type="button" class="btn btn-sm btn-main store-order-status" data-order="' . $order->id . '" data-status="' . $next[$order->status][0] . '">' . $next[$order->status][1] . '</button> ';
    }
    if ($order->status !== 'closed' && $order->status !== 'cancelled') {
        $buttons .= '<a class="btn btn-sm btn-main-outline" href="' . PT_Link('order/' . $order->order_number) . '">View</a>';
    }
    return $buttons;
}

/** Buyer-side action buttons for an order row. */
function PT_StoreBuyerOrderActions($order) {
    $buttons = '<a class="btn btn-sm btn-main-outline" href="' . PT_Link('order/' . $order->order_number) . '">View</a>';
    if (!in_array($order->status, array('paid', 'closed', 'cancelled', 'refunded'), true)) {
        // Carry the full breakdown (raw amounts) so the pay dialog itemises the
        // order instead of showing the charged amount as the subtotal.
        $fee = PT_StoreJPesaFeeSplit($order->total);
        $buttons .= ' <button type="button" class="btn btn-sm btn-light store-pay" data-order="' . $order->id
            . '" data-number="' . htmlspecialchars($order->order_number) . '"'
            . ' data-subtotal="' . PT_StoreMoney($order->subtotal) . '"'
            . ' data-discount="' . PT_StoreMoney($order->discount_total) . '"'
            . ' data-delivery="' . PT_StoreMoney($order->delivery_total) . '"'
            . ' data-tax="' . PT_StoreMoney($order->tax_total) . '"'
            . ' data-fee="' . PT_StoreMoney($fee['fee']) . '"'
            . ' data-total="' . PT_StoreMoney($order->total)
            . '" data-charged="' . PT_StoreMoney($fee['charged']) . '">Pay</button>';
    }
    return $buttons;
}

/** Basket icon used on the merch cards' CTA buttons. */
function PT_StoreBasketIcon() {
    return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 6h18l-1.5 9.5a2 2 0 0 1-2 1.5H6.5a2 2 0 0 1-2-1.5L3 6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 6a4 4 0 0 1 8 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M9 11v3M15 11v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
}

/**
 * Designed product cards for a merch strip attached to a video, live or
 * article. Each card offers Book (services) or Order (products) with a basket
 * icon, matching the rest of the playtag theme.
 */
function PT_StoreMerchHTML($target_type, $target_id) {
    global $db;
    if (!function_exists('PT_StoreAttachmentsFor') || !PT_StoreEnabled()) {
        return '';
    }
    $target_type = in_array($target_type, array('video', 'live', 'article'), true) ? $target_type : 'video';
    $products = PT_StoreAttachmentsFor($target_type, $target_id, 8);
    if (empty($products)) {
        return '';
    }
    $currency = PT_StoreCurrencySymbol();
    $cards = '';
    foreach ($products as $product) {
        $is_service = $product->kind === 'service';
        $seller = PT_StoreSellerById($product->seller_id);
        $img = PT_StorePrimaryImage($product);
        $href = PT_Link('product/' . $product->slug) . '?target_type=' . $target_type . '&target_id=' . (int) $target_id;
        $cards .= '<div class="store_merch_item content">'
            . '<a class="store_merch_thumb" href="' . $href . '" data-load="?link1=product&id=' . $product->slug . '&target_type=' . $target_type . '&target_id=' . (int) $target_id . '">'
            . ($img !== '' ? '<img src="' . htmlspecialchars($img) . '" alt="' . htmlspecialchars($product->title) . '" loading="lazy">' : '<span class="store_card_noimg">' . PT_StoreBasketIcon() . '</span>')
            . ($is_service ? '<span class="store_card_flag">Service</span>' : '')
            . '</a>'
            . '<div class="store_merch_meta">'
            . (!empty($seller) ? '<a class="store_merch_seller" href="' . PT_Link('timeline/' . $seller->store_slug . '/store') . '">' . htmlspecialchars($seller->store_name) . '</a>' : '')
            . '<a class="store_merch_title black bold" href="' . $href . '" data-load="?link1=product&id=' . $product->slug . '&target_type=' . $target_type . '&target_id=' . (int) $target_id . '">' . htmlspecialchars($product->title) . '</a>'
            . '<div class="store_merch_price main bold">' . $currency . ' ' . PT_StoreMoney(PT_StoreEffectivePrice($product, false)) . '</div>'
            . '<div class="store_merch_cta">'
            . '<a class="btn btn-sm btn-main" href="' . $href . '" data-load="?link1=product&id=' . $product->slug . '&target_type=' . $target_type . '&target_id=' . (int) $target_id . '">' . PT_StoreBasketIcon() . '<span>' . ($is_service ? 'Book' : 'Order') . '</span></a>'
            . '</div></div></div>';
    }
    return '<div class="store_merch mb-3"><div class="store_merch_head d-flex align-items-center justify-content-between mb-2"><span class="bold">' . ($target_type === 'article' ? 'Products in this article' : 'Shop this ' . $target_type) . '</span></div><div class="store_merch_row">' . $cards . '</div></div>';
}

/** Backwards-compatible wrapper for the watch page. */
function PT_StoreWatchMerch($video) {
    if (empty($video)) {
        return '';
    }
    return PT_StoreMerchHTML('video', $video->id);
}

/** True when $user_id owns the video/live/article the merch is attached to. */
function PT_StoreOwnsTarget($user_id, $target_type, $target_id) {
    global $db;
    $user_id = (int) $user_id;
    $target_id = (int) $target_id;
    if ($user_id <= 0 || $target_id <= 0) {
        return false;
    }
    try {
        if ($target_type === 'article') {
            return (int) $db->where('id', $target_id)->where('user_id', $user_id)->getValue(T_POSTS, 'COUNT(*)') > 0;
        }
        return (int) $db->where('id', $target_id)->where('user_id', $user_id)->getValue(T_VIDEOS, 'COUNT(*)') > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Render the seller's merch picker for an editor (video, live or article).
 * Returns '' when Store is off or the editor's owner has no active listings,
 * so callers can include it unconditionally. Checkboxes carry the product id;
 * the surrounding form is serialised by store.js and posted to attach_publish.
 */
function PT_StoreEditorAttach($target_type, $target_id, $user_id = 0) {
    global $pt;
    if (!function_exists('PT_StoreEnabled') || !PT_StoreEnabled()) {
        return '';
    }
    $user_id = $user_id ? (int) $user_id : (int) (IS_LOGGED ? $pt->user->id : 0);
    if ($user_id <= 0 || !PT_StoreOwnsTarget($user_id, $target_type, $target_id)) {
        return '';
    }
    $seller = PT_StoreSellerByUser($user_id);
    if (empty($seller)) {
        return '';
    }
    $products = PT_StoreSellerProducts($seller->id, null, 200);
    if (empty($products)) {
        return '';
    }
    $attached = array();
    foreach (PT_StoreAttachmentsFor($target_type, $target_id, 100) as $p) {
        $attached[(int) $p->id] = true;
    }
    $currency = PT_StoreCurrencySymbol();
    $rows = '';
    foreach ($products as $product) {
        $checked = isset($attached[(int) $product->id]) ? ' checked' : '';
        $rows .= '<label class="store_attach_row d-flex align-items-center justify-content-between p-2 mb-1">'
            . '<span class="d-flex align-items-center"><input type="checkbox" class="store_attach_check mr-2" value="' . (int) $product->id . '"' . $checked . '>'
            . '<span class="black">' . htmlspecialchars($product->title) . '</span></span>'
            . '<span class="small text-muted">' . ($product->kind === 'service' ? 'Service' : 'Product') . ' &middot; ' . $currency . ' ' . PT_StoreMoney(PT_StoreEffectivePrice($product, false)) . '</span></label>';
    }
    return '<div class="content p-3 mb-3 store_attach" data-target-type="' . htmlspecialchars($target_type) . '" data-target-id="' . (int) $target_id . '">'
        . '<div class="d-flex align-items-center justify-content-between mb-2"><span class="bold">Attach merchandise</span>'
        . '<a href="' . PT_Link('store?tab=myshop') . '" class="small">Manage store</a></div>'
        . $rows
        . '<button type="button" class="btn btn-sm btn-main mt-2 store_attach_publish">Save merch</button></div>'
        . '<script src="' . $pt->config->theme_url . '/js/store.js?version=' . $pt->config->version . '"></script>';
}

// =========================================================================
//  Double-entry ledger
// =========================================================================

/**
 * Post a balanced journal. $lines is a list of
 *   array('account' => code, 'user_id' => 0, 'debit' => '0', 'credit' => '0', 'memo' => '')
 * The journal is rejected unless debits equal credits to 10 dp.
 * Returns the txn_id on success, false otherwise.
 */
function PT_StoreLedgerPost($lines, $meta = array()) {
    global $db, $pt;
    if (empty($lines) || !is_array($lines)) {
        return false;
    }
    $totalDebit = PT_StakeDec(0);
    $totalCredit = PT_StakeDec(0);
    foreach ($lines as $line) {
        $totalDebit = PT_StakeAdd($totalDebit, isset($line['debit']) ? $line['debit'] : 0);
        $totalCredit = PT_StakeAdd($totalCredit, isset($line['credit']) ? $line['credit'] : 0);
    }
    if (PT_StakeCmp($totalDebit, $totalCredit) !== 0) {
        PT_StoreLog('unbalanced journal rejected: debit=' . $totalDebit . ' credit=' . $totalCredit);
        return false;
    }
    $txn_id = !empty($meta['txn_id']) ? $meta['txn_id'] : PT_StoreTxnId();
    $currency = !empty($meta['currency']) ? $meta['currency'] : PT_StoreCurrency();
    $order_id = !empty($meta['order_id']) ? (int) $meta['order_id'] : 0;
    $document_id = !empty($meta['document_id']) ? (int) $meta['document_id'] : 0;
    $created_by = !empty($meta['created_by']) ? (int) $meta['created_by'] : 0;
    $now = time();
    foreach ($lines as $line) {
        $db->insert(T_STORE_LEDGER_ENTRIES, array(
            'txn_id'       => $txn_id,
            'account_code' => $line['account'],
            'user_id'      => isset($line['user_id']) ? (int) $line['user_id'] : 0,
            'order_id'     => $order_id,
            'document_id'  => $document_id,
            'debit'        => PT_StakeDec(isset($line['debit']) ? $line['debit'] : 0),
            'credit'       => PT_StakeDec(isset($line['credit']) ? $line['credit'] : 0),
            'currency'     => $currency,
            'memo'         => isset($line['memo']) ? substr($line['memo'], 0, 500) : '',
            'created_by'   => $created_by,
            'created_at'   => $now,
        ));
    }
    return $txn_id;
}

function PT_StoreTxnId() {
    return PT_StoreRef('STX');
}

/** Net balance of an account (debit - credit) for one user or the whole account. */
function PT_StoreLedgerBalance($account_code, $user_id = null) {
    global $db;
    try {
        if ($user_id === null) {
            $row = $db->rawQueryOne("SELECT COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c FROM `" . T_STORE_LEDGER_ENTRIES . "` WHERE `account_code` = ?", array($account_code));
        } else {
            $row = $db->rawQueryOne("SELECT COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c FROM `" . T_STORE_LEDGER_ENTRIES . "` WHERE `account_code` = ? AND `user_id` = ?", array($account_code, (int) $user_id));
        }
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    if (empty($row)) {
        return PT_StakeDec(0);
    }
    return PT_StakeSub($row->d, $row->c);
}

/** Everything a buyer owes (their accounts-receivable debit balance). */
function PT_StoreBuyerBalance($user_id) {
    return PT_StoreLedgerBalance('1100', $user_id);
}

function PT_StoreFlagTransaction($txn_id, $is_error, $reason, $by) {
    global $db;
    $existing = $db->where('txn_id', $txn_id)->getOne(T_STORE_LEDGER_FLAGS);
    $data = array(
        'is_error'   => $is_error ? 1 : 0,
        'reason'     => substr((string) $reason, 0, 500),
        'flagged_by' => (int) $by,
        'flagged_at' => time(),
    );
    if (!empty($existing)) {
        return (bool) $db->where('id', $existing->id)->update(T_STORE_LEDGER_FLAGS, $data);
    }
    $data['txn_id'] = $txn_id;
    return (bool) $db->insert(T_STORE_LEDGER_FLAGS, $data);
}

function PT_StoreTransactionIsError($txn_id) {
    global $db;
    try {
        return (int) $db->where('txn_id', $txn_id)->where('is_error', 1)->getValue(T_STORE_LEDGER_FLAGS, 'COUNT(*)') > 0;
    } catch (Exception $e) {
        return false;
    }
}

// =========================================================================
//  Sequential document numbers
// =========================================================================

/**
 * A store reference: an uppercase prefix concatenated to a random
 * alphanumeric string (e.g. SO8Y78290J, INV9028HDJSK). No dashes, no date.
 */
function PT_StoreRef($prefix, $length = 9) {
    $alphabet = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $max = strlen($alphabet) - 1;
    $suffix = '';
    for ($i = 0; $i < $length; $i++) {
        $suffix .= $alphabet[random_int(0, $max)];
    }
    return $prefix . $suffix;
}

function PT_StoreNextNumber($doc_type) {
    $prefixes = array(
        'quotation'                => 'QUO',
        'invoice'                  => 'INV',
        'payment_credit_note'      => 'PCN',
        'receipt'                  => 'RCP',
        'cancellation_credit_note' => 'CCN',
        'refund_credit_note'       => 'RCN',
    );
    $prefix = isset($prefixes[$doc_type]) ? $prefixes[$doc_type] : 'DOC';
    return PT_StoreRef($prefix);
}

// =========================================================================
//  Business documents
// =========================================================================

/**
 * Create a document row plus its lines. Returns the inserted document id, or
 * false. The document number is allocated atomically.
 */
function PT_StoreCreateDocument($doc_type, $order, $data = array(), $items = array()) {
    global $db, $pt;
    $number = PT_StoreNextNumber($doc_type);
    if (empty($number)) {
        return false;
    }
    $verify = strtoupper(bin2hex(random_bytes(4)));
    $doc_id = PT_DbGuardedRun(function () use ($db, $doc_type, $order, $data, $items, $number, $verify) {
        $id = $db->insert(T_STORE_DOCUMENTS, array(
            'doc_type'       => $doc_type,
            'number'         => $number,
            'order_id'       => (int) $order->id,
            'invoice_id'     => !empty($data['invoice_id']) ? (int) $data['invoice_id'] : 0,
            'buyer_id'       => (int) $order->buyer_id,
            'seller_id'      => (int) $order->seller_id,
            'seller_user_id' => (int) $order->seller_user_id,
            'status'         => !empty($data['status']) ? $data['status'] : 'pending',
            'currency'       => PT_StoreCurrency(),
            'subtotal'       => PT_StakeDec(isset($data['subtotal']) ? $data['subtotal'] : 0),
            'discount'       => PT_StakeDec(isset($data['discount']) ? $data['discount'] : 0),
            'tax_total'      => PT_StakeDec(isset($data['tax_total']) ? $data['tax_total'] : 0),
            'total'          => PT_StakeDec(isset($data['total']) ? $data['total'] : 0),
            'amount'         => PT_StakeDec(isset($data['amount']) ? $data['amount'] : 0),
            'balance'        => PT_StakeDec(isset($data['balance']) ? $data['balance'] : 0),
            'payment_reference' => isset($data['payment_reference']) ? $data['payment_reference'] : '',
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : '',
            'reason'         => isset($data['reason']) ? substr($data['reason'], 0, 500) : '',
            'verify_code'    => $verify,
            'meta'           => !empty($data['meta']) ? json_encode($data['meta']) : null,
            'created_at'     => time(),
        ));
        if (empty($id)) {
            return false;
        }
        foreach ($items as $item) {
            $db->insert(T_STORE_DOCUMENT_ITEMS, array(
                'document_id'  => $id,
                'description'  => isset($item['description']) ? substr($item['description'], 0, 300) : '',
                'type'         => isset($item['type']) ? $item['type'] : 'product',
                'qty'          => PT_StakeDec(isset($item['qty']) ? $item['qty'] : 1),
                'unit_price'   => PT_StakeDec(isset($item['unit_price']) ? $item['unit_price'] : 0),
                'amount'       => PT_StakeDec(isset($item['amount']) ? $item['amount'] : 0),
                'service_date' => isset($item['service_date']) ? $item['service_date'] : '',
                'duration'     => isset($item['duration']) ? $item['duration'] : '',
                'location'     => isset($item['location']) ? $item['location'] : '',
                'specialist'   => isset($item['specialist']) ? $item['specialist'] : '',
                'created_at'   => time(),
            ));
        }
        return $id;
    });
    return !empty($doc_id) ? $doc_id : false;
}

function PT_StoreDocumentItems($document_id) {
    global $db;
    try {
        return $db->where('document_id', (int) $document_id)->orderBy('id', 'ASC')->get(T_STORE_DOCUMENT_ITEMS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreDocument($id) {
    global $db;
    try {
        return $db->where('id', (int) $id)->getOne(T_STORE_DOCUMENTS);
    } catch (Exception $e) {
        return null;
    }
}

/** The invoice currently attached to an order (latest), if any. */
function PT_StoreOrderInvoice($order_id) {
    global $db;
    try {
        return $db->where('order_id', (int) $order_id)->where('doc_type', 'invoice')->orderBy('id', 'DESC')->getOne(T_STORE_DOCUMENTS);
    } catch (Exception $e) {
        return null;
    }
}

function PT_StoreOrderDocuments($order_id) {
    global $db;
    try {
        return $db->where('order_id', (int) $order_id)->orderBy('id', 'ASC')->get(T_STORE_DOCUMENTS);
    } catch (Exception $e) {
        return array();
    }
}

/** The most recent document attached to an order (receipt > credit note > invoice > quotation). */
function PT_StoreLatestDocument($order_id) {
    global $db;
    try {
        return $db->where('order_id', (int) $order_id)->orderBy('id', 'DESC')->getOne(T_STORE_DOCUMENTS);
    } catch (Exception $e) {
        return null;
    }
}

// =========================================================================
//  Orders and booking
// =========================================================================

function PT_StoreCurrencyFor($seller = null) {
    return PT_StoreCurrency();
}

/**
 * Build an order from a cart. $cart is a list of
 *   array('product_id' => n, 'variant_id' => n, 'qty' => n, 'slot_start' => ts)
 * The seller is derived from the first product (a single order never mixes
 * sellers; the caller splits the cart per seller before calling).
 */
function PT_StoreBuildOrder($buyer_id, $cart, $options = array()) {
    global $db, $pt;
    $buyer_id = (int) $buyer_id;
    if ($buyer_id <= 0) {
        return array('status' => false, 'message' => 'You must be logged in to order or book.');
    }
    if (!PT_StoreHasAcceptedTerms($buyer_id, 'buyer')) {
        return array('status' => false, 'message' => 'Accept the Store policies before ordering.');
    }
    if (empty($cart) || !is_array($cart)) {
        return array('status' => false, 'message' => 'Your cart is empty.');
    }

    $first = PT_StoreProduct($cart[0]['product_id']);
    if (empty($first) || $first->status !== 'active') {
        return array('status' => false, 'message' => 'That product is not available.');
    }
    $seller = PT_StoreSellerById($first->seller_id);
    if (empty($seller) || $seller->status !== 'active') {
        return array('status' => false, 'message' => 'That store is not available.');
    }
    $seller_user_id = (int) $seller->user_id;
    $member = PT_StoreBuyerIsMember($buyer_id, $seller_user_id);
    $benefit = PT_StoreMembershipBenefit($seller->id);

    $delivery_mode = isset($options['delivery_mode']) ? $options['delivery_mode'] : 'deliver';
    if (!in_array($delivery_mode, array('deliver', 'pickup', 'at_provider', 'at_customer', 'digital'), true)) {
        $delivery_mode = 'deliver';
    }
    $buyer_location_id = !empty($options['buyer_location_id']) ? (int) $options['buyer_location_id'] : 0;
    // Fall back to the buyer's saved store location if we know it.
    if ($buyer_location_id <= 0) {
        $buyer_location_id = (int) PT_StoreBuyerLocationId($buyer_id);
    }

    $subtotal = PT_StakeDec(0);          // gross goods amount the buyer pays
    $net_total = PT_StakeDec(0);         // tax-excluded goods amount
    $discount_total = PT_StakeDec(0);
    $delivery_total = PT_StakeDec(0);
    $tax_total = PT_StakeDec(0);
    $commission_total = PT_StakeDec(0);
    $seller_total = PT_StakeDec(0);
    $kind = 'order';
    $lines = array();

    foreach ($cart as $entry) {
        $product = PT_StoreProduct($entry['product_id']);
        if (empty($product) || $product->status !== 'active' || (int) $product->seller_id !== (int) $seller->id) {
            return array('status' => false, 'message' => 'One of the items is no longer available.');
        }
        $qty = max(1, (int) (isset($entry['qty']) ? $entry['qty'] : 1));
        if ($product->kind === 'service') {
            $kind = 'booking';
        }
        $unit = PT_StoreEffectivePrice($product, $member);
        $base_price = PT_StakeDec($product->price);
        $base_line = PT_StakeMul($base_price, PT_StakeDec($qty));
        $line_gross = PT_StakeMul($unit, PT_StakeDec($qty));
        $line_discount = PT_StakeSub($base_line, $line_gross);
        if (PT_StakeCmp($line_discount, '0') < 0) {
            $line_discount = PT_StakeDec(0);
        }

        // Tax: 8% by default. Inclusive listings already contain it; the tax is
        // carved out of the price and never paid to the seller nor counted as
        // platform commission.
        $tax_split = PT_StoreTaxSplit($line_gross, !empty($product->tax_inclusive));
        $line_net = $tax_split['net'];
        $line_tax = $tax_split['tax'];
        $line_gross = $tax_split['gross'];

        $bps = PT_StoreCommissionBps($product, $seller);
        $commission = PT_StakeDiv(PT_StakeMul($line_net, PT_StakeDec($bps)), PT_StakeDec('10000'));
        $line_seller = PT_StakeSub($line_net, $commission);

        $subtotal = PT_StakeAdd($subtotal, $line_gross);
        $net_total = PT_StakeAdd($net_total, $line_net);
        $discount_total = PT_StakeAdd($discount_total, $line_discount);
        $tax_total = PT_StakeAdd($tax_total, $line_tax);
        $commission_total = PT_StakeAdd($commission_total, $commission);
        $seller_total = PT_StakeAdd($seller_total, $line_seller);

        $lines[] = array(
            'product' => $product,
            'qty' => $qty,
            'variant_id' => isset($entry['variant_id']) ? (int) $entry['variant_id'] : 0,
            'unit' => $unit,
            'line_total' => $line_gross,
            'line_net' => $line_net,
            'line_tax' => $line_tax,
            'line_discount' => $line_discount,
            'delivery_fee' => PT_StakeDec(0),
            'commission_bps' => $bps,
            'commission' => $commission,
            'seller_amount' => $line_seller,
            'slot_start' => isset($entry['slot_start']) ? (int) $entry['slot_start'] : 0,
        );
    }

    // System-priced delivery: buyer location + basket weight + chosen mode.
    $quote_lines = array();
    foreach ($lines as $l) {
        $quote_lines[] = array('product' => $l['product'], 'qty' => $l['qty']);
    }
    if ($member && !empty($benefit) && !empty($benefit->free_delivery)) {
        $delivery_quote = array('fee' => PT_StakeDec(0), 'mode' => $delivery_mode, 'reason' => 'Free for members', 'weight_grams' => 0, 'zone' => null);
    } else {
        $delivery_quote = PT_StoreQuoteDelivery($seller, $quote_lines, $buyer_location_id, $delivery_mode, isset($options['pickup_id']) ? (int) $options['pickup_id'] : 0);
    }
    if ($delivery_quote['mode'] === 'pickup_only') {
        return array('status' => false, 'message' => $delivery_quote['reason']);
    }
    $delivery_total = PT_StakeDec($delivery_quote['fee']);

    // Total = goods (tax-inclusive gross) + delivery.
    $total = PT_StakeAdd($subtotal, $delivery_total);
    // The buyer-facing subtotal is the tax-excluded goods amount before any
    // member discount, so that Subtotal - Discount + Tax + Delivery reconciles
    // to Total. ($subtotal above is the tax-inclusive gross the buyer pays.)
    $subtotal_for_display = PT_StakeAdd($net_total, $discount_total);

    // Longest return window on the order drives the escrow release time.
    $escrow_days = 0;
    foreach ($lines as $line) {
        $d = PT_StoreEscrowDays($line['product']);
        if ($d > $escrow_days) {
            $escrow_days = $d;
        }
    }

    $order_number = PT_StoreRef('SO');
    $order_id = PT_DbGuardedRun(function () use ($db, $order_number, $buyer_id, $seller, $kind, $subtotal, $subtotal_for_display, $discount_total, $delivery_total, $tax_total, $commission_total, $seller_total, $total, $options, $escrow_days, $lines, $delivery_mode, $buyer_location_id) {
        $id = $db->insert(T_STORE_ORDERS, array(
            'order_number'   => $order_number,
            'buyer_id'       => $buyer_id,
            'seller_id'      => (int) $seller->id,
            'seller_user_id' => (int) $seller->user_id,
            'kind'           => $kind,
            'status'         => 'pending',
            'currency'       => PT_StoreCurrency(),
            'subtotal'       => $subtotal_for_display,
            'discount_total' => $discount_total,
            'delivery_total' => $delivery_total,
            'tax_total'      => $tax_total,
            'commission_total'=> $commission_total,
            'seller_total'   => $seller_total,
            'total'          => $total,
            'delivery_mode'  => $delivery_mode,
            'buyer_location_id' => $buyer_location_id,
            'buyer_address'  => !empty($options['buyer_address']) ? substr($options['buyer_address'], 0, 500) : '',
            'shipping'       => !empty($options['shipping']) ? json_encode($options['shipping']) : null,
            'buyer_note'     => !empty($options['buyer_note']) ? substr($options['buyer_note'], 0, 1000) : '',
            'placed_at'      => time(),
            'created_at'     => time(),
            'updated_at'     => time(),
        ));
        if (empty($id)) {
            return false;
        }
        foreach ($lines as $line) {
            $variant_label = '';
            if (!empty($line['variant_id'])) {
                $variant = $db->where('id', $line['variant_id'])->where('product_id', $line['product']->id)->getOne(T_STORE_PRODUCT_VARIANTS);
                if (!empty($variant)) {
                    $variant_label = $variant->name;
                }
            }
            $db->insert(T_STORE_ORDER_ITEMS, array(
                'order_id'      => $id,
                'product_id'    => (int) $line['product']->id,
                'variant_id'    => (int) $line['variant_id'],
                'kind'          => $line['product']->kind,
                'title'         => $line['product']->title,
                'variant_label' => $variant_label,
                'qty'           => $line['qty'],
                'unit_price'    => $line['unit'],
                'line_discount' => $line['line_discount'],
                'line_total'    => $line['line_total'],
                'delivery_fee'  => $line['delivery_fee'],
                'commission_bps'=> $line['commission_bps'],
                'commission_amount' => $line['commission'],
                'seller_amount' => $line['seller_amount'],
                'requires_return' => !empty(PT_StoreCategory($line['product']->category_id) ? PT_StoreCategory($line['product']->category_id)->requires_return : 0) ? 1 : 0,
                'return_days'   => PT_StoreEscrowDays($line['product']),
                'created_at'    => time(),
            ));
            if ($line['product']->kind === 'service' && !empty($line['slot_start'])) {
                $db->insert(T_STORE_BOOKINGS, array(
                    'order_id'   => $id,
                    'product_id' => (int) $line['product']->id,
                    'seller_id'  => (int) $seller->id,
                    'buyer_id'   => $buyer_id,
                    'slot_start' => (int) $line['slot_start'],
                    'slot_end'   => (int) $line['slot_start'] + ((int) $line['product']->booking_duration_minutes * 60),
                    'status'     => 'reserved',
                    'created_at' => time(),
                    'updated_at' => time(),
                ));
            }
        }
        return $id;
    });

    if (empty($order_id)) {
        return array('status' => false, 'message' => 'Could not place the order.');
    }

    $order = $db->where('id', $order_id)->getOne(T_STORE_ORDERS);
    if (empty($order)) {
        return array('status' => false, 'message' => 'Could not place the order.');
    }

    // Quotation is issued while the order is pending.
    PT_StoreIssueQuotation($order);
    PT_StoreNotifyOrderParties($order, 'order_placed');
    return array('status' => true, 'order_id' => $order_id, 'order' => $order);
}

function PT_StoreOrderItems($order_id) {
    global $db;
    try {
        return $db->where('order_id', (int) $order_id)->orderBy('id', 'ASC')->get(T_STORE_ORDER_ITEMS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreOrderAttachmentsForSeller($order_id) {
    // Reserved for future per-order add-ons.
    return array();
}

// =========================================================================
//  Document builders
// =========================================================================

function PT_StoreDocumentItemLines($order) {
    $items = PT_StoreOrderItems($order->id);
    $out = array();
    $bookings = array();
    foreach (PT_StoreBookingsForOrder($order->id) as $b) {
        $bookings[$b->order_item_id] = $b;
    }
    foreach ($items as $item) {
        $service_date = '';
        $duration = '';
        if (isset($bookings[$item->id]) && !empty($bookings[$item->id]->slot_start)) {
            $service_date = gmdate('D, d M Y H:i', $bookings[$item->id]->slot_start);
            $duration = round(($bookings[$item->id]->slot_end - $bookings[$item->id]->slot_start) / 60) . ' min';
        }
        $out[] = array(
            'description'  => $item->title . (!empty($item->variant_label) ? ' (' . $item->variant_label . ')' : ''),
            'type'         => $item->kind === 'service' ? 'service' : 'product',
            'qty'          => $item->qty,
            'unit_price'   => $item->unit_price,
            'amount'       => $item->line_total,
            'service_date' => $service_date,
            'duration'     => $duration,
            'location'     => '',
            'specialist'   => '',
        );
    }
    if (PT_StakeCmp($order->delivery_total, '0') > 0) {
        $out[] = array(
            'description' => 'Delivery / shipping',
            'type' => 'delivery',
            'qty' => 1,
            'unit_price' => $order->delivery_total,
            'amount' => $order->delivery_total,
            'service_date' => '', 'duration' => '', 'location' => '', 'specialist' => '',
        );
    }
    return $out;
}

function PT_StoreIssueQuotation($order) {
    $items = PT_StoreDocumentItemLines($order);
    $validity = (int) PT_StoreParam('store_doc_validity_days', 7);
    $doc_id = PT_StoreCreateDocument('quotation', $order, array(
        'status'   => 'pending',
        'subtotal' => $order->subtotal,
        'discount' => $order->discount_total,
        'tax_total'=> $order->tax_total,
        'total'    => $order->total,
        'meta'     => array('valid_until' => gmdate('Y-m-d', time() + ($validity * 86400))),
    ), $items);
    if (!empty($doc_id)) {
        PT_StoreEmailDocument($doc_id);
    }
    return $doc_id;
}

function PT_StoreIssueInvoice($order) {
    // Reverse any existing invoice first (idempotent re-issue is not allowed;
    // an order invoices once).
    $existing = PT_StoreOrderInvoice($order->id);
    if (!empty($existing)) {
        return $existing->id;
    }
    $items = PT_StoreDocumentItemLines($order);
    $doc_id = PT_StoreCreateDocument('invoice', $order, array(
        'status'   => 'confirmed',
        'subtotal' => $order->subtotal,
        'discount' => $order->discount_total,
        'tax_total'=> $order->tax_total,
        'total'    => $order->total,
        'balance'  => $order->total,
        'meta'     => array('due_date' => gmdate('Y-m-d', time() + (7 * 86400))),
    ), $items);
    if (empty($doc_id)) {
        return false;
    }
    // Invoice debits the buyer's receivable and credits the seller payable,
    // commission, delivery and tax. Each item contributes the tax-excluded net
    // to the seller/commission split, so tax is never part of their payout.
    $tax = PT_StakeDec($order->tax_total);
    $delivery = PT_StakeDec($order->delivery_total);
    PT_StoreLedgerPost(array(
        array('account' => '1100', 'user_id' => $order->buyer_id, 'debit' => $order->total, 'memo' => 'Invoice ' . $order->order_number),
        array('account' => '2200', 'user_id' => $order->seller_user_id, 'credit' => $order->seller_total, 'memo' => 'Seller payable ' . $order->order_number),
        array('account' => '2500', 'credit' => $order->commission_total, 'memo' => 'Commission payable'),
        array('account' => '2600', 'credit' => $delivery, 'memo' => 'Delivery payable'),
        array('account' => '2400', 'credit' => $tax, 'memo' => 'Tax payable'),
    ), array('order_id' => $order->id, 'document_id' => $doc_id));
    PT_StoreEmailDocument($doc_id);
    return $doc_id;
}

function PT_StoreRecordPayment($order, $amount, $method, $reference) {
    global $db;
    $amount = PT_StakeDec($amount);
    if (PT_StakeCmp($amount, '0') <= 0) {
        return false;
    }
    $invoice = PT_StoreOrderInvoice($order->id);
    if (empty($invoice)) {
        return false;
    }
    $paid_to_date = PT_StakeDec($order->paid_total);
    $new_paid = PT_StakeAdd($paid_to_date, $amount);
    if (PT_StakeCmp($new_paid, PT_StakeDec($order->total)) > 0) {
        $new_paid = PT_StakeDec($order->total);
        $amount = PT_StakeSub($new_paid, $paid_to_date);
    }
    if (PT_StakeCmp($amount, '0') <= 0) {
        return false;
    }
    $balance = PT_StakeSub(PT_StakeDec($order->total), $new_paid);

    $cn_id = PT_StoreCreateDocument('payment_credit_note', $order, array(
        'invoice_id' => $invoice->id,
        'status'     => 'approved',
        'subtotal'   => $order->subtotal,
        'discount'   => $order->discount_total,
        'tax_total'  => $order->tax_total,
        'total'      => $order->total,
        'amount'     => $amount,
        'balance'    => $balance,
        'payment_reference' => $reference,
        'payment_method'    => $method,
    ), PT_StoreDocumentItemLines($order));

    // Split the payment proportionally. Seller, commission, delivery and tax
    // are derived cumulatively (before vs after this payment) so that the sum
    // of the parts over several part-payments equals the invoice exactly with
    // no rounding drift, and the seller's escrow remainder telescopes exactly.
    $total = PT_StakeDec($order->total);
    $commission_total = PT_StakeDec($order->commission_total);
    $delivery_total   = PT_StakeDec($order->delivery_total);
    $tax_total        = PT_StakeDec($order->tax_total);
    $seller_total     = PT_StakeDec($order->seller_total);
    $part = function ($cum_total, $before, $now, $denom) {
        $c = $denom == PT_StakeDec(0) ? PT_StakeDec(0) : PT_StakeDiv(PT_StakeMul($now, $cum_total), $denom);
        $b = $denom == PT_StakeDec(0) ? PT_StakeDec(0) : PT_StakeDiv(PT_StakeMul($before, $cum_total), $denom);
        return PT_StakeSub($c, $b);
    };
    $commission_part = $part($commission_total, $paid_to_date, $new_paid, $total);
    $delivery_part   = $part($delivery_total, $paid_to_date, $new_paid, $total);
    $tax_part        = $part($tax_total, $paid_to_date, $new_paid, $total);
    $seller_held     = PT_StakeSub(PT_StakeSub(PT_StakeSub($amount, $commission_part), $delivery_part), $tax_part);
    $clearing = PT_StakeSub(PT_StakeSub(PT_StakeSub(PT_StakeSub($amount, $seller_held), $commission_part), $delivery_part), $tax_part);

    $lines = array(
        array('account' => '1200', 'debit' => $amount, 'memo' => 'Payment received ' . $reference),
        array('account' => '1100', 'user_id' => $order->buyer_id, 'credit' => $amount, 'memo' => 'Payment applied'),
        // Move the seller's share of the payable into the escrow hold. It is
        // released to their withdrawable balance once the return window ends.
        array('account' => '2200', 'user_id' => $order->seller_user_id, 'debit' => $seller_held, 'memo' => 'Seller payable moved to escrow'),
        array('account' => '1300', 'credit' => $seller_held, 'memo' => 'Seller funds held in escrow'),
        array('account' => '2500', 'debit' => $commission_part, 'memo' => 'Commission earned'),
        array('account' => '4000', 'credit' => $commission_part, 'memo' => 'Commission revenue'),
        array('account' => '2600', 'debit' => $delivery_part, 'memo' => 'Delivery collected'),
        array('account' => '4100', 'credit' => $delivery_part, 'memo' => 'Delivery revenue'),
        array('account' => '2400', 'debit' => $tax_part, 'memo' => 'Tax collected'),
        array('account' => '4200', 'credit' => $tax_part, 'memo' => 'Tax collected (owed)'),
    );
    if (PT_StakeCmp($clearing, '0') > 0) {
        $lines[] = array('account' => '4000', 'credit' => $clearing, 'memo' => 'Rounding clearing');
    } elseif (PT_StakeCmp($clearing, '0') < 0) {
        $lines[] = array('account' => '4000', 'debit' => PT_StakeSub('0', $clearing), 'memo' => 'Rounding clearing');
    }
    PT_StoreLedgerPost($lines, array('order_id' => $order->id, 'document_id' => $cn_id));

    $data = array(
        'paid_total'    => $new_paid,
        'payment_method'=> $method,
        'payment_ref'   => $reference,
        'paid_at'       => time(),
        'updated_at'    => time(),
    );
    if (PT_StakeCmp($balance, '0') <= 0) {
        $data['status'] = 'paid';
    }
    $db->where('id', $order->id)->update(T_STORE_ORDERS, $data);
    $order = $db->where('id', $order->id)->getOne(T_STORE_ORDERS);
    if (!empty($cn_id)) {
        PT_StoreEmailDocument($cn_id);
    }
    if ($order->status === 'paid') {
        PT_StoreIssueReceipt($order);
        PT_StoreCreateEarning($order);
    }
    PT_StoreNotifyOrderParties($order, 'payment_received');
    return true;
}

/**
 * Settle a confirmed/approved order when its JPesa payment is verified.
 * Called from the shared JPesa reconciliation for purchase_type = 'store'.
 */
function PT_StoreSettlePaidOrder($order_id, $amount, $method = 'jpesa', $reference = '') {
    global $db;
    $order = $db->where('id', (int) $order_id)->getOne(T_STORE_ORDERS);
    if (empty($order) || in_array($order->status, array('paid', 'closed', 'cancelled', 'refunded'), true)) {
        return false;
    }
    if ($order->status === 'pending') {
        PT_StoreSetOrderStatus($order, 'confirmed', 0, 'payment');
        $order = $db->where('id', $order->id)->getOne(T_STORE_ORDERS);
    }
    return PT_StoreRecordPayment($order, $amount, $method, $reference);
}

function PT_StoreIssueReceipt($order) {
    $existing = PT_StoreOrderDocuments($order->id);
    foreach ($existing as $d) {
        if ($d->doc_type === 'receipt') {
            return $d->id;
        }
    }
    $doc_id = PT_StoreCreateDocument('receipt', $order, array(
        'status'   => 'paid',
        'subtotal' => $order->subtotal,
        'discount' => $order->discount_total,
        'tax_total'=> $order->tax_total,
        'total'    => $order->total,
        'amount'   => $order->paid_total,
        'balance'  => '0',
        'meta'     => array('closes_at' => gmdate('Y-m-d', $order->escrow_release_at)),
    ), PT_StoreDocumentItemLines($order));
    if (!empty($doc_id)) {
        PT_StoreEmailDocument($doc_id);
    }
    return $doc_id;
}

/** Net balance (debit - credit) of one account for a single order. */
function PT_StoreOrderAccountBalance($order_id, $code) {
    global $db;
    try {
        $row = $db->rawQueryOne(
            "SELECT COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c FROM `" . T_STORE_LEDGER_ENTRIES . "` WHERE `order_id` = ? AND `account_code` = ?",
            array((int) $order_id, $code)
        );
    } catch (Exception $e) {
        return PT_StakeDec(0);
    }
    return empty($row) ? PT_StakeDec(0) : PT_StakeSub($row->d, $row->c);
}

/**
 * Build the journal that unwinds an order's remaining ledger position and
 * returns $cash_out to the buyer. Uses the order's actual balances so the
 * reversal is exact regardless of how the order was paid.
 */
function PT_StoreBuildUnwindLines($order, $cash_out) {
    $cash_out = PT_StakeDec($cash_out);
    $lines = array();
    // Clear the outstanding receivable.
    $ar = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, '1100'));
    if (PT_StakeCmp($ar, '0') < 0) {
        $u = PT_StakeSub('0', $ar);
        $lines[] = array('account' => '1100', 'user_id' => $order->buyer_id, 'debit' => $u, 'memo' => 'Clear outstanding receivable');
    }
    // Clear the seller escrow hold and payable.
    foreach (array('1300' => 'Clear seller escrow hold', '2200' => 'Clear seller payable') as $code => $memo) {
        $bal = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, $code));
        if (PT_StakeCmp($bal, '0') < 0) {
            $lines[] = array('account' => $code, 'user_id' => $code === '2200' ? $order->seller_user_id : 0, 'debit' => PT_StakeSub('0', $bal), 'memo' => $memo);
        }
    }
    // Clear the platform commission/delivery payable and any tax collected.
    foreach (array('2500' => 'Clear commission payable', '2600' => 'Clear delivery payable', '4200' => 'Clear tax collected') as $code => $memo) {
        $bal = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, $code));
        if (PT_StakeCmp($bal, '0') < 0) {
            $lines[] = array('account' => $code, 'debit' => PT_StakeSub('0', $bal), 'memo' => $memo);
        }
    }
    // Balance the journal against cash leaving and revenue reversed.
    $total_debit = PT_StakeDec(0);
    foreach ($lines as $l) {
        $total_debit = PT_StakeAdd($total_debit, $l['debit']);
    }
    $need_credit = PT_StakeAdd($total_debit, $cash_out);
    $rev_4000 = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, '4000'));
    $rev_4100 = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, '4100'));
    $avail_rev = PT_StakeAdd(PT_StakeCmp($rev_4000, '0') < 0 ? PT_StakeSub('0', $rev_4000) : '0', PT_StakeCmp($rev_4100, '0') < 0 ? PT_StakeSub('0', $rev_4100) : '0');
    $reverse = $need_credit;
    if (PT_StakeCmp($reverse, $avail_rev) > 0) {
        $reverse = $avail_rev;
    }
    if (PT_StakeCmp($reverse, '0') > 0) {
        $lines[] = array('account' => '4000', 'debit' => $reverse, 'memo' => 'Reverse platform revenue on ' . $order->order_number);
    }
    $residual = PT_StakeSub($need_credit, $reverse);
    if (PT_StakeCmp($residual, '0') > 0) {
        // Anything the platform cannot reverse (e.g. tax) is a refund expense.
        $lines[] = array('account' => '5000', 'debit' => $residual, 'memo' => 'Refund expense');
    }
    if (PT_StakeCmp($cash_out, '0') > 0) {
        $lines[] = array('account' => '1200', 'credit' => $cash_out, 'memo' => 'Refund paid out');
    }
    return $lines;
}

function PT_StoreCancelOrder($order, $reason, $by_user = 0) {
    global $db;
    if (in_array($order->status, array('cancelled', 'refunded', 'closed'), true)) {
        return false;
    }
    $invoice = PT_StoreOrderInvoice($order->id);
    $paid = PT_StakeDec($order->paid_total);
    $doc_id = PT_StoreCreateDocument('cancellation_credit_note', $order, array(
        'invoice_id' => !empty($invoice) ? $invoice->id : 0,
        'status'     => 'approved',
        'total'      => $order->total,
        'amount'     => $order->total,
        'reason'     => $reason,
        'meta'       => array('refund_amount' => $paid, 'destination' => 'original payment method'),
    ), PT_StoreDocumentItemLines($order));

    // Unwind the order's ledger position and refund any collected cash.
    $lines = PT_StoreBuildUnwindLines($order, $paid);
    if (!empty($lines)) {
        PT_StoreLedgerPost($lines, array('order_id' => $order->id, 'document_id' => $doc_id));
    }
    PT_StoreVoidEscrow($order->id);
    $db->where('id', $order->id)->update(T_STORE_ORDERS, array(
        'status' => 'cancelled',
        'cancel_reason' => substr((string) $reason, 0, 500),
        'updated_at' => time(),
    ));
    PT_StoreCancelBookingForOrder($order->id);
    if (!empty($doc_id)) {
        PT_StoreEmailDocument($doc_id);
    }
    $order = $db->where('id', $order->id)->getOne(T_STORE_ORDERS);
    PT_StoreNotifyOrderParties($order, 'order_cancelled');
    return true;
}

function PT_StoreRefundOrder($order, $amount, $reason, $by_user = 0) {
    global $db;
    $amount = PT_StakeDec($amount);
    $paid = PT_StakeDec($order->paid_total);
    if (PT_StakeCmp($amount, '0') <= 0 || PT_StakeCmp($amount, $paid) > 0) {
        return false;
    }
    $invoice = PT_StoreOrderInvoice($order->id);
    $doc_id = PT_StoreCreateDocument('refund_credit_note', $order, array(
        'invoice_id' => !empty($invoice) ? $invoice->id : 0,
        'status'     => 'approved',
        'total'      => $order->total,
        'amount'     => $amount,
        'reason'     => $reason,
        'meta'       => array('destination' => 'original payment method'),
    ), PT_StoreDocumentItemLines($order));
    // Refund: the cash goes back to the buyer, funded first from the seller's
    // escrow hold and then from reversing platform revenue. Booked as an expense
    // only for whatever cannot be funded from those accounts.
    $lines = array();
    $escrow_held = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, '1300'));
    $available_escrow = PT_StakeCmp($escrow_held, '0') < 0 ? PT_StakeSub('0', $escrow_held) : PT_StakeDec(0);
    $from_escrow = PT_StakeCmp($amount, $available_escrow) < 0 ? $amount : $available_escrow;
    if (PT_StakeCmp($from_escrow, '0') > 0) {
        $lines[] = array('account' => '1300', 'debit' => $from_escrow, 'memo' => 'Refund funded from seller escrow');
    }
    $remaining = PT_StakeSub($amount, $from_escrow);
    if (PT_StakeCmp($remaining, '0') > 0) {
        $rev = PT_StakeDec(PT_StoreOrderAccountBalance($order->id, '4000'));
        $avail_rev = PT_StakeCmp($rev, '0') < 0 ? PT_StakeSub('0', $rev) : PT_StakeDec(0);
        $from_rev = PT_StakeCmp($remaining, $avail_rev) < 0 ? $remaining : $avail_rev;
        if (PT_StakeCmp($from_rev, '0') > 0) {
            $lines[] = array('account' => '4000', 'debit' => $from_rev, 'memo' => 'Reverse commission on refund');
            $remaining = PT_StakeSub($remaining, $from_rev);
        }
    }
    if (PT_StakeCmp($remaining, '0') > 0) {
        $lines[] = array('account' => '5000', 'debit' => $remaining, 'memo' => 'Refund granted');
    }
    $lines[] = array('account' => '1200', 'credit' => $amount, 'memo' => 'Refund paid out');
    PT_StoreLedgerPost($lines, array('order_id' => $order->id, 'document_id' => $doc_id));
    $refund_total = PT_StakeAdd(PT_StakeDec($order->refund_total), $amount);
    $status = (PT_StakeCmp($refund_total, $paid) >= 0) ? 'refunded' : $order->status;
    $db->where('id', $order->id)->update(T_STORE_ORDERS, array(
        'refund_total' => $refund_total,
        'status'       => $status,
        'updated_at'   => time(),
    ));
    // Void any unreleased escrow so the seller cannot withdraw refunded money.
    PT_StoreVoidEscrow($order->id);
    if (!empty($doc_id)) {
        PT_StoreEmailDocument($doc_id);
    }
    $order = $db->where('id', $order->id)->getOne(T_STORE_ORDERS);
    PT_StoreNotifyOrderParties($order, 'order_refunded');
    return true;
}

/**
 * Advance an order status and emit the next document. Only the transitions
 * that make business sense are allowed.
 */
function PT_StoreSetOrderStatus($order, $status, $by_user = 0, $reason = '') {
    global $db;
    $allowed = array(
        // Goods move through fulfilment (approved -> shipped -> delivered)
        // before payment; services skip straight to paid after approval.
        // Payment can happen at any pre-fulfilment point, and the escrow
        // window then advances the order to closed.
        'pending'  => array('confirmed', 'cancelled'),
        'confirmed'=> array('approved', 'shipped', 'delivered', 'paid', 'cancelled'),
        'approved' => array('shipped', 'delivered', 'paid', 'cancelled'),
        'shipped'  => array('delivered', 'paid', 'cancelled'),
        'delivered'=> array('paid', 'cancelled'),
        'paid'     => array('closed', 'delivered', 'refunded'),
        'closed'   => array(),
        'cancelled'=> array(),
        'refunded' => array(),
        'error'    => array('pending', 'confirmed', 'approved', 'shipped', 'delivered', 'paid', 'cancelled', 'closed'),
    );
    $status = (string) $status;
    if ($status === 'error') {
        $db->where('id', $order->id)->update(T_STORE_ORDERS, array('status' => 'error', 'updated_at' => time()));
        return true;
    }
    $current = $order->status;
    if (!isset($allowed[$current]) || !in_array($status, $allowed[$current], true)) {
        return false;
    }
    $update = array('status' => $status, 'updated_at' => time());
    if ($status === 'confirmed') {
        $update['confirmed_at'] = time();
    }
    if ($status === 'approved') {
        $update['approved_at'] = time();
    }
    if ($status === 'shipped') {
        $update['shipped_at'] = time();
    }
    if ($status === 'delivered') {
        $update['delivered_at'] = time();
    }
    if ($status === 'paid') {
        $update['paid_at'] = time();
    }
    if ($status === 'closed') {
        $update['closed_at'] = time();
    }
    $db->where('id', $order->id)->update(T_STORE_ORDERS, $update);
    $order = $db->where('id', $order->id)->getOne(T_STORE_ORDERS);

    // The invoice is the tax document for the sale, so it is issued as soon as
    // the seller commits to the order (confirmed or approved) and is idempotent
    // thereafter.
    if (in_array($status, array('confirmed', 'approved'), true)) {
        PT_StoreIssueInvoice($order);
    }
    if ($status === 'cancelled') {
        return PT_StoreCancelOrder($order, $reason ?: 'Cancelled', $by_user);
    }
    if ($status === 'closed') {
        PT_StoreReleaseEarning($order->id);
    }
    PT_StoreNotifyOrderParties($order, 'status_' . $status);
    return true;
}

// =========================================================================
//  Bookings
// =========================================================================

function PT_StoreBookingsForOrder($order_id) {
    global $db;
    try {
        return $db->where('order_id', (int) $order_id)->orderBy('slot_start', 'ASC')->get(T_STORE_BOOKINGS);
    } catch (Exception $e) {
        return array();
    }
}

function PT_StoreCancelBookingForOrder($order_id) {
    global $db;
    try {
        $db->where('order_id', (int) $order_id)->update(T_STORE_BOOKINGS, array('status' => 'cancelled', 'updated_at' => time()));
    } catch (Exception $e) {
    }
}

/**
 * Expand a service's weekly template into concrete available slots for the
 * next $days days, removing blackouts and already-reserved capacity.
 */
function PT_StoreAvailableSlots($product, $days = 30) {
    global $db;
    if (empty($product) || $product->kind !== 'service') {
        return array();
    }
    $slots = array();
    try {
        $templates = $db->where('product_id', $product->id)->where('is_active', 1)->orderBy('weekday', 'ASC')->orderBy('start_time', 'ASC')->get(T_STORE_PRODUCT_SLOTS);
        $blackouts = $db->where('product_id', $product->id)->get(T_STORE_BLACKOUTS);
    } catch (Exception $e) {
        return array();
    }
    if (empty($templates)) {
        return array();
    }
    $lead = (int) $product->booking_lead_days * 86400;
    $capacity_default = max(1, (int) $product->booking_capacity);
    $now = time();
    $start = $now + $lead;
    for ($d = 0; $d < $days; $d++) {
        $day_ts = strtotime('today UTC', $start) + ($d * 86400);
        $weekday = (int) gmdate('w', $day_ts);
        foreach ($templates as $t) {
            if ((int) $t->weekday !== $weekday) {
                continue;
            }
            $slot_start = strtotime(gmdate('Y-m-d', $day_ts) . ' ' . $t->start_time . ' UTC');
            $slot_end = strtotime(gmdate('Y-m-d', $day_ts) . ' ' . $t->end_time . ' UTC');
            if ($slot_start < $now + $lead || $slot_end <= $slot_start) {
                continue;
            }
            $blocked = false;
            foreach ($blackouts as $b) {
                if ($slot_start < $b->end_ts && $slot_end > $b->start_ts) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }
            $capacity = !empty($t->capacity) ? (int) $t->capacity : $capacity_default;
            try {
                $booked = (int) $db->where('product_id', $product->id)->where('slot_start', $slot_start)->where('status', array('reserved', 'confirmed'), 'IN')->getValue(T_STORE_BOOKINGS, 'COUNT(*)');
            } catch (Exception $e) {
                $booked = 0;
            }
            $slots[] = array(
                'ts' => $slot_start,
                'end' => $slot_end,
                'label' => gmdate('D, d M Y', $slot_start),
                'time' => gmdate('H:i', $slot_start) . ' - ' . gmdate('H:i', $slot_end),
                'remaining' => max(0, $capacity - $booked),
            );
        }
    }
    return $slots;
}

// =========================================================================
//  Earnings / escrow
// =========================================================================

/** Create the seller earning row in escrow for a paid order. */
function PT_StoreCreateEarning($order) {
    global $db;
    $existing = $db->where('order_id', (int) $order->id)->getOne(T_STORE_EARNINGS);
    if (!empty($existing)) {
        return $existing->id;
    }
    // order.subtotal is the tax-excluded goods amount; the goods gross the
    // earning reports adds the tax back to the pre-commission value.
    $gross = PT_StakeAdd(PT_StakeDec($order->subtotal), PT_StakeDec($order->tax_total));
    $net = PT_StakeDec($order->seller_total);
    $release_at = 0;
    // Escrow ends when the longest return window since payment has elapsed.
    $release_at = $order->paid_at ? ($order->paid_at + ((int) PT_StoreOrderEscrowDays($order) * 86400)) : 0;
    $id = PT_DbGuardedRun(function () use ($db, $order, $gross, $net, $release_at) {
        return $db->insert(T_STORE_EARNINGS, array(
            'order_id'       => (int) $order->id,
            'seller_id'      => (int) $order->seller_id,
            'seller_user_id' => (int) $order->seller_user_id,
            'currency'       => PT_StoreCurrency(),
            'gross'          => $gross,
            'commission'     => $order->commission_total,
            'net'            => $net,
            'status'         => 'escrow',
            'release_at'     => $release_at,
            'created_at'     => time(),
        ));
    });
    if (!empty($id)) {
        $db->where('id', $order->id)->update(T_STORE_ORDERS, array('escrow_release_at' => $release_at, 'updated_at' => time()));
    }
    return $id;
}

function PT_StoreOrderEscrowDays($order) {
    $items = PT_StoreOrderItems($order->id);
    $max = (int) PT_StoreParam('store_default_escrow_days', 7);
    foreach ($items as $item) {
        $product = PT_StoreProduct($item->product_id);
        if (!empty($product)) {
            $d = PT_StoreEscrowDays($product);
            if ($d > $max) {
                $max = $d;
            }
        }
    }
    return $max;
}

/** Release an escrow earning into the seller's withdrawable balance. */
function PT_StoreReleaseEarning($order_id) {
    global $db;
    $earning = $db->where('order_id', (int) $order_id)->getOne(T_STORE_EARNINGS);
    if (empty($earning) || $earning->status !== 'escrow') {
        return false;
    }
    if ($earning->release_at > 0 && $earning->release_at > time()) {
        return false;
    }
    $net = PT_StakeDec($earning->net);
    if (PT_StakeCmp($net, '0') <= 0) {
        $db->where('id', $earning->id)->update(T_STORE_EARNINGS, array('status' => 'released', 'released_at' => time()));
        return true;
    }
    $ok = PT_DbGuardedRun(function () use ($db, $earning, $net) {
        // The escrow restriction lifts: clear the escrow hold and recognise the
        // seller payable, which is now withdrawable from their balance.
        PT_StoreLedgerPost(array(
            array('account' => '1300', 'debit' => $net, 'memo' => 'Escrow restriction lifted'),
            array('account' => '2200', 'user_id' => $earning->seller_user_id, 'credit' => $net, 'memo' => 'Seller payable now withdrawable'),
        ), array('order_id' => $earning->order_id));
        $db->where('id', $earning->id)->update(T_STORE_EARNINGS, array('status' => 'released', 'released_at' => time()));
        // The withdrawable balance the withdrawal request system already knows.
        $db->where('id', $earning->seller_user_id)->update(T_USERS, array('balance' => $db->inc($net)));
        return true;
    });
    if ($ok) {
        PT_StoreNotifyUser($earning->seller_user_id, 'seller', $earning->order_id, 'earnings_released', 'Earnings released', 'Your Store earnings are now available in your balance.');
    }
    return $ok;
}

/** Void an unreleased escrow earning (cancellation / refund). */
function PT_StoreVoidEscrow($order_id) {
    global $db;
    $earning = $db->where('order_id', (int) $order_id)->getOne(T_STORE_EARNINGS);
    if (empty($earning) || $earning->status !== 'escrow') {
        return;
    }
    $db->where('id', $earning->id)->update(T_STORE_EARNINGS, array('status' => 'refunded'));
}

// =========================================================================
//  Seller / buyer notification helpers
// =========================================================================

function PT_StoreNotifyUser($user_id, $role, $order_id, $type, $title, $body) {
    global $db, $pt;
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }
    $db->insert(T_STORE_NOTIFICATIONS, array(
        'user_id'  => $user_id,
        'role'     => in_array($role, array('buyer', 'seller', 'admin'), true) ? $role : 'buyer',
        'order_id' => (int) $order_id,
        'type'     => PT_Secure($type),
        'title'    => PT_Secure($title),
        'body'     => PT_Secure($body),
        'seen'     => 0,
        'created_at' => time(),
    ));
    return true;
}

function PT_StoreNotifyOrderParties($order, $event) {
    global $pt;
    if (empty($order)) {
        return;
    }
    $labels = array(
        'order_placed'    => array('Order received', 'Your order %s has been received and a quotation was issued.'),
        'payment_received'=> array('Payment received', 'We received a payment for order %s.'),
        'order_cancelled' => array('Order cancelled', 'Order %s was cancelled.'),
        'order_refunded'  => array('Order refunded', 'Order %s was refunded.'),
    );
    if (isset($labels[$event])) {
        $t = $labels[$event];
        PT_StoreNotifyUser($order->buyer_id, 'buyer', $order->id, $event, $t[0], sprintf($t[1], $order->order_number));
    } else {
        PT_StoreNotifyUser($order->buyer_id, 'buyer', $order->id, $event, 'Order update', 'Order ' . $order->order_number . ' is now ' . $order->status . '.');
    }
    PT_StoreNotifyUser($order->seller_user_id, 'seller', $order->id, $event, 'New order activity', 'Order ' . $order->order_number . ' (' . $order->status . ').');
    PT_StoreEmailOrderParties($order, $event);
}

function PT_StoreEmailOrderParties($order, $event) {
    global $db, $pt;
    $buyer = $db->where('id', $order->buyer_id)->getOne(T_USERS, array('id', 'email', 'username', 'first_name', 'last_name'));
    $seller = $db->where('id', $order->seller_user_id)->getOne(T_USERS, array('email', 'username', 'first_name', 'last_name'));
    $subject = 'Jackisa Store - ' . str_replace('_', ' ', $event) . ' (' . $order->order_number . ')';
    $body = '<p>Order <b>' . htmlspecialchars($order->order_number) . '</b> is now <b>' . htmlspecialchars($order->status) . '</b>.</p>'
          . '<p>Total: ' . htmlspecialchars(PT_StoreMoneyDisplay($order->total)) . '</p>'
          . '<p>View it in your Jackisa Stream account under Store &gt; Orders.</p>';
    // The buyer's copy carries the latest business document as a PDF so every
    // stage of the order arrives with the paper trail attached.
    $buyer_attachments = array();
    $latest = PT_StoreLatestDocument($order->id);
    if (!empty($latest)) {
        $pdf = PT_StoreDocumentPdf($latest, $order, $buyer);
        if (!empty($pdf)) {
            $buyer_attachments[] = array('path' => $pdf, 'name' => ucwords(str_replace('_', ' ', $latest->doc_type)) . ' ' . $latest->number . '.pdf');
        }
    }
    if (!empty($buyer) && !empty($buyer->email)) {
        PT_StoreSendMail($buyer->email, $subject, $body, $buyer->username, $buyer_attachments);
    }
    if (!empty($seller) && !empty($seller->email)) {
        PT_StoreSendMail($seller->email, $subject, $body, $seller->username);
    }
}

function PT_StoreSendMail($to_email, $subject, $message, $to_name = '', $attachments = array()) {
    global $pt;
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    try {
        if (!function_exists('PT_SendMessage')) {
            return false;
        }
        // Derive a valid sender domain from the configured site URL; an empty
        // host would make the From header malformed and the mail silently
        // dropped by the MTA.
        $host = preg_replace('#^https?://#', '', (string) $pt->config->site_url);
        $host = preg_replace('#/.*$#', '', $host);
        if ($host === '') {
            $host = 'localhost';
        }
        $from_email = !empty($pt->config->email) ? $pt->config->email : ('no-reply@' . $host);
        return PT_SendMessage(array(
            'from_email'   => $from_email,
            'from_name'    => $pt->config->name,
            'to_email'     => $to_email,
            'to_name'      => $to_name,
            'subject'      => $subject,
            'charSet'      => 'UTF-8',
            'message_body' => $message,
            'is_html'      => true,
            'attachments'  => $attachments,
        ));
    } catch (Exception $e) {
        return false;
    }
}

// =========================================================================
//  Document email rendering
// =========================================================================

function PT_StoreDocMeta() {
    global $pt;
    return array(
        'company' => array(
            'name'    => PT_StoreParam('store_company_name', 'Jackisa Technologies Limited'),
            'address' => PT_StoreParam('store_company_address', 'Kampala, Uganda'),
            'tin'     => PT_StoreParam('store_company_tin', ''),
            'email'   => PT_StoreParam('store_company_email', !empty($pt->config->email) ? $pt->config->email : ''),
            'phone'   => PT_StoreParam('store_company_phone', ''),
        ),
    );
}

/** Render a document through the shared template engine and email it. */
function PT_StoreEmailDocument($document_id) {
    global $db;
    $doc = PT_StoreDocument($document_id);
    if (empty($doc) || !empty($doc->emailed_at)) {
        return false;
    }
    $order = $db->where('id', $doc->order_id)->getOne(T_STORE_ORDERS);
    if (empty($order)) {
        return false;
    }
    $buyer = $db->where('id', $doc->buyer_id)->getOne(T_USERS, array('id', 'email', 'username', 'first_name', 'last_name', 'phone_number'));
    if (empty($buyer) || empty($buyer->email)) {
        return false;
    }
    $html = PT_StoreRenderDocument($doc, $order, $buyer);
    if (empty($html)) {
        return false;
    }
    $labels = array(
        'quotation' => 'Quotation', 'invoice' => 'Invoice',
        'payment_credit_note' => 'Payment credit note', 'receipt' => 'Receipt',
        'cancellation_credit_note' => 'Cancellation credit note', 'refund_credit_note' => 'Refund credit note',
    );
    $label = isset($labels[$doc->doc_type]) ? $labels[$doc->doc_type] : 'Document';
    // Attach the PDF version of the document when a renderer is available.
    $attachments = array();
    $pdf = PT_StoreDocumentPdf($doc, $order, $buyer, $html);
    if (!empty($pdf)) {
        $attachments[] = array('path' => $pdf, 'name' => $label . ' ' . $doc->number . '.pdf');
    }
    $ok = PT_StoreSendMail($buyer->email, 'Jackisa Stream - ' . $label . ' ' . $doc->number, $html, $buyer->username, $attachments);
    if ($ok) {
        PT_DbGuardedRun(function () use ($db, $doc) {
            $db->where('id', $doc->id)->update(T_STORE_DOCUMENTS, array('emailed_at' => time()));
        });
    }
    return $ok;
}

/**
 * Return the cached PDF path for a document, rendering it from the HTML when
 * needed. Returns false when the bundled PDF library is unavailable.
 */
function PT_StoreDocumentPdf($doc, $order = null, $buyer = null, $html = null) {
    global $db;
    if (empty($doc)) {
        return false;
    }
    $dir = __DIR__ . '/../store_pdf';
    $target = $dir . '/doc-' . (int) $doc->id . '.pdf';
    if (is_file($target) && filesize($target) > 100) {
        return $target;
    }
    if ($html === null) {
        if ($order === null) {
            $order = $db->where('id', $doc->order_id)->getOne(T_STORE_ORDERS);
        }
        if ($buyer === null) {
            $buyer = $db->where('id', $doc->buyer_id)->getOne(T_USERS, array('id', 'email', 'username', 'first_name', 'last_name', 'phone_number'));
        }
        $html = PT_StoreRenderDocument($doc, $order, $buyer);
    }
    if (empty($html)) {
        return false;
    }
    $tmp = PT_StoreHtmlToPdf($html, 'doc-' . (int) $doc->id);
    if (empty($tmp)) {
        return false;
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (@rename($tmp, $target)) {
        return $target;
    }
    return $tmp;
}

/**
 * Minimal, self-contained renderer for the attached Jackisa document
 * templates. Supports {{var}}, dotted paths ({{doc.number}}) and
 * {{#section}}...{{/section}} conditional / loop blocks. Sections whose value
 * is truthy render once (or once per item for lists); empty ones are dropped.
 */
function PT_StoreRenderTemplate($template, $data) {
    // Loops first: {{#items}} ... {{/items}}.
    $template = preg_replace_callback('/\{\{#([a-zA-Z0-9_.]+)\}\}(.*?)\{\{\/\1\}\}/s', function ($m) use ($data) {
        $key = $m[1];
        $body = $m[2];
        $value = PT_StoreDataGet($data, $key);
        if (is_array($value)) {
            $out = '';
            foreach ($value as $row) {
                $out .= PT_StoreRenderTemplate($body, is_array($row) ? $row : array());
            }
            return $out;
        }
        return $value ? PT_StoreRenderTemplate($body, $data) : '';
    }, $template);

    // Simple values.
    $template = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($data) {
        $value = PT_StoreDataGet($data, $m[1]);
        if (is_array($value) || is_object($value)) {
            return '';
        }
        return $value === null ? '' : (string) $value;
    }, $template);

    return $template;
}

function PT_StoreDataGet($data, $path) {
    $value = $data;
    foreach (explode('.', $path) as $key) {
        if (is_array($value) && array_key_exists($key, $value)) {
            $value = $value[$key];
        } else {
            return null;
        }
    }
    return $value;
}

function PT_StoreRenderDocument($doc, $order, $buyer) {
    global $db, $pt;
    $seller = $db->where('id', $doc->seller_user_id)->getOne(T_USERS, array('id', 'username', 'email', 'first_name', 'last_name'));
    $currency = $doc->currency ?: PT_StoreCurrency();
    $meta = PT_StoreDocMeta();
    $items = PT_StoreDocumentItems($doc->id);
    $docMeta = !empty($doc->meta) ? json_decode($doc->meta, true) : array();

    $itemRows = array();
    foreach ($items as $item) {
        $itemRows[] = array(
            'type'        => htmlspecialchars($item->type),
            'description' => htmlspecialchars($item->description),
            'qty'         => PT_StoreMoney($item->qty),
            'unit_price'  => PT_StoreMoney($item->unit_price),
            'amount'      => PT_StoreMoney($item->amount),
            'service_date'=> htmlspecialchars($item->service_date),
            'duration'    => htmlspecialchars($item->duration),
            'location'    => htmlspecialchars($item->location),
            'specialist'  => htmlspecialchars($item->specialist),
        );
    }

    $invoice = null;
    if (!empty($doc->invoice_id)) {
        $invoice = PT_StoreDocument($doc->invoice_id);
    }
    $paidToDate = PT_StakeSub(PT_StakeDec($order->total), PT_StakeDec($doc->balance));
    $company = $meta['company'];

    $data = array(
        'doc' => array(
            'number'      => $doc->number,
            'date'        => gmdate('d M Y', $doc->created_at),
            'generated_at'=> gmdate('d M Y H:i') . ' UTC',
            'verify_code' => $doc->verify_code,
            'valid_until' => isset($docMeta['valid_until']) ? $docMeta['valid_until'] : '',
            'due_date'    => isset($docMeta['due_date']) ? $docMeta['due_date'] : '',
        ),
        'company' => array(
            'address' => htmlspecialchars($company['address']),
            'tin'     => htmlspecialchars($company['tin']),
            'email'   => htmlspecialchars($company['email']),
            'phone'   => htmlspecialchars($company['phone']),
        ),
        'status' => $doc->status,
        'order'  => array(
            'number'     => $order->order_number,
            'closes_at'  => isset($docMeta['closes_at']) ? $docMeta['closes_at'] : '',
        ),
        'invoice' => array('number' => !empty($invoice) ? $invoice->number : ''),
        'buyer' => array(
            'name'    => htmlspecialchars(trim((!empty($buyer->first_name) ? $buyer->first_name . ' ' . $buyer->last_name : $buyer->username))),
            'email'   => htmlspecialchars($buyer->email),
            'phone'   => htmlspecialchars(isset($buyer->phone_number) ? $buyer->phone_number : ''),
            'address' => '',
            'tin'     => '',
        ),
        'seller' => array(
            'name'   => htmlspecialchars(!empty($seller) ? trim((!empty($seller->first_name) ? $seller->first_name . ' ' . $seller->last_name : $seller->username)) : ''),
            'handle' => htmlspecialchars(!empty($seller) ? $seller->username : ''),
            'tin'    => '',
        ),
        'currency'      => htmlspecialchars($currency),
        'subtotal'      => PT_StoreMoney($doc->subtotal),
        'discount'      => PT_StoreMoney($doc->discount),
        'tax_total'     => PT_StoreMoney($doc->tax_total),
        'delivery'      => PT_StoreMoney(!empty($order->delivery_total) ? $order->delivery_total : 0),
        'tax_rate'      => PT_StoreParam('store_tax_rate', '0'),
        'total'         => PT_StoreMoney($doc->total),
        'amount'        => PT_StoreMoney($doc->amount),
        'balance'       => PT_StoreMoney($doc->balance),
        'paid_to_date'  => PT_StoreMoney($paidToDate),
        'credit_total'  => PT_StoreMoney($doc->amount),
        'payment' => array(
            'amount'    => PT_StoreMoney($doc->amount),
            'method'    => htmlspecialchars($doc->payment_method),
            'reference' => htmlspecialchars($doc->payment_reference),
            'date'      => gmdate('d M Y', $doc->created_at),
            'instructions' => 'Pay securely in the Jackisa Stream app with JPesa (mobile money) or your wallet balance.',
        ),
        'refund' => array(
            'amount'      => PT_StoreMoney(isset($docMeta['refund_amount']) ? $docMeta['refund_amount'] : $doc->amount),
            'destination' => htmlspecialchars(isset($docMeta['destination']) ? $docMeta['destination'] : 'original payment method'),
            'reason'      => htmlspecialchars($doc->reason),
            'date'        => gmdate('d M Y', $doc->created_at),
        ),
        'cancel' => array(
            'reason' => htmlspecialchars($doc->reason),
            'date'   => gmdate('d M Y', $doc->created_at),
        ),
        'items' => $itemRows,
    );

    $template = 'emails/store-' . str_replace('_', '-', $doc->doc_type);
    $page = './themes/' . $pt->config->theme . '/layout/' . $template . '.html';
    if (!file_exists($page)) {
        PT_StoreLog('missing document template: ' . $page);
        return '';
    }
    return PT_StoreRenderTemplate(file_get_contents($page), $data);
}

// =========================================================================
//  Logging
// =========================================================================

function PT_StoreLog($message) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    @file_put_contents($dir . '/store.log', gmdate('c') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// =========================================================================
//  Store cron entry point
// =========================================================================

/**
 * Run the continuous Store work:
 *   1. reconcile pending JPesa store payments
 *   2. release escrow earnings whose return window has elapsed
 *   3. close paid orders whose escrow has been released
 */
function PT_StoreRunCron() {
    global $db;
    $summary = array('payments' => 0, 'released' => 0, 'closed' => 0);

    if (function_exists('PT_JPesaReconcilePending')) {
        try {
            $summary['payments'] = PT_JPesaReconcilePending();
        } catch (Exception $e) {
        }
    }

    $now = time();
    // Release every escrow earning whose return window has elapsed, including
    // windows of zero days (digital / final-sale items), so nothing is left
    // holding forever.
    try {
        $earnings = $db->where('status', 'escrow')
            ->where('release_at', 0)
            ->where('release_at', $now, '<=', 'AND')
            ->get(T_STORE_EARNINGS);
    } catch (Exception $e) {
        $earnings = array();
    }
    foreach ($earnings as $earning) {
        if (PT_StoreReleaseEarning($earning->order_id)) {
            $summary['released']++;
        }
    }

    // Close a paid order once its escrow window is over. This is driven by the
    // order's own escrow_release_at (set from the product category's return
    // days when the order was paid), so it closes on schedule even when a
    // zero-day item never created an escrow row to "release".
    try {
        $paid = $db->where('status', 'paid')->get(T_STORE_ORDERS);
    } catch (Exception $e) {
        $paid = array();
    }
    foreach ($paid as $order) {
        $release_at = (int) $order->escrow_release_at;
        $earning = $db->where('order_id', $order->id)->getOne(T_STORE_EARNINGS);
        if (!empty($earning) && $earning->status === 'escrow') {
            // Still held: only release once the order's own window is over.
            if ($release_at > 0 && $release_at > $now) {
                continue;
            }
            PT_StoreReleaseEarning($order->id);
            $earning = $db->where('order_id', $order->id)->getOne(T_STORE_EARNINGS);
        }
        $released = !empty($earning) && $earning->status === 'released';
        // A zero-day item creates no escrow hold, so it closes on the order's
        // window alone.
        $no_escrow = empty($earning) && $release_at > 0 && $release_at <= $now;
        if ($released || $no_escrow) {
            $db->where('id', $order->id)->update(T_STORE_ORDERS, array('status' => 'closed', 'closed_at' => $now, 'updated_at' => $now));
            $summary['closed']++;
        }
    }

    PT_StoreLog('cron ' . json_encode($summary));
    return $summary;
}

/**
 * Absolute URLs for the public store surface (storefront, active listings and
 * live seller shops), consumed by the sitemap generator so search engines can
 * discover the commerce pages alongside videos and articles.
 */
function PT_StoreSitemapUrls() {
    global $db;
    $urls = array();
    if (!function_exists('PT_StoreEnabled') || !PT_StoreEnabled()) {
        return $urls;
    }
    $now = time();
    $urls[] = array('loc' => PT_Link('store'), 'time' => $now, 'priority' => '0.7');

    try {
        $products = $db->where('status', 'active')->orderBy('id', 'DESC')
            ->get(T_STORE_PRODUCTS, 20000, array('slug', 'updated_at'));
    } catch (Exception $e) {
        $products = array();
    }
    foreach ($products as $product) {
        if (empty($product->slug)) {
            continue;
        }
        $urls[] = array(
            'loc'      => PT_Link('product/' . $product->slug),
            'time'     => !empty($product->updated_at) ? (int) $product->updated_at : $now,
            'priority' => '0.8',
        );
    }

    try {
        $sellers = $db->where('status', 'active')->get(T_STORE_SELLERS, 20000, array('user_id'));
    } catch (Exception $e) {
        $sellers = array();
    }
    foreach ($sellers as $seller) {
        $username = PT_StoreSellerUsername($seller);
        if ($username === '') {
            continue;
        }
        $urls[] = array('loc' => PT_Link('@' . $username . '?page=store'), 'time' => $now, 'priority' => '0.6');
    }

    return $urls;
}

// =========================================================================
//  Immutability guard for store accounting rows (uses the shared helper)
// =========================================================================

if (!function_exists('PT_StoreGuardedRun')) {
    function PT_StoreGuardedRun(callable $callback) {
        if (function_exists('PT_DbGuardedRun')) {
            return PT_DbGuardedRun($callback);
        }
        return $callback();
    }
}