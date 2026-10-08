# Mostaager Facility PRO — 18.2.0 (UX P0)

## Financial actions: busy state + no double submit
- New `assets/js/ms-ux.js` (loaded in <head>): `MSUX.busy()`, `MSUX.error()`, `MSUX.json()`.
- Wallet recharge (owner + tenant), deposit release (owner), maintenance payment (owner),
  security-deposit payment (tenant): button disabled with "جاري..." until the request finishes.
- Wallet recharge: accepts Arabic digits and commas; shows a message on invalid input (was silent).

## Readable error messages
- Server codes (not_owner, already_paid, security_failed, -1 ...) are translated to Arabic.
- Unknown English codes and JSON dumps are never shown to users.
- "Pay now" and manager mark-paid/cancel use the same mapping.

## Building manager — invoice actions
- "عرض" opens the invoice PDF inline (was: non-existent admin page ms_invoice_view).
- "دفع" replaced by "تسجيل كمدفوعة" using the existing scoped manager action (was: non-existent /payment/ page).
- "+ إنشاء فاتورة" removed (was: non-existent admin page ms_create_invoice).
- Marking paid no longer wipes the other buttons in the row (PDF stays).

## Currency
- Analytics revenue shown in ج.م (was ر.س). Advanced settings default currency EGP (was SAR).
  NOTE: a value already saved in the database is not changed — check Settings.

## Security deposit
- Deduction reason is mandatory (client + server) when a deduction is made.
