# RC03 TEST Deployment and Rollback Runbook (NOT EXECUTED)

Target: TEST only `test.rayongcoop.org`, TEST DB `rycoopweb_test`. Never deploy to Production automatically.

## Pre-deployment gates
1. Review the PR diff and ensure the base branch is correct; compare with the actual TEST VPS files.
2. Back up TEST DB and application directory, record SHA-256 checksums, record active TEST release path and nginx PHP upstream.
3. Verify TEST DB name from effective server environment without printing credentials.
4. Verify schema constraints: `board_terms.term_number` uniqueness and `board_term_members` identity rules.
5. Verify existing TEST MediaController, DocumentController routes and login/PDPA/MFA changes are preserved.
6. Pass php lint, available PHP tests, frontend lint/typecheck/build and fixture-based integration tests. Do NOT use Production data.

## Manual TEST-only rollout (after approval)
1. Check out approved commit to a *new* TEST release folder, never overlay the live TEST directory blindly.
2. Configure the release against `rycoopweb_test` only; verify filesystem permissions and PHP-FPM.
3. Run only reviewed migrations against a disposable clone first; take another backup if migrations are necessary.
4. Deploy TEST static frontend built from that same commit.
5. Switch TEST upstream/root atomically, verify health, anonymous denial of admin API, public board fallback and JS assets.
6. With authorized TEST admin account and non-production fixtures, test Import Preview, Draft Import 38, repeat import, selective copy to Draft 39, explicit approval/publish, audit logs and E2E. Never test with live member or finance data.

## Rollback
- For code-only changes, restore prior TEST release directory and static build; reload TEST-only nginx config after `nginx -t`.
- For migrations/data mutations, use a documented point-in-time TEST DB restore. Code rollback alone does not undo DB writes.
- Recheck login, MFA, PDPA, Media API, Document API, board page and audit logs.
- Record before/after SHA and time of rollback.

## Production gate
No PROD deploy or merge until explicit authorization, passed QA, DBA sign-off and a tested rollback plan.
