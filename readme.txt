=== TicketSpice Dashboard ===
Contributors: mmm-delicious
Tags: ticketspice, mailchimp, woocommerce, dashboard, webhook, integration, ecommerce
Requires at least: 5.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful integration and reporting dashboard for syncing TicketSpice with WooCommerce and Mailchimp.

== Description ==

TicketSpice Dashboard allows seamless syncing of TicketSpice order data with both WooCommerce and Mailchimp, enabling marketing automation and ecommerce record keeping directly from your WordPress site.

Includes visual reporting, webhook logging, testing utilities, and configuration via the admin dashboard.

= Features =

* Sync TicketSpice orders into Mailchimp ecommerce.
* Sync TicketSpice orders into WooCommerce.
* Create WooCommerce customers, products, and orders automatically.
* Create Mailchimp customers, orders, products, and tags automatically.
* Toggle Mailchimp or WooCommerce sync independently.
* Admin settings page with secure API credential fields.
* View and clear webhook logs.
* Dashboard widget with charts of total sales and top products (last 30 days).
* Webhook tester and duplication check utility.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ticketspice-dashboard` directory, or install it through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Visit **Settings > TicketSpice Dashboard** to configure Mailchimp and WooCommerce API keys.
4. Add your webhook URL in TicketSpice as: `https://yourdomain.com/ticketspice-webhook`

== Frequently Asked Questions ==

= What is the webhook endpoint URL? =

Your webhook endpoint will be `https://yourdomain.com/ticketspice-webhook`.

= Does this plugin overwrite WooCommerce orders? =

No. It checks for duplicates using meta data before creating an order.

= Can I disable Mailchimp or WooCommerce syncing? =

Yes. You can toggle both independently from the settings page.

= Is log data stored securely? =

Logs are written to the uploads directory and can be turned off or cleared at any time.

== Screenshots ==

1. Admin settings for WooCommerce and Mailchimp API keys.
2. Webhook log viewer and tester.
3. Dashboard chart with top products and revenue.

== Changelog ==

= 1.0.0 =
* Initial release with full integration.
* Webhook logging and tester.
* Admin panel with settings and dashboard charts.

== Upgrade Notice ==

= 1.0.0 =
First stable release.
