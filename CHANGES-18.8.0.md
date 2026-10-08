# Mostaager Facility PRO — 18.8.0 (Phase 5: one property↔building/unit contract)

## New: includes/ms-property-link.php
One place that owns the link between a Houzez `property` post and Mostaager rows.
- `ms_property_save_guard($post_id, $post, $context)` — shared autosave/revision/auto-draft/
  capability check, plus once-per-request per handler.
- `ms_property_building_id()` / `ms_link_property_building()` — reads any legacy key
  (`ms_building_id`, `_ms_building_id`, `building_id`) and writes all of them, so old code keeps working.
- `ms_property_unit_id()` / `ms_link_property_unit()` — same for units (+ the `property_id` column).
- `ms_create_building_from_property()` — the ONLY path that creates a building from a property;
  returns the existing link instead of creating a second building. Inserts only columns the
  installation actually has.
- `ms_sync_property_unit_fields()` — update the linked unit's columns safely.

## Fixed
- `ms_sync_unit_with_property()` used `WHERE id = $property_id`, i.e. it treated the WordPress post
  ID as the units-table row ID: it edited an unrelated unit, or inserted a bogus one. It now resolves
  the real unit and never creates one.
- Two separate "create a building from this property" paths existed
  (includes/houzez-sync.php, includes/houzez/class-property-sync.php) writing different meta keys.
  Both files were dead (never required / never instantiated) and are deleted; the feature now lives in
  the one active metabox as "+ أنشئ مبنى جديداً من هذا العقار".
- The building metabox, the Houzez adapter and the unit-field sync all go through the shared guard,
  so no handler runs twice for one save.

## REST API hardening (mostager/v1)
- `ms_houzez_api_permission()` now requires a logged-in user (authentication only; authorization stays
  per-object in each handler).
- `ms_houzez_can_access_building()` allowed only site admins and building managers, so owners and
  tenants got 403 on /units, /maintenance and /discussions. It now uses the unified
  `ms_user_can_access_building()` (manager, assigned agent, owner, tenant).
- `/mostager/v1/docs` is no longer public (manage_options).

Verified on WordPress + Houzez 4.3.5 + Theme Functionality + CRM 1.5.0: saving a property twice
creates exactly one building, all three meta keys stay consistent, only the linked unit is touched,
zero PHP warnings.
