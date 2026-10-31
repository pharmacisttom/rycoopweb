# RYCOOPWEB 3.0 — TEST Migration Runbook & Execution Guide

**Environment:** TEST (`https://test.rayongcoop.org`)
**Path:** `/var/www/rycoopweb/test-v2`
**Target Database:** `rycoopweb_test`
**Branch:** `codex/legacy-member-dividend-migration`

---

## 1. Pre-Migration Prerequisites & Safety Checklist

- [ ] **Database Backup:** Create a pre-migration snapshot of `rycoopweb_test`
  ```bash
  mysqldump -u root -p rycoopweb_test > /var/backups/rycoopweb_test_pre_member_migration_$(date +%Y%m%d_%H%M%S).sql
  ```
- [ ] **Feature Flags Verification:** Confirm `.env` has:
  ```ini
  FEATURE_MEMBER_PORTAL=false
  FEATURE_MEMBER_LOGIN=false
  BACKUP_RESTORE_EXECUTION_ENABLED=false
  ```
- [ ] **Migration Check:** Confirm Migrations 019, 020, and 025 are **NOT** executed.
- [ ] **Staging Dump Placement:** Upload `rayongcoop_db.sql` to a secured non-web-accessible directory (e.g. `/var/staging/rayongcoop_db.sql`) with permissions `0600`.

---

## 2. Step-by-Step Execution Plan

### Step 1: Execute Dry Run on TEST
```bash
cd /var/www/rycoopweb/test-v2
php scripts/legacy-member-migrate.php \
  --source=/var/staging/rayongcoop_db.sql \
  --dry-run \
  --batch-tag=MIG-TEST-$(date +%Y%m%d-%H%M)
```
- Verify `Status: DRY_RUN_PASSED`.
- Compare output totals with Reconciled Financial Figures (71,019,297.45 THB for 2567 and 72,635,733.40 THB for 2568).
- Confirm zero database modifications.

### Step 2: Execute Committed Live Migration on TEST
*(Only after explicit approval)*
```bash
php scripts/legacy-member-migrate.php \
  --source=/var/staging/rayongcoop_db.sql \
  --commit \
  --batch-tag=MIG-TEST-$(date +%Y%m%d-%H%M)
```
- Expected outcome:
  - 2,508 users inserted (with role `member`)
  - 2,508 members inserted (with leading-zero `member_no` preserved)
  - 4,855 dividend records inserted across 2567 & 2568
  - 2 batch run entries created in `member_import_runs`

### Step 3: Post-Migration Verification Queries
```bash
mysql -u root -p rycoopweb_test -e "
SELECT 'Users' AS entity, COUNT(*) AS count FROM users
UNION ALL
SELECT 'Members', COUNT(*) FROM members
UNION ALL
SELECT 'Dividends 2567', COUNT(*) FROM member_dividends WHERE year = 2567
UNION ALL
SELECT 'Dividends 2568', COUNT(*) FROM member_dividends WHERE year = 2568
UNION ALL
SELECT 'Import Runs', COUNT(*) FROM member_import_runs;
"
```
Verify that:
- Existing Super Admin (ID 1) remains intact:
  ```bash
  mysql -u root -p rycoopweb_test -e "SELECT id, username, email FROM users WHERE id = 1;"
  ```
- Existing staff accounts maintain their original roles:
  ```bash
  mysql -u root -p rycoopweb_test -e "
  SELECT u.id, u.username, r.slug
  FROM users u
  JOIN user_roles ur ON u.id = ur.user_id
  JOIN roles r ON r.id = ur.role_id
  WHERE r.slug != 'member';
  "
  ```
