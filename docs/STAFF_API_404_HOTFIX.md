# Staff API 404 hotfix

The TEST screenshot shows the SPA `/admin/users` loading while the staff API returns
the application's `404 ไม่พบหน้าที่ต้องการ` message.

Confirmed in source and reproduced locally: `config/routes.php` registers the group
index using `$r->get('')`. Router generated `/api/admin/staff/`, whereas
`Request::parseUri()` normalizes both `/api/admin/staff` and `/api/admin/staff/` to
`/api/admin/staff`. The regex therefore never matched. The same defect affected
the group index `/admin/led`.

Router registration now uses the same trailing-slash normalization as Request and
preserves `/` as the root route. The handler, authentication, Super Admin role gate,
MFA, mutation CSRF and controller logic are unchanged.

`tests/StaffRouteDispatchTest.php` failed before the fix and passes after it. It loads
the actual route configuration, dispatches real Request/Response objects and reaches
the actual staff controller against an isolated SQLite fixture. Both URL forms verify:
guest 401, pending MFA 403, member/editor 403, verified Super Admin 200 with the real
staff/MFA/Google payload shape and no encrypted secret. It also checks canonical
registration of every route, group-index uniqueness and root-route dispatch.
The test is included in PHP CI. Existing workflow route, permission, MFA, dual-control,
Control Center, request-security and security-regression suites pass locally.

The earlier browser presentation tests used stub APIs and could not detect this real
PHP registration mismatch. This new dispatch-level test covers that gap.

## TEST release

Review the hotfix PR into Test, then deploy its reviewed backend source in a new TEST
release using the existing approved release procedure. This is a PHP backend fix;
deploying only a frontend build cannot fix it. No new frontend assets, migration,
password reset, MFA key change, `.env` overwrite, Nginx alias or Basic Auth removal
is required. Keep restore execution disabled. No live deployment was performed.

After deployment, use an approved TEST session to verify `/api/admin/staff` returns
200 JSON and `/admin/users` shows staff. Without that session the endpoint must return
401/403, never staff records. Also verify trailing-slash URLs and `/admin/led` routing.
If the old 404 persists, confirm the active backend release points to the hotfix and
refresh PHP-FPM/opcache according to the approved server runbook; do not change auth.
Rollback uses the retained previous backend release target; no database rollback.
