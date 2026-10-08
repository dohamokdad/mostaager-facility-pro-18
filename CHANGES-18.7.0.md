# Mostaager Facility PRO — 18.7.0 (CRM bridge, verified against Houzez CRM 1.5.0)

Confirmed from the plugin source: CRM data lives in `{prefix}houzez_crm_deals` / `houzez_crm_leads`
(not post types), `Houzez_Deals::get_deal()` does not exist, and `Houzez_Leads::get_lead()` filters
by the current user — so the bridge reads the tables directly.

- Deal → tenant conversion now uses the real schema (deal_id, user_id, agent_id, lead_id,
  listing_id, deal_group = active|won|lost).
- Permission: the deal must belong to the current user (user_id or agent_id) unless admin.
- Automatic unit linking: when the deal has a listing_id that maps to an ms_units row
  (property_id, or the property's unit_id meta), the tenant is attached to that unit —
  a lease row in ms_unit_tenants plus the unit marked occupied.
- Optional automatic conversion (OFF by default, option `ms_crm_auto_convert` = 1):
  the CRM fires no hook on status change, so a short scan is scheduled after any deal
  add/status change, with an hourly fallback. Each converted deal is stamped on the user
  (`_ms_source_deal_id`), so re-running never duplicates a tenant or a lease.

Tested on WordPress + Houzez 4.3.5 + Houzez Theme Functionality 4.3.5 + Houzez CRM 1.5.0:
manual and automatic conversion both create the tenant (roles: tenant + houzez_buyer), link the
unit, and are idempotent. Zero PHP warnings.
