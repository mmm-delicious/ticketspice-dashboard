// assets/js/charts.js

document.addEventListener("DOMContentLoaded", function () {
    const salesChart = document.getElementById("tsd-sales-chart");
    const topProductsChart = document.getElementById("tsd-top-products-chart");

    if (salesChart) {
        new Chart(salesChart, {
            type: "line",
            data: window.tsdSalesData,
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: "TicketSpice Sales (Last 30 Days)",
                    },
                    legend: {
                        display: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Total Sales ($)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
    }

    if (topProductsChart) {
        new Chart(topProductsChart, {
            type: "bar",
            data: window.tsdTopProductsData,
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: "Top Products Sold"
                    },
                    legend: {
                        display: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Units Sold'
                        }
                    }
                }
            }
        });
    }
});
