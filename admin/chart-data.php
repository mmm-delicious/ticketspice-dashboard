<?php
// admin/chart-data.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_tsd_chart_data', 'tsd_generate_chart_data' );

function tsd_generate_chart_data() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized', 401 );
    }

    global $wpdb;

    $days = 30;
    $date_format = 'Y-m-d';

    $dates = [];
    $sales_by_day = [];
    $top_products = [];

    for ( $i = $days - 1; $i >= 0; $i-- ) {
        $date = date( $date_format, strtotime( "-$i days" ) );
        $dates[] = $date;
        $sales_by_day[ $date ] = 0;
    }

    // Sales by day
    $orders = wc_get_orders([
        'limit' => -1,
        'status' => ['wc-completed', 'wc-processing'],
        'date_paid' => '>' . date( 'Y-m-d', strtotime( "-$days days" ) ),
        'meta_key' => '_ticketspice_order_id',
    ]);

    $product_sales = [];

    foreach ( $orders as $order ) {
        $date = $order->get_date_paid() ? $order->get_date_paid()->format( $date_format ) : null;
        if ( $date && isset( $sales_by_day[ $date ] ) ) {
            $sales_by_day[ $date ] += floatval( $order->get_total() );
        }

        foreach ( $order->get_items() as $item ) {
            $name = $item->get_name();
            if ( ! isset( $product_sales[ $name ] ) ) {
                $product_sales[ $name ] = 0;
            }
            $product_sales[ $name ] += $item->get_quantity();
        }
    }

    // Format Chart.js data
    $response = [
        'sales' => [
            'labels' => array_values( $dates ),
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => array_values( $sales_by_day ),
                    'backgroundColor' => 'rgba(0, 123, 255, 0.2)',
                    'borderColor' => 'rgba(0, 123, 255, 1)',
                    'borderWidth' => 2,
                    'tension' => 0.3,
                    'fill' => true,
                ]
            ]
        ],
        'top_products' => [
            'labels' => array_keys( $product_sales ),
            'datasets' => [
                [
                    'label' => 'Top Products',
                    'data' => array_values( $product_sales ),
                    'backgroundColor' => 'rgba(40, 167, 69, 0.6)',
                    'borderColor' => 'rgba(40, 167, 69, 1)',
                    'borderWidth' => 1,
                ]
            ]
        ]
    ];

    wp_send_json_success( $response );
}
