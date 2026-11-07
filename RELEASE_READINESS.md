# RYCOOPWEB — RELEASE READINESS & DEPLOYMENT SIGN-OFF REPORT

**Repository:** `pharmacisttom/rycoopweb`<br>
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)<br>
**Production Status:** STRICTLY FROZEN<br>
**Target Branch:** `integration/test-rc03-unified-content`<br>
**Verified Baseline:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`<br>
**Deployment Date:** 2026-11-07<br>
**Sign-off Authority:** Principal Full-Stack Architect & DevSecOps Lead

---

## 1. Release Objective

Deploy the RC03 Unified Content Integration release to the **TEST** environment. This release unifies all public frontend routes with administrative workflows, eliminating mock fallbacks, client-side data silos (`localStorage`), and fabricated statistics while establishing multi-term committee governance.

> [!CAUTION]
> **PRODUCTION ENVIRONMENT IS STRICTLY FROZEN.**
> Under no circumstances must this branch be merged to `main` or deployed to production servers without formal cooperative board approval.

---

## 2. Pre-Flight Verification Checklist

| Check Category | Verification Item | Status | Evidence / Notes |
|:---|:---|:---:|:---|
| **Production Freeze** | Target environment is TEST ONLY | **VERIFIED** | Branch: `integration/test-rc03-unified-content` |
| **Financial Immutability** | Member dividend records preserved | **VERIFIED** | `member_dividends = 4,855` strictly immutable |
| **Member Data Integrity** | Authoritative VPS member and user counts | **VERIFIED** | `members = 2,508`, `users = 2,509` intact (dev fixture = 2,510) |
| **Database Migrations** | Single-file Migration 030 runner clean | **VERIFIED** | Migration `030` is strictly additive |
| **Migration Safety** | Rollback scripts safely isolated | **VERIFIED** | Rollbacks isolated in `database/rollbacks/` |
| **Security - PDPA** | PDPA fail-closed enforcement active | **VERIFIED** | `MemberPdpaGateTest.php` passing |
| **Security - MFA & RBAC**| Admin MFA recovery and RBAC active | **VERIFIED** | `SecurityRegressionTest.php` passing |
| **Security - Member Portal** | Member portal remains disabled on TEST | **VERIFIED** | `FEATURE_MEMBER_PORTAL=false` enforced |
| **Security - CSRF** | All mutating endpoints enforce CSRF | **VERIFIED** | Verified in complaints & board term APIs |
| **Code Quality** | Patch whitespace check clean | **VERIFIED** | `git diff --check` returns 0 warnings |
| **Frontend Build** | Vite production bundle builds cleanly | **VERIFIED** | `npm run build` completed in ~7s |

---

## 3. Database Migration Summary

### Forward Single-File Migration: `030_create_board_term_management_tables.sql`
- **Tables Created (Additive Only):**
  - `board_terms` (Term number, title, dates, scheduled publication, status, lock version)
  - `board_term_members` (Term ID, user/officer references, role, position, workplace, sort order)
  - `board_term_groups` (Subcommittee grouping definitions)
  - `board_term_group_members` (Group-to-member mappings)
- **Destructive Statements:** NONE (Strictly additive, no `DROP`, `TRUNCATE`, or column drops).
- **Execution:** Automated via `php bin/console db:migrate --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql`.

---

## 4. Test Suite Execution Sign-Off

The complete automated quality gate suite was executed against the active MySQL database and PHP 8.3 / Node.js runtime:

```bash
# 1. Multi-Term Committee Management Suite
php tests/BoardTermManagementTest.php                # PASSED (45 assertions)

# 2. Complaints Subsystem Suite
php tests/ComplaintWorkflowTest.php                  # PASSED (25 assertions)

# 3. Welfare Subsystem Suite
php tests/WelfareWorkflowTest.php                    # PASSED (17 assertions)

# 4. Downloadable Documents Subsystem Suite
php tests/DocumentsWorkflowTest.php                  # PASSED (18 assertions)

# 5. E-Services & FAQs Subsystem Suite
php tests/EservicesFaqsConsistencyTest.php          # PASSED (16 assertions)

# 6. Products, Rates & Contacts Consistency Suite
php tests/ProductsRatesConsistencyTest.php           # PASSED (21 assertions)

# 7. SSR Reference Integrity Suite
node tests/PublicContentReferenceTest.mjs            # PASSED (All SSR renders)

# 8. Public Content Versioning & Audit Suite
php tests/PublicContentWorkflowTest.php              # PASSED (24 assertions)

# 9. Security & Governance Gates
php tests/SecurityRegressionTest.php                 # PASSED (All security gates)
php tests/MemberPdpaGateTest.php                     # PASSED (PDPA fail-closed)
php tests/MigrationRunnerTest.php                    # PASSED (Migration safety & Section 18)
php tests/MediaUrlTest.php                           # PASSED (13 assertions)

# 10. Frontend Build & Whitespace Check
npm run build                                        # PASSED (Vite production build)
git diff --check                                     # PASSED (No whitespace errors)
```

---

## 5. Deployment Procedure (TEST ONLY)

### Phase 1: Immutable Staging
```bash
export TARGET_SHA=$(git rev-parse HEAD)

mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}
git archive HEAD | tar -x -C /var/www/rycoopweb/releases/${TARGET_SHA}

ln -sfn /var/www/rycoopweb/shared-test/.env /var/www/rycoopweb/releases/${TARGET_SHA}/.env
mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}/storage
ln -sfn /var/www/rycoopweb/shared-test/uploads /var/www/rycoopweb/releases/${TARGET_SHA}/public/storage/uploads
ln -sfn /var/www/rycoopweb/shared-test/logs /var/www/rycoopweb/releases/${TARGET_SHA}/storage/logs
ln -sfn /var/www/rycoopweb/shared-test/tmp /var/www/rycoopweb/releases/${TARGET_SHA}/storage/tmp

cd /var/www/rycoopweb/releases/${TARGET_SHA}
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}-static
cp -r /var/www/rycoopweb/releases/${TARGET_SHA}/* /var/www/rycoopweb/releases/${TARGET_SHA}-static/
cd /var/www/rycoopweb/releases/${TARGET_SHA}-static
npm ci --silent
VITE_FEATURE_MEMBER_PORTAL=false VITE_PUBLIC_STATIC_MODE=false npx vite build --base=/

chown -R www-data:www-data /var/www/rycoopweb/releases/${TARGET_SHA} /var/www/rycoopweb/releases/${TARGET_SHA}-static
```

### Phase 2: Single-File Database Migration
```bash
cd /var/www/rycoopweb/releases/${TARGET_SHA}

set -a
[ -f /var/www/rycoopweb/shared-test/.env ] && . /var/www/rycoopweb/shared-test/.env
set +a

php bin/console db:migrate --dry-run --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql
php bin/console db:migrate --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql
```
*Verification: Migration 030 creates 4 additive tables. Existing records and financial counts are preserved untouched.*

### Phase 3: Controlled Symlink Switch via Atomic Rename
```bash
ORIG_BACKEND=$(readlink -f /var/www/rycoopweb/test-v2)
ORIG_STATIC=$(readlink -f /var/www/rycoopweb/test-v2-static)

ln -sfn /var/www/rycoopweb/releases/${TARGET_SHA} /var/www/rycoopweb/test-v2.tmp
mv -Tf /var/www/rycoopweb/test-v2.tmp /var/www/rycoopweb/test-v2

ln -sfn /var/www/rycoopweb/releases/${TARGET_SHA}-static /var/www/rycoopweb/test-v2-static.tmp
mv -Tf /var/www/rycoopweb/test-v2-static.tmp /var/www/rycoopweb/test-v2-static

sudo systemctl restart rycoop-test-fpm.service
sudo nginx -s reload
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset successful\n'; }"
```

---

## 6. Nuanced UAT Verification Protocol

Smoke test expectations must strictly distinguish between code capability, database records, published content, and fallback behavior:

1. **Visit `https://test.rayongcoop.org/`:** Verify banner, live stats, announcements, and footer contact telephone (`038-967-409`).
2. **Visit `https://test.rayongcoop.org/board_committee`:** Committee Term 38 displays 15 directors, Chairperson badge, 3 advisors, 3 auditors. Fallback to approved legacy data guarantees continuous availability if DB term not yet published.
3. **Visit `https://test.rayongcoop.org/documents`:** 12 active documents render across 6 categories. Clean empty notice if a category has no documents. Real download count increments.
4. **Visit `https://test.rayongcoop.org/eservice`:** Active e-services load from DB. Clicking external links triggers confirmation modal.
5. **Visit `https://test.rayongcoop.org/welfare`:** Active welfare benefits render without mock fallback.
6. **Visit `https://test.rayongcoop.org/contact?tab=complaint`:** Submit complaint; verify tracking ID `TKT-YYYYMM-XXXX`.
7. **Visit `https://test.rayongcoop.org/contact?tab=tracking`:** Lookup ticket; verify masked complainant name (`นา*** มั***`).
8. **Login to Admin `https://test.rayongcoop.org/admin/board-staff`:** Confirm Term 38 published and Term 39 draft isolated. Term 39 remains unpublished until formal approval.

---

## 7. Rollback Plan

> [!WARNING]
> **CODE ROLLBACK NEVER EXECUTES DROP TABLE AUTOMATICALLY.**<br>
> Code rollback restores active release symlinks to the baseline release targets (`ORIG_BACKEND` & `ORIG_STATIC`).<br>
> Migration 030 is strictly additive; tables and committee records remain safely in the database.

1. **Symlink Restoration via Atomic Rename:**
   ```bash
   ln -sfn "${ORIG_BACKEND}" /var/www/rycoopweb/test-v2.tmp
   mv -Tf /var/www/rycoopweb/test-v2.tmp /var/www/rycoopweb/test-v2

   ln -sfn "${ORIG_STATIC}" /var/www/rycoopweb/test-v2-static.tmp
   mv -Tf /var/www/rycoopweb/test-v2-static.tmp /var/www/rycoopweb/test-v2-static

   sudo systemctl restart rycoop-test-fpm.service
   sudo nginx -s reload
   ```
2. **Exceptional Schema Rollback (Manual Disaster Recovery Only):**
   ```bash
   mysql -u <user> -p rycoopweb_test < database/rollbacks/030_rollback_board_term_management_tables.sql
   ```
   *Accurate drop order: `board_term_group_members` -> `board_term_groups` -> `board_term_members` -> `board_terms`.*

---

## 8. Final Recommendation & GO/NO-GO Assessment

All 10 required release runbook corrections and quality criteria are 100% satisfied.

**RELEASE DECISION:** **GO FOR CONTROLLED TEST DEPLOYMENT**<br>
**PRODUCTION STATUS:** **STRICTLY FROZEN**
