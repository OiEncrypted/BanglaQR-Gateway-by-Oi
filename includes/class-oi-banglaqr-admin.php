<?php
/**
 * Admin logic and screens interface for Oi_BanglaQR
 */

defined('ABSPATH') || exit;

#[\AllowDynamicProperties]
class Oi_BanglaQR_Admin
{

    /**
     * Track displayed orders to prevent duplicate receipt markup.
     *
     * @var array
     */
    protected static $rendered_orders = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Display in admin order details (Legacy hook & HPOS meta box)
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_slip_in_admin'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));

        // Admin Bar live count notification
        add_action('admin_bar_menu', array($this, 'add_admin_bar_pending_orders_count'), 95);

        // WordPress Dashboard Widget for BanglaQR pending verification orders
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));

        // Invalidate orders count cache on order status change or new order
        add_action('woocommerce_order_status_changed', array($this, 'clear_orders_count_cache'));
        add_action('woocommerce_new_order', array($this, 'clear_orders_count_cache'));
        add_action('woocommerce_trash_order', array($this, 'clear_orders_count_cache'));
        add_action('woocommerce_delete_order', array($this, 'clear_orders_count_cache'));
    }

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook Admin screen hook.
     */
    public function enqueue_admin_assets($hook)
    {
        // Enqueue only on relevant screens: settings, orders, or dashboard
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_settings_page = isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'wc-settings';
        $is_dashboard     = ($hook === 'index.php');

        // Check for WooCommerce order screens (Classic and HPOS)
        $is_order_page = false;
        $screen = get_current_screen();
        if ($screen) {
            if ($screen->id === 'shop_order' || $screen->id === 'woocommerce_page_wc-orders' || strpos($screen->id, 'wc-orders') !== false || (isset($screen->post_type) && $screen->post_type === 'shop_order')) {
                $is_order_page = true;
            }
        }

        if (!$is_settings_page && !$is_order_page && !$is_dashboard) {
            return;
        }

        $handle = 'banglaqr-admin';

        $css_ver = file_exists(OI_BANGLAQR_PATH . 'includes/css/admin.css') ? filemtime(OI_BANGLAQR_PATH . 'includes/css/admin.css') : OI_BANGLAQR_VERSION;
        $js_ver  = file_exists(OI_BANGLAQR_PATH . 'includes/js/admin.js') ? filemtime(OI_BANGLAQR_PATH . 'includes/js/admin.js') : OI_BANGLAQR_VERSION;

        // Enqueue styles
        wp_enqueue_style($handle, OI_BANGLAQR_URL . 'includes/css/admin.css', array(), $css_ver);

        // Enqueue script only on settings page to handle Repeatable QR manager
        if ($is_settings_page) {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-sortable');

            wp_enqueue_script($handle, OI_BANGLAQR_URL . 'includes/js/admin.js', array('jquery', 'jquery-ui-sortable'), $js_ver, true);

            wp_localize_script($handle, 'oi_banglaqr_admin_params', array(
                'media_title' => __('Select QR Image', 'banglaqr-payment-gateway-by-oi'),
                'media_button_text' => __('Use This QR Image', 'banglaqr-payment-gateway-by-oi'),
                'confirm_delete' => __('Are you sure you want to remove this QR account?', 'banglaqr-payment-gateway-by-oi'),
                'default_qr_url' => OI_BANGLAQR_URL . 'includes/img/testqr.png',
                'i18n_new_qr_account' => __('New QR Account', 'banglaqr-payment-gateway-by-oi'),
                'i18n_fee_suffix' => __('% Fee', 'banglaqr-payment-gateway-by-oi'),
                'i18n_no_fee' => __('No Fee', 'banglaqr-payment-gateway-by-oi'),
                'i18n_active' => __('Active', 'banglaqr-payment-gateway-by-oi'),
                'i18n_inactive' => __('Inactive', 'banglaqr-payment-gateway-by-oi'),
                'i18n_scan_preview' => __('Scan Preview', 'banglaqr-payment-gateway-by-oi'),
                'i18n_scan_hint' => __('Scan with your phone to confirm this QR code reads properly.', 'banglaqr-payment-gateway-by-oi'),
                'i18n_account_name' => __('Account Name', 'banglaqr-payment-gateway-by-oi'),
                'i18n_account_name_ph' => __('e.g. bKash Merchant, City Bank BanglaQR', 'banglaqr-payment-gateway-by-oi'),
                'i18n_processing_fee' => __('Processing Fee (%)', 'banglaqr-payment-gateway-by-oi'),
                'i18n_processing_fee_ph' => __('e.g. 1.85 (leave 0 for no fee)', 'banglaqr-payment-gateway-by-oi'),
                'i18n_qr_code_image' => __('QR Code Image', 'banglaqr-payment-gateway-by-oi'),
                'i18n_upload_ph' => __('Upload or paste image URL', 'banglaqr-payment-gateway-by-oi'),
                'i18n_upload_btn' => __('Upload', 'banglaqr-payment-gateway-by-oi'),
                'i18n_active_on_checkout' => __('Active on Checkout', 'banglaqr-payment-gateway-by-oi'),
                'i18n_set_as_active' => __('Set as Active', 'banglaqr-payment-gateway-by-oi'),
                'i18n_remove_account' => __('Remove Account', 'banglaqr-payment-gateway-by-oi'),
                'i18n_no_qrs_msg' => __('You have not added any QR accounts yet. Add your first account to start accepting QR payments.', 'banglaqr-payment-gateway-by-oi'),
                'i18n_add_first_qr' => __('Add First QR Account', 'banglaqr-payment-gateway-by-oi'),
                'i18n_add_another_qr' => __('Add Another QR Account', 'banglaqr-payment-gateway-by-oi'),
                'i18n_enabled' => __('Enabled', 'banglaqr-payment-gateway-by-oi'),
                'i18n_disabled' => __('Disabled', 'banglaqr-payment-gateway-by-oi'),
                'i18n_gateway_active' => __('Gateway Active', 'banglaqr-payment-gateway-by-oi'),
                'i18n_gateway_inactive' => __('Gateway Inactive', 'banglaqr-payment-gateway-by-oi'),
                'i18n_select_gateway_logo' => __('Select Gateway Logo', 'banglaqr-payment-gateway-by-oi'),
                'i18n_use_logo' => __('Use Logo', 'banglaqr-payment-gateway-by-oi')
            ));
        }
    }

    /**
     * Clear orders count cache.
     */
    public function clear_orders_count_cache()
    {
        delete_transient('oi_banglaqr_pending_count');
    }

    /**
     * Get pending BanglaQR orders count with efficient 1-minute transient caching.
     *
     * @return int
     */
    public function get_pending_banglaqr_orders_count()
    {
        $cached = get_transient('oi_banglaqr_pending_count');
        if ($cached !== false) {
            return (int) $cached;
        }

        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        try {
            // Fetch order IDs that used BanglaQR and are awaiting verification
            $orders = wc_get_orders(array(
                'payment_method' => 'oi_banglaqr',
                'status'         => array('on-hold', 'pending'),
                'limit'          => 100,
                'return'         => 'ids',
            ));

            $count = is_array($orders) ? count($orders) : 0;
            set_transient('oi_banglaqr_pending_count', $count, 60);
            return $count;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get appropriate URL for orders list (Supports HPOS & Classic).
     *
     * @return string
     */
    public function get_orders_list_url()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            return admin_url('admin.php?page=wc-orders&status=wc-on-hold');
        }
        return admin_url('edit.php?post_type=shop_order&post_status=wc-on-hold');
    }

    /**
     * Add notification badge in top WordPress Admin Bar.
     *
     * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
     */
    public function add_admin_bar_pending_orders_count($wp_admin_bar)
    {
        if (!is_admin() && !is_admin_bar_showing()) {
            return;
        }

        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            return;
        }

        $count = $this->get_pending_banglaqr_orders_count();
        if ($count <= 0) {
            return;
        }

        $title = sprintf(
            '<span class="ab-icon dashicons dashicons-money-alt" style="margin-top:2px; font-size:16px;"></span><span class="ab-label">BanglaQR <span class="oi-banglaqr-adminbar-badge">%d</span></span>',
            $count
        );

        $wp_admin_bar->add_node(array(
            'id'    => 'oi_banglaqr_pending_bar',
            'title' => $title,
            'href'  => $this->get_orders_list_url(),
            'meta'  => array(
                'title' => sprintf(__('%d টি BanglaQR অর্ডার ভেরিফিকেশনের অপেক্ষায় আছে', 'banglaqr-payment-gateway-by-oi'), $count),
            ),
        ));
    }

    /**
     * Register Dashboard Widget.
     */
    public function add_dashboard_widget()
    {
        if (!current_user_can('edit_shop_orders') && !current_user_can('manage_woocommerce')) {
            return;
        }

        wp_add_dashboard_widget(
            'oi_banglaqr_dashboard_widget',
            __('BanglaQR - নতুন অর্ডার ও পেমেন্ট ভেরিফিকেশন', 'banglaqr-payment-gateway-by-oi'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Render Dashboard Widget content.
     */
    public function render_dashboard_widget()
    {
        if (!function_exists('wc_get_orders')) {
            echo '<p>' . esc_html__('WooCommerce is required.', 'banglaqr-payment-gateway-by-oi') . '</p>';
            return;
        }

        $pending_count = $this->get_pending_banglaqr_orders_count();

        // Get up to 5 recent orders for quick review
        $orders = wc_get_orders(array(
            'payment_method' => 'oi_banglaqr',
            'status'         => array('on-hold', 'pending'),
            'limit'          => 5,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $orders_url = $this->get_orders_list_url();
        ?>
        <div class="oi-banglaqr-widget-container">
            <div class="oi-banglaqr-widget-summary">
                <div class="oi-banglaqr-widget-count-box">
                    <span class="oi-banglaqr-widget-count"><?php echo intval($pending_count); ?></span>
                    <span class="oi-banglaqr-widget-count-label"><?php esc_html_e('পেন্ডিং অর্ডার (Awaiting Verification)', 'banglaqr-payment-gateway-by-oi'); ?></span>
                </div>
                <a href="<?php echo esc_url($orders_url); ?>" class="button button-primary oi-banglaqr-view-all-btn">
                    <?php esc_html_e('সব অর্ডার দেখুন', 'banglaqr-payment-gateway-by-oi'); ?> &rarr;
                </a>
            </div>

            <?php if (empty($orders)): ?>
                <div class="oi-banglaqr-widget-empty">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <p><?php esc_html_e('বর্তমানে কোনো পেন্ডিং BanglaQR অর্ডার নেই। সবগুলো ভেরিফাইড!', 'banglaqr-payment-gateway-by-oi'); ?></p>
                </div>
            <?php else: ?>
                <div class="oi-banglaqr-widget-table-wrap">
                    <table class="widefat oi-banglaqr-widget-table" style="margin-top:12px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('অর্ডার', 'banglaqr-payment-gateway-by-oi'); ?></th>
                            <th><?php esc_html_e('গ্রাহক', 'banglaqr-payment-gateway-by-oi'); ?></th>
                            <th><?php esc_html_e('পরিমাণ', 'banglaqr-payment-gateway-by-oi'); ?></th>
                            <th><?php esc_html_e('TrxID / স্লিপ', 'banglaqr-payment-gateway-by-oi'); ?></th>
                            <th><?php esc_html_e('অ্যাকশন', 'banglaqr-payment-gateway-by-oi'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            $order_id   = $order->get_id();
                            $trx_id     = $order->get_meta('_oi_banglaqr_transaction_id');
                            $receipt_id = $order->get_meta('_oi_banglaqr_receipt_id');
                            $edit_url   = $order->get_edit_order_url();
                            $customer   = trim((string)$order->get_formatted_billing_full_name());
                            if (empty($customer)) {
                                $customer = __('Guest', 'banglaqr-payment-gateway-by-oi');
                            }
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($edit_url); ?>"><strong>#<?php echo esc_html($order->get_order_number()); ?></strong></a>
                                    <div style="font-size:11px; color:#64748b;"><?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('M j, g:i a') : ''); ?></div>
                                </td>
                                <td><?php echo esc_html($customer); ?></td>
                                <td><strong><?php echo wp_kses_post($order->get_formatted_order_total()); ?></strong></td>
                                <td>
                                    <?php if (!empty($trx_id)): ?>
                                        <code style="font-size:11px; background:#f1f5f9; padding:2px 4px; border-radius:3px;"><?php echo esc_html($trx_id); ?></code>
                                    <?php endif; ?>
                                    <?php if (!empty($receipt_id)): 
                                        $img_url = is_numeric($receipt_id) ? wp_get_attachment_url($receipt_id) : $receipt_id;
                                    ?>
                                        <?php if ($img_url): ?>
                                            <a href="<?php echo esc_url($img_url); ?>" target="_blank" class="oi-banglaqr-slip-badge" title="<?php esc_attr_e('View Slip', 'banglaqr-payment-gateway-by-oi'); ?>">
                                                <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                <span><?php esc_html_e('স্লিপ', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                                        <?php esc_html_e('যাচাই করুন', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Register meta box for order details (HPOS & Classic compatible).
     */
    public function add_order_meta_box()
    {
        $screen = class_exists(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
            && function_exists('wc_get_container')
            && wc_get_container()->has(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)
            && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'oi_banglaqr_order_meta_box',
            __('BanglaQR পেমেন্ট রিসিট ও ট্রানজেকশন তথ্য', 'banglaqr-payment-gateway-by-oi'),
            array($this, 'render_order_meta_box'),
            $screen,
            'side',
            'high'
        );
    }

    /**
     * Render the order meta box.
     *
     * @param WP_Post|WC_Order $post_or_order Post or Order object.
     */
    public function render_order_meta_box($post_or_order)
    {
        $order = ($post_or_order instanceof WC_Order) ? $post_or_order : wc_get_order($post_or_order);
        if (!$order) {
            return;
        }

        if ($order->get_payment_method() !== 'oi_banglaqr') {
            echo '<p style="color:#64748b; margin:0;">' . esc_html__('এই অর্ডারে BanglaQR ব্যবহার করা হয়নি।', 'banglaqr-payment-gateway-by-oi') . '</p>';
            return;
        }

        self::$rendered_orders[$order->get_id()] = true;
        $this->render_slip_card_markup($order);
    }

    /**
     * Display the uploaded bank receipt slip and/or Transaction ID in classic order screen hook.
     *
     * @param WC_Order $order WooCommerce order object.
     */
    public function display_order_slip_in_admin($order)
    {
        if (!$order) {
            return;
        }

        if ($order->get_payment_method() !== 'oi_banglaqr') {
            return;
        }

        // Avoid duplicate rendering if already rendered in meta box
        if (!empty(self::$rendered_orders[$order->get_id()])) {
            return;
        }

        self::$rendered_orders[$order->get_id()] = true;
        $this->render_slip_card_markup($order);
    }

    /**
     * Output slip details markup.
     *
     * @param WC_Order $order WooCommerce Order object.
     */
    protected function render_slip_card_markup($order)
    {
        $receipt_id  = $order->get_meta('_oi_banglaqr_receipt_id');
        $trx_id      = $order->get_meta('_oi_banglaqr_transaction_id');
        $selected_qr = $order->get_meta('_oi_banglaqr_selected_qr');

        // Fallback to order transaction_id if set
        if (empty($trx_id) && $order->get_transaction_id()) {
            $trx_id = $order->get_transaction_id();
        }

        $image_url = '';
        if (!empty($receipt_id)) {
            if (is_numeric($receipt_id)) {
                $image_url = wp_get_attachment_url($receipt_id);
            } else {
                $image_url = esc_url($receipt_id);
            }
        }
        ?>
        <div class="clear"></div>
        <div class="banglaqr-admin-order-receipt-card" style="margin-top: 10px;">
            <div class="banglaqr-receipt-card-header">
                <span class="banglaqr-receipt-bank-name" style="display:inline-flex; align-items:center; gap:6px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2z"/><path d="M18 14h3v3h-3z"/><path d="M14 18h3v3h-3z"/></svg>
                    <span><?php echo esc_html(!empty($selected_qr) ? $selected_qr : __('Bangla QR Payment Details', 'banglaqr-payment-gateway-by-oi')); ?></span>
                </span>
                <?php if ($image_url): ?>
                    <a href="<?php echo esc_url($image_url); ?>" target="_blank" rel="noopener noreferrer" class="banglaqr-view-full-link">
                        <span><?php esc_html_e('View Full Image', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($trx_id)): ?>
                <div class="banglaqr-admin-trx-box">
                    <div>
                        <div class="banglaqr-admin-trx-label" style="display:flex; align-items:center; gap:4px;">
                            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
                            <span><?php esc_html_e('Transaction ID (TrxID)', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        </div>
                        <div class="banglaqr-admin-trx-val">
                            <?php echo esc_html($trx_id); ?>
                        </div>
                    </div>
                    <button type="button" class="button button-small banglaqr-copy-btn banglaqr-btn-with-icon"
                        onclick="(function(btn, txt){ if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(txt); } else { var t=document.createElement('textarea'); t.value=txt; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); } var s=btn.querySelector('span'); if(s){ var orig=s.innerText; s.innerText='<?php echo esc_js(__('Copied!', 'banglaqr-payment-gateway-by-oi')); ?>'; setTimeout(function(){s.innerText=orig;}, 2000); } })(this, '<?php echo esc_js($trx_id); ?>');">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span><?php esc_html_e('Copy', 'banglaqr-payment-gateway-by-oi'); ?></span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($image_url): ?>
                <div class="banglaqr-receipt-image-preview-container">
                    <img src="<?php echo esc_url($image_url); ?>"
                        alt="<?php esc_attr_e('Payment Receipt', 'banglaqr-payment-gateway-by-oi'); ?>"
                        class="banglaqr-receipt-preview-img" />
                </div>
            <?php elseif (!empty($receipt_id)): ?>
                <div class="banglaqr-receipt-missing-box">
                    <p class="banglaqr-receipt-missing-notice" style="display:flex; align-items:center; gap:6px;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?php esc_html_e('The uploaded receipt screenshot could not be loaded or was removed from the media library.', 'banglaqr-payment-gateway-by-oi'); ?></span>
                    </p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
