// Jackisa Store front-end: consent, product wizard, delivery, checkout.
(function ($) {
	'use strict';

	// The SPA swaps #container_content and the browser re-executes this file
	// on every navigation. Drop this plugin's previous delegated handlers
	// first, otherwise they stack up and a single click fires once per visit
	// (placing one order per visit). Per-page init below still runs each time.
	$(document).off('.ptstore');

	function hash() { return $('.main_session').val() || ''; }

	function ajax(action, payload, done) {
		$.post(site_url + '/aj/store/' + action + '?hash=' + hash(), payload || {}, function (res) {
			if (typeof done === 'function') { done(res || {}); }
		}, 'json').fail(function () {
			if (typeof done === 'function') { done({ status: 400, message: 'Request failed.' }); }
		});
	}
	window.PT_StoreAjax = ajax;

	function notify(type, message) {
		if (window.toastr && toastr[type]) { toastr[type](message); return; }
		if (typeof notifier === 'function') { notifier(type, message); return; }
		// SweetAlert2 is loaded globally by the theme. A toast keeps the user on
		// the page with no backdrop to clean up, which is what a bare error
		// needed — a modal left the page dark after it was dismissed.
		if (window.Swal && typeof Swal.fire === 'function') {
			Swal.fire({
				toast: true, position: 'top-end', icon: type === 'error' ? 'error' : 'success',
				title: message, showConfirmButton: false, timer: 4000, timerProgressBar: true
			});
			return;
		}
		// Never a native alert: fall back to an HTML banner modal.
		storeReviewModal(type === 'error' ? 'Something went wrong' : 'Notice', message);
	}
	window.PT_StoreNotify = notify;

	// Remove any modal backdrop / body lock that outlives its dialog. This
	// is what left the page dark after an action was rejected.
	function cleanupModals() {
		if ($('.modal.show').length === 0) {
			$('.modal-backdrop').remove();
			$('body').removeClass('modal-open').css('padding-right', '');
		}
	}
	$(document).on('hidden.bs.modal.ptstore', '.modal', cleanupModals);

	// --- No native confirm(): a reusable HTML confirmation modal ------------
	function confirmDialog(opts) {
		opts = opts || {};
		var id = 'store_confirm_modal';
		// Hide then remove: removing a still-shown modal leaves Bootstrap's
		// backdrop behind, which is what kept the page dark after an error.
		$('#' + id).modal('hide').remove();
		cleanupModals();
		var html = '<div class="modal fade" id="' + id + '" tabindex="-1" role="dialog" aria-hidden="true">' +
			'<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">' +
			'<div class="modal-header"><h5 class="modal-title">' + (opts.title || 'Please confirm') + '</h5>' +
			'<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>' +
			'<div class="modal-body">' + (opts.body || '') + '</div>' +
			'<div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">' + (opts.cancel || 'Cancel') + '</button>' +
			'<button type="button" class="btn btn-main store_confirm_ok">' + (opts.ok || 'Confirm') + '</button></div>' +
			'</div></div></div>';
		var $m = $(html).appendTo('body');
		var confirmed = false;
		$m.find('.store_confirm_ok').on('click', function () {
			confirmed = true;
			$m.modal('hide');
		});
		// Run the action once the dialog is fully hidden, so its backdrop is
		// gone before any response error is shown.
		$m.on('hidden.bs.modal', function () {
			$m.remove();
			cleanupModals();
			if (confirmed && typeof opts.onConfirm === 'function') { opts.onConfirm(); }
		});
		$m.modal('show');
	}
	window.PT_StoreConfirm = confirmDialog;

	// An informational HTML modal (no native alert), optionally redirecting on
	// close — used to acknowledge review submissions in place.
	function storeReviewModal(title, message, redirect) {
		var id = 'store_ack_modal';
		// Hide then remove so a repeated error never stacks a second backdrop
		// (removing a shown modal skips Bootstrap's own cleanup).
		$('#' + id).modal('hide').remove();
		cleanupModals();
		var html = '<div class="modal fade" id="' + id + '" tabindex="-1" role="dialog" aria-hidden="true">' +
			'<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">' +
			'<div class="modal-header"><h5 class="modal-title">' + title + '</h5></div>' +
			'<div class="modal-body"><p class="mb-0">' + message + '</p></div>' +
			'<div class="modal-footer"><button type="button" class="btn btn-main" data-dismiss="modal">Close</button></div>' +
			'</div></div></div>';
		var $m = $(html).appendTo('body');
		$m.on('hidden.bs.modal', function () {
			$m.remove();
			// Removing the dialog here stops the delegated hidden.bs.modal
			// cleanup from firing, so clear the backdrop/body lock explicitly.
			cleanupModals();
			if (redirect) { location.href = redirect; }
		});
		$m.modal('show');
	}
	window.PT_StoreAck = storeReviewModal;

	var currency = window.PT_STORE_CURRENCY || '';

	function money(v) {
		// Accept either a raw number or a currency-prefixed string ("UGX 236.00"), which parseFloat alone would read as NaN.
		var n = typeof v === 'number' ? v : parseFloat(String(v == null ? 0 : v).replace(/[^0-9.\-]/g, ''));
		if (!isFinite(n)) { n = 0; }
		return currency + ' ' + n.toFixed(2);
	}

	// =======================================================================
	//  Consent — recorded once for buying and selling
	// =======================================================================
	$(document).on('submit.ptstore', '.store_consent_form', function (e) {
		e.preventDefault();
		var $f = $(this);
		if (!$f.find('.store_terms_check').is(':checked')) {
			notify('error', 'Please accept the policies to continue.');
			return;
		}
		ajax('accept_terms', { context: $f.data('context') || 'seller' }, function (res) {
			if (res.status === 200) { location.reload(); }
			else { notify('error', res.message || 'Error'); }
		});
	});

	$(document).on('submit.ptstore', '.store_consent_buyer', function (e) {
		e.preventDefault();
		if (!$('.store_buyer_terms_check').is(':checked')) { notify('error', 'Please accept the policies first.'); return; }
		ajax('accept_terms', { context: 'buyer' }, function (res) {
			if (res.status === 200) { location.reload(); } else { notify('error', res.message || 'Error'); }
		});
	});

	// =======================================================================
	//  Seller: create store (with type + documents)
	// =======================================================================
	$(document).on('click.ptstore', '.store_type_card', function () {
		var type = $(this).data('type');
		$('.store_type_card').removeClass('is-active');
		$(this).addClass('is-active');
		$('.store_type_choice').val(type);
		var business = type === 'business';
		$('.store_business_fields').toggle(business);
		renderVerifyFields();
	});
	$(document).on('submit.ptstore', '.store_create_form', function (e) {
		e.preventDefault();
		var $f = $(this);
		var fd = new FormData($f[0]);
		fd.append('hash', hash());
		$.ajax({
			url: site_url + '/aj/store/create_store?hash=' + hash(),
			type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
		}).done(function (res) {
			if (res.status === 200) {
				var seller = res.seller || {};
				if (seller.store_type === 'business') {
					$('#store_create_form_wrap').slideUp();
					$('#store_business_verify').slideDown();
					notify('success', 'Now submit your business approval documents.');
				} else {
					storeReviewModal('Your store is under review',
						'Thank you. We are checking your details and will email you the moment your store is approved, or if anything needs changing.',
						site_url + '/store?tab=myshop');
				}
			} else { notify('error', res.message || 'Error'); }
		}).fail(function () { notify('error', 'Request failed.'); });
	});

	$(document).on('submit.ptstore', '.store_verify_form', function (e) {
		e.preventDefault();
		var $f = $(this);
		var fd = new FormData($f[0]);
		fd.append('hash', hash());
		$.ajax({
			url: site_url + '/aj/store/verify?hash=' + hash(),
			type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
		}).done(function (res) {
			if (res.status === 200) {
				storeReviewModal('Store submitted for review',
					'Thank you. We will email you once your store is approved, or if anything needs changing.',
					site_url + '/settings/store');
			} else { notify('error', res.message || 'Error'); }
		}).fail(function () { notify('error', 'Request failed.'); });
	});

	$(document).on('click.ptstore', '.store_verify_open', function () {
		$('a[href="#store_tab_verify"]').tab('show');
	});

	// Render the document inputs the store type requires.
        function renderVerifyFields() {
                var docs = window.PT_STORE_REQUIRED_DOCS || {};
                var $wrap = $('#store_verify_fields');
                if (!$wrap.length) { return; }
                $wrap.empty();
                $.each(docs, function (key, label) {
                        var accept = key === 'national_id' ? 'image/*,application/pdf' : 'application/pdf,image/*';
                        $wrap.append('<div class="form-group store_doc_field">' +
                                '<div class="d-flex align-items-center tag_point_earn store_doc_upload" role="button" tabindex="0">' +
                                        '<div class="d-inline-flex align-items-center justify-content-center position-relative main icon">' +
                                                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M4 7.5A2.5 2.5 0 0 1 6.5 5h2.6c.5 0 .97.24 1.26.65l.78 1.1c.29.41.76.65 1.26.65h4.1A2.5 2.5 0 0 1 19 9.9v4.6A2.5 2.5 0 0 1 16.5 17h-10A2.5 2.5 0 0 1 4 14.5v-7Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><circle cx="12" cy="11.5" r="2.6" stroke="currentColor" stroke-width="1.5"/></svg>' +
                                        '</div>' +
                                        '<div><div class="h6 mb-0">' + label + '</div>' +
                                        '<small class="mt-1 form-text text-muted d-none text-truncate store_doc_name"></small></div>' +
                                '</div>' +
                                '<input type="file" class="d-none store_doc_input" name="doc_' + key + '" accept="' + accept + '"></div>');
                });
        }
        renderVerifyFields();

        $(document).on('click.ptstore', '.store_doc_upload', function () {
                $(this).closest('.store_doc_field').find('.store_doc_input').trigger('click');
        });
        $(document).on('change.ptstore', '.store_doc_input', function () {
                var name = this.value.replace(/C:\\fakepath\\/i, '');
                $(this).closest('.store_doc_field').find('.store_doc_name').text(name).removeClass('d-none');
        });

	// =======================================================================
	//  Seller: product wizard
	// =======================================================================
	var monthDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

	function wizardReset() {
		var $w = $('#store_product_wizard');
		if (!$w.length) { return; }
		$w.find('form')[0].reset();
		$('#store_product_id').val(0);
		$('#store_product_images_json').val('[]');
		$('#store_product_attributes_json').val('{}');
		$('#store_product_variants_json').val('[]');
		$('#store_product_slots_json').val('[]');
		$('#store_upload_grid').empty();
		$('#store_attribute_fields').empty();
		$('#store_variant_fields').empty();
		$('#store_slot_fields').empty();
		$('#store_rev_kind').text('Product');
		$('#store_wizard_title').text('Add a listing');
		wizardGo(1);
	}

	function wizardGo(step) {
		$('#store_product_wizard .store_wizard_steps .step').each(function () {
			var s = parseInt($(this).data('step'), 10);
			$(this).toggleClass('is-active', s === step).toggleClass('is-done', s < step);
		});
		$('#store_product_wizard .store_wizard_panel').removeClass('is-active')
			.filter('[data-step="' + step + '"]').addClass('is-active');
		$('#store_product_wizard .store_wizard_prev').toggle(step > 1);
		// Stock only applies to physical products.
		var isService = ($('#store_product_kind').val() || 'product') === 'service';
		var isDigital = ($('#store_product_type').val() || 'physical') === 'digital';
		$('#store_product_wizard .store_stock_field').prop('hidden', isService || isDigital);
		$('#store_product_wizard .store_wizard_next').toggle(step < 5);
		$('#store_product_wizard .store_wizard_submit').toggle(step === 5);
		$('#store_product_wizard').data('step', step);
		if (step === 5) { wizardReview(); }
	}

	function wizardStep() { return parseInt($('#store_product_wizard').data('step') || 1, 10); }

	function syncKind() {
		var kind = $('#store_product_kind').val() || 'product';
		var $w = $('#store_product_wizard');
		var isService = kind === 'service';
		// A digital item never ships, so stock / weight / fulfilment are moot.
		var isDigital = !isService && ($('#store_product_type').val() || 'physical') === 'digital';
		$w.find('.store_service_only').prop('hidden', !isService);
		// A service never has physical/digital fields; a digital item never
		// ships, so weight / fulfilment / stock are moot. Disabled controls are
		// not submitted.
		$w.find('.store_product_only').prop('hidden', isService || isDigital);
		$w.find('.store_product_type_wrap').prop('hidden', isService).find('select').prop('disabled', isService);
		loadAttributes(kind);
		renderVariants();
	}

	function loadAttributes(kind) {
		var cat = $('#store_product_category').val();
		ajax('attributes', { kind: kind, category_id: cat }, function (res) {
			var $wrap = $('#store_attribute_fields');
			$wrap.empty();
			if (res.status !== 200 || !res.attributes.length) {
				$wrap.append('<p class="text-muted small">No extra specs for this category.</p>');
				return;
			}
			res.attributes.forEach(function (a) {
				var label = a.label + (a.required ? ' *' : '');
				var input;
				if (a.type === 'select' && a.options && a.options.length) {
					input = '<select class="form-control store_attr_input" data-key="' + a.key + '"><option value=""></option>';
					a.options.forEach(function (o) { input += '<option value="' + o + '">' + o + '</option>'; });
					input += '</select>';
				} else if (a.type === 'boolean') {
					input = '<select class="form-control store_attr_input" data-key="' + a.key + '"><option value=""></option><option value="Yes">Yes</option><option value="No">No</option></select>';
				} else if (a.type === 'number') {
					input = '<input type="number" class="form-control store_attr_input" data-key="' + a.key + '" placeholder="' + (a.placeholder || '') + '">';
				} else {
					input = '<input type="text" class="form-control store_attr_input" data-key="' + a.key + '" placeholder="' + (a.placeholder || '') + '">';
				}
                                $wrap.append('<div class="store_attr_row form-group">' + input +
                                        '<label>' + label + (a.unit ? ' (' + a.unit + ')' : '') + '</label></div>');
			});
		});
	}

	function collectAttributes() {
		var out = {};
		$('#store_attribute_fields .store_attr_input').each(function () {
			var v = $(this).val();
			if (v !== '' && v !== null) { out[$(this).data('key')] = v; }
		});
		$('#store_product_attributes_json').val(JSON.stringify(out));
	}

	// Optional variants (name + delta + stock) for products and services.
	function variantRow(v) {
		v = v || { name: '', price_delta: 0, stock: 0 };
		return '<div class="store_variant_row">' +
			'<input type="text" class="form-control form-control-sm v_name" placeholder="Variant (e.g. Large)" value="' + (v.name || '') + '">' +
			'<input type="number" step="0.01" class="form-control form-control-sm v_delta" placeholder="+/- price" value="' + (v.price_delta || 0) + '">' +
			'<input type="number" min="0" class="form-control form-control-sm v_stock" placeholder="Stock" value="' + (v.stock || 0) + '">' +
			'<button type="button" class="btn btn-sm btn-light store_del_variant">&times;</button></div>';
	}

	function renderVariants() {
		$('#store_variant_fields').html(
			'<div class="store_addon_group"><h6>Variants (optional)</h6><div id="store_variant_rows"></div>' +
			'<button type="button" class="btn btn-sm btn-light store_add_variant">Add variant</button></div>'
		);
	}

	function collectVariants() {
		var out = [];
		$('#store_variant_rows .store_variant_row').each(function () {
			var name = $.trim($(this).find('.v_name').val());
			if (!name) { return; }
			out.push({ name: name, price_delta: $(this).find('.v_delta').val() || 0, stock: $(this).find('.v_stock').val() || 0 });
		});
		$('#store_product_variants_json').val(JSON.stringify(out));
	}

	// Weekly availability slots for services.
	function slotRow(data) {
		data = data || { weekday: 1, start_time: '09:00', end_time: '10:00', capacity: 1 };
		var opts = monthDays.map(function (d, i) {
			return '<option value="' + i + '"' + (parseInt(data.weekday, 10) === i ? ' selected' : '') + '>' + d + '</option>';
		}).join('');
		return '<div class="d-flex align-items-center gap-2 mb-2 store_slot_row">' +
			'<select class="form-control form-control-sm w-auto slot_weekday">' + opts + '</select>' +
			'<input type="time" class="form-control form-control-sm w-auto slot_start" value="' + (data.start_time || '09:00') + '">' +
			'<input type="time" class="form-control form-control-sm w-auto slot_end" value="' + (data.end_time || '10:00') + '">' +
			'<input type="number" class="form-control form-control-sm w-auto slot_capacity" min="1" value="' + (data.capacity || 1) + '">' +
			'<button type="button" class="btn btn-sm btn-light store_del_slot">&times;</button></div>';
	}
	function collectSlots() {
		var out = [];
		$('#store_slot_fields .store_slot_row').each(function () {
			out.push({
				weekday: $(this).find('.slot_weekday').val(),
				start_time: $(this).find('.slot_start').val(),
				end_time: $(this).find('.slot_end').val(),
				capacity: $(this).find('.slot_capacity').val()
			});
		});
		$('#store_product_slots_json').val(JSON.stringify(out));
	}

	// Subscriber benefits are store-wide; the listing just inherits them.
	function renderBenefitSummary() {
		var $wrap = $('#store_benefit_summary');
		if (!$wrap.length) { return; }
		var b = window.PT_STORE_BENEFIT || {};
		var parts = [];
		if (parseInt(b.free_delivery, 10) === 1) { parts.push('Free delivery for subscribers'); }
		if (parseFloat(b.discount_pct || 0) > 0) { parts.push(parseFloat(b.discount_pct) + '% subscriber discount'); }
		if (parseInt(b.addon_id || 0, 10) > 0) {
			var label = $('#store_benefit_addon option[value="' + b.addon_id + '"]').text();
			if (label) { parts.push('Free add-on: ' + label); }
		}
		$wrap.html(parts.length
			? '<div class="small text-muted mb-1">Your subscribers get</div><div>' + parts.join(' &middot; ') + '</div>'
			: '<div class="text-muted small">No subscriber benefits set.</div>');
	}

	// Image upload with preview and main-image selection. Click, keyboard or drag-drop.
	$(document).on('click.ptstore', '#store_upload_area', function () { $('#store_image_input').trigger('click'); });
	$(document).on('keydown.ptstore', '#store_upload_area', function (e) {
		if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('#store_image_input').trigger('click'); }
	});
	$(document).on('dragover.ptstore dragenter.ptstore', '#store_upload_area', function (e) { e.preventDefault(); $(this).addClass('is-drag'); });
	$(document).on('dragleave.ptstore dragend.ptstore drop.ptstore', '#store_upload_area', function () { $(this).removeClass('is-drag'); });
	$(document).on('drop.ptstore', '#store_upload_area', function (e) {
		e.preventDefault();
		var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
		if (files && files.length) { uploadImages(files); }
	});
	$(document).on('change.ptstore', '#store_image_input', function () {
		if (this.files && this.files.length) { uploadImages(this.files); }
		$(this).val('');
	});
	function uploadImages(files) {
		Array.prototype.forEach.call(files, function (file) {
			if (!/^image\//.test(file.type || '')) { return; }
			var fd = new FormData();
			fd.append('image', file);
			fd.append('hash', hash());
			$.ajax({ url: site_url + '/aj/store/upload_image?hash=' + hash(), type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
				.done(function (res) {
					if (res.status === 200) { addImageThumb(res.url); } else { notify('error', res.message || 'Upload failed.'); }
				}).fail(function () { notify('error', 'Upload failed.'); });
		});
	}

	function currentImages() {
		try { return JSON.parse($('#store_product_images_json').val() || '[]'); } catch (e) { return []; }
	}
	function addImageThumb(url) {
		var imgs = currentImages();
		imgs.push(url);
		$('#store_product_images_json').val(JSON.stringify(imgs));
		renderThumbs();
	}
	function renderThumbs() {
		var imgs = currentImages();
		var $g = $('#store_upload_grid');
		$g.empty();
		imgs.forEach(function (url, i) {
			var $it = $('<div class="store_upload_item" data-index="' + i + '"><img src="' + url + '">' +
				'<button type="button" class="rm store_rm_image">&times;</button>' +
				(i === 0 ? '<span class="main_tag">Main</span>' : '<button type="button" class="btn btn-sm btn-light store_set_main" style="position:absolute;bottom:4px;left:4px;font-size:10px;padding:1px 6px">Set main</button>') +
				'</div>');
			$g.append($it);
		});
	}
	$(document).on('click.ptstore', '.store_rm_image', function () {
		var i = parseInt($(this).closest('.store_upload_item').data('index'), 10);
		var imgs = currentImages();
		imgs.splice(i, 1);
		$('#store_product_images_json').val(JSON.stringify(imgs));
		renderThumbs();
	});
	$(document).on('click.ptstore', '.store_set_main', function () {
		var i = parseInt($(this).closest('.store_upload_item').data('index'), 10);
		var imgs = currentImages();
		var item = imgs.splice(i, 1)[0];
		imgs.unshift(item);
		$('#store_product_images_json').val(JSON.stringify(imgs));
		renderThumbs();
	});

        function kindLabel() {
                var kind = $('#store_product_kind').val() || 'product';
                if (kind === 'service') { return 'Service / booking'; }
                var type = $('#store_product_type').val() || 'physical';
                return type === 'digital' ? 'Digital product' : 'Physical product';
        }
        function wizardReview() {
		var price = parseFloat($('#store_product_price').val() || 0);
		var inclusive = $('#store_product_tax_inclusive').is(':checked');
		var tax = inclusive ? price - price / 1.08 : price * 0.08;
		var net = inclusive ? price - tax : price;
		$('#store_rev_price').text(money(net) + (inclusive ? ' (+ tax incl.)' : ''));
		$('#store_rev_tax').text(money(tax) + ' (8%)');
		$('#store_rev_kind').text(kindLabel());
		renderBenefitSummary();
		collectAttributes(); collectVariants(); collectSlots();
	}

	function wizardOpen() {
		var $w = $('#store_product_wizard');
		if (!$w.length) { return; }
		$w.stop(true, true).slideDown(220);
	}
	function wizardClose() {
		var $w = $('#store_product_wizard');
		if (!$w.length) { return; }
		$w.stop(true, true).slideUp(180);
		wizardReset();
	}
	$(document).on('click.ptstore', '.store_wizard_close', function () { wizardClose(); });

	$(document).on('click.ptstore', '#store_new_product, .store_start_new', function () {
		// Ask what kind of listing first; .store_choose_kind then opens the wizard.
		$('#store_add_product_modal').modal('show');
	});
	$(document).on('click.ptstore', '.store_choose_kind', function () {
		var kind = $(this).data('kind');
		$('#store_add_product_modal').modal('hide');
		wizardOpen();
		wizardReset();
		$('#store_product_kind').val(kind).trigger('change');
		$('html, body').animate({ scrollTop: $('#store_product_wizard').offset().top - 80 }, 300);
	});
	$(document).on('change.ptstore', '#store_product_kind, #store_product_category, #store_product_type', function () {
		syncKind();
	});
	$(document).on('click.ptstore', '.store_wizard_next', function () {
		var s = wizardStep();
		if (s === 1 && !$.trim($('#store_product_title').val())) { notify('error', 'Give your listing a title.'); return; }
		if (s === 4 && !($('#store_product_price').val() > 0)) { notify('error', 'Enter a price.'); return; }
		if (s >= 5) { return; }
		wizardGo(s + 1);
	});
	$(document).on('click.ptstore', '.store_wizard_prev', function () { wizardGo(Math.max(1, wizardStep() - 1)); });
	$(document).on('click.ptstore', '.store_add_variant', function () { $('#store_variant_rows').append(variantRow()); });
	$(document).on('click.ptstore', '.store_del_variant', function () { $(this).closest('.store_variant_row').remove(); });
	$(document).on('click.ptstore', '.store_add_slot', function () { $('#store_slot_fields').append(slotRow({ weekday: 1 })); });
	$(document).on('click.ptstore', '.store_del_slot', function () { $(this).closest('.store_slot_row').remove(); });

	$(document).on('submit.ptstore', '#store_product_form', function (e) {
		e.preventDefault();
		collectAttributes(); collectVariants(); collectSlots();
		var payload = $(this).serializeArray().reduce(function (o, kv) {
			if (kv.name === 'member_addon_id') { o[kv.name] = kv.value; return o; }
			o[kv.name] = kv.value; return o;
		}, {});
		var variants = JSON.parse($('#store_product_variants_json').val() || '[]');
		var slots = JSON.parse($('#store_product_slots_json').val() || '[]');
		if (variants.length) { payload.variants_json = JSON.stringify(variants); }
		if (slots.length) { payload.slots_json = JSON.stringify(slots); }
		ajax('save_product', payload, function (res) {
			if (res.status === 200) {
				var title = res.requires_approval ? 'Listing submitted' : 'Listing saved';
				var body = res.requires_approval
					? 'Your listing is under review.'
					: 'Your listing is live in your store.';
				$('body').append('<div class="modal fade" id="store_saved_modal"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">' + title + '</h5></div><div class="modal-body"><p class="mb-1">' + body + '</p></div><div class="modal-footer"><button type="button" class="btn btn-main" data-dismiss="modal">Close</button></div></div></div></div>');
				$('#store_saved_modal').one('hidden.bs.modal', function () { location.reload(); }).modal('show');
			} else { notify('error', res.message || 'Could not save the listing.'); }
		});
	});

	// Edit an existing listing: load it into the wizard.
	$(document).on('click.ptstore', '.store-edit-product', function () {
		var id = $(this).data('id');
		ajax('product', { id: id }, function (res) {
			if (res.status !== 200) { notify('error', res.message || 'Could not load.'); return; }
			var p = res.product;
			wizardOpen();
			wizardReset();
			$('#store_product_id').val(p.id);
			$('#store_product_kind').val(p.kind);
			$('#store_product_type').val(p.product_type);
			$('#store_product_category').val(p.category_id);
			$('#store_product_title').val(p.title);
			$('#store_product_description').val(p.description);
			$('#store_product_price').val(p.price);
			$('#store_product_compare').val(p.compare_price);
			$('#store_product_stock').val(p.stock);
			$('#store_product_weight').val(p.weight_grams);
			$('#store_product_tax_inclusive').prop('checked', parseInt(p.tax_inclusive, 10) === 1);
			$('#store_product_delivery_mode').val(p.delivery_mode);
			$('#store_product_service_mode').val(p.service_location_mode);
			$('#store_product_duration').val(p.booking_duration_minutes);
			$('#store_product_capacity').val(p.booking_capacity);
			$('#store_product_images_json').val(JSON.stringify(p.images || []));
			$('#store_product_variants_json').val(JSON.stringify(p.variants || []));
			$('#store_product_attributes_json').val(JSON.stringify(p.attributes || {}));
			$('#store_wizard_title').text('Edit listing');
			renderThumbs();
			syncKind();
			(p.variants || []).forEach(function (v) { $('#store_variant_rows').append(variantRow(v)); });
			(p.slots || []).forEach(function (s) { $('#store_slot_fields').append(slotRow(s)); });
			setTimeout(function () {
				$.each(p.attributes || {}, function (k, v) {
					$('#store_attribute_fields .store_attr_input[data-key="' + k + '"]').val(v);
				});
			}, 500);
			$('a[href="#store_tab_catalogue"]').tab('show');
			$('html, body').animate({ scrollTop: $('#store_product_wizard').offset().top - 80 }, 300);
		});
	});

	$(document).on('click.ptstore', '.store-delete-product', function () {
		var id = $(this).data('id');
		confirmDialog({
			title: 'Archive this listing?', body: 'It will be hidden from your storefront.',
			ok: 'Archive', onConfirm: function () {
				ajax('delete_product', { id: id }, function (res) {
					if (res.status === 200) { location.reload(); } else { notify('error', 'Could not archive.'); }
				});
			}
		});
	});

	// =======================================================================
	//  Seller: profile, delivery zones, pickup points, benefits
	// =======================================================================
	$(document).on('submit.ptstore', '.store_profile_form', function (e) {
		e.preventDefault();
		var payload = $(this).serializeArray().reduce(function (o, kv) { o[kv.name] = kv.value; return o; }, {});
		ajax('save_seller', payload, function (res) {
			notify(res.status === 200 ? 'success' : 'error', res.status === 200 ? 'Store profile saved.' : (res.message || 'Error'));
		});
	});

	function locationOptions(sel) {
		var out = '<option value="0">— Any / whole country —</option>';
		(window.PT_STORE_LOCATIONS || []).forEach(function (l) {
			out += '<option value="' + l.id + '"' + (parseInt(sel, 10) === l.id ? ' selected' : '') + '>' + l.name + '</option>';
		});
		return out;
	}
	function zoneRow(z) {
		z = z || {};
		return '<div class="store_variant_row store_zone_row">' +
			'<select class="form-control form-control-sm z_loc">' + locationOptions(z.location_id) + '</select>' +
			'<input type="number" step="0.01" class="form-control form-control-sm z_base" placeholder="Base fee" value="' + (z.base_fee || 0) + '">' +
			'<input type="number" step="0.01" class="form-control form-control-sm z_kg" placeholder="Per kg" value="' + (z.per_kg_fee || 0) + '">' +
			'<input type="number" step="0.01" class="form-control form-control-sm z_free" placeholder="Free over" value="' + (z.free_over || 0) + '">' +
			'<button type="button" class="btn btn-sm btn-light store_del_zone">&times;</button></div>';
	}
	function pickupRow(p) {
		p = p || {};
		return '<div class="store_variant_row store_pickup_row">' +
			'<input type="text" class="form-control form-control-sm p_name" placeholder="Point name" value="' + (p.name || '') + '">' +
			'<input type="text" class="form-control form-control-sm p_addr" placeholder="Address" value="' + (p.address || '') + '">' +
			'<input type="text" class="form-control form-control-sm p_phone" placeholder="Phone" value="' + (p.contact_phone || '') + '">' +
			'<input type="number" step="0.01" class="form-control form-control-sm p_fee" placeholder="Fee" value="' + (p.fee || 0) + '">' +
			'<button type="button" class="btn btn-sm btn-light store_del_pickup">&times;</button></div>';
	}
	if ($('#store_zone_rows').length) {
		(window.PT_STORE_DELIVERY_ZONES || []).forEach(function (z) { $('#store_zone_rows').append(zoneRow(z)); });
	}
	if ($('#store_pickup_rows').length) {
		(window.PT_STORE_PICKUP_POINTS || []).forEach(function (p) { $('#store_pickup_rows').append(pickupRow(p)); });
	}
	$(document).on('click.ptstore', '.store_add_zone', function () { $('#store_zone_rows').append(zoneRow()); });
	$(document).on('click.ptstore', '.store_del_zone', function () { $(this).closest('.store_zone_row').remove(); });
	$(document).on('click.ptstore', '.store_add_pickup', function () { $('#store_pickup_rows').append(pickupRow()); });
	$(document).on('click.ptstore', '.store_del_pickup', function () { $(this).closest('.store_pickup_row').remove(); });

	$(document).on('click.ptstore', '.store_save_zones', function () {
		var zones = [];
		$('#store_zone_rows .store_zone_row').each(function () {
			zones.push({
				location_id: $(this).find('.z_loc').val(),
				base_fee: $(this).find('.z_base').val(),
				per_kg_fee: $(this).find('.z_kg').val(),
				free_over: $(this).find('.z_free').val()
			});
		});
		ajax('save_delivery_zones', { zones: JSON.stringify(zones) }, function (res) {
			notify(res.status === 200 ? 'success' : 'error', res.status === 200 ? 'Delivery zones saved.' : (res.message || 'Error'));
		});
	});
	$(document).on('click.ptstore', '.store_save_pickups', function () {
		var points = [];
		$('#store_pickup_rows .store_pickup_row').each(function () {
			points.push({
				name: $(this).find('.p_name').val(),
				address: $(this).find('.p_addr').val(),
				contact_phone: $(this).find('.p_phone').val(),
				fee: $(this).find('.p_fee').val()
			});
		});
		ajax('save_pickup_points', { points: JSON.stringify(points) }, function (res) {
			notify(res.status === 200 ? 'success' : 'error', res.status === 200 ? 'Pickup points saved.' : (res.message || 'Error'));
		});
	});

	// Prefill stored subscriber benefits.
	if ($('.store_benefits_form').length && window.PT_STORE_BENEFIT) {
		var b = window.PT_STORE_BENEFIT;
		$('#store_benefit_free_delivery').prop('checked', parseInt(b.free_delivery, 10) === 1);
		$('#store_benefit_pct').val(parseFloat(b.discount_pct || 0));
		$('#store_benefit_addon').val(b.addon_id || 0);
	}
	$(document).on('submit.ptstore', '.store_benefits_form', function (e) {
		e.preventDefault();
		ajax('save_membership', {
			free_delivery: $('#store_benefit_free_delivery').is(':checked') ? 1 : 0,
			discount_pct: $('#store_benefit_pct').val(),
			addon_id: $('#store_benefit_addon').val(),
			is_active: 1
		}, function (res) {
			notify(res.status === 200 ? 'success' : 'error', res.status === 200 ? 'Subscriber benefits saved.' : (res.message || 'Error'));
		});
	});

	// =======================================================================
	//  Seller: orders
	// =======================================================================
	$(document).on('click.ptstore', '.store-order-status', function () {
		var $b = $(this);
		ajax('set_order_status', { order_id: $b.data('order'), status: $b.data('status') }, function (res) {
			if (res.status === 200) { location.reload(); } else { notify('error', res.message || 'Error'); }
		});
	});

	// =======================================================================
	//  Buyer: product page order / book with the delivery quote
	// =======================================================================
	function stockQty() { return parseInt($('#store_qty').val() || 1, 10); }

	function refreshQuote(productId, mode) {
		ajax('quote', {
			cart: JSON.stringify([{ product_id: productId, qty: stockQty() }]),
			delivery_mode: mode || 'deliver',
			buyer_location_id: $('#store_buyer_location').val() || 0,
			pickup_id: $('#store_pickup_choice').val() || 0
		}, function (res) {
			if (res.status === 200) {
				$('#store_delivery_quote').text(res.fee_raw > 0 ? money(res.fee_raw) : 'Free').parent().show();
				$('#store_quote_note').text(res.reason || '');
			}
		});
	}

	$(document).on('change.ptstore', '#store_buyer_location, #store_delivery_mode, #store_pickup_choice', function () {
		var p = window.PT_STORE_PRODUCT || {};
		if (p.id) { refreshQuote(p.id, $('#store_delivery_mode').val()); }
	});

	var checkoutBusy = false;
	function checkout(cart, mode) {
		if (checkoutBusy) { return; }
		checkoutBusy = true;
		ajax('order', {
			cart: JSON.stringify(cart),
			delivery_mode: mode || 'deliver',
			buyer_location_id: $('#store_buyer_location').val() || 0,
			pickup_id: $('#store_pickup_choice').val() || 0,
			buyer_address: $('#store_buyer_address').val() || ''
		}, function (res) {
			checkoutBusy = false;
			if (res.status !== 200) { notify('error', res.message || 'Error'); return; }
			openPayModal(res.order_id, res.order_number, res.subtotal, res.discount, res.delivery, res.tax, res.fee, res.charged);
		});
	}

	function openPayModal(orderId, orderNumber, subtotal, discount, delivery, tax, fee, charged) {
		// The dialog opens from the checkout response or from an order page
		// button. Only the checkout response carries the full breakdown, so any
		// missing line is derived from the others rather than showing NaN.
		var has = function (v) { return v !== undefined && v !== null && v !== ''; };
		if (!has(subtotal)) { subtotal = has(charged) ? charged : 0; }
		if (!has(discount)) { discount = 0; }
		if (!has(delivery)) { delivery = 0; }
		if (!has(tax)) { tax = 0; }
		if (!has(fee)) { fee = 0; }
		if (!has(charged)) {
			var num = function (v) { return parseFloat(String(v).replace(/[^0-9.\-]/g, '') || 0); };
			charged = num(subtotal) - num(discount) + num(delivery) + num(tax) + num(fee);
		}
		var row = function (label, value, strong) {
			return '<div class="d-flex justify-content-between mb-1"><span class="text-muted">' + label + '</span><span' + (strong ? ' class="bold"' : '') + '>' + value + '</span></div>';
		};
		var lines = row('Subtotal', money(subtotal)) + row('Discount', '-' + money(discount)) +
			row('Delivery', money(delivery)) + row('Tax', money(tax)) + row('JPesa Fees', money(fee));
		var body = '<p class="mb-2">Paying for <strong>' + (orderNumber || '') + '</strong></p>' +
			'<div class="store_price_break mb-3">' + lines + '<hr>' + row('Amount charged', money(charged), true) + '</div>' +
			'<div class="form-group"><label>Mobile money number</label>' +
			'<input type="tel" class="form-control" id="store_pay_mobile" placeholder="07xx xxx xxx"></div>' +
			'<div id="store_pay_error" class="text-danger small"></div>';
		var html = '<div class="modal fade" id="store_pay_modal"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">' +
			'<div class="modal-header"><h5 class="modal-title">Pay for your order</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>' +
			'<div class="modal-body">' + body + '</div>' +
			'<div class="modal-footer"><a href="' + site_url + '/store?tab=orders" class="btn btn-light">Pay later</a>' +
			'<button type="button" class="btn btn-main store_pay_confirm">Pay now</button></div></div></div></div>';
		$('body').append(html);
		$('#store_pay_modal').modal('show');
		$('#store_pay_modal').on('hidden.bs.modal', function () { $(this).remove(); });
		$('#store_pay_modal .store_pay_confirm').on('click', function () {
			var mobile = $.trim($('#store_pay_mobile').val());
			if (!mobile) { $('#store_pay_error').text('Enter your mobile money number.'); return; }
			$(this).prop('disabled', true).text('Sending…');
			ajax('pay_jpesa', { order_id: orderId, mobile: mobile }, function (pay) {
				if (pay.status === 200) {
					$('#store_pay_modal .modal-body').html('<p class="mb-0">' + (pay.message || 'Approve the payment on your phone.') + '</p>');
					pollPayment(pay.transaction_id, site_url + '/store?tab=orders');
				} else {
					$('#store_pay_error').text(pay.message || 'Payment failed.');
					$('#store_pay_modal .store_pay_confirm').prop('disabled', false).text('Pay now');
				}
			});
		});
	}

	function pollPayment(tid, redirect) {
		if (!tid) { location.href = redirect; return; }
		var tries = 0;
		var poll = setInterval(function () {
			tries++;
			$.post(site_url + '/aj/wallet/jpesa_status?hash=' + hash(), { transaction_id: tid }, function (s) {
				if (s.status !== 200 && s.status !== 202) { clearInterval(poll); location.href = redirect; }
			}, 'json').fail(function () { if (tries > 6) { clearInterval(poll); location.href = redirect; } });
			if (tries > 40) { clearInterval(poll); location.href = redirect; }
		}, 5000);
	}

	$(document).on('click.ptstore', '.store_buy_btn', function () {
		var p = window.PT_STORE_PRODUCT || {};
		checkout([{ product_id: p.id, qty: stockQty(), variant_id: parseInt($('#store_variant').val() || 0, 10) }], $('#store_delivery_mode').val());
	});
	$(document).on('click.ptstore', '.store_book_btn', function () {
		var p = window.PT_STORE_PRODUCT || {};
		var slot = $('#store_slot').val();
		if (!slot) { notify('error', 'Choose a time first.'); return; }
		checkout([{ product_id: p.id, qty: 1, slot_start: parseInt(slot, 10) }], 'service');
	});

	// Pay an existing order from the order page.
	$(document).on('click.ptstore', '.store-pay', function () {
		var $b = $(this);
		openPayModal($b.data('order'), $b.data('number') || '', $b.data('subtotal') || 0,
			$b.data('discount') || 0, $b.data('delivery') || 0, $b.data('tax') || 0,
			$b.data('fee') || 0, $b.data('charged') || 0);
	});

	$(document).on('click.ptstore', '.store-cancel-order', function () {
		var $b = $(this);
		confirmDialog({
			title: 'Cancel this order?', body: 'This cannot be undone.',
			ok: 'Cancel order', onConfirm: function () {
				ajax('cancel_order', { order_id: $b.data('order') }, function (res) {
					if (res.status === 200) { location.reload(); } else { notify('error', res.message || 'Error'); }
				});
			}
		});
	});

	// =======================================================================
	//  Reviews + merch attach
	// =======================================================================
	$(document).on('submit.ptstore', '.store_review_form', function (e) {
		e.preventDefault();
		var $f = $(this);
		ajax('review', {
			product_id: $f.data('product'),
			rating: $f.find('[name=rating]').val(),
			title: $f.find('[name=title]').val(),
			body: $f.find('[name=body]').val()
		}, function (res) {
			if (res.status === 200) { notify('success', 'Thanks for your review.'); location.reload(); }
			else { notify('error', res.message || 'Error'); }
		});
	});

	$(document).on('click.ptstore', '.store_attach_btn', function () {
		var $b = $(this);
		ajax('attach', {
			product_id: $b.data('product'),
			target_type: $b.data('target-type'),
			target_id: $b.data('target-id')
		}, function (res) {
			notify(res.status === 200 ? 'success' : 'error', res.status === 200 ? 'Merch attached.' : 'Could not attach.');
		});
	});

	$(document).on('click.ptstore', '.store_attach_publish', function () {
		var $box = $(this).closest('.store_attach');
		var ids = [];
		$box.find('.store_attach_check:checked').each(function () { ids.push($(this).val()); });
		ajax('attach_publish', {
			target_type: $box.data('target-type'),
			target_id: $box.data('target-id'),
			product_ids: ids
		}, function (res) {
			notify(res.status === 200 ? 'success' : 'error', res.status === 200 ? 'Merch saved.' : (res.message || 'Could not save.'));
		});
	});

	// =======================================================================
	//  Init
	// =======================================================================
	function storeInit() {
		if ($('#store_product_wizard').length) {
			wizardGo(1);
			syncKind();
		}
	}
	$(storeInit);
	if (window.pjax) { $(document).on('pjax:end.ptstore', storeInit); }

})(jQuery);
