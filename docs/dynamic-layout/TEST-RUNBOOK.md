# TEST deployment, UAT and rollback

**Current remote status: blocked before connection.** The user confirmed host `119.59.116.32`, user `coopadmin`, expected port 22/profile `rycoop-test`, URL `https://test.rayongcoop.org`, database `rycoopweb_test`. The local machine has no configured rycoop-test profile, authorized SSH key location or trusted known_hosts/fingerprint. Per user instruction, connection attempts stop here. Do not guess credentials, create a key, accept an unverified host key, or use `StrictHostKeyChecking=no`. No TEST migration/deployment/service restart has occurred.

## Access and read-only preflight

An operator must provision the approved profile/key and independently verify the server's SSH host-key fingerprint. Then connect through `ssh rycoop-test`, with strict host checking. First inspect only: host/user identity, TEST backend/static symlink targets, dedicated TEST FPM process, existing `/app/admin/layout` route/source, Nginx TEST mapping, actual MySQL version/schema, permissions and TEST upload/private paths. Confirm `SELECT DATABASE()` is exactly `rycoopweb_test` using separate TEST credentials. Do not print .env, passwords, private keys, TOTP secrets, session cookies or database dumps into the chat/logs. Do not read or change production credentials.

Compare deployed source to this branch before choosing a release. The supplied manager baseline is absent in this checkout; an existing manager on TEST requires reconciliation, not blind replacement. Verify disk/memory/GD-WebP availability and actual MySQL 8 DDL behavior. This remote preflight must finish before any migration or restart; obtain authorization for concrete TEST changes afterward.

## Backup and migration procedure (operator-run after preflight/authorization)

1. Record exact current TEST application/assets release and publication IDs. Make a consistent backup of **rycoopweb_test** to a protected TEST backup directory; verify nonempty backup, restore procedure and checksum. Keep backup credentials in a protected defaults file, not command arguments/chat. Preserve TEST uploads and the new private gallery storage. Never use or back up production as part of this release.
2. Build the reviewed commit in an isolated release directory using locked Composer/npm dependencies. Run QA commands. Do not deploy from the dirty developer workspace.
3. Inspect migration `database/migrations/032_dynamic_layout_gallery.sql` against the actual TEST schema. It is additive/idempotent, registers three permissions and does not grant existing roles. Apply only to the verified TEST connection after backup. Check all six new InnoDB tables, keys, foreign keys, singleton row and permission rows. Do not run the isolated MySQL fixture against the real TEST database: it requires an empty disposable database and seeds synthetic accounts.
4. Set PHP GD WebP, fileinfo, mbstring and upload settings: upload_max_filesize=5M, post_max_size at least 24M, max_file_uploads at least 12, memory_limit at least 256M; align the TEST reverse proxy body limit. Rejected oversize requests must remain errors.
5. Provide persistent TEST-only `storage/private/layout-gallery` across releases, owned/readable by the TEST PHP worker, with private parent permissions. The private folder must have no web-server alias/static mapping. Only the authenticated/published file controllers serve its WebP bytes. Preserve existing uploads/logs paths. Do not recursively chmod/chown existing application storage.
6. Atomically switch only the TEST release/assets after approval, and clear only dedicated TEST opcode cache/FPM if necessary. Do not invoke the existing deployment script blindly: it includes a shared Nginx reload. Do not restart/reload production or shared services in this task.

## Real persisted TEST acceptance checklist

Use dedicated authorized UAT admin/editor/publisher identities, not client-supplied actor IDs or login bypasses. Keep a signed record of IDs, screenshots, status codes, row counts, publication/version IDs, audit IDs and original homepage responses. Fixture screenshots do not satisfy this checklist.

- Open `/app/admin/layout` with the real secure session; verify anonymous, pending MFA and missing permission failures. Confirm the original homepage and existing sections/URLs remain intact before first publication.
- Create “รูปกิจกรรมของสหกรณ์”, child sections, multiple nested activity categories and two albums; edit, archive/restore and delete a childless draft section. Reject parent cycles, invalid IDs, duplicate slugs, foreign image IDs and deletion of referenced categories.
- Upload multiple real approved activity images; verify MIME/signatures, size/pixel limits, WebP dimensions, captions, alt text, image order and selected cover. Verify EXIF/GPS removal. An authenticated private image URL must fail anonymously and no unpublished ID must load from a public URL.
- Choose grid/carousel/masonry/cards and desktop/tablet/mobile settings. Drag/drop and keyboard-reorder sections. Confirm homepage placement and navigation placement are independent.
- Preview local edits and the authenticated full saved draft. Save Draft; compare public output and current publication pointer unchanged. Verify all existing homepage blocks still work, including calculators, links and conditional statistics.
- Attempt publication with only login MFA, wrong scope, expired/replayed grant, editor-only role and missing CSRF; expect rejection. With authorized fresh TOTP, publish; verify one current pointer, immutable section/album/image snapshot and required audit in the same transaction.
- Edit draft text/album/images after publication and confirm public output unchanged until another authorized publish. Open public album detail, pagination/category filters, metadata/canonical URL, lightbox keyboard navigation and responsive layouts.
- Submit two simultaneous publishers holding the same version from independent valid sessions; expect one success and one stale 409 (or a retriable DB deadlock conflict), one new revision/pointer/audit and no partial album snapshots. Test edit-vs-publish ordering under actual TEST isolation level.
- Roll back through history with fresh `layout.rollback` TOTP. Verify a new immutable publication restores earlier layout/albums/images while preserving the working draft. Run existing News/Media/Document/Board Term/session regressions.

## Rollback

For content, prefer the authorized audited rollback API; it preserves draft and history. For code, stop TEST administration, capture logs/audit IDs and restore both prior TEST backend/static release links atomically. Preserve all new tables, publication rows and private files; do not DROP tables, delete images, restore the entire database blindly, or touch financial/member/production records. The old application ignores new layout tables and retains its original homepage. Keep persistent private files accessible if returning to the new code. If a database restore is unavoidable, obtain separate TEST restore approval and preserve the post-change audit trail first.

## Repeatable local/CI checks

`php tests/DynamicLayoutTest.php`, `node tests/DynamicLayoutHttpTest.mjs`, `node tests/DynamicLayoutRegressionTest.mjs`, `node tests/DynamicLayoutBrowserTest.mjs`, `npm run build`. Browser tests need Playwright Chromium or an approved `PLAYWRIGHT_EXECUTABLE`. Existing BoardTermManagement/PublicContentWorkflow/MediaUrl/SecurityRegression/MfaWorkflow/NewsGallery tests are isolated and were also run.

`tests/DynamicLayoutMySqlTest.php` runs only against a fresh disposable loopback database when `DYNAMIC_LAYOUT_ISOLATED=1` and an explicit DSN `mysql:host=127.0.0.1;port=PORT;dbname=rycoopweb_test` plus separate fixture credentials are supplied. It refuses any nonempty database. The dedicated GitHub workflow provisions its own MySQL 8 service and never deploys.
