# RYCOOPWEB 3.0 — TEST Deployment & Staging Runbook

Release Target: TEST Environment (`https://test.rayongcoop.org`)  
Base Branch: `Test`  
Approved Feature Branch: `codex/admin-crud-functional-recovery`  
Operating Environment: Ubuntu 24.04 LTS, Nginx, PHP-FPM 8.3, MySQL 8.0

---

## 1. Absolute Pre-Deployment Safety Rules

> [!CAUTION]
> **STRICT COMPLIANCE MANDATORY**:
> 1. DO NOT deploy or change symlinks automatically without explicit administrator sign-off.
> 2. DO NOT modify Production (`www.rayongcoop.org`).
> 3. DO NOT alter `APP_KEY` or MFA Encryption Key in `.env`.
> 4. DO NOT rerun Migration `025_create_admin_control_center_tables.sql`.
> 5. Keep Migrations `019` and `020` ON HOLD.
> 6. Keep `BACKUP_RESTORE_EXECUTION_ENABLED=false`.
> 7. Keep Member Portal Feature Flags disabled (`false`) until data sign-off.

---

## 2. Phase 1: Read-Only System & Symlink Inspection

Log in to the VPS via SSH and verify current active directories, symlinks, and commits:

```bash
# 1. Verify current active symlinks
readlink -f /var/www/rycoopweb/test-v2
readlink -f /var/www/rycoopweb/test-v2-static

# 2. Check active backend commit and git status
git -C /var/www/rycoopweb/test-v2 rev-parse HEAD
git -C /var/www/rycoopweb/test-v2 status --short

# 3. Check active frontend HTML build fingerprint
sha256sum /var/www/rycoopweb/test-v2-static/index.html

# 4. Confirm PHP-FPM runtime and MySQL version
php -v | head -n 1
mysql -u root -p -e "SELECT VERSION(), DATABASE();" rycoopweb_test
```

---

## 3. Phase 2: Staging Immutable Release Directory

Create an immutable timestamped release directory for both backend and frontend:

```bash
RELEASE_TAG="release-$(date +%Y%m%d_%H%M%S)"
TARGET_BACKEND="/var/www/rycoopweb/releases/${RELEASE_TAG}"
TARGET_FRONTEND="/var/www/rycoopweb/releases/${RELEASE_TAG}_static"

mkdir -p "${TARGET_BACKEND}" "${TARGET_FRONTEND}"

# Clone approved commit to release directory
git clone --depth 1 --branch codex/admin-crud-functional-recovery \
    https://github.com/pharmacisttom/rycoopweb.git "${TARGET_BACKEND}"

cd "${TARGET_BACKEND}"

# 1. Install production PHP dependencies (no dev packages)
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# 2. Link persistent shared uploads and logs
ln -sfn /var/www/rycoopweb/shared-test/uploads "${TARGET_BACKEND}/storage/uploads"
ln -sfn /var/www/rycoopweb/shared-test/logs "${TARGET_BACKEND}/storage/logs"

# 3. Copy validated TEST environment configuration
cp /var/www/rycoopweb/shared-test/.env "${TARGET_BACKEND}/.env"

# 4. Build immutable frontend assets
npm ci
npm run build
cp -r public/app/* "${TARGET_FRONTEND}/"

# 5. Fix file permissions
chown -R www-data:www-data "${TARGET_BACKEND}" "${TARGET_FRONTEND}"
chmod -R 755 "${TARGET_BACKEND}" "${TARGET_FRONTEND}"
chmod -R 775 "${TARGET_BACKEND}/storage"
```

---

## 4. Phase 3: Database Verification & Pre-Release Backup

Create a physical dump of `rycoopweb_test` before switching release:

```bash
BACKUP_FILE="/var/www/rycoopweb/backups/rycoopweb_test_pre_${RELEASE_TAG}.sql"
mysqldump -u root -p --single-transaction --routines --triggers rycoopweb_test > "${BACKUP_FILE}"
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

# Verify tables required for PR Officer, MFA and Control Center
mysql -u root -p -e "
SELECT TABLE_NAME, ENGINE, TABLE_ROWS 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA='rycoopweb_test' AND TABLE_NAME IN 
('users', 'roles', 'permissions', 'role_permissions', 'user_roles', 
 'staff_invitations', 'user_google_identities', 'approval_requests');"
```

---

## 5. Phase 4: Atomic Symlink Switch & Service Reload

Switch the active symlinks to the newly staged immutable release:

```bash
# Atomic symlink switch
ln -sfn "${TARGET_BACKEND}" /var/www/rycoopweb/test-v2
ln -sfn "${TARGET_FRONTEND}" /var/www/rycoopweb/test-v2-static

# Clear OpCache and reload PHP-FPM / Nginx
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx

echo "Release ${RELEASE_TAG} activated on TEST."
```

---

## 6. Phase 5: Post-Deployment Smoke Verification

1. Verify public homepage:
   `curl -ILs https://test.rayongcoop.org | head -n 5`
2. Verify single Footer Visitor Counter:
   Confirm footer renders single Navy/Teal strip and `/visitor-stats` is reachable.
3. Verify Official Logo:
   Check `https://test.rayongcoop.org/app/assets/coop-logo-*.webp` returns `HTTP 200`.
4. Perform Authenticated Super Admin UAT:
   - Super Admin Google Login + TOTP MFA.
   - Navigate to `/admin/users` (verify `/api/admin/staff/` loads with `200 OK`).
   - Test "เชิญเจ้าหน้าที่ประชาสัมพันธ์" modal with synthetic Gmail.
   - Verify invitation URL generation and revocation.
