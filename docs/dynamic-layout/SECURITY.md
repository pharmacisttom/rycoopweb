# Security review

Reviewed controls and actual tests are recorded in QA.md/evidence. This is a code/local test review; remote TEST configuration remains unverified.

| Boundary | Control |
| --- | --- |
| Admin session | AuthMiddleware and service Auth::check require active, version-valid, MFA-complete server session; member role denied |
| Authorization | Dedicated layout.view/update/publish at route and service; no synonym to unrelated modules; no automatic role grants |
| Sensitive actions | Real existing TOTP verifier, exact per-action scope, freshness, account/session-version binding and consumed-grant checks |
| Browser writes | CSRF on every POST/PATCH/DELETE; upload/publication rate limits |
| Concurrency | One ordered aggregate row lock, required global version, transactionally updated current pointer and audit |
| SQL | Parameterized IDs/data; pagination integers bounded; no editor-provided table/column SQL |
| XSS | Closed content schema, PHP HTMLPurifier for rich text, React text escaping, DOMPurify and safe preview URLs; no executable templates |
| Hierarchy | Duplicate/orphan/cycle/depth checks, category reference integrity and unique slugs |
| Uploads | Uploaded-file provenance, byte signatures/finfo, size/dimension/pixel checks, actual decoding, WebP re-encoding, EXIF/payload removal, thumbnails, random IDs |
| Filesystem | Fixed private root and validated IDs/sizes; no client paths/extensions; exclusive creation and failed-request cleanup |
| Public data | Read-only immutable snapshots only; no draft initializer/publisher on GET; indexed published membership protects images |
| Audit | Server-derived actor, required audit within data transaction; no TOTP/code/credential included in layout audit |

Residual operational gates: verified SSH host/key/profile, TEST-only credentials and filesystem mappings, protected persistent private media, actual MySQL 8 verification, proxy/PHP upload limits, actual MFA/role grants and signed TEST UAT. Historical published images remain retained and retrievable through their published version URLs; erasure/retention needs a separate authorized policy. The editor deliberately caps tree depth/node counts and batch sizes while keeping album count uncapped with pagination. No dependency or production credential changes are required.
