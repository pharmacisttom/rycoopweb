# RC03 follow-up hardening — 2026-11-16

## Source and boundaries

Parent branch: `codex/rc03-board-term-hardening`; reviewed parent commit `43263743647e8f79db67eb452539d15529db61d1`.

This follow-up changes only BoardTerm service/trait tests and this document. **No deployment, MySQL connection, operational import, approval or publication was performed.** Production and member/dividend/finance data are out of scope.

## Additional fixes

1. `getSelectionCandidates` rejects a target other than Term 39 and rejects a source other than Term 38. The preview and commit endpoints now share the same allowlist validation.
2. `createTerm` now uses a required audit within the same transaction as draft insertion, with no nested PDO transaction when invoked by `selectiveTransfer`. In a direct creation a failed audit must roll back the term row. Existing `term_number` uniqueness remains required for concurrent requests.
3. Disposable SQLite test adds: invalid destination 40 rejected; required create audit failure rolls back term row.

## Evidence classification

- Source review / GitHub diff: performed.
- Earlier parent evidence: see `docs/rc03-board-term-hardening/evidence.txt`, including 90 BoardTerm assertions, 24 PublicContent assertions, 13 Media URL cases, 73 security assertions, Chrome mocked browser and Vite build.
- **New follow-up tests: written but NOT EXECUTED** in this tool session; PHP lint, PHP/SQLite tests, frontend build/CI and MySQL testing must be re-run at this follow-up commit SHA before approval.
- GitHub branch state does not establish a clean or dirty developer workspace: the GitHub commits include only the explicit changed files.

## Mandatory operator-controlled TEST acceptance gates

**Before mutating any board rows**: verify target hostname, DB user grants limited to `rycoopweb_test`, `SELECT DATABASE()`, current repo SHA, four InnoDB board tables, unique term number constraint, foreign keys, effective isolation level, full original term 38 snapshots including media hashes and group links, and backup/restore plan. Never reuse production credentials or run the legacy environment-writing DocumentsWorkflowTest.

After separate, explicit authorization for disposable fixture writes, execute the runbook's two independent HTTP sessions and MySQL transaction tests: concurrent copy 38 → draft 39, one success/one conflict, stale preview 409, duplicate and foreign ID rejection, rollback, single published current term, required audit, MFA/RBAC/CSRF matrix and GET zero writes. Publishing any term additionally requires explicit separate operator authorization; do not publish solely to pass a test on the operational dataset. Use a disposable TEST clone for publication concurrency where feasible.

Verify real approved Document HTTP streaming and Media paths on TEST only, without exposing session cookies, sensitive document content or customer data. The parent SQLite Document contract does not prove live file access.

## Deployment decision

**NO-GO / PENDING TEST gates**. No merge/deploy or production-ready claim. A future release must reconcile the TEST VPS's manually installed Media/Document patches with the exact reviewed GitHub commit, and must compare browser assets and PHP backend as one coherent release. Rollback must not re-enable old public GET schedule writes.
