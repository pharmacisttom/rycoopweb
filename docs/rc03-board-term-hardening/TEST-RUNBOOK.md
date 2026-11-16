# RC03 TEST deployment and rollback runbook

Scope: pharmacisttom/rycoopweb, TEST only, database `rycoopweb_test`. Nothing in this runbook authorizes production access. Deployment, operational imports, approval and publication were not performed during development.

## Release contents and prerequisites

Use branch `codex/rc03-board-term-hardening`. Review its commit/diff before deployment. Base commit: `9cb8cdf2f0744a6bdd5bbcef5c80fdb6d7b0a9fb`.

No new migration is required. Existing migration 030 must already provide the four InnoDB board-term tables, the unique term number, and cascading foreign keys. Approval uses required audit events containing a fingerprint of the term, members, groups and links; the storage status remains `draft` until publication. Every content change invalidates the approval fingerprint.

Only `people.view` plus `people.update` may import/copy/edit; `people.view` plus `people.publish` may approve/publish. Authentication must be a live MFA-complete backend session. Browser mutations require CSRF. Actor IDs always come from that session. Scheduling is deliberately disabled; an old due scheduled row remains untouched by public reads. The old full-copy endpoint returns validation guidance to use previewed selective-copy. Destructive `force` reconciliation is rejected.

## Before any TEST change

1. Verify the remote, branch and exact commit. Deploy the reviewed commit, not this developer's dirty workspace. Keep application credentials outside the release artifact.
2. Verify the target hostname and use a TEST-only database account restricted to `rycoopweb_test`. Execute `SELECT DATABASE()` and stop unless the result is exactly `rycoopweb_test`. Do not reuse production credentials.
3. Take a TEST application release backup and a consistent backup of `board_terms`, `board_term_members`, `board_term_groups`, `board_term_group_members`, and the relevant audit records. Record row counts, source member/group/link snapshots and custom image paths. Preserve media files.
4. Verify InnoDB, isolation level, unique term-number constraint and foreign keys. Do not truncate, reseed, reconcile or run the former environment-writing board test. `tests/BoardTermManagementTest.php` now delegates to the disposable SQLite suite.
5. Run the checks in QA.md. Run the MySQL concurrency checks below on TEST before approving release readiness. SQLite evidence does not verify MySQL locking.

## Manual deployment

Build with the locked dependencies (`composer install`, `npm ci`, `npm run build`) in an isolated release directory. Install reviewed PHP/source files and generated `public/app` assets as one release. Preserve environment configuration, uploads and existing database records. Switch the TEST release atomically and clear PHP opcode caches using the normal TEST process. No seeder or operational import is part of deployment.

## TEST verification and optional authorized workflow

1. Compare public board, Media and Document responses with the pre-deployment baseline. With no current published term, legacy catalog/settings plus eligible Public Content revisions remain the fallback.
2. Log in as an authenticated MFA-complete editor. Open Import Preview; compare counts 15 directors, 3 advisors, 3 auditors and 3 groups and review names. Confirm no table row count or public API response changes from preview.
3. Only after separate operator authorization, commit draft import. Verify term 38 is draft/non-current and the public response is unchanged. Re-import and compare complete term/member/group/link snapshots and custom images; every existing row must be unchanged. Force reconciliation must fail.
4. Select actual term 38 members and preview copy to term number 39. Confirm the actual destination ID (or absence) and selected members/groups. Confirm draft creation; compare term 38 snapshots, photos and all original links. Source records must remain identical; destination must be draft/non-current. Replay, stale preview, duplicate selection, foreign member ID, invalid term ID and published destination must fail without partial writes.
5. Verify anonymous, pending MFA, member and editor publication requests fail; missing CSRF must fail. An editor must not be able to approve.
6. An authorized publisher may approve the exact draft. Approval does not change public data. Modify the draft and verify publication now requires approval again. Explicit publication is a separate operator action: it archives the previous current term and promotes the approved draft in one transaction with required audit events. Query current published count; it must be exactly one.
7. BoardTerm publication owns boardMembers, boardGroups, advisors and auditors together. Public Content CRUD/history remains available, but those revisions act as fallback while a current published BoardTerm exists. Publication never rewrites or archives Public Content revisions.
8. Verify PHP audit entries for import_draft, approve, selective_copy, archive and publish, and before/after snapshots where applicable. Use only the isolated fixture for injected audit failures.

## MySQL concurrency release gate (not executed during review)

Use two independent TEST sessions with different valid CSRF tokens. Preview the same selected members into draft 39, then submit both copies concurrently. Expect one success and one conflict/validation response; verify no duplicate members, groups or links and identical source data. Preview again, change a source member or destination group in the other session, then submit the old preview: expect 409 and no writes.

Approve two separate fixture drafts, then publish concurrently. The ordered `SELECT ... FOR UPDATE` on board_terms serializes publication/copy/edit; accept a retriable deadlock/conflict if returned, never duplicate current rows. Verify one current published term and corresponding archive/publication audit records. Test with the actual TEST isolation level. Do not create fixture terms containing real member/financial data. Do not test production.

## Rollback

Stop board administration on TEST and capture the failing release's audit trail and board snapshots first. If only draft imports/copies occurred, leave all draft rows intact and restore the previous application/assets release; never delete term 38 or term 39 as a rollback shortcut.

The previous application had public-GET publication side effects: disable public board access during rollback until that behavior is guarded, and do not serve the old release against due scheduled terms. Prefer a forward fix retaining this read-only public path.

If publication occurred, prefer an authorized forward correction reviewed against the preserved snapshots. Do not blindly restore the whole database or republish archived rows through direct SQL. If restoration is necessary, obtain explicit TEST restore authorization, suspend writers, restore only the coordinated board-table snapshot with referential integrity, preserve the post-change audit evidence, and record the restore. Never restore member/dividend/financial tables. Verify original images, source rows, fallback/active public payload and current-term count before reopening TEST.
