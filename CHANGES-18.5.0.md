# Mostaager Facility PRO — 18.5.0 (Houzez integration, phase 1)

Verified against Houzez 4.3.5 + Houzez Theme Functionality 4.3.5 on a real WordPress install.

## New: Houzez dashboard bridge
- `includes/houzez/class-houzez-dashboard-bridge.php`
  - Page template "Mostaager: لوحة داخل Houzez" renders our dashboards inside the Houzez dashboard frame
    (`templates/houzez-dashboard-shell.php` → get_header('dashboard') + Houzez sidebar/topbar).
  - Registers our template with `houzez_is_dashboard_filter` so Houzez loads its dashboard CSS/JS.
  - Injects a role-based section into the Houzez sidebar via the WP core action
    `get_template_part_template-parts/dashboard/dashboard-menu` — no theme file is touched.
  - One-time page migration (auto, reversible from the admin notice; `ms_houzez_bridge_auto_migrate` filter).
  - Tenants landing on the Houzez home dashboard are sent to their Mostaager page.
  - "Add property" inside our dashboards now opens the native Houzez submit form.
- `assets/css/ms-houzez-shell.css` + `assets/js/ms-houzez-shell.js`: content fits the Houzez frame,
  Houzez dashboard menu gets Mostaager colours (navy/gold + skyline). Disable with `ms_houzez_brand_dashboard`.
- Our own sidebar is hidden (not removed) inside the frame, so tab switching keeps working.

## Fixes found while testing
- Legacy redirects blocked owners from the Houzez dashboard and tenants from the Houzez profile page;
  they are now skipped when the bridge is active, and login redirects use the real page URLs.
- Duplicate quick-action row on the tenant dashboard removed when the page header already has actions.
- Invoice tables showed `get_post(building_id)` — i.e. the WordPress post that happens to share the
  internal building id ("Hello world!"). New helper `ms_building_display_name()` resolves the real name.

## Known, not fixed yet
- Fresh activation does not create the plugin tables (installer functions load on plugins_loaded).
- PHP notices in includes/inline-property-form.php ($agents) and includes/class-monitoring.php.
