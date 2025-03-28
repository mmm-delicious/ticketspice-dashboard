<?php
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
 * Catch the webhook request on template_redirect
 */
add_action( 'template_redirect', function() {
    if ( get_query_var( 'ticketspice_webhook' ) ) {
        do_action( 'ticketspice_mailchimp_webhook' );
        exit;
    }
});

/**
 * Webhook handler function — triggers sync if enabled
 */
add_action( 'ticketspice_mailchimp_webhook', function() {
    $input = file_get_contents( 'php://input' );
    if ( ! $input ) {
        tsd_log_message( 'Webhook received empty input.' );
        status_header( 400 );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo wp_json_encode( [ 'error' => 'No payload received' ] );
        exit;
    }

    $data = json_decode( $input, true );

    tsd_log_message( 'Webhook received: ' . wp_json_encode( $data ) );

    if ( empty( $data['data'] ) ) {
        tsd_log_message( 'Invalid or empty webhook payload.' );
        status_header( 400 );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo wp_json_encode( [ 'error' => 'Invalid payload' ] );
        exit;
    }

    // Conditionally trigger Mailchimp and WooCommerce sync
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
});
