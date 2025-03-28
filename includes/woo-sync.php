<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce Sync Functionality via Webhook.
 */
add_action( 'tsd_sync_woocommerce', function( $data ) {
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
                $product_id = wc_sync_create_product( $sku, $ticket_label, $woo_api_url, $auth_query );
            }

            if ( $product_id ) {
                $line_items[] = [
                    "product_id" => $product_id,
                    "quantity"   => 1,
                    "price"      => floatval( $ticket['amount'] ?? 0 )
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

    // Send order creation request to WooCommerce REST API
    $order_endpoint = $woo_api_url . "/orders" . $auth_query;
    $ch = curl_init( $order_endpoint );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json"
    ]);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $order_data ) );
    $response  = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    tsd_log_message( "WooCommerce Order Response: HTTP {$http_code}: " . sanitize_text_field( $response ) );
});

/**
 * Helper function: Check if a product exists in WooCommerce by SKU.
 */
if ( ! function_exists( 'wc_sync_get_product_id' ) ) {
    function wc_sync_get_product_id( $sku, $woo_api_url, $auth_query ) {
        $url = $woo_api_url . "/products" . $auth_query . "&sku=" . urlencode( $sku );
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $response  = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        if ( $http_code === 200 ) {
            $products = json_decode( $response, true );
            if ( is_array( $products ) && count( $products ) > 0 ) {
                return $products[0]['id'] ?? false;
            }
        }

        return false;
    }
}

/**
 * Helper function: Create a new product in WooCommerce.
 */
if ( ! function_exists( 'wc_sync_create_product' ) ) {
    function wc_sync_create_product( $sku, $name, $woo_api_url, $auth_query ) {
        $url = $woo_api_url . "/products" . $auth_query;
        $data = [
            "name"          => sanitize_text_field( $name ),
            "type"          => "simple",
            "regular_price" => "0",
            "sku"           => sanitize_text_field( $sku ),
            "status"        => "publish"
        ];
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $data ) );
        $response  = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        tsd_log_message( "Created WooCommerce product SKU {$sku} - HTTP {$http_code}: " . sanitize_text_field( $response ) );

        if ( $http_code === 201 || $http_code === 200 ) {
            $product = json_decode( $response, true );
            return $product['id'] ?? false;
        }

        return false;
    }
}

/**
 * Action Scheduler Integration
 */
if ( class_exists( 'ActionScheduler' ) ) {
    add_action( 'tsd_process_webhook', 'tsd_process_webhook_job' );
    function tsd_process_webhook_job( $data ) {
        if ( get_option( 'tsd_enable_mailchimp', 'yes' ) === 'yes' ) {
            do_action( 'tsd_sync_mailchimp', $data );
        }
        if ( get_option( 'tsd_enable_woocommerce', 'yes' ) === 'yes' ) {
            do_action( 'tsd_sync_woocommerce', $data );
        }
    }
}
