<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package BanglaQR_Gateway_By_Oi
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete the plugin options and transients
delete_option('woocommerce_oi_banglaqr_settings');
delete_transient('oi_banglaqr_pending_count');

// Clear scheduled cleanup cron
wp_clear_scheduled_hook('oi_banglaqr_daily_cleanup');