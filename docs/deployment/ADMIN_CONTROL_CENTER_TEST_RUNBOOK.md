# RYCOOPWEB 3.0 — Admin Control Center TEST Staging Runbook & Rollback Plan

## 1. Purpose & Operational Safety Scope

This runbook governs the safe deployment, verification, and rollback of **RYCOOPWEB 3.0 — Enterprise Admin Control Center** (`codex/admin-control-center`) on the **TEST** environment (`https://test.rayongcoop.org`).

> [!IMPORTANT]
> **Production Protection Policy**
> - Do **NOT** deploy automatically to Production (`https://www.rayongcoop.org`).
> - Do **NOT** execute database migrations automatically. Migrations `019_create_google_oauth_and_mfa_tables.sql` and `020_seed_initial_super_admin.sql` remain **ON HOLD** pending explicit DBA approval.
> - Do **NOT** rotate or modify `APP_KEY` or `TWO_FACTOR_ENCRYPTION_KEY`.
> - Do **NOT** reset MFA enrollments or disable Google OIDC / Mandatory MFA.

---

## 2. Branch & Commit Lineage

- **Integration Baseline**: `Test` (`2d400a7`)
- **Unmerged Security Hotfix Included**: `codex/fix-mfa-qr` (`5cbdfa8` — standards-compliant QR SVG generator & round-trip decoder verification)
- **Feature Branch**: `codex/admin-control-center` (fast-forward child of `5cbdfa8`, preserving all `Test` and MFA QR hotfixes)

---

## 3. Pre-Deployment Checklist (TEST Only)

1. **Verify Environment Variables on TEST (`/var/www/rycoopweb-test/.env`)**:
   - Confirm `APP_ENV=testing` or `staging`
   - Confirm `APP_URL=https://test.rayongcoop.org`
   - Confirm `APP_KEY` and `TWO_FACTOR_ENCRYPTION_KEY` are unchanged
   - Confirm `FEATURE_MEMBER_PORTAL` and `FEATURE_MEMBER_LOGIN` match TEST configuration
2. **Record Pre-Deployment Snapshot**:
   ```bash
   cd /var/www/rycoopweb-test
   git rev-parse HEAD > /tmp/rycoop_test_pre_acc_commit.txt
   tar -czf /tmp/rycoop_test_public_build_$(date +%Y%m%d_%H%M%S).tar.gz public/assets public/index.html
   ```

---

## 4. Step-by-Step Deployment to TEST (`https://test.rayongcoop.org`)

```bash
# 1. Navigate to the TEST webroot
cd /var/www/rycoopweb-test

# 2. Fetch latest branches without modifying untracked storage/uploads
git fetch origin

# 3. Checkout the Admin Control Center branch (which includes Test + fix-mfa-qr)
git checkout codex/admin-control-center
git pull --ff-only origin codex/admin-control-center

# 4. Install deterministic PHP & Node dependencies from lockfiles
composer install --no-dev --optimize-autoloader --no-interaction
npm ci

# 5. Build the production-grade React 18 SPA bundle
npm run build

# 6. Run automated verification suites before reloading services
php tests/QrCodeGeneratorTest.php
node tests/QrCodeRoundTripTest.mjs
php tests/SecurityRegressionTest.php
php tests/WorkflowRoutesTest.php
php tests/PermissionBehaviorTest.php
php tests/AdminControlCenterTest.php
node tests/WorkflowFrontendTest.mjs

# 7. Reload PHP-FPM and Nginx gracefully
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx
```

---

## 5. Post-Deployment Smoke Test Checklist on TEST

1. **Public Website & Member Portal Regression Check**:
   - Visit `https://test.rayongcoop.org/` and confirm official cooperative logo, Thai typography, news, and calculators render normally.
   - Visit `https://test.rayongcoop.org/member/dividends` and confirm member dividend lookup works and member accounts receive `403` if they attempt to access `/api/admin/dashboard/summary`.
2. **Authentication, Google OIDC & Mandatory MFA**:
   - Sign in via `/admin/login` with a staff/admin account.
   - Confirm Mandatory MFA verification (`/admin/mfa/verify`) or enrollment (`/admin/mfa/enroll`) renders a scannable QR code (`bacon/bacon-qr-code`).
3. **Admin Control Center 3.0 Verification**:
   - Verify `/admin/dashboard` displays the 12 KPI cards and 10 charts using real database figures (and shows `"ยังไม่มีข้อมูล"` when a data source has no records).
   - Verify `/admin/operations`, `/admin/users`, `/admin/roles`, `/admin/permissions`, `/admin/departments`, `/admin/audit-logs`, `/admin/security`, `/admin/security/sessions`, `/admin/security/mfa`, `/admin/backups`, `/admin/backups/history`, `/admin/health`, `/admin/reports`, and `/admin/settings`.
   - Verify `/admin/cms` opens the legacy 8-module Cooperative CMS (`AdminDashboardPage`) without regression.
   - Inspect `/api/admin/security/mfa` and `/api/admin/audit-logs/center` JSON payloads in browser DevTools to confirm no `two_factor_secret`, `two_factor_recovery_codes`, `password`, or session cookies are exposed.

---

## 6. Immediate Rollback Plan

If any anomaly is detected on TEST, roll back within 60 seconds using the recorded commit and asset archive:

```bash
cd /var/www/rycoopweb-test
git checkout $(cat /tmp/rycoop_test_pre_acc_commit.txt)
composer dump-autoload --optimize
npm run build
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

Because **no destructive database migrations** were executed, rolling back the application code and frontend build immediately restores the exact prior runtime state with zero database drift.

