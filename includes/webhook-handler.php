<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the custom endpoint: /ticketspice-webhook
 */
add_action( 'init', function() {
    add_rewrite_rule( '^ticketspice-webhook/?$', 'index.php?ticketspice_webhook=1', 'top' );
});

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'ticketspice_webhook';
    return $vars;
});

/**
 * Catch the webhook request on template_redirect.
 *
 * ✅ Updated to not re-read the input since it is already handled in the main plugin file.
 */
add_action( 'template_redirect', function() {
    if ( get_query_var( 'ticketspice_webhook' ) ) {
        // Read the input once and decode it.
        $input = file_get_contents( 'php://input' );
        $data  = json_decode( $input, true );
        // Log the webhook data.
        tsd_log_message( 'Webhook received: ' . wp_json_encode( $data ) );
        // Pass the decoded data to the webhook hook.
        do_action( 'ticketspice_mailchimp_webhook', $data );
        status_header(200);
        echo 'Webhook received successfully.';
        exit;
    }
});

/**
 * Webhook handler function that triggers sync actions.
 *
 * ✅ Updated to accept the webhook payload as a parameter rather than re-reading php://input.
 */
add_action( 'ticketspice_mailchimp_webhook', function( $data = null ) {
    // If $data is not provided, attempt to read it.
    if ( is_null( $data ) ) {
        $input = file_get_contents( 'php://input' );
        $data  = json_decode( $input, true );
    }

    tsd_log_message( 'Webhook data: ' . wp_json_encode( $data ) );

    if ( empty( $data['data'] ) ) {
        tsd_log_message( 'Invalid or empty webhook payload.' );
        status_header( 400 );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo wp_json_encode( [ 'error' => 'Invalid payload' ] );
        exit;
    }

    // ✅ If Action Scheduler is available, enqueue async actions for better performance.
    if ( class_exists( 'ActionScheduler' ) ) {
        if ( function_exists( 'as_enqueue_async_action' ) ) {
            as_enqueue_async_action( 'tsd_process_webhook', [ $data ] );
        } else {
            if ( get_option( 'tsd_enable_mailchimp', 'yes' ) === 'yes' ) {
                do_action( 'tsd_sync_mailchimp', $data );
            }
            if ( get_option( 'tsd_enable_woocommerce', 'yes' ) === 'yes' ) {
                do_action( 'tsd_sync_woocommerce', $data );
            }
        }
    } else {
        if ( get_option( 'tsd_enable_mailchimp', 'yes' ) === 'yes' ) {
            do_action( 'tsd_sync_mailchimp', $data );
        }
        if ( get_option( 'tsd_enable_woocommerce', 'yes' ) === 'yes' ) {
            do_action( 'tsd_sync_woocommerce', $data );
        }
    }

    status_header( 200 );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo '✅ Webhook processed.';
    exit;
}, 10, 1 );

