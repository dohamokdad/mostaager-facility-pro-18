# Mostaager Facility PRO — 18.1.0

## PDF
- New engine `includes/class-ms-pdf.php`: lazy TCPDF loading (vendor/autoload.php never existed → all PDFs were broken).
- Requires PHP extensions: curl + mbstring (TCPDF 6.11 fatals without curl; now fails gracefully).
- TCPDF trimmed 27MB → 1.8MB (only aealarabiya + core fonts, QR barcode).
- Invoices: total now equals invoice amount (old code added 14% VAT on top); fixed empty email attachment;
  removed "account number: to be added" placeholder; access check + per-invoice nonce; download links in owner/tenant/building dashboards.
- Reports: admin reports export fixed (wrong nonce + action collision with Houzez reports); Houzez CSV now contains data; PDF added
  to admin reports, Houzez reports, analytics, and owner monthly reports. Excel stubs removed.
- Exports stored in protected uploads/ms-exports with one-time, user-bound links (auto-deleted after 1h).
- Owner monthly report scoped to the owner's own units (previously summed the whole platform).

## Security
- Invoice payment ownership check (string/int strict-comparison bug).
- ms_get_owners_by_building / ms_get_tenants_by_building: nonce + building scope.
- Contract uploads: nonce, property ownership, real file-type validation.
- Maintenance REST: removed nested $wpdb->prepare(); stats in one query.
- Login redirect no longer uses HTTP_HOST.
- REST /auth/token: password no longer sanitized; 5 attempts / 15 min per IP.

## Removed (dead code)
- includes/class-dashboard-charts.php + admin/js/dashboard-charts.js + admin/css/dashboard-enhanced.css (never instantiated).
- admin/views/reports-advanced.php (never loaded).
- .git directory.

## Optional settings (wp_options)
- ms_invoice_vat_rate (default 0) · ms_invoice_bank_details · ms_invoice_payment_terms · ms_pdf_branding (name/tagline/contact)
