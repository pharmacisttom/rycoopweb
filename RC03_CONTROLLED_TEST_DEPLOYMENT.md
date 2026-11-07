# RYCOOPWEB — RC03 CONTROLLED TEST DEPLOYMENT PROCEDURE

**Repository:** `pharmacisttom/rycoopweb`  
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Target Branch:** `integration/test-rc03-unified-content`  
**Integration Commit SHA:** `3317da8`  
**Verified Baseline:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`  
**Production Status:** STRICTLY FROZEN  

---

## 1. Prerequisites & Safety Invariants

Before initiating deployment on the TEST server:
1. Confirm current branch is `integration/test-rc03-unified-content`.
2. Confirm target database is `rycoopweb_test`.
3. Verify that `FEATURE_MEMBER_PORTAL=false` is set in the server `.env`.
4. Ensure full database backup of `rycoopweb_test` is taken prior to running migrations.

---

## 2. Step-by-Step Deployment Runbook

### Step 1: Code Checkout
```bash
git fetch pharmacisttom
git checkout integration/test-rc03-unified-content
git pull pharmacisttom integration/test-rc03-unified-content
```

### Step 2: Database Migration Execution
Apply forward Migration 030:
```bash
php bin/migrate.php
```
*Verification: Confirm output indicates `030_create_board_term_management_tables.sql` applied successfully with 0 errors.*

### Step 3: Initialize Committee Term 38
Reconcile and seed Committee Term 38 into `board_terms`:
```bash
php -r "require 'app/Core/Helpers.php'; require 'app/Core/Database.php'; require 'app/Services/BoardTermService.php'; App\Services\BoardTermService::reconcileTerm38();"
```
*Verification: Confirm `board_terms` contains Term 38 with `is_current = 1` and 15 directors in `board_term_members`.*

### Step 4: Frontend Production Build
```bash
npm install --frozen-lockfile
npm run build
```
*Verification: Confirm Vite build produces production assets in `public/app/assets/`.*

### Step 5: Storage Permissions
```bash
chmod -R 775 storage/
chmod -R 775 public/storage/uploads/
```

### Step 6: OPcache Reset
```bash
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset successful'; }"
```

---

## 3. Post-Deployment Smoke Test Protocol

Perform the following smoke tests on `https://test.rayongcoop.org`:

| Step | URL | Expected Behavior |
|:---:|:---|:---|
| 1 | `https://test.rayongcoop.org/` | Hero slider, stats banner, active announcements, and footer telephone `038-967-409` render cleanly. |
| 2 | `https://test.rayongcoop.org/board_committee` | Committee Term 38 displays 15 directors, Chairperson badge, 3 advisors, 3 auditors, and 3 subcommittees. |
| 3 | `https://test.rayongcoop.org/board_officer` | Staff directory renders 6 officers with valid photos and departments. |
| 4 | `https://test.rayongcoop.org/documents` | 12 active documents render across 6 categories. Download links stream valid files and increment counter. |
| 5 | `https://test.rayongcoop.org/welfare` | Active welfare benefit cards render with grant amounts and eligibility criteria. |
| 6 | `https://test.rayongcoop.org/eservice` | Service cards render. Clicking external links triggers confirmation modal. |
| 7 | `https://test.rayongcoop.org/contact?tab=complaint` | Submit test complaint. Verify receipt of `TKT-YYYYMM-XXXX`. |
| 8 | `https://test.rayongcoop.org/contact?tab=tracking` | Track ticket. Verify masked complainant name (`นา*** มั***`) and masked contact details. |
| 9 | `https://test.rayongcoop.org/admin/board-staff` | Admin login with TOTP MFA. Verify 6 tabs render with full Term 38 data; verify Term 39 draft can be edited. |

---

## 4. Rapid Rollback Procedure

If any anomaly is detected on the TEST deployment:

1. **Revert Git to Baseline:**
   ```bash
   git checkout 2bf75982e5b0d1a328fc184c7a84d4fdf41210c2
   npm run build
   ```
2. **Revert Database Schema (if required):**
   Execute rollback script manually:
   ```bash
   mysql -u root -p rycoopweb_test < database/rollbacks/030_rollback_board_term_management_tables.sql
   ```
3. **Purge Caches:**
   Reset OPcache and CDN caches.
