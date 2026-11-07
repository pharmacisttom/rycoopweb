# RYCOOPWEB — ROLLBACK RUNBOOK & RECOVERY PLAN
## Emergency Recovery Procedure for Candidate Release 01 on TEST
**Target Hostname:** `test.rayongcoop.org`
**Rollback Baseline:** Pre-Deployment Active Release (Commit `bd8f23a0bbffe7ebce2eed75996ae6ea907768bf` / `19e9c7d`)
**Target Database:** `rycoopweb_test`

---

### 1. Trigger Conditions for Immediate Rollback
Initiate immediate rollback if any of the following occur on TEST:
- HTTP 500, 502, or 504 on public homepage (`https://test.rayongcoop.org`).
- Failure of `/api/csrf-token` or `/api/auth/me`.
- Member Login, Admin Login, or TOTP MFA flow disruption.
- Database migration error or schema lock.
- Any regression in Dividend Import or financial data views.

---

### 2. Automated One-Command Symlink Rollback
The deployment script `bin/deploy-test-release.sh` contains an automatic rollback trap. If manual rollback is required:

```bash
# 1. SSH to TEST VPS
ssh root@test.rayongcoop.org

# 2. Point active symlinks back to verified baseline release
BASE_DIR="/var/www/rycoopweb"
PREVIOUS_RELEASE="${BASE_DIR}/releases/bd8f23a0bbffe7ebce2eed75996ae6ea907768bf"

ln -sfn "${PREVIOUS_RELEASE}" "${BASE_DIR}/test-v2"
ln -sfn "${PREVIOUS_RELEASE}-static" "${BASE_DIR}/test-v2-static"

# 3. Restart dedicated FPM service and reload Nginx
systemctl restart rycoop-test-fpm.service
nginx -s reload

echo "[SUCCESS] Active TEST symlinks reverted to baseline ${PREVIOUS_RELEASE}"
```

---

### 3. Database Migration 029 Rollback

If database columns for Popup Campaign Manager V2 need to be removed:

```bash
# 1. Verify schema before rollback
mysql --defaults-file=/root/.my.cnf -e "DESCRIBE popups;" rycoopweb_test

# 2. Execute hardened rollback script
mysql --defaults-file=/root/.my.cnf rycoopweb_test < \
  /var/www/rycoopweb/releases/d9e6a44bc86e55b342bb47d58bc3f313933f5bb7/database/migrations/029_rollback_popup_campaign_manager_v2_fields.sql

# 3. Alternatively, restore pre-migration backup if table state needs full reset
mysql --defaults-file=/root/.my.cnf rycoopweb_test < \
  /var/www/rycoopweb/shared-test/storage/backups/rycoopweb_test_popups_pre_029_*.sql
```

---

### 4. Post-Rollback Health Check
1. Verify `curl -k -I https://test.rayongcoop.org` returns HTTP 200 or 401 (Basic auth).
2. Verify Admin Login and Dividend Import page mount correctly.
3. Confirm dedicated FPM is running: `systemctl status rycoop-test-fpm.service`.
