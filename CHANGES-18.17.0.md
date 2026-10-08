# Mostaager Facility PRO — 18.17.0

## Fixed: building dashboard would not switch sections while open
Loading `/building-dashboard/#units` worked, but moving between #units / #maintenance / #invoices
without a full refresh did not. The building dashboard ships its own inline tab script, and
18.15.0's deep-link handler asked that script to do the switch (by clicking the tab link), so it
inherited whatever that script did — or did not do.

The handler no longer depends on any dashboard's own code:
- it switches the panels itself (class + display) and updates both menus (the plugin sidebar and
  the section injected into the Houzez sidebar);
- it also intercepts clicks on any in-page `#section` link, so a link works even when the hash
  does not change;
- it keeps the URL in sync with `history.pushState`, so browser back/forward still move between
  sections.

Verified on the building dashboard: direct load of #units, then clicking through maintenance →
invoices → units → overview → invoices, all switch correctly with no JS errors.
