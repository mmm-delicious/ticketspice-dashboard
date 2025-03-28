<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'tsd_log_message' ) ) {
    function tsd_log_message( $message, $is_error = false ) {
        $enabled = get_option( 'tsd_logging_enabled', 'yes' );
        if ( $enabled !== 'yes' ) return;

        $log_file = tsd_get_log_file_path();
        $dir = dirname( $log_file );

        // ✅ Ensure the uploads dir exists
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        // ✅ Make sure file exists and is writable
        if ( ! file_exists( $log_file ) ) {
            file_put_contents( $log_file, '' ); // create empty file
        }

        if ( is_writable( $log_file ) ) {
            $entry = date( "Y-m-d H:i:s" ) . " - " . sanitize_text_field( $message ) . "\n";
            file_put_contents( $log_file, $entry, FILE_APPEND );
        }

        if ( $is_error ) {
            error_log( "[TicketSpice] " . sanitize_text_field( $message ) );
        }
    }
}

function tsd_get_log_file_path() {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['basedir'] ) . 'ticketspice_log.txt';
}

function tsd_get_log_file_contents() {
    $log_file = tsd_get_log_file_path();
    return file_exists( $log_file ) ? (string) file_get_contents( $log_file ) : '';
}

function tsd_clear_log_file() {
    $log_file = tsd_get_log_file_path();
    if ( file_exists( $log_file ) ) {
        unlink( $log_file );
    }
}
