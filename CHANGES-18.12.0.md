# Mostaager Facility PRO — 18.12.0 (payment path tested end-to-end)

Tested on a real stack: WordPress 7.0.1 + Houzez 4.3.5 + WooCommerce 11.1.2 (EGP),
with a manual test gateway — no built-in gateway involved.

## Result of the end-to-end test
- invoice → `ms_create_woo_order_for_invoice()` → WooCommerce order with the right total/currency/customer ✅
- clicking "pay" twice reuses the **same** order instead of creating a second one ✅
- `payment_complete` (instant gateways) marks the invoice paid with a paid date ✅
- `on-hold → completed` (bank transfer / manual gateways) also marks it paid ✅
- repeating `payment_complete` does not double-apply anything ✅
- wallet top-up: order → payment → balance credited once; repeating the payment does not credit twice ✅

## Fixed
- **Double payment:** an already-paid (or cancelled) invoice could still generate a new payment
  link — a tenant could pay the same rent twice. `ms_create_woo_order_for_invoice()` now returns
  `WP_Error('invoice_not_payable')` and every caller (3 AJAX endpoints + REST v2) answers with
  HTTP 409 and a clear message instead of a payment URL.

## Known behaviour worth deciding on
- Cancelling or refunding an order does **not** revert the invoice to unpaid; it stays `paid`.
  That is intentional for now (accounting trail) but should be a conscious business decision.
