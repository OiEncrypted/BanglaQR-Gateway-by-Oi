<?php
/**
 * Gateway class for Oi_BanglaQR
 */

defined('ABSPATH') || exit;

#[\AllowDynamicProperties]
class Oi_BanglaQR_Gateway extends WC_Payment_Gateway
{
    public $theme_color;
    public $order_status;
    public $receipt_rule;
    public $trxid_rule;
    public $enable_manual_payment;
    public $manual_bkash;
    public $manual_nagad;
    public $manual_rocket;
    public $manual_upay;
    public $manual_cellfin;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'oi_banglaqr';
        $this->has_fields = true;
        $this->method_title = __('Bangla QR Payment', 'banglaqr-payment-gateway-by-oi');
        $this->method_description = __('Receive payments through Bangladeshi Bank/Mobile QR codes (Bkash, Nagad, Rocket, Bank QR, etc.).', 'banglaqr-payment-gateway-by-oi');

        // Load settings form fields and settings
        $this->init_form_fields();
        $this->init_settings();

        // Get option values
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        // Set the gateway icon URL for WooCommerce admin payments list
        $logo_url = $this->get_option('gateway_logo', '');
        $this->icon = $logo_url ? $logo_url : OI_BANGLAQR_URL . 'assets/logo.jpg';

        // Action hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_assets'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_fields'));

        // Output modal in footer
        add_action('wp_footer', array($this, 'render_modal_html'));

        // Thank You page, Customer My Account, and Order Emails
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page_content'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'view_order_receipt_content'), 10, 1);
        add_action('woocommerce_email_after_order_table', array($this, 'email_instructions'), 10, 4);
    }

    /**
     * Get gateway ID.
     *
     * @return string
     */
    public function get_id()
    {
        return $this->id;
    }

    /**
     * Get the currently active QR account from settings.
     *
     * @param array|null $settings Optional settings array.
     * @return array
     */
    public static function get_active_qr($settings = null)
    {
        if ($settings === null) {
            $settings = get_option('woocommerce_oi_banglaqr_settings', array());
        }

        $qrs_table = isset($settings['qrs_table']) ? $settings['qrs_table'] : array();
        if (!is_array($qrs_table) || empty($qrs_table)) {
            return array(
                'qr_name'        => 'BanglaQR (Primary)',
                'qr_code_url'    => OI_BANGLAQR_URL . 'includes/img/testqr.png',
                'payment_charge' => '1',
                'is_active'      => 'yes',
            );
        }

        foreach ($qrs_table as $qr) {
            if (isset($qr['is_active']) && $qr['is_active'] === 'yes') {
                return $qr;
            }
        }

        return $qrs_table[0];
    }

    /**
     * Define the gateway settings fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'checkbox',
                'label' => __('Enable BanglaQR Gateway by Oi', 'banglaqr-payment-gateway-by-oi'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Payment Title', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('The payment method title shown to your customers at checkout.', 'banglaqr-payment-gateway-by-oi'),
                'default' => __('Bangla QR Payment', 'banglaqr-payment-gateway-by-oi'),
                'desc_tip' => true,
            ),
            'gateway_logo' => array(
                'title' => __('Gateway Logo', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('A brand icon or logo displayed beside the payment title on checkout.', 'banglaqr-payment-gateway-by-oi'),
                'default' => OI_BANGLAQR_URL . 'assets/logo.jpg',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Payment Description', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'textarea',
                'description' => __('Helpful payment instructions displayed when customers select this gateway.', 'banglaqr-payment-gateway-by-oi'),
                'default' => __('Scan the QR code with any banking or MFS app (bKash, Nagad, Rocket, CellFin, etc.) to complete your payment.', 'banglaqr-payment-gateway-by-oi'),
                'desc_tip' => true,
            ),
            'qrs_table' => array(
                'title' => __('QR Accounts', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'qrs_table',
                'description' => __('Add your payment QR codes and choose which account is active on checkout.', 'banglaqr-payment-gateway-by-oi'),
                'default' => array(
                    array(
                        'qr_name' => 'BanglaQR (Primary)',
                        'qr_code_url' => OI_BANGLAQR_URL . 'includes/img/testqr.png',
                        'payment_charge' => '1',
                        'is_active' => 'yes',
                    )
                ),
            ),
            'theme_color' => array(
                'title' => __('Theme Accent Color', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'color',
                'description' => __('Choose the accent color for popup buttons and highlights to match your store theme.', 'banglaqr-payment-gateway-by-oi'),
                'default' => '#0A694B',
                'desc_tip' => true,
            ),
            'order_status' => array(
                'title' => __('Default Order Status', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'select',
                'description' => __('The status assigned to new orders once a customer submits their payment confirmation.', 'banglaqr-payment-gateway-by-oi'),
                'default' => 'on-hold',
                'options' => array(
                    'on-hold'    => __('On-Hold (Recommended)', 'banglaqr-payment-gateway-by-oi'),
                    'processing' => __('Processing', 'banglaqr-payment-gateway-by-oi'),
                    'completed'  => __('Completed', 'banglaqr-payment-gateway-by-oi'),
                    'pending'    => __('Pending Payment', 'banglaqr-payment-gateway-by-oi'),
                ),
            ),
            'receipt_rule' => array(
                'title' => __('Receipt Screenshot Rule', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'select',
                'description' => __('Choose whether customers must attach a payment screenshot before placing their order.', 'banglaqr-payment-gateway-by-oi'),
                'default' => 'optional',
                'options' => array(
                    'mandatory' => __('Mandatory (Must upload)', 'banglaqr-payment-gateway-by-oi'),
                    'optional'  => __('Optional (Recommended)', 'banglaqr-payment-gateway-by-oi'),
                    'hidden'    => __('Hidden (Do not show)', 'banglaqr-payment-gateway-by-oi'),
                ),
            ),
            'trxid_rule' => array(
                'title' => __('Transaction ID Rule', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'select',
                'description' => __('Choose whether customers must enter their payment Transaction ID before placing their order.', 'banglaqr-payment-gateway-by-oi'),
                'default' => 'optional',
                'options' => array(
                    'mandatory' => __('Mandatory (Must enter)', 'banglaqr-payment-gateway-by-oi'),
                    'optional'  => __('Optional (Recommended)', 'banglaqr-payment-gateway-by-oi'),
                    'hidden'    => __('Hidden (Do not show)', 'banglaqr-payment-gateway-by-oi'),
                ),
            ),
            'enable_manual_payment' => array(
                'title' => __('Manual Mobile Banking', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'checkbox',
                'label' => __('Show direct mobile banking numbers for customers who prefer sending money manually', 'banglaqr-payment-gateway-by-oi'),
                'default' => 'no',
            ),
            'manual_bkash' => array(
                'title' => __('bKash Number', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('Your bKash personal or merchant account number.', 'banglaqr-payment-gateway-by-oi'),
                'default' => '',
                'desc_tip' => true,
            ),
            'manual_nagad' => array(
                'title' => __('Nagad Number', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('Your Nagad personal or merchant account number.', 'banglaqr-payment-gateway-by-oi'),
                'default' => '',
                'desc_tip' => true,
            ),
            'manual_rocket' => array(
                'title' => __('Rocket Number', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('Your Rocket personal or merchant account number.', 'banglaqr-payment-gateway-by-oi'),
                'default' => '',
                'desc_tip' => true,
            ),
            'manual_upay' => array(
                'title' => __('Upay Number', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('Your Upay personal or merchant account number.', 'banglaqr-payment-gateway-by-oi'),
                'default' => '',
                'desc_tip' => true,
            ),
            'manual_cellfin' => array(
                'title' => __('CellFin Number', 'banglaqr-payment-gateway-by-oi'),
                'type' => 'text',
                'description' => __('Your CellFin account or card number.', 'banglaqr-payment-gateway-by-oi'),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Render the settings page with a custom tabbed dashboard.
     */
    public function admin_options()
    {
        wp_enqueue_media();

        $enabled = $this->get_option('enabled', 'no');
        $title = $this->get_option('title', 'Bangla QR Payment');
        $gateway_logo = $this->get_option('gateway_logo');
        if (!$gateway_logo) {
            $gateway_logo = OI_BANGLAQR_URL . 'assets/logo.jpg';
        }
        $description = $this->get_option('description', '');
        $theme_color = $this->get_option('theme_color', '#0A694B');
        
        $order_status = $this->get_option('order_status', 'on-hold');
        $receipt_rule = $this->get_option('receipt_rule', 'optional');
        $trxid_rule = $this->get_option('trxid_rule', 'optional');
        
        $enable_manual_payment = $this->get_option('enable_manual_payment', 'no');
        $manual_bkash = $this->get_option('manual_bkash', '');
        $manual_nagad = $this->get_option('manual_nagad', '');
        $manual_rocket = $this->get_option('manual_rocket', '');
        $manual_upay = $this->get_option('manual_upay', '');
        $manual_cellfin = $this->get_option('manual_cellfin', '');
        
        $qrs = $this->get_option('qrs_table', array());
        $active_qr = self::get_active_qr($this->settings);
        $active_qr_name = !empty($active_qr['qr_name']) ? $active_qr['qr_name'] : __('None Selected', 'banglaqr-payment-gateway-by-oi');
        $active_qr_fee = isset($active_qr['payment_charge']) ? $active_qr['payment_charge'] : '0';
        if (!is_array($qrs) || empty($qrs)) {
            $qrs = array($active_qr);
        }

        $configured_manual_count = 0;
        if (!empty($manual_bkash)) $configured_manual_count++;
        if (!empty($manual_nagad)) $configured_manual_count++;
        if (!empty($manual_rocket)) $configured_manual_count++;
        if (!empty($manual_upay)) $configured_manual_count++;
        if (!empty($manual_cellfin)) $configured_manual_count++;
        ?>
        <div class="banglaqr-admin-dashboard-wrap">
            <!-- Header Section with Live Status and Quick Metrics -->
            <div class="banglaqr-dashboard-header">
                <div class="banglaqr-header-top">
                    <div class="banglaqr-header-info">
                        <div class="banglaqr-logo-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 15h.008v.008H15V15zm0 2.25h.008v.008H15v-.008zM17.25 15h.008v.008H17.25V15zm0 2.25h.008v.008H17.25v-.008zm-2.25 2.25h.008v.008H15v-.008zm2.25 0h.008v.008H17.25v-.008zM19.5 15h.008v.008H19.5V15zm0 2.25h.008v.008H19.5v-.008zm-2.25-4.5h.008v.008H17.25v-.008zm2.25 0h.008v.008H19.5v-.008z" />
                            </svg>
                        </div>
                        <div class="banglaqr-header-title-box">
                            <h1 class="banglaqr-header-title"><?php esc_html_e('BanglaQR Settings', 'banglaqr-payment-gateway-by-oi'); ?></h1>
                            <p class="banglaqr-header-subtitle"><?php esc_html_e('Configure your QR accounts, transaction processing fees, and checkout payment rules.', 'banglaqr-payment-gateway-by-oi'); ?></p>
                        </div>
                    </div>
                    <div class="banglaqr-header-actions">
                        <span class="banglaqr-status-badge <?php echo $enabled === 'yes' ? 'status-active' : 'status-inactive'; ?>">
                            <span class="status-dot"></span>
                            <?php echo $enabled === 'yes' ? esc_html__('Gateway Active', 'banglaqr-payment-gateway-by-oi') : esc_html__('Gateway Inactive', 'banglaqr-payment-gateway-by-oi'); ?>
                        </span>
                    </div>
                </div>

                <!-- Quick Configuration Metrics Bar -->
                <div class="banglaqr-quick-metrics-row">
                    <div class="banglaqr-metric-pill">
                        <span class="banglaqr-metric-label"><?php esc_html_e('Active QR', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="banglaqr-metric-value"><?php echo esc_html($active_qr_name); ?></span>
                        <span class="banglaqr-metric-badge"><?php echo esc_html($active_qr_fee); ?>% <?php esc_html_e('Fee', 'banglaqr-payment-gateway-by-oi'); ?></span>
                    </div>
                    <div class="banglaqr-metric-pill">
                        <span class="banglaqr-metric-label"><?php esc_html_e('Receipt Upload', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="banglaqr-metric-value"><?php echo esc_html(ucfirst($receipt_rule)); ?></span>
                    </div>
                    <div class="banglaqr-metric-pill">
                        <span class="banglaqr-metric-label"><?php esc_html_e('Transaction ID', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="banglaqr-metric-value"><?php echo esc_html(ucfirst($trxid_rule)); ?></span>
                    </div>
                    <div class="banglaqr-metric-pill">
                        <span class="banglaqr-metric-label"><?php esc_html_e('Manual Accounts', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="banglaqr-metric-value">
                            <?php echo $enable_manual_payment === 'yes' ? esc_html(sprintf(__('%d Active', 'banglaqr-payment-gateway-by-oi'), $configured_manual_count)) : esc_html__('Disabled', 'banglaqr-payment-gateway-by-oi'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php
            $is_block_checkout = false;
            if (function_exists('has_block') && function_exists('wc_get_page_id')) {
                $checkout_page_id = wc_get_page_id('checkout');
                if ($checkout_page_id > 0 && has_block('woocommerce/checkout', $checkout_page_id)) {
                    $is_block_checkout = true;
                }
            }
            if ($is_block_checkout):
            ?>
            <div class="banglaqr-blocks-notice" style="margin: 18px 0; padding: 14px 18px; background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb; border-radius: 8px; display: flex; align-items: flex-start; gap: 12px;">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div style="font-size: 13px; color: #1e3a8a; line-height: 1.55;">
                    <strong style="display:block; margin-bottom: 2px;"><?php esc_html_e('WooCommerce Checkout Blocks Detected', 'banglaqr-payment-gateway-by-oi'); ?></strong>
                    <?php esc_html_e('Your store is currently using the Gutenberg Checkout Block. BanglaQR Gateway provides an interactive scan-to-pay modal and verified receipt uploader which operates with the WooCommerce Classic Checkout shortcode [woocommerce_checkout]. If BanglaQR does not display on your checkout page, please edit your Checkout page and replace the block with the Classic Checkout shortcode.', 'banglaqr-payment-gateway-by-oi'); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Unified Settings Sections -->
            <div class="banglaqr-settings-panel">
                
                <!-- Section 1: General Gateway Settings -->
                <div class="banglaqr-settings-section">
                    <div class="banglaqr-section-header">
                        <div class="banglaqr-section-icon-badge badge-green">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        </div>
                        <div class="banglaqr-section-header-text">
                            <h2><?php esc_html_e('General Gateway Settings', 'banglaqr-payment-gateway-by-oi'); ?></h2>
                            <p><?php esc_html_e('Branding, accent theme color, and customer-facing checkout presentation.', 'banglaqr-payment-gateway-by-oi'); ?></p>
                        </div>
                    </div>
                    <div class="banglaqr-section-body">
                        <div class="banglaqr-grid-2col">
                            
                            <!-- Left Column: Branding & Controls -->
                            <div class="banglaqr-card-subpanel">
                                <h3 class="banglaqr-subpanel-title">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/></svg>
                                    <span><?php esc_html_e('Branding & Availability', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </h3>

                                <!-- Enable/Disable Gateway Toggle Card -->
                                <div class="banglaqr-toggle-card">
                                    <div class="banglaqr-toggle-wrapper">
                                        <label class="banglaqr-switch">
                                            <input type="checkbox" name="woocommerce_oi_banglaqr_enabled" id="woocommerce_oi_banglaqr_enabled" value="yes" <?php checked($enabled, 'yes'); ?> />
                                            <span class="banglaqr-slider"></span>
                                        </label>
                                    </div>
                                    <div class="banglaqr-toggle-card-text">
                                        <label for="woocommerce_oi_banglaqr_enabled" class="banglaqr-toggle-card-label">
                                            <?php esc_html_e('Enable Gateway on Checkout', 'banglaqr-payment-gateway-by-oi'); ?>
                                        </label>
                                        <p class="banglaqr-field-tip">
                                            <?php esc_html_e('Make this payment method active and visible to customers.', 'banglaqr-payment-gateway-by-oi'); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Gateway Logo -->
                                <div class="banglaqr-grid-field">
                                    <label for="woocommerce_oi_banglaqr_gateway_logo" class="banglaqr-field-label-with-icon">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        <span><?php esc_html_e('Gateway Logo', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                    </label>
                                    <div class="banglaqr-uploader-inline">
                                        <div class="banglaqr-logo-preview-box" id="banglaqr-gateway-logo-preview">
                                            <?php if ($gateway_logo): ?>
                                                <img src="<?php echo esc_url($gateway_logo); ?>" alt="Gateway Logo" />
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#94a3b8" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <input type="text" name="woocommerce_oi_banglaqr_gateway_logo" id="woocommerce_oi_banglaqr_gateway_logo" value="<?php echo esc_attr($gateway_logo); ?>" placeholder="<?php esc_attr_e('Paste logo image URL or upload', 'banglaqr-payment-gateway-by-oi'); ?>" class="banglaqr-general-input" />
                                        <button type="button" class="button button-secondary banglaqr-btn-with-icon" id="banglaqr-upload-gateway-logo-btn">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span><?php esc_html_e('Upload', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                        </button>
                                    </div>
                                    <p class="banglaqr-field-tip">
                                        <?php esc_html_e('Displayed beside the gateway title on the checkout page for trust recognition.', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </p>
                                </div>

                                <!-- Theme Accent Color -->
                                <div class="banglaqr-grid-field">
                                    <label for="woocommerce_oi_banglaqr_theme_color" class="banglaqr-field-label-with-icon">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a7 7 0 0 0 0 14 3 3 0 0 0 3-3c0-1.5-1-2-1-3.5a1.5 1.5 0 0 1 3 0A7 7 0 0 0 12 2z"/></svg>
                                        <span><?php esc_html_e('Theme Accent Color', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                    </label>
                                    <div class="banglaqr-color-control-wrapper">
                                        <div class="banglaqr-color-swatch-indicator" id="banglaqr-theme-color-swatch" style="background-color: <?php echo esc_attr($theme_color); ?>;"></div>
                                        <input type="text" id="banglaqr-theme-color-hex" value="<?php echo esc_attr($theme_color); ?>" class="banglaqr-color-hex-badge" readonly />
                                        <input type="color" name="woocommerce_oi_banglaqr_theme_color" id="woocommerce_oi_banglaqr_theme_color" value="<?php echo esc_attr($theme_color); ?>" class="banglaqr-color-native-input" />
                                        <label for="woocommerce_oi_banglaqr_theme_color" class="button button-secondary banglaqr-btn-with-icon banglaqr-color-trigger-btn">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 12 4-4a2 2 0 1 0-3-3l-4 4"/><path d="m12 15-7 7H2v-3l7-7"/></svg>
                                            <span><?php esc_html_e('Change Color', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                        </label>
                                    </div>
                                    <p class="banglaqr-field-tip">
                                        <?php esc_html_e('Accent color used for checkout modal banners, buttons, and status indicators.', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Right Column: Checkout Presentation -->
                            <div class="banglaqr-card-subpanel">
                                <h3 class="banglaqr-subpanel-title">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h10"/><path d="M7 12h10"/><path d="M7 17h6"/></svg>
                                    <span><?php esc_html_e('Checkout Presentation', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </h3>

                                <!-- Gateway Title -->
                                <div class="banglaqr-grid-field">
                                    <label for="woocommerce_oi_banglaqr_title" class="banglaqr-field-label-with-icon">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
                                        <span><?php esc_html_e('Checkout Title', 'banglaqr-payment-gateway-by-oi'); ?> <span class="req">*</span></span>
                                    </label>
                                    <input type="text" name="woocommerce_oi_banglaqr_title" id="woocommerce_oi_banglaqr_title" value="<?php echo esc_attr($title); ?>" placeholder="e.g. Bangla QR Payment" required class="banglaqr-general-input" />
                                    <p class="banglaqr-field-tip">
                                        <?php esc_html_e('The payment gateway name shown in the WooCommerce checkout payment list.', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </p>
                                </div>

                                <!-- Gateway Description -->
                                <div class="banglaqr-grid-field">
                                    <label for="woocommerce_oi_banglaqr_description" class="banglaqr-field-label-with-icon">
                                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        <span><?php esc_html_e('Checkout Description', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                    </label>
                                    <textarea name="woocommerce_oi_banglaqr_description" id="woocommerce_oi_banglaqr_description" rows="4" placeholder="<?php esc_attr_e('e.g. Scan the QR code using your bKash, Nagad, Rocket, CellFin, or mobile banking app to pay securely.', 'banglaqr-payment-gateway-by-oi'); ?>" class="banglaqr-general-textarea"><?php echo esc_textarea($description); ?></textarea>
                                    <p class="banglaqr-field-tip">
                                        <?php esc_html_e('Informative guidance displayed below the title when shoppers select this payment method.', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- End General Settings Section -->

                <!-- Section 2: Checkout Rules & Status (3-Card Matrix) -->
                <div class="banglaqr-settings-section">
                    <div class="banglaqr-section-header">
                        <div class="banglaqr-section-icon-badge badge-blue">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                        <div class="banglaqr-section-header-text">
                            <h2><?php esc_html_e('Checkout Rules & Order Status', 'banglaqr-payment-gateway-by-oi'); ?></h2>
                            <p><?php esc_html_e('Define order status and payment verification requirements for shoppers.', 'banglaqr-payment-gateway-by-oi'); ?></p>
                        </div>
                    </div>
                    <div class="banglaqr-section-body">
                        <div class="banglaqr-rules-grid">
                            
                            <!-- Rule Card 1: Default Order Status -->
                            <div class="banglaqr-rule-card">
                                <div class="banglaqr-rule-card-header">
                                    <div class="banglaqr-rule-icon">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <label for="woocommerce_oi_banglaqr_order_status" class="banglaqr-rule-title">
                                        <?php esc_html_e('Default Order Status', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </label>
                                </div>
                                <select name="woocommerce_oi_banglaqr_order_status" id="woocommerce_oi_banglaqr_order_status" class="banglaqr-general-input banglaqr-rule-select">
                                    <option value="on-hold" <?php selected($order_status, 'on-hold'); ?>><?php esc_html_e('On-Hold (Recommended)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="processing" <?php selected($order_status, 'processing'); ?>><?php esc_html_e('Processing', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="completed" <?php selected($order_status, 'completed'); ?>><?php esc_html_e('Completed', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="pending" <?php selected($order_status, 'pending'); ?>><?php esc_html_e('Pending Payment', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                </select>
                                <p class="banglaqr-field-tip">
                                    <?php esc_html_e('Status assigned immediately after customer confirms payment in the checkout popup.', 'banglaqr-payment-gateway-by-oi'); ?>
                                </p>
                            </div>

                            <!-- Rule Card 2: Receipt Screenshot Rule -->
                            <div class="banglaqr-rule-card">
                                <div class="banglaqr-rule-card-header">
                                    <div class="banglaqr-rule-icon">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                    </div>
                                    <label for="woocommerce_oi_banglaqr_receipt_rule" class="banglaqr-rule-title">
                                        <?php esc_html_e('Receipt Screenshot Rule', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </label>
                                </div>
                                <select name="woocommerce_oi_banglaqr_receipt_rule" id="woocommerce_oi_banglaqr_receipt_rule" class="banglaqr-general-input banglaqr-rule-select">
                                    <option value="optional" <?php selected($receipt_rule, 'optional'); ?>><?php esc_html_e('Optional (Recommended)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="mandatory" <?php selected($receipt_rule, 'mandatory'); ?>><?php esc_html_e('Mandatory (Must upload)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="hidden" <?php selected($receipt_rule, 'hidden'); ?>><?php esc_html_e('Hidden (Do not show)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                </select>
                                <p class="banglaqr-field-tip">
                                    <?php esc_html_e('Allows customers to upload their payment screenshot or bank receipt as proof.', 'banglaqr-payment-gateway-by-oi'); ?>
                                </p>
                            </div>

                            <!-- Rule Card 3: Transaction ID Rule -->
                            <div class="banglaqr-rule-card">
                                <div class="banglaqr-rule-card-header">
                                    <div class="banglaqr-rule-icon">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
                                    </div>
                                    <label for="woocommerce_oi_banglaqr_trxid_rule" class="banglaqr-rule-title">
                                        <?php esc_html_e('Transaction ID Rule', 'banglaqr-payment-gateway-by-oi'); ?>
                                    </label>
                                </div>
                                <select name="woocommerce_oi_banglaqr_trxid_rule" id="woocommerce_oi_banglaqr_trxid_rule" class="banglaqr-general-input banglaqr-rule-select">
                                    <option value="optional" <?php selected($trxid_rule, 'optional'); ?>><?php esc_html_e('Optional (Recommended)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="mandatory" <?php selected($trxid_rule, 'mandatory'); ?>><?php esc_html_e('Mandatory (Must enter)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                    <option value="hidden" <?php selected($trxid_rule, 'hidden'); ?>><?php esc_html_e('Hidden (Do not show)', 'banglaqr-payment-gateway-by-oi'); ?></option>
                                </select>
                                <p class="banglaqr-field-tip">
                                    <?php esc_html_e('Allows shoppers to submit their 8-10 digit bank/MFS transaction identifier.', 'banglaqr-payment-gateway-by-oi'); ?>
                                </p>
                            </div>

                        </div>
                    </div>
                </div> <!-- End Checkout Rules Section -->

                <!-- Section 3: Manual Payment Accounts (Branded Cards) -->
                <div class="banglaqr-settings-section">
                    <div class="banglaqr-section-header">
                        <div class="banglaqr-section-icon-badge badge-amber">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        </div>
                        <div class="banglaqr-section-header-text">
                            <h2><?php esc_html_e('Manual Payment Accounts', 'banglaqr-payment-gateway-by-oi'); ?></h2>
                            <p><?php esc_html_e('Provide direct mobile wallet numbers for customers who prefer sending money manually.', 'banglaqr-payment-gateway-by-oi'); ?></p>
                        </div>
                    </div>
                    <div class="banglaqr-section-body">
                        
                        <!-- Master Enable Switch Card -->
                        <div class="banglaqr-toggle-card banglaqr-mfs-master-toggle">
                            <div class="banglaqr-toggle-wrapper">
                                <label class="banglaqr-switch">
                                    <input type="checkbox" name="woocommerce_oi_banglaqr_enable_manual_payment" id="woocommerce_oi_banglaqr_enable_manual_payment" value="yes" <?php checked($enable_manual_payment, 'yes'); ?> />
                                    <span class="banglaqr-slider"></span>
                                </label>
                            </div>
                            <div class="banglaqr-toggle-card-text">
                                <label for="woocommerce_oi_banglaqr_enable_manual_payment" class="banglaqr-toggle-card-label">
                                    <?php esc_html_e('Enable Manual Payment Accounts', 'banglaqr-payment-gateway-by-oi'); ?>
                                </label>
                                <p class="banglaqr-field-tip">
                                    <?php esc_html_e('Shows a "Prefer manual payment?" dropdown in the checkout modal with copyable numbers.', 'banglaqr-payment-gateway-by-oi'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- 5 Branded Provider Cards Grid -->
                        <div class="banglaqr-mfs-grid">
                            
                            <!-- bKash Card -->
                            <div class="banglaqr-mfs-card mfs-card-bkash">
                                <div class="banglaqr-mfs-card-header">
                                    <div class="banglaqr-mfs-badge">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>bKash</span>
                                    </div>
                                    <span class="banglaqr-mfs-type-tag"><?php esc_html_e('Merchant / Personal', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </div>
                                <div class="banglaqr-mfs-input-box">
                                    <input type="text" name="woocommerce_oi_banglaqr_manual_bkash" id="woocommerce_oi_banglaqr_manual_bkash" value="<?php echo esc_attr($manual_bkash); ?>" placeholder="017xxxxxxxx" class="banglaqr-general-input banglaqr-mfs-input" maxlength="11" />
                                </div>
                            </div>

                            <!-- Nagad Card -->
                            <div class="banglaqr-mfs-card mfs-card-nagad">
                                <div class="banglaqr-mfs-card-header">
                                    <div class="banglaqr-mfs-badge">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Nagad</span>
                                    </div>
                                    <span class="banglaqr-mfs-type-tag"><?php esc_html_e('Merchant / Personal', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </div>
                                <div class="banglaqr-mfs-input-box">
                                    <input type="text" name="woocommerce_oi_banglaqr_manual_nagad" id="woocommerce_oi_banglaqr_manual_nagad" value="<?php echo esc_attr($manual_nagad); ?>" placeholder="018xxxxxxxx" class="banglaqr-general-input banglaqr-mfs-input" maxlength="11" />
                                </div>
                            </div>

                            <!-- Rocket Card -->
                            <div class="banglaqr-mfs-card mfs-card-rocket">
                                <div class="banglaqr-mfs-card-header">
                                    <div class="banglaqr-mfs-badge">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Rocket</span>
                                    </div>
                                    <span class="banglaqr-mfs-type-tag"><?php esc_html_e('12-digit Account', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </div>
                                <div class="banglaqr-mfs-input-box">
                                    <input type="text" name="woocommerce_oi_banglaqr_manual_rocket" id="woocommerce_oi_banglaqr_manual_rocket" value="<?php echo esc_attr($manual_rocket); ?>" placeholder="019xxxxxxxx" class="banglaqr-general-input banglaqr-mfs-input" maxlength="12" />
                                </div>
                            </div>

                            <!-- Upay Card -->
                            <div class="banglaqr-mfs-card mfs-card-upay">
                                <div class="banglaqr-mfs-card-header">
                                    <div class="banglaqr-mfs-badge">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                        <span>Upay</span>
                                    </div>
                                    <span class="banglaqr-mfs-type-tag"><?php esc_html_e('Merchant / Personal', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </div>
                                <div class="banglaqr-mfs-input-box">
                                    <input type="text" name="woocommerce_oi_banglaqr_manual_upay" id="woocommerce_oi_banglaqr_manual_upay" value="<?php echo esc_attr($manual_upay); ?>" placeholder="016xxxxxxxx" class="banglaqr-general-input banglaqr-mfs-input" maxlength="11" />
                                </div>
                            </div>

                            <!-- CellFin Card -->
                            <div class="banglaqr-mfs-card mfs-card-cellfin">
                                <div class="banglaqr-mfs-card-header">
                                    <div class="banglaqr-mfs-badge">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                        <span>CellFin</span>
                                    </div>
                                    <span class="banglaqr-mfs-type-tag"><?php esc_html_e('Account / Card / Phone', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </div>
                                <div class="banglaqr-mfs-input-box">
                                    <input type="text" name="woocommerce_oi_banglaqr_manual_cellfin" id="woocommerce_oi_banglaqr_manual_cellfin" value="<?php echo esc_attr($manual_cellfin); ?>" placeholder="CellFin number or card" class="banglaqr-general-input banglaqr-mfs-input" />
                                </div>
                            </div>

                        </div>
                    </div>
                </div> <!-- End Manual Payment Accounts Section -->

                <!-- Section 4: Manage QR Accounts -->
                <div class="banglaqr-settings-section">
                    <div class="banglaqr-section-header">
                        <div class="banglaqr-section-icon-badge badge-purple">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h2v2h-2z"/><path d="M18 14h3v3h-3z"/><path d="M14 18h3v3h-3z"/><path d="M20 20h1v1h-1z"/></svg>
                        </div>
                        <div class="banglaqr-section-header-text">
                            <h2><?php esc_html_e('Manage QR Accounts', 'banglaqr-payment-gateway-by-oi'); ?></h2>
                            <p><?php esc_html_e('Add, reorder, and activate QR accounts. The active account will be displayed in the checkout popup.', 'banglaqr-payment-gateway-by-oi'); ?></p>
                        </div>
                    </div>
                    <div class="banglaqr-section-body">
                        <!-- Accordion Container -->
                        <div id="banglaqr-qr-accordion-wrapper" class="banglaqr-qr-accordion-wrapper">
                            <!-- Populated dynamically by admin.js -->
                        </div>
                        
                        <!-- Hidden JSON field for QRs -->
                        <input type="hidden" name="woocommerce_oi_banglaqr_qrs_table" id="woocommerce_oi_banglaqr_qrs_table" value="<?php echo esc_attr(wp_json_encode($qrs)); ?>" />
                    </div>
                </div> <!-- End QR Accounts Section -->

            </div>
        </div>
        <?php
    }

    /**
     * Validate and sanitize the QR table field.
     *
     * @param string $key Settings key.
     * @param string $value Field value.
     * @return array Sanitized array of QR codes.
     */
    public function validate_qrs_table_field($key, $value)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_value = isset($_POST[$this->get_field_key($key)]) ? wp_unslash($_POST[$this->get_field_key($key)]) : '';
        $decoded = json_decode($raw_value, true);
        if (!is_array($decoded)) {
            $decoded = json_decode(html_entity_decode($raw_value), true);
        }

        $sanitized_qrs = array();
        $has_active = false;
        if (is_array($decoded)) {
            foreach ($decoded as $qr) {
                $qr_name = sanitize_text_field(isset($qr['qr_name']) ? $qr['qr_name'] : '');
                $qr_code_url = esc_url_raw(isset($qr['qr_code_url']) ? $qr['qr_code_url'] : '');

                // Skip saving completely empty entries
                if (empty($qr_name) && empty($qr_code_url)) {
                    continue;
                }

                $charge = isset($qr['payment_charge']) ? floatval($qr['payment_charge']) : 0;
                $charge = max(0, min(100, $charge));

                $is_active = (isset($qr['is_active']) && $qr['is_active'] === 'yes' && !$has_active) ? 'yes' : 'no';
                if ($is_active === 'yes') {
                    $has_active = true;
                }

                $sanitized_qrs[] = array(
                    'qr_name' => $qr_name,
                    'qr_code_url' => $qr_code_url,
                    'payment_charge' => (string)$charge,
                    'is_active' => $is_active,
                );
            }
        }

        // If none is active but accounts exist, make the first one active
        if (!$has_active && !empty($sanitized_qrs)) {
            $sanitized_qrs[0]['is_active'] = 'yes';
        }

        return $sanitized_qrs;
    }

    /**
     * Enqueue CSS and JS assets on the checkout page.
     */
    public function enqueue_checkout_assets()
    {
        $is_thankyou   = function_exists('is_order_received_page') && is_order_received_page();
        $is_view_order = function_exists('is_view_order_page') && is_view_order_page();
        $is_pay_page   = function_exists('is_checkout_pay_page') && is_checkout_pay_page();
        $is_checkout   = function_exists('is_checkout') && is_checkout();

        if (!$is_checkout && !$is_thankyou && !$is_view_order) {
            return;
        }

        $handle = 'banglaqr-frontend';

        $css_ver = file_exists(OI_BANGLAQR_PATH . 'includes/css/banglaqr-frontend.css') ? filemtime(OI_BANGLAQR_PATH . 'includes/css/banglaqr-frontend.css') : OI_BANGLAQR_VERSION;
        $js_ver  = file_exists(OI_BANGLAQR_PATH . 'includes/js/banglaqr-frontend.js') ? filemtime(OI_BANGLAQR_PATH . 'includes/js/banglaqr-frontend.js') : OI_BANGLAQR_VERSION;

        wp_enqueue_style($handle, OI_BANGLAQR_URL . 'includes/css/banglaqr-frontend.css', array(), $css_ver);

        // On thank you and view order pages, we only need the CSS for receipt styling
        if ($is_thankyou || $is_view_order) {
            return;
        }

        if (!$this->is_available()) {
            return;
        }

        wp_enqueue_script($handle, OI_BANGLAQR_URL . 'includes/js/banglaqr-frontend.js', array('jquery'), $js_ver, true);

        // Find active QR code from settings
        $settings = $this->settings;
        $active_qr = self::get_active_qr($settings);
        $charge_percent = ($active_qr && isset($active_qr['payment_charge'])) ? floatval($active_qr['payment_charge']) : 0;

        // Formatted total respecting store decimal settings and order-pay context
        $total_amount = 0;
        if ($is_pay_page) {
            global $wp;
            $pay_order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
            if ($pay_order_id > 0) {
                $pay_order = wc_get_order($pay_order_id);
                if ($pay_order) {
                    $total_amount = $pay_order->get_total();
                }
            }
        } elseif (function_exists('WC') && WC()->cart) {
            $total_amount = WC()->cart->total;
        }

        $formatted_total = html_entity_decode(wp_strip_all_tags(wc_price($total_amount)));
        $raw_numeric_total = number_format(floatval($total_amount), function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2, '.', '');

        // Dynamically set max file size based on server limit and our 5MB default
        $max_upload_size = wp_max_upload_size();
        $allowed_max_size = min(5 * 1024 * 1024, $max_upload_size);
        $allowed_max_size_mb = max(1, round($allowed_max_size / (1024 * 1024)));
        
        // Localize configuration data
        wp_localize_script($handle, 'oi_banglaqr_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'upload_nonce' => wp_create_nonce('oi_banglaqr_upload_slip_action'),
            'active_qr' => $active_qr,
            'gateway_id' => $this->id,
            'theme_color' => isset($settings['theme_color']) && !empty($settings['theme_color']) ? $settings['theme_color'] : '#0A694B',
            'max_file_size' => $allowed_max_size,
            'text_max_file_size' => $allowed_max_size_mb . 'MB',
            'paymentpage_img_url' => OI_BANGLAQR_URL . 'includes/img/banglaqr-paymentpage.png',
            'order_total' => $formatted_total,
            'raw_order_total' => $raw_numeric_total,
            'payment_charge' => $charge_percent,
            'error_no_file' => __('Please upload your payment screenshot or enter your Transaction ID to confirm your order.', 'banglaqr-payment-gateway-by-oi'),
            'error_invalid_file' => __('Please upload a valid image file (JPEG, PNG, or WebP).', 'banglaqr-payment-gateway-by-oi'),
            'error_file_too_large' => sprintf(__('This image is too large. Maximum allowed size is %s.', 'banglaqr-payment-gateway-by-oi'), $allowed_max_size_mb . 'MB'),
            'i18n_required_field' => __('%s is required.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_valid_email' => __('Please enter a valid email address for %s.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_terms' => __('Please check the box to agree to the website terms and conditions.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_payment_details_attached' => __('Payment Details Attached', 'banglaqr-payment-gateway-by-oi'),
            'i18n_edit' => __('Edit', 'banglaqr-payment-gateway-by-oi'),
            'i18n_account' => __('Account:', 'banglaqr-payment-gateway-by-oi'),
            'i18n_transaction_id' => __('Transaction ID:', 'banglaqr-payment-gateway-by-oi'),
            'i18n_receipt' => __('Receipt:', 'banglaqr-payment-gateway-by-oi'),
            'i18n_view_receipt' => __('View Receipt', 'banglaqr-payment-gateway-by-oi'),
            'i18n_payment_received' => __('Payment Details Received!', 'banglaqr-payment-gateway-by-oi'),
            'i18n_confirming_order' => __('Thank you! We are confirming your order now...', 'banglaqr-payment-gateway-by-oi'),
            'i18n_close_full_view' => __('Close Full View', 'banglaqr-payment-gateway-by-oi'),
            'i18n_payment_receipt' => __('Payment Receipt', 'banglaqr-payment-gateway-by-oi'),
            'i18n_session_expired' => __('Your payment session has expired.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_extend_time' => __('Extend Time (15 min)', 'banglaqr-payment-gateway-by-oi'),
            'i18n_receipt_required' => __('Please upload your payment receipt screenshot before completing your order.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_trx_required' => __('Please enter your payment Transaction ID before completing your order.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_submitting_receipt' => __('Submitting Receipt...', 'banglaqr-payment-gateway-by-oi'),
            'i18n_confirming_order_btn' => __('Confirming Order...', 'banglaqr-payment-gateway-by-oi'),
            'i18n_confirm_btn_default' => __('Confirm & Place Order', 'banglaqr-payment-gateway-by-oi'),
            'i18n_upload_network_error' => __('We could not upload your receipt due to a network connection issue. Please check your internet and try again.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_upload_failed' => __('We could not upload your receipt image. Please try again or use another format.', 'banglaqr-payment-gateway-by-oi'),
            'i18n_copy_amount' => __('Copy', 'banglaqr-payment-gateway-by-oi'),
            'i18n_amount_copied' => __('Copied', 'banglaqr-payment-gateway-by-oi'),
            'i18n_change_file' => __('Change', 'banglaqr-payment-gateway-by-oi'),
            'receipt_rule' => isset($settings['receipt_rule']) ? $settings['receipt_rule'] : 'optional',
            'trxid_rule' => isset($settings['trxid_rule']) ? $settings['trxid_rule'] : 'optional',
            'enable_manual_payment' => isset($settings['enable_manual_payment']) ? $settings['enable_manual_payment'] : 'no',
            'manual_bkash' => isset($settings['manual_bkash']) ? $settings['manual_bkash'] : '',
            'manual_nagad' => isset($settings['manual_nagad']) ? $settings['manual_nagad'] : '',
            'manual_rocket' => isset($settings['manual_rocket']) ? $settings['manual_rocket'] : '',
            'manual_upay' => isset($settings['manual_upay']) ? $settings['manual_upay'] : '',
            'manual_cellfin' => isset($settings['manual_cellfin']) ? $settings['manual_cellfin'] : '',
        ));
    }

    /**
     * Get gateway icon logo.
     * Show the uploaded logo directly after the title on the checkout page.
     *
     * @return string
     */
    public function get_icon()
    {
        $logo_url = $this->icon;
        $icon = '';
        if ($logo_url) {
            $icon = '<img class="banglaqr-checkout-gateway-logo" src="' . esc_url($logo_url) . '" alt="' . esc_attr($this->get_title()) . '" style="max-height: 30px; max-width: 100px; margin-left: 5px; vertical-align: middle; display: inline-block;" />';
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * Render the payment fields on Checkout.
     */
    public function payment_fields()
    {
        // Output description if set
        if ($this->description) {
            echo wp_kses_post(wpautop($this->description));
        }

        // Output hidden checkout inputs which will hold the slip metadata
        ?>
                <div id="banglaqr-checkout-fields-container">
                    <input type="hidden" name="oi_banglaqr_receipt_id" id="oi_banglaqr_receipt_id" value="" />
                    <input type="hidden" name="oi_banglaqr_receipt_token" id="oi_banglaqr_receipt_token" value="" />
                    <input type="hidden" name="oi_banglaqr_transaction_id" id="oi_banglaqr_transaction_id" value="" />
                    <input type="hidden" name="oi_banglaqr_selected_qr" id="oi_banglaqr_selected_qr" value="" />
                    <input type="hidden" name="oi_banglaqr_confirmed" id="oi_banglaqr_confirmed" value="0" />
                    <div id="banglaqr-selected-qr-preview" class="banglaqr-selected-qr-preview"></div>
                </div>
                <?php
    }

    /**
     * Server-side validation of checkout fields when order is submitted.
     */
    public function validate_checkout_fields()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (isset($_POST['payment_method']) && sanitize_text_field(wp_unslash($_POST['payment_method'])) === $this->id) {
            $receipt_rule = $this->get_option('receipt_rule', 'optional');
            $trxid_rule = $this->get_option('trxid_rule', 'optional');

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $has_receipt = !empty($_POST['oi_banglaqr_receipt_id']);
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $has_trx_id = !empty($_POST['oi_banglaqr_transaction_id']) && trim(sanitize_text_field(wp_unslash($_POST['oi_banglaqr_transaction_id']))) !== '';

            if ($receipt_rule === 'mandatory' && !$has_receipt) {
                wc_add_notice(__('Please upload your payment receipt screenshot to complete this order.', 'banglaqr-payment-gateway-by-oi'), 'error');
            }

            if ($trxid_rule === 'mandatory' && !$has_trx_id) {
                wc_add_notice(__('Please enter your payment Transaction ID (TrxID) to complete this order.', 'banglaqr-payment-gateway-by-oi'), 'error');
            }

            // Require at least one only if both receipt and TrxID are set to optional
            if ($receipt_rule === 'optional' && $trxid_rule === 'optional') {
                if (!$has_receipt && !$has_trx_id) {
                    wc_add_notice(__('Please provide either a payment receipt screenshot or your Transaction ID to complete this order.', 'banglaqr-payment-gateway-by-oi'), 'error');
                }
            }
        }
    }

    /**
     * WooCommerce core method to validate fields before order processing.
     * Supported across both classic checkout and pay-for-order pages.
     *
     * @return bool
     */
    public function validate_fields()
    {
        $this->validate_checkout_fields();
        return function_exists('wc_notice_count') ? (wc_notice_count('error') === 0) : true;
    }

    /**
     * Static AJAX upload entry point to ensure execution even when
     * WooCommerce has not initialized payment gateway classes.
     */
    public static function ajax_upload_slip_handler()
    {
        $gateway = new self();
        $gateway->ajax_upload_slip();
    }

    /**
     * Handle AJAX file upload for payment receipt.
     */
    public function ajax_upload_slip()
    {
        // Security check
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'oi_banglaqr_upload_slip_action')) {
            wp_send_json_error(array('message' => __('Your checkout session expired. Please refresh the page and try again.', 'banglaqr-payment-gateway-by-oi')));
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Ensure session cookie and token exist for guest uploads
        $session_token = '';
        if (function_exists('WC') && WC()->session) {
            if (!WC()->session->has_session()) {
                WC()->session->set_customer_session_cookie(true);
            }
            $session_token = WC()->session->get('oi_banglaqr_session_token');
            if (empty($session_token)) {
                $session_token = wp_generate_password(32, false);
                WC()->session->set('oi_banglaqr_session_token', $session_token);
            }
        }

        $attachment_id = 0;
        $allowed_mimes = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        );

        // 1. Standard $_FILES handling (Multipart Form Data - Preferred & Fastest)
        if (!empty($_FILES['oi_banglaqr_file'])) {
            $file = $_FILES['oi_banglaqr_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

            if (!empty($file['error'])) {
                $error_code = intval($file['error']);
                $error_message = __('Please select a receipt image file to upload.', 'banglaqr-payment-gateway-by-oi');
                if ($error_code === UPLOAD_ERR_INI_SIZE || $error_code === UPLOAD_ERR_FORM_SIZE) {
                    $error_message = __('This image file is too large. Please choose a smaller photo or screenshot.', 'banglaqr-payment-gateway-by-oi');
                }
                wp_send_json_error(array('message' => $error_message));
            }

            if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
                wp_send_json_error(array('message' => __('Please select a valid image file to upload.', 'banglaqr-payment-gateway-by-oi')));
            }

            // Strictly verify that the uploaded file is a valid image format (JPEG, PNG, WebP, GIF)
            $image_info = @getimagesize($file['tmp_name']);
            if (!$image_info || empty($image_info['mime']) || !array_key_exists($image_info['mime'], $allowed_mimes)) {
                wp_send_json_error(array('message' => __('Please upload a valid image file (JPEG, PNG, or WebP).', 'banglaqr-payment-gateway-by-oi')));
            }

            // Format check: verify/determine matching extension
            $ext = $allowed_mimes[$image_info['mime']];

            // Allow the user's original filename
            $raw_name = !empty($file['name']) ? sanitize_text_field(wp_unslash($file['name'])) : 'receipt';
            $info = pathinfo($raw_name);
            $orig_base = !empty($info['filename']) ? $info['filename'] : 'receipt';
            $orig_ext  = !empty($info['extension']) ? strtolower($info['extension']) : $ext;

            // Ensure extension is a valid image extension
            if (!in_array($orig_ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
                $orig_ext = $ext;
            }

            // Keep user's original filename, only sanitizing for filesystem safety.
            $clean_base = sanitize_file_name($orig_base);
            if (empty($clean_base) || $clean_base === '.') {
                $clean_base = 'receipt_' . substr(md5($orig_base), 0, 8);
            }
            $target_filename = $clean_base . '.' . $orig_ext;
            $file['name'] = $target_filename;

            $overrides = array(
                'test_form' => false,
                'test_type' => false,
                'mimes'     => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'gif'          => 'image/gif',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                ),
            );

            $uploaded_file = wp_handle_upload($file, $overrides);

            // Fallback: If wp_handle_upload fails for filesystem or move reasons, use wp_upload_bits
            if (isset($uploaded_file['error']) || empty($uploaded_file['file'])) {
                $file_contents = file_get_contents($file['tmp_name']);
                if ($file_contents !== false) {
                    $upload_fallback = wp_upload_bits($target_filename, null, $file_contents);
                    if (empty($upload_fallback['error']) && !empty($upload_fallback['file'])) {
                        $uploaded_file = $upload_fallback;
                        $uploaded_file['type'] = $image_info['mime'];
                    }
                }
            }

            if (isset($uploaded_file['error']) && !empty($uploaded_file['error'])) {
                wp_send_json_error(array('message' => $uploaded_file['error']));
            }

            if (empty($uploaded_file['file'])) {
                wp_send_json_error(array('message' => __('We could not save your receipt image. Please try uploading again.', 'banglaqr-payment-gateway-by-oi')));
            }

            $filename = $uploaded_file['file'];
            $mime_type = !empty($uploaded_file['type']) ? $uploaded_file['type'] : $image_info['mime'];

            $attachment = array(
                'post_mime_type' => $mime_type,
                'post_title'     => $orig_base,
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $filename);
        }

        // 2. Fallback: Process Base64 payload only if no multipart file was received
        if (!$attachment_id && !empty($_POST['image_base64'])) {
            $base64_data = wp_unslash($_POST['image_base64']);
            if (preg_match('/^data:image\/(jpeg|jpg|png|webp|gif);base64,([A-Za-z0-9+\/=\s]+)$/', $base64_data, $matches)) {
                $ext = strtolower($matches[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                $decoded = base64_decode($matches[2]);

                if ($decoded !== false) {
                    $tmp_name = wp_tempnam('receipt');
                    file_put_contents($tmp_name, $decoded);
                    $image_info = @getimagesize($tmp_name);
                    @unlink($tmp_name);

                    if (!$image_info || empty($image_info['mime']) || !array_key_exists($image_info['mime'], $allowed_mimes)) {
                        wp_send_json_error(array('message' => __('Please upload a valid image file (JPEG, PNG, or WebP).', 'banglaqr-payment-gateway-by-oi')));
                    }

                    $ext = $allowed_mimes[$image_info['mime']];

                    $raw_name = !empty($_POST['image_name']) ? sanitize_text_field(wp_unslash($_POST['image_name'])) : 'receipt';
                    $info = pathinfo($raw_name);
                    $orig_base = !empty($info['filename']) ? $info['filename'] : 'receipt';
                    $orig_ext  = !empty($info['extension']) ? strtolower($info['extension']) : $ext;
                    if (!in_array($orig_ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
                        $orig_ext = $ext;
                    }

                    $clean_base = sanitize_file_name($orig_base);
                    if (empty($clean_base) || $clean_base === '.') {
                        $clean_base = 'receipt_' . substr(md5($orig_base), 0, 8);
                    }
                    $filename_to_save = $clean_base . '.' . $orig_ext;

                    $upload = wp_upload_bits($filename_to_save, null, $decoded);

                    if (!empty($upload['error'])) {
                        wp_send_json_error(array('message' => $upload['error']));
                    }

                    $filename = $upload['file'];
                    $wp_filetype = wp_check_filetype($filename, null);
                    $mime_type = !empty($wp_filetype['type']) ? $wp_filetype['type'] : $image_info['mime'];

                    $attachment = array(
                        'post_mime_type' => $mime_type,
                        'post_title'     => $orig_base,
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    );

                    $attachment_id = wp_insert_attachment($attachment, $filename);
                }
            }
        }

        if (!$attachment_id || is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => __('We could not save your receipt image. Please try uploading again.', 'banglaqr-payment-gateway-by-oi')));
        }

        // Safely generate metadata without crashing on memory limits or timeouts
        if (function_exists('wp_generate_attachment_metadata')) {
            try {
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $filename);
                if (!empty($attachment_data) && !is_wp_error($attachment_data)) {
                    wp_update_attachment_metadata($attachment_id, $attachment_data);
                }
            } catch (\Throwable $e) {
                // Silently ignore thumbnail generation errors to prevent upload failures
            }
        }

        // Security mark to link receipt to customer session
        update_post_meta($attachment_id, '_oi_banglaqr_pending_upload', '1');
        if (!empty($session_token)) {
            update_post_meta($attachment_id, '_oi_banglaqr_uploader_token', $session_token);
        }
        if (is_user_logged_in()) {
            update_post_meta($attachment_id, '_oi_banglaqr_uploader_user_id', get_current_user_id());
        }

        $receipt_token = wp_create_nonce('oi_banglaqr_receipt_' . $attachment_id);

        wp_send_json_success(array(
            'id'            => $attachment_id,
            'attachment_id' => $attachment_id,
            'token'         => $receipt_token,
            'url'           => wp_get_attachment_url($attachment_id),
        ));
    }

    /**
     * Process WooCommerce Payment.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $receipt_id = 0;
        $trx_id = '';
        $selected_qr = '';

        // Save receipt and selected QR metadata to the order
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!empty($_POST['oi_banglaqr_receipt_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $candidate_id = absint(wp_unslash($_POST['oi_banglaqr_receipt_id']));

            // Security check: Only bind attachment if it was uploaded in this customer's active session or matches signed token
            if ($candidate_id > 0) {
                $receipt_post = get_post($candidate_id);
                if ($receipt_post && $receipt_post->post_type === 'attachment' && (int)$receipt_post->post_parent === 0) {
                    if (get_post_meta($candidate_id, '_oi_banglaqr_pending_upload', true) === '1') {
                        $uploader_token   = get_post_meta($candidate_id, '_oi_banglaqr_uploader_token', true);
                        $uploader_user_id = (int) get_post_meta($candidate_id, '_oi_banglaqr_uploader_user_id', true);
                        $session_token    = (function_exists('WC') && WC()->session) ? WC()->session->get('oi_banglaqr_session_token') : '';
                        $receipt_token    = !empty($_POST['oi_banglaqr_receipt_token']) ? sanitize_key(wp_unslash($_POST['oi_banglaqr_receipt_token'])) : '';

                        $is_token_valid = (!empty($uploader_token) && !empty($session_token) && hash_equals((string)$uploader_token, (string)$session_token));
                        $is_nonce_valid = (!empty($receipt_token) && wp_verify_nonce($receipt_token, 'oi_banglaqr_receipt_' . $candidate_id));
                        $is_user_valid  = (is_user_logged_in() && $uploader_user_id > 0 && $uploader_user_id === get_current_user_id());

                        if ($is_token_valid || $is_nonce_valid || $is_user_valid) {
                            $receipt_id = $candidate_id;
                            $order->update_meta_data('_oi_banglaqr_receipt_id', $receipt_id);

                            // Set the attachment as media parent of this order and link to order ID for HPOS
                            update_post_meta($receipt_id, '_oi_banglaqr_order_id', $order_id);
                            wp_update_post(array(
                                'ID'          => $receipt_id,
                                'post_parent' => $order_id,
                            ));
                            // Remove pending mark so cleanup cron will not delete it
                            delete_post_meta($receipt_id, '_oi_banglaqr_pending_upload');
                            delete_post_meta($receipt_id, '_oi_banglaqr_uploader_token');
                            delete_post_meta($receipt_id, '_oi_banglaqr_uploader_user_id');
                        }
                    }
                }
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!empty($_POST['oi_banglaqr_transaction_id'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $trx_id = sanitize_text_field(wp_unslash($_POST['oi_banglaqr_transaction_id']));
            $order->update_meta_data('_oi_banglaqr_transaction_id', $trx_id);
            $order->set_transaction_id($trx_id);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!empty($_POST['oi_banglaqr_selected_qr'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $selected_qr = sanitize_text_field(wp_unslash($_POST['oi_banglaqr_selected_qr']));
            $order->update_meta_data('_oi_banglaqr_selected_qr', $selected_qr);
        }

        // Build informative order note
        $note_details = array();
        if (!empty($selected_qr)) {
            // translators: %s is the QR account name.
            $note_details[] = sprintf(__('QR: %s', 'banglaqr-payment-gateway-by-oi'), $selected_qr);
        }
        if (!empty($trx_id)) {
            // translators: %s is the transaction ID.
            $note_details[] = sprintf(__('TrxID: %s', 'banglaqr-payment-gateway-by-oi'), $trx_id);
        }
        if ($receipt_id) {
            $note_details[] = __('Receipt: Attached', 'banglaqr-payment-gateway-by-oi');
        }

        $order_note = __('Awaiting payment verification for Bangla QR.', 'banglaqr-payment-gateway-by-oi');
        if (!empty($note_details)) {
            $order_note .= ' (' . implode(' | ', $note_details) . ')';
        }

        // Explicitly persist order metadata (essential for HPOS & order-pay screens)
        $order->save();

        // Set order status based on admin configuration (automatically triggers stock handling safely)
        $order_status = $this->get_option('order_status', 'on-hold');
        if ($order->get_status() !== $order_status) {
            $order->update_status($order_status, $order_note);
        } else {
            $order->add_order_note($order_note);
            $order->save();
        }

        // Clear cart safely if present
        if (function_exists('WC') && WC()->cart) {
            WC()->cart->empty_cart();
        }

        // Return thank you redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Render the modal HTML in the footer on the checkout page.
     */
    public function render_modal_html()
    {
        $is_pay_page   = function_exists('is_checkout_pay_page') && is_checkout_pay_page();
        $is_checkout   = function_exists('is_checkout') && is_checkout();
        $is_thankyou   = function_exists('is_order_received_page') && is_order_received_page();

        if ((!$is_checkout && !$is_pay_page) || $is_thankyou || !$this->is_available()) {
            return;
        }

        $settings  = $this->settings;
        $active_qr = self::get_active_qr($settings);
        $charge    = ($active_qr && isset($active_qr['payment_charge'])) ? floatval($active_qr['payment_charge']) : 0;
        $receipt_rule = isset($settings['receipt_rule']) ? $settings['receipt_rule'] : 'optional';
        $trxid_rule = isset($settings['trxid_rule']) ? $settings['trxid_rule'] : 'optional';
        $enable_manual_payment = isset($settings['enable_manual_payment']) ? $settings['enable_manual_payment'] : 'no';
        $paymentpage_img_url = OI_BANGLAQR_URL . 'includes/img/banglaqr-paymentpage.png';

        // Formatted total respecting store decimal settings and order-pay context
        $total_amount = 0;
        if ($is_pay_page) {
            global $wp;
            $pay_order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
            if ($pay_order_id > 0) {
                $pay_order = wc_get_order($pay_order_id);
                if ($pay_order) {
                    $total_amount = $pay_order->get_total();
                }
            }
        } elseif (function_exists('WC') && WC()->cart) {
            $total_amount = WC()->cart->total;
        }

        $formatted_total = wc_price($total_amount);
        $raw_numeric_total = number_format(floatval($total_amount), function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2, '.', '');
        
        $max_upload_size = wp_max_upload_size();
        $allowed_max_size = min(5 * 1024 * 1024, $max_upload_size);
        $allowed_max_size_mb = max(1, round($allowed_max_size / (1024 * 1024))) . 'MB';
        $settings = get_option('woocommerce_oi_banglaqr_settings', array());
        $modal_theme_color = isset($settings['theme_color']) && !empty($settings['theme_color']) ? $settings['theme_color'] : '#0A694B';
        ?>
        <div id="banglaqr-modal" class="banglaqr-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="banglaqr-modal-title" style="--banglaqr-primary: <?php echo esc_attr($modal_theme_color); ?>; --banglaqr-modal-primary: <?php echo esc_attr($modal_theme_color); ?>;">
            <div class="banglaqr-modal-container" role="document">
                <!-- Non-destructive Success Overlay -->
                <div id="banglaqr-success-overlay" class="banglaqr-success-overlay" style="display:none;">
                    <div class="banglaqr-success-icon-wrap">
                        <div class="banglaqr-success-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </div>
                    </div>
                    <div class="banglaqr-success-title"><?php esc_html_e('Payment Details Received!', 'banglaqr-payment-gateway-by-oi'); ?></div>
                    <div class="banglaqr-success-subtitle"><?php esc_html_e('Thank you! We are confirming your order now...', 'banglaqr-payment-gateway-by-oi'); ?></div>
                </div>

                <!-- Header -->
                <div class="banglaqr-modal-header">
                    <div>
                        <h3 id="banglaqr-modal-title"><?php esc_html_e('Bangla QR Payment', 'banglaqr-payment-gateway-by-oi'); ?></h3>
                        <p class="banglaqr-modal-subtitle"><?php esc_html_e('Scan the QR code with your mobile banking app to pay', 'banglaqr-payment-gateway-by-oi'); ?></p>
                    </div>
                    <div style="display:flex; align-items:center; gap: 12px;">
                        <div class="banglaqr-countdown-timer" id="banglaqr-countdown-timer">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span id="banglaqr-timer-text">15:00</span>
                        </div>
                        <button type="button" class="banglaqr-modal-close" id="banglaqr-modal-close-btn" aria-label="<?php esc_attr_e('Close modal', 'banglaqr-payment-gateway-by-oi'); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="banglaqr-modal-body">
                    <div id="banglaqr-error-banner" class="banglaqr-modal-error" role="alert" aria-live="polite"></div>

                    <?php if ($active_qr && !empty($active_qr['qr_code_url'])): ?>
                        <div class="banglaqr-payable-amount-box">
                            <div class="banglaqr-payable-label"><?php esc_html_e('Payable Amount', 'banglaqr-payment-gateway-by-oi'); ?></div>
                            <div class="banglaqr-payable-row">
                                <div class="banglaqr-payable-value" id="banglaqr-modal-payable-val"><?php echo wp_kses_post($formatted_total); ?></div>
                                <button type="button" class="banglaqr-copy-amount-btn" id="banglaqr-copy-amount-btn" data-amount="<?php echo esc_attr($raw_numeric_total); ?>" title="<?php esc_attr_e('Copy Amount', 'banglaqr-payment-gateway-by-oi'); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    <span class="banglaqr-copy-amount-text"><?php esc_html_e('Copy', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </button>
                            </div>
                            <?php if ($charge > 0): ?>
                                <div class="banglaqr-payable-note"><?php printf(esc_html__('(Includes %s%% payment processing fee)', 'banglaqr-payment-gateway-by-oi'), $charge); ?></div>
                            <?php else: ?>
                                <div class="banglaqr-payable-note"><?php esc_html_e('(No extra fees applied)', 'banglaqr-payment-gateway-by-oi'); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- QR Code Box -->
                        <div class="banglaqr-qr-wrapper">
                            <div class="banglaqr-qr-box is-zoomable" id="banglaqr-qr-box" title="<?php esc_attr_e('Tap or click to view larger QR code', 'banglaqr-payment-gateway-by-oi'); ?>" role="button" tabindex="0" aria-label="<?php esc_attr_e('Enlarge QR Code', 'banglaqr-payment-gateway-by-oi'); ?>">
                                <div class="banglaqr-qr-zoom-badge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                                </div>
                                <img src="<?php echo esc_url($active_qr['qr_code_url']); ?>" alt="<?php echo esc_attr(!empty($active_qr['qr_name']) ? $active_qr['qr_name'] : 'Bangla QR Code'); ?>" />
                                <div class="banglaqr-qr-box-text"><?php esc_html_e('Scan with Your App', 'banglaqr-payment-gateway-by-oi'); ?></div>
                            </div>
                            <div class="banglaqr-qr-zoom-hint-text"><?php esc_html_e('Tap to enlarge QR code', 'banglaqr-payment-gateway-by-oi'); ?></div>
                            <div class="banglaqr-qr-actions-row">
                                <a href="<?php echo esc_url($active_qr['qr_code_url']); ?>" download="banglaqr-pay.png" target="_blank" rel="noopener noreferrer" class="banglaqr-download-qr-btn" id="banglaqr-download-qr-btn" title="<?php esc_attr_e('QR কোড সেভ বা ডাউনলোড করুন', 'banglaqr-payment-gateway-by-oi'); ?>">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    <span><?php esc_html_e('QR কোড সেভ / ডাউনলোড করুন', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </a>
                            </div>
                        </div>

                        <!-- Mobile Friendly Instruction Box -->
                        <div class="banglaqr-mobile-helper-box">
                            <div class="banglaqr-mobile-helper-header">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                                <strong><?php esc_html_e('মোবাইলে কেনাকাটা করছেন?', 'banglaqr-payment-gateway-by-oi'); ?></strong>
                            </div>
                            <p class="banglaqr-mobile-helper-desc">
                                <?php esc_html_e('১. উপরের QR কোডটি সেভ/স্ক্রিনশট নিন।', 'banglaqr-payment-gateway-by-oi'); ?><br>
                                <?php esc_html_e('২. আপনার বিকাশ, নগদ বা ব্যাংক অ্যাপ ওপেন করে কিউআর স্ক্যানারে যান এবং "গ্যালারি থেকে স্ক্যান" (Scan from Gallery) দিন।', 'banglaqr-payment-gateway-by-oi'); ?><br>
                                <?php esc_html_e('৩. অথবা নিচের বাটনে ক্লিক করে সরাসরি নাম্বারে সেন্ড মানি করুন।', 'banglaqr-payment-gateway-by-oi'); ?>
                            </p>
                        </div>

                        <!-- Payment Page Banner -->
                        <?php if ($paymentpage_img_url): ?>
                            <div class="banglaqr-payment-methods-banner">
                                <img src="<?php echo esc_url($paymentpage_img_url); ?>" alt="<?php esc_attr_e('Accepted Payment Methods', 'banglaqr-payment-gateway-by-oi'); ?>" />
                            </div>
                        <?php endif; ?>

                        <!-- Instruction Alert Banner -->
                        <div class="banglaqr-instruction-banner">
                            <p class="banglaqr-instruction-text"><?php esc_html_e('Open your bank or mobile wallet app (bKash, Nagad, Rocket, CellFin, etc.) and scan the QR code to pay. Then confirm your payment below.', 'banglaqr-payment-gateway-by-oi'); ?></p>
                        </div>

                        <?php if ($enable_manual_payment === 'yes'): 
                            $manual_accounts = array(
                                array('name' => 'bKash', 'number' => isset($settings['manual_bkash']) ? $settings['manual_bkash'] : ''),
                                array('name' => 'Nagad', 'number' => isset($settings['manual_nagad']) ? $settings['manual_nagad'] : ''),
                                array('name' => 'Rocket', 'number' => isset($settings['manual_rocket']) ? $settings['manual_rocket'] : ''),
                                array('name' => 'Upay', 'number' => isset($settings['manual_upay']) ? $settings['manual_upay'] : ''),
                                array('name' => 'CellFin', 'number' => isset($settings['manual_cellfin']) ? $settings['manual_cellfin'] : ''),
                            );
                            $has_manual = false;
                            foreach ($manual_accounts as $acc) {
                                if (!empty($acc['number']) && trim((string)$acc['number']) !== '') {
                                    $has_manual = true;
                                    break;
                                }
                            }
                            if ($has_manual):
                        ?>
                            <div class="banglaqr-trx-toggle-wrap" style="margin-top:15px; margin-bottom: 5px;">
                                <button type="button" class="banglaqr-trx-toggle-btn" id="banglaqr-mfs-toggle-btn" aria-expanded="false" aria-controls="banglaqr-mfs-accounts-container">
                                    <span class="banglaqr-trx-toggle-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                    </span>
                                    <span class="banglaqr-trx-toggle-text"><?php esc_html_e('Prefer to pay manually? View mobile wallet numbers', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                </button>
                            </div>
                            <div class="banglaqr-manual-accounts" id="banglaqr-mfs-accounts-container" style="display:none; margin-top: 10px;">
                                <?php foreach ($manual_accounts as $acc): 
                                    if (!empty($acc['number']) && trim((string)$acc['number']) !== ''):
                                        $brandClass = 'banglaqr-brand-' . strtolower($acc['name']);
                                ?>
                                    <div class="banglaqr-manual-account-item <?php echo esc_attr($brandClass); ?>">
                                        <span class="banglaqr-manual-account-name"><?php echo esc_html($acc['name']); ?></span>
                                        <div class="banglaqr-manual-account-number-wrap">
                                            <span class="banglaqr-manual-account-number"><?php echo esc_html($acc['number']); ?></span>
                                            <button type="button" class="banglaqr-manual-copy-btn" data-number="<?php echo esc_attr($acc['number']); ?>" title="<?php esc_attr_e('Copy Number', 'banglaqr-payment-gateway-by-oi'); ?>">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                                <span><?php esc_html_e('Copy', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        <?php 
                            endif;
                        endif; 
                        ?>
                    <?php else: ?>
                        <div class="banglaqr-modal-error" style="display:block;">
                            <?php esc_html_e('No active QR accounts are currently set up. Please contact support or choose another payment method.', 'banglaqr-payment-gateway-by-oi'); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Upload Receipt Section -->
                    <?php if ($receipt_rule !== 'hidden'): ?>
                        <div class="banglaqr-upload-section">
                            <label class="banglaqr-upload-label" for="banglaqr-file-input">
                                <?php esc_html_e('Upload Payment Screenshot or Receipt', 'banglaqr-payment-gateway-by-oi'); ?>
                                <?php if ($receipt_rule === 'mandatory'): ?>
                                    <span style="color:#ef4444;">*</span>
                                <?php endif; ?>
                            </label>
                            <div id="banglaqr-dropzone" class="banglaqr-dropzone" tabindex="0" role="button" aria-label="<?php esc_attr_e('Upload payment screenshot', 'banglaqr-payment-gateway-by-oi'); ?>">
                                <svg class="banglaqr-upload-icon" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" /></svg>
                                <span class="banglaqr-upload-text"><?php esc_html_e('Choose or drop payment screenshot', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                <span class="banglaqr-upload-subtext"><?php printf(esc_html__('Supports JPEG, PNG, WEBP up to %s', 'banglaqr-payment-gateway-by-oi'), $allowed_max_size_mb); ?></span>
                                <input type="file" id="banglaqr-file-input" style="display:none;" accept="image/jpeg,image/png,image/webp,image/gif" />
                            </div>
                            <div id="banglaqr-file-preview-container"></div>
                        </div>
                    <?php endif; ?>

                    <!-- Transaction ID Section -->
                    <?php if ($trxid_rule !== 'hidden'): 
                        $hide_toggle = ($receipt_rule === 'hidden' || $trxid_rule === 'mandatory');
                    ?>
                        <div class="banglaqr-trx-section">
                            <?php if (!$hide_toggle): ?>
                                <div class="banglaqr-trx-toggle-wrap">
                                    <button type="button" class="banglaqr-trx-toggle-btn" id="banglaqr-trx-toggle-btn" aria-expanded="false" aria-controls="banglaqr-trx-input-container">
                                        <span class="banglaqr-trx-toggle-icon">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                        </span>
                                        <span class="banglaqr-trx-toggle-text"><?php esc_html_e('Have a Transaction ID? Enter it here', 'banglaqr-payment-gateway-by-oi'); ?></span>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="banglaqr-trx-input-container" id="banglaqr-trx-input-container" style="<?php echo $hide_toggle ? 'display:block;' : 'display:none;'; ?>">
                                <label class="banglaqr-trx-label" for="banglaqr-trx-input">
                                    <?php esc_html_e('Transaction ID (TrxID) / Reference', 'banglaqr-payment-gateway-by-oi'); ?>
                                    <?php if ($trxid_rule === 'mandatory'): ?>
                                        <span style="color:#ef4444;">*</span>
                                    <?php endif; ?>
                                </label>
                                <div class="banglaqr-trx-input-box">
                                    <svg class="banglaqr-trx-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                    <input type="text" id="banglaqr-trx-input" class="banglaqr-trx-input" placeholder="<?php esc_attr_e('e.g. 9K28DF109X or Bank Reference', 'banglaqr-payment-gateway-by-oi'); ?>" autocomplete="off" />
                                </div>
                                <span class="banglaqr-trx-hint"><?php esc_html_e('You can find this in your bank or mobile wallet confirmation SMS / receipt.', 'banglaqr-payment-gateway-by-oi'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                </div> <!-- Close modal-body -->

                <!-- Footer -->
                <div class="banglaqr-modal-footer">
                    <button type="button" class="banglaqr-btn banglaqr-btn-cancel" id="banglaqr-btn-cancel"><?php esc_html_e('Cancel', 'banglaqr-payment-gateway-by-oi'); ?></button>
                    <button type="button" class="banglaqr-btn banglaqr-btn-submit" id="banglaqr-btn-submit"><?php esc_html_e('Confirm & Place Order', 'banglaqr-payment-gateway-by-oi'); ?></button>
                </div>
            </div> <!-- Close modal-container -->

            <!-- Enlarged QR Lightbox View -->
            <?php if ($active_qr && !empty($active_qr['qr_code_url'])): ?>
                <div id="banglaqr-zoom-overlay" class="banglaqr-zoom-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Enlarged QR Code', 'banglaqr-payment-gateway-by-oi'); ?>">
                    <div class="banglaqr-zoom-card">
                        <div class="banglaqr-zoom-header">
                            <div class="banglaqr-zoom-title-box">
                                <span class="banglaqr-zoom-badge"><?php echo esc_html(!empty($active_qr['qr_name']) ? $active_qr['qr_name'] : 'Bangla QR'); ?></span>
                                <h4 class="banglaqr-zoom-title"><?php esc_html_e('Scan to Pay', 'banglaqr-payment-gateway-by-oi'); ?></h4>
                            </div>
                            <button type="button" class="banglaqr-zoom-close" id="banglaqr-zoom-close-btn" aria-label="<?php esc_attr_e('Close enlarged QR', 'banglaqr-payment-gateway-by-oi'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="banglaqr-zoom-img-wrap">
                            <img src="<?php echo esc_url($active_qr['qr_code_url']); ?>" alt="<?php echo esc_attr(!empty($active_qr['qr_name']) ? $active_qr['qr_name'] : 'Bangla QR Code'); ?>" class="banglaqr-zoom-img" />
                        </div>
                        <div class="banglaqr-zoom-footer-note"><?php esc_html_e('Scan directly from your screen using your bank or mobile wallet app.', 'banglaqr-payment-gateway-by-oi'); ?></div>
                        <button type="button" class="banglaqr-zoom-dismiss-btn" id="banglaqr-zoom-dismiss-btn"><?php esc_html_e('Done / Back to Checkout', 'banglaqr-payment-gateway-by-oi'); ?></button>
                    </div>
                </div>
            <?php endif; ?>
        </div> <!-- Close modal-overlay -->
        <?php
    }

    /**
     * Display order payment info on Order Received (Thank You) page.
     *
     * @param int $order_id Order ID.
     */
    public function thankyou_page_content($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $trx_id      = $order->get_meta('_oi_banglaqr_transaction_id');
        $selected_qr = $order->get_meta('_oi_banglaqr_selected_qr');
        $receipt_id  = $order->get_meta('_oi_banglaqr_receipt_id');

        if (empty($trx_id) && $order->get_transaction_id()) {
            $trx_id = $order->get_transaction_id();
        }
        $brand_color = !empty($this->theme_color) ? $this->theme_color : '#0A694B';
        ?>
        <div class="banglaqr-thankyou-receipt-card">
            <div class="banglaqr-thankyou-header">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="<?php echo esc_attr($brand_color); ?>" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h4><?php esc_html_e('BanglaQR পেমেন্ট তথ্য সফলভাবে গৃহীত হয়েছে', 'banglaqr-payment-gateway-by-oi'); ?></h4>
            </div>
            <p class="banglaqr-thankyou-note">
                <?php esc_html_e('ধন্যবাদ! আপনার পেমেন্ট তথ্য আমাদের অ্যাকাউন্টিং টিমে পৌঁছেছে। যাচাই শেষে দ্রুত অর্ডারটি প্রসেস করা হবে।', 'banglaqr-payment-gateway-by-oi'); ?>
            </p>
            <div class="banglaqr-thankyou-meta-grid">
                <?php if (!empty($selected_qr)): ?>
                    <div class="banglaqr-thankyou-meta-item">
                        <span class="label"><?php esc_html_e('QR অ্যাকাউন্ট:', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="val"><?php echo esc_html($selected_qr); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($trx_id)): ?>
                    <div class="banglaqr-thankyou-meta-item">
                        <span class="label"><?php esc_html_e('Transaction ID (TrxID):', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="val"><code><?php echo esc_html($trx_id); ?></code></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($receipt_id)): 
                    $receipt_url = is_numeric($receipt_id) ? wp_get_attachment_url($receipt_id) : $receipt_id;
                    if ($receipt_url):
                ?>
                    <div class="banglaqr-thankyou-meta-item">
                        <span class="label"><?php esc_html_e('পেমেন্ট স্লিপ:', 'banglaqr-payment-gateway-by-oi'); ?></span>
                        <span class="val"><a href="<?php echo esc_url($receipt_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('রিসিট দেখুন', 'banglaqr-payment-gateway-by-oi'); ?> &rarr;</a></span>
                    </div>
                <?php endif; endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display payment verification details on Customer My Account -> View Order page.
     *
     * @param WC_Order $order Order object.
     */
    public function view_order_receipt_content($order)
    {
        // Prevent duplicate rendering on Thank You / Order Received page
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return;
        }

        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }
        $this->thankyou_page_content($order->get_id());
    }

    /**
     * Add payment details to WooCommerce order emails.
     *
     * @param WC_Order $order Order object.
     * @param bool $sent_to_admin Whether sent to admin.
     * @param bool $plain_text Plain text email.
     * @param WC_Email $email Email object.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false, $email = null)
    {
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $trx_id      = $order->get_meta('_oi_banglaqr_transaction_id');
        $selected_qr = $order->get_meta('_oi_banglaqr_selected_qr');
        $receipt_id  = $order->get_meta('_oi_banglaqr_receipt_id');
        $receipt_url = '';

        if (!empty($receipt_id)) {
            $receipt_url = is_numeric($receipt_id) ? wp_get_attachment_url($receipt_id) : $receipt_id;
        }

        if (empty($trx_id) && $order->get_transaction_id()) {
            $trx_id = $order->get_transaction_id();
        }

        if ($plain_text) {
            echo "\n====================================================\n";
            echo esc_html__("BanglaQR পেমেন্ট বিবরণ:\n", 'banglaqr-payment-gateway-by-oi');
            if (!empty($selected_qr)) {
                echo esc_html__("অ্যাকাউন্ট: ", 'banglaqr-payment-gateway-by-oi') . $selected_qr . "\n";
            }
            if (!empty($trx_id)) {
                echo esc_html__("TrxID: ", 'banglaqr-payment-gateway-by-oi') . $trx_id . "\n";
            }
            if (!empty($receipt_url)) {
                echo esc_html__("পেমেন্ট স্লিপ: ", 'banglaqr-payment-gateway-by-oi') . $receipt_url . "\n";
            }
            echo esc_html__("পেমেন্ট যাচাইয়ের পর অর্ডারটি কনফার্ম করা হবে।\n", 'banglaqr-payment-gateway-by-oi');
            echo "====================================================\n\n";
        } else {
            $brand_color = !empty($this->theme_color) ? $this->theme_color : '#0A694B';
            ?>
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:20px 0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
                <h3 style="margin-top:0; color:<?php echo esc_attr($brand_color); ?>; font-size:16px; display:flex; align-items:center;">
                    <?php esc_html_e('BanglaQR পেমেন্ট বিবরণ', 'banglaqr-payment-gateway-by-oi'); ?>
                </h3>
                <p style="margin:4px 0 12px 0; color:#334155; font-size:14px;">
                    <?php esc_html_e('আপনার পেমেন্ট তথ্য জমা নেওয়া হয়েছে। আমাদের টিম যাচাই শেষে অর্ডারটি সম্পন্ন করবে।', 'banglaqr-payment-gateway-by-oi'); ?>
                </p>
                <table style="width:100%; border-collapse:collapse; font-size:13px; color:#475569;">
                    <?php if (!empty($selected_qr)): ?>
                        <tr>
                            <td style="padding:4px 0; font-weight:600; width:140px;"><?php esc_html_e('QR অ্যাকাউন্ট:', 'banglaqr-payment-gateway-by-oi'); ?></td>
                            <td style="padding:4px 0; color:#0f172a;"><?php echo esc_html($selected_qr); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($trx_id)): ?>
                        <tr>
                            <td style="padding:4px 0; font-weight:600;"><?php esc_html_e('Transaction ID (TrxID):', 'banglaqr-payment-gateway-by-oi'); ?></td>
                            <td style="padding:4px 0; color:#0f172a;"><strong><?php echo esc_html($trx_id); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <?php if (!empty($receipt_url)): ?>
                        <tr>
                            <td style="padding:4px 0; font-weight:600;"><?php esc_html_e('পেমেন্ট স্লিপ:', 'banglaqr-payment-gateway-by-oi'); ?></td>
                            <td style="padding:4px 0;"><a href="<?php echo esc_url($receipt_url); ?>" target="_blank" style="color:<?php echo esc_attr($brand_color); ?>; font-weight:700; text-decoration:underline;"><?php esc_html_e('রিসিট দেখুন', 'banglaqr-payment-gateway-by-oi'); ?> &rarr;</a></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php
        }
    }

}

