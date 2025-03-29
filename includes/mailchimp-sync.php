<?php
// Include logger functionality.
require_once plugin_dir_path(__FILE__) . 'logger.php';

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mailchimp Sync Functionality via Webhook.
 */
add_action( 'tsd_sync_mailchimp', function( $data ) {
    // Skip if "dry_run" is enabled.
    if ( isset( $data['dry_run'] ) && $data['dry_run'] === true ) {
        tsd_log_message( '🛑 Dry run enabled: Mailchimp sync skipped.' );
        return;
    }

    // Log the received payload.
    tsd_log_message( "Received TicketSpice Webhook: " . wp_json_encode( $data ) );

    // Validate payload.
    if ( empty( $data['data'] ) ) {
        tsd_log_message( "Error: Invalid payload received.", true );
        return;
    }

    // Retrieve Mailchimp configuration from plugin options.
    $api_key       = get_option( 'tsd_mailchimp_api_key' );
    $server_prefix = get_option( 'tsd_mailchimp_server_prefix' );
    $store_id      = get_option( 'tsd_mailchimp_store_id' );
    $list_id       = get_option( 'tsd_mailchimp_list_id' );

    if ( empty( $api_key ) ) {
        tsd_log_message( "Mailchimp API key is missing!", true );
        return;
    }

    // Process billing and order data.
    $billing    = $data['data']['billing'] ?? [];
    $email      = sanitize_email( $billing['email'] ?? '' );
    if ( empty( $email ) ) {
        tsd_log_message( "Error: Missing or invalid email in payload.", true );
        return;
    }

    $first_name = sanitize_text_field( $billing['name']['first'] ?? '' );
    $last_name  = sanitize_text_field( $billing['name']['last'] ?? '' );
    $phone      = $billing['phone'] ?? ( $billing['card']['phone'] ?? '' );
    if ( ! empty( $phone ) ) {
        $phone = preg_replace( '/\D+/', '', $phone );
        if ( strlen( $phone ) == 10 ) {
            $phone = "+1" . $phone;
        } elseif ( strlen( $phone ) > 10 && substr( $phone, 0, 1 ) !== '+' ) {
            $phone = "+" . $phone;
        }
    }

    $address     = $billing['address'] ?? [];
    $customer_id = md5( strtolower( $email ) );

    // Determine financial status.
    switch ( strtolower( $data['data']['orderStatus'] ?? '' ) ) {
        case 'refunded':
            $financial_status = 'refunded';
            break;
        case 'canceled':
        case 'cancelled':
            $financial_status = 'canceled';
            break;
        case 'pending':
            $financial_status = 'pending';
            break;
        default:
            $financial_status = 'completed';
    }

    // Calculate totals
    // ✅ Format totals as strings with two decimals
    $total_amount   = number_format( floatval( $data['data']['total'] ?? 0 ), 2, '.', '' );
    $tickets_total  = 0;
    if ( ! empty( $data['data']['tickets'] ) ) {
       foreach ( $data['data']['tickets'] as $ticket ) {
           $tickets_total += floatval( $ticket['amount'] ?? 0 );
       }
    }
    // Ensure discount_total is also formatted as a string.
    $discount_total = number_format( max( 0, $tickets_total - floatval( $data['data']['total'] ?? 0 ) ), 2, '.', '' );

    // Extract coupon code if available.
    $coupon_code = '';
    if ( ! empty( $data['data']['registrants'][0]['data'] ) ) {
        foreach ( $data['data']['registrants'][0]['data'] as $entry ) {
            if ( ( $entry['key'] ?? '' ) === 'couponCode' && ! empty( $entry['value'] ) ) {
                $coupon_code = sanitize_text_field( $entry['value'] );
                break;
            }
        }
    }

    // Prepare subscriber data.
    $subscriber_data = [
        "email_address"  => $email,
        "status_if_new"  => "subscribed",
        "merge_fields"   => [
            "FNAME"   => $first_name,
            "LNAME"   => $last_name,
            "PHONE"   => $phone,
            "ADDRESS" => [
                "addr1"   => $address['street1'] ?? '',
                "addr2"   => '',
                "city"    => $address['city'] ?? '',
                "state"   => $address['state'] ?? '',
                "zip"     => $address['postalCode'] ?? '',
                "country" => $address['country'] ?? 'US'
            ]
        ]
    ];

    // Subscriber update via Mailchimp API.
    $audience_url = "https://{$server_prefix}.api.mailchimp.com/3.0/lists/{$list_id}/members/" . md5( strtolower( $email ) );
    $response = tsd_mailchimp_api_request( $audience_url, 'PUT', $api_key, $subscriber_data );
    tsd_log_message( "Mailchimp Subscriber Response: " . $response );

    // Customer sync.
    $customer_url = "https://{$server_prefix}.api.mailchimp.com/3.0/ecommerce/stores/{$store_id}/customers/{$customer_id}";
    $customer_data = [
        "id"             => $customer_id,
        "email_address"  => $email,
        "opt_in_status"  => true,
        "first_name"     => $first_name,
        "last_name"      => $last_name,
        "address"        => [
            "address1"    => $address['street1'] ?? '',
            "address2"    => "",
            "city"        => $address['city'] ?? '',
            "province"    => $address['state'] ?? '',
            "postal_code" => $address['postalCode'] ?? '',
            "country"     => $address['country'] ?? 'US'
        ]
    ];
    $response = tsd_mailchimp_api_request( $customer_url, 'PUT', $api_key, $customer_data );
    tsd_log_message( "Mailchimp Customer Response: " . $response );

    // Add tags to subscriber.
    $tags_url = "https://{$server_prefix}.api.mailchimp.com/3.0/lists/{$list_id}/members/" . md5( strtolower( $email ) ) . "/tags";
    $tags_data = [ "tags" => [ [ "name" => "TicketSpice", "status" => "active" ] ] ];
    $response = tsd_mailchimp_api_request( $tags_url, 'POST', $api_key, $tags_data );
    tsd_log_message( "Mailchimp Tag Response: " . $response );

    // --- Ensure Mailchimp Product Exists ---
    // Build line items for the order from ticket data.
    $lines = [];
    if ( ! empty( $data['data']['tickets'] ) ) {
       // Assume one ticket per order.
       $ticket = $data['data']['tickets'][0];
       $ticket_label = sanitize_text_field( $ticket['ticketLabel'] ?? 'Ticket' );
       $ticket_price = number_format( floatval( $ticket['amount'] ?? 0 ), 2, '.', '' );
       $quantity = 1;
       // Use a sanitized title as product_id.
       $product_id = sanitize_title( $ticket_label );
       
       // Ensure the product exists in Mailchimp.
       // This will create or update the product in your Mailchimp ecommerce store.
       $product_endpoint = "https://{$server_prefix}.api.mailchimp.com/3.0/ecommerce/stores/{$store_id}/products/{$product_id}";
       $product_data = [
           'id'      => $product_id,
           'title'   => $ticket_label,
           'variants' => [
               [
                   'id'    => $product_id,
                   'title' => $ticket_label,
                   'price' => $ticket_price
               ]
           ]
       ];
       $product_response = tsd_mailchimp_api_request( $product_endpoint, 'PUT', $api_key, $product_data );
       tsd_log_message("Mailchimp Product Response: " . $product_response);
       
       // Build the line item including the required product_variant_id field.
       $lines[] = [
           'id'                 => $product_id,
           'product_id'         => $product_id,
           'product_variant_id' => $product_id, // Added required field.
           'product_title'      => $ticket_label,
           'quantity'           => $quantity,
           'price'              => $ticket_price
       ];
    }

    // --- Prepare Order Data ---
    $order_id   = (string) ( $data['data']['transactionId'] ?? uniqid( 'order_' ) );
    $order_date = $data['data']['registrationTimestamp'] ?? date( "Y-m-d\TH:i:s+00:00" );
    $order_currency = $data['data']['currency'] ?? 'USD';
    $order_data = [
       "id"                  => $order_id,
       "customer"            => [
           "id"            => $customer_id,
           "email_address" => $email,
           "opt_in_status" => true
       ],
       "currency_code"       => $order_currency,
       "order_total"         => $total_amount,
       "discount_total"      => $discount_total,
       "financial_status"    => $financial_status,
       "processed_at_foreign"=> $order_date,
       "lines"               => $lines // Now contains at least one valid line item.
    ];

    // Final Order Push.
    $order_url = "https://{$server_prefix}.api.mailchimp.com/3.0/ecommerce/stores/{$store_id}/orders/{$order_id}";
    $response = tsd_mailchimp_api_request( $order_url, 'PUT', $api_key, $order_data );
    tsd_log_message( "Mailchimp Order Response: " . $response );

});

/**
 * Mailchimp API wrapper using WP HTTP API.
 *
 * ✅ Replaced cURL with wp_remote_request.
 */
function tsd_mailchimp_api_request( $url, $method, $api_key, $body ) {
    $args = [
        'method'  => strtoupper( $method ),
        'headers' => [
            'Authorization' => "apikey {$api_key}",
            'Content-Type'  => 'application/json'
        ],
        'body'    => wp_json_encode( $body ),
        'timeout' => 15,
    ];
    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        tsd_log_message( "HTTP API Error: " . $response->get_error_message(), true );
        return "HTTP error: " . $response->get_error_message();
    } else {
        $http_code = wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );
        return "HTTP {$http_code}: " . sanitize_text_field( $body_response );
    }
}

/**
 * Legacy Mailchimp request function.
 *
 * ✅ This function is now deprecated in favor of tsd_mailchimp_api_request().
 * You can safely remove it if you are no longer calling it.
 */
function mailchimp_request($method, $endpoint, $api_key, $data = null) {
    // Deprecated. Using the new API wrapper.
    $server_prefix = substr($api_key, strpos($api_key, '-') + 1);
    $url = "https://$server_prefix.api.mailchimp.com/3.0/$endpoint";
    $args = [
        'method'  => strtoupper($method),
        'headers' => [
            'Authorization' => "apikey {$api_key}",
            'Content-Type'  => 'application/json'
        ],
        'body'    => $data ? wp_json_encode( $data ) : '',
        'timeout' => 15,
    ];
    $response = wp_remote_request( $url, $args );
    if ( is_wp_error( $response ) ) {
        tsd_log_message( "HTTP API Error in legacy mailchimp_request: " . $response->get_error_message(), true );
        return null;
    }
    $http_code = wp_remote_retrieve_response_code( $response );
    $body_response = wp_remote_retrieve_body( $response );
    tsd_log_message( "Legacy Mailchimp response [$http_code]: " . $body_response );
    return json_decode( $body_response, true );
}
