<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', 'tsd_register_plugin_settings' );

function tsd_register_plugin_settings() {
    // Register all plugin options
    register_setting( 'tsd_settings_group', 'tsd_logging_enabled' );
    register_setting( 'tsd_settings_group', 'tsd_enable_mailchimp' );
    register_setting( 'tsd_settings_group', 'tsd_enable_woocommerce' );

    // Mailchimp settings
    register_setting( 'tsd_settings_group', 'tsd_mailchimp_api_key' );
    register_setting( 'tsd_settings_group', 'tsd_mailchimp_server_prefix' );
    register_setting( 'tsd_settings_group', 'tsd_mailchimp_store_id' );
    register_setting( 'tsd_settings_group', 'tsd_mailchimp_list_id' );

    // WooCommerce settings
    register_setting( 'tsd_settings_group', 'tsd_woo_consumer_key' );
    register_setting( 'tsd_settings_group', 'tsd_woo_consumer_secret' );

    add_settings_section( 'tsd_main_section', 'TicketSpice Plugin Settings', null, 'tsd_settings_page' );

    // Logging
    add_settings_field(
        'tsd_logging_enabled',
        'Enable Logging',
        function() {
            $val = get_option( 'tsd_logging_enabled', 'yes' );
            echo '<input type="checkbox" name="tsd_logging_enabled" value="yes"' . checked( 'yes', $val, false ) . '> Log webhook activity to file';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    // Sync toggles
    add_settings_field(
        'tsd_enable_mailchimp',
        'Enable Mailchimp Sync',
        function() {
            $val = get_option( 'tsd_enable_mailchimp', 'yes' );
            echo '<input type="checkbox" name="tsd_enable_mailchimp" value="yes"' . checked( 'yes', $val, false ) . '> Sync incoming TicketSpice orders to Mailchimp';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    add_settings_field(
        'tsd_enable_woocommerce',
        'Enable WooCommerce Sync',
        function() {
            $val = get_option( 'tsd_enable_woocommerce', 'yes' );
            echo '<input type="checkbox" name="tsd_enable_woocommerce" value="yes"' . checked( 'yes', $val, false ) . '> Sync incoming TicketSpice orders to WooCommerce';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    // Mailchimp Config
    add_settings_field(
        'tsd_mailchimp_api_key',
        'Mailchimp API Key',
        function() {
            echo '<input type="password" style="width: 400px;" name="tsd_mailchimp_api_key" value="' . esc_attr( get_option( 'tsd_mailchimp_api_key' ) ) . '" />';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    add_settings_field(
        'tsd_mailchimp_server_prefix',
        'Mailchimp Server Prefix (e.g., us14)',
        function() {
            echo '<input type="text" name="tsd_mailchimp_server_prefix" value="' . esc_attr( get_option( 'tsd_mailchimp_server_prefix' ) ) . '" />';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    add_settings_field(
        'tsd_mailchimp_store_id',
        'Mailchimp Store ID',
        function() {
            echo '<input type="text" name="tsd_mailchimp_store_id" value="' . esc_attr( get_option( 'tsd_mailchimp_store_id' ) ) . '" />';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    add_settings_field(
        'tsd_mailchimp_list_id',
        'Mailchimp Audience/List ID',
        function() {
            echo '<input type="text" name="tsd_mailchimp_list_id" value="' . esc_attr( get_option( 'tsd_mailchimp_list_id' ) ) . '" />';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    // WooCommerce Config
    add_settings_field(
        'tsd_woo_consumer_key',
        'WooCommerce Consumer Key',
        function() {
            echo '<input type="password" style="width: 400px;" name="tsd_woo_consumer_key" value="' . esc_attr( get_option( 'tsd_woo_consumer_key' ) ) . '" />';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    add_settings_field(
        'tsd_woo_consumer_secret',
        'WooCommerce Consumer Secret',
        function() {
            echo '<input type="password" style="width: 400px;" name="tsd_woo_consumer_secret" value="' . esc_attr( get_option( 'tsd_woo_consumer_secret' ) ) . '" />';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );

    // Webhook URL (Read-Only)
    add_settings_field(
        'tsd_webhook_url',
        'Webhook URL',
        function() {
            echo '<input type="text" readonly style="width: 100%; padding: 10px; font-size: 16px;" value="' . esc_url( home_url( '/ticketspice-webhook' ) ) . '" />';
            echo '<p class="description">Use this URL to configure your TicketSpice webhooks.</p>';
        },
        'tsd_settings_page',
        'tsd_main_section'
    );
}
