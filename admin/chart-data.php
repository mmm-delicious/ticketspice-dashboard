<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ajax handler for loading chart data into the TicketSpice Dashboard
 */
add_action( 'wp_ajax_tsd_chart_data', 'tsd_handle_chart_data_request' );

function tsd_handle_chart_data_request() {
    // ✅ Security check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
    }

    // ✅ Prevent caching
    nocache_headers();

    // ✅ Sample sales data — you'd replace this with real logic
    $sales_data = [
        '2024-03-01' => 1200,
        '2024-03-02' => 800,
        '2024-03-03' => 950,
        '2024-03-04' => 1100,
        '2024-03-05' => 700
    ];

    $top_products_data = [
        'VIP Pass'          => 25,
        'General Admission' => 80,
        'Backstage'         => 15
    ];

    // ✅ Send safe JSON response
    wp_send_json_success( [
        'sales'        => $sales_data,
        'top_products' => $top_products_data
    ] );
}
