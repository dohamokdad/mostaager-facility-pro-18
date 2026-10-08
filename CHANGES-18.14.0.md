# Mostaager Facility PRO — 18.14.0 (every credit goes through WooCommerce)

## Audit of every money-in path
All five user-facing top-up endpoints already created a WooCommerce order and credited only after
payment: `ms_create_wallet_recharge` (core/ajax.php), `ms_wallet_recharge` (pro-platform),
`ms_wallet_topup` (dashboard integration), the `[wallet_topup]` shortcode, and the REST top-up.

The audit found **one path that bypassed WooCommerce**: `wp_ajax_approve_invoice` — a manager
approving a legacy invoice credited the building wallet directly, with no order and no record.

## Manual collection is now a WooCommerce order too
- New `ms_create_offline_paid_order()`: creates an order paid by the method
  "تحصيل نقدي / تحويل بنكي", stamped with who collected it, the building and the invoice, then calls
  `payment_complete()` so the normal hooks do the crediting.
- `approve_invoice` uses it, and stores the order id on the invoice (`_ms_wc_order_id`).
- New `ms_credit_building_wallet_from_order()` credits the building wallet from such orders,
  once per order (`_ms_building_wallet_credited`), and refunding the order deducts it again.

Result: cash, bank transfer and online payments all end up as WooCommerce orders, so the money
ledger has one source of truth and nothing is credited without a record.

## Remaining intentional exception
The admin user-profile wallet field still writes a balance directly — it is an explicit
administrative correction, restricted to `manage_options` and logged as a wallet transaction.

Tested live (WooCommerce 11.1.2): manual collection of 2,500 creates a completed order and credits
the building wallet once; re-running the credit changes nothing; refunding the order returns the
balance to zero.
