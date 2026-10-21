<?php
/**
 * Plugin Name: BanglaQR Gateway by Oi
 * Plugin URI: https://oitech.com.bd/open-source/
 * Description: A payment gateway supporting bank and mobile QR payments with a scan-to-pay popup and payment receipt upload verification.
 * Version: 0.3.1
 * Author: Oi Technologies
 * Author URI: https://oitech.com.bd/
 * License: GPLv3 or later
 * Domain Path: /languages
 * Tested up to: 7.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.8
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: banglaqr-payment-gateway-by-oi
 */

defined('ABSPATH') || exit;

// Define plugin-wide constants
define('OI_BANGLAQR_VERSION', '0.3.1');
define('OI_BANGLAQR_PATH', plugin_dir_path(__FILE__));
define('OI_BANGLAQR_URL', plugin_dir_url(__FILE__));
define('OI_BANGLAQR_BASENAME', plugin_basename(__FILE__));

/**
 * Declare WooCommerce HPOS and Blocks Compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, false);
    }
});

/**
 * Add settings action link to the plugin lists table.
 *
 * @param array $links Array of plugin action links.
 * @return array Modified links array.
 */
function oi_banglaqr_add_settings_link($links)
{
    if (class_exists('WooCommerce')) {
        $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=oi_banglaqr');
        $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'banglaqr-payment-gateway-by-oi') . '</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links_' . OI_BANGLAQR_BASENAME, 'oi_banglaqr_add_settings_link');

/**
 * Initialize the plugin when plugins are loaded.
 */
function oi_banglaqr_init_plugin()
{
    // Load plugin text domain for translations
    load_plugin_textdomain('banglaqr-payment-gateway-by-oi', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Hard check for WooCommerce
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'oi_banglaqr_woocommerce_missing_notice');
        return;
    }

    // Load WooCommerce specific files
    require_once OI_BANGLAQR_PATH . 'includes/class-oi-banglaqr-gateway.php';
    require_once OI_BANGLAQR_PATH . 'includes/class-oi-banglaqr-admin.php';

    new Oi_BanglaQR_Admin();

    // Register gateway in WooCommerce
    add_filter('woocommerce_payment_gateways', 'oi_banglaqr_register_gateway');

    // Calculate fees globally to bypass class instantiation delays
    add_action('woocommerce_cart_calculate_fees', 'oi_banglaqr_add_payment_charge_fee');

    // Register AJAX slip upload handlers globally so they run reliably during admin-ajax.php
    add_action('wp_ajax_oi_banglaqr_upload_slip', array('Oi_BanglaQR_Gateway', 'ajax_upload_slip_handler'));
    add_action('wp_ajax_nopriv_oi_banglaqr_upload_slip', array('Oi_BanglaQR_Gateway', 'ajax_upload_slip_handler'));
    add_action('wc_ajax_oi_banglaqr_upload_slip', array('Oi_BanglaQR_Gateway', 'ajax_upload_slip_handler'));
}
add_action('plugins_loaded', 'oi_banglaqr_init_plugin', 11);

/**
 * WooCommerce Missing Admin Notice.
 */
function oi_banglaqr_woocommerce_missing_notice()
{
    ?>
    <div class="error notice">
        <p><?php esc_html_e('BanglaQR Gateway by Oi requires WooCommerce to be installed and active. The plugin is currently disabled.', 'banglaqr-payment-gateway-by-oi'); ?>
        </p>
    </div>
    <?php
}

/**
 * Register Gateway with WooCommerce.
 *
 * @param array $gateways WooCommerce gateways.
 * @return array
 */
function oi_banglaqr_register_gateway($gateways)
{
    $gateways[] = 'Oi_BanglaQR_Gateway';
    return $gateways;
}

/**
 * Add payment gateway charge fee globally.
 */
function oi_banglaqr_add_payment_charge_fee()
{
    $is_ajax = function_exists('wp_doing_ajax') ? wp_doing_ajax() : (defined('DOING_AJAX') && DOING_AJAX);
    if ((is_admin() && !$is_ajax) || !WC()->cart) {
        return;
    }

    // Do not show the fee on the cart page
    if (is_cart()) {
        return;
    }

    // 1. Fetch the active QR code settings directly from database
    $settings = get_option('woocommerce_oi_banglaqr_settings', array());
    if (!is_array($settings) || empty($settings['enabled']) || $settings['enabled'] !== 'yes') {
        return;
    }

    // 2. Get chosen payment method dynamically from request or WooCommerce Session
    $chosen_gateway = '';

    // Direct POST param (present on checkout submission or direct AJAX)
    if (!empty($_POST['payment_method'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $chosen_gateway = sanitize_text_field(wp_unslash($_POST['payment_method']));
    }

    // AJAX checkout serialized form data (standard WooCommerce update_order_review AJAX)
    if (empty($chosen_gateway) && !empty($_POST['post_data'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_data = array();
        parse_str(wp_unslash($_POST['post_data']), $post_data); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!empty($post_data['payment_method'])) {
            $chosen_gateway = sanitize_text_field($post_data['payment_method']);
            // Synchronize with WooCommerce session immediately
            if (WC()->session) {
                WC()->session->set('chosen_payment_method', $chosen_gateway);
            }
        }
    }

    // WooCommerce Session
    if (empty($chosen_gateway) && WC()->session) {
        $chosen_gateway = WC()->session->get('chosen_payment_method');
    }

    // Fallback: If session is still empty on initial checkout load, check default gateway
    if (empty($chosen_gateway)) {
        $default_gateway = get_option('woocommerce_default_gateway');
        if (!empty($default_gateway)) {
            $chosen_gateway = $default_gateway;
        }
    }

    // Strictly enforce: ONLY add fee when Bangla QR ('oi_banglaqr') is chosen!
    if ('oi_banglaqr' !== $chosen_gateway) {
        return;
    }

    $active_qr = Oi_BanglaQR_Gateway::get_active_qr($settings);
    $charge_percent = ($active_qr && isset($active_qr['payment_charge'])) ? floatval($active_qr['payment_charge']) : 0;
    if ($charge_percent <= 0) {
        return;
    }

    // 3. Base amount: net cart total after discounts + shipping
    $base_amount = WC()->cart->get_cart_contents_total() + WC()->cart->get_shipping_total();

    // Calculate fee respecting store decimal precision
    $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
    $fee = round(($base_amount * $charge_percent) / 100, $decimals);

    if ($fee > 0) {
        // translators: %s is the payment charge percentage.
        $fee_name = sprintf(__('Payment Processing Fee (%s%%)', 'banglaqr-payment-gateway-by-oi'), $charge_percent);
        $is_taxable = apply_filters('oi_banglaqr_fee_is_taxable', false);
        WC()->cart->add_fee($fee_name, $fee, $is_taxable);
    }
}

/**
 * Schedule a daily cleanup event for pending uploads to prevent storage exhaustion.
 */
function oi_banglaqr_activate_plugin()
{
    if (!wp_next_scheduled('oi_banglaqr_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'oi_banglaqr_daily_cleanup');
    }
}
register_activation_hook(__FILE__, 'oi_banglaqr_activate_plugin');

/**
 * Clear the scheduled event on plugin deactivation.
 */
function oi_banglaqr_deactivate_plugin()
{
    wp_clear_scheduled_hook('oi_banglaqr_daily_cleanup');
}
register_deactivation_hook(__FILE__, 'oi_banglaqr_deactivate_plugin');

/**
 * Cleanup orphaned pending receipt uploads older than 24 hours.
 */
function oi_banglaqr_cleanup_pending_receipts()
{
    $args = array(
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 100,
        'date_query'     => array(
            array(
                'column' => 'post_date_gmt',
                'before' => '24 hours ago',
            ),
        ),
        'meta_query'     => array(
            array(
                'key'   => '_oi_banglaqr_pending_upload',
                'value' => '1',
            ),
        ),
    );

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        foreach ($query->posts as $attachment) {
            // Safety check: Never delete an attachment that is linked to an order (Supports classic & HPOS)
            $order_id_meta = get_post_meta($attachment->ID, '_oi_banglaqr_order_id', true);
            if ((!empty($attachment->post_parent) && intval($attachment->post_parent) > 0) || !empty($order_id_meta)) {
                delete_post_meta($attachment->ID, '_oi_banglaqr_pending_upload');
                continue;
            }
            wp_delete_attachment($attachment->ID, true);
        }
    }
}
add_action('oi_banglaqr_daily_cleanup', 'oi_banglaqr_cleanup_pending_receipts');
