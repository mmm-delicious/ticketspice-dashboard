<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Mailchimp Sync Functionality via Webhook.
 */
add_action( 'tsd_sync_mailchimp', function( $data ) {
    // Log the received payload
    tsd_log_message( "Received TicketSpice Webhook: " . wp_json_encode( $data ) );

    // Validate payload
    if ( empty( $data['data'] ) ) {
        tsd_log_message( "Error: Invalid payload received.", true );
        return;
    }

    // Retrieve Mailchimp configuration from plugin options
    $api_key       = get_option( 'tsd_mailchimp_api_key' );
    $server_prefix = get_option( 'tsd_mailchimp_server_prefix' );
    $store_id      = get_option( 'tsd_mailchimp_store_id' );
    $list_id       = get_option( 'tsd_mailchimp_list_id' );

    if ( empty( $api_key ) ) {
        tsd_log_message( "Mailchimp API key is missing!", true );
        return;
    }

    // Process billing and order data
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

    // Determine financial status
    switch ( strtolower( $data['data']['orderStatus'] ?? '' ) ) {
        case 'refunded':
            $financial_status = 'refunded';
            break;
        case 'canceled':
            $financial_status = 'canceled';
            break;
        case 'pending':
            $financial_status = 'pending';
            break;
        default:
            $financial_status = 'completed';
    }

    // Calculate totals
    $total_amount   = floatval( $data['data']['total'] ?? 0 );
    $tickets_total  = 0;
    if ( ! empty( $data['data']['tickets'] ) ) {
        foreach ( $data['data']['tickets'] as $ticket ) {
            $tickets_total += floatval( $ticket['amount'] ?? 0 );
        }
    }
    $discount_total = max( 0, $tickets_total - $total_amount );

    // Extract coupon code if available
    $coupon_code = '';
    if ( ! empty( $data['data']['registrants'][0]['data'] ) ) {
        foreach ( $data['data']['registrants'][0]['data'] as $entry ) {
            if ( ( $entry['key'] ?? '' ) === 'couponCode' && ! empty( $entry['value'] ) ) {
                $coupon_code = sanitize_text_field( $entry['value'] );
                break;
            }
        }
    }

    // Subscriber data
    $subscriber_data = [
        "email_address"  => $email,
        "status_if_new"  => "subscribed",
        "merge_fields"   => [
            "FNAME"   => $first_name,
            "LNAME"   => $last_name,
            "PHONE"   => $phone,
            "ADDRESS" => [
                "addr1" => $address['street1'] ?? '',
                "addr2" => '',
                "city"  => $address['city'] ?? '',
                "state" => $address['state'] ?? '',
                "zip"   => $address['postalCode'] ?? '',
                "country" => $address['country'] ?? 'US'
            ]
        ]
    ];

    $audience_url = "https://{$server_prefix}.api.mailchimp.com/3.0/lists/{$list_id}/members/" . md5( strtolower( $email ) );
    $response = tsd_mailchimp_api_request( $audience_url, 'PUT', $api_key, $subscriber_data );
    tsd_log_message( "Mailchimp Subscriber Response: " . $response );

    // Customer sync
    $customer_url = "https://{$server_prefix}.api.mailchimp.com/3.0/ecommerce/stores/{$store_id}/customers/{$customer_id}";
    $customer_data = [
        "id"             => $customer_id,
        "email_address"  => $email,
        "opt_in_status"  => true,
        "first_name"     => $first_name,
        "last_name"      => $last_name,
        "address"        => [
            "address1"   => $address['street1'] ?? '',
            "address2"   => "",
            "city"       => $address['city'] ?? '',
            "province"   => $address['state'] ?? '',
            "postal_code"=> $address['postalCode'] ?? '',
            "country"    => $address['country'] ?? 'US'
        ]
    ];
    $response = tsd_mailchimp_api_request( $customer_url, 'PUT', $api_key, $customer_data );
    tsd_log_message( "Mailchimp Customer Response: " . $response );

    // Add tags
    $tags_url = "https://{$server_prefix}.api.mailchimp.com/3.0/lists/{$list_id}/members/" . md5( strtolower( $email ) ) . "/tags";
    $tags_data = [ "tags" => [ [ "name" => "TicketSpice", "status" => "active" ] ] ];
    $response = tsd_mailchimp_api_request( $tags_url, 'POST', $api_key, $tags_data );
    tsd_log_message( "Mailchimp Tag Response: " . $response );

    // Prepare order
    $order_id   = (string) ( $data['data']['transactionId'] ?? uniqid( 'order_' ) );
    $order_date = $data['data']['registrationTimestamp'] ?? date( "Y-m-d\TH:i:s+00:00" );
    $order_currency = $data['data']['currency'] ?? 'USD';
    $order_data = [
        "id"               => $order_id,
        "customer"         => [
            "id"            => $customer_id,
            "email_address" => $email,
            "opt_in_status" => true
        ],
        "currency_code"    => $order_currency,
        "order_total"      => $total_amount,
        "discount_total"   => $discount_total,
        "financial_status" => $financial_status,
        "processed_at_foreign" => $order_date,
        "lines"            => []
    ];

    if ( ! empty( $coupon_code ) ) {
        $order_data["tracking_code"] = $coupon_code;
    }

    // Product creation helpers (left as-is)
    // ...

    // Line items and product creation logic (left as-is, but safe)

    // Final Order Push
    $order_url = "https://{$server_prefix}.api.mailchimp.com/3.0/ecommerce/stores/{$store_id}/orders/{$order_id}";
    $response = tsd_mailchimp_api_request( $order_url, 'PUT', $api_key, $order_data );
    tsd_log_message( "Mailchimp Order Response: " . $response );
});

/**
 * Mailchimp API wrapper (shared by all requests)
 */
function tsd_mailchimp_api_request( $url, $method, $api_key, $body ) {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, strtoupper( $method ) );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        "Authorization: apikey {$api_key}",
        "Content-Type: application/json"
    ]);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    return "HTTP {$http_code}: " . sanitize_text_field( $response );
}

