#!/usr/bin/env bash
# ==============================================================================
# RYCOOPWEB 3.0 — HARDENED MEMBER PORTAL TEST UAT DEPLOYMENT SCRIPT
#
# Release Target: TEST ONLY (https://test.rayongcoop.org)
# Base Application Release: integration/rycoopweb-test-uat
# Database: rycoopweb_test (PRODUCTION NEVER TOUCHED)
# ==============================================================================
set -euo pipefail

TARGET_SHA="${1:-HEAD}"
# Resolve full commit SHA if HEAD or short SHA passed
REPO_URL="https://github.com/pharmacisttom/rycoopweb.git"
BASE_DIR="/var/www/rycoopweb"
RELEASES_DIR="${BASE_DIR}/releases"
SHARED_TEST_DIR="${BASE_DIR}/shared-test"

ACTIVE_BACKEND_LINK="${BASE_DIR}/test-v2"
ACTIVE_STATIC_LINK="${BASE_DIR}/test-v2-static"

# Resolve commit SHA if working in a git clone
if git rev-parse --verify "${TARGET_SHA}" >/dev/null 2>&1; then
    RESOLVED_SHA=$(git rev-parse "${TARGET_SHA}")
else
    RESOLVED_SHA="${TARGET_SHA}"
fi

UAT_BACKEND="${RELEASES_DIR}/${RESOLVED_SHA}-uat"
UAT_STATIC="${RELEASES_DIR}/${RESOLVED_SHA}-uat-static"

echo "=============================================================================="
echo " RYCOOPWEB HARDENED TEST UAT DEPLOYMENT"
echo " Target Release Backend: ${UAT_BACKEND}"
echo " Target Release Static:  ${UAT_STATIC}"
echo " Resolved Commit SHA:    ${RESOLVED_SHA}"
echo " Target Environment:     TEST ONLY (rycoopweb_test)"
echo "=============================================================================="

# ------------------------------------------------------------------------------
# HELPER: ATOMIC SYMLINK SWITCH WITH STRICT RESOLUTION VALIDATION
# (Uses validated temporary symlink followed by mv -Tf; never ln -sfn directly on live links)
# ------------------------------------------------------------------------------
atomic_symlink_switch() {
    local target="$1"
    local link_path="$2"
    local link_dir
    link_dir=$(dirname "${link_path}")
    local tmp_link="${link_dir}/.tmp_link_$$.$(basename "${link_path}")"

    if [ ! -d "${target}" ]; then
        echo "[ERROR] Target directory does not exist: ${target}" >&2
        return 1
    fi

    # Create temporary symlink pointing to the target
    ln -snf "${target}" "${tmp_link}"

    # Validate temporary symlink resolves to intended target
    local resolved
    resolved=$(readlink -f "${tmp_link}" 2>/dev/null || echo "")
    local expected
    expected=$(readlink -f "${target}" 2>/dev/null || echo "")
    if [ -z "${resolved}" ] || [ "${resolved}" != "${expected}" ]; then
        echo "[ERROR] Validated symlink check failed: resolved '${resolved}' != expected '${expected}'" >&2
        rm -f "${tmp_link}"
        return 1
    fi

    # Atomically replace live symlink with validated temporary link
    mv -Tf "${tmp_link}" "${link_path}"

    # Verify live symlink now points to target
    local live_resolved
    live_resolved=$(readlink -f "${link_path}" 2>/dev/null || echo "")
    if [ "${live_resolved}" != "${expected}" ]; then
        echo "[ERROR] Post-switch verification failed for ${link_path}: '${live_resolved}' != '${expected}'" >&2
        return 1
    fi
}

# ------------------------------------------------------------------------------
# PHASE 1: PRECONDITIONS, IDEMPOTENCY & ISOLATION CHECKS
# ------------------------------------------------------------------------------
echo "--- [PHASE 1] Precondition & Isolation Verification ---"

# Requirement: Abort if intended release directory already exists (never overwrite immutable releases)
if [ -e "${UAT_BACKEND}" ]; then
    echo "[ERROR] Refusing to overwrite existing release backend directory: ${UAT_BACKEND}" >&2
    echo "        Repeated execution cannot destroy or overwrite an existing immutable release. Aborting." >&2
    exit 1
fi
if [ -e "${UAT_STATIC}" ]; then
    echo "[ERROR] Refusing to overwrite existing release static directory: ${UAT_STATIC}" >&2
    echo "        Repeated execution cannot destroy or overwrite an existing immutable release. Aborting." >&2
    exit 1
fi

# Requirement: Check active dedicated PHP-FPM service before switching
echo "[CHECK 1.1] Checking active dedicated FPM service (rycoop-test-fpm.service)..."
if ! systemctl is-active --quiet rycoop-test-fpm.service; then
    echo "[ERROR] Dedicated FPM service 'rycoop-test-fpm.service' is not active!" >&2
    exit 1
fi
echo "[PASS] Dedicated 'rycoop-test-fpm.service' is active and running."

# Requirement: Preserve production and existing shared-test/.env without modification
SHARED_ENV="${SHARED_TEST_DIR}/.env"
if [ ! -f "${SHARED_ENV}" ]; then
    echo "[ERROR] Precondition Failed: ${SHARED_ENV} does not exist!" >&2
    echo "       Refusing to proceed without existing verified TEST configuration." >&2
    exit 1
fi
echo "[PASS] Verified existing shared-test/.env preserved intact: ${SHARED_ENV}"

# Requirement: Record original backend and frontend targets before switching
ORIG_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}" 2>/dev/null || echo "")
ORIG_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}" 2>/dev/null || echo "")

if [ -z "${ORIG_BACKEND}" ] || [ ! -d "${ORIG_BACKEND}" ]; then
    echo "[ERROR] Cannot determine valid active backend symlink to rollback to: ${ORIG_BACKEND}" >&2
    exit 1
fi
if [ -z "${ORIG_STATIC}" ] || [ ! -d "${ORIG_STATIC}" ]; then
    echo "[ERROR] Cannot determine valid active frontend symlink to rollback to: ${ORIG_STATIC}" >&2
    exit 1
fi

echo "${ORIG_BACKEND}" > "${SHARED_TEST_DIR}/last_pre_uat_backend.txt"
echo "${ORIG_STATIC}" > "${SHARED_TEST_DIR}/last_pre_uat_static.txt"
echo "[PASS] Recorded exact pre-UAT symlink baselines for rollback."
echo "       Previous Backend Target: ${ORIG_BACKEND}"
echo "       Previous Static Target:  ${ORIG_STATIC}"

# Requirement: Create isolated TEST UAT environment configuration (.env.uat)
UAT_ENV="${SHARED_TEST_DIR}/.env.uat"
echo "[DIAGNOSTIC] Generating isolated UAT configuration (${UAT_ENV}) from ${SHARED_ENV}..."
cp "${SHARED_ENV}" "${UAT_ENV}"

# Requirement: Verify the actual TEST database name is strictly rycoopweb_test before any DB operation
DB_NAME=$(grep -E '^DB_DATABASE=' "${UAT_ENV}" | head -n1 | cut -d '=' -f2 | tr -d ' "\r\n')
if [ "${DB_NAME}" != "rycoopweb_test" ]; then
    echo "[CRITICAL ERROR] Target database name in ${UAT_ENV} is '${DB_NAME}', expected strictly 'rycoopweb_test'!" >&2
    echo "                 Refusing to execute any database operations against non-test database! Aborting." >&2
    rm -f "${UAT_ENV}"
    exit 1
fi
echo "[PASS] Verified target database is strictly 'rycoopweb_test'."

update_or_append_flag() {
    local key="$1"
    local val="$2"
    local file="$3"
    if grep -q "^${key}=" "${file}"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "${file}"
    else
        echo "${key}=${val}" >> "${file}"
    fi
}

update_or_append_flag "FEATURE_MEMBER_LOGIN" "true" "${UAT_ENV}"
update_or_append_flag "FEATURE_MEMBER_PORTAL" "true" "${UAT_ENV}"
update_or_append_flag "FEATURE_MEMBER_FINANCIAL_DATA" "true" "${UAT_ENV}"
update_or_append_flag "VITE_FEATURE_MEMBER_PORTAL" "true" "${UAT_ENV}"

chown www-data:www-data "${UAT_ENV}" 2>/dev/null || true
chmod 640 "${UAT_ENV}" 2>/dev/null || true

echo "[PASS] Isolated TEST UAT environment configuration generated:"
grep -E '^(FEATURE_MEMBER_|VITE_FEATURE_MEMBER_)' "${UAT_ENV}"

# ------------------------------------------------------------------------------
# PHASE 2: STAGE IMMUTABLE TEST UAT RELEASE (BACKEND & FRONTEND)
# ------------------------------------------------------------------------------
echo ""
echo "--- [PHASE 2] Staging Separate Immutable UAT Release ---"
mkdir -p "${RELEASES_DIR}"

# Populate UAT release codebase from verified commit without destructive rm -rf
if [ -d "${RELEASES_DIR}/${RESOLVED_SHA}" ]; then
    echo "[DIAGNOSTIC] Copying codebase from verified base release ${RESOLVED_SHA}..."
    cp -a "${RELEASES_DIR}/${RESOLVED_SHA}" "${UAT_BACKEND}"
else
    echo "[DIAGNOSTIC] Cloning clean repository into UAT release directory..."
    git clone "${REPO_URL}" "${UAT_BACKEND}"
    git -C "${UAT_BACKEND}" checkout "${RESOLVED_SHA}"
fi

cd "${UAT_BACKEND}"

# Requirement: Link isolated UAT .env
rm -f "${UAT_BACKEND}/.env"
ln -snf "${UAT_ENV}" "${UAT_BACKEND}/.env"

# Requirement: Preserve shared TEST uploads, logs, tmp and backups.
# Avoid creating symlinks inside existing storage directories; verify link types and targets explicitly.
mkdir -p "${UAT_BACKEND}/storage"
for subdir in uploads logs tmp; do
    target_link="${UAT_BACKEND}/storage/${subdir}"
    shared_target="${SHARED_TEST_DIR}/${subdir}"
    mkdir -p "${shared_target}"
    
    # Remove template directory or stale symlink inside release only
    if [ -L "${target_link}" ] || [ -d "${target_link}" ]; then
        rm -rf "${target_link}"
    fi
    
    ln -s "${shared_target}" "${target_link}"
    
    if [ ! -L "${target_link}" ]; then
        echo "[ERROR] Storage link creation failed: ${target_link} is not a symlink!" >&2
        exit 1
    fi
    res_link=$(readlink -f "${target_link}")
    res_expected=$(readlink -f "${shared_target}")
    if [ "${res_link}" != "${res_expected}" ]; then
        echo "[ERROR] Storage link target mismatch for ${subdir}: '${res_link}' != '${res_expected}'" >&2
        exit 1
    fi
done
echo "[PASS] Verified all storage symlinks point directly to shared-test storage."

# Verify database connection to rycoopweb_test using the staged codebase and .env.uat
echo "[CHECK 2.1] Verifying runtime database connection to rycoopweb_test..."
php -r "
    require '${UAT_BACKEND}/vendor/autoload.php';
    require '${UAT_BACKEND}/app/Core/helpers.php';
    \$cfg = require '${UAT_BACKEND}/config/database.php';
    try {
        \$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            \$cfg['host'] ?? '127.0.0.1',
            \$cfg['port'] ?? 3306,
            \$cfg['database'] ?? '',
            \$cfg['charset'] ?? 'utf8mb4'
        );
        \$pdo = new PDO(\$dsn, \$cfg['username'] ?? '', \$cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        \$db = \$pdo->query('SELECT DATABASE()')->fetchColumn();
        if (\$db !== 'rycoopweb_test') {
            fprintf(STDERR, 'Database mismatch: %s\n', \$db);
            exit(1);
        }
    } catch (\Throwable \$e) {
        fprintf(STDERR, 'Database connection error: %s\n', \$e->getMessage());
        exit(1);
    }
" || {
    echo "[ERROR] Pre-switch database connectivity check failed!" >&2
    exit 1
}
echo "[PASS] Runtime database connectivity to 'rycoopweb_test' confirmed."

# Build React frontend with UAT flags
echo "[DIAGNOSTIC] Installing dependencies and building React frontend with VITE_FEATURE_MEMBER_PORTAL=true..."
export VITE_FEATURE_MEMBER_PORTAL="true"
export FEATURE_MEMBER_PORTAL="true"
export FEATURE_MEMBER_LOGIN="true"
export FEATURE_MEMBER_FINANCIAL_DATA="true"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --silent
npm run build

echo "[DIAGNOSTIC] Staging compiled UAT static assets..."
mkdir -p "${UAT_STATIC}"
cp -r public/app/* "${UAT_STATIC}/"

# Ensure Nginx static root resolves both / and /app correctly
if [ ! -d "${UAT_STATIC}/app" ]; then
    ln -snf . "${UAT_STATIC}/app"
fi

# Set permissions
echo "[DIAGNOSTIC] Setting strict least-privilege permissions on UAT release code..."
chown -R www-data:www-data "${UAT_BACKEND}" "${UAT_STATIC}"
find "${UAT_BACKEND}" -type d -exec chmod 755 {} \;
find "${UAT_BACKEND}" -type f -exec chmod 644 {} \;
find "${UAT_BACKEND}/bin" -type f -exec chmod 755 {} \; 2>/dev/null || true
chmod -R 775 "${UAT_BACKEND}/storage"

sudo -u www-data test -r "${UAT_BACKEND}/public/index.php" || {
    echo "[ERROR] PHP-FPM user www-data cannot read ${UAT_BACKEND}/public/index.php" >&2
    exit 1
}
sudo -u www-data test -w "${UAT_BACKEND}/storage/logs" || {
    echo "[ERROR] PHP-FPM user www-data cannot write to ${UAT_BACKEND}/storage/logs" >&2
    exit 1
}
test -f "${UAT_STATIC}/index.html" || {
    echo "[ERROR] UAT static release missing index.html" >&2
    exit 1
}
echo "[PASS] File permissions, static root assets, and storage access verified."

# ------------------------------------------------------------------------------
# PHASE 3: RUN AUTOMATED TEST SUITES BEFORE SWITCHING SYMLINKS
# ------------------------------------------------------------------------------
echo ""
echo "--- [PHASE 3] Pre-Switch Automated Quality & Security Verification ---"
cd "${UAT_BACKEND}"

echo "[CHECK 3.1] Verifying PHP syntax across codebase..."
find app config public -name "*.php" -exec php -l {} + > /dev/null
echo "[PASS] PHP syntax check clean."

echo "[CHECK 3.2] Running Member Name Masking Unit Test..."
php tests/MemberNameMaskingUnitTest.php

echo "[CHECK 3.3] Running Member Login & PDPA 3.0 Flow Test Suite..."
php tests/MemberLoginPdpaOnlyFlowTest.php

echo "[CHECK 3.4] Running Member PDPA Session Gate Test Suite..."
php tests/MemberPdpaGateTest.php

echo "[CHECK 3.5] Running Member PII Masking & Activation Test Suite..."
php tests/MemberPiiMaskingAndActivationTest.php

echo "[CHECK 3.6] Running Full Member Data Reconciliation & Backend 2FA Suite..."
php tests/MemberFullDataReconciliationUatTest.php

echo "[CHECK 3.7] Running Financial Source Reconciliation..."
php scripts/full_source_reconciliation_report.php

echo "[PASS] All pre-switch automated verification suites passed cleanly (100%)."

# ------------------------------------------------------------------------------
# PHASE 4: ATOMIC SYMLINK SWITCH & RESTART rycoop-test-fpm ONLY
# ------------------------------------------------------------------------------
echo ""
echo "--- [PHASE 4] Atomic Symlink Switch & Dedicated Service Activation ---"

rollback() {
    local exit_code=$?
    echo "[CRITICAL] Post-switch smoke test failed (code: ${exit_code})! Initiating immediate atomic rollback..." >&2
    if [ -n "${ORIG_BACKEND}" ] && [ -d "${ORIG_BACKEND}" ]; then
        atomic_symlink_switch "${ORIG_BACKEND}" "${ACTIVE_BACKEND_LINK}"
        echo "[ROLLBACK] Restored backend symlink -> ${ORIG_BACKEND}" >&2
    fi
    if [ -n "${ORIG_STATIC}" ] && [ -d "${ORIG_STATIC}" ]; then
        atomic_symlink_switch "${ORIG_STATIC}" "${ACTIVE_STATIC_LINK}"
        echo "[ROLLBACK] Restored frontend symlink -> ${ORIG_STATIC}" >&2
    fi
    sudo systemctl restart rycoop-test-fpm.service || true
    echo "[ROLLBACK] TEST symlinks and dedicated FPM restored to pre-UAT state." >&2
    exit "${exit_code}"
}

trap rollback ERR INT TERM

# Switch TEST symlinks atomically via validated temporary symlink & mv -Tf
echo "[DIAGNOSTIC] Atomically switching TEST symlinks to UAT release..."
atomic_symlink_switch "${UAT_BACKEND}" "${ACTIVE_BACKEND_LINK}"
atomic_symlink_switch "${UAT_STATIC}" "${ACTIVE_STATIC_LINK}"

# Restart ONLY rycoop-test-fpm.service (SHARED FPM & PROD UNTOUCHED)
echo "[DIAGNOSTIC] Restarting dedicated rycoop-test-fpm.service (SHARED FPM & PROD UNTOUCHED)..."
sudo systemctl restart rycoop-test-fpm.service

if ! systemctl is-active --quiet rycoop-test-fpm.service; then
    echo "[ERROR] Dedicated FPM service 'rycoop-test-fpm.service' failed to restart!" >&2
    exit 1
fi
echo "[PASS] Dedicated 'rycoop-test-fpm.service' is active."

# ------------------------------------------------------------------------------
# PHASE 5: MULTI-TIER HTTP SMOKE TESTS & RUNTIME VERIFICATION
# (Do not treat a single HTTP 401 check as complete UAT)
# ------------------------------------------------------------------------------
echo ""
echo "--- [PHASE 5] HTTP Smoke Tests & Runtime Verification ---"
SMOKE_URL="https://test.rayongcoop.org"

# Test 5.1: Gateway Basic Auth Challenge
GATEWAY_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${SMOKE_URL}/" || echo "000")
echo "[SMOKE TEST 5.1] Public gateway response code: HTTP ${GATEWAY_CODE}"
if [ "${GATEWAY_CODE}" != "401" ]; then
    echo "[ERROR] Gateway Security Failed: Expected HTTP 401 Basic Auth challenge, received: HTTP ${GATEWAY_CODE}" >&2
    exit 1
fi
echo "[PASS] Gateway Basic Auth gate is ACTIVE."

# Test 5.2: Direct API execution / runtime feature flag check via CLI in UAT context
echo "[SMOKE TEST 5.2] Verifying PHP runtime feature flags in UAT environment..."
RUNTIME_FLAGS=$(php -r "
    require '${UAT_BACKEND}/vendor/autoload.php';
    require '${UAT_BACKEND}/app/Core/helpers.php';
    \$cfg = require '${UAT_BACKEND}/config/features.php';
    echo sprintf('login=%s,portal=%s,financial=%s',
        (\$cfg['member_login'] ?? false) ? 'true' : 'false',
        (\$cfg['member_portal'] ?? false) ? 'true' : 'false',
        (\$cfg['member_financial_data'] ?? false) ? 'true' : 'false'
    );
")
echo "[DIAGNOSTIC] Effective PHP Feature Flags: ${RUNTIME_FLAGS}"

if [[ "${RUNTIME_FLAGS}" != *"login=true"* ]] || [[ "${RUNTIME_FLAGS}" != *"portal=true"* ]] || [[ "${RUNTIME_FLAGS}" != *"financial=true"* ]]; then
    echo "[ERROR] Feature flags not active in runtime: ${RUNTIME_FLAGS}" >&2
    exit 1
fi
echo "[PASS] PHP runtime feature flags active: member_login=true, member_portal=true, member_financial_data=true"

# Test 5.3: Verify compiled React assets contain member portal and new pages
echo "[SMOKE TEST 5.3] Verifying compiled React bundles..."
for component in MemberDashboardPage MemberDividendsPage LoanSchedulePage; do
    if ! grep -rq "${component}" "${UAT_STATIC}/assets/"; then
        echo "[ERROR] Compiled React assets missing ${component} chunk!" >&2
        exit 1
    fi
    echo "[PASS] Found ${component} in static release bundle."
done

# Test 5.4: Comprehensive Authenticated HTTP Smoke Tests (if TEST_HTTP_AUTH provided)
if [ -n "${TEST_HTTP_AUTH:-}" ]; then
    echo "[SMOKE TEST 5.4] Testing authenticated HTTP access across critical routes (credentials masked)..."
    
    check_http_endpoint() {
        local path="$1"
        local expected="$2"
        local code
        code=$(curl -s -u "${TEST_HTTP_AUTH}" -o /dev/null -w "%{http_code}" --max-time 10 "${SMOKE_URL}${path}" || echo "000")
        echo "  - Route ${path} -> HTTP ${code} (expected ${expected})"
        if [ "${code}" != "${expected}" ]; then
            echo "[ERROR] Route ${path} failed: expected HTTP ${expected}, received HTTP ${code}" >&2
            return 1
        fi
    }

    check_http_endpoint "/" "200"
    check_http_endpoint "/csrf-token" "200"
    check_http_endpoint "/member/login" "200"
    check_http_endpoint "/loan-schedule" "200"
    check_http_endpoint "/documents" "200"
    check_http_endpoint "/associations" "200"
    check_http_endpoint "/welfare" "200"
    check_http_endpoint "/complaints" "200"
    echo "[PASS] All authenticated endpoint smoke tests succeeded (HTTP 200)."
else
    echo "[NOTICE] TEST_HTTP_AUTH not provided in environment; external authenticated curl verification skipped."
    echo "         Local PHP-FPM, static chunk verification, and database connectivity verified 100%."
fi

# Disarm trap on successful completion
trap - ERR INT TERM

echo ""
echo "=============================================================================="
echo " RYCOOPWEB TEST UAT DEPLOYMENT COMPLETED SUCCESSFULLY"
echo " Active Backend: ${UAT_BACKEND}"
echo " Active Static:  ${UAT_STATIC}"
echo " Database:       rycoopweb_test"
echo " Pre-UAT Backup: ${SHARED_TEST_DIR}/last_pre_uat_backend.txt"
echo " Status:         READY FOR UAT VERIFICATION"
echo "=============================================================================="
