# RYCOOPWEB 3.0 — Visitor Counter & Web Analytics TEST Deployment Runbook

> **Target Environment**: `https://test.rayongcoop.org`
> **Branch**: `codex/visitor-counter-analytics` (built on `Test` + `codex/fix-mfa-qr` + `codex/admin-control-center`)
> **Important**: Do **not** deploy automatically to Production (`https://www.rayongcoop.org`). Do **not** execute database migrations `019` or `020` (remain ON HOLD).

---

## 1. Pre-Deployment Checklist

1. Verify all automated tests and production build pass locally:
   ```bash
   php tests/VisitorAnalyticsTest.php
   php tests/AdminControlCenterTest.php
   php tests/SecurityRegressionTest.php
   php tests/WorkflowRoutesTest.php
   node tests/PublicContentReferenceTest.mjs
   node tests/NavigationRedirectTest.mjs
   node tests/WorkflowFrontendTest.mjs
   npm run build
   ```
2. Confirm `website_visits` and `visitor_counters` tables exist in the `TEST` database (from `021_create_visitor_counter_tables.sql`).
3. Confirm no secrets, `.env` files, or private keys are staged in Git.

---

## 2. Deployment Steps for `TEST` (`https://test.rayongcoop.org`)

```bash
# 1. Fetch and checkout the branch on TEST server
cd /var/www/rycoopweb
git fetch origin
git checkout codex/visitor-counter-analytics
git pull origin codex/visitor-counter-analytics

# 2. Install frontend & PHP dependencies using existing lockfiles
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# 3. Reload PHP-FPM and Nginx gracefully
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

---

## 3. Post-Deployment Smoke Verification on `TEST`

1. **Public Counter Widget Visibility**:
   - Open `https://test.rayongcoop.org/` and verify the homepage summary widget (`VisitorCounter variant="home"`) and footer widget (`VisitorCounter variant="footer"`) display real database figures for `กำลังใช้งานเว็บไซต์`, `ผู้เข้าชมวันนี้`, `ผู้เข้าชมเดือนนี้`, and `ผู้เข้าชมทั้งหมด`.
2. **Public Analytics Page (`/visitor-stats`)**:
   - Open `https://test.rayongcoop.org/visitor-stats` and verify daily trends, monthly trends, hourly distribution, device breakdown, top public pages, and metric definitions render without any fabricated fallback numbers.
3. **Private Route Exclusion Check**:
   - Visit `/admin/login` and `/admin/dashboard`, then confirm `website_visits` does **not** record `/admin/*` paths.
4. **Admin Visitor Analytics Dashboard (`/admin/analytics/visitors`)**:
   - Log in with an authorized account (`super_admin`, `it_admin`, `auditor`, or `executive`), navigate to `/admin/analytics/visitors`, verify all 8 KPI cards and 6 charts/tables render, and test the audited CSV export button.

---

## 4. Rollback Procedure

If any issue is observed on `TEST`:

```bash
cd /var/www/rycoopweb
git checkout Test
npm run build
sudo systemctl reload php8.3-fpm
```
