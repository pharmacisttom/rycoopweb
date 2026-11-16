# RC03 QA report

Date: 2026-11-16 (Asia/Bangkok). Target: TEST only. Branch: `codex/rc03-board-term-hardening`.

Implemented and tested locally; NOT deployed and NOT approved as MySQL/TEST release-ready. No environment database was opened, no operational import was run, and no production/member/dividend/financial records were changed. Import and copy tests execute exclusively against a disposable `sqlite::memory:` PDO installed before application database access.

## Actual evidence

Runtime: PHP 8.2.12, Node 24.21.0, npm 10.8.3, Git 2.54.0.windows.1. Browser checks used installed Google Chrome through Playwright because the bundled Chromium executable was unavailable.

A clean snapshot of the staged RC03 sources was extracted under ignored `outputs/rc03/review` and tested independently of the pre-existing uncommitted changes. Captured stdout is in evidence.txt.

| Check | Result |
| --- | --- |
| PHP syntax: changed PHP files and routes | PASS |
| `php tests/BoardTermManagementTest.php` | PASS, 90 assertions; delegates to isolated hardening suite |
| `php tests/PublicContentWorkflowTest.php` | PASS, 24 assertions |
| `php tests/MediaUrlTest.php` | PASS, 13 cases |
| `php tests/SecurityRegressionTest.php` | PASS, 73 assertions |
| `node tests/PublicContentReferenceTest.mjs` | PASS, legacy values/groups and rendered public pages |
| `node tests/BoardTermWorkflowBrowserTest.mjs` | PASS, mocked API in Chrome; preview, draft confirmation, CSRF header, stale error, distinct approve/publish, published label |
| `npm run build` | PASS; Vite warns about an existing chunk over 500 kB |
| React lint/typecheck | Not configured in package.json |
| TEST/MySQL concurrency, HTTP deployment smoke and real file download | NOT RUN; required before TEST release acceptance |

Hardening assertions include zero preview writes; 15/3/3/3 import contract; fallback; draft import preserving public output; full row/photo/edit preservation on re-import; force rejection; valid source/destination; duplicate/stale/foreign selection rejection; source/photo/group preservation; term 39 remaining draft; import/copy/publication rollback with injected failures; unapproved/unauthorized/MFA rejection; approval invalidation after edits; single current publication and explicit archive; public read zero writes even with a due scheduled row; BoardTerm precedence; malformed API IDs and 422/403 response contracts; active-only Document list/field contract and inactive-download rejection; board route authentication/CSRF registrations; actual CSRF 419 rejection; session actor identity overriding a supplied actor ID.

The legacy DocumentsWorkflowTest.php was not run: it opens the environment database, increments download counts, inserts/deletes records and resets counters. Equivalent read/denial contracts are verified by the new disposable fixture. A successful real file stream remains a TEST smoke check. Media URL behavior is unchanged and its 13 existing cases pass.

## Changed files

- app/Controllers/Admin/BoardTermWorkflowController.php — strict IDs and consistent responses; administrative workflow routes.
- app/Services/BoardTermService.php — draft-only safe import, immutable existing imports, draft edit guards, explicit authorized publication, no public scheduled writes; unsafe legacy full copy/schedule disabled.
- app/Services/BoardTermWorkflow.php — preview, selective copy, locks, snapshot validation, approval and required audit.
- app/Services/PublicContentService.php — three-line change defining complete BoardTerm graph precedence.
- config/routes.php — authenticated, permission-scoped, CSRF-protected workflow routes.
- src/components/admin/BoardTermWorkflowPanel.jsx — import/copy previews, selection, confirmation, approval/publication and statuses.
- src/pages/AdminBoardPersonnelPage.jsx — embeds workflow panel, preserves category/Public Content CRUD.
- tests/BoardTermManagementTest.php — safe compatibility entry point.
- tests/BoardTermWorkflowHardeningTest.php — disposable service/API/security/Document regression tests.
- tests/BoardTermWorkflowBrowserTest.mjs and tests/fixtures/BoardTermWorkflow.jsx — browser/API contract fixture.
- docs/rc03-board-term-hardening/ — this report, evidence, TEST deployment/rollback runbook and handoff prompt.

No migration: existing status enum remains unchanged; approval is a separately audited fingerprint of a draft. Existing published terms are archived only on explicit authorized publication. Scheduler execution is disabled. Reconciliation with force and unpreviewed legacy full-copy are rejected rather than retaining unsafe alternate paths.

Only RC03 changes are committed. Existing unrelated modified/untracked work is intentionally left in the workspace. In particular, only the three RC03 precedence lines from PublicContentService.php are staged; its prior advisor/photo and client-IP edits are excluded. The already-dirty BoardStaffController.php is excluded entirely.

## Residual release gates

MySQL/InnoDB concurrency and deployed HTTP/session behavior are not proven by SQLite or mocked browser tests. Validate them using TEST-RUNBOOK.md before any deployment acceptance. No schema uniqueness constraint is added for is_current; controlled service writers serialize on ordered term row locks. Direct SQL writers must not bypass this service. No production writes, deployment or merge is authorized by this report.
