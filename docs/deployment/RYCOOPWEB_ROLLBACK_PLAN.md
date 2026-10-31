# RYCOOPWEB 3.0 — Emergency Rollback & Recovery Plan

Target Environment: TEST (`https://test.rayongcoop.org`)  
Base Release Architecture: Atomic Symlinked Releases  
Scope: Immediate recovery in case of critical defect or deployment failure

---

## 1. Rollback Principles & Safety Constraints

1. **Atomic Symlink Reversion**:
   - Both backend (`/var/www/rycoopweb/test-v2`) and frontend (`/var/www/rycoopweb/test-v2-static`) must be switched back together to maintain contract parity.
2. **Zero Destructive Database Operations**:
   - Never run `DOWN` migrations or execute `DROP TABLE`.
   - Never reset user accounts, passwords, or rotation keys (`APP_KEY`).
   - Synthetic invitations or audit logs created during UAT are preserved.
3. **Safety Locks Kept Enforced**:
   - `BACKUP_RESTORE_EXECUTION_ENABLED` remains `false`.
   - Member Portal feature flags remain `false`.

---

## 2. Emergency Rollback Execution

### Step 1: Identify Previous Known-Good Release

Inspect the `/var/www/rycoopweb/releases/` directory for the previous immutable release:

```bash
ls -lt /var/www/rycoopweb/releases/ | head -n 10
# Identify the previous stable release, e.g.:
# PREV_BACKEND="/var/www/rycoopweb/releases/release-20261029_061157"
# PREV_FRONTEND="/var/www/rycoopweb/releases/release-20261029_061157_static"
```

### Step 2: Atomic Symlink Switchback

```bash
PREV_BACKEND="/var/www/rycoopweb/releases/<PREVIOUS_RELEASE_TAG>"
PREV_FRONTEND="/var/www/rycoopweb/releases/<PREVIOUS_RELEASE_TAG>_static"

# Revert symlinks
ln -sfn "${PREV_BACKEND}" /var/www/rycoopweb/test-v2
ln -sfn "${PREV_FRONTEND}" /var/www/rycoopweb/test-v2-static

# Reload web services
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx

echo "Rollback to ${PREV_BACKEND} completed."
```

### Step 3: Verify Post-Rollback System Health

```bash
# 1. Verify HTTP Response
curl -ILs https://test.rayongcoop.org | head -n 5

# 2. Verify Active Commit
git -C /var/www/rycoopweb/test-v2 rev-parse HEAD

# 3. Check PHP Error Log
tail -n 50 /var/www/rycoopweb/shared-test/logs/php_errors.log
```

---

## 3. Incident Logging & Reporting

Document the incident with:
1. Exact timestamp of failure detection and rollback execution.
2. Error symptoms and log traces (`X-Correlation-Id`).
3. Root cause classification (Code defect, Database mismatch, or Infrastructure fault).
4. Corrective actions required before next staging attempt.
