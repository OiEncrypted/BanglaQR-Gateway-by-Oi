/**
 * Admin Panel JS for Bangla QR Payment Method WooCommerce Gateway
 * Accordion Architecture
 */

jQuery(document).ready(function ($) {
    // Safety check
    if (typeof oi_banglaqr_admin_params === 'undefined') {
        return;
    }

    // =========================================================================
    // 1. Audio Alert Notification (Web Audio API Synthesizer)
    // =========================================================================
    function playBanglaQRChime() {
        try {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            var ctx = new AudioCtx();
            if (ctx.state === 'suspended') {
                ctx.resume();
            }
            var now = ctx.currentTime;
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, now); // D5
            osc.frequency.setValueAtTime(880, now + 0.12); // A5

            gain.gain.setValueAtTime(0.2, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start(now);
            osc.stop(now + 0.6);
        } catch (e) {
            console.warn('BanglaQR: Web Audio chime failed', e);
        }
    }

    // Check if new pending orders arrived since last admin page load
    if (oi_banglaqr_admin_params.sound_alert_enabled) {
        var currentPending = parseInt(oi_banglaqr_admin_params.pending_orders_count, 10) || 0;
        var lastPending = parseInt(localStorage.getItem('oi_banglaqr_pending_count'), 10);

        if (!isNaN(lastPending) && currentPending > lastPending) {
            playBanglaQRChime();
        }
        localStorage.setItem('oi_banglaqr_pending_count', currentPending);
    }

    // Test Sound button on settings page
    $(document).on('click', '#banglaqr-test-sound-btn', function (e) {
        e.preventDefault();
        playBanglaQRChime();
        var $btn = $(this);
        var origText = $btn.find('span').text();
        $btn.find('span').text(oi_banglaqr_admin_params.i18n_sound_tested || 'Played!');
        setTimeout(function () {
            $btn.find('span').text(origText);
        }, 1800);
    });

    // =========================================================================
    // 2. Slip Lightbox Modal Viewer
    // =========================================================================
    var currentRotation = 0;

    $(document).on('click', '.banglaqr-admin-lightbox-trigger', function (e) {
        e.preventDefault();
        var url = $(this).attr('data-slip-url') || $(this).attr('src') || $(this).attr('href');
        var orderLabel = $(this).attr('data-order') || '';

        if (!url) return;

        currentRotation = 0;
        $('#banglaqr-admin-lightbox-img').attr('src', url).css('transform', 'rotate(0deg)');
        $('#banglaqr-admin-lightbox-external-btn').attr('href', url);
        $('#banglaqr-admin-lightbox-order-badge').text(orderLabel ? orderLabel : 'Slip');
        $('#banglaqr-admin-slip-lightbox').fadeIn(150);
    });

    $(document).on('click', '#banglaqr-admin-lightbox-close-btn, #banglaqr-admin-slip-lightbox', function (e) {
        if (e.target === this || $(this).is('#banglaqr-admin-lightbox-close-btn') || $(this).closest('#banglaqr-admin-lightbox-close-btn').length) {
            e.preventDefault();
            $('#banglaqr-admin-slip-lightbox').fadeOut(120);
        }
    });

    $(document).on('click', '#banglaqr-admin-lightbox-rotate-btn', function (e) {
        e.preventDefault();
        currentRotation = (currentRotation + 90) % 360;
        $('#banglaqr-admin-lightbox-img').css('transform', 'rotate(' + currentRotation + 'deg)');
    });

    // Close on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#banglaqr-admin-slip-lightbox').is(':visible')) {
            $('#banglaqr-admin-slip-lightbox').fadeOut(120);
        }
    });

    // =========================================================================
    // 3. 1-Click Quick Order Status Actions (Approve / Reject)
    // =========================================================================
    $(document).on('click', '.banglaqr-qa-approve', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        if (!orderId || $btn.prop('disabled')) return;

        $btn.prop('disabled', true).find('span').text(oi_banglaqr_admin_params.i18n_approving || 'Approving...');

        $.ajax({
            url: oi_banglaqr_admin_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'oi_banglaqr_quick_order_status',
                nonce: oi_banglaqr_admin_params.admin_nonce,
                order_id: orderId,
                status_action: 'approve'
            },
            success: function (res) {
                if (res && res.success) {
                    var $container = $('#banglaqr-admin-qa-' + orderId);
                    if ($container.length && res.data && res.data.status_pill_html) {
                        $container.html(res.data.status_pill_html);
                    }
                    var $orderStatusSelect = $('select#order_status');
                    if ($orderStatusSelect.length) {
                        $orderStatusSelect.val('wc-processing').trigger('change');
                    }
                } else {
                    alert((res && res.data && res.data.message) ? res.data.message : 'Error approving payment.');
                    $btn.prop('disabled', false).find('span').text('Approve (Processing)');
                }
            },
            error: function () {
                alert('Network error while processing request.');
                $btn.prop('disabled', false).find('span').text('Approve (Processing)');
            }
        });
    });

    $(document).on('click', '.banglaqr-qa-reject', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var orderId = $btn.data('order-id');
        if (!orderId || $btn.prop('disabled')) return;

        var reason = prompt(oi_banglaqr_admin_params.i18n_confirm_reject + '\n' + (oi_banglaqr_admin_params.i18n_reject_reason_prompt || 'Reason:'));
        if (reason === null) {
            return;
        }

        $btn.prop('disabled', true).find('span').text(oi_banglaqr_admin_params.i18n_rejecting || 'Rejecting...');

        $.ajax({
            url: oi_banglaqr_admin_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'oi_banglaqr_quick_order_status',
                nonce: oi_banglaqr_admin_params.admin_nonce,
                order_id: orderId,
                status_action: 'reject',
                note: reason
            },
            success: function (res) {
                if (res && res.success) {
                    var $container = $('#banglaqr-admin-qa-' + orderId);
                    if ($container.length && res.data && res.data.status_pill_html) {
                        $container.html(res.data.status_pill_html);
                    }
                    var $orderStatusSelect = $('select#order_status');
                    if ($orderStatusSelect.length) {
                        $orderStatusSelect.val('wc-failed').trigger('change');
                    }
                } else {
                    alert((res && res.data && res.data.message) ? res.data.message : 'Error rejecting payment.');
                    $btn.prop('disabled', false).find('span').text('Reject (Failed)');
                }
            },
            error: function () {
                alert('Network error while processing request.');
                $btn.prop('disabled', false).find('span').text('Reject (Failed)');
            }
        });
    });

    // =========================================================================
    // 4. Settings Page: QR Accounts Accordion Manager
    // =========================================================================
    if (!$('#woocommerce_oi_banglaqr_qrs_table').length) {
        return;
    }

    var $hiddenInput = $('#woocommerce_oi_banglaqr_qrs_table');
    var $accordionWrapper = $('#banglaqr-qr-accordion-wrapper');

    var qrs = [];

    // Parse initial QR accounts data
    try {
        var rawVal = $hiddenInput.val();
        if (rawVal) {
            qrs = JSON.parse(rawVal);
        }
    } catch (e) {
        try {
            var decoded = $('<textarea/>').html(rawVal).text();
            qrs = JSON.parse(decoded);
        } catch (e2) {
            console.error('Failed to parse QR accounts data: ', e2);
        }
    }

    if (!Array.isArray(qrs)) {
        qrs = [];
    }

    // Helper to escape attributes
    function escapeAttr(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Generate HTML for a single accordion row
    function renderAccordionItem(qr, idx) {
        var qrName = qr.qr_name || '';
        var qrCodeUrl = qr.qr_code_url || '';
        var active = (qr.is_active === 'yes');

        var checkedStr = active ? 'checked' : '';
        var isExpandedCls = (idx === 0) ? 'is-expanded' : '';
        if (qrs.length === 1) isExpandedCls = 'is-expanded';

        var logoHtml = qrCodeUrl
            ? '<img src="' + escapeAttr(qrCodeUrl) + '" alt="' + escapeAttr(qrName) + '" />'
            : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>';

        var qrPreview = qrCodeUrl
            ? '<img src="' + escapeAttr(qrCodeUrl) + '" alt="' + escapeAttr(qrName) + '" />'
            : '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>';

        var fee = qr.payment_charge || '0';
        var feeBadge = (parseFloat(fee) > 0)
            ? '<span class="banglaqr-accordion-badge-fee">' + escapeAttr(fee) + oi_banglaqr_admin_params.i18n_fee_suffix + '</span>'
            : '<span class="banglaqr-accordion-badge-fee">' + oi_banglaqr_admin_params.i18n_no_fee + '</span>';

        var statusBadge = active 
            ? '<span class="banglaqr-status-badge status-active"><span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_active + '</span>'
            : '<span class="banglaqr-status-badge status-inactive"><span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_inactive + '</span>';

        var html = '';
        html += '<div class="banglaqr-qr-accordion-item ' + isExpandedCls + '" data-index="' + idx + '">';
        
        // Accordion Header
        html += '  <div class="banglaqr-accordion-header">';
        html += '    <div class="banglaqr-sort-handle" title="Drag to reorder"><svg viewBox="0 0 24 24" width="16" height="16" fill="#94a3b8"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg></div>';
        html += '    <div class="banglaqr-accordion-logo">' + logoHtml + '</div>';
        html += '    <div class="banglaqr-accordion-title">' + (qrName ? escapeAttr(qrName) : oi_banglaqr_admin_params.i18n_new_qr_account) + '</div>';
        html += '    <div class="banglaqr-accordion-actions" style="display:flex; align-items:center;">';
        html += feeBadge;
        html += '      <div class="banglaqr-badge-wrapper">' + statusBadge + '</div>';
        html += '      <span class="banglaqr-accordion-toggle-svg"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>';
        html += '    </div>';
        html += '  </div>';

        // Accordion Body
        html += '  <div class="banglaqr-accordion-body">';
        html += '    <div class="banglaqr-accordion-body-inner">';
        
        // Left Column: Scan Preview
        html += '      <div class="banglaqr-large-qr-preview-wrapper">';
        html += '        <div class="banglaqr-grid-field"><label class="banglaqr-field-label-with-icon" style="margin-bottom:4px;"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg><span>' + oi_banglaqr_admin_params.i18n_scan_preview + '</span></label></div>';
        html += '        <div class="banglaqr-large-qr-preview-box" style="' + (qrCodeUrl ? 'display:flex;' : 'display:none;') + '">';
        html += '          <img class="banglaqr-large-qr-preview-img" src="' + escapeAttr(qrCodeUrl) + '" alt="QR Preview" />';
        html += '        </div>';
        html += '        <div class="banglaqr-large-qr-empty-box" style="' + (qrCodeUrl ? 'display:none;' : 'display:flex;') + '">';
        html += '          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2z"/><path d="M18 14h3v3h-3z"/><path d="M14 18h3v3h-3z"/></svg>';
        html += '          <span>No QR Selected</span>';
        html += '        </div>';
        html += '        <span class="banglaqr-scan-hint" style="font-size: 11px; color: #64748b; margin-top: 6px; display: block; line-height: 1.4; ' + (qrCodeUrl ? '' : 'display:none;') + '">' + oi_banglaqr_admin_params.i18n_scan_hint + '</span>';
        html += '      </div>';

        // Right Column: Inputs
        html += '      <div class="banglaqr-card-inputs-wrapper">';
        
        // Name
        html += '        <div class="banglaqr-grid-field">';
        html += '          <label class="banglaqr-field-label-with-icon"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg><span>' + oi_banglaqr_admin_params.i18n_account_name + ' <span class="req">*</span></span></label>';
        html += '          <input type="text" class="banglaqr-qr-name-input banglaqr-general-input" value="' + escapeAttr(qrName) + '" placeholder="' + escapeAttr(oi_banglaqr_admin_params.i18n_account_name_ph) + '" />';
        html += '        </div>';

        // Charge
        var qrCharge = qr.payment_charge || '0';
        html += '        <div class="banglaqr-grid-field">';
        html += '          <label class="banglaqr-field-label-with-icon"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg><span>' + oi_banglaqr_admin_params.i18n_processing_fee + '</span></label>';
        html += '          <input type="number" step="0.01" min="0" max="100" class="banglaqr-payment-charge-input banglaqr-general-input" value="' + escapeAttr(qrCharge) + '" placeholder="' + escapeAttr(oi_banglaqr_admin_params.i18n_processing_fee_ph) + '" />';
        html += '        </div>';

        // Image URL
        html += '        <div class="banglaqr-grid-field">';
        html += '          <label class="banglaqr-field-label-with-icon"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2z"/><path d="M18 14h3v3h-3z"/><path d="M14 18h3v3h-3z"/></svg><span>' + oi_banglaqr_admin_params.i18n_qr_code_image + ' <span class="req">*</span></span></label>';
        html += '          <div class="banglaqr-uploader-inline">';
        html += '            <div class="banglaqr-logo-preview-box banglaqr-qr-preview-box">' + qrPreview + '</div>';
        html += '            <input type="text" class="banglaqr-qr-code-url-input banglaqr-general-input" value="' + escapeAttr(qrCodeUrl) + '" placeholder="' + escapeAttr(oi_banglaqr_admin_params.i18n_upload_ph) + '" />';
        html += '            <button type="button" class="button button-secondary banglaqr-upload-logo-btn banglaqr-btn-with-icon">';
        html += '              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
        html += '              <span>' + oi_banglaqr_admin_params.i18n_upload_btn + '</span>';
        html += '            </button>';
        html += '          </div>';
        html += '        </div>';

        // Bottom Actions (Activate toggle and Delete)
        html += '        <div class="banglaqr-panel-actions">';
        html += '          <div class="banglaqr-toggle-wrapper" style="margin-right: auto;">';
        html += '            <label class="banglaqr-switch">';
        html += '              <input type="checkbox" class="banglaqr-is-active-input" ' + checkedStr + ' />';
        html += '              <span class="banglaqr-slider"></span>';
        html += '            </label>';
        html += '            <span class="banglaqr-toggle-status">' + (active ? oi_banglaqr_admin_params.i18n_active_on_checkout : oi_banglaqr_admin_params.i18n_set_as_active) + '</span>';
        html += '          </div>';
        html += '          <button type="button" class="banglaqr-delete-row-btn" title="' + oi_banglaqr_admin_params.i18n_remove_account + '">';
        html += '            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
        html += '            <span>' + oi_banglaqr_admin_params.i18n_remove_account + '</span>';
        html += '          </button>';
        html += '        </div>';

        html += '      </div>'; // End Inputs wrapper
        html += '    </div>'; // End Body Inner
        html += '  </div>'; // End Body
        html += '</div>'; // End Item

        return html;
    }

    // Refresh layout in the DOM
    function renderTable() {
        // Save expansion states
        var expandedStates = [];
        $('.banglaqr-qr-accordion-item').each(function() {
            if ($(this).hasClass('is-expanded')) {
                expandedStates.push(parseInt($(this).data('index'), 10));
            }
        });

        $accordionWrapper.empty();

        if (qrs.length === 0) {
            var emptyHtml = '<div class="banglaqr-empty-qrs-box">';
            emptyHtml += '  <div class="banglaqr-empty-icon-wrap">';
            emptyHtml += '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2z"/><path d="M18 14h3v3h-3z"/><path d="M14 18h3v3h-3z"/><path d="M20 20h1v1h-1z"/></svg>';
            emptyHtml += '  </div>';
            emptyHtml += '  <p>' + oi_banglaqr_admin_params.i18n_no_qrs_msg + '</p>';
            emptyHtml += '  <button type="button" class="button button-primary banglaqr-btn-with-icon" id="banglaqr-add-first-qr-btn">';
            emptyHtml += '    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
            emptyHtml += '    <span>' + oi_banglaqr_admin_params.i18n_add_first_qr + '</span>';
            emptyHtml += '  </button>';
            emptyHtml += '</div>';
            $accordionWrapper.append(emptyHtml);
            serializeData();
            return;
        }

        $.each(qrs, function (idx, qr) {
            var html = renderAccordionItem(qr, idx);
            var $item = $(html);
            
            // Restore expansion state if re-rendering
            if (expandedStates.indexOf(idx) !== -1) {
                $item.addClass('is-expanded');
            } else if (expandedStates.length > 0) {
                $item.removeClass('is-expanded');
            }

            $accordionWrapper.append($item);
        });

        var addBtnHtml = '<button type="button" class="banglaqr-btn-add-inline banglaqr-btn-with-icon" id="banglaqr-add-qr-row-inline">';
        addBtnHtml += '  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
        addBtnHtml += '  <span>' + oi_banglaqr_admin_params.i18n_add_another_qr + '</span>';
        addBtnHtml += '</button>';
        $accordionWrapper.append(addBtnHtml);

        serializeData();
    }

    // Add new card data model
    function addQrCard(isDefaultTest) {
        var makeActive = qrs.length === 0 ? 'yes' : 'no';
        var newQr;

        if (isDefaultTest) {
            newQr = {
                qr_name: 'BanglaQR (Primary)',
                qr_code_url: oi_banglaqr_admin_params.default_qr_url || '',
                payment_charge: '1',
                is_active: 'yes'
            };
        } else {
            newQr = {
                qr_name: '',
                qr_code_url: '',
                payment_charge: '0',
                is_active: makeActive
            };
        }

        qrs.push(newQr);
        
        // Collapse all others and expand new one
        renderTable();
        
        var newIdx = qrs.length - 1;
        setTimeout(function() {
            $('.banglaqr-qr-accordion-item').removeClass('is-expanded');
            $('.banglaqr-qr-accordion-item[data-index="' + newIdx + '"]').addClass('is-expanded');
        }, 50);
    }

    // Read DOM panel inputs and serialize to JSON
    function serializeData() {
        var serialized = [];
        $accordionWrapper.find('.banglaqr-qr-accordion-item').each(function () {
            var $panel = $(this);
            var qr = {
                qr_name: ($panel.find('.banglaqr-qr-name-input').val() || '').trim(),
                qr_code_url: ($panel.find('.banglaqr-qr-code-url-input').val() || '').trim(),
                payment_charge: ($panel.find('.banglaqr-payment-charge-input').val() || '0').trim() || '0',
                is_active: $panel.find('.banglaqr-is-active-input').is(':checked') ? 'yes' : 'no'
            };
            serialized.push(qr);
        });
        qrs = serialized;
        $hiddenInput.val(JSON.stringify(serialized));
    }

    // Accordion expand/collapse
    $(document).on('click', '.banglaqr-accordion-header', function (e) {
        if ($(e.target).closest('.banglaqr-sort-handle').length || $(e.target).closest('.banglaqr-badge-wrapper').length) {
            return; // Ignore drag handle or badge clicks
        }
        var $item = $(this).closest('.banglaqr-qr-accordion-item');
        $item.toggleClass('is-expanded');
    });

    // Handle bank logo media uploader frame
    $(document).on('click', '.banglaqr-upload-logo-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var $panel = $btn.closest('.banglaqr-qr-accordion-item');
        var $previewBox = $panel.find('.banglaqr-qr-preview-box');
        var $urlInput = $panel.find('.banglaqr-qr-code-url-input');

        var fileFrame = wp.media({
            title: oi_banglaqr_admin_params.media_title,
            button: { text: oi_banglaqr_admin_params.media_button_text },
            multiple: false
        });

        fileFrame.on('select', function () {
            var attachment = fileFrame.state().get('selection').first().toJSON();
            var imageUrl = attachment.url;

            $urlInput.val(imageUrl);
            $previewBox.html('<img src="' + escapeAttr(imageUrl) + '" />');

            // Sync to header logo preview
            $panel.find('.banglaqr-accordion-logo').html('<img src="' + escapeAttr(imageUrl) + '" />');

            // Sync and show the large scanner preview
            var $largeWrapper = $panel.find('.banglaqr-large-qr-preview-wrapper');
            $largeWrapper.find('.banglaqr-large-qr-preview-img').attr('src', imageUrl);
            $largeWrapper.find('.banglaqr-large-qr-preview-box').css('display', 'flex');
            $largeWrapper.find('.banglaqr-large-qr-empty-box').hide();
            $largeWrapper.find('.banglaqr-scan-hint').show();

            serializeData();
        });

        fileFrame.open();
    });

    // Delete row event
    $(document).on('click', '.banglaqr-delete-row-btn', function (e) {
        e.preventDefault();
        if (confirm(oi_banglaqr_admin_params.confirm_delete)) {
            var $panel = $(this).closest('.banglaqr-qr-accordion-item');
            var idx = parseInt($panel.data('index'), 10);

            // Sync current inputs before deleting
            serializeData();
            qrs.splice(idx, 1);

            // If the deleted QR code was active, default another QR code to active
            var hasActive = false;
            $.each(qrs, function (i, q) {
                if (q.is_active === 'yes') {
                    hasActive = true;
                    return false;
                }
            });
            if (!hasActive && qrs.length > 0) {
                qrs[0].is_active = 'yes';
            }

            renderTable();
        }
    });

    // Add Row Click triggers
    $(document).on('click', '#banglaqr-add-qr-row-inline, #banglaqr-add-first-qr-btn', function (e) {
        e.preventDefault();
        addQrCard(false);
    });

    // Dynamic Title Sync to the Tab title
    $(document).on('input', '.banglaqr-qr-name-input', function () {
        var $panel = $(this).closest('.banglaqr-qr-accordion-item');
        var name = $(this).val().trim();
        $panel.find('.banglaqr-accordion-title').text(name ? name : oi_banglaqr_admin_params.i18n_new_qr_account);
    });

    // Inline URL preview sync
    $(document).on('input', '.banglaqr-qr-code-url-input', function () {
        var $panel = $(this).closest('.banglaqr-qr-accordion-item');
        var url = $(this).val().trim();
        var $previewBox = $panel.find('.banglaqr-qr-preview-box');
        var $tabPreview = $panel.find('.banglaqr-accordion-logo');

        if (url) {
            $previewBox.html('<img src="' + escapeAttr(url) + '" />');
            $tabPreview.html('<img src="' + escapeAttr(url) + '" />');

            // Sync and show the large scanner preview
            var $largeWrapper = $panel.find('.banglaqr-large-qr-preview-wrapper');
            $largeWrapper.find('.banglaqr-large-qr-preview-img').attr('src', url);
            $largeWrapper.find('.banglaqr-large-qr-preview-box').css('display', 'flex');
            $largeWrapper.find('.banglaqr-large-qr-empty-box').hide();
            $largeWrapper.find('.banglaqr-scan-hint').show();
        } else {
            $previewBox.html('<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>');
            $tabPreview.html('<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>');

            var $largeWrapper = $panel.find('.banglaqr-large-qr-preview-wrapper');
            $largeWrapper.find('.banglaqr-large-qr-preview-img').attr('src', '');
            $largeWrapper.find('.banglaqr-large-qr-preview-box').hide();
            $largeWrapper.find('.banglaqr-large-qr-empty-box').css('display', 'flex');
            $largeWrapper.find('.banglaqr-scan-hint').hide();
        }
    });

    // Enabled/Disable switch changes status text and tab opacity (mutually exclusive active QRs)
    $(document).on('change', '.banglaqr-is-active-input', function () {
        var $panel = $(this).closest('.banglaqr-qr-accordion-item');
        var idx = parseInt($panel.data('index'), 10);
        var isChecked = $(this).is(':checked');

        if (isChecked) {
            // Uncheck other active checkboxes
            $('.banglaqr-is-active-input').each(function () {
                var $other = $(this);
                var $otherPanel = $other.closest('.banglaqr-qr-accordion-item');
                var otherIdx = parseInt($otherPanel.data('index'), 10);
                if (otherIdx !== idx) {
                    $other.prop('checked', false);
                    $otherPanel.find('.banglaqr-badge-wrapper').html('<span class="banglaqr-status-badge status-inactive"><span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_inactive + '</span>');
                    $otherPanel.find('.banglaqr-toggle-status').text(oi_banglaqr_admin_params.i18n_set_as_active);
                }
            });

            $panel.find('.banglaqr-badge-wrapper').html('<span class="banglaqr-status-badge status-active"><span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_active + '</span>');
            $panel.find('.banglaqr-toggle-status').text(oi_banglaqr_admin_params.i18n_active_on_checkout);
        } else {
            $panel.find('.banglaqr-badge-wrapper').html('<span class="banglaqr-status-badge status-inactive"><span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_inactive + '</span>');
            $panel.find('.banglaqr-toggle-status').text(oi_banglaqr_admin_params.i18n_set_as_active);
        }

        serializeData();
    });

    // Serialize when input values change
    $(document).on('input change', '.banglaqr-qr-accordion-item input', function () {
        serializeData();
    });

    // Enable dragging accordion rows smoothly without DOM recreation
    if ($.fn.sortable) {
        $accordionWrapper.sortable({
            handle: '.banglaqr-sort-handle',
            placeholder: 'banglaqr-qr-accordion-item ui-sortable-helper',
            items: '.banglaqr-qr-accordion-item',
            axis: 'y',
            update: function () {
                var reordered = [];
                $accordionWrapper.find('.banglaqr-qr-accordion-item').each(function (newIndex) {
                    var $panel = $(this);
                    $panel.attr('data-index', newIndex);
                    var qr = {
                        qr_name: ($panel.find('.banglaqr-qr-name-input').val() || '').trim(),
                        qr_code_url: ($panel.find('.banglaqr-qr-code-url-input').val() || '').trim(),
                        payment_charge: ($panel.find('.banglaqr-payment-charge-input').val() || '0').trim() || '0',
                        is_active: $panel.find('.banglaqr-is-active-input').is(':checked') ? 'yes' : 'no'
                    };
                    reordered.push(qr);
                });

                qrs = reordered;
                $hiddenInput.val(JSON.stringify(reordered));
            }
        });
    }

    // Toggle status text and status badge in settings tab
    $(document).on('change', '#woocommerce_oi_banglaqr_enabled', function () {
        var isChecked = $(this).is(':checked');
        $(this).closest('.banglaqr-toggle-wrapper').find('.banglaqr-toggle-status').text(isChecked ? oi_banglaqr_admin_params.i18n_enabled : oi_banglaqr_admin_params.i18n_disabled);

        // Update header active badge
        var $badge = $('.banglaqr-status-badge').first();
        if ($badge.closest('.banglaqr-header-actions').length) {
            if (isChecked) {
                $badge.removeClass('status-inactive').addClass('status-active');
                $badge.html('<span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_gateway_active);
            } else {
                $badge.removeClass('status-active').addClass('status-inactive');
                $badge.html('<span class="status-dot"></span> ' + oi_banglaqr_admin_params.i18n_gateway_inactive);
            }
        }
    });

    // Handle gateway logo media uploader frame
    $(document).on('click', '#banglaqr-upload-gateway-logo-btn', function (e) {
        e.preventDefault();
        var $previewBox = $('#banglaqr-gateway-logo-preview');
        var $urlInput = $('#woocommerce_oi_banglaqr_gateway_logo');

        var fileFrame = wp.media({
            title: oi_banglaqr_admin_params.i18n_select_gateway_logo,
            button: { text: oi_banglaqr_admin_params.i18n_use_logo },
            multiple: false
        });

        fileFrame.on('select', function () {
            var attachment = fileFrame.state().get('selection').first().toJSON();
            var imageUrl = attachment.url;

            $urlInput.val(imageUrl).trigger('change');
            $previewBox.html('<img src="' + escapeAttr(imageUrl) + '" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 4px;" />');
        });

        fileFrame.open();
    });

    $(document).on('input change', '#woocommerce_oi_banglaqr_gateway_logo', function () {
        var url = $(this).val().trim();
        var $previewBox = $('#banglaqr-gateway-logo-preview');

        if (url) {
            $previewBox.html('<img src="' + escapeAttr(url) + '" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 4px;" />');
        } else {
            $previewBox.html('<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>');
        }
    });

    function hexToRgba(hex, alpha) {
        if (!hex) return 'rgba(10, 105, 75, ' + alpha + ')';
        var clean = hex.replace('#', '');
        if (clean.length === 3) {
            clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
        }
        var r = parseInt(clean.substring(0, 2), 16) || 10;
        var g = parseInt(clean.substring(2, 4), 16) || 105;
        var b = parseInt(clean.substring(4, 6), 16) || 75;
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function adjustColorBrightness(hex, percent) {
        if (!hex) return '#07523a';
        var clean = hex.replace('#', '');
        if (clean.length === 3) {
            clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
        }
        var num = parseInt(clean, 16);
        var amt = Math.round(2.55 * percent);
        var r = (num >> 16) + amt;
        var g = ((num >> 8) & 0x00ff) + amt;
        var b = (num & 0x0000ff) + amt;
        return '#' + (0x1000000 + (r < 255 ? (r < 0 ? 0 : r) : 255) * 0x10000 + (g < 255 ? (g < 0 ? 0 : g) : 255) * 0x100 + (b < 255 ? (b < 0 ? 0 : b) : 255)).toString(16).slice(1);
    }

    // Sync theme accent color picker
    $(document).on('input change', '#woocommerce_oi_banglaqr_theme_color', function () {
        var val = $(this).val();
        $('#banglaqr-theme-color-swatch').css('background-color', val);
        $('#banglaqr-theme-color-hex').val(val);

        var wrap = document.querySelector('.banglaqr-admin-dashboard-wrap');
        if (wrap) {
            wrap.style.setProperty('--banglaqr-primary', val);
            wrap.style.setProperty('--banglaqr-primary-hover', adjustColorBrightness(val, -15));
            wrap.style.setProperty('--banglaqr-primary-light', hexToRgba(val, 0.08));
            wrap.style.setProperty('--banglaqr-primary-border', hexToRgba(val, 0.25));
        }
    });

    $(document).on('click', '#banglaqr-theme-color-swatch, .banglaqr-color-trigger-btn', function (e) {
        e.preventDefault();
        $('#woocommerce_oi_banglaqr_theme_color').trigger('click');
    });

    // Ensure table data is freshly serialized on settings form submission
    $(document).on('submit', 'form#mainform', function () {
        serializeData();
    });

    // =========================================================================
    // 6. Segmented Tab Navigation & Quick Header Save Actions
    // =========================================================================
    function switchAdminTab(tabName) {
        if (!tabName) tabName = 'general';

        // Update tab buttons state
        $('.banglaqr-tab-btn').removeClass('is-active').attr('aria-selected', 'false');
        $('.banglaqr-tab-btn[data-tab="' + tabName + '"]').addClass('is-active').attr('aria-selected', 'true');

        // Show/hide sections
        if (tabName === 'all') {
            $('[data-tab-content]').show();
        } else {
            $('[data-tab-content]').hide();
            var $target = $('[data-tab-content="' + tabName + '"]');
            if ($target.length) {
                $target.fadeIn(150);
            } else {
                $('[data-tab-content]').show();
            }
        }

        // Persist tab in localStorage
        try {
            localStorage.setItem('oi_banglaqr_active_tab', tabName);
        } catch (e) {}
    }

    // Tab button click
    $(document).on('click', '.banglaqr-tab-btn', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        switchAdminTab(tab);
    });

    // Metric pill shortcut click
    $(document).on('click', '.banglaqr-metric-pill[data-tab-target]', function (e) {
        e.preventDefault();
        var target = $(this).data('tab-target');
        switchAdminTab(target);

        // Smooth scroll to target section
        var $targetSection = $('[data-tab-content="' + target + '"]');
        if ($targetSection.length) {
            $('html, body').animate({
                scrollTop: $targetSection.offset().top - 90
            }, 250);
        }
    });

    // Header Quick Save button
    $(document).on('click', '#banglaqr-header-save-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).find('span').text(oi_banglaqr_admin_params.i18n_saving || 'Saving...');

        // Trigger WooCommerce settings save button
        var $saveBtn = $('form#mainform').find('button[name="save"], input[type="submit"][name="save"]').first();
        if ($saveBtn.length) {
            $saveBtn.trigger('click');
        } else {
            $('form#mainform').trigger('submit');
        }
    });

    // Restore saved active tab on load
    if ($('.banglaqr-tabs-nav').length) {
        var savedTab = 'general';
        try {
            savedTab = localStorage.getItem('oi_banglaqr_active_tab') || 'general';
        } catch (e) {}

        if ($('.banglaqr-tab-btn[data-tab="' + savedTab + '"]').length) {
            switchAdminTab(savedTab);
        } else {
            switchAdminTab('general');
        }
    }

    // Initial render
    renderTable();
});
