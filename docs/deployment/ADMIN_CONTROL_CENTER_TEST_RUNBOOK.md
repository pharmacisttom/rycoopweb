# RYCOOPWEB 3.0 — Admin Control Center, Visitor Analytics & MFA Redesign TEST Deployment Runbook

## 1. Purpose & Operational Safety Scope

This runbook governs the staged deployment, isolated lab database verification (`rycoopweb_migration_lab`), live TEST migration (`rycoopweb_test`), symlink switch, and rollback of **RYCOOPWEB 3.0** (`codex/mfa-ui-redesign` / `codex/admin-control-center` / `codex/visitor-counter-analytics`) on the **TEST** environment (`https://test.rayongcoop.org`).

> [!IMPORTANT]
> **Mandatory Safety Guardrails**
> - **NEVER** execute `019_cleanup_legacy_interest_rates.sql` or `020_sync_deposit_rates.sql`. Both remain strictly blocked by the financial safety gate.
> - **NEVER** modify or overwrite existing user accounts (`users`), role assignments (`user_roles`), MFA secrets/enrollments (`two_factor_*`, `user_mfa_recovery_codes`), or financial data (`member_dividends`, `interest_rates`, `deposit_products`, `loan_products`).
> - **ALWAYS** verify `025_create_admin_control_center_tables.sql` in the isolated **`rycoopweb_migration_lab`** database (`--lab --dry-run` and `--lab`) before applying it to `rycoopweb_test`.
> - **NEVER** rotate or modify `APP_KEY` or `TWO_FACTOR_ENCRYPTION_KEY`.
> - **NEVER** deploy automatically to Production (`https://www.rayongcoop.org`).

---

## 2. Server Release & Symlink Architecture (`TEST`)

| Component | Server Path | Description |
|---|---|---|
| Release Repository | `/var/www/rycoopweb/releases/<COMMIT_SHA>` | Immutable directory for each staged Git commit |
| Active Backend Symlink | `/var/www/rycoopweb/test-current` | Atomic symlink pointing to the active `/var/www/rycoopweb/releases/<COMMIT_SHA>` |
| Frontend Static Assets | `/var/www/rycoopweb/test-v2-static` | Nginx static root populated from `public/app/*` built by Vite |
| Shared Environment & Storage | `/var/www/rycoopweb/shared/.env` & `storage/` | Persistent `.env`, `storage/logs`, `storage/uploads`, `storage/backups` |
| Isolated Migration Lab DB | `rycoopweb_migration_lab` | Disposable MySQL 8.0 clone for pre-deployment `--lab --dry-run` & `--lab` testing |
| Live TEST Database | `rycoopweb_test` | Active MySQL 8.0 database backing `https://test.rayongcoop.org` |

---

## 3. Phase 1 — Stage New Release in `/var/www/rycoopweb/releases/<SHA>`

```bash
# 1. Set the approved commit SHA and record the current active release for instant rollback
export NEW_SHA="<APPROVED_COMMIT_SHA>"
export PREV_RELEASE=$(readlink -f /var/www/rycoopweb/test-current)
echo "${PREV_RELEASE}" > /tmp/rycoop_test_prev_release_$(date +%Y%m%d_%H%M%S).txt

# 2. Clone/checkout into an immutable release directory
mkdir -p /var/www/rycoopweb/releases/${NEW_SHA}
cd /var/www/rycoopweb/releases/${NEW_SHA}
git clone https://github.com/pharmacisttom/rycoopweb.git .
git checkout ${NEW_SHA}

# 3. Link shared .env and persistent storage directories
ln -sfn /var/www/rycoopweb/shared/.env /var/www/rycoopweb/releases/${NEW_SHA}/.env
rm -rf /var/www/rycoopweb/releases/${NEW_SHA}/storage/uploads /var/www/rycoopweb/releases/${NEW_SHA}/storage/backups
ln -sfn /var/www/rycoopweb/shared/storage/uploads /var/www/rycoopweb/releases/${NEW_SHA}/storage/uploads
ln -sfn /var/www/rycoopweb/shared/storage/backups /var/www/rycoopweb/releases/${NEW_SHA}/storage/backups

# 4. Install deterministic PHP & Node dependencies from lockfiles and build SPA
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# 5. Run automated pre-switch verification suites inside the staged release
php tests/MigrationRunnerTest.php
php tests/AdminControlCenterTest.php
php tests/VisitorAnalyticsTest.php
php tests/MfaWorkflowTest.php
php tests/QrCodeGeneratorTest.php
node tests/QrCodeRoundTripTest.mjs
php tests/SecurityRegressionTest.php
php tests/WorkflowRoutesTest.php
node tests/WorkflowFrontendTest.mjs
node tests/MfaUiRedesignTest.mjs
```

---

## 4. Phase 2 — Verify Migration `025` in Isolated Lab DB (`rycoopweb_migration_lab`)

Before touching `rycoopweb_test`, refresh `rycoopweb_migration_lab` with the current schema from `rycoopweb_test` and test `025_create_admin_control_center_tables.sql` in isolation:

```bash
cd /var/www/rycoopweb/releases/${NEW_SHA}

# 1. Refresh isolated lab schema from rycoopweb_test (structure + RBAC metadata only)
mysqldump --single-transaction --no-data --routines --triggers rycoopweb_test | mysql rycoopweb_migration_lab
mysqldump --single-transaction rycoopweb_test migrations roles permissions role_permissions | mysql rycoopweb_migration_lab

# 2. Inspect status in rycoopweb_migration_lab
php database/run_migration.php --status --lab

# 3. Run dry-run preflight of Migration 025 in rycoopweb_migration_lab
php database/run_migration.php 025_create_admin_control_center_tables.sql --lab --dry-run

# 4. Execute Migration 025 in rycoopweb_migration_lab and verify idempotency
php database/run_migration.php 025_create_admin_control_center_tables.sql --lab
php database/run_migration.php 025_create_admin_control_center_tables.sql --lab

# 5. Verify tables & permissions in rycoopweb_migration_lab
mysql rycoopweb_migration_lab -e "
  SHOW TABLES LIKE 'approval_requests';
  SHOW TABLES LIKE 'admin_dashboard_preferences';
  SELECT COUNT(*) AS total_roles FROM roles;
  SELECT COUNT(*) AS total_permissions FROM permissions;
"
```

---

## 5. Phase 3 — Backup `rycoopweb_test` & Apply Migration `025`

Only after Phase 2 succeeds with zero errors:

```bash
cd /var/www/rycoopweb/releases/${NEW_SHA}

# 1. Take a full logical backup of rycoopweb_test before migration
export BACKUP_FILE="/var/www/rycoopweb/shared/storage/backups/rycoopweb_test_pre_025_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump --single-transaction --routines --triggers rycoopweb_test | gzip > "${BACKUP_FILE}"
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

# 2. Dry-run Migration 025 against rycoopweb_test
php database/run_migration.php 025_create_admin_control_center_tables.sql --dry-run

# 3. Apply Migration 025 to rycoopweb_test (Strictly single-file execution — NEVER run 019 or 020)
php database/run_migration.php 025_create_admin_control_center_tables.sql

# 4. Confirm migration status on rycoopweb_test
php database/run_migration.php --status
```

---

## 6. Phase 4 — Publish Static Assets & Switch `/var/www/rycoopweb/test-current` Symlink

```bash
# 1. Backup current static assets
tar -czf /tmp/rycoop_test_v2_static_$(date +%Y%m%d_%H%M%S).tar.gz -C /var/www/rycoopweb/test-v2-static .

# 2. Sync new Vite build (public/app/*) to /var/www/rycoopweb/test-v2-static/
cp -r /var/www/rycoopweb/releases/${NEW_SHA}/public/app/* /var/www/rycoopweb/test-v2-static/

# 3. Atomically switch the active release symlink
ln -sfn /var/www/rycoopweb/releases/${NEW_SHA} /var/www/rycoopweb/test-current

# 4. Reload PHP-FPM and Nginx gracefully
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx
```

---

## 7. Phase 5 — Post-Deployment Smoke Verification on `https://test.rayongcoop.org`

1. **Public Website & Visitor Counter**:
   - Visit `https://test.rayongcoop.org/` and `https://test.rayongcoop.org/visitor-stats` and verify real database figures render for `กำลังใช้งานเว็บไซต์`, `ผู้เข้าชมวันนี้`, `ผู้เข้าชมเดือนนี้`, and `ผู้เข้าชมทั้งหมด`.
2. **Secure MFA UI/UX & Authentication (`/admin/login` -> `/admin/mfa/verify`)**:
   - Sign in at `/admin/login` and verify `/admin/mfa/verify` renders the redesigned 6-box segmented OTP interface, light/dark mode toggle, recovery code toggle, and session expiry protection while preserving existing TOTP enrollments.
3. **Admin Control Center 3.0 (All 14 Modules)**:
   - Verify `/admin/dashboard`, `/admin/analytics/visitors`, `/admin/operations`, `/admin/users`, `/admin/roles`, `/admin/permissions`, `/admin/departments`, `/admin/audit-logs`, `/admin/security`, `/admin/security/sessions`, `/admin/security/mfa`, `/admin/backups`, `/admin/health`, `/admin/reports`, and `/admin/settings`.
   - Confirm `/api/admin/security/mfa` and `/api/admin/audit-logs/center` never expose `two_factor_secret`, recovery code hashes, or passwords.

---

## 8. Immediate Rollback Plan (< 60 Seconds)

Because Migration `025` is 100% additive and non-destructive, application rollback requires only switching the symlink and restoring static assets:

```bash
# 1. Atomically point /var/www/rycoopweb/test-current back to the previous release
ln -sfn "${PREV_RELEASE}" /var/www/rycoopweb/test-current

# 2. Restore previous static bundle in /var/www/rycoopweb/test-v2-static
cp -r "${PREV_RELEASE}/public/app/"* /var/www/rycoopweb/test-v2-static/

# 3. Reload PHP-FPM and Nginx
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```
*(Optional schema reversal for `025` is documented in `docs/database/SQL_DEPENDENCY_MATRIX_3_0.md` Section 4.2.)*
