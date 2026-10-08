# Mostaager Facility PRO — 18.16.0

## Fixed: `admin-ajax.php 400 (Bad Request)` on every dashboard tab
Reproduced locally and traced to `ms_get_notification_stats`:

- `assets/js/notifications.js` called it from `init()` on **every** dashboard page, although the
  stats belong to the admin "send notification" screen.
- The handler lives in `MS_Multi_Channel_Notifications`, and that class was **never instantiated**,
  so the action had no handler at all — WordPress answers an unknown action with `0` and HTTP 400.

Both sides fixed:
- the stats request now runs only when the admin notification UI is present on the page;
- the class is instantiated, so its AJAX endpoints (stats, templates, scheduling, preferences)
  actually exist.

Verified: the building dashboard now issues only `ms_get_building_dashboard` and
`ms_get_notifications_since`, both HTTP 200 — no 400 remains.
