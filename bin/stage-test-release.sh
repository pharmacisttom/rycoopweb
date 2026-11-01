#!/usr/bin/env bash
# ==============================================================================
# RYCOOPWEB 3.0 — STAGE TEST RELEASE (IMMUTABLE & NON-SILENT DIAGNOSTICS)
#
# Release Target: TEST ONLY (https://test.rayongcoop.org)
# Target Commit: 19e9c7da2746a7879c723733dbadc70291290dfc
# Baseline Release: db0f08a09a056ff854b80350f9e45d0663215024
# ==============================================================================
set -euo pipefail

TARGET_SHA="${1:-19e9c7da2746a7879c723733dbadc70291290dfc}"
REPO_URL="https://github.com/pharmacisttom/rycoopweb.git"
BASE_DIR="/var/www/rycoopweb"
RELEASES_DIR="${BASE_DIR}/releases"
SHARED_TEST_DIR="${BASE_DIR}/shared-test"
TARGET_BACKEND="${RELEASES_DIR}/${TARGET_SHA}"
TARGET_STATIC="${RELEASES_DIR}/${TARGET_SHA}-static"

echo "=============================================================================="
echo " RYCOOPWEB STAGE TEST RELEASE: ${TARGET_SHA}"
echo "=============================================================================="

# ------------------------------------------------------------------------------
# PHASE 1: DIAGNOSTIC PRECONDITION VERIFICATION (NO SILENT FAILURES)
# ------------------------------------------------------------------------------
echo "--- [PHASE 1] Precondition & Shared Storage Diagnostics ---"

# 1. Base Releases Directory
if [ ! -d "${RELEASES_DIR}" ]; then
    echo "[DIAGNOSTIC] Releases directory missing at ${RELEASES_DIR}. Creating..."
    mkdir -p "${RELEASES_DIR}"
    echo "[PASS] Created ${RELEASES_DIR}"
else
    echo "[PASS] Releases directory exists: ${RELEASES_DIR}"
fi

# 2. Shared-Test Directory & Shared .env
if [ ! -d "${SHARED_TEST_DIR}" ]; then
    echo "[DIAGNOSTIC] Shared-test directory missing at ${SHARED_TEST_DIR}. Creating..."
    mkdir -p "${SHARED_TEST_DIR}"
fi

SHARED_ENV="${SHARED_TEST_DIR}/.env"
if [ ! -f "${SHARED_ENV}" ]; then
    echo "[ERROR] Precondition Failed: Shared TEST .env missing at ${SHARED_ENV}" >&2
    echo "       Refusing to continue without verified TEST environment configuration." >&2
    exit 1
fi
echo "[PASS] Shared TEST .env verified: ${SHARED_ENV}"

# 3. Shared Storage Directories (uploads, logs, tmp)
for sub in uploads logs tmp; do
    DIR_PATH="${SHARED_TEST_DIR}/${sub}"
    if [ ! -d "${DIR_PATH}" ]; then
        echo "[DIAGNOSTIC] ${sub} directory missing at ${DIR_PATH}. Creating with secure permissions..."
        mkdir -p "${DIR_PATH}"
        chown -R www-data:www-data "${DIR_PATH}" || true
        chmod -R 775 "${DIR_PATH}" || true
        echo "[PASS] Created and secured: ${DIR_PATH}"
    else
        echo "[PASS] Shared storage exists: ${DIR_PATH}"
    fi
done

# 4. Inspect Current Live Release Symlinks
ACTIVE_BACKEND_LINK="${BASE_DIR}/test-v2"
ACTIVE_STATIC_LINK="${BASE_DIR}/test-v2-static"

CURRENT_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}" 2>/dev/null || echo "NONE")
CURRENT_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}" 2>/dev/null || echo "NONE")

echo "[DIAGNOSTIC] Current Active Backend Symlink: ${CURRENT_BACKEND}"
echo "[DIAGNOSTIC] Current Active Frontend Symlink: ${CURRENT_STATIC}"

if [ "${CURRENT_BACKEND}" != "NONE" ]; then
    echo "${CURRENT_BACKEND}" > "${SHARED_TEST_DIR}/last_active_backend.txt"
    echo "${CURRENT_STATIC}" > "${SHARED_TEST_DIR}/last_active_static.txt"
fi

# ------------------------------------------------------------------------------
# PHASE 2: STAGE IMMUTABLE RELEASE DIRECTORIES
# ------------------------------------------------------------------------------
echo ""
echo "--- [PHASE 2] Staging Immutable Release ---"
echo "Target Backend Path: ${TARGET_BACKEND}"
echo "Target Frontend Static Path: ${TARGET_STATIC}"

mkdir -p "${TARGET_STATIC}"

if [ -d "${TARGET_BACKEND}/.git" ]; then
    echo "[DIAGNOSTIC] Release directory exists. Checking commit..."
    CURRENT_REV=$(git -C "${TARGET_BACKEND}" rev-parse HEAD 2>/dev/null || echo "")
    if [ "${CURRENT_REV}" != "${TARGET_SHA}" ]; then
        echo "[DIAGNOSTIC] Fetching approved target commit ${TARGET_SHA}..."
        git -C "${TARGET_BACKEND}" fetch origin "${TARGET_SHA}"
        git -C "${TARGET_BACKEND}" checkout "${TARGET_SHA}"
    else
        echo "[PASS] Repository is already at verified commit ${TARGET_SHA}"
    fi
else
    echo "[DIAGNOSTIC] Cloning repository into immutable release directory..."
    git clone "${REPO_URL}" "${TARGET_BACKEND}"
    git -C "${TARGET_BACKEND}" checkout "${TARGET_SHA}"
    echo "[PASS] Checked out commit $(git -C "${TARGET_BACKEND}" rev-parse --short HEAD)"
fi

cd "${TARGET_BACKEND}"

# 1. Link Persistent Shared Storage & TEST .env
echo "[DIAGNOSTIC] Linking persistent shared storage and configuration..."
rm -f "${TARGET_BACKEND}/.env"
ln -sfn "${SHARED_ENV}" "${TARGET_BACKEND}/.env"

rm -rf "${TARGET_BACKEND}/storage/uploads" "${TARGET_BACKEND}/storage/logs" "${TARGET_BACKEND}/storage/tmp"
mkdir -p "${TARGET_BACKEND}/storage"
ln -sfn "${SHARED_TEST_DIR}/uploads" "${TARGET_BACKEND}/storage/uploads"
ln -sfn "${SHARED_TEST_DIR}/logs" "${TARGET_BACKEND}/storage/logs"
ln -sfn "${SHARED_TEST_DIR}/tmp" "${TARGET_BACKEND}/storage/tmp"

# 2. Deterministic Dependencies & Build
echo "[DIAGNOSTIC] Installing production PHP dependencies via Composer..."
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "[DIAGNOSTIC] Installing Node dependencies and compiling React frontend..."
npm ci --silent
npm run build

echo "[DIAGNOSTIC] Syncing compiled assets to immutable static release directory..."
cp -r public/app/* "${TARGET_STATIC}/"

# 3. Ownership & File Permissions
echo "[DIAGNOSTIC] Setting strict least-privilege permissions..."
chown -R www-data:www-data "${TARGET_BACKEND}" "${TARGET_STATIC}"
find "${TARGET_BACKEND}" -type d -exec chmod 755 {} \;
find "${TARGET_BACKEND}" -type f -exec chmod 644 {} \;
find "${TARGET_BACKEND}/bin" -type f -exec chmod 755 {} \;
chmod -R 775 "${TARGET_BACKEND}/storage"

# 4. Validate PHP-FPM Access
echo "[DIAGNOSTIC] Validating PHP-FPM read/write access..."
sudo -u www-data test -r "${TARGET_BACKEND}/public/index.php" || {
    echo "[ERROR] PHP-FPM user www-data cannot read index.php" >&2
    exit 1
}
sudo -u www-data test -w "${TARGET_BACKEND}/storage/logs" || {
    echo "[ERROR] PHP-FPM user www-data cannot write to storage/logs" >&2
    exit 1
}
echo "[PASS] PHP-FPM permission check verified."

echo "=============================================================================="
echo " STAGING COMPLETE: Release ${TARGET_SHA} is ready for deployment."
echo "=============================================================================="

