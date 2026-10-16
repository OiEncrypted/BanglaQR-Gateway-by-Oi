/**
 * Frontend JS for Bangla QR Payment Method WooCommerce Gateway
 */

jQuery(document).ready(function ($) {
    // Safety check: verify parameters exist
    if (typeof oi_banglaqr_params === 'undefined') {
        return;
    }

    var selectedFile = null;
    var selectedBase64 = null;
    var selectedFileName = 'receipt.jpg';
    var uploadInProgress = false;
    var currentObjectUrl = null;
    var $lastActiveElement = null;

    // Escaping helper
    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    function escAttr(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }

    // Dynamic color helpers for conversion color system
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

    // Get dynamic checkout total from DOM, fallback to localized total
    function getCurrentOrderTotal() {
        var total = '';
        var $domTotal = $('.order-total strong span.woocommerce-Price-amount, .order-total strong, .order-total .amount').first();
        if ($domTotal.length) {
            total = $domTotal.text().trim();
        }
        if (!total) {
            total = oi_banglaqr_params.order_total || '0';
        }
        return total;
    }

    // Update payable amount in modal without rebuilding HTML
    function updateModalAmounts() {
        var total = getCurrentOrderTotal();
        $('#banglaqr-modal-payable-val').html(total); // using html to retain price formatting
        var cleanAmount = String(total).replace(/[^0-9\.]/g, '');
        if (cleanAmount) {
            $('#banglaqr-copy-amount-btn').attr('data-amount', cleanAmount);
        }
    }

    function copyTextToClipboard(text, callback) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (callback) callback();
            }).catch(function () {
                fallbackCopyText(text, callback);
            });
        } else {
            fallbackCopyText(text, callback);
        }
    }

    function fallbackCopyText(text, callback) {
        var tempInput = document.createElement('textarea');
        tempInput.value = text;
        tempInput.style.position = 'fixed';
        tempInput.style.left = '-9999px';
        tempInput.style.top = '0';
        document.body.appendChild(tempInput);
        tempInput.focus();
        tempInput.select();
        try {
            document.execCommand('copy');
            if (callback) callback();
        } catch (err) {
            console.error('Fallback copy failed', err);
        }
        document.body.removeChild(tempInput);
    }

    // Bind events
    function setupModalEvents() {
        if ($('#banglaqr-modal').data('events-bound')) return;
        $('#banglaqr-modal').data('events-bound', true);

        // Copy to clipboard with fallback and debounce/timer management
        var defaultCopyHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span>Copy</span>';
        var copiedHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Copied</span>';

        $(document).off('click', '.banglaqr-manual-copy-btn').on('click', '.banglaqr-manual-copy-btn', function(e) {
            e.preventDefault();
            var btn = $(this);
            var num = String(btn.data('number') || '');

            // Clear any active timer so rapid clicks don't get stuck on 'Copied'
            var activeTimer = btn.data('copy-timer');
            if (activeTimer) {
                clearTimeout(activeTimer);
                btn.removeData('copy-timer');
            }

            copyTextToClipboard(num, function() {
                btn.html(copiedHtml);
                btn.addClass('copied');

                var timerId = setTimeout(function() {
                    btn.html(defaultCopyHtml);
                    btn.removeClass('copied');
                    btn.removeData('copy-timer');
                }, 2000);

                btn.data('copy-timer', timerId);
            });
        });

        // Copy payable amount to clipboard
        var defaultCopyAmountHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span class="banglaqr-copy-amount-text">' + escHtml(oi_banglaqr_params.i18n_copy_amount || 'Copy') + '</span>';
        var copiedAmountHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="20 6 9 17 4 12"></polyline></svg><span class="banglaqr-copy-amount-text">' + escHtml(oi_banglaqr_params.i18n_amount_copied || 'Copied') + '</span>';

        $(document).off('click', '#banglaqr-copy-amount-btn').on('click', '#banglaqr-copy-amount-btn', function(e) {
            e.preventDefault();
            var btn = $(this);
            var amt = btn.attr('data-amount') || '';
            if (!amt) {
                amt = getCurrentOrderTotal();
            }
            var cleanAmt = String(amt).replace(/[^0-9\.]/g, '');

            copyTextToClipboard(cleanAmt, function() {
                btn.html(copiedAmountHtml).addClass('copied');
                setTimeout(function() {
                    btn.html(defaultCopyAmountHtml).removeClass('copied');
                }, 2000);
            });
        });

        // Download / Save QR Code Handler for Mobile & Desktop
        $(document).off('click', '#banglaqr-download-qr-btn').on('click', '#banglaqr-download-qr-btn', function(e) {
            var qrUrl = $(this).attr('href');
            if (!qrUrl) return;

            try {
                fetch(qrUrl)
                    .then(function(res) { return res.blob(); })
                    .then(function(blob) {
                        var blobUrl = window.URL.createObjectURL(blob);
                        var tempA = document.createElement('a');
                        tempA.style.display = 'none';
                        tempA.href = blobUrl;
                        tempA.download = 'banglaqr-pay.png';
                        document.body.appendChild(tempA);
                        tempA.click();
                        document.body.removeChild(tempA);
                        setTimeout(function() { window.URL.revokeObjectURL(blobUrl); }, 1000);
                    })
                    .catch(function() {
                        // If cross-origin fetch is restricted, native browser link download/open handles it
                    });
            } catch (err) {}
        });

        // TrxID Smart Validation (Clean prefixes on blur/change to preserve smooth mobile typing)
        $('#banglaqr-trx-input').on('change blur', function() {
            var val = $(this).val();
            if (val) {
                var cleaned = val.replace(/^(trxid|txnid|transaction\s*id|trans\s*id|ref|reference)[:\s\-\.]+/i, '').trim();
                $(this).val(cleaned.toUpperCase());
            }
        });

        // Trigger submission when pressing Enter inside TrxID input
        $('#banglaqr-trx-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#banglaqr-btn-submit').click();
            }
        });

        // Renew expired session
        $(document).on('click', '#banglaqr-timer-renew-btn', function (e) {
            e.preventDefault();
            hideError();
            startTimer();
        });

        // Cancel/Close modal
        $('#banglaqr-modal-close-btn, #banglaqr-btn-cancel').on('click', function (e) {
            e.preventDefault();
            if (uploadInProgress) return;
            closeModal();
        });

        // Trigger file input click when clicking dropzone
        $('#banglaqr-dropzone').on('click keydown', function (e) {
            if (uploadInProgress) return;
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            if (e.target.id !== 'banglaqr-file-input') {
                e.preventDefault();
                $('#banglaqr-file-input').click();
            }
        });

        // Handle file change
        $('#banglaqr-file-input').on('change', function (e) {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        // Drag and drop events
        $('#banglaqr-dropzone').on('dragover', function (e) {
            e.preventDefault();
            if (uploadInProgress) return;
            $(this).addClass('is-dragover');
        });

        $('#banglaqr-dropzone').on('dragleave', function (e) {
            e.preventDefault();
            $(this).removeClass('is-dragover');
        });

        $('#banglaqr-dropzone').on('drop', function (e) {
            e.preventDefault();
            if (uploadInProgress) return;
            $(this).removeClass('is-dragover');

            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        // Open QR Zoom Lightbox
        $('#banglaqr-qr-box').on('click keydown', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            e.preventDefault();
            openQrZoom();
        });

        // Close QR Zoom Lightbox
        $('#banglaqr-zoom-close-btn, #banglaqr-zoom-dismiss-btn').on('click', function (e) {
            e.preventDefault();
            closeQrZoom();
        });

        $('#banglaqr-zoom-overlay').on('click', function (e) {
            if ($(e.target).closest('.banglaqr-zoom-card').length === 0) {
                closeQrZoom();
            }
        });

        // Toggle Transaction ID input field
        $('#banglaqr-trx-toggle-btn').on('click', function (e) {
            e.preventDefault();
            var $container = $('#banglaqr-trx-input-container');
            var $btn = $(this);

            if ($container.is(':visible')) {
                $container.slideUp(180);
                $btn.removeClass('is-open').attr('aria-expanded', 'false');
            } else {
                $container.slideDown(200, function () {
                    $('#banglaqr-trx-input').focus();
                });
                $btn.addClass('is-open').attr('aria-expanded', 'true');
            }
        });

        // Toggle MFS accounts container
        $('#banglaqr-mfs-toggle-btn').on('click', function (e) {
            e.preventDefault();
            var $container = $('#banglaqr-mfs-accounts-container');
            var $btn = $(this);

            if ($container.is(':visible')) {
                $container.slideUp(180);
                $btn.removeClass('is-open').attr('aria-expanded', 'false');
            } else {
                $container.slideDown(200);
                $btn.addClass('is-open').attr('aria-expanded', 'true');
            }
        });

        // Submit form inside modal
        $('#banglaqr-btn-submit').on('click', function (e) {
            e.preventDefault();
            if (uploadInProgress) return;

            hideError();

            var trxId = $('#banglaqr-trx-input').val() ? $.trim($('#banglaqr-trx-input').val()) : '';

            // Validate required fields based on rules
            if (oi_banglaqr_params.receipt_rule === 'mandatory' && !selectedFile) {
                showError(oi_banglaqr_params.i18n_receipt_required || 'Please upload your payment receipt screenshot before completing your order.');
                return;
            }

            if (oi_banglaqr_params.trxid_rule === 'mandatory' && !trxId) {
                showError(oi_banglaqr_params.i18n_trx_required || 'Please enter your payment Transaction ID before completing your order.');
                return;
            }

            // Require at least one only if both receipt and TrxID are optional
            if (oi_banglaqr_params.receipt_rule === 'optional' && oi_banglaqr_params.trxid_rule === 'optional') {
                if (!selectedFile && !trxId) {
                    showError(oi_banglaqr_params.error_no_file);
                    return;
                }
            }

            // If file is selected, validate it
            if (selectedFile) {
                if (selectedFile.size > oi_banglaqr_params.max_file_size) {
                    showError(oi_banglaqr_params.error_file_too_large);
                    return;
                }

                if (!selectedFile.type.match('image.*')) {
                    showError(oi_banglaqr_params.error_invalid_file);
                    return;
                }

                uploadFile(trxId);
            } else {
                // Transaction ID only (no file attached)
                submitWithTrxIdOnly(trxId);
            }
        });

        // Focus trap & ESC key handling
        $(document).on('keydown.banglaqr', function (e) {
            if (e.key === 'Escape') {
                if ($('#banglaqr-zoom-overlay').is(':visible')) {
                    closeQrZoom();
                } else if ($('#banglaqr-modal').hasClass('is-active') && !uploadInProgress) {
                    closeModal();
                }
            } else if (e.key === 'Tab' && $('#banglaqr-modal').hasClass('is-active')) {
                // Focus trap within modal
                var $focusable = $('#banglaqr-modal').find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
                if ($focusable.length === 0) return;

                var $first = $focusable.first();
                var $last = $focusable.last();

                if (e.shiftKey) {
                    if (document.activeElement === $first[0]) {
                        e.preventDefault();
                        $last.focus();
                    }
                } else {
                    if (document.activeElement === $last[0]) {
                        e.preventDefault();
                        $first.focus();
                    }
                }
            }
        });
    }

    // QR Zoom Helpers
    function openQrZoom() {
        $('#banglaqr-zoom-overlay').fadeIn(200).addClass('is-active');
        $('#banglaqr-zoom-close-btn').focus();
    }

    function closeQrZoom() {
        $('#banglaqr-zoom-overlay').fadeOut(150).removeClass('is-active');
        $('#banglaqr-qr-box').focus();
    }

    // Image compression utility with aggressive lightweight optimization (guaranteed < 1MB)
    function compressImage(file, callback) {
        function fallbackToOriginal() {
            callback(file, null);
        }

        if (!window.FileReader || !document.createElement('canvas').getContext) {
            fallbackToOriginal();
            return;
        }

        // If file is already small (<= 2MB), keep original file and format directly without compression
        if (file && file.size <= 2 * 1024 * 1024) {
            fallbackToOriginal();
            return;
        }

        var reader = new FileReader();

        reader.onerror = function () {
            fallbackToOriginal();
        };

        reader.onload = function (event) {
            var img = new Image();

            img.onerror = function () {
                fallbackToOriginal();
            };

            img.onload = function () {
                try {
                    var canvas = document.createElement('canvas');
                    var ctx = canvas.getContext('2d');
                    if (!ctx) {
                        fallbackToOriginal();
                        return;
                    }

                    var MAX_DIM = 1200;
                    var width = img.width;
                    var height = img.height;

                    if (width > height) {
                        if (width > MAX_DIM) {
                            height = Math.round(height * (MAX_DIM / width));
                            width = MAX_DIM;
                        }
                    } else {
                        if (height > MAX_DIM) {
                            width = Math.round(width * (MAX_DIM / height));
                            height = MAX_DIM;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    // Fill with white background to prevent transparent PNG screenshots from having black background
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, width, height);
                    ctx.drawImage(img, 0, 0, width, height);

                    var baseName = (file.name || 'receipt').replace(/\.[^/.]+$/, '');
                    if (!baseName) {
                        baseName = 'receipt';
                    }

                    if (!canvas.toBlob) {
                        var base64Data = null;
                        try {
                            base64Data = canvas.toDataURL('image/jpeg', 0.7);
                        } catch (e) {}
                        callback(file, base64Data);
                        return;
                    }

                    function exportBlob(quality) {
                        canvas.toBlob(function (blob) {
                            if (!blob) {
                                fallbackToOriginal();
                                return;
                            }

                            // If still larger than 1MB and quality can be reduced, compress further
                            if (blob.size > 1024 * 1024 && quality > 0.4) {
                                exportBlob(quality - 0.15);
                                return;
                            }

                            var newFile;
                            try {
                                newFile = new File([blob], baseName + '.jpg', {
                                    type: 'image/jpeg',
                                    lastModified: Date.now()
                                });
                            } catch (e) {
                                // Fallback for Safari iOS < 14 and WebViews where new File throws TypeError
                                newFile = blob;
                                newFile.name = baseName + '.jpg';
                            }

                            // Release memory
                            img.onload = null;
                            img.onerror = null;
                            img.src = '';
                            canvas.width = 0;
                            canvas.height = 0;
                            callback(newFile, null);
                        }, 'image/jpeg', quality);
                    }

                    exportBlob(0.65);
                } catch (err) {
                    fallbackToOriginal();
                }
            };

            img.src = event.target.result;
        };

        try {
            reader.readAsDataURL(file);
        } catch (e) {
            fallbackToOriginal();
        }
    }

    // Process file validation and rendering previews
    function handleFileSelect(file) {
        hideError();

        // Check file type robustly (handles empty file.type on mobile browsers)
        var isImage = (file.type && file.type.match('image.*')) || (/\.(jpe?g|png|webp|gif)$/i.test(file.name));
        if (!isImage) {
            showError(oi_banglaqr_params.error_invalid_file);
            return;
        }

        selectedFileName = file.name || 'receipt.jpg';

        // Compress image before proceeding
        compressImage(file, function (compressedFile, base64Data) {
            var maxAllowed = parseInt(oi_banglaqr_params.max_file_size, 10) || (5 * 1024 * 1024);
            // Check file size on compressed file against server limits
            if (compressedFile.size > maxAllowed) {
                showError(oi_banglaqr_params.error_file_too_large);
                return;
            }

            selectedFile = compressedFile;
            selectedBase64 = base64Data;

            // Revoke old object URL if exists to prevent memory leaks
            if (currentObjectUrl) {
                URL.revokeObjectURL(currentObjectUrl);
            }
            currentObjectUrl = URL.createObjectURL(compressedFile);
            var sizeInMb = (compressedFile.size / (1024 * 1024)).toFixed(2) + ' MB';

            $('#banglaqr-file-preview-container').html(
                '<div class="banglaqr-file-preview-card">' +
                '  <div class="banglaqr-file-thumbnail" id="banglaqr-file-thumbnail-btn" style="background-image: url(' + currentObjectUrl + '); cursor: pointer;" title="Click to enlarge"></div>' +
                '  <div class="banglaqr-file-info">' +
                '    <div class="banglaqr-file-name" title="' + escAttr(file.name) + '">' + escHtml(file.name) + '</div>' +
                '    <div class="banglaqr-file-size">' + sizeInMb + '</div>' +
                '    <div class="banglaqr-progress-container" style="display:none;">' +
                '      <div class="banglaqr-progress-bar" id="banglaqr-progress-bar"></div>' +
                '    </div>' +
                '  </div>' +
                '  <div class="banglaqr-file-preview-actions">' +
                '    <button type="button" class="banglaqr-change-file-btn" id="banglaqr-change-file" aria-label="Change file">' +
                '      <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>' +
                '      <span>' + escHtml(oi_banglaqr_params.i18n_change_file || 'Change') + '</span>' +
                '    </button>' +
                '    <button type="button" class="banglaqr-remove-file-btn" id="banglaqr-remove-file" aria-label="Remove file">' +
                '      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>' +
                '    </button>' +
                '  </div>' +
                '</div>'
            );

            // Auto-collapse dropzone to maximize mobile screen space
            $('#banglaqr-dropzone').hide();

            // Bind change action
            $('#banglaqr-change-file').on('click', function (e) {
                e.preventDefault();
                if (uploadInProgress) return;
                $('#banglaqr-file-input').click();
            });

            // Bind delete action
            $('#banglaqr-remove-file').on('click', function (e) {
                e.preventDefault();
                if (uploadInProgress) return;
                resetFileSelector();
            });

            // Bind zoom action
            $('#banglaqr-file-thumbnail-btn').on('click', function (e) {
                e.preventDefault();
                var zoomHtml = '  <div id="banglaqr-receipt-zoom-overlay" class="banglaqr-zoom-overlay is-active" style="display:flex; z-index: 1000000;" role="dialog" aria-modal="true">';
                zoomHtml += '    <div class="banglaqr-zoom-card">';
                zoomHtml += '      <div class="banglaqr-zoom-header">';
                zoomHtml += '        <div class="banglaqr-zoom-title-box"><h4 class="banglaqr-zoom-title">' + escHtml(oi_banglaqr_params.i18n_payment_receipt) + '</h4></div>';
                zoomHtml += '        <button type="button" class="banglaqr-zoom-close" id="banglaqr-receipt-zoom-close-btn">';
                zoomHtml += '          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12" /></svg>';
                zoomHtml += '        </button>';
                zoomHtml += '      </div>';
                zoomHtml += '      <div class="banglaqr-zoom-img-wrap">';
                zoomHtml += '        <img src="' + currentObjectUrl + '" class="banglaqr-zoom-img" />';
                zoomHtml += '      </div>';
                zoomHtml += '      <button type="button" class="banglaqr-zoom-dismiss-btn" id="banglaqr-receipt-zoom-dismiss-btn">' + escHtml(oi_banglaqr_params.i18n_close_full_view) + '</button>';
                zoomHtml += '    </div>';
                zoomHtml += '  </div>';

                $('body').append(zoomHtml);

                $('#banglaqr-receipt-zoom-close-btn, #banglaqr-receipt-zoom-dismiss-btn, #banglaqr-receipt-zoom-overlay').on('click', function(e) {
                    if (e.target.id === 'banglaqr-receipt-zoom-overlay' || e.currentTarget.tagName === 'BUTTON') {
                        $('#banglaqr-receipt-zoom-overlay').remove();
                    }
                });
            });
        });
    }

    function resetFileSelector() {
        selectedFile = null;
        selectedBase64 = null;
        selectedFileName = 'receipt.jpg';
        if (currentObjectUrl) {
            URL.revokeObjectURL(currentObjectUrl);
            currentObjectUrl = null;
        }
        $('#banglaqr-file-input').val('');
        $('#banglaqr-file-preview-container').empty();
        $('#banglaqr-dropzone').show();
        hideError();
    }

    function showError(msg, isHtml) {
        var $banner = $('#banglaqr-error-banner');
        if (isHtml) {
            $banner.html(msg);
        } else {
            $banner.text(msg);
        }
        $banner.fadeIn(200).addClass('banglaqr-shake');
        setTimeout(function () {
            $banner.removeClass('banglaqr-shake');
        }, 400);
    }

    function hideError() {
        $('#banglaqr-error-banner').fadeOut(100).removeClass('banglaqr-shake').empty();
    }

    var timeLeft = 15 * 60;

    function startTimer() {
        if (window.banglaqrTimerInterval) {
            clearInterval(window.banglaqrTimerInterval);
        }
        
        timeLeft = 15 * 60; // reset to 15 mins
        var timerDisplay = $('#banglaqr-timer-text');
        var timerContainer = $('#banglaqr-countdown-timer');
        
        timerDisplay.text('15:00');
        timerContainer.removeClass('danger');
        $('#banglaqr-btn-submit').prop('disabled', false).css({ 'opacity': '1', 'cursor': 'pointer' });
        $('#banglaqr-file-input').prop('disabled', false);
        $('#banglaqr-trx-input').prop('disabled', false);

        window.banglaqrTimerInterval = setInterval(function() {
            timeLeft--;
            var minutes = Math.floor(timeLeft / 60);
            var seconds = timeLeft % 60;
            
            var displayStr = (minutes < 10 ? '0' + minutes : minutes) + ':' + (seconds < 10 ? '0' + seconds : seconds);
            timerDisplay.text(displayStr);
            
            if (timeLeft <= 60) {
                timerContainer.addClass('danger');
            }
            
            if (timeLeft <= 0) {
                clearInterval(window.banglaqrTimerInterval);
                var expiredHtml = '<span>' + escHtml(oi_banglaqr_params.i18n_session_expired) + '</span> <button type="button" id="banglaqr-timer-renew-btn" style="background:#fee2e2; border:1px solid #fecaca; color:#b91c1c; font-weight:700; border-radius:6px; cursor:pointer; padding:2px 8px; margin-left:6px; font-size:12px;">' + escHtml(oi_banglaqr_params.i18n_extend_time) + '</button>';
                showError(expiredHtml, true);
                // Keep inputs and submit button active so customers who already paid can still complete order without panic
            }
        }, 1000);
    }

    function openModal() {
        $lastActiveElement = document.activeElement;
        
        var themeColor = oi_banglaqr_params.theme_color || '#0A694B';
        var modal = document.getElementById('banglaqr-modal');
        if (modal) {
            var hoverColor = adjustColorBrightness(themeColor, -15);
            var lightTint = hexToRgba(themeColor, 0.08);
            var borderTint = hexToRgba(themeColor, 0.25);

            modal.style.setProperty('--banglaqr-primary', themeColor);
            modal.style.setProperty('--banglaqr-modal-primary', themeColor);
            modal.style.setProperty('--banglaqr-primary-hover', hoverColor);
            modal.style.setProperty('--banglaqr-modal-primary-hover', hoverColor);
            modal.style.setProperty('--banglaqr-primary-light', lightTint);
            modal.style.setProperty('--banglaqr-modal-primary-light', lightTint);
            modal.style.setProperty('--banglaqr-primary-border', borderTint);
            modal.style.setProperty('--banglaqr-modal-primary-border', borderTint);
        }

        setupModalEvents();
        updateModalAmounts();
        hideError();
        $('#banglaqr-success-overlay').hide();
        $('#banglaqr-btn-submit').prop('disabled', false).removeClass('loading').html(escHtml(oi_banglaqr_params.i18n_confirm_btn_default || 'Confirm & Place Order'));
        $('#banglaqr-btn-cancel').prop('disabled', false);
        startTimer();

        // Show overlay
        $('#banglaqr-modal').addClass('is-active');
        $('body').css('overflow', 'hidden'); // block background scrolling

        // Focus close button
        setTimeout(function () {
            $('#banglaqr-modal-close-btn').focus();
        }, 50);
    }

    function closeModal() {
        if (window.banglaqrTimerInterval) {
            clearInterval(window.banglaqrTimerInterval);
        }

        hideError();
        $('#banglaqr-success-overlay').hide();
        $('#banglaqr-modal').removeClass('is-active');
        $('body').css('overflow', ''); // restore scroll

        if ($lastActiveElement && $($lastActiveElement).is(':visible')) {
            $($lastActiveElement).focus();
        }
    }

    function getCheckoutForm() {
        var $form = $('form.checkout');
        if (!$form.length) {
            $form = $('form#order_review');
        }
        return $form;
    }

    function playSuccessAnimationAndSubmit() {
        // Non-destructive overlay animation - preserves all inner form elements
        $('#banglaqr-success-overlay').fadeIn(150);

        setTimeout(function() {
            uploadInProgress = false;
            closeModal();
            var $form = getCheckoutForm();
            if ($form.length) {
                $form.submit();
            }
        }, 1000); // 1000ms snappy response
    }

    // Submit with Transaction ID Only (No Image Upload)
    function submitWithTrxIdOnly(trxId) {
        uploadInProgress = true;

        var activeQrName = 'QR Payment';
        if (oi_banglaqr_params.active_qr && oi_banglaqr_params.active_qr.qr_name) {
            activeQrName = oi_banglaqr_params.active_qr.qr_name;
        }

        // Store metadata in hidden checkout inputs
        $('#oi_banglaqr_receipt_id').val('');
        $('#oi_banglaqr_transaction_id').val(trxId);
        $('#oi_banglaqr_selected_qr').val(activeQrName);
        $('#oi_banglaqr_confirmed').val('1');

        // Render preview snippet on checkout page
        var previewMarkup = '<div class="banglaqr-preview-header">' +
            '<div class="banglaqr-preview-title">' +
                '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
                '<span>' + escHtml(oi_banglaqr_params.i18n_payment_details_attached) + '</span>' +
            '</div>' +
            '<a href="#" id="banglaqr-change-receipt-btn" class="banglaqr-preview-change-btn">' +
                '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>' +
                '<span>' + escHtml(oi_banglaqr_params.i18n_edit) + '</span>' +
            '</a>' +
        '</div>' +
        '<div class="banglaqr-preview-meta">' +
            '<div class="banglaqr-preview-meta-item"><span><strong>' + escHtml(oi_banglaqr_params.i18n_account) + '</strong> ' + escHtml(activeQrName) + '</span></div>' +
            '<div class="banglaqr-preview-meta-item"><span><strong>' + escHtml(oi_banglaqr_params.i18n_transaction_id) + '</strong> <span class="banglaqr-preview-tag">' + escHtml(trxId) + '</span></span></div>' +
        '</div>';
        $('#banglaqr-selected-qr-preview').html(previewMarkup).show();

        var $submitBtn = $('#banglaqr-btn-submit');
        $('#banglaqr-btn-cancel').prop('disabled', true);
        $submitBtn.prop('disabled', true).addClass('loading').html('<span class="banglaqr-spinner"></span> <span>' + escHtml(oi_banglaqr_params.i18n_confirming_order_btn || 'Confirming Order...') + '</span>');

        // Play animation and submit
        playSuccessAnimationAndSubmit();
    }

    // AJAX Upload and Compress (With optional TrxID)
    function uploadFile(trxId) {
        if (!selectedFile) return;

        uploadInProgress = true;

        // Show progress bar
        $('.banglaqr-progress-container').show();
        $('#banglaqr-progress-bar').css('width', '0%');

        // Disable buttons
        $('#banglaqr-btn-cancel, #banglaqr-remove-file').prop('disabled', true);
        var $submitBtn = $('#banglaqr-btn-submit');
        $submitBtn.prop('disabled', true).addClass('loading');
        $submitBtn.html('<span class="banglaqr-spinner"></span> <span>' + escHtml(oi_banglaqr_params.i18n_submitting_receipt || 'Submitting Receipt...') + '</span>');

        // Build FormData
        var formData = new FormData();
        formData.append('action', 'oi_banglaqr_upload_slip');
        formData.append('nonce', oi_banglaqr_params.upload_nonce);

        // Compute safe filename ensuring valid extension
        var safeName = selectedFileName || (selectedFile && selectedFile.name ? selectedFile.name : 'receipt.jpg');
        if (!/\.(jpe?g|png|webp|gif)$/i.test(safeName)) {
            safeName += '.jpg';
        }

        // Append file as standard multipart form data with explicit filename
        if (selectedFile) {
            formData.append('oi_banglaqr_file', selectedFile, safeName);
            formData.append('image_name', safeName);
        } else if (selectedBase64) {
            formData.append('image_base64', selectedBase64);
        }

        $.ajax({
            url: oi_banglaqr_params.ajax_url,
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function () {
                var myXhr = $.ajaxSettings.xhr();
                if (myXhr.upload) {
                    myXhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) {
                            var percentage = Math.round((e.loaded / e.total) * 100);
                            $('#banglaqr-progress-bar').css('width', percentage + '%');
                        }
                    }, false);
                }
                return myXhr;
            },
            success: function (response) {
                if (response && response.success && response.data) {
                    var activeQrName = 'QR Payment';
                    if (oi_banglaqr_params.active_qr && oi_banglaqr_params.active_qr.qr_name) {
                        activeQrName = oi_banglaqr_params.active_qr.qr_name;
                    }

                    // Read attachment ID robustly (supporting both 'attachment_id' and 'id')
                    var attachmentId = response.data.attachment_id || response.data.id || '';

                    // Store details in checkout fields
                    $('#oi_banglaqr_receipt_id').val(attachmentId);
                    if (response.data && response.data.token) {
                        $('#oi_banglaqr_receipt_token').val(response.data.token);
                    }
                    $('#oi_banglaqr_transaction_id').val(trxId || '');
                    $('#oi_banglaqr_selected_qr').val(activeQrName);
                    $('#oi_banglaqr_confirmed').val('1');

                    // Render small success snippet on checkout page
                    var previewMarkup = '<div class="banglaqr-preview-header">' +
                        '<div class="banglaqr-preview-title">' +
                            '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>' +
                            '<span>' + escHtml(oi_banglaqr_params.i18n_payment_details_attached) + '</span>' +
                        '</div>' +
                        '<a href="#" id="banglaqr-change-receipt-btn" class="banglaqr-preview-change-btn">' +
                            '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>' +
                            '<span>' + escHtml(oi_banglaqr_params.i18n_edit) + '</span>' +
                        '</a>' +
                    '</div>' +
                    '<div class="banglaqr-preview-meta">' +
                        '<div class="banglaqr-preview-meta-item"><span><strong>' + escHtml(oi_banglaqr_params.i18n_account) + '</strong> ' + escHtml(activeQrName) + '</span></div>' +
                        '<div class="banglaqr-preview-meta-item"><span><strong>' + escHtml(oi_banglaqr_params.i18n_receipt) + '</strong> <a href="' + escAttr(response.data.url) + '" target="_blank" rel="noopener noreferrer" style="color: var(--banglaqr-modal-primary, var(--banglaqr-primary, #0A694B)); font-weight:600; text-decoration:underline;">' + escHtml(oi_banglaqr_params.i18n_view_receipt) + '</a></span></div>';
                    if (trxId) {
                        previewMarkup += '<div class="banglaqr-preview-meta-item"><span><strong>' + escHtml(oi_banglaqr_params.i18n_transaction_id) + '</strong> <span class="banglaqr-preview-tag">' + escHtml(trxId) + '</span></span></div>';
                    }
                    previewMarkup += '</div>';

                    $('#banglaqr-selected-qr-preview').html(previewMarkup).show();

                    $submitBtn.html('<span class="banglaqr-spinner"></span> <span>' + escHtml(oi_banglaqr_params.i18n_confirming_order_btn || 'Confirming Order...') + '</span>');

                    playSuccessAnimationAndSubmit();
                } else {
                    handleUploadError(response && response.data && response.data.message ? response.data.message : (oi_banglaqr_params.i18n_upload_failed || 'We could not upload your receipt image. Please try again or use another format.'));
                }
            },
            error: function (xhr) {
                var errorMsg = oi_banglaqr_params.i18n_upload_network_error || 'We could not upload your receipt due to a network connection issue. Please check your internet and try again.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                }
                handleUploadError(errorMsg);
            }
        });
    }

    function handleUploadError(errMsg) {
        uploadInProgress = false;
        $('.banglaqr-progress-container').hide();
        $('#banglaqr-progress-bar').css('width', '0%');

        // Re-enable actions
        $('#banglaqr-btn-cancel, #banglaqr-remove-file').prop('disabled', false);
        var $submitBtn = $('#banglaqr-btn-submit');
        $submitBtn.prop('disabled', false).removeClass('loading');
        $submitBtn.html(escHtml(oi_banglaqr_params.i18n_confirm_btn_default || 'Confirm & Place Order'));

        showError(errMsg);
    }

    // Validate checkout required fields before triggering popup
    function validateCheckoutForm() {
        var $form = getCheckoutForm();
        if (!$form.length || !$form.hasClass('checkout')) {
            return true;
        }

        var errors = [];

        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error').remove();
        $('form.checkout .woocommerce-invalid').removeClass('woocommerce-invalid');

        // Loop through WooCommerce required fields
        $('form.checkout .validate-required').each(function () {
            var $row = $(this);
            if (!$row.is(':visible')) {
                return;
            }

            var $input = $row.find('input, select, textarea');
            if ($input.length === 0) {
                return;
            }

            var val = $input.val();
            var labelText = $row.find('label').text().replace(/\*/g, '').trim();
            if (!labelText) {
                labelText = $input.attr('placeholder') || $input.attr('name') || 'Required field';
            }

            var reqMsg = (oi_banglaqr_params.i18n_required_field || '%s is a required field.').replace('%s', '<strong>' + escHtml(labelText) + '</strong>');

            if ($input.is(':checkbox')) {
                if (!$input.is(':checked')) {
                    errors.push(reqMsg);
                    $row.addClass('woocommerce-invalid');
                }
            } else {
                if (!val || val.trim() === '') {
                    errors.push(reqMsg);
                    $row.addClass('woocommerce-invalid');
                } else {
                    if ($input.attr('type') === 'email' || ($input.attr('name') && $input.attr('name').indexOf('email') !== -1)) {
                        var emailReg = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailReg.test(val)) {
                            var emailMsg = (oi_banglaqr_params.i18n_valid_email || 'Please enter a valid email address for %s.').replace('%s', '<strong>' + escHtml(labelText) + '</strong>');
                            errors.push(emailMsg);
                            $row.addClass('woocommerce-invalid');
                        }
                    }
                }
            }
        });

        // Verify terms and conditions checkbox
        var $terms = $('#terms');
        if ($terms.length && $terms.is(':visible') && !$terms.is(':checked')) {
            errors.push(oi_banglaqr_params.i18n_terms || 'You must accept the terms and conditions.');
            $terms.closest('p').addClass('woocommerce-invalid');
        }

        if (errors.length > 0) {
            var errorHtml = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">';
            errorHtml += '  <ul class="woocommerce-error" role="alert">';
            $.each(errors, function (idx, err) {
                errorHtml += '    <li>' + err + '</li>';
            });
            errorHtml += '  </ul>';
            errorHtml += '</div>';

            $form.prepend(errorHtml);

            $('html, body').animate({
                scrollTop: ($form.offset().top - 100)
            }, 300);

            return false;
        }

        return true;
    }

    // Reset confirmation and dynamically update WooCommerce checkout totals when payment method changes
    $(document).on('change', 'input[name="payment_method"]', function () {
        var selectedMethod = $(this).val();
        if (selectedMethod !== oi_banglaqr_params.gateway_id) {
            $('#oi_banglaqr_confirmed').val('0');
        }
        // Force WooCommerce checkout to recalculate so processing fees appear/disappear dynamically
        $(document.body).trigger('update_checkout');
    });

    // When WooCommerce finishes updating the checkout fragments, keep modal amount in sync
    $(document.body).on('updated_checkout payment_method_selected', function () {
        updateModalAmounts();
    });

    // Intercept checkout submit button
    $(document).on('click', 'form.checkout #place_order, form#order_review #place_order, #place_order, button[name="woocommerce_checkout_place_order"]', function (e) {
        var activePaymentMethod = $('input[name="payment_method"]:checked').val();

        if (activePaymentMethod === oi_banglaqr_params.gateway_id) {
            var isConfirmed = $('#oi_banglaqr_confirmed').val() === '1';
            var receiptId = $('#oi_banglaqr_receipt_id').val();
            var trxId = $('#oi_banglaqr_transaction_id').val();

            // If confirmed via modal or values are already set, allow standard form submission
            if (isConfirmed || (receiptId && receiptId !== '') || (trxId && trxId !== '')) {
                return true;
            }

            if (!validateCheckoutForm()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }

            e.preventDefault();
            e.stopPropagation();

            openModal();
            return false;
        }
    });

    // In case WooCommerce triggers submission via event (use delegated event so AJAX fragment refresh never breaks it)
    $(document).on('checkout_place_order_' + oi_banglaqr_params.gateway_id, 'form.checkout, form#order_review', function () {
        var isConfirmed = $('#oi_banglaqr_confirmed').val() === '1';
        var receiptId = $('#oi_banglaqr_receipt_id').val();
        var trxId = $('#oi_banglaqr_transaction_id').val();

        if (isConfirmed || (receiptId && receiptId !== '') || (trxId && trxId !== '')) {
            return true;
        }

        if (!validateCheckoutForm()) {
            return false;
        }

        openModal();
        return false;
    });

    // Listen to "Change" receipt/TrxID selection link on checkout page
    $(document).on('click', '#banglaqr-change-receipt-btn', function (e) {
        e.preventDefault();
        $('#oi_banglaqr_receipt_id').val('');
        $('#oi_banglaqr_transaction_id').val('');
        $('#oi_banglaqr_selected_qr').val('');
        $('#oi_banglaqr_confirmed').val('0');
        $('#banglaqr-selected-qr-preview').hide().empty();

        openModal();
    });

    // Listen to WooCommerce checkout errors to reset buttons
    $(document.body).on('checkout_error', function () {
        $('#banglaqr-btn-submit').prop('disabled', false).removeClass('loading').html(escHtml(oi_banglaqr_params.i18n_confirm_btn_default || 'Confirm & Place Order'));
        $('#banglaqr-btn-cancel, #banglaqr-remove-file').prop('disabled', false);
        $('.banglaqr-progress-container').hide();
    });
});
