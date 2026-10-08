# Mostaager Facility PRO — 18.11.0 (WooCommerce-only payments)

The plugin no longer ships or references any payment gateway of its own.

## Removed
- `includes/telr-integration.php` (the bundled Telr WooCommerce gateway) and its loading.
- Telr helpers in includes/functions.php: `ms_get_telr_credentials`, `ms_build_telr_payload`,
  `ms_request_telr_payment_url`, `ms_get_mostaager_telr_gateway`.
- The "direct Telr" branch in `ms_create_woo_order_for_invoice()` and
  `ms_create_woo_order_for_wallet_recharge()`: both now always return the WooCommerce
  order-pay URL, so the buyer picks whichever gateway is enabled in WooCommerce.
- Telr fields and the "default payment gateway" selector from unified/advanced settings
  (replaced by a note pointing to WooCommerce → Settings → Payments), and the Telr entries in
  the integrations catalogue.
- Plugin header description updated: payments are via WooCommerce, any enabled gateway.

## Unchanged (already gateway-agnostic)
Invoice/wallet reconciliation listens to `woocommerce_payment_complete` and
`woocommerce_order_status_processing|completed`, so any WooCommerce gateway
(Paymob, Fawry, Kashier, Geidea, bank transfer, COD for testing…) marks the invoice paid and
credits the wallet without further changes.

## Fixed
- PHP notice from a settings field without a `default` value.
