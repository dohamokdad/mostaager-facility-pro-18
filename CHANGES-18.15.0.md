# Mostaager Facility PRO — 18.15.0

## Fixed: deep links to a dashboard section did nothing
`/building-dashboard/#units` (and the same on the tenant/owner/agent dashboards) kept showing
"نظرة عامة". Each dashboard reads `location.hash` **once**, at DOMContentLoaded, and never listens
for `hashchange` — so any navigation that only changes the fragment (pasting the URL while already
on the page, using browser history, or clicking a section link in the Houzez sidebar) left the
page on the previous tab. The console showed `URL hash:` empty → `No hash, defaulting to overview`.

- `assets/js/ms-ux.js` now handles deep links for every dashboard: it opens the requested section
  on load and on every `hashchange` / `popstate`, by clicking the matching tab link (works even when
  the plugin's own sidebar is hidden inside the Houzez frame) with a manual fallback.
- `?tab=units` is supported as well, for links that must survive redirects that drop the fragment.

## Fixed: two 404s on every dashboard page
`includes/user-experience.php` linked `assets/manifest.json` and registered
`assets/js/service-worker.js` — neither file ships with the plugin, so every page load produced a
404 manifest fetch and a failed service-worker registration. Both are now emitted only when the
files exist. The theme-color meta also moved to the brand navy.

Tested live: loading without a hash opens Overview; switching the hash to #units / #invoices /
#maintenance / #overview switches the section every time; `?tab=invoices` opens Invoices. No JS errors.
