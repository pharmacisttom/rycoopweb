#!/usr/bin/env bash
# ==============================================================================
# RYCOOPWEB 3.0 — ATOMIC TEST DEPLOYMENT & SERVICE ACTIVATION
#
# Release Target: TEST ONLY (https://test.rayongcoop.org)
# Target Commit: 19e9c7da2746a7879c723733dbadc70291290dfc
# ==============================================================================
set -euo pipefail

TARGET_SHA="${1:-19e9c7da2746a7879c723733dbadc70291290dfc}"
BASE_DIR="/var/www/rycoopweb"
RELEASES_DIR="${BASE_DIR}/releases"
SHARED_TEST_DIR="${BASE_DIR}/shared-test"
TARGET_BACKEND="${RELEASES_DIR}/${TARGET_SHA}"
TARGET_STATIC="${RELEASES_DIR}/${TARGET_SHA}-static"

ACTIVE_BACKEND_LINK="${BASE_DIR}/test-v2"
ACTIVE_STATIC_LINK="${BASE_DIR}/test-v2-static"

echo "=============================================================================="
echo " RYCOOPWEB ATOMIC TEST DEPLOYMENT: ${TARGET_SHA}"
echo "=============================================================================="

# 1. Staging Pre-flight Verification
if [ ! -d "${TARGET_BACKEND}" ] || [ ! -d "${TARGET_STATIC}" ]; then
    echo "[ERROR] Staged release directory missing. Please run stage-test-release.sh first." >&2
    exit 1
fi

# 2. Check Dedicated FPM Service & Nginx
echo "[DIAGNOSTIC] Checking dedicated FPM service (rycoop-test-fpm)..."
if ! systemctl is-active --quiet rycoop-test-fpm.service; then
    echo "[ERROR] Dedicated FPM service 'rycoop-test-fpm.service' is not active!" >&2
    exit 1
fi
echo "[PASS] Dedicated service 'rycoop-test-fpm.service' is active."

echo "[DIAGNOSTIC] Testing Nginx configuration..."
sudo nginx -t

# 3. Snapshot Current Targets for Safe Rollback
PREV_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}" 2>/dev/null || echo "")
PREV_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}" 2>/dev/null || echo "")
echo "[INFO] Previous backend: ${PREV_BACKEND}"
echo "[INFO] Previous frontend: ${PREV_STATIC}"

# Define rollback function
rollback() {
    echo "[WARNING] Critical deployment failure! Executing automated rollback..." >&2
    if [ -n "${PREV_BACKEND}" ] && [ -d "${PREV_BACKEND}" ]; then
        ln -sfn "${PREV_BACKEND}" "${ACTIVE_BACKEND_LINK}"
        echo "[ROLLBACK] Restored backend symlink to ${PREV_BACKEND}" >&2
    fi
    if [ -n "${PREV_STATIC}" ] && [ -d "${PREV_STATIC}" ]; then
        ln -sfn "${PREV_STATIC}" "${ACTIVE_STATIC_LINK}"
        echo "[ROLLBACK] Restored frontend symlink to ${PREV_STATIC}" >&2
    fi
    sudo systemctl restart rycoop-test-fpm.service || true
    sudo nginx -s reload || true
    echo "[ROLLBACK] Dedicated services restored. Production remains 100% untouched." >&2
    exit 1
}

# 4. Atomic Symlink Switch
echo "[DIAGNOSTIC] Performing atomic symlink switch to release ${TARGET_SHA}..."
ln -sfn "${TARGET_BACKEND}" "${ACTIVE_BACKEND_LINK}"
ln -sfn "${TARGET_STATIC}" "${ACTIVE_STATIC_LINK}"

# 5. Dedicated FPM Restart & Nginx Reload
echo "[DIAGNOSTIC] Restarting dedicated rycoop-test-fpm.service (SHARED FPM UNTOUCHED)..."
if ! sudo systemctl restart rycoop-test-fpm.service; then
    echo "[ERROR] Failed to restart rycoop-test-fpm.service!" >&2
    rollback
fi

echo "[DIAGNOSTIC] Reloading Nginx..."
if ! sudo nginx -s reload; then
    echo "[ERROR] Failed to reload Nginx!" >&2
    rollback
fi

# 6. HTTP Smoke Tests
echo "[DIAGNOSTIC] Executing post-switch HTTP smoke tests..."
SMOKE_URL="https://test.rayongcoop.org"

# Check Basic Auth challenge or public response
HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 10 "${SMOKE_URL}" || echo "000")
echo "[SMOKE TEST] GET ${SMOKE_URL} -> HTTP ${HTTP_CODE}"

if [ "${HTTP_CODE}" = "000" ] || [ "${HTTP_CODE}" = "500" ] || [ "${HTTP_CODE}" = "502" ] || [ "${HTTP_CODE}" = "503" ] || [ "${HTTP_CODE}" = "504" ]; then
    echo "[ERROR] Smoke test failed with HTTP ${HTTP_CODE}!" >&2
    rollback
fi

echo "=============================================================================="
echo " DEPLOYMENT SUCCESSFUL: Release ${TARGET_SHA} is active on TEST!"
echo " Active Backend: $(readlink -f ${ACTIVE_BACKEND_LINK})"
echo " Active Frontend: $(readlink -f ${ACTIVE_STATIC_LINK})"
echo " Dedicated FPM: ACTIVE (rycoop-test-fpm.service)"
echo " Production: 100% UNTOUCHED"
echo "=============================================================================="
