# RYCOOPWEB — RC03 CONTROLLED TEST DEPLOYMENT PROCEDURE

**Repository:** `pharmacisttom/rycoopweb`<br>
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)<br>
**Target Branch:** `integration/test-rc03-unified-content`<br>
**Verified Baseline:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2` (RC02 Deployed Baseline)<br>
**Production Status:** STRICTLY FROZEN

---

## 1. Prerequisites & Safety Invariants

Before initiating deployment on the TEST server:

1. **Target Environment Verification:** Deployment target is strictly TEST (`rycoopweb_test`). Production (`main`, `rycoopweb_prod`) is **STRICTLY FROZEN**.
2. **Authoritative VPS Baseline Records:**
   - `members = 2,508`
   - `member_dividends = 4,855`
   - `users = 2,509` (2,508 members + 1 Super Administrator User ID 1)
   > [!IMPORTANT]
   > The local development database may report 2,510 users due to an isolated local test fixture (User ID 2510 `memberadmin`). **NEVER create, delete, or modify user or member accounts to reconcile counts.** The VPS baseline of 2,509 users is authoritative and must remain strictly untouched.
3. **Environment Flags:** Verify `/var/www/rycoopweb/shared-test/.env`:
   - `FEATURE_MEMBER_PORTAL=false`
   - `APP_ENV=testing`
4. **Pre-Deployment Backup:** Create a full verified SQL dump before applying any schema changes:
   ```bash
   mysqldump -u <user> -p rycoopweb_test > /var/backups/rycoopweb_test_pre_rc03_$(date +%Y%m%d_%H%M%S).sql
   ```
5. **Committee Term Governance Rules:**
   - **Term 38 Public Continuity:** Committee Term 38 must remain publicly visible to website visitors at all times until an authorized replacement term is explicitly published.
   - **Term 39 Draft Isolation:** Committee Term 39 must remain an unpublished administrative draft until formally approved by cooperative authorities.
   - **No Auto-Seeding / Auto-Publishing:** Migration 030 is schema-only. It does **not** automatically seed or publish term 38 or term 39.

---

## 2. Server Architecture & Staged Release Layout

The TEST environment enforces an immutable staged release layout with atomic symlink switching:

- **Base Directory:** `/var/www/rycoopweb`
- **Releases Directory:** `/var/www/rycoopweb/releases`
- **Shared Test Storage:** `/var/www/rycoopweb/shared-test` (holds `.env`, `uploads/`, `logs/`, `tmp/`)
- **Staged Backend Release:** `/var/www/rycoopweb/releases/${TARGET_SHA}`
- **Staged Frontend Release:** `/var/www/rycoopweb/releases/${TARGET_SHA}-static`
- **Active Backend Symlink:** `/var/www/rycoopweb/test-v2` -> active backend release
- **Active Frontend Symlink:** `/var/www/rycoopweb/test-v2-static` -> active frontend release
- **Dedicated PHP-FPM Service:** `rycoop-test-fpm.service` (Isolated from production PHP-FPM)
- **Web Server:** Nginx reloading via `sudo nginx -s reload`
- **Rollback Retention:** Previous active releases (including RC02 baseline `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`) are preserved in `/var/www/rycoopweb/releases/` to guarantee instantaneous zero-recompile rollback.

---

## 3. Step-by-Step Deployment Runbook

### Phase 1: Immutable Release Staging

Execute staging into isolated release directories:

```bash
# 1. Export Target SHA
export TARGET_SHA=$(git rev-parse HEAD)
echo "Staging Release: ${TARGET_SHA}"

# 2. Stage Immutable Backend
mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}
git archive HEAD | tar -x -C /var/www/rycoopweb/releases/${TARGET_SHA}

# 3. Bind Shared Test Configuration & Persistent Storage
ln -sfn /var/www/rycoopweb/shared-test/.env /var/www/rycoopweb/releases/${TARGET_SHA}/.env
mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}/storage
ln -sfn /var/www/rycoopweb/shared-test/uploads /var/www/rycoopweb/releases/${TARGET_SHA}/public/storage/uploads
ln -sfn /var/www/rycoopweb/shared-test/logs /var/www/rycoopweb/releases/${TARGET_SHA}/storage/logs
ln -sfn /var/www/rycoopweb/shared-test/tmp /var/www/rycoopweb/releases/${TARGET_SHA}/storage/tmp

# 4. Install PHP Production Dependencies (Retaining Vendor Autoloader)
cd /var/www/rycoopweb/releases/${TARGET_SHA}
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# 5. Stage & Compile Frontend Assets with Explicit Base & Feature Flags
mkdir -p /var/www/rycoopweb/releases/${TARGET_SHA}-static
cp -r /var/www/rycoopweb/releases/${TARGET_SHA}/* /var/www/rycoopweb/releases/${TARGET_SHA}-static/
cd /var/www/rycoopweb/releases/${TARGET_SHA}-static
npm ci --silent
VITE_FEATURE_MEMBER_PORTAL=false VITE_PUBLIC_STATIC_MODE=false npx vite build --base=/

# 6. Set Least-Privilege Permissions
chown -R www-data:www-data /var/www/rycoopweb/releases/${TARGET_SHA} /var/www/rycoopweb/releases/${TARGET_SHA}-static
find /var/www/rycoopweb/releases/${TARGET_SHA} -type d -exec chmod 755 {} \;
find /var/www/rycoopweb/releases/${TARGET_SHA} -type f -exec chmod 644 {} \;
find /var/www/rycoopweb/releases/${TARGET_SHA}/bin -type f -exec chmod 755 {} \;
chmod -R 775 /var/www/rycoopweb/releases/${TARGET_SHA}/storage
```

*(Alternatively, run `/var/www/rycoopweb/bin/stage-test-release.sh ${TARGET_SHA}`).*

---

### Phase 2: Single-File Database Migration 030

Always execute Migration 030 as a single-file migration with prior dry-run and explicit environment loading:

```bash
cd /var/www/rycoopweb/releases/${TARGET_SHA}

# 1. Explicit, Secure Environment Loading
set -a
[ -f /var/www/rycoopweb/shared-test/.env ] && . /var/www/rycoopweb/shared-test/.env
set +a

# 2. Pre-Flight Dry Run (Validates syntax, isolation, and safety without modifying database)
php bin/console db:migrate --dry-run --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql

# 3. Live Forward Single-File Migration
php bin/console db:migrate --db-name=rycoopweb_test --file=030_create_board_term_management_tables.sql
```

**Post-Migration Verification:**
- Confirm 4 additive tables exist in `rycoopweb_test`:
  - `board_terms`
  - `board_term_members`
  - `board_term_groups`
  - `board_term_group_members`
- Total table count in `rycoopweb_test` increases by exactly 4 (from 34 to 38).
- Confirm authoritative VPS invariants remain untouched:
  - `members = 2,508`
  - `member_dividends = 4,855`
  - `users = 2,509`

---

### Phase 3: Controlled Symlink Switch via Atomic Rename

Store and validate both original targets before switching, then perform controlled atomic replacement using temporary symlinks:

```bash
ACTIVE_BACKEND_LINK="/var/www/rycoopweb/test-v2"
ACTIVE_STATIC_LINK="/var/www/rycoopweb/test-v2-static"

# 1. Store and Validate Original Release Targets (Pre-Switch Baseline)
ORIG_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}" 2>/dev/null || echo "")
ORIG_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}" 2>/dev/null || echo "")

if [ -z "${ORIG_BACKEND}" ] || [ ! -d "${ORIG_BACKEND}" ]; then
    echo "[ERROR] Invalid active backend target: ${ORIG_BACKEND}" >&2
    exit 1
fi
if [ -z "${ORIG_STATIC}" ] || [ ! -d "${ORIG_STATIC}" ]; then
    echo "[ERROR] Invalid active frontend target: ${ORIG_STATIC}" >&2
    exit 1
fi

echo "[INFO] Stored verified baseline backend:  ${ORIG_BACKEND}"
echo "[INFO] Stored verified baseline frontend: ${ORIG_STATIC}"

# 2. Controlled Atomic Symlink Switch (Temporary Symlink + POSIX mv -Tf)
ln -sfn "/var/www/rycoopweb/releases/${TARGET_SHA}" "${ACTIVE_BACKEND_LINK}.tmp"
mv -Tf "${ACTIVE_BACKEND_LINK}.tmp" "${ACTIVE_BACKEND_LINK}"

ln -sfn "/var/www/rycoopweb/releases/${TARGET_SHA}-static" "${ACTIVE_STATIC_LINK}.tmp"
mv -Tf "${ACTIVE_STATIC_LINK}.tmp" "${ACTIVE_STATIC_LINK}"

# 3. Dedicated Service Restart & Nginx Reload
sudo systemctl restart rycoop-test-fpm.service
sudo nginx -s reload

# 4. OPcache Reset
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset successful\n'; }"
```

*(Alternatively, run `/var/www/rycoopweb/bin/deploy-test-release.sh ${TARGET_SHA}` which performs the above sequence with an automated rollback trap).*

---

## 4. Nuanced UAT Verification Matrix

UAT expectations must explicitly distinguish between **Code Capability**, **Database Records**, **Published Content**, **Legacy Approved Fallback**, and **Missing or Unavailable Content**:

| Route / Module | Code Capability | Database Records | Published Content vs Fallback | Expected UAT Behavior |
|:---|:---|:---|:---|:---|
| `/board_committee` | Multi-term governance, dynamic grouping, badges | `board_terms`, `board_term_members` | Serves published Term 38 from DB if seeded; **falls back to approved legacy reference** if DB empty | Committee Term 38 displays 15 directors, Chairperson badge, 3 advisors, 3 auditors. Zero downtime. |
| `/board_officer` | Staff directory with departments and photos | `staff` table | Serves DB records if present; **falls back to approved staff reference** if DB empty | Staff directory displays 6 officers with valid department tags and photos. |
| `/documents` | Category filter, secure stream, download counter | `documents`, `document_categories` | Serves active DB records; **shows empty state** if category has no records | 12 active documents render across 6 categories. Download increments counter. Empty categories show clean notice. |
| `/welfare` | Card view, grant criteria, requirements | `welfare` table | Serves active DB records; **shows empty state** if no active records | Welfare cards render grant amounts. No mock or fabricated items displayed. |
| `/eservice` | Service portal links with exit confirmation modal | `eservice_links` table | Serves active DB records; **shows empty state** if no active records | Service cards render. Clicking external link triggers security exit confirmation modal. |
| `/contact?tab=complaint` | Server-side ticket creation, rate limit, validation | `complaints`, `complaint_logs` | Submits new record to DB | Generates `TKT-YYYYMM-XXXX`. Returns sanitized confirmation. |
| `/contact?tab=tracking` | Masked PDPA status tracking | `complaints` table | Read-only masked status lookup | Valid ticket + phone returns masked name (`นา*** มั***`). Non-existent ticket returns clean not found notice. |
| `/admin/board-staff` | 6-tab administrative CMS, term draft editor | `board_terms`, `board_term_members` | Admin session required (TOTP MFA) | Displays Term 38 details; allows Term 39 draft creation/editing. Term 39 remains unpublished. |

---

## 5. Rollback Architecture & Procedures

### 5.1 Automated Code Rollback (Preserves Database)

> [!WARNING]
> **NEVER EXECUTE DROP TABLE ROLLBACK AUTOMATICALLY.**<br>
> Code rollback must restore previous active release symlinks only (`ORIG_BACKEND` -> `test-v2`, `ORIG_STATIC` -> `test-v2-static`).<br>
> Migration 030 is **strictly additive** and fully backward-compatible with the RC02 codebase. All database tables and committee records remain safely intact during a code rollback.

```bash
# 1. Restore Previous Active Release Targets via Atomic Symlink Rename
ln -sfn "${ORIG_BACKEND}" /var/www/rycoopweb/test-v2.tmp
mv -Tf /var/www/rycoopweb/test-v2.tmp /var/www/rycoopweb/test-v2

ln -sfn "${ORIG_STATIC}" /var/www/rycoopweb/test-v2-static.tmp
mv -Tf /var/www/rycoopweb/test-v2-static.tmp /var/www/rycoopweb/test-v2-static

# 2. Restart Dedicated TEST FPM & Reload Nginx
sudo systemctl restart rycoop-test-fpm.service
sudo nginx -s reload

# 3. Flush OPcache
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache reset successful\n'; }"
```

### 5.2 Exceptional Schema Rollback (Manual Disaster Recovery Only)

If and only if database schema reversion is explicitly authorized by cooperative engineering leads, execute the isolated rollback script manually:

```bash
mysql -u <user> -p rycoopweb_test < database/rollbacks/030_rollback_board_term_management_tables.sql
```

**Accurate Reverse Foreign-Key Table Drop Order:**
1. `DROP TABLE IF EXISTS board_term_group_members;`
2. `DROP TABLE IF EXISTS board_term_groups;`
3. `DROP TABLE IF EXISTS board_term_members;`
4. `DROP TABLE IF EXISTS board_terms;` *(Corrected: removed stale `board_term_terms`)*
