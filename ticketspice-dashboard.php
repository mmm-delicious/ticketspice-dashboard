<?php
/**
 * Plugin Name: TicketSpice Dashboard
 * Description: Integration dashboard for TicketSpice, WooCommerce, and Mailchimp with logs, webhooks, and visual reports.
 * Version: 1.0.1
 * Author: MMM Delicious 🍰
 * Developer: Mark McDonnell
 * Text Domain: ticketspice-dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'TSD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include core functionality
require_once TSD_PLUGIN_DIR . 'includes/logger.php';
require_once TSD_PLUGIN_DIR . 'includes/webhook-handler.php';
require_once TSD_PLUGIN_DIR . 'includes/mailchimp-sync.php';
require_once TSD_PLUGIN_DIR . 'includes/woo-sync.php';

// Admin-only functionality
if ( is_admin() ) {
    require_once TSD_PLUGIN_DIR . 'admin/chart-data.php';
    require_once TSD_PLUGIN_DIR . 'admin/dashboard.php';
    require_once TSD_PLUGIN_DIR . 'admin/settings.php';
    require_once TSD_PLUGIN_DIR . 'admin/tester.php';

    // Load admin assets only on plugin page
    add_action( 'admin_enqueue_scripts', function( $hook ) {
        if ( $hook !== 'toplevel_page_ticketspice_dashboard' ) {
            return;
        }
        wp_enqueue_style( 'tsd-admin-css', TSD_PLUGIN_URL . 'assets/css/admin.css' );
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null );
        wp_enqueue_script( 'tsd-charts-js', TSD_PLUGIN_URL . 'assets/js/charts.js', ['chartjs'], '1.0.0', true );
    });
}

/**
 * Webhook Handler (for 'ticketspice_mailchimp_webhook' hook)
 */
function tsd_handle_webhook() {
    $logging_enabled   = get_option( 'tsd_logging_enabled' ) === 'yes';
    $enable_mailchimp  = get_option( 'tsd_enable_mailchimp' ) === 'yes';
    $enable_woo        = get_option( 'tsd_enable_woocommerce' ) === 'yes';

    $mailchimp_api_key      = get_option( 'tsd_mailchimp_api_key' );
    $mailchimp_server       = get_option( 'tsd_mailchimp_server_prefix' );
    $mailchimp_store_id     = get_option( 'tsd_mailchimp_store_id' );
    $mailchimp_list_id      = get_option( 'tsd_mailchimp_list_id' );

    $woo_consumer_key       = get_option( 'tsd_woo_consumer_key' );
    $woo_consumer_secret    = get_option( 'tsd_woo_consumer_secret' );
    $woocommerce_api_url    = get_option( 'tsd_woo_api_url', site_url( '/wp-json/wc/v3' ) );

    // Logging helper
    if ( ! function_exists( 'tsd_log' ) ) {
        function tsd_log( $msg ) {
            if ( get_option( 'tsd_logging_enabled' ) === 'yes' ) {
                $upload_dir = wp_upload_dir();
                $log_file = trailingslashit( $upload_dir['basedir'] ) . 'webhook_log.txt';
                file_put_contents( $log_file, date( "Y-m-d H:i:s" ) . " - " . $msg . "\n", FILE_APPEND );
                error_log( $msg );
            }
        }
    }

    // Precheck for enabled features
    if ( empty( $mailchimp_api_key ) && $enable_mailchimp ) {
        tsd_log( "Mailchimp is enabled but API key is missing." );
        return;
    }

    if ( ( empty( $woo_consumer_key ) || empty( $woo_consumer_secret ) ) && $enable_woo ) {
        tsd_log( "WooCommerce is enabled but credentials are missing." );
        return;
    }

    // Handle Webhook Payload
    $input = file_get_contents( "php://input" );
    $data  = json_decode( $input, true );
    tsd_log( "Received TicketSpice Webhook: " . json_encode( $data ) );

    if ( empty( $data['data'] ) ) {
        tsd_log( "Error: Invalid webhook payload." );
        return;
    }

    // Delegate handling to sync script
    do_action( 'tsd_process_webhook_data', $data );
}

// Hook the handler into custom event
add_action( 'ticketspice_mailchimp_webhook', 'tsd_handle_webhook' );

/**
 * Register custom webhook URL route
 */
add_action( 'init', function() {
    add_rewrite_rule( '^ticketspice-webhook/?$', 'index.php?ticketspice_webhook=1', 'top' );
});

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ticketspice_webhook';
    return $vars;
});

add_action( 'template_redirect', function() {
    if ( get_query_var( 'ticketspice_webhook' ) ) {
        do_action( 'ticketspice_mailchimp_webhook' );
        http_response_code(200);
        echo 'Webhook received successfully.';
        exit;
    }
});
