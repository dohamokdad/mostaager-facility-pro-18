# Mostaager Facility PRO — 18.9.0 (Phase 6: API authorization + contract)

## Object-level authorization in mfp/v1 (previously missing)
A valid JWT was enough to read other people's buildings by passing an id:
- `GET /discussions` had **no** authorization at all beyond the token.
- `GET /wallet?building_id=` let any manager read any building's wallet.
- `GET /maintenance?building_id=` accepted an arbitrary building for owner/tenant roles.
- `POST /maintenance` never checked the manager owns the building, and accepted a unit from
  another building.
All of these now go through `can_access_building()` → the unified
`ms_user_can_access_building()` (admin, building manager, assigned agent, owner, tenant).

`GET /buildings/{id}/units` also stopped rejecting owners and tenants outright — they now see
the units of buildings they are actually attached to.

## Validation
- Maintenance `status`, `priority` and `payer_type` are whitelisted on create (422 with the
  allowed values in `details`), matching the existing whitelist on PATCH.
- A `unit_id` that does not belong to `building_id` is rejected (422 `unit_building_mismatch`).
- `PATCH /maintenance/{id}` now also works for site admins, not managers only.

## Unified response contract
- Success: `{ success: true, data: …, meta: { page, per_page, total, total_pages } }`
  (meta only on list endpoints: units, maintenance, discussions — `?page=`/`?per_page=`, max 100).
- Error: `{ success: false, code: "forbidden_building", message: "…", details: {} }`
- HTTP codes: 400 bad request, 401 missing/invalid token, 403 insufficient permission,
  404 not found, 422 invalid payload, 500 internal.
- `POST /maintenance` returns 201 and fires `ms_maintenance_created`.

## REST meta
`ms_building_id`, `ms_unit_id`, `ms_unit_number`, `ms_floor`, `ms_unit_status` are registered with
`register_post_meta()` (typed, sanitized, `auth_callback` = `edit_post`) instead of exposing raw meta.

Live-tested on the local Houzez stack: a tenant reads their own building's units with pagination
meta, and receives 403 `forbidden_building` for another building's units, discussions and
maintenance; an anonymous request gets 401. Zero PHP warnings.
