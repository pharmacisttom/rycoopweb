# RYCOOPWEB — RC03 CONTROLLED TEST DEPLOYMENT PROCEDURE

**Repository:** `pharmacisttom/rycoopweb`  
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Target Branch:** `integration/test-rc03-unified-content`  
**Verified Baseline:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`  
**Production Status:** STRICTLY FROZEN  

---

## 1. Prerequisites & Safety Invariants

Before initiating deployment on the TEST server:
1. **Target Environment Verification:** Deployment target is strictly TEST (`rycoopweb_test`). Production (`main`, `rycoopweb_prod`) is **STRICTLY FROZEN**.
2. **Immutable Record Invariants:** Verify the immutable counts remain untouched:
   - `members = 2,508`
   - `users = 2,510` (2,508 members + 1 super admin ID 1 + 1 member admin ID 2510)
   - `member_dividends = 4,855`
3. **Environment Flags:** Verify that `FEATURE_MEMBER_PORTAL=false` is set in `/var/www/rycoopweb/shared-test/.env`.
4. **Pre-Deployment Backup:** Ensure full database backup of `rycoopweb_test` is created prior to running migrations:
   ```bash
   mysqldump -u <user> -p rycoopweb_test > /var/backups/rycoopweb_test_pre_rc03_$(date +%Y%m%d_%H%M%S).sql
   ```
5. **No Automatic Term Seeding / Publishing:** Migration 030 is schema-only. Committee terms are **NOT** automatically seeded or published during migration. Term 38 remains the active published committee term; Term 39 remains an unapproved draft.

---

## 2. Server Architecture & Staged Release Layout

The TEST environment utilizes an immutable staged release layout with atomic symlink switching:

- **Base Directory:** `/var/www/rycoopweb`
- **Releases Directory:** `/var/www/rycoopweb/releases`
- **Shared Test Storage:** `/var/www/rycoopweb/shared-test` (holds `.env`, `uploads/`, `logs/`, `tmp/`)
- **Staged Backend Release:** `/var/www/rycoopweb/releases/${TARGET_SHA}`
- **Staged Frontend Release:** `/var/www/rycoopweb/releases/${TARGET_SHA}-static`
- **Active Backend Symlink:** `/var/www/rycoopweb/test-v2` -> active backend release
- **Active Frontend Symlink:** `/var/www/rycoopweb/test-v2-static` -> active frontend release
- **Dedicated PHP-FPM Service:** `rycoop-test-fpm.service` (Isolated from production PHP-FPM)
- **Web Server:** Nginx reloading via `sudo nginx -s reload`

---

## 3. Step-by-Step Deployment Runbook

### Phase 1: Stage Release Artifacts (Immutable Staging)

Run the automated staging script or perform manual staging into release directories:

```bash
# 1. Export Target SHA
export TARGET_SHA=$(git rev-parse HEAD)

# 2. Stage Immutable Backend
mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}
git archive HEAD | tar -x -C /var/www/rycoopweb/releases/${TARGET_SHA}

# 3. Bind Shared Test Configuration & Storage
ln -sfn /var/www/rycoopweb/shared-test/.env /var/www/rycoopweb/releases/${TARGET_SHA}/.env
ln -sfn /var/www/rycoopweb/shared-test/uploads /var/www/rycoopweb/releases/${TARGET_SHA}/public/storage/uploads
ln -sfn /var/www/rycoopweb/shared-test/logs /var/www/rycoopweb/releases/${TARGET_SHA}/storage/logs

# 4. Install PHP Dependencies
cd /var/www/rycoopweb/releases/${TARGET_SHA}
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Stage & Build Frontend Assets
mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}-static
cp -r /var/www/rycoopweb/releases/${TARGET_SHA}/* /var/www/rycoopweb/releases/${TARGET_SHA}-static/
cd /var/www/rycoopweb/releases/${TARGET_SHA}-static
npm ci
npm run build
```

*(Alternatively, execute `/var/www/rycoopweb/bin/stage-test-release.sh ${TARGET_SHA}` which performs the above steps in a verified sequence).*

---

### Phase 2: Database Migration Execution

Execute forward Migration 030 via the official CLI console runner `bin/console`:

```bash
cd /var/www/rycoopweb/releases/${TARGET_SHA}

# 1. Pre-Flight Dry Run
php bin/console db:migrate --dry-run --db-name=rycoopweb_test

# 2. Execute Forward Migration
php bin/console db:migrate --db-name=rycoopweb_test
```

**Verification Criteria:**
- Output displays `030_create_board_term_management_tables.sql` applied successfully with 0 errors.
- Confirm 4 new tables created in `rycoopweb_test`:
  - `board_terms`
  - `board_term_members`
  - `board_term_groups`
  - `board_term_group_members`
- Total table count in `rycoopweb_test` increases from 34 to 38.
- Verify immutable counts: `members = 2,508`, `users = 2,510`, `member_dividends = 4,855`.

---

### Phase 3: Atomic Symlink Switch & Service Activation

Execute the atomic symlink switch and restart the dedicated TEST FPM service:

```bash
# 1. Snapshot Current Targets (Pre-Switch Baseline)
ORIG_BACKEND=$(readlink -f /var/www/rycoopweb/test-v2)
ORIG_STATIC=$(readlink -f /var/www/rycoopweb/test-v2-static)

echo "Baseline Backend: ${ORIG_BACKEND}"
echo "Baseline Static:  ${ORIG_STATIC}"

# 2. Atomic Symlink Switch
ln -sfn /var/www/rycoopweb/releases/${TARGET_SHA} /var/www/rycoopweb/test-v2
ln -sfn /var/www/rycoopweb/releases/${TARGET_SHA}-static /var/www/rycoopweb/test-v2-static

# 3. Restart Dedicated FPM & Reload Nginx
sudo systemctl restart rycoop-test-fpm.service
sudo nginx -s reload

# 4. Reset OPcache
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset successful\n'; }"
```

*(Alternatively, execute `/var/www/rycoopweb/bin/deploy-test-release.sh ${TARGET_SHA}` which automates snapshotting, trap-based rollback, atomic switching, and health verification).*

---

## 4. Post-Deployment Smoke Test Protocol

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

## 5. Rapid Rollback Procedure (Symlink & Database)

If any anomaly or regression is detected on the TEST deployment:

### Step 1: Immediate Symlink Rollback
Restore the verified pre-deployment release targets immediately (without recompiling or modifying git worktrees):

```bash
# Restore previous active release symlinks
ln -sfn "${ORIG_BACKEND}" /var/www/rycoopweb/test-v2
ln -sfn "${ORIG_STATIC}" /var/www/rycoopweb/test-v2-static

# Restart dedicated TEST FPM & reload Nginx
sudo systemctl restart rycoop-test-fpm.service
sudo nginx -s reload

# Flush OPcache
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset successful\n'; }"
```

### Step 2: Database Schema Rollback (If Required)
If Migration 030 must be rolled back, execute the safe reverse foreign key script:

```bash
mysql -u <user> -p rycoopweb_test < database/rollbacks/030_rollback_board_term_management_tables.sql
```

**Rollback Script Order:**
1. `DROP TABLE IF EXISTS board_term_group_members;`
2. `DROP TABLE IF EXISTS board_term_groups;`
3. `DROP TABLE IF EXISTS board_term_members;`
4. `DROP TABLE IF EXISTS board_term_terms;`
5. `DROP TABLE IF EXISTS board_terms;`

### Step 3: Verification After Rollback
- Confirm `https://test.rayongcoop.org` serves HTTP 200 on all public routes.
- Confirm `https://test.rayongcoop.org/board_committee` continues serving published Term 38.
- Confirm immutable counts: `members = 2,508`, `users = 2,510`, `member_dividends = 4,855`.
