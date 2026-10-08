# Mostaager Facility PRO — 18.13.0

## Fatal with WP-Optimize Minify (dashboard pages)
The plugin enqueued Google Fonts as a single `css2?family=A&family=B` URL. WP-Optimize's
font concatenator parses that query with `parse_str()`, where the repeated `family` key keeps only
the last value, and then dies with
`in_array(): Argument #2 must be of type array, null given`
inside `WP_Optimize_Minify_Fonts::parse_font_api2_url()` — which took down every dashboard page.

- Fonts are now enqueued as **one request per family** (Cairo, Tajawal), which those parsers handle.
- New filter to switch plugin fonts off entirely when the theme already loads them:
  `add_filter('ms_load_brand_fonts', '__return_false');`
- The same multi-family URL in includes/houzez-ui-integration.php was replaced (it also pulled
  Poppins, which is not part of the brand).

## Wallet ⇄ WooCommerce (new includes/wallet-woo-sync.php)
Top-ups already went through WooCommerce; the cycle is now complete:
- **Refunds deduct the balance.** Refunding a top-up order (fully or partially) removes the
  refunded amount from the wallet instead of leaving the money with the user. Tracked with
  `_ms_wallet_refunded_total`, so repeating a refund never deducts twice.
- **One validation point** for top-up amounts: `ms_wallet_topup_limits()` (options
  `ms_wallet_topup_min` = 50, `ms_wallet_topup_max` = 100000, filterable) enforced inside
  `ms_create_woo_order_for_wallet_recharge()`, so every entry point — tenant/owner dashboards,
  shortcode, REST, mobile — gets the same rule and a clear 422 message.
- Refunding an **invoice** order does not silently pass: it fires `ms_invoice_order_refunded` and
  notifies an administrator to review the invoice manually.

Tested live on WordPress 7.0.1 + WooCommerce 11.1.2: below-minimum and above-maximum amounts are
rejected with their own error codes; 1000 credited then refunded returns the balance to its
starting value; repeating the refund does not deduct again.
