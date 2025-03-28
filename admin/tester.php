<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders the Webhook Tester admin page
 */
function tsd_render_tester_page() {
    ?>
    <div class="wrap">
        <h1>Webhook Tester</h1>
        <p><?php echo esc_html( 'Use this tool to simulate incoming TicketSpice webhooks. You can toggle coupon codes, refunds, and duplicate orders to test the Mailchimp & WooCommerce sync behavior.' ); ?></p>
        
        <form method="post">
            <?php wp_nonce_field( 'tsd_test_webhook' ); ?>

            <h3>Webhook Payload</h3>
            <textarea name="tsd_test_payload" style="width:100%; height:300px;"><?php echo esc_textarea( tsd_get_sample_webhook() ); ?></textarea>

            <h3>Options</h3>
            <label><input type="checkbox" name="simulate_duplicate" /> <?php echo esc_html( 'Simulate Duplicate Order' ); ?></label><br>
            <label><input type="checkbox" name="include_coupon" /> <?php echo esc_html( 'Include Coupon Code' ); ?></label><br>
            <label><input type="checkbox" name="simulate_refund" /> <?php echo esc_html( 'Simulate Refunded Order' ); ?></label><br><br>

            <?php submit_button( 'Send Test Webhook' ); ?>
        </form>
    </div>
    <?php

    if (
        isset( $_POST['_wpnonce'] )
        && wp_verify_nonce( $_POST['_wpnonce'], 'tsd_test_webhook' )
        && isset( $_POST['tsd_test_payload'] )
    ) {
        $payload_raw = wp_unslash( $_POST['tsd_test_payload'] );
        $data = json_decode( $payload_raw, true );

        if ( is_array( $data ) && isset( $data['data'] ) ) {
            if ( isset( $_POST['simulate_refund'] ) ) {
                $data['data']['orderStatus'] = 'refunded';
            }

            if ( isset( $_POST['simulate_duplicate'] ) && isset( $data['data']['orderNumber'] ) ) {
                $data['data']['orderNumber'] = 'DUPLICATE-' . sanitize_text_field( $data['data']['orderNumber'] );
            }

            if ( isset( $_POST['include_coupon'] ) ) {
                $data['data']['registrants'][0]['data'][] = [
                    'key'   => 'couponCode',
                    'label' => 'Coupon Code',
                    'type'  => 'couponCode',
                    'value' => 'TESTCODE2025'
                ];
            }

            $payload = wp_json_encode( $data );

            $response = wp_remote_post( home_url( '/ticketspice-webhook' ), [
                'method'  => 'POST',
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => $payload
            ] );

            if ( is_wp_error( $response ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html( 'Webhook failed: ' . $response->get_error_message() ) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html( 'Webhook sent successfully! Check the log for processing details.' ) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . esc_html( 'Invalid JSON payload.' ) . '</p></div>';
        }
    }
}

/**
 * Returns a sample payload for testing
 */
function tsd_get_sample_webhook() {
    return json_encode([
        'eventType' => 'registration',
        'data' => [
            'billing' => [
                'email' => 'demo@example.com',
                'phone' => '+15555550123',
                'name' => [ 'first' => 'Demo', 'last' => 'User' ],
                'address' => [
                    'street1'    => '123 Sample Street',
                    'city'       => 'Demo City',
                    'state'      => 'CA',
                    'postalCode' => '90001',
                    'country'    => 'US'
                ]
            ],
            'currency' => 'USD',
            'formName' => 'Sample Event',
            'orderNumber' => 'ORDER-' . rand(1000,9999),
            'orderStatus' => 'completed',
            'registrationTimestamp' => date('c'),
            'transactionId' => rand(10000000,99999999),
            'total' => 50.00,
            'tickets' => [
                [
                    'id' => uniqid(),
                    'lookupId' => rand(100000,999999),
                    'ticketLabel' => 'General Admission',
                    'amount' => 50.00
                ]
            ],
            'registrants' => [
                [ 'data' => [] ]
            ]
        ]
    ], JSON_PRETTY_PRINT );
}
