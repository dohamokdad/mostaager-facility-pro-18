# Mostaager Facility PRO — 18.18.0

Diagnostics for the dashboard tab behaviour, exposed on the `MSUX` object so a problem can be
identified from the browser console without guessing:

- `MSUX.version` — the version of ms-ux.js actually loaded in the page (catches a cached or
  minified-away file immediately).
- `MSUX.openTab('units')` — switches the section directly and returns true/false.
- `MSUX.tabs()` — the section ids present on the page.

No behaviour changes; 18.17.0's section switching is unchanged.
