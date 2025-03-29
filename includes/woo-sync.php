<?php
require_once plugin_dir_path(__FILE__) . 'logger.php';

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce Sync Functionality via Webhook.
 */
add_action( 'tsd_sync_woocommerce', function( $data ) {
    if ( isset( $data['dry_run'] ) && $data['dry_run'] === true ) {
        tsd_log_message('🛑 Dry run enabled: WooCommerce sync skipped.');
        return;
    }
    // Retrieve WooCommerce configuration from plugin options
    $woo_api_url = get_option( 'tsd_woo_api_url', site_url( '/wp-json/wc/v3' ) );
    $woo_ck      = get_option( 'tsd_woo_consumer_key' );
    $woo_cs      = get_option( 'tsd_woo_consumer_secret' );

    if ( empty( $woo_ck ) || empty( $woo_cs ) ) {
        tsd_log_message( "WooCommerce credentials are missing.", true );
        return;
    }

    // Build WooCommerce auth query parameters
    $auth_query = "?consumer_key=" . urlencode( $woo_ck ) . "&consumer_secret=" . urlencode( $woo_cs );

    // Extract billing data
    $billing = $data['data']['billing'] ?? [];
    $email   = sanitize_email( $billing['email'] ?? '' );
    if ( empty( $email ) ) {
        tsd_log_message( "Error: Missing email in payload for WooCommerce sync.", true );
        return;
    }
    $first_name = sanitize_text_field( $billing['name']['first'] ?? '' );
    $last_name  = sanitize_text_field( $billing['name']['last'] ?? '' );
    $phone      = sanitize_text_field( $billing['phone'] ?? '' );
    $address    = $billing['address'] ?? [];

    // Map billing info to WooCommerce order billing and shipping fields
    $woo_billing = [
        "first_name" => $first_name,
        "last_name"  => $last_name,
        "address_1"  => $address['street1'] ?? '',
        "address_2"  => "",
        "city"       => $address['city'] ?? '',
        "state"      => $address['state'] ?? '',
        "postcode"   => $address['postalCode'] ?? '',
        "country"    => $address['country'] ?? '',
        "email"      => $email,
        "phone"      => $phone
    ];

    // Create line items from tickets
    $line_items = [];
    if ( ! empty( $data['data']['tickets'] ) ) {
        foreach ( $data['data']['tickets'] as $ticket ) {
            $sku = (string) ( $ticket['ticketKey'] ?? $ticket['id'] );
            $ticket_label = sanitize_text_field( $ticket['ticketLabel'] ?? 'Ticket' );

           // Check if product exists in WooCommerce; if not, create it
           $product_id = wc_sync_get_product_id( $sku, $woo_api_url, $auth_query );
           if ( ! $product_id ) {
               // Format the ticket price as a string with two decimals.
               $ticket_price = number_format( floatval( $ticket['amount'] ?? 0 ), 2, '.', '' );
               $product_id = wc_sync_create_product( $sku, $ticket_label, $woo_api_url, $auth_query, $ticket_price );
           }

            if ( $product_id ) {
                $line_items[] = [
                    "product_id" => $product_id,
                    "quantity"   => 1,
                    "price"      => number_format( floatval( $ticket['amount'] ?? 0 ), 2, '.', '' )

                ];
            }
        }
    }

    // Determine order status based on payload orderStatus
    $order_status_raw = strtolower( $data['data']['orderStatus'] ?? '' );
    $order_status = 'processing';
    if ( $order_status_raw === 'refunded' ) {
        $order_status = 'refunded';
    } elseif ( $order_status_raw === 'canceled' ) {
        $order_status = 'cancelled';
    } elseif ( $order_status_raw === 'pending' ) {
        $order_status = 'pending';
    }

    // Build WooCommerce order payload
    $order_data = [
        "payment_method"       => "ticketspice",
        "payment_method_title" => "TicketSpice",
        "set_paid"             => true,
        "billing"              => $woo_billing,
        "shipping"             => $woo_billing,
        "line_items"           => $line_items,
        "status"               => $order_status,
        "meta_data"            => [
            [
                "key"   => "ticketspice_order",
                "value" => "yes"
            ]
        ]
    ];

    // Send order creation request to WooCommerce REST API using WP HTTP API
    $order_endpoint = $woo_api_url . "/orders" . $auth_query;
    $args = [
        'method'  => 'POST',
        'headers' => [
            'Content-Type' => 'application/json'
        ],
        'body'    => wp_json_encode( $order_data ),
        'timeout' => 15,
    ];
    $response = wp_remote_post( $order_endpoint, $args );
    if ( is_wp_error( $response ) ) {
        tsd_log_message( "HTTP API Error in WooCommerce order creation: " . $response->get_error_message(), true );
    } else {
        $http_code = wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );
        tsd_log_message( "WooCommerce Order Response: HTTP {$http_code}: " . sanitize_text_field( $body_response ) );
    }
});

/**
 * Helper function: Check if a product exists in WooCommerce by SKU.
 * ✅ Updated to use wp_remote_get.
 */
if ( ! function_exists( 'wc_sync_get_product_id' ) ) {
    function wc_sync_get_product_id( $sku, $woo_api_url, $auth_query ) {
        $url = $woo_api_url . "/products" . $auth_query . "&sku=" . urlencode( $sku );
        $response = wp_remote_get( $url, ['timeout' => 15] );
        if ( is_wp_error( $response ) ) {
            tsd_log_message("HTTP API Error in wc_sync_get_product_id: " . $response->get_error_message(), true);
            return false;
        }
        $http_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $http_code === 200 ) {
            $products = json_decode( $body, true );
            if ( is_array( $products ) && count( $products ) > 0 ) {
                return $products[0]['id'] ?? false;
            }
        }
        return false;
    }
}

/**
 * Helper function: Create a new product in WooCommerce.
 * ✅ Updated to use wp_remote_post.
 */
if ( ! function_exists( 'wc_sync_create_product' ) ) {
    function wc_sync_create_product( $sku, $name, $woo_api_url, $auth_query, $price = "0" ) {
        $url = $woo_api_url . "/products" . $auth_query;
        $data = [
            "name"          => sanitize_text_field( $name ),
            "type"          => "simple",
            // Use the provided price instead of "0".
            "regular_price" => $price,
            "sku"           => sanitize_text_field( $sku ),
            "status"        => "publish"
        ];
        $args = [
            'method'  => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $data ),
            'timeout' => 15,
        ];
        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            tsd_log_message("HTTP API Error in wc_sync_create_product: " . $response->get_error_message(), true);
            return false;
        }
        $http_code = wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );
        tsd_log_message( "Created WooCommerce product SKU {$sku} - HTTP {$http_code}: " . sanitize_text_field( $body_response ) );
        if ( $http_code === 201 || $http_code === 200 ) {
            $product = json_decode( $body_response, true );
            return $product['id'] ?? false;
        }
        return false;
    }
}

/**
 * Legacy WooCommerce request function.
 * ✅ Updated to use WP HTTP API and tsd_log_message().
 */
function woo_request( $method, $endpoint, $body = null ) {
    $woocommerce_api_url = 'https://www.centeredpresents.com/wp-json/wc/v3';
    $key    = defined('WOOCOMMERCE_CONSUMER_KEY') ? urlencode(WOOCOMMERCE_CONSUMER_KEY) : '';
    $secret = defined('WOOCOMMERCE_CONSUMER_SECRET') ? urlencode(WOOCOMMERCE_CONSUMER_SECRET) : '';

    $url = strpos($endpoint, '?') !== false
        ? "$woocommerce_api_url/$endpoint&consumer_key=$key&consumer_secret=$secret"
        : "$woocommerce_api_url/$endpoint?consumer_key=$key&consumer_secret=$secret";

    tsd_log_message( "WooCommerce Request: $method $url" );

    $args = [
        'method'  => strtoupper($method),
        'headers' => [ 'Content-Type' => 'application/json' ],
        'timeout' => 15,
    ];
    if ( $body ) {
        $args['body'] = wp_json_encode( $body );
    }
    $response = wp_remote_request($url, $args);
    if ( is_wp_error($response) ) {
        tsd_log_message( "HTTP API Error in woo_request: " . $response->get_error_message(), true );
    } else {
        $http_code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);
        if ( $http_code >= 400 ) {
            tsd_log_message( "WooCommerce $method $endpoint failed: HTTP $http_code - $body_response", true );
        } else {
            tsd_log_message( "WooCommerce $method $endpoint success: HTTP $http_code - $body_response" );
        }
    }
    return json_decode( wp_remote_retrieve_body($response), true );
}

/* -----------------------------------------------------------------------------
   New Code: Action Scheduler Callback Registration
   -----------------------------------------------------------------------------
   The following code ensures that the scheduled action 'tsd_process_webhook' has
   a registered callback. This prevents the error:
   "Scheduled action for tsd_process_webhook will not be executed as no callbacks are registered."
----------------------------------------------------------------------------- */

// Define the callback function if not already defined.
if ( ! function_exists( 'tsd_process_webhook_job' ) ) {
    function tsd_process_webhook_job( $data ) {
        if ( get_option( 'tsd_enable_mailchimp', 'yes' ) === 'yes' ) {
            do_action( 'tsd_sync_mailchimp', $data );
        }
        if ( get_option( 'tsd_enable_woocommerce', 'yes' ) === 'yes' ) {
            do_action( 'tsd_sync_woocommerce', $data );
        }
    }
}

// Ensure the callback is registered on every request via the 'init' hook.
add_action( 'init', function() {
    if ( class_exists( 'ActionScheduler' ) ) {
        add_action( 'tsd_process_webhook', 'tsd_process_webhook_job', 10, 1 );
        // tsd_log_message( "Registered tsd_process_webhook callback via Action Scheduler." );
    }
});
