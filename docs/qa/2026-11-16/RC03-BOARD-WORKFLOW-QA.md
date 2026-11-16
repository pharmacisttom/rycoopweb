# RC03 Board Term Workflow Hardening — QA Report (TEST-only)

Date: 2026-11-16

## Scope
Base: `feature/rc03-board-term38-import-term39-transfer`.
Branch: `feature/rc03-board-workflow-hardening-20261116`.
Production: **NOT TOUCHED**. Database imports, publications and deployments: **NOT EXECUTED**.

## Changes
- Term 38 import is create-only and inserts `draft`, `is_current=0`, `published_at=NULL`.
- Re-running import against an existing Term 38 returns `already_exists` without reconciling existing members/photos/groups. Intentional reconciliation remains a separate action and needs its own review.
- Import no longer archives `public_content_revisions` or demotes the current published term.
- `getPublicActiveTerm()` no longer publishes scheduled terms from a public GET. Scheduled publication must be moved to an authenticated/audited scheduler before enabling this feature.
- Selective transfer validates exactly Term 38 -> Term 39, unique positive selected IDs, and target `draft` status. Checks all requested IDs exist, and locks relevant term rows during transfer.
- Published BoardTerm overlay is applied after published public-content revisions for consistent precedence.
- Frontend import labels make draft-only semantics explicit.

## Evidence: static code checks
These assertions must be verified in the branch diff: draft insert, no import archive, no publish-on-GET, source/target term guards, and updated React labels.

## Test execution (NOT RUN)
| Check | Status |
| --- | --- |
| PHP syntax on changed service files | PENDING |
| React lint/build/typecheck (as configured) | PENDING |
| Import preview read-only | PENDING |
| Import Term 38 draft with fixture DB | PENDING |
| Repeat import preserves existing rows and photos | PENDING |
| Term 39 partial copy; source unchanged | PENDING |
| Concurrent imports/transfers and uniqueness constraints | PENDING |
| Unauthorized/MFA/CSRF access | PENDING |
| No writes on public GET | PENDING |
| Public Content revision precedence/fallback | PENDING |
| Scheduled publish via privileged job | BLOCKED: job not implemented |
| Media and Document API regression | PENDING |
| Welfare and entire frontend/backend CRUD E2E | OUT OF SCOPE / PENDING |

## Risks & blockers
1. The DB schema's unique constraints for `board_terms.term_number` have not been verified. Concurrent creation of Term 38/39 needs migration review or unique-index evidence.
2. Existing `reconcileTerm38()` and older Admin endpoints need separate authorization and mutation review.
3. Scheduled publication must not rely on the removed GET side effect; implement and validate a privileged scheduler.
4. End-to-end RBAC, MFA-complete session, rollback, audit transactionality, and idempotent selective transfer require real TEST evidence.
5. Production code and TEST-VPS may not match this GitHub base; reconcile branch changes and TEST patches (Media/Document) before deployment.

**Release gate: NOT APPROVED. Do not merge or deploy until blockers are resolved and tests pass.**
