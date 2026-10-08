# Mostaager Facility PRO — 18.6.0 (Houzez integration, phases 2–4)

## Phase 2 — Roles
- includes/houzez-roles-integration.php rewritten against the REAL Houzez roles:
  owner→houzez_owner, agent→houzez_agent/houzez_agency, building_manager→houzez_manager,
  tenant→houzez_buyer (one-way: a Houzez buyer is NOT a tenant).
  `houzez_building_manager` does not exist in Houzez and was removed.
- Sync runs on WP core hooks (user_register, set_user_role, add_user_role, remove_user_role,
  profile_update) plus houzez_after_register. Removing a role removes its counterpart.
- One-time sync of existing users with an admin notice.

## Phase 3 — CRM
- Deleted 3 dead lead converters (533 lines) that targeted non-existent post types and
  registered the same AJAX action twice.
- New includes/houzez/class-crm-bridge.php + assets/js/ms-crm-bridge.js: a "تحويل لمستأجر"
  button on CRM deal rows. Resolves the deal via Houzez_Deals/Houzez_Leads or the CRM tables,
  creates/links the tenant with add_role (the old code wiped all other roles), then points to
  the unit-assignment screen. Silently inactive when the Houzez CRM plugin is absent.

## Phase 4 — Cleanup
- Dead hooks remapped to real ones: houzez_search_query_args→houzez20_property_filter,
  houzez_single_property_after_description / houzez_property_after_content→houzez_single_listing.
- Dead registrations removed: houzez_dashboard_tabs, houzez_user_dashboard_menu,
  houzez_user_dashboard_widgets, houzez_property_meta_output, houzez_property_meta_fields,
  houzez_map_marker_data, houzez_area_unit / houzez_property_size(_unit).
- Removed nopriv AJAX for maintenance requests and wallet top-ups; nonce check now accepts the
  unified dashboard nonce. Removed an enqueue of a CSS file that does not exist.
- Property→unit sync no longer runs on autosave/revisions and runs once per request.
- Activation now loads core/install.php, so a fresh install actually creates its tables.
- Fixed undefined $agents in the inline property form (the required "agent" select was always
  empty, so the form could not be submitted) and an undefined 'timestamp' notice in monitoring.

Verified on WordPress + Houzez 4.3.5 + Houzez Theme Functionality 4.3.5: zero PHP warnings and
zero JS errors on the owner and tenant dashboards.
