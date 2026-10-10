<?php
// Jackisa Store AJAX: buyer ordering/booking, seller catalog, reviews.
// Dispatched as aj/...?type=store&first=<action>. ajax.php already enforces
// the main-session hash for every action here.
if (!function_exists('PT_StoreEnabled') || !PT_StoreEnabled()) {
    $data = array('status' => 404, 'message' => 'Store is not available.');
    return;
}

// Persist an uploaded file under upload/store/... and return its relative path.
if (!function_exists('PT_StoreSaveUpload')) {
    function PT_StoreSaveUpload($field, $allowed_ext, $max_bytes) {
        if (empty($_FILES[$field]) || empty($_FILES[$field]['tmp_name']) || (int) $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return array('error' => 'no file');
        }
        if ((int) $_FILES[$field]['size'] > $max_bytes) {
            return array('error' => 'File is too large.');
        }
        $name = $_FILES[$field]['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            return array('error' => 'File format not supported.');
        }
        $dir = 'upload/store/' . date('Y') . '/' . date('m');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($dir . '/index.html', 'index.html');
        $base = md5($name . microtime(true)) . '.' . $ext;
        $filename = $dir . '/' . $base;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $filename)) {
            return array('error' => 'Could not store the file.');
        }
        return array('file' => $filename, 'original' => $name);
    }
}

$action = !empty($_GET['first']) ? PT_Secure($_GET['first'], 0) : '';
$data['status'] = 400;

$public_actions = array('public_product', 'locations');
if (!IS_LOGGED && !in_array($action, $public_actions, true)) {
    $data = array('status' => 401, 'message' => 'You must be logged in.');
    return;
}

switch ($action) {

    // ---------------------------------------------------------------------
    //  Reference data
    // ---------------------------------------------------------------------
    case 'locations':
        $out = array();
        foreach (PT_StoreLocations() as $loc) {
            $out[] = array('id' => (int) $loc->id, 'name' => $loc->name, 'type' => $loc->type, 'parent_id' => (int) $loc->parent_id);
        }
        $data = array('status' => 200, 'locations' => $out);
        break;

    case 'addons':
        $kind = (!empty($_POST['kind']) && $_POST['kind'] === 'service') ? 'service' : 'product';
        $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $groups = array();
        foreach (PT_StoreAddonCategories($kind) as $g) {
            $groups[(int) $g->id] = array('id' => (int) $g->id, 'name' => $g->name, 'addons' => array());
        }
        foreach (PT_StoreAddons($kind, $category_id) as $a) {
            $gid = (int) $a->addon_category_id;
            if (!isset($groups[$gid])) {
                $groups[$gid] = array('id' => $gid, 'name' => 'Other', 'addons' => array());
            }
            $groups[$gid]['addons'][] = array(
                'id' => (int) $a->id,
                'name' => $a->name,
                'description' => $a->description,
                'value' => PT_StoreAddonValueText($a),
            );
        }
        $data = array('status' => 200, 'groups' => array_values($groups));
        break;

    case 'attributes':
        $kind = (!empty($_POST['kind']) && $_POST['kind'] === 'service') ? 'service' : 'product';
        $category_id = !empty($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
        $out = array();
        foreach (PT_StoreAttributeDefs($category_id, $kind) as $def) {
            $out[] = array(
                'key' => $def->field_key,
                'label' => $def->label,
                'type' => $def->field_type,
                'options' => !empty($def->options) ? json_decode($def->options, true) : array(),
                'unit' => $def->unit,
                'placeholder' => $def->placeholder,
                'required' => (int) $def->is_required,
            );
        }
        $data = array('status' => 200, 'attributes' => $out);
        break;

    case 'quote':
        $cart = json_decode(isset($_POST['cart']) ? $_POST['cart'] : '[]', true);
        if (!is_array($cart) || empty($cart)) {
            $data = array('status' => 400, 'message' => 'Your cart is empty.');
            break;
        }
        $first = PT_StoreProduct($cart[0]['product_id']);
        if (empty($first)) {
            $data = array('status' => 400, 'message' => 'Unknown item.');
            break;
        }
        $seller = PT_StoreSellerById($first->seller_id);
        $lines = array();
        foreach ($cart as $entry) {
            $p = PT_StoreProduct($entry['product_id']);
            if (!empty($p)) {
                $lines[] = array('product' => $p, 'qty' => max(1, (int) (isset($entry['qty']) ? $entry['qty'] : 1)));
            }
        }
        $mode = isset($_POST['delivery_mode']) ? $_POST['delivery_mode'] : 'deliver';
        $loc = !empty($_POST['buyer_location_id']) ? (int) $_POST['buyer_location_id'] : PT_StoreBuyerLocationId($pt->user->id);
        $quote = PT_StoreQuoteDelivery($seller, $lines, $loc, $mode, !empty($_POST['pickup_id']) ? (int) $_POST['pickup_id'] : 0);
        $data = array(
            'status' => 200,
            'fee' => PT_StoreMoney($quote['fee']),
            'fee_raw' => $quote['fee'],
            'mode' => $quote['mode'],
            'reason' => $quote['reason'],
            'weight_grams' => $quote['weight_grams'],
        );
        break;

    // ---------------------------------------------------------------------
    //  Policy consent
    // ---------------------------------------------------------------------
    case 'accept_terms':
        // Consent is recorded once and covers both buying and selling.
        $context = (!empty($_POST['context']) && $_POST['context'] === 'buyer') ? 'buyer' : 'seller';
        $ok = PT_StoreAcceptTerms($pt->user->id, $context);
        $data = array('status' => $ok ? 200 : 400, 'message' => $ok ? 'Policies accepted.' : 'Could not record your consent.');
        break;

    case 'create_store':
        if (!PT_StoreHasAcceptedTerms($pt->user->id, 'seller')) {
            $data = array('status' => 400, 'message' => 'Accept the Store policies first.');
            break;
        }
        $seller = PT_StoreCreateSeller($pt->user->id, array(
            'store_name'   => !empty($_POST['store_name']) ? $_POST['store_name'] : $pt->user->username,
            'description'  => isset($_POST['description']) ? $_POST['description'] : '',
            'contact_email'=> !empty($_POST['contact_email']) ? $_POST['contact_email'] : $pt->user->email,
            'contact_phone'=> isset($_POST['contact_phone']) ? $_POST['contact_phone'] : '',
            'store_type'   => isset($_POST['store_type']) ? $_POST['store_type'] : 'personal',
            'location_id'  => isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0,
            'address'      => isset($_POST['address']) ? $_POST['address'] : '',
            'reg_number'   => isset($_POST['reg_number']) ? $_POST['reg_number'] : '',
            'tax_id'       => isset($_POST['tax_id']) ? $_POST['tax_id'] : '',
            'offers_delivery' => isset($_POST['offers_delivery']) ? !empty($_POST['offers_delivery']) : 1,
            'offers_pickup'   => !empty($_POST['offers_pickup']),
        ));
        $data = array('status' => !empty($seller['status']) ? 200 : 400, 'seller' => $seller, 'message' => isset($seller['message']) ? $seller['message'] : '');
        break;

    case 'save_seller':
        $result = PT_StoreUpdateSeller($pt->user->id, array(
            'store_name'   => isset($_POST['store_name']) ? $_POST['store_name'] : '',
            'description'  => isset($_POST['description']) ? $_POST['description'] : '',
            'contact_email'=> isset($_POST['contact_email']) ? $_POST['contact_email'] : '',
            'contact_phone'=> isset($_POST['contact_phone']) ? $_POST['contact_phone'] : '',
            'location_id'  => isset($_POST['location_id']) ? (int) $_POST['location_id'] : 0,
            'address'      => isset($_POST['address']) ? $_POST['address'] : '',
            'offers_delivery' => isset($_POST['offers_delivery']) ? !empty($_POST['offers_delivery']) : null,
            'offers_pickup'   => isset($_POST['offers_pickup']) ? !empty($_POST['offers_pickup']) : null,
        ));
        $data = array('status' => !empty($result['status']) ? 200 : 400, 'message' => isset($result['message']) ? $result['message'] : '');
        break;

    // Submit verification documents (a PDF for a business, an ID for personal).
    case 'verify':
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 400, 'message' => 'Create your store first.');
            break;
        }
        $required = PT_StoreRequiredDocs($seller->store_type);
        // A registered business proves itself with PDF approval documents; a
        // personal store proves identity with a national ID (image or PDF).
        $allowed = ($seller->store_type === 'business')
            ? array('pdf')
            : array('pdf', 'jpg', 'jpeg', 'png');
        $uploaded = array();
        foreach ($required as $doc_type => $label) {
            $file = PT_StoreSaveUpload('doc_' . $doc_type, $allowed, 8 * 1024 * 1024);
            if (empty($file['error'])) {
                PT_StoreAddVerificationDocument($seller->id, $pt->user->id, $doc_type, $file['file'], $file['original']);
                $uploaded[$doc_type] = true;
            }
        }
        if (empty($uploaded)) {
            $data = array('status' => 400, 'message' => 'Attach the required document(s) first.');
            break;
        }
        $db->where('id', $seller->id)->update(T_STORE_SELLERS, array(
            'verification_status' => 'submitted',
            'updated_at' => time(),
        ));
        PT_StoreNotifyUser($pt->user->id, 'seller', 0, 'verify_submitted', 'Verification submitted', 'We received your documents and will review them shortly.');
        $data = array('status' => 200, 'message' => 'Documents submitted for review.');
        break;

    // ---------------------------------------------------------------------
    //  Media uploads (product images)
    // ---------------------------------------------------------------------
    case 'upload_image':
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 400, 'message' => 'Create your store first.');
            break;
        }
        if (empty($_FILES['image'])) {
            $data = array('status' => 400, 'message' => 'No image supplied.');
            break;
        }
        $up = PT_StoreSaveUpload('image', array('jpg', 'jpeg', 'png', 'gif', 'webp'), 5 * 1024 * 1024);
        if (!empty($up['error'])) {
            $data = array('status' => 400, 'message' => $up['error']);
            break;
        }
        $data = array('status' => 200, 'file' => $up['file'], 'url' => PT_Link($up['file']));
        break;

    // ---------------------------------------------------------------------
    //  Seller catalog
    // ---------------------------------------------------------------------
    case 'save_product':
        $product_id = !empty($_POST['id']) ? (int) $_POST['id'] : 0;
        $payload = array(
            'title'        => isset($_POST['title']) ? $_POST['title'] : '',
            'kind'         => isset($_POST['kind']) ? $_POST['kind'] : 'product',
            'product_type' => isset($_POST['product_type']) ? $_POST['product_type'] : 'physical',
            'category_id'  => isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0,
            'description'  => isset($_POST['description']) ? $_POST['description'] : '',
            'price'        => isset($_POST['price']) ? $_POST['price'] : 0,
            'compare_price'=> isset($_POST['compare_price']) ? $_POST['compare_price'] : 0,
            'tax_inclusive'=> !empty($_POST['tax_inclusive']),
            'discount_type'=> isset($_POST['discount_type']) ? $_POST['discount_type'] : 'none',
            'discount_value'=> isset($_POST['discount_value']) ? $_POST['discount_value'] : 0,
            'discount_start'=> isset($_POST['discount_start']) ? $_POST['discount_start'] : '',
            'discount_end' => isset($_POST['discount_end']) ? $_POST['discount_end'] : '',
            'track_stock'  => !empty($_POST['track_stock']),
            'stock'        => isset($_POST['stock']) ? (int) $_POST['stock'] : 0,
            'weight_grams' => isset($_POST['weight_grams']) ? (int) $_POST['weight_grams'] : 0,
            'delivery_fee' => isset($_POST['delivery_fee']) ? $_POST['delivery_fee'] : 0,
            'delivery_note'=> isset($_POST['delivery_note']) ? $_POST['delivery_note'] : '',
            'delivery_mode'=> isset($_POST['delivery_mode']) ? $_POST['delivery_mode'] : 'deliver',
            'service_location_mode' => isset($_POST['service_location_mode']) ? $_POST['service_location_mode'] : '',
            'status'       => isset($_POST['status']) ? $_POST['status'] : '',
            'booking_duration_minutes' => isset($_POST['booking_duration_minutes']) ? (int) $_POST['booking_duration_minutes'] : 60,
            'booking_capacity' => isset($_POST['booking_capacity']) ? (int) $_POST['booking_capacity'] : 1,
            'booking_buffer_minutes' => isset($_POST['booking_buffer_minutes']) ? (int) $_POST['booking_buffer_minutes'] : 0,
            'member_free_delivery' => !empty($_POST['member_free_delivery']),
            'member_discount_pct'  => isset($_POST['member_discount_pct']) ? $_POST['member_discount_pct'] : 0,
            'member_addon_id' => isset($_POST['member_addon_id']) ? (int) $_POST['member_addon_id'] : 0,
        );
        if (!empty($_POST['images']) && is_array($_POST['images'])) {
            $payload['images'] = array_values(array_filter(array_map('trim', $_POST['images'])));
        } elseif (!empty($_POST['images_json'])) {
            $decoded = json_decode($_POST['images_json'], true);
            if (is_array($decoded)) {
                $payload['images'] = array_values(array_filter(array_map('trim', $decoded)));
            }
        }
        if (!empty($_POST['attributes']) && is_array($_POST['attributes'])) {
            $clean = array();
            foreach ($_POST['attributes'] as $k => $v) {
                $clean[substr((string) $k, 0, 80)] = is_array($v) ? array_map('strval', $v) : substr((string) $v, 0, 500);
            }
            $payload['attributes'] = $clean;
        }
        // The wizard posts structured JSON; fall back to classic arrays too.
        if (!empty($_POST['attributes_json'])) {
            $decoded = json_decode($_POST['attributes_json'], true);
            if (is_array($decoded)) {
                $clean = array();
                foreach ($decoded as $k => $v) {
                    $clean[substr((string) $k, 0, 80)] = is_array($v) ? array_map('strval', $v) : substr((string) $v, 0, 500);
                }
                if (!empty($clean)) {
                    $payload['attributes'] = $clean;
                }
            }
        }
        if (!empty($_POST['variants_json'])) {
            $decoded = json_decode($_POST['variants_json'], true);
            if (is_array($decoded)) {
                $variants = array();
                foreach ($decoded as $v) {
                    if (empty($v['name']) || trim((string) $v['name']) === '') {
                        continue;
                    }
                    $variants[] = array(
                        'name'        => $v['name'],
                        'options'     => isset($v['options']) ? $v['options'] : '',
                        'price_delta' => isset($v['price_delta']) ? $v['price_delta'] : 0,
                        'stock'       => isset($v['stock']) ? $v['stock'] : 0,
                    );
                }
                if (!empty($variants)) {
                    $payload['variants'] = $variants;
                }
            }
        }
        if (!empty($_POST['slot_weekday']) && is_array($_POST['slot_weekday'])) {
            $slots = array();
            foreach ($_POST['slot_weekday'] as $i => $wd) {
                $slots[] = array(
                    'weekday' => (int) $wd,
                    'start_time' => isset($_POST['slot_start'][$i]) ? $_POST['slot_start'][$i] : '09:00',
                    'end_time' => isset($_POST['slot_end'][$i]) ? $_POST['slot_end'][$i] : '10:00',
                    'capacity' => isset($_POST['slot_capacity'][$i]) ? $_POST['slot_capacity'][$i] : 1,
                );
            }
            if (!empty($slots)) {
                $payload['slots'] = $slots;
            }
        } elseif (!empty($_POST['slots_json'])) {
            $decoded = json_decode($_POST['slots_json'], true);
            if (is_array($decoded) && !empty($decoded)) {
                $payload['slots'] = $decoded;
            }
        }
        $result = PT_StoreSaveProduct($pt->user->id, $payload, $product_id);
        if (!empty($result['status'])) {
            $data = array(
                'status' => 200,
                'product_id' => $result['product_id'],
                'listing_status' => $result['listing_status'],
                'sku' => isset($result['sku']) ? $result['sku'] : '',
                'requires_approval' => !empty($result['requires_approval']),
            );
        } else {
            $data = array('status' => 400, 'message' => $result['message']);
        }
        break;

    case 'delete_product':
        $ok = PT_StoreDeleteProduct($pt->user->id, !empty($_POST['id']) ? (int) $_POST['id'] : 0);
        $data = array('status' => $ok ? 200 : 400);
        break;

    case 'save_membership':
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 400, 'message' => 'Create your store first.');
            break;
        }
        $benefit = array(
            'free_delivery' => !empty($_POST['free_delivery']) ? 1 : 0,
            'discount_pct'  => PT_StakeDec(isset($_POST['discount_pct']) ? $_POST['discount_pct'] : 0),
            'addon_id'      => !empty($_POST['addon_id']) ? (int) $_POST['addon_id'] : 0,
            'is_active'     => !empty($_POST['is_active']) ? 1 : 0,
            'updated_at'    => time(),
        );
        $existing = PT_StoreMembershipBenefit($seller->id);
        if (empty($existing)) {
            $benefit['seller_id'] = $seller->id;
            $db->insert(T_STORE_MEMBERSHIP, $benefit);
        } else {
            $db->where('seller_id', $seller->id)->update(T_STORE_MEMBERSHIP, $benefit);
        }
        $data = array('status' => 200);
        break;

    case 'save_delivery_zones':
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 400, 'message' => 'Create your store first.');
            break;
        }
        $zones = json_decode(isset($_POST['zones']) ? $_POST['zones'] : '[]', true);
        if (!is_array($zones)) {
            $zones = array();
        }
        PT_StoreSaveDeliveryZones($seller->id, $zones);
        $data = array('status' => 200, 'message' => 'Delivery zones saved.');
        break;

    case 'save_pickup_points':
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 400, 'message' => 'Create your store first.');
            break;
        }
        $points = json_decode(isset($_POST['points']) ? $_POST['points'] : '[]', true);
        if (!is_array($points)) {
            $points = array();
        }
        PT_StoreSavePickupPoints($seller->id, $points);
        $data = array('status' => 200, 'message' => 'Pickup points saved.');
        break;

    case 'attach':
        $product_id = !empty($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $product = PT_StoreProduct($product_id);
        if (empty($product) || (int) $product->user_id !== (int) $pt->user->id) {
            $data = array('status' => 400, 'message' => 'Unknown listing.');
            break;
        }
        $target_type = isset($_POST['target_type']) ? $_POST['target_type'] : 'video';
        $target_id   = !empty($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
        $ok = PT_StoreAttachProduct($product_id, $target_type, $target_id, $product->seller_id);
        $data = array('status' => $ok ? 200 : 400);
        break;

    case 'detach':
        $ok = PT_StoreDetachProduct($pt->user->id, !empty($_POST['product_id']) ? (int) $_POST['product_id'] : 0, isset($_POST['target_type']) ? $_POST['target_type'] : 'video', !empty($_POST['target_id']) ? (int) $_POST['target_id'] : 0);
        $data = array('status' => $ok ? 200 : 400);
        break;

    // Publish/replace the merch attached to one video, live or article. The
    // editor posts the full chosen set; anything else owned by the seller is
    // detached. Rejects targets the caller does not own.
    case 'attach_publish':
        $target_type = isset($_POST['target_type']) && in_array($_POST['target_type'], array('video', 'live', 'article'), true) ? $_POST['target_type'] : 'video';
        $target_id   = !empty($_POST['target_id']) ? (int) $_POST['target_id'] : 0;
        if ($target_id <= 0 || !PT_StoreOwnsTarget($pt->user->id, $target_type, $target_id)) {
            $data = array('status' => 400, 'message' => 'Unknown target.');
            break;
        }
        $wanted = !empty($_POST['product_ids']) && is_array($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 400, 'message' => 'Create your store first.');
            break;
        }
        $mine = array();
        foreach (PT_StoreSellerProducts($seller->id, null, 500) as $p) {
            $mine[(int) $p->id] = true;
        }
        $kept = array();
        foreach ($wanted as $pid) {
            if (isset($mine[$pid])) {
                PT_StoreAttachProduct($pid, $target_type, $target_id, $seller->id);
                $kept[$pid] = true;
            }
        }
        foreach (PT_StoreAttachmentsFor($target_type, $target_id, 100) as $attached) {
            if (!isset($kept[(int) $attached->id])) {
                PT_StoreDetachProduct($pt->user->id, (int) $attached->id, $target_type, $target_id);
            }
        }
        $data = array('status' => 200, 'count' => count($kept));
        break;

    case 'my_products':
        $seller = PT_StoreSellerByUser($pt->user->id);
        if (empty($seller)) {
            $data = array('status' => 200, 'products' => array());
            break;
        }
        $products = PT_StoreSellerProducts($seller->id, null, 200);
        $out = array();
        foreach ($products as $p) {
            $out[] = array(
                'id' => (int) $p->id,
                'title' => $p->title,
                'kind' => $p->kind,
                'status' => $p->status,
                'sku' => $p->sku,
                'price' => PT_StoreMoney($p->price),
            );
        }
        $data = array('status' => 200, 'products' => $out);
        break;

    // One owned listing, fully populated for the edit wizard.
    case 'product':
        $product = PT_StoreProduct(!empty($_POST['id']) ? (int) $_POST['id'] : 0);
        if (empty($product) || (int) $product->user_id !== (int) $pt->user->id) {
            $data = array('status' => 400, 'message' => 'Unknown listing.');
            break;
        }
        $images = array();
        foreach (PT_StoreProductImages($product->id) as $img) {
            $images[] = PT_Link($img->image);
        }
        $variants = array();
        foreach (PT_StoreProductVariants($product->id) as $v) {
            $variants[] = array('name' => $v->name, 'options' => $v->options, 'price_delta' => PT_StoreMoney($v->price_delta), 'stock' => (int) $v->stock);
        }
        $slots = array();
        try {
            foreach ($db->where('product_id', $product->id)->orderBy('id', 'ASC')->get(T_STORE_PRODUCT_SLOTS) as $s) {
                $slots[] = array('weekday' => (int) $s->weekday, 'start_time' => $s->start_time, 'end_time' => $s->end_time, 'capacity' => (int) $s->capacity);
            }
        } catch (Exception $e) {
        }
        $data = array(
            'status' => 200,
            'product' => array(
                'id' => (int) $product->id,
                'title' => $product->title,
                'kind' => $product->kind,
                'product_type' => $product->product_type,
                'category_id' => (int) $product->category_id,
                'description' => (string) $product->description,
                'sku' => (string) $product->sku,
                'price' => PT_StoreMoney($product->price),
                'compare_price' => PT_StoreMoney($product->compare_price),
                'tax_inclusive' => (int) $product->tax_inclusive,
                'stock' => (int) $product->stock,
                'weight_grams' => (int) $product->weight_grams,
                'delivery_mode' => $product->delivery_mode,
                'service_location_mode' => $product->service_location_mode,
                'booking_duration_minutes' => (int) $product->booking_duration_minutes,
                'booking_capacity' => (int) $product->booking_capacity,
                'booking_buffer_minutes' => (int) $product->booking_buffer_minutes,
                'attributes' => json_decode((string) $product->attributes, true) ?: new stdClass(),
                'images' => $images,
                'variants' => $variants,
                'slots' => $slots,
            ),
        );
        break;

    // ---------------------------------------------------------------------
    //  Slots for a service
    // ---------------------------------------------------------------------
    case 'slots':
        $product = PT_StoreProduct(!empty($_POST['product_id']) ? (int) $_POST['product_id'] : 0);
        if (empty($product) || $product->kind !== 'service') {
            $data = array('status' => 400, 'slots' => array());
            break;
        }
        $slots = PT_StoreAvailableSlots($product, 45);
        $out = array();
        foreach ($slots as $s) {
            $out[] = array('start' => $s['start'], 'label' => gmdate('D j M, H:i', $s['start']), 'remaining' => $s['remaining']);
        }
        $data = array('status' => 200, 'slots' => $out);
        break;

    // ---------------------------------------------------------------------
    //  Buyer: order / book
    // ---------------------------------------------------------------------
    case 'order':
        $cart = json_decode(isset($_POST['cart']) ? $_POST['cart'] : '[]', true);
        if (!is_array($cart) || empty($cart)) {
            $data = array('status' => 400, 'message' => 'Your cart is empty.');
            break;
        }
        $buyer_location = !empty($_POST['buyer_location_id']) ? (int) $_POST['buyer_location_id'] : 0;
        $options = array(
            'shipping' => !empty($_POST['shipping']) ? json_decode($_POST['shipping'], true) : array(),
            'buyer_note' => isset($_POST['buyer_note']) ? $_POST['buyer_note'] : '',
            'delivery_mode' => isset($_POST['delivery_mode']) ? $_POST['delivery_mode'] : 'deliver',
            'buyer_location_id' => $buyer_location,
            'pickup_id' => !empty($_POST['pickup_id']) ? (int) $_POST['pickup_id'] : 0,
            'buyer_address' => isset($_POST['buyer_address']) ? $_POST['buyer_address'] : '',
        );
        $result = PT_StoreBuildOrder($pt->user->id, $cart, $options);
        if (empty($result['status'])) {
            $data = array('status' => 400, 'message' => $result['message']);
            break;
        }
        if ($buyer_location > 0) {
            PT_StoreRememberBuyerLocation($pt->user->id, $buyer_location);
        }
        $order = $result['order'];
        $fee_split = PT_StoreJPesaFeeSplit($order->total);
        $data = array(
            'status'       => 200,
            'order_id'     => $result['order_id'],
            'order_number' => $order->order_number,
            'subtotal'     => PT_StoreMoney($order->subtotal),
            'discount'     => PT_StoreMoney($order->discount_total),
            'delivery'     => PT_StoreMoney($order->delivery_total),
            'tax'          => PT_StoreMoney($order->tax_total),
            'fee'          => PT_StoreMoney($fee_split['fee']),
            'total'        => PT_StoreMoney($order->total),
            'charged'      => PT_StoreMoney($fee_split['charged']),
        );
        break;

    case 'pay_jpesa':
        require_once 'assets/includes/jpesa_config.php';
        $order = $db->where('id', !empty($_POST['order_id']) ? (int) $_POST['order_id'] : 0)->getOne(T_STORE_ORDERS);
        if (empty($order) || (int) $order->buyer_id !== (int) $pt->user->id) {
            $data = array('status' => 400, 'message' => 'Order not found.');
            break;
        }
        if (in_array($order->status, array('paid', 'closed'), true)) {
            $data = array('status' => 400, 'message' => 'This order is already paid.');
            break;
        }
        $mobile = isset($_POST['mobile']) && is_string($_POST['mobile']) ? trim($_POST['mobile']) : '';
        $amount = PT_StakeDec($order->total);
        if (function_exists('PT_SecurityRateLimit') && !PT_SecurityRateLimit('store_jpesa:' . $pt->user->id, 10, 600)) {
            $data = array('status' => 429, 'message' => 'Too many attempts. Please wait.');
            break;
        }
        if (!preg_match('/^\+?[0-9]{9,15}$/', $mobile)) {
            $data = array('status' => 400, 'message' => 'Enter a valid mobile number.');
            break;
        }
        $jpesa_currency = !empty($pt->config->jpesa_currency) ? $pt->config->jpesa_currency : $jpesa_currency;
        $mobile = ltrim($mobile, '+');
        if (substr($mobile, 0, 1) == '0') {
            $mobile = '256' . substr($mobile, 1);
        }
        if (strlen($mobile) < 12 || strlen($mobile) > 15 || !in_array(substr($mobile, 0, 3), array('256', '254'))) {
            $data = array('status' => 400, 'message' => 'Enter a valid Uganda or Kenya number.');
            break;
        }
        $pay_amount = round((float) $amount, 2);
        // Same JPesa service-fee gross-up the wallet refill uses, via one helper
        // so the charged amount the buyer sees cannot drift from what is sent.
        $fee_split = PT_StoreJPesaFeeSplit($amount);
        $charged_amount = round((float) $fee_split['charged'], 2);
        $merchant_transaction = 'SO' . $order->id . strtoupper(bin2hex(random_bytes(6)));
        $xml = '<?xml version="1.0" encoding="UTF-8"?><g7bill><_key_>' . htmlspecialchars($jpesa_api_key, ENT_XML1) . '</_key_><cmd>account</cmd><action>credit</action><pt>mm</pt><mobile>' . htmlspecialchars($mobile, ENT_XML1) . '</mobile><amount>' . htmlspecialchars(number_format($charged_amount, 2, '.', ''), ENT_XML1) . '</amount><callback>' . htmlspecialchars(PT_Link('aj/wallet/jpesa_ipn'), ENT_XML1) . '</callback><tx>' . htmlspecialchars($merchant_transaction, ENT_XML1) . '</tx><description>Jackisa Store ' . htmlspecialchars($order->order_number, ENT_XML1) . '</description></g7bill>';
        $ch = curl_init($jpesa_api_url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $xml, CURLOPT_HTTPHEADER => array('Content-Type: text/xml'), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60));
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        if (empty($curl_error) && !empty($result['tid']) && !empty($result['api_status']) && strtolower($result['api_status']) == 'success') {
            $db->insert(T_JPESA_PAYMENTS, array(
                'user_id' => $pt->user->id, 'transaction_id' => $result['tid'],
                'merchant_transaction_id' => $merchant_transaction, 'amount' => $pay_amount,
                'charged_amount' => $charged_amount, 'currency' => $jpesa_currency,
                'purchase_type' => 'store', 'order_id' => $order->id,
                'target_user_id' => $order->seller_user_id, 'status' => 'pending', 'created_at' => time(),
            ));
            $data = array('status' => 200, 'transaction_id' => $result['tid'], 'message' => 'Approve the payment on your phone to complete the order.');
        } else {
            $data = array('status' => 400, 'message' => !empty($curl_error) ? 'JPesa is temporarily unavailable.' : (!empty($result['msg']) ? $result['msg'] : 'JPesa rejected the request.'));
        }
        break;

    case 'pay_wallet':
        $order = $db->where('id', !empty($_POST['order_id']) ? (int) $_POST['order_id'] : 0)->getOne(T_STORE_ORDERS);
        if (empty($order) || (int) $order->buyer_id !== (int) $pt->user->id) {
            $data = array('status' => 400, 'message' => 'Order not found.');
            break;
        }
        $wallet = PT_StakeDec($pt->user->wallet);
        if (PT_StakeCmp($wallet, $order->total) < 0) {
            $data = array('status' => 400, 'message' => 'Your wallet balance is too low.');
            break;
        }
        $reference = PT_StoreRef('WAL');
        PT_DbGuardedRun(function () use ($db, $pt, $order) {
            $db->where('id', $pt->user->id)->update(T_USERS, array('wallet' => $db->dec($order->total)));
        });
        $ok = PT_StoreSettlePaidOrder($order->id, $order->total, 'wallet', $reference);
        $data = $ok ? array('status' => 200, 'message' => 'Paid from your wallet.') : array('status' => 400, 'message' => 'Could not settle the order.');
        break;

    case 'cancel_order':
        $order = $db->where('id', !empty($_POST['order_id']) ? (int) $_POST['order_id'] : 0)->getOne(T_STORE_ORDERS);
        if (empty($order) || ((int) $order->buyer_id !== (int) $pt->user->id && (int) $order->seller_user_id !== (int) $pt->user->id && !PT_IsAdmin())) {
            $data = array('status' => 400, 'message' => 'Order not found.');
            break;
        }
        $ok = PT_StoreCancelOrder($order, isset($_POST['reason']) ? $_POST['reason'] : '', $pt->user->id);
        $data = array('status' => $ok ? 200 : 400, 'message' => $ok ? 'Order cancelled.' : 'This order cannot be cancelled.');
        break;

    case 'set_order_status':
        $order = $db->where('id', !empty($_POST['order_id']) ? (int) $_POST['order_id'] : 0)->getOne(T_STORE_ORDERS);
        if (empty($order) || (int) $order->seller_user_id !== (int) $pt->user->id) {
            $data = array('status' => 400, 'message' => 'Order not found.');
            break;
        }
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        $ok = PT_StoreSetOrderStatus($order, $status, $pt->user->id, isset($_POST['reason']) ? $_POST['reason'] : '');
        $data = array(
            'status' => $ok ? 200 : 400,
            'message' => $ok ? 'Order updated.' : 'That status change is not allowed from "' . $order->status . '".',
        );
        break;

    // ---------------------------------------------------------------------
    //  Reviews
    // ---------------------------------------------------------------------
    case 'review':
        $result = PT_StoreSaveReview(
            $pt->user->id,
            !empty($_POST['product_id']) ? (int) $_POST['product_id'] : 0,
            isset($_POST['rating']) ? (int) $_POST['rating'] : 5,
            isset($_POST['title']) ? $_POST['title'] : '',
            isset($_POST['body']) ? $_POST['body'] : ''
        );
        $data = array('status' => !empty($result['status']) ? 200 : 400, 'message' => isset($result['message']) ? $result['message'] : '');
        break;

    case 'document':
        $doc = PT_StoreDocument(!empty($_GET['id']) ? (int) $_GET['id'] : 0);
        if (empty($doc)) {
            $data = array('status' => 404, 'message' => 'Document not found.');
            break;
        }
        $order = $db->where('id', $doc->order_id)->getOne(T_STORE_ORDERS);
        if (empty($order) || ((int) $order->buyer_id !== (int) $pt->user->id && (int) $order->seller_user_id !== (int) $pt->user->id && !PT_IsAdmin())) {
            $data = array('status' => 403, 'message' => 'Not allowed.');
            break;
        }
        $buyer = $db->where('id', $doc->buyer_id)->getOne(T_USERS);
        $pdf = PT_StoreDocumentPdf($doc, $order, $buyer);
        if (!empty($pdf)) {
            $labels = array(
                'quotation' => 'Quotation', 'invoice' => 'Invoice',
                'payment_credit_note' => 'Payment-credit-note', 'receipt' => 'Receipt',
                'cancellation_credit_note' => 'Cancellation-credit-note', 'refund_credit_note' => 'Refund-credit-note',
            );
            $label = isset($labels[$doc->doc_type]) ? $labels[$doc->doc_type] : 'Document';
            $filename = $label . '-' . $doc->number . '.pdf';
            $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($pdf));
            readfile($pdf);
            exit();
        }
        // Renderer unavailable: serve the HTML version instead.
        header('Content-Type: text/html; charset=utf-8');
        echo PT_StoreRenderDocument($doc, $order, $buyer);
        exit();

    default:
        $data = array('status' => 404, 'message' => 'Unknown Store action.');
        break;
}

header('Content-Type: application/json');
echo json_encode($data);
exit();
