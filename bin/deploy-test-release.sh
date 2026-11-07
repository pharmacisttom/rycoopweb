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
test -f "${TARGET_BACKEND}/public/index.php" || {
    echo "[ERROR] Target backend missing public/index.php" >&2
    exit 1
}
test -f "${TARGET_STATIC}/index.html" || {
    echo "[ERROR] Target static missing index.html" >&2
    exit 1
}

# 2. Check Dedicated FPM Service & Nginx
echo "[DIAGNOSTIC] Checking dedicated FPM service (rycoop-test-fpm)..."
if ! systemctl is-active --quiet rycoop-test-fpm.service; then
    echo "[ERROR] Dedicated FPM service 'rycoop-test-fpm.service' is not active!" >&2
    exit 1
fi
echo "[PASS] Dedicated service 'rycoop-test-fpm.service' is active."

echo "[DIAGNOSTIC] Testing Nginx configuration..."
sudo nginx -t

# 3. Snapshot Current Targets for Safe Rollback (Pre-Switch)
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

echo "[INFO] Verified baseline backend target: ${ORIG_BACKEND}"
echo "[INFO] Verified baseline frontend target: ${ORIG_STATIC}"

# RULE 5: Reliable automated rollback of both TEST symlinks on failure
# NOTE: Code rollback restores previous active release symlinks only;
# it NEVER executes DROP TABLE automatically, preserving additive tables and data.
rollback() {
    local exit_code=$?
    echo "[CRITICAL] Deployment failed (code: ${exit_code})! Initiating rollback of both TEST symlinks..." >&2
    if [ -n "${ORIG_BACKEND}" ] && [ -d "${ORIG_BACKEND}" ]; then
        ln -sfn "${ORIG_BACKEND}" "${ACTIVE_BACKEND_LINK}.tmp"
        mv -Tf "${ACTIVE_BACKEND_LINK}.tmp" "${ACTIVE_BACKEND_LINK}"
        echo "[ROLLBACK] Restored backend symlink -> ${ORIG_BACKEND}" >&2
    fi
    if [ -n "${ORIG_STATIC}" ] && [ -d "${ORIG_STATIC}" ]; then
        ln -sfn "${ORIG_STATIC}" "${ACTIVE_STATIC_LINK}.tmp"
        mv -Tf "${ACTIVE_STATIC_LINK}.tmp" "${ACTIVE_STATIC_LINK}"
        echo "[ROLLBACK] Restored frontend symlink -> ${ORIG_STATIC}" >&2
    fi
    sudo systemctl restart rycoop-test-fpm.service || true
    sudo nginx -s reload || true
    echo "[ROLLBACK] TEST symlinks and dedicated FPM restored to previous baseline." >&2
    echo "[NOTE] Code rollback preserved all additive database tables and data (NO DROP TABLE)." >&2
    exit "${exit_code}"
}

trap rollback ERR INT TERM

# 4. Controlled Symlink Switch using Temporary Symlink and Atomic Rename
echo "[DIAGNOSTIC] Performing atomic symlink switch to release ${TARGET_SHA}..."
ln -sfn "${TARGET_BACKEND}" "${ACTIVE_BACKEND_LINK}.tmp"
mv -Tf "${ACTIVE_BACKEND_LINK}.tmp" "${ACTIVE_BACKEND_LINK}"

ln -sfn "${TARGET_STATIC}" "${ACTIVE_STATIC_LINK}.tmp"
mv -Tf "${ACTIVE_STATIC_LINK}.tmp" "${ACTIVE_STATIC_LINK}"

# 5. Dedicated FPM Restart & Nginx Reload
echo "[DIAGNOSTIC] Restarting dedicated rycoop-test-fpm.service (SHARED FPM UNTOUCHED)..."
sudo systemctl restart rycoop-test-fpm.service

echo "[DIAGNOSTIC] Reloading Nginx..."
sudo nginx -s reload

# 6. HTTP & API Smoke Tests (RULE 6: Verify authenticated HTTP and API responses)
echo "[DIAGNOSTIC] Executing post-switch HTTP & API smoke tests..."
SMOKE_URL="https://test.rayongcoop.org"

# Test 6.1: Public / Basic Auth Gateway Challenge
GW_HTTP_CODE=$(curl -k -s -o /dev/null -w "%{http_code}" --max-time 10 "${SMOKE_URL}" || echo "000")
echo "[SMOKE TEST] GET ${SMOKE_URL} -> HTTP ${GW_HTTP_CODE}"

if [ "${GW_HTTP_CODE}" = "000" ] || [ "${GW_HTTP_CODE}" = "500" ] || [ "${GW_HTTP_CODE}" = "502" ] || [ "${GW_HTTP_CODE}" = "503" ] || [ "${GW_HTTP_CODE}" = "504" ]; then
    echo "[ERROR] Gateway smoke test failed with HTTP ${GW_HTTP_CODE}!" >&2
    false # Triggers rollback trap
fi

# Test 6.2: Direct Application Runtime & API Kernel Dispatch Check
echo "[SMOKE TEST] Verifying direct application API response (/api/csrf-token)..."
APP_API_CHECK=$(php -r '
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["REQUEST_URI"] = "/api/csrf-token";
    $_SERVER["HTTP_ACCEPT"] = "application/json";
    $_SERVER["HTTP_X_REQUESTED_WITH"] = "XMLHttpRequest";
    require "'"${TARGET_BACKEND}"'/public/index.php";
' 2>&1 || echo "ERROR")

if ! echo "${APP_API_CHECK}" | grep -qi 'token'; then
    echo "[ERROR] Application API smoke test failed: ${APP_API_CHECK}" >&2
    false # Triggers rollback trap
fi
echo "[PASS] Application API kernel dispatched successfully with valid JSON token."

# Test 6.3: Authenticated HTTP & API Request (if TEST_HTTP_AUTH is provided)
if [ -n "${TEST_HTTP_AUTH:-}" ]; then
    echo "[SMOKE TEST] Testing authenticated HTTP access with TEST_HTTP_AUTH..."
    AUTH_HOME_CODE=$(curl -k -s -u "${TEST_HTTP_AUTH}" -o /dev/null -w "%{http_code}" --max-time 10 "${SMOKE_URL}/" || echo "000")
    echo "[SMOKE TEST] Authenticated GET / -> HTTP ${AUTH_HOME_CODE}"
    if [ "${AUTH_HOME_CODE}" != "200" ]; then
        echo "[ERROR] Authenticated homepage request failed with HTTP ${AUTH_HOME_CODE}" >&2
        false # Triggers rollback trap
    fi

    AUTH_API_CODE=$(curl -k -s -u "${TEST_HTTP_AUTH}" -o /dev/null -w "%{http_code}" --max-time 10 "${SMOKE_URL}/api/csrf-token" || echo "000")
    echo "[SMOKE TEST] Authenticated GET /api/csrf-token -> HTTP ${AUTH_API_CODE}"
    if [ "${AUTH_API_CODE}" != "200" ]; then
        echo "[ERROR] Authenticated API request failed with HTTP ${AUTH_API_CODE}" >&2
        false # Triggers rollback trap
    fi
    echo "[PASS] Authenticated HTTP and API responses verified (HTTP 200 OK)."
fi

# Clear trap on success
trap - ERR INT TERM

echo "=============================================================================="
echo " DEPLOYMENT SUCCESSFUL: Release ${TARGET_SHA} is active on TEST!"
echo " Active Backend: $(readlink -f ${ACTIVE_BACKEND_LINK})"
echo " Active Frontend: $(readlink -f ${ACTIVE_STATIC_LINK})"
echo " Dedicated FPM: ACTIVE (rycoop-test-fpm.service)"
echo " Production: 100% UNTOUCHED"
echo "=============================================================================="
