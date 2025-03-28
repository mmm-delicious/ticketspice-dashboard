
---

## 📊 Dashboard Charts

The plugin includes a dashboard widget with:

- Total revenue over last 30 days
- Top 5 products sold via TicketSpice
- Automatically refreshed and rendered using Chart.js
- Logs auto-clear after 30 days (configurable)

---

## 🔁 Webhook Tester

Use the tester tab in the admin panel to:

- Send mock webhook data
- Test Mailchimp sync only
- Test WooCommerce sync only
- Validate duplicate detection and coupon creation

---

## 🧩 Developer Notes

- Fully namespaced and follows WordPress plugin coding standards
- Uses WordPress Options API for settings
- Uses `admin_enqueue_scripts` for conditional asset loading
- Webhook endpoint is registered with `rewrite_rules` at:  
  `https://yourdomain.com/ticketspice-webhook`

---

## 📎 Frequently Asked Questions

### Where do I configure the plugin?
Go to `Settings > TicketSpice Dashboard`.

### Can I disable Mailchimp or WooCommerce syncing?
Yes, individually toggle either integration via the settings page.

### Where are logs stored?
In your WordPress uploads directory (`/uploads/webhook_log.txt`) and viewable via the admin.

### Will this create duplicate WooCommerce orders?
No — it checks meta data (`_ticketspice_order_id`) before inserting.

---

## 📄 License

This plugin is licensed under the GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
