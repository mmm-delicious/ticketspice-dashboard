<?php
/**
 * Plugin Name: TicketSpice Dashboard
 * Description: Integration dashboard for TicketSpice, WooCommerce, and Mailchimp with logs, webhooks, and visual reports.
 * Version: 1.0.3
 * Author: MMM Delicious 🍰
 * Developer: Mark McDonnell
 * Text Domain: ticketspice-dashboard
 */

// ✅ Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants for directory and URL paths.
define( 'TSD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include core functionality files.
require_once TSD_PLUGIN_DIR . 'includes/logger.php';
require_once TSD_PLUGIN_DIR . 'includes/webhook-handler.php';
require_once TSD_PLUGIN_DIR . 'includes/mailchimp-sync.php';
require_once TSD_PLUGIN_DIR . 'includes/woo-sync.php';

// Admin-only functionality: include admin files and enqueue admin assets.
if ( is_admin() ) {
    require_once TSD_PLUGIN_DIR . 'admin/chart-data.php';
    require_once TSD_PLUGIN_DIR . 'admin/dashboard.php';
    require_once TSD_PLUGIN_DIR . 'admin/settings.php';
    require_once TSD_PLUGIN_DIR . 'admin/tester.php';

    // Enqueue admin assets only on our plugin page.
    add_action( 'admin_enqueue_scripts', function( $hook ) {
        // ✅ Only enqueue assets on the plugin's top-level admin page.
        if ( $hook !== 'toplevel_page_ticketspice_dashboard' ) {
            return;
        }
        wp_enqueue_style( 'tsd-admin-css', TSD_PLUGIN_URL . 'assets/css/admin.css' );
        wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null );
        wp_enqueue_script( 'tsd-charts-js', TSD_PLUGIN_URL . 'assets/js/charts.js', ['chartjs'], '1.0.0', true );
    });
}

/**
 * Webhook Handler for TicketSpice data.
 *
 * Modified to accept the decoded payload as a parameter so that the input
 * is read only once from the HTTP request. This prevents duplicate reading of php://input.
 */
function tsd_handle_webhook( $data = null ) {
    // ✅ If $data is not passed, read and decode the input.
    if ( is_null( $data ) ) {
        $input = file_get_contents( "php://input" );
        $data  = json_decode( $input, true );
    }
    
    // Log the received data.
    tsd_log_message( "Received TicketSpice Webhook: " . wp_json_encode( $data ) );

    // Validate that we have the expected payload.
    if ( empty( $data['data'] ) ) {
        tsd_log_message( "Error: Invalid webhook payload." );
        return;
    }

    // Delegate processing to the sync routines.
    do_action( 'tsd_process_webhook_data', $data );
}

// ✅ Update: Modify hook callback to accept the data from the webhook endpoint.
add_action( 'ticketspice_mailchimp_webhook', 'tsd_handle_webhook', 10, 1 );

/**
 * Register a custom webhook URL route for receiving TicketSpice webhooks.
 */
add_action( 'init', function() {
    add_rewrite_rule( '^ticketspice-webhook/?$', 'index.php?ticketspice_webhook=1', 'top' );
});

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ticketspice_webhook';
    return $vars;
});

// ✅ Update: In template_redirect, read the input once, decode it, and pass it to the hook.
add_action( 'template_redirect', function() {
    if ( get_query_var( 'ticketspice_webhook' ) ) {
        $input = file_get_contents( "php://input" );
        $data  = json_decode( $input, true );
        // Log the initial webhook receipt.
        tsd_log_message( "Webhook endpoint triggered with data: " . wp_json_encode( $data ) );
        // Pass the decoded data to the webhook hook.
        do_action( 'ticketspice_mailchimp_webhook', $data );
        status_header(200);
        echo 'Webhook received successfully.';
        exit;
    }
});

// Process the webhook data by syncing with Mailchimp and WooCommerce.
add_action( 'tsd_process_webhook_data', 'tsd_process_ticketspice_data' );

function tsd_process_ticketspice_data( $data ) {
    // ✅ Changed from "log_message" to "tsd_log_message" to use our plugin's logging function.
    if ( ! is_array( $data ) ) {
        tsd_log_message( 'Invalid payload received in tsd_process_ticketspice_data', true );
        return;
    }

    // Sync with Mailchimp if the function exists.
    if ( function_exists( 'sync_with_mailchimp' ) ) {
        sync_with_mailchimp( $data );
    } else {
        tsd_log_message( 'sync_with_mailchimp() not found', true );
    }

    // Sync with WooCommerce if the function exists.
    if ( function_exists( 'sync_with_woocommerce' ) ) {
        sync_with_woocommerce( $data );
    } else {
        tsd_log_message( 'sync_with_woocommerce() not found', true );
    }
}

// -----------------------------------------------------------------------------
// SCHEDULED EVENT: Automatically Clear Log File Daily
// -----------------------------------------------------------------------------

// Function to schedule the daily log clear event on plugin activation.
function tsd_activate_plugin() {
    if ( ! wp_next_scheduled( 'tsd_daily_clear_log' ) ) {
        wp_schedule_event( time(), 'daily', 'tsd_daily_clear_log' );
    }
}
register_activation_hook( __FILE__, 'tsd_activate_plugin' );

// Function to clear the scheduled event on plugin deactivation.
function tsd_deactivate_plugin() {
    wp_clear_scheduled_hook( 'tsd_daily_clear_log' );
}
register_deactivation_hook( __FILE__, 'tsd_deactivate_plugin' );

// Hook our log clear function to the scheduled event.
add_action( 'tsd_daily_clear_log', 'tsd_clear_log_file' );
