#!/usr/bin/env bash
# ==============================================================================
# RYCOOPWEB 3.0 — HARDENED ROLLBACK TEST MEMBER PORTAL UAT SCRIPT
#
# Release Target: TEST ONLY (https://test.rayongcoop.org)
# Rollback Target: Exact previous baseline recorded before UAT switch
# ==============================================================================
set -euo pipefail

BASE_DIR="/var/www/rycoopweb"
SHARED_TEST_DIR="${BASE_DIR}/shared-test"

ACTIVE_BACKEND_LINK="${BASE_DIR}/test-v2"
ACTIVE_STATIC_LINK="${BASE_DIR}/test-v2-static"

echo "=============================================================================="
echo " RYCOOPWEB HARDENED ROLLBACK: TEST MEMBER PORTAL UAT"
echo " Target Environment: TEST ONLY (rycoopweb_test)"
echo "=============================================================================="

# ------------------------------------------------------------------------------
# HELPER: ATOMIC SYMLINK SWITCH WITH VALIDATION
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
# STEP 1: RESOLVE RECORDED PRE-UAT BASELINE TARGETS
# (Requirement 9: Must restore exact previous targets, not hardcoded paths)
# ------------------------------------------------------------------------------
BACKEND_RECORD="${SHARED_TEST_DIR}/last_pre_uat_backend.txt"
STATIC_RECORD="${SHARED_TEST_DIR}/last_pre_uat_static.txt"

if [ ! -f "${BACKEND_RECORD}" ] || [ ! -f "${STATIC_RECORD}" ]; then
    echo "[ERROR] Pre-UAT baseline records missing:" >&2
    echo "        Expected: ${BACKEND_RECORD} and ${STATIC_RECORD}" >&2
    echo "        Refusing to restore to hardcoded or unverified paths. Aborting." >&2
    exit 1
fi

PREV_BACKEND=$(cat "${BACKEND_RECORD}" | tr -d '[:space:]')
PREV_STATIC=$(cat "${STATIC_RECORD}" | tr -d '[:space:]')

if [ -z "${PREV_BACKEND}" ] || [ ! -d "${PREV_BACKEND}" ]; then
    echo "[ERROR] Recorded previous backend directory is invalid or missing: '${PREV_BACKEND}'" >&2
    exit 1
fi
if [ -z "${PREV_STATIC}" ] || [ ! -d "${PREV_STATIC}" ]; then
    echo "[ERROR] Recorded previous static directory is invalid or missing: '${PREV_STATIC}'" >&2
    exit 1
fi

echo "[DIAGNOSTIC] Restoring symlinks atomically to exact recorded pre-UAT baseline:"
echo "  Backend Target: ${PREV_BACKEND}"
echo "  Static Target:  ${PREV_STATIC}"

# ------------------------------------------------------------------------------
# STEP 2: ATOMIC RESTORATION OF TEST SYMLINKS
# (Requirement 6, 7: Validated temporary link & mv -Tf)
# ------------------------------------------------------------------------------
atomic_symlink_switch "${PREV_BACKEND}" "${ACTIVE_BACKEND_LINK}"
atomic_symlink_switch "${PREV_STATIC}" "${ACTIVE_STATIC_LINK}"

# ------------------------------------------------------------------------------
# STEP 3: RESTART DEDICATED rycoop-test-fpm.service ONLY
# (Requirement 12, 13: Shared FPM, Production, and Nginx untouched)
# ------------------------------------------------------------------------------
echo "[DIAGNOSTIC] Restarting dedicated rycoop-test-fpm.service (SHARED FPM & PROD UNTOUCHED)..."
sudo systemctl restart rycoop-test-fpm.service

if ! systemctl is-active --quiet rycoop-test-fpm.service; then
    echo "[ERROR] Dedicated FPM service 'rycoop-test-fpm.service' failed to restart!" >&2
    exit 1
fi
echo "[PASS] Dedicated 'rycoop-test-fpm.service' is active."

# ------------------------------------------------------------------------------
# STEP 4: POST-RESTORE VERIFICATION
# ------------------------------------------------------------------------------
echo "[DIAGNOSTIC] Verifying restored active release symlinks..."
CURRENT_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}")
CURRENT_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}")

if [ "${CURRENT_BACKEND}" != "${PREV_BACKEND}" ]; then
    echo "[ERROR] Active Backend mismatch: '${CURRENT_BACKEND}' != '${PREV_BACKEND}'" >&2
    exit 1
fi
if [ "${CURRENT_STATIC}" != "${PREV_STATIC}" ]; then
    echo "[ERROR] Active Static mismatch: '${CURRENT_STATIC}' != '${PREV_STATIC}'" >&2
    exit 1
fi

echo "[PASS] Active Backend successfully restored: ${CURRENT_BACKEND}"
echo "[PASS] Active Static successfully restored:  ${CURRENT_STATIC}"
echo "=============================================================================="
echo " ROLLBACK COMPLETE: TEST environment restored to exact pre-UAT release."
echo "=============================================================================="
