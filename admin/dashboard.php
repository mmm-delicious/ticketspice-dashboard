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

        <div style="margin-bottom: 1em;">
            <button class="tsd-range-btn" data-range="week">Last 7 Days</button>
            <button class="tsd-range-btn" data-range="month">Last 30 Days</button>
        </div>

        <canvas id="tsd_sales_chart" height="60"></canvas>
        <canvas id="tsd_top_products_chart" height="40" style="margin-top: 50px;"></canvas>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
    function loadReportData(range = 'week') {
        fetch(ajaxurl + '?action=tsd_chart_data&range=' + range)
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;

                const sales = res.data.sales;
                const top = res.data.top_products;

                const salesCtx = document.getElementById('tsd_sales_chart').getContext('2d');
                const topCtx = document.getElementById('tsd_top_products_chart').getContext('2d');

                if (window.salesChart) window.salesChart.destroy();
                if (window.topProductsChart) window.topProductsChart.destroy();

                window.salesChart = new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: Object.keys(sales),
                        datasets: [{
                            label: 'Sales ($)',
                            data: Object.values(sales),
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: '#36a2eb',
                            borderWidth: 2,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        animation: {
                            duration: 800,
                            easing: 'easeOutQuart'
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `$${context.raw.toFixed(2)} in sales`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });

                window.topProductsChart = new Chart(topCtx, {
                    type: 'bar',
                    data: {
                        labels: Object.keys(top),
                        datasets: [{
                            label: 'Top Products (Qty)',
                            data: Object.values(top),
                            backgroundColor: '#3366cc'
                        }]
                    },
                    options: {
                        responsive: true,
                        animation: {
                            duration: 800,
                            easing: 'easeOutQuart'
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.raw} units sold`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });

                // ✅ Correct placement: inside .then
                const leaderboardBody = document.getElementById('tsd_leaderboard_body');
                leaderboardBody.innerHTML = '';

                Object.entries(top).forEach(([name, qty], index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${name}</td>
                        <td>${qty}</td>
                    `;
                    leaderboardBody.appendChild(row);
                });
            });
    }

        </script>
    </div>
    <div id="tsd_top_products_leaderboard" style="margin-top: 40px;">
        <h2>🏆 Top 5 Products (Past 30 Days)</h2>
        <table class="tsd-leaderboard">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Quantity Sold</th>
                </tr>
            </thead>
            <tbody id="tsd_leaderboard_body">
                <!-- JS will populate this -->
            </tbody>
        </table>
    </div>

    <?php
}
