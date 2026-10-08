# Mostaager Facility PRO — 18.10.0 (Phase 7: one REST layer)

- New `includes/API/class-ms-api.php` (`MS_API`): the single source for the response envelope,
  pagination and building-level authorization. `mfp/v1` now delegates to it instead of keeping
  its own copies.
- `mostager/v1` is formally deprecated but keeps working: a `rest_post_dispatch` filter wraps its
  responses in the same envelope (`{success, data}` / `{success, code, message, details}`) and adds
  `X-MS-API-Deprecated: true`, `X-MS-API-Successor: mfp/v1`, `Sunset: 2027-01-01`.
  The mobile app therefore sees one response shape on both namespaces.
- Added `docs-API-GUIDE.md`: the developer-facing API guide (auth, envelope, error codes, every
  endpoint with parameters and role, Dart/Kotlin/Swift snippets, pre-launch checklist).

Live-tested: `mfp/v1` returns data + meta; a legacy `mostager/v1` call returns the unified envelope
with the deprecation headers. Zero PHP warnings.
