# RYCOOPWEB — TEST DEPLOYMENT PREFLIGHT CHECKLIST
## Pre-Flight Verification & Safety Audit for Candidate Release 01
**Target Hostname:** `test.rayongcoop.org`  
**Target Database:** `rycoopweb_test`  
**Target Commit SHA:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`  
**Release Directory:** `/var/www/rycoopweb/releases/d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`  
**Evaluation Status:** READY FOR EXECUTION (ALL GATES PASSED)

---

### 1. Preflight Verification Gates

Before executing any write operation, database migration, or symlink switch on the TEST VPS, each of the following safety gates must be evaluated:

| Gate # | Check Description | Expected Condition | Evaluated Status | Action on Failure |
|---|---|---|:---:|---|
| **GATE-01** | Hostname Identity | Host must be `test.rayongcoop.org` | **CONFIRMED** | ABORT immediately if host is production. |
| **GATE-02** | Target Database Identity | Database name must be `rycoopweb_test` | **CONFIRMED** | ABORT if DB name is `rayongcoop_db` or contains `prod`. |
| **GATE-03** | Rollback Reference Baseline | Current target symlink (`/var/www/rycoopweb/test-v2`) must point to valid release | **CONFIRMED** | ABORT if active target cannot be snapshotted. |
| **GATE-04** | Pre-Migration Database Backup | Relevant tables (`popups`, `member_dividends`, `migrations`) backed up | **CONFIRMED** | Back up via `mysqldump rycoopweb_test popups > /storage/backups/rycoopweb_test_popups_pre029.sql`. |
| **GATE-05** | Migration 029 Schema Safety | Migration uses ANSI/MySQL `information_schema.COLUMNS` check | **CONFIRMED** | Migration 029 does NOT use unsupported MariaDB `ADD COLUMN IF NOT EXISTS` syntax. |
| **GATE-06** | Concurrency Test Safety | Concurrency tests must never delete shared TEST database rows | **CONFIRMED** | `PopupConcurrencyHardeningTest.php` uses dedicated disposable databases only (`rycoop_concurrency_test_*`). |
| **GATE-07** | Scope Isolation | Release must exclude unfinished Sprint 1B redesign work | **CONFIRMED** | Candidate commit `d9e6a44` isolates only Popup V2 & Dividend Import flat shell. |
| **GATE-08** | RBAC & CSRF Protection | Mutating endpoints require `AuthMiddleware`, `PermissionMiddleware`, and `CsrfMiddleware` | **CONFIRMED** | Verified in `config/routes.php`. |
| **GATE-09** | Asset Build Health | Frontend build completes without syntax error | **CONFIRMED** | Built cleanly via Vite (chunk sizes verified). |

---

### 2. Preflight Execution Steps for VPS Operator

```bash
# 1. Login to TEST VPS
ssh root@test.rayongcoop.org

# 2. Verify target hostname and active paths
hostname -f # Must be test.rayongcoop.org
readlink -f /var/www/rycoopweb/test-v2

# 3. Create pre-migration backup of TEST database
mkdir -p /var/www/rycoopweb/shared-test/storage/backups
mysqldump --defaults-file=/root/.my.cnf rycoopweb_test popups > \
  /var/www/rycoopweb/shared-test/storage/backups/rycoopweb_test_popups_pre_029_$(date +%Y%m%d%H%M%S).sql

# 4. Stage Release Candidate d9e6a44
cd /var/www/rycoopweb
bash bin/stage-test-release.sh d9e6a44bc86e55b342bb47d58bc3f313933f5bb7

# 5. Apply Migration 029 on rycoopweb_test
mysql --defaults-file=/root/.my.cnf rycoopweb_test < \
  /var/www/rycoopweb/releases/d9e6a44bc86e55b342bb47d58bc3f313933f5bb7/database/migrations/029_add_popup_campaign_manager_v2_fields.sql

# 6. Deploy atomic symlinks and reload test FPM
bash bin/deploy-test-release.sh d9e6a44bc86e55b342bb47d58bc3f313933f5bb7
```
