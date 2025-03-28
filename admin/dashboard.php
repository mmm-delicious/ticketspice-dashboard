<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Hook into WP Admin menu
add_action( 'admin_menu', 'tsd_register_admin_menu' );

// Inject chart data JS into Reports page only
add_action( 'admin_footer', function() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'ticketspice-dashboard' ) {
        ?>
        <script>
            fetch(ajaxurl + '?action=tsd_chart_data')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.tsdSalesData = data.data.sales;
                        window.tsdTopProductsData = data.data.top_products;
                        const event = new Event('DOMContentLoaded');
                        document.dispatchEvent(event);
                    }
                });
        </script>
        <?php
    }
});

/**
 * Register admin menu pages
 */
function tsd_register_admin_menu() {
    // Main page: Reports
    add_menu_page(
        'TicketSpice Dashboard',
        'TicketSpice',
        'manage_options',
        'ticketspice-dashboard',
        'tsd_render_reports_page',
        'dashicons-chart-bar',
        25
    );

    add_submenu_page(
        'ticketspice-dashboard',
        'Reports',
        'Reports',
        'manage_options',
        'ticketspice-dashboard',
        'tsd_render_reports_page'
    );

    add_submenu_page(
        'ticketspice-dashboard',
        'Webhook Log',
        'Webhook Log',
        'manage_options',
        'ticketspice-log',
        'tsd_render_log_page'
    );

    add_submenu_page(
        'ticketspice-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'ticketspice-settings',
        'tsd_render_settings_page'
    );

    add_submenu_page(
        'ticketspice-dashboard',
        'Webhook Tester',
        'Webhook Tester',
        'manage_options',
        'ticketspice-tester',
        'tsd_render_tester_page'
    );
}

/**
 * Render: Settings Page
 */
function tsd_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>TicketSpice Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields( 'tsd_settings_group' );
                do_settings_sections( 'tsd_settings_page' );
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Render: Log Viewer
 */
function tsd_render_log_page() {
    ?>
    <div class="wrap">
        <h1>Webhook Log</h1>
        <textarea style="width:100%;height:300px;" readonly><?php echo esc_textarea(tsd_get_log_file_contents()); ?></textarea>
        <form method="post">
            <?php wp_nonce_field( 'tsd_clear_log_action', 'tsd_clear_log_nonce' ); ?>
            <?php submit_button('Clear Log', 'delete', 'clear_log'); ?>
        </form>
    </div>
    <?php
    if (
        isset($_POST['clear_log']) &&
        isset($_POST['tsd_clear_log_nonce']) &&
        wp_verify_nonce($_POST['tsd_clear_log_nonce'], 'tsd_clear_log_action')
    ) {
        tsd_clear_log_file();
        echo "<script>location.reload();</script>";
    }
}

/**
 * Render: Reports Page (Charts)
 */
function tsd_render_reports_page() {
    ?>
    <div class="wrap">
        <h1>TicketSpice Reports</h1>
        <canvas id="tsd_sales_chart" width="100%" height="40"></canvas>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.tsdSalesData) return;
            new Chart(document.getElementById('tsd_sales_chart'), {
                type: 'bar',
                data: {
                    labels: Object.keys(window.tsdSalesData),
                    datasets: [{
                        label: 'TicketSpice Sales (Last 30 Days)',
                        data: Object.values(window.tsdSalesData),
                        backgroundColor: '#36a2eb'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Sales ($)'
                            }
                        }
                    }
                }
            });
        });
        </script>
    </div>
    <?php
}
