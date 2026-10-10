<?php
// Jackisa Store: public product / service detail.
if (!function_exists('PT_StoreEnabled') || !PT_StoreEnabled()) {
    header("Location: " . PT_Link('404'));
    exit();
}

$product = null;
if (!empty($_GET['id'])) {
    $product = PT_StoreProductBySlug($_GET['id']);
    if (empty($product) && ctype_digit((string) $_GET['id'])) {
        $product = PT_StoreProduct((int) $_GET['id']);
    }
}
if (empty($product) || $product->status !== 'active') {
    header("Location: " . PT_Link('404'));
    exit();
}

$buyer_id = IS_LOGGED ? $pt->user->id : 0;
$view     = PT_StorePublicProduct($product, $buyer_id);
$currency = PT_StoreCurrencySymbol();
$is_owner = IS_LOGGED && (int) $product->user_id === (int) $pt->user->id;

$reviews_html = '';
foreach ($view['reviews'] as $review) {
    $reviewer = $review->user;
    $reviews_html .= PT_LoadPage('store/partials/review-row', array(
        'R_RATING' => (int) $review->rating,
        'R_STARS'  => str_repeat('&#9733;', (int) $review->rating) . str_repeat('&#9734;', 5 - (int) $review->rating),
        'R_TITLE'  => htmlspecialchars($review->title),
        'R_BODY'   => nl2br(htmlspecialchars($review->body)),
        'R_USER'   => htmlspecialchars(!empty($reviewer) ? $reviewer->username : 'Member'),
        'R_DATE'   => gmdate('d M Y', $review->created_at),
    ));
}

$gallery_html = '';
foreach ($view['images'] as $image) {
    $gallery_html .= PT_LoadPage('store/partials/gallery-image', array(
        'IMAGE' => htmlspecialchars($image->image),
    ));
}

$variants_html = '';
foreach ($view['variants'] as $variant) {
    $price = PT_StakeAdd($view['price'], PT_StakeDec($variant->price_delta));
    $variants_html .= '<option value="' . $variant->id . '" data-delta="' . htmlspecialchars($variant->price_delta) . '">' . htmlspecialchars($variant->name) . ' &mdash; ' . $currency . ' ' . PT_StoreMoney($price) . '</option>';
}

$slots_html = '';
if ($product->kind === 'service') {
    $slots = PT_StoreAvailableSlots($product, 45);
    foreach ($slots as $slot) {
        if ($slot['remaining'] <= 0) {
            continue;
        }
        $slots_html .= '<option value="' . $slot['start'] . '">' . gmdate('D j M, H:i', $slot['start']) . ' (' . $slot['remaining'] . ' left)</option>';
    }
}

$benefit = $view['benefit'];
$benefit_html = '';
if (!empty($benefit)) {
    if (!empty($benefit->free_delivery)) {
        $benefit_html .= '<span class="badge badge-light mr-1">Free delivery for subscribers</span>';
    }
    if (PT_StakeCmp($benefit->discount_pct, '0') > 0) {
        $benefit_html .= '<span class="badge badge-light mr-1">' . number_format((float) $benefit->discount_pct, 0) . '% subscriber discount</span>';
    }
    if (!empty($benefit->addon_text)) {
        $benefit_html .= '<span class="badge badge-light mr-1">' . htmlspecialchars($benefit->addon_text) . '</span>';
    }
}

$owner_products_html = '';
if ($is_owner) {
    $seller = PT_StoreSellerByUser($pt->user->id);
    if (!empty($seller)) {
        foreach (PT_StoreSellerProducts($seller->id, null, 100) as $p) {
            $owner_products_html .= '<option value="' . $p->id . '">' . htmlspecialchars($p->title) . '</option>';
        }
    }
}

// Structured, category-specific specs.
$attr_rows = '';
foreach ($view['attributes'] as $attr) {
    $attr_rows .= '<tr><th>' . htmlspecialchars($attr['label']) . '</th><td>' . htmlspecialchars($attr['value']) . (empty($attr['unit']) ? '' : ' ' . htmlspecialchars($attr['unit'])) . '</td></tr>';
}

// The free add-on this listing grants subscribers (a catalogue choice).
$addon_html = '';
if (!empty($view['addon'])) {
    $addon_html = '<span class="badge badge-light mr-1">Free for subscribers: ' . htmlspecialchars($view['addon']->name) . '</span>';
}

$delivery_mode = $product->kind === 'service' ? 'service' : (!empty($product->delivery_mode) ? $product->delivery_mode : 'deliver');
$buyer_location_id = IS_LOGGED ? PT_StoreBuyerLocationId($pt->user->id) : 0;
$buyer_location_name = '';
if ($buyer_location_id) {
    $bloc = PT_StoreLocation($buyer_location_id);
    if (!empty($bloc)) {
        $buyer_location_name = $bloc->name;
    }
}
$pickup_html = '';
if ($product->kind !== 'service' && $delivery_mode !== 'deliver') {
    foreach (PT_StorePickupPoints($product->seller_id) as $point) {
        $pickup_html .= '<option value="' . $point->id . '">' . htmlspecialchars($point->name) . (PT_StakeCmp(PT_StakeDec($point->fee), '0') > 0 ? ' (+' . $currency . ' ' . PT_StoreMoney($point->fee) . ')' : '') . '</option>';
    }
}

$pt->page        = 'product';
$pt->page_url_   = $pt->config->site_url . '/product/' . $product->slug;
$pt->title       = $product->title . ' | ' . $pt->config->title;
$pt->description = mb_substr(strip_tags((string) $product->description), 0, 160);
$pt->keyword     = $pt->config->keyword;

$pt->content = PT_LoadPage('store/product', array(
    'CURRENCY'        => $currency,
    'P_ID'            => $product->id,
    'P_TITLE'         => htmlspecialchars($product->title),
    'P_DESCRIPTION'   => nl2br(htmlspecialchars((string) $product->description)),
    'P_KIND'          => $product->kind,
    'P_URL'           => PT_Link('product/' . $product->slug),
    'P_PRICE'         => $currency . ' ' . PT_StoreMoney($view['price']),
    'P_BASE_PRICE'    => $currency . ' ' . PT_StoreMoney($view['base_price']),
    'P_HAS_DISCOUNT'  => PT_StakeCmp($view['base_price'], $view['price']) > 0 ? 1 : 0,
    'P_DELIVERY'      => PT_StakeCmp(PT_StakeDec($product->delivery_fee), '0') > 0 ? $currency . ' ' . PT_StoreMoney($product->delivery_fee) : '',
    'P_RATING'        => $view['rating']['count'] > 0 ? number_format($view['rating']['average'], 1) : '',
    'P_RATING_C'      => $view['rating']['count'],
    'GALLERY_HTML'    => $gallery_html,
    'GALLERY_EMPTY'   => empty($gallery_html) ? 1 : 0,
    'VARIANTS_HTML'   => $variants_html,
    'HAS_VARIANTS'    => empty($variants_html) ? 0 : 1,
    'SLOTS_HTML'      => $slots_html,
    'IS_SERVICE'      => $product->kind === 'service' ? 1 : 0,
    'BENEFIT_HTML'    => $benefit_html,
    'REVIEWS_HTML'    => $reviews_html,
    'REVIEWS_COUNT'   => $view['rating']['count'],
    'ATTR_ROWS'       => $attr_rows,
    'HAS_ATTRS'       => empty($attr_rows) ? 0 : 1,
    'ADDON_HTML'      => $addon_html,
    'DELIVERY_MODE'   => $delivery_mode,
    'BUYER_LOCATION_ID' => $buyer_location_id,
    'BUYER_LOCATION_NAME' => htmlspecialchars($buyer_location_name),
    'LOCATIONS_HTML'  => PT_StoreLocationOptions($buyer_location_id),
    'PICKUP_HTML'     => $pickup_html,
    'HAS_PICKUP'      => empty($pickup_html) ? 0 : 1,
    'SELLER_NAME'     => htmlspecialchars(!empty($view['seller']) ? $view['seller']->store_name : ''),
    'SELLER_URL'      => !empty($view['seller_user']) ? PT_Link('@' . $view['seller_user']->username) : '#',
    'SELLER_USERNAME' => !empty($view['seller_user']) ? htmlspecialchars($view['seller_user']->username) : '',
    'STORE_URL'       => !empty($view['seller_user']) ? PT_Link('@' . $view['seller_user']->username . '?page=store') : '#',
    'IS_OWNER'        => $is_owner ? 1 : 0,
    'CAN_BUY'         => (IS_LOGGED && !$is_owner) ? 1 : 0,
    'HAS_BUYER_TERMS' => (IS_LOGGED && PT_StoreHasAcceptedTerms($pt->user->id, 'buyer')) ? 1 : 0,
    'TERMS_URL'       => htmlspecialchars(PT_StoreParam('store_terms_url', 'https://jackisa.com/terms/terms')),
    'OWNER_PRODUCTS'  => $owner_products_html,
));
