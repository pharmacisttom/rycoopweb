# RYCOOPWEB 3.0 — Visitor Counter & Web Analytics TEST Deployment Runbook

> **Target Environment**: `https://test.rayongcoop.org`
> **Release Topology**: `/var/www/rycoopweb/releases/<COMMIT_SHA>` -> `/var/www/rycoopweb/test-current` symlink & `/var/www/rycoopweb/test-v2-static`
> **Important**: Do **not** deploy automatically to Production (`https://www.rayongcoop.org`). Do **not** execute database migrations `019` or `020` (strictly blocked).

---

## 1. Pre-Deployment Checklist

1. Verify all automated tests and production build pass locally:
   ```bash
   php tests/MigrationRunnerTest.php
   php tests/VisitorAnalyticsTest.php
   php tests/AdminControlCenterTest.php
   php tests/MfaWorkflowTest.php
   php tests/SecurityRegressionTest.php
   php tests/WorkflowRoutesTest.php
   node tests/PublicContentReferenceTest.mjs
   node tests/NavigationRedirectTest.mjs
   node tests/WorkflowFrontendTest.mjs
   node tests/MfaUiRedesignTest.mjs
   npm run build
   ```
2. Confirm `visitor_stats` (`001_initial_schema.sql`) and `visitors_log` (`006_create_visitors_log.sql`) exist in `rycoopweb_test`.
3. Verify Migration `025_create_admin_control_center_tables.sql` (which seeds `analytics.view`, `analytics.visitors.view`, and `analytics.visitors.export` permissions) passes `--lab --dry-run` and `--lab` in `rycoopweb_migration_lab` before applying to `rycoopweb_test`.

---

## 2. Staged Release & Symlink Deployment Steps (`TEST`)

```bash
export NEW_SHA="<APPROVED_COMMIT_SHA>"
export PREV_RELEASE=$(readlink -f /var/www/rycoopweb/test-current)

# 1. Stage release in /var/www/rycoopweb/releases/${NEW_SHA}
mkdir -p /var/www/rycoopweb/releases/${NEW_SHA}
cd /var/www/rycoopweb/releases/${NEW_SHA}
git clone https://github.com/pharmacisttom/rycoopweb.git .
git checkout ${NEW_SHA}

# 2. Link shared .env & storage, then build
ln -sfn /var/www/rycoopweb/shared/.env /var/www/rycoopweb/releases/${NEW_SHA}/.env
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# 3. Verify Migration 025 in isolated rycoopweb_migration_lab first, then apply to rycoopweb_test
php database/run_migration.php 025_create_admin_control_center_tables.sql --lab --dry-run
php database/run_migration.php 025_create_admin_control_center_tables.sql --lab
php database/run_migration.php 025_create_admin_control_center_tables.sql --dry-run
php database/run_migration.php 025_create_admin_control_center_tables.sql

# 4. Copy static assets to /var/www/rycoopweb/test-v2-static and switch symlink
cp -r /var/www/rycoopweb/releases/${NEW_SHA}/public/app/* /var/www/rycoopweb/test-v2-static/
ln -sfn /var/www/rycoopweb/releases/${NEW_SHA} /var/www/rycoopweb/test-current

# 5. Reload PHP-FPM and Nginx gracefully
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx
```

---

## 3. Post-Deployment Smoke Verification on `TEST`

1. **Public Counter Widget Visibility**:
   - Open `https://test.rayongcoop.org/` and verify the homepage summary widget (`VisitorCounter variant="home"`) and footer widget (`VisitorCounter variant="footer"`) display real database figures from `visitor_stats` and `visitors_log` for `กำลังใช้งานเว็บไซต์`, `ผู้เข้าชมวันนี้`, `ผู้เข้าชมเดือนนี้`, and `ผู้เข้าชมทั้งหมด`.
2. **Public Analytics Page (`/visitor-stats`)**:
   - Open `https://test.rayongcoop.org/visitor-stats` and verify daily trends, monthly trends, hourly distribution, device breakdown, top public pages, and metric definitions render without any fabricated fallback numbers.
3. **Private Route Exclusion Check**:
   - Visit `/admin/login` and `/admin/dashboard`, then confirm `visitors_log` does **not** record `/admin/*` paths.
4. **Admin Visitor Analytics Dashboard (`/admin/analytics/visitors`)**:
   - Log in with an authorized account (`super_admin`, `it_admin`, `auditor`, or `executive`), navigate to `/admin/analytics/visitors`, verify all 8 KPI cards and 6 charts/tables render, and test the audited CSV export button (`analytics.visitors.export`).

---

## 4. Rollback Procedure

```bash
ln -sfn "${PREV_RELEASE}" /var/www/rycoopweb/test-current
cp -r "${PREV_RELEASE}/public/app/"* /var/www/rycoopweb/test-v2-static/
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```
