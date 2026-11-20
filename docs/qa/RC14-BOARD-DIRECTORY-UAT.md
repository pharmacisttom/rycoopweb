# RC14 Board Directory — TEST-first UAT

## Scope
Target page: `/app/admin/public-content?section=boards`

- Existing board list displayed as a stable-order table, with term 39 / term 38 / all filters.
- Search by name, position, workplace or ID; preserve the original order and allow up/down moves within a term.
- Multi-select checkbox and “remove from draft” only: no direct database delete; still requires the existing `/drafts` and `/publish` authorization workflow.
- Historical term 38 and active committee-group references are protected from selection/removal, matching server-side checks.
- Photo/name/button opens a modal containing existing per-member edit fields, photo upload and relevant draft/publishing history.
- Preserve existing board-term audit, committee copy (38 to 39), source URL, effective date and publishing history.
- All existing public-content backend routes and database schema remain unchanged.

## Critical safeguards
- Removal does not change published data until authorized publication. Before publishing, verify board group references and 15-member counts from actual appointment documents.
- When changing a board member, verify ID remains stable; avoid removing a member referenced by an active committee group.
- History shown in the modal filters version payloads by member ID and does not claim per-field change tracking.
- Do not upload real member/board personal data into disposable TEST environments without permission.

## TEST deployment (selective, no backend changes)
- Verify commit SHA and read the shell script before execution: `scripts/deploy-test-board-directory-rc14.sh`.
- The script discovers the active Nginx TEST baseline, copies it into a distinct release, then imports **only** `PublicContentAdminPage.jsx` and `BoardTermAdminRC06.css` from the reviewed Git commit.
- It runs `npm ci` with postinstall, builds the frontend, validates TEST database configuration, and switches only the TEST Nginx file. A failed switch/HTTP probe triggers config rollback; authorized browser UAT is still needed.
- Do not deploy the full RC14 branch over the selective RC03/RC13 TEST baseline.

## Gate before merge or deploy
1. `npm ci` (project postinstall generates scoped AdminLTE CSS)
2. `npm run build`
3. `php tests/BoardDirectoryRc14ContractTest.php`
4. `php tests/SecurityCookieRc12ContractTest.php`
5. TEST browser UAT: selection/unselection, select visible term, multi-remove and cancel, save draft, publish by authorized reviewer, restore version, mobile table scroll, modal editing, photo upload, modal history.
6. Confirm TEST uses only `rycoopweb_test`, record release path and backup/rollback.
7. Production must not be changed until the tests pass; use selective deployment preserving production backend/authentication.

## Status
Source patch and isolated TEST deployment script submitted to GitHub. GitHub's isolated build has passed; production build/QA gates and authorized TEST browser UAT must be checked on the latest commit. TEST and Production are NOT YET DEPLOYED for RC14.
