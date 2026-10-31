# Staff list trailing-slash hotfix

Branch: `codex/fix-staff-api-trailing-slash`, target `Test`.

## Scope and necessary companion fix

Both frontend list consumers now use `/api/admin/staff/`:
`AdminControlCenterPage.jsx` and `AdminAccessPage.jsx`.
Action/parameterized paths (`/{id}/suspend`, `activate`, `revoke-sessions`, etc.)
are unchanged. The response remains `success`, `staff`, `invitations`, `roles`,
with existing nested MFA and Google metadata.

The inspected Test baseline is `09ee6d0`. Its actual declaration is `$r->get('')`,
not `$r->get('/')`; both generate the same trailing-slash route in the old Router.
However Request strips the trailing slash BEFORE dispatch. Raw regex matching
`/api/admin/staff/` is therefore not sufficient evidence that an HTTP request works.
A frontend-only URL change still fails on that baseline.

This branch includes the existing PR #10 Router fix (`9941860`) unchanged as a
companion ancestor. Its normalization makes actual Request/Router dispatch agree.
The frontend-specific commit is separate for review. No authentication/role/MFA/CSRF
logic or controller authorization is changed. Review #10 first; merging it separately
will reduce this PR to the remaining frontend/test changes. Do not deploy frontend
changes alone while leaving the old Router active.

## Failure state

The users page clears the old staff response when fetching, rejects a malformed
successful payload without a staff array, and does not render StaffDirectory after
an API failure. A successful empty array still correctly represents an empty roster.
Existing error text and manual refresh controls remain available.

## Regression evidence

- `StaffRouteDispatchTest.php` checks both frontend call sites against the actual
  registered handler after Request normalization. Actual controller dispatch remains
  guest 401, pending MFA 403, member/editor 403, verified Super Admin 200.
- `StaffPageIntegrationTest.mjs` starts an isolated PHP loopback server and uses
  the real App, AuthProvider and protected `/admin/users` route. A nonce-protected,
  test-only bootstrap prepares synthetic PHP sessions through Auth::login.
- The browser receives its identity from that PHP session. Staff API success is
  served by actual application Router, middleware and StaffManagementController;
  it is not a success-response stub. SQLite fixtures replace database access only.
- Verified all four top-level contract fields; both frontend calls use the list slash.
  Separate controlled 404/503/malformed-response cases prove no misleading staff table.
- The PHP fixture is outside `public`, refuses execution except cli-server on loopback
  with a random test nonce, and never touches live accounts, secrets or MySQL data.
  This is isolated session integration, not interactive Google/MFA enrollment or live TEST UAT.
- Screenshots: ignored `storage/tmp/staff-page-integration/*.png`; CI uploads images
  only, never its session files or bootstrap nonce.

Run the dispatch, permission, MFA, dual-control, security and frontend workflow suites,
React build, and `node tests/StaffPageIntegrationTest.mjs`. PHP needs pdo_sqlite and
mbstring; Node uses Playwright. `PHP_BINARY` and `PLAYWRIGHT_EXECUTABLE` optionally
select local executables. Existing browser presentation mocks were updated to the
exact trailing-slash list path.

## Release and rollback

Review CI for the exact head SHA, then use the existing approved immutable TEST
release process to deploy BOTH the companion backend fix and rebuilt frontend.
Do not edit the active release. No migration, .env overwrite, key rotation, password
reset or Nginx alias is needed. Keep 019/020 on hold; do not rerun 025. Preserve MFA,
Basic Auth and disabled backup restore execution. No merge or deployment was performed.

After approved TEST cutover verify the real Super Admin session, list records and
error handling, and guest/MFA/member denials. Roll back the backend/frontend release
targets together if needed. No database or key rollback is required.
