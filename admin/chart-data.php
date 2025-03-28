<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_tsd_chart_data', 'tsd_handle_chart_data_request' );

function tsd_handle_chart_data_request() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
    }

    nocache_headers();

    $range = $_GET['range'] ?? 'week';
    $days = $range === 'month' ? 30 : 7;

    $sales_data = [];
    $top_products = [];

    // Build date range map
    for ( $i = $days - 1; $i >= 0; $i-- ) {
        $date = date( 'Y-m-d', strtotime( "-$i days" ) );
        $sales_data[ $date ] = 0;
    }

    $args = [
        'limit'     => -1,
        'status'    => ['completed', 'processing'],
        'date_paid' => '>' . ( new DateTime( "-$days days" ) )->format( 'Y-m-d H:i:s' ),
    ];

    $orders = wc_get_orders( $args );
    $product_totals = [];

    foreach ( $orders as $order ) {
        $date = $order->get_date_paid()->format( 'Y-m-d' );
        $sales_data[ $date ] += floatval( $order->get_total() );

        foreach ( $order->get_items() as $item ) {
            $name = $item->get_name();
            $qty = $item->get_quantity();
            $product_totals[ $name ] = ( $product_totals[ $name ] ?? 0 ) + $qty;
        }
    }

    // Top 5 products sorted by quantity
    arsort( $product_totals );
    $top_products = array_slice( $product_totals, 0, 5, true );

    wp_send_json_success( [
        'sales'        => $sales_data,
        'top_products' => $top_products
    ] );
}
