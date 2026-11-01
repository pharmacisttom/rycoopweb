# RYCOOPWEB 3.0 — TEST Legacy Member Migration Runbook

**Status: REVIEW / NO-GO for live import.** Target is TEST only: `rycoopweb_test`, `https://test.rayongcoop.org`. Never touch production, production DB, application keys, or existing `.env`.

## Mandatory gates

1. PR #15 is reviewed and all PHP 8.3/8.5, MySQL 8, frontend and browser CI checks pass on the **exact final head**. Confirm no merge conflicts and record the SHA.
2. DBA confirms the current target counts, role assignments, `users.id=1` identity, schema, required unique constraints and foreign keys. Record a SHA-256 checksum and location of a verified backup; restore-test backup in an isolated database. Do not publish personally identifying data.
3. Existing TEST flags must ALREADY be `FEATURE_MEMBER_PORTAL=false`, `FEATURE_MEMBER_LOGIN=false`, and `BACKUP_RESTORE_EXECUTION_ENABLED=false`. If any flag is not compliant, **STOP**; the CLI has no permission to edit `.env`.
4. Migrations 019, 020, and 025 must not be run. Any new database structure (the `legacy_member_mappings` provenance table) requires its **own reviewed DBA change and explicit approval** before a dry run. The importer itself must not run schema DDL and must use a low-privilege account.
5. The source SQL backup must be operator-verified for size, hash, provenance, access control, all expected INSERT records, referential integrity, and financial totals. Store outside webroot with mode 0600 and exclude from Git/public artifacts.
6. Database target is forcibly checked: `rycoopweb_test` (disposable `rayongcoop_ci` only for the CI harness). No bypass switches.

## Preflight, read-only (VPS)

```bash
set -euo pipefail
BASE=/var/www/rycoopweb
readlink -f "$BASE/test-v2"
readlink -f "$BASE/test-v2-static"
sudo mysql rycoopweb_test -e "SELECT 'users' entity, COUNT(*) n FROM users UNION ALL SELECT 'members', COUNT(*) FROM members UNION ALL SELECT 'member_dividends', COUNT(*) FROM member_dividends UNION ALL SELECT 'member_import_runs', COUNT(*) FROM member_import_runs;"
sudo mysql rycoopweb_test -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='rycoopweb_test' AND TABLE_NAME='legacy_member_mappings';"
```

Verify the three feature flags in an access-controlled manner, without printing credentials or keys. If `FEATURE_MEMBER_LOGIN=true` (observed during earlier TEST preflight), this gate is **BLOCKED** and must not be bypassed in TEST. Do not edit `.env` within this request.

## Database backup

Only after explicit backup authorization, create a timestamped, owner-only backup of **TEST** using `mysqldump --single-transaction --quick --routines --triggers --no-tablespaces rycoopweb_test`. Verify nonempty output, checksum, and restore in a separate verification database before any commit.

## Dry run (after approved ledger provisioning and flags preflight)

```bash
cd /var/www/rycoopweb/test-v2
php scripts/legacy-member-migrate.php --source=/var/staging/rayongcoop_db.sql --dry-run --batch-tag=MIG-TEST-DRY-YYYYMMDDHHMM
```

Record baseline and post-run row counts **including ledger mappings, users, user_roles, members, member_dividends, member_import_runs and audit_logs**, plus financial totals, before declaring Zero Mutation. A `DRY_RUN_PASSED` message alone is not proof. MySQL DDL is not rollbackable; never create tables inside the dry run.

Expected source: 2,508 member records, 2,508 permitted member accounts, 4,855 dividends (2567: 2,429; 2568: 2,426); net 2567: 71,019,297.45 THB, net 2568: 72,635,733.40 THB (total 143,655,030.85 THB). These are reference targets to compare against actual source and target results, not proof that TEST was migrated.

## Committed import — separate approval required

Only after a new explicit approval for the exact reviewed code SHA, source dump hash, schema, backup and batch tag, run `--commit` against TEST and capture evidence. Do **not** run `--commit` in this phase. Accounts imported with `inactive` status pending secure identity verification; Member Portal stays disabled.

After any committed import, verify identities, role ceiling, member_no leading zeros, batch mapping rows, referential integrity, exact yearly financial totals and no unrelated data changes. Abort acceptance on any mismatch.

## Rollback

Never run `--rollback` without a rollback impact preview and database snapshot. See `docs/migration/ROLLBACK_PLAN.md`. Prefer an isolated restore rehearsal and surgical rollback only when provenance is complete and no later dependent batch exists.
