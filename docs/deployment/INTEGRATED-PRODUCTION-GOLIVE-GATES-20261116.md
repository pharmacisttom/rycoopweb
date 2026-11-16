# RYCOOP Integrated Production Release — CRITICAL GO-LIVE GATE

**Date:** 2026-11-16
**Status:** Candidate only — not deployed. No production migrations, merges, nginx edits, file overwrites or operational data mutations.

## Verified topology
Read-only GitHub Actions run `43908721158`:
- Main HTTPS SPA root: `/var/www/rycoopweb/production-releases/member-calculator-rc03-20261111-public`
- PHP proxy: `127.0.0.1:18092`; production loopback PHP public root: `/var/www/rycoopweb/production-releases/member-dividend-unlock-rc01-20261111/public`
- PHP-FPM: `/run/php/rycoop-prod.sock`
- Test is independently located at `test-v2` and `test-v2-static`.
- There is **no verified current production symlink**, and no proof a full application lives in the frontend release directory.

## Scope expected by operator
Integrated rollout should eventually include all independently validated public and admin modules:
- Expanded About history, strategic vision and five founding committee members.
- Activity photo album create/update/delete-archive, upload, cover, caption, reorder, explicit publish, public published-only view; migration `031_create_activity_photo_albums.sql`.
- Admin media and documents; board terms; welfare; content management; website routes.
- Member/PDPA/dividend interfaces ONLY after explicit end-to-end compatibility checks. No live member or financial writes in release preparation.

## Mandatory UI ↔ API ↔ Database contract gate
A green React build is not sufficient. For EACH module, document and pass:
1. User-facing UI action and route, HTTP method, API handler/controller.
2. DB table/columns, migrations and preservation of legacy records.
3. Create: validation, insert and audit log, UI refetch and persistence after reload.
4. Read: list/detail, pagination, status filtering and authorization.
5. Update: optimistic/stale behavior, transaction, audit log, reflected UI state.
6. Delete/archive: right permission, expected soft/hard delete, no dangling media or unintended data loss, refreshed UI.
7. Upload/download where applicable: storage containment, file type, size, performance and access control.
8. Negative cases: unauthenticated, MFA-incomplete, member, wrong role, invalid CSRF, cross-user access, invalid IDs.
9. Public GET/HEAD read-only behavior and unpublished/archived content isolation.
10. Real TEST MySQL, HTTP requests, browser UAT, concurrent updates, mobile responsiveness and resettable fixtures.

### Activity Gallery static test
`php tests/ActivityFrontendBackendContractTest.php` asserts paths, methods, handlers, middleware, basic UI tokens, and migration columns. **Static checks cannot establish that real CRUD persists correctly**.
Additional CI tests: `ActivityGalleryRouteTest.php` and disposable `ActivityGalleryDataTest.php`.
Activity Gallery admin UI now re-reads saved album state after update; archiving does not attempt to fetch an archived resource and only clears selection after success.

### Activity Gallery TEST runtime acceptance
- Apply migration 031 to **TEST database only after verified backup and review**, inspect resulting table structures and permissions.
- Test login + staff MFA and role. Create draft, reload, edit, upload 1 JPEG/PNG and 20 images, create thumbnail and metadata removal, cover/caption/reorder/remove, publish, then archive.
- Verify database values, file existence/ownership, API response, thumbnail URL, audit entries and role isolation after each action.
- Verify no public access to draft, archived album or private storage; Nginx must not serve private image files directly.
- 200-image queue/large-image workload must be performed using synthetic data on isolated TEST; no actual member/finance data.
- No automatic publish; leave workflow Draft unless tested manually.

## Critical blockers before Production
- Candidate branch has drift versus `Test` (at snapshot: 121 commits ahead, 17 behind). Resolve conflicts/rebase and CI-test the **exact merged SHA**. Do not deploy a draft branch as-is.
- Current CI does not prove all admin APIs against live MySQL/HTTP in TEST; legacy and Activity data migration must be validated.
- Production currently splits SPA static root and PHP proxy root, and numerous past releases are partial. Verify release switch contract before writing any files.
- Snapshot/backup existing frontend static assets, backend config, DB and media. Demonstrate restoration and exact-commit rollback on TEST or disposable deployment.
- PHP GD WebP and resource limits/permissions, private photo path and media retention must pass TEST.
- Nginx location routing, /app/about, /app/activities, /app/admin/activities, /api/public/activities and member portal smoke must pass following a staged switch.
- Role and PDPA access gates, including member dividend, must not be relaxed.
- Release approval, narrow-scoped SSH workflow and post-deploy monitoring must be recorded.

## Cutover and rollback principles
- Generate immutable frontend and backend artifacts from one approved commit. Existing production source and .env remain unchanged until validated.
- Stage alongside existing releases with rollback pointer and explicit health gates.
- No DB migration without DB backup, compatible rollback and tested data-preservation path; irreversible DB changes require separate hold.
- Switch SPA and backend only if their API contract/version are compatible, and inspect server-specific Nginx includes/proxies.
- Capture HTTP and authorization health post cutover; rollback atomically on first failure.
- No user-facing claim of successful deployment until verified publicly and via authenticated TEST/production smoke.
