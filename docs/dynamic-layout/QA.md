# Dynamic Layout & Gallery QA/UAT report

Date: 2026-11-18, Asia/Bangkok. Branch: `codex/dynamic-layout-gallery`.

**Code and isolated checks completed; remote TEST UAT NOT executed. Production readiness is NO-GO.** No migration, deployment or service restart occurred on TEST/Production. The approved TEST SSH profile/key/trusted host key are missing locally; connection work stopped per the user's explicit instruction. See TEST-RUNBOOK.md for the confirmed connection details and remaining gates.

## Executed evidence

The final intended source set was exported into ignored `outputs/dynamic-layout/review`, with a separate Composer runtime copy, and checked without the pre-existing dirty workspace changes. A legacy confirmation helper used native confirm in automated browsers; the feature now calls the approved SweetAlert confirmation API explicitly through `layoutAdminActions.js`. The final clean browser check passed after that correction. Original work remains unstaged/uncommitted, including the prior CI workflow, catalog CRUD, client-IP changes and other feature work. Only the necessary Request body parser is staged from that already-dirty shared file.

| Check | Actual result |
| --- | --- |
| PHP syntax | PASS, 14 changed PHP source/test files in the clean source set |
| DynamicLayoutTest.php | PASS, 92 assertions: tree/category cycles, CRUD, slugs, stale edits, pagination beyond twenty albums, RBAC/MFA/scopes, signature/size/EXIF/path checks, immutable snapshots, replay rejection, rollback, required-audit failures, public reads and route authentication/CSRF |
| DynamicLayoutHttpTest.mjs | PASS: real PHP HTTP router/session, CSRF 419, JSON PATCH/DELETE, persisted SQLite CRUD, real multipart files, decoded WebP/private/public reads, failed-batch file/DB cleanup, TOTP publication and replay rejection |
| DynamicLayoutBrowserTest.mjs | PASS: actual PHP-backed editor, create/nest/delete sections, nested categories, albums, multi-upload, alt/caption, keyboard and drag ordering, preview, saved draft, actual TOTP publish and rollback, public metadata/album/lightbox keyboard, no overflow at 1440/768/375 |
| DynamicLayoutRegressionTest.mjs | PASS: SHA-256 comparison proves all eight existing homepage JSX blocks and their conditional expressions are unchanged |
| BoardTermManagementTest.php | PASS, 90 assertions |
| PublicContentWorkflowTest.php | PASS, 24 assertions |
| SecurityRegressionTest.php | PASS, 73 assertions |
| MfaWorkflowTest.php | PASS, 19 assertions |
| NewsGalleryTest.php | PASS, 23 assertions |
| MediaUrlTest.php | PASS, 13 cases |
| PublicContentReferenceTest.mjs | PASS, reference values/groups and rendered public pages |
| npm run build | PASS; Vite reports a bundle-size warning over 500 kB |
| ESLint / TypeScript | Not configured in this JavaScript repository; no lint/typecheck success claimed |
| Disposable real SQL migration/concurrency | PASS on **MariaDB 10.4.32** at loopback port 13318, fresh workspace-only datadir: migration twice, singleton/permissions idempotency; two independent real-TOTP publishers produce one success, one stale 409, one revision/current pointer/audit. Server was shut down afterward |
| MySQL 8 CI | Dedicated isolated workflow added; not claimed passed until a recorded GitHub result is available |
| Real persisted TEST acceptance | NOT RUN; requires approved SSH materials, read-only preflight, backup and explicit change authorization |

Actual stdout, syntax output and representative clean browser screenshots are under `evidence/`. The browser's visual AuthContext identity is a presentation fixture; backend authentication, CSRF, TOTP, SQL persistence and upload processing run through the actual PHP implementations. These are local fixture records, never claimed as TEST records. Local MariaDB evidence is not relabeled as MySQL 8 evidence.

## Acceptance mapping

| Requested workflow | Local implementation/evidence | TEST status |
| --- | --- | --- |
| Open Layout Manager and create activity section | Browser/HTTP PASS | OPEN |
| Multiple nested activity categories and section hierarchy | Browser + cycle tests PASS | OPEN |
| Create albums and upload images | Real multipart/decoding/persistence PASS | OPEN |
| Display/device selection | Editor settings/shared renderer; masonry and mobile preview exercised | OPEN |
| Reorder sections | Actual DnD and keyboard controls PASS | OPEN |
| Preview and Save Draft | Browser/API; public unchanged before publish PASS | OPEN |
| Publish with required MFA | Actual TOTP verification, exact scope, RBAC and replay tests PASS | OPEN |
| Content appears publicly | Local published API and public album page PASS | OPEN |
| Edit without breaking existing sections | Immutable snapshot and exact eight-block regression PASS | OPEN |
| Roll back previous publication | Actual browser TOTP rollback + snapshot/draft preservation tests PASS | OPEN |

## Delivery and limitations

Architecture/schema/API contracts: ARCHITECTURE.md. Security review: SECURITY.md. Migration: `database/migrations/032_dynamic_layout_gallery.sql` (additive, six tables, no role grants). Deployment/backup/rollback/UAT: TEST-RUNBOOK.md. Production gates: PRODUCTION-READINESS.md. Handoff prompt: HANDOFF-PROMPT.md. The Git commit provides the complete changed-file list and unified diff; a local diff artifact is generated under ignored outputs.

No library upgrades, production writes, main merge, operational imports or financial/member data changes were performed. The new publish workflow owns layout/gallery only; Board Term and existing Public Content CRUD remain separate. History and historically published images are deliberately retained. Large deployments must validate publication duration/limits and the private persistent media path on the actual TEST topology.

Do not approve a release until all TEST items above are signed with real IDs, timestamps, API status/audit evidence and screenshots. The user's corrected SSH instructions take precedence over the earlier guessed connection attempt; no further SSH connection is attempted without the approved profile/key/trust material.
