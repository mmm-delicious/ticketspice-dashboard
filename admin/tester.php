<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renders the Webhook Tester admin page
 */
function tsd_render_tester_page() {
    ?>
    <div class="wrap">
        <h1>Webhook Tester</h1>
        <p>Use this tool to simulate incoming TicketSpice webhooks. You can toggle coupon codes, refunds, and duplicate orders to test the Mailchimp & WooCommerce sync behavior.</p>

        <form method="post">
            <?php wp_nonce_field( 'tsd_test_webhook' ); ?>

            <h3>Webhook Payload</h3>
            <textarea name="tsd_test_payload" style="width:100%; height:300px;"><?php echo esc_textarea( tsd_get_sample_webhook() ); ?></textarea>

            <h3>Options</h3>
            <label><input type="checkbox" name="simulate_duplicate" /> Simulate Duplicate Order</label><br>
            <label><input type="checkbox" name="include_coupon" /> Include Coupon Code</label><br>
            <label><input type="checkbox" name="simulate_refund" /> Simulate Refunded Order</label><br>
            <label><input type="checkbox" name="dry_run" /> Safe Mode (don’t sync to Mailchimp or Woo)</label><br><br>

            <?php submit_button( 'Send Test Webhook' ); ?>
        </form>
    </div>
    <?php

    if (
        isset( $_POST['_wpnonce'] )
        && wp_verify_nonce( $_POST['_wpnonce'], 'tsd_test_webhook' )
        && isset( $_POST['tsd_test_payload'] )
    ) {
        $data = json_decode( stripslashes( $_POST['tsd_test_payload'] ), true );

        if ( isset( $_POST['simulate_refund'] ) ) {
            $data['data']['orderStatus'] = 'refunded';
        }

        if ( isset( $_POST['simulate_duplicate'] ) ) {
            $data['data']['orderNumber'] = 'DUPLICATE-' . $data['data']['orderNumber'];
        }

        if ( isset( $_POST['include_coupon'] ) ) {
            $data['data']['registrants'][0]['data'][] = [
                'key'   => 'couponCode',
                'label' => 'Coupon Code',
                'type'  => 'couponCode',
                'value' => 'TESTCODE2025'
            ];
        }

        if ( isset( $_POST['dry_run'] ) ) {
            $data['dry_run'] = true;
        }

        $payload = json_encode( $data );

        $response = wp_remote_post( home_url( '/ticketspice-webhook' ), [
            'method'  => 'POST',
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $payload
        ] );

        if ( is_wp_error( $response ) ) {
            echo '<div class="notice notice-error"><p>Webhook failed: ' . esc_html( $response->get_error_message() ) . '</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Webhook sent successfully! Check the log for API responses.</p></div>';
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
