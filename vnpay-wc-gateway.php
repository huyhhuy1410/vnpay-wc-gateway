<?php

/**
 * Plugin Name: VNPAY Payment Gateway for WooCommerce
 * Description: Accept payments via VNPAY in your WooCommerce store
 * Version: 1.0.0
 * Author:      Huy Vo
 * Text Domain: vnpay-wc-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 4.0
 * WC tested up to: 7.8
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('VNPAY_WOO_VERSION', '1.0.0');
define('VNPAY_WOO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VNPAY_WOO_PLUGIN_DIR', plugin_dir_path(__FILE__));



/**
 * Initialize the plugin
 */
function vnpay_woo_init()
{
    // Make sure WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return;
    }
    // Include required files
    require_once VNPAY_WOO_PLUGIN_DIR . 'includes/class-vnpay-gateway.php';
    // Include logger
    require_once VNPAY_WOO_PLUGIN_DIR . 'includes/class-vnpay-logger.php';
    // Register payment gateway
    add_filter('woocommerce_payment_gateways', 'vnpay_woo_add_gateway');


    // Load plugin text domain
    load_plugin_textdomain('vnpay-wc-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'vnpay_woo_init');

/**
 * Declare HPOS (High-Performance Order Storage) compatibility
 * This silences the WooCommerce warning and confirms the gateway
 * uses WooCommerce CRUD (no direct wp_posts/wp_postmeta access).
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Add VNPAY Gateway to WooCommerce
 *
 * @param array $gateways WooCommerce payment gateways
 * @return array Payment gateways with VNPAY added
 */
function vnpay_woo_add_gateway($gateways)
{
    $gateways[] = 'WC_VNPAY_Gateway';
    return $gateways;
}

/**
 * Register activation hook
 */
function vnpay_woo_activate()
{
    global $wpdb;

    // Create logs table
    $table_name = $wpdb->prefix . 'vnpay_logs';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        flow varchar(16) NOT NULL,
        order_id bigint(20) unsigned NULL,
        transaction_no varchar(64) NULL,
        amount bigint(20) NULL,
        ip varchar(45) NULL,
        url text NULL,
        status varchar(32) NULL,
        code varchar(32) NULL,
        message text NULL,
        payload_request longtext NULL,
        payload_response longtext NULL,
        signature_valid tinyint(1) NULL,
        extra longtext NULL,
        PRIMARY KEY  (id),
        KEY created_at_idx (created_at),
        KEY flow_order_idx (flow, order_id)
    ) $charset_collate;";
    dbDelta($sql);

    // Schedule daily retention job (keeps at least 60 days)
    if (!wp_next_scheduled('vnpay_log_retention')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'vnpay_log_retention');
    }

    // Flush rewrites
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'vnpay_woo_activate');

/**
 * Deactivation: unschedule retention event
 */
function vnpay_woo_deactivate()
{
    $timestamp = wp_next_scheduled('vnpay_log_retention');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'vnpay_log_retention');
    }
}
register_deactivation_hook(__FILE__, 'vnpay_woo_deactivate');

/**
 * Retention callback: purge logs older than configured days (min 60)
 */
add_action('vnpay_log_retention', function () {
    global $wpdb;
    $table = $wpdb->prefix . 'vnpay_logs';
    $days = (int) get_option('vnpay_log_retention_days', 60);
    if ($days < 60) {
        $days = 60;
    }
    // Use prepared query to delete old rows
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE created_at < (NOW() - INTERVAL %d DAY)", $days));
});

/**
 * Optional: settings defaults
 */
add_action('admin_init', function () {
    // Ensure default options exist
    if (get_option('vnpay_logging_enabled') === false) {
        update_option('vnpay_logging_enabled', 1);
    }
    if (get_option('vnpay_log_retention_days') === false) {
        update_option('vnpay_log_retention_days', 60);
    }
});
