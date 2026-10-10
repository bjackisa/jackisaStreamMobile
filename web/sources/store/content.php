<?php
// Jackisa Store: storefront, seller dashboard and buyer orders.
if (!function_exists('PT_StoreEnabled') || !PT_StoreEnabled()) {
    header("Location: " . PT_Link('404'));
    exit();
}

$pt->page        = 'store';
$pt->page_url_   = $pt->config->site_url . '/store';
$pt->title       = $pt->config->name . ' | ' . $lang_array['store'];
$pt->description = $pt->config->description;
$pt->keyword     = $pt->config->keyword;
$terms_url       = PT_StoreParam('store_terms_url', 'https://jackisa.com/terms/terms');

$currency   = PT_StoreCurrencySymbol();
$tab        = 'browse';
if (!empty($_GET['tab']) && in_array($_GET['tab'], array('browse', 'myshop', 'orders', 'sell'), true)) {
    $tab = $_GET['tab'];
}
$seller     = IS_LOGGED ? PT_StoreSellerByUser($pt->user->id) : null;
$is_seller  = !empty($seller);
$has_seller_terms = IS_LOGGED ? PT_StoreHasAcceptedTerms($pt->user->id, 'seller') : false;
$has_buyer_terms  = IS_LOGGED ? PT_StoreHasAcceptedTerms($pt->user->id, 'buyer') : false;

// Browse: the whole marketplace, newest approved listings.
$browse_html = '';
$kind_filter = (!empty($_GET['kind']) && in_array($_GET['kind'], array('product', 'service'), true)) ? $_GET['kind'] : '';
$category_filter = !empty($_GET['category']) ? (int) $_GET['category'] : 0;
try {
    $q = $db->where('status', 'active');
    if (!empty($kind_filter)) {
        $q = $q->where('kind', $kind_filter);
    }
    if (!empty($category_filter)) {
        $q = $q->where('category_id', $category_filter);
    }
    $browse_products = $q->orderBy('is_featured', 'DESC')->orderBy('id', 'DESC')->get(T_STORE_PRODUCTS, 60);
} catch (Exception $e) {
    $browse_products = array();
}
foreach ($browse_products as $product) {
    $browse_rating = PT_StoreProductRating($product->id);
    $browse_seller = PT_StoreSellerById($product->seller_id);
    $browse_html .= PT_LoadPage('store/partials/product-card', array(
        'P_ID'       => $product->id,
        'P_SLUG'     => htmlspecialchars($product->slug),
        'P_TITLE'    => htmlspecialchars($product->title),
        'P_IMAGE'    => htmlspecialchars(PT_StorePrimaryImage($product)),
        'P_CURRENCY' => $currency,
        'P_PRICE'    => PT_StoreMoney(PT_StoreEffectivePrice($product, false)),
        'P_WAS'      => PT_StakeCmp(PT_StakeDec($product->compare_price), PT_StakeDec($product->price)) > 0 ? PT_StoreMoney($product->compare_price) : '',
        'P_KIND'     => $product->kind,
        'P_TYPE'     => $product->product_type,
        'P_URL'      => PT_Link('product/' . $product->slug),
        'P_SELLER'   => !empty($browse_seller) ? htmlspecialchars($browse_seller->store_name) : '',
        'P_SELLER_URL' => !empty($browse_seller) ? PT_Link('@' . PT_StoreSellerUsername($browse_seller) . '?page=store') : '#',
        'P_SELLER_LOAD' => !empty($browse_seller) ? '?link1=timeline&id=' . urlencode(PT_StoreSellerUsername($browse_seller)) . '&page=store' : '',
        'P_CTA_ICON' => PT_StoreBasketIcon(),
        'P_RATING'   => $browse_rating['count'] > 0 ? number_format($browse_rating['average'], 1) : '',
        'P_RATING_C' => $browse_rating['count'],
    ));
}

// Category filter chips: browse top-level categories, then drill into the
// children of the selected one (no 160-chip wall).
$categories_html = '';
$cat_parent = 0;
if (!empty($category_filter)) {
    $current_cat = PT_StoreCategory($category_filter);
    if (!empty($current_cat)) {
        $cat_parent = (int) $current_cat->parent_id;
    }
}
$cat_chips = !empty($cat_parent) ? PT_StoreChildCategories($cat_parent, $kind_filter ?: null) : PT_StoreTopCategories($kind_filter ?: null);
$cat_qs = $kind_filter ? '&kind=' . urlencode($kind_filter) : '';
if (!empty($cat_parent)) {
    $back_href = PT_Link('store' . (!empty($kind_filter) ? '?kind=' . urlencode($kind_filter) : ''));
    $back_load = '?link1=store' . $cat_qs;
    $categories_html .= '<a class="store_cat_chip" href="' . $back_href . '" data-load="' . $back_load . '">&larr; All categories</a>';
}
foreach ($cat_chips as $cat) {
    $active = ((int) $category_filter === (int) $cat->id) ? ' is-active' : '';
    $categories_html .= '<a class="store_cat_chip' . $active . '" href="' . PT_Link('store?category=' . $cat->id . $cat_qs) . '" data-load="?link1=store&category=' . $cat->id . $cat_qs . '">' . htmlspecialchars($cat->name) . '</a>';
}

// Seller dashboard state.
$my_products_html = '';
$seller_orders_html = '';
$my_orders_html = '';
$sales_total = '0';
$sales_pending = '0';
$store_status = $is_seller ? $seller->status : '';
$verification_status = $is_seller ? $seller->verification_status : '';
$store_banner_html = '';
if ($is_seller) {
    $my_products = PT_StoreSellerProducts($seller->id, null, 200);
    foreach ($my_products as $product) {
        $my_products_html .= PT_LoadPage('store/partials/my-product-card', array(
            'P_ID'      => $product->id,
            'P_TITLE'   => htmlspecialchars($product->title),
            'P_KIND'    => $product->kind,
            'P_STATUS'  => $product->status,
            'P_SKU'     => htmlspecialchars((string) $product->sku),
            'P_SLUG'    => htmlspecialchars($product->slug),
            'P_IMAGE'   => htmlspecialchars(PT_StorePrimaryImage($product)),
            'P_CURRENCY'=> $currency,
            'P_REJECT'  => !empty($product->reject_reason) ? PT_StoreRejectionReasonLabel($product->reject_reason) : '',
            'P_PRICE'   => PT_StoreMoney($product->price),
            'P_URL'     => PT_Link('product/' . $product->slug),
            'P_JSON'   => htmlspecialchars(json_encode(array(
                'id' => $product->id, 'title' => $product->title, 'kind' => $product->kind,
                'product_type' => $product->product_type, 'category_id' => $product->category_id,
                'description' => $product->description, 'sku' => $product->sku,
                'price' => PT_StoreMoney($product->price), 'compare_price' => PT_StoreMoney($product->compare_price),
                'delivery_fee' => PT_StoreMoney($product->delivery_fee), 'stock' => $product->stock,
                'track_stock' => $product->track_stock, 'member_discount_pct' => $product->member_discount_pct,
                'member_free_delivery' => $product->member_free_delivery, 'member_addon_id' => $product->member_addon_id,
                'weight_grams' => $product->weight_grams, 'tax_inclusive' => $product->tax_inclusive,
                'delivery_mode' => $product->delivery_mode, 'service_location_mode' => $product->service_location_mode,
            ))),
        ));
    }
    foreach (PT_StoreSellerOrders($pt->user->id, 100) as $order) {
        $buyer = $db->where('id', $order->buyer_id)->getOne(T_USERS, array('id', 'username', 'first_name', 'last_name'));
        $seller_orders_html .= PT_LoadPage('store/partials/order-row', array(
            'O_ID'      => $order->id,
            'O_NUMBER'  => htmlspecialchars($order->order_number),
            'O_URL'     => PT_Link('order/' . $order->order_number),
            'O_KIND'    => $order->kind,
            'O_STATUS'  => $order->status,
            'O_TOTAL'   => $currency . ' ' . PT_StoreMoney($order->total),
            'O_DATE'    => gmdate('d M Y', $order->created_at),
            'O_COUNTERPART' => htmlspecialchars(!empty($buyer) ? $buyer->username : ''),
            'O_SELLER_VIEW' => 1,
            'O_ACTIONS' => PT_StoreSellerOrderActions($order),
        ));
        if (in_array($order->status, array('paid', 'closed'), true)) {
            $sales_total = PT_StakeAdd($sales_total, $order->seller_total);
        } else {
            $sales_pending = PT_StakeAdd($sales_pending, $order->seller_total);
        }
    }

    // The store's review banner: pending review, rejected with reason, or live.
    if ($store_status === 'pending' || $verification_status === 'submitted') {
        $store_banner_html = '<div class="store_review_banner is-review mb-4"><span class="ic">' . PT_StoreBasketIcon() . '</span><div><h5 class="mb-1">Your store is under review</h5><p class="mb-0 text-muted">We are checking your details. We will email you the moment it is approved or if anything needs changing.</p></div></div>';
    } elseif ($verification_status === 'rejected' || $store_status === 'rejected') {
        $reason = !empty($seller->rejection_reason) ? PT_StoreRejectionReasonLabel($seller->rejection_reason) : '';
        $store_banner_html = '<div class="store_review_banner is-rejected mb-4"><span class="ic">' . PT_StoreBasketIcon() . '</span><div><h5 class="mb-1">Your store needs changes</h5><p class="mb-1 text-muted">' . ($reason !== '' ? 'Reason: ' . htmlspecialchars($reason) : 'Please review your details.') . '</p><a class="btn btn-sm btn-main mt-2" href="' . PT_Link('settings/store') . '">Update & resubmit</a></div></div>';
    }
}

if (IS_LOGGED) {
    foreach (PT_StoreBuyerOrders($pt->user->id, 100) as $order) {
        $my_orders_html .= PT_LoadPage('store/partials/order-row', array(
            'O_ID'     => $order->id,
            'O_NUMBER' => htmlspecialchars($order->order_number),
            'O_URL'    => PT_Link('order/' . $order->order_number),
            'O_KIND'   => $order->kind,
            'O_STATUS' => $order->status,
            'O_TOTAL'  => $currency . ' ' . PT_StoreMoney($order->total),
            'O_DATE'   => gmdate('d M Y', $order->created_at),
            'O_COUNTERPART' => $order->kind === 'booking' ? 'Booking' : 'Order',
            'O_SELLER_VIEW' => 0,
            'O_ACTIONS' => PT_StoreBuyerOrderActions($order),
        ));
    }
}

// Product form reference data.
$store_categories_html = '';
foreach (PT_StoreCategories() as $cat) {
    $store_categories_html .= '<option value="' . $cat->id . '" data-kind="' . $cat->kind . '">' . htmlspecialchars($cat->name) . '</option>';
}
$store_locations_html = PT_StoreLocationOptions(0);
$store_addons_html = '<option value="0">No add-on</option>';
foreach (PT_StoreAddons('both') as $a) {
    $store_addons_html .= '<option value="' . (int) $a->id . '">' . htmlspecialchars($a->name) . '</option>';
}

$pt->content = PT_LoadPage('store/content', array(
    'CURRENCY'          => $currency,
    'TAB'               => $tab,
    'IS_SELLER'         => $is_seller ? 1 : 0,
    'STORE_STATUS'      => htmlspecialchars((string) $store_status),
    'VERIFICATION_STATUS' => htmlspecialchars((string) $verification_status),
    'STORE_BANNER'      => $store_banner_html,
    'HAS_SELLER_TERMS'  => $has_seller_terms ? 1 : 0,
    'HAS_BUYER_TERMS'   => $has_buyer_terms ? 1 : 0,
    'STORE_NAME'        => $is_seller ? htmlspecialchars($seller->store_name) : '',
    'BROWSE_HTML'       => $browse_html,
    'CATEGORIES_HTML'   => $categories_html,
    'MY_PRODUCTS_HTML'  => $my_products_html,
    'SELLER_ORDERS_HTML'=> $seller_orders_html,
    'MY_ORDERS_HTML'    => $my_orders_html,
    'SALES_TOTAL'       => PT_StoreMoney($sales_total),
    'SALES_PENDING'     => PT_StoreMoney($sales_pending),
    'SALES_TOTAL_HTML'  => '<sup>' . $currency . '</sup>' . PT_StoreMoney($sales_total),
    'SALES_PENDING_HTML'=> '<sup>' . $currency . '</sup>' . PT_StoreMoney($sales_pending),
    'PRODUCT_COUNT'     => $is_seller ? count(PT_StoreSellerProducts($seller->id, null, 500)) : 0,
    'STORE_SELLER_EMAIL' => IS_LOGGED ? htmlspecialchars($pt->user->email) : '',
    'STORE_CONTACT_EMAIL' => IS_LOGGED ? htmlspecialchars($pt->user->email) : '',
    'STORE_DESCRIPTION' => '',
    'STORE_ADDRESS'     => '',
    'LOCATIONS_JSON'    => '[]',
    'DELIVERY_ZONES_JSON' => '[]',
    'PICKUP_POINTS_JSON' => '[]',
    'VERIFICATION_DOCS' => '[]',
    'REQUIRED_DOCS_JSON' => '{}',
    'BENEFIT_JSON'      => '{}',
    'REJECTION_REASONS_HTML' => '',
    'PRODUCT_CATEGORIES'=> $store_categories_html,
    'LOCATIONS_HTML'    => $store_locations_html,
    'ADDONS_HTML'       => $store_addons_html,
    'KIND_FILTER'       => $kind_filter,
    'CATEGORY_FILTER'   => $category_filter,
    'STORE_TERMS_URL'   => htmlspecialchars($terms_url),
    'REFUND_URL'        => htmlspecialchars(PT_StoreParam('store_refund_url', 'https://jackisa.com/terms/refund-policy')),
    'STREAM_TERMS_URL'  => htmlspecialchars($terms_url),
    'PRIVACY_URL'       => htmlspecialchars(PT_StoreParam('store_privacy_url', 'https://jackisa.com/terms/privacy-policy')),
    'LOGIN_URL'         => PT_Link('login?red=' . urlencode(PT_Link('store'))),
    'PRODUCT_URL_BASE'  => PT_Link('product'),
    'SETTINGS_STORE_URL'=> PT_Link('settings/store'),
));
