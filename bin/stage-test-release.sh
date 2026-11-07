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

ACTIVE_BACKEND_LINK="${BASE_DIR}/test-v2"
ACTIVE_STATIC_LINK="${BASE_DIR}/test-v2-static"

echo "=============================================================================="
echo " RYCOOPWEB STAGE TEST RELEASE: ${TARGET_SHA}"
echo "=============================================================================="

# ------------------------------------------------------------------------------
# PHASE 1: PRECONDITION & DIRECTORY LAYOUT VERIFICATION
# ------------------------------------------------------------------------------
echo "--- [PHASE 1] Precondition & Shared Storage Diagnostics ---"

CURRENT_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}" 2>/dev/null || echo "NONE")
CURRENT_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}" 2>/dev/null || echo "NONE")

echo "[DIAGNOSTIC] Current Active Backend: ${CURRENT_BACKEND}"
echo "[DIAGNOSTIC] Current Active Frontend: ${CURRENT_STATIC}"

# RULE 1: Never checkout or modify the currently active TEST release
if [ "${TARGET_BACKEND}" = "${CURRENT_BACKEND}" ]; then
    echo "[ERROR] Precondition Failed: Target staging directory (${TARGET_BACKEND}) is currently active!" >&2
    echo "       Refusing to checkout or mutate the running release in-place." >&2
    exit 1
fi
echo "[PASS] Active release isolation verified (target differs from active release)."

# Base Releases Directory
if [ ! -d "${RELEASES_DIR}" ]; then
    echo "[DIAGNOSTIC] Releases directory missing at ${RELEASES_DIR}. Creating..."
    mkdir -p "${RELEASES_DIR}"
fi
echo "[PASS] Releases directory exists: ${RELEASES_DIR}"

# RULE 2: Resolve shared-test/.env against existing verified TEST configuration without overwriting or exposing secrets
if [ ! -d "${SHARED_TEST_DIR}" ]; then
    mkdir -p "${SHARED_TEST_DIR}"
fi

SHARED_ENV="${SHARED_TEST_DIR}/.env"
if [ ! -f "${SHARED_ENV}" ]; then
    echo "[DIAGNOSTIC] shared-test/.env not found. Resolving from verified active TEST release..."
    if [ "${CURRENT_BACKEND}" != "NONE" ] && [ -f "${CURRENT_BACKEND}/.env" ]; then
        cp "${CURRENT_BACKEND}/.env" "${SHARED_ENV}"
        chown www-data:www-data "${SHARED_ENV}" 2>/dev/null || true
        chmod 640 "${SHARED_ENV}" 2>/dev/null || true
        echo "[PASS] Successfully resolved shared-test/.env from active release without exposing secrets."
    else
        echo "[ERROR] Precondition Failed: Neither ${SHARED_ENV} nor active release .env found!" >&2
        echo "       Cannot proceed without verified TEST environment configuration." >&2
        exit 1
    fi
else
    echo "[PASS] Verified existing shared-test/.env preserved intact: ${SHARED_ENV}"
fi

# RULE 3: Preserve shared-test/uploads, logs, tmp and avoid unsafe recursive permission changes
for sub in uploads logs tmp; do
    DIR_PATH="${SHARED_TEST_DIR}/${sub}"
    if [ ! -d "${DIR_PATH}" ]; then
        echo "[DIAGNOSTIC] ${sub} directory missing at ${DIR_PATH}. Creating..."
        mkdir -p "${DIR_PATH}"
        chown www-data:www-data "${DIR_PATH}" 2>/dev/null || true
        chmod 775 "${DIR_PATH}" 2>/dev/null || true
        echo "[PASS] Created: ${DIR_PATH}"
    else
        # Only set ownership/permissions on the directory itself, NEVER recursively (-R) on existing files
        chown www-data:www-data "${DIR_PATH}" 2>/dev/null || true
        chmod 775 "${DIR_PATH}" 2>/dev/null || true
        echo "[PASS] Preserved shared storage directory: ${DIR_PATH}"
    fi
done

# Record previous release targets for deployment reference
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
    echo "[DIAGNOSTIC] Cloning clean repository into immutable release directory..."
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
VITE_FEATURE_MEMBER_PORTAL=false VITE_PUBLIC_STATIC_MODE=false npx vite build --base=/

echo "[DIAGNOSTIC] Syncing compiled assets to immutable static release directory..."
rm -rf "${TARGET_STATIC:?}"/*
cp -r public/app/* "${TARGET_STATIC}/"

# RULE 4: Validate correct backend and static release layouts
echo "[DIAGNOSTIC] Validating release directory layout..."
test -f "${TARGET_BACKEND}/public/index.php" || {
    echo "[ERROR] Backend layout invalid: missing public/index.php" >&2
    exit 1
}
test -f "${TARGET_BACKEND}/vendor/autoload.php" || {
    echo "[ERROR] Backend layout invalid: missing vendor/autoload.php" >&2
    exit 1
}
test -L "${TARGET_BACKEND}/.env" || {
    echo "[ERROR] Backend layout invalid: missing .env symlink" >&2
    exit 1
}
test -f "${TARGET_STATIC}/index.html" || {
    echo "[ERROR] Static layout invalid: missing index.html" >&2
    exit 1
}
test -d "${TARGET_STATIC}/assets" || {
    echo "[ERROR] Static layout invalid: missing assets/ directory" >&2
    exit 1
}
echo "[PASS] Release layouts verified (backend and static)."

# 3. Ownership & File Permissions on Release Code
echo "[DIAGNOSTIC] Setting strict least-privilege permissions on release code..."
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
