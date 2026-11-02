#!/usr/bin/env bash
# ==============================================================================
# RYCOOPWEB 3.0 — ROLLBACK MEMBER PORTAL UAT RELEASE
#
# Release Target: TEST ONLY (https://test.rayongcoop.org)
# Rollback Target: Previous baseline release (pre-UAT flags)
# ==============================================================================
set -euo pipefail

TARGET_SHA="${1:-19e9c7da2746a7879c723733dbadc70291290dfc}"
BASE_DIR="/var/www/rycoopweb"
RELEASES_DIR="${BASE_DIR}/releases"
SHARED_TEST_DIR="${BASE_DIR}/shared-test"

ACTIVE_BACKEND_LINK="${BASE_DIR}/test-v2"
ACTIVE_STATIC_LINK="${BASE_DIR}/test-v2-static"

echo "=============================================================================="
echo " RYCOOPWEB ROLLBACK MEMBER PORTAL UAT RELEASE"
echo " Target Environment: TEST ONLY (rycoopweb_test)"
echo "=============================================================================="

# Determine previous release target
PREV_BACKEND=""
PREV_STATIC=""

if [ -f "${SHARED_TEST_DIR}/last_pre_uat_backend.txt" ]; then
    PREV_BACKEND=$(cat "${SHARED_TEST_DIR}/last_pre_uat_backend.txt" | tr -d '[:space:]')
fi
if [ -f "${SHARED_TEST_DIR}/last_pre_uat_static.txt" ]; then
    PREV_STATIC=$(cat "${SHARED_TEST_DIR}/last_pre_uat_static.txt" | tr -d '[:space:]')
fi

# Fallback to standard base release if snapshot file missing
if [ -z "${PREV_BACKEND}" ] || [ ! -d "${PREV_BACKEND}" ]; then
    PREV_BACKEND="${RELEASES_DIR}/${TARGET_SHA}"
fi
if [ -z "${PREV_STATIC}" ] || [ ! -d "${PREV_STATIC}" ]; then
    PREV_STATIC="${RELEASES_DIR}/${TARGET_SHA}-static"
fi

if [ ! -d "${PREV_BACKEND}" ] || [ ! -d "${PREV_STATIC}" ]; then
    echo "[ERROR] Cannot find valid rollback targets: ${PREV_BACKEND} or ${PREV_STATIC}" >&2
    exit 1
fi

echo "[DIAGNOSTIC] Restoring symlinks to pre-UAT baseline:"
echo "  Backend: ${PREV_BACKEND}"
echo "  Static:  ${PREV_STATIC}"

ln -sfn "${PREV_BACKEND}" "${ACTIVE_BACKEND_LINK}"
ln -sfn "${PREV_STATIC}" "${ACTIVE_STATIC_LINK}"

echo "[DIAGNOSTIC] Restarting dedicated rycoop-test-fpm.service..."
sudo systemctl restart rycoop-test-fpm.service

echo "[DIAGNOSTIC] Gracefully reloading Nginx..."
sudo nginx -s reload

echo "[DIAGNOSTIC] Verifying restored active release symlinks..."
CURRENT_BACKEND=$(readlink -f "${ACTIVE_BACKEND_LINK}")
CURRENT_STATIC=$(readlink -f "${ACTIVE_STATIC_LINK}")

echo "[PASS] Active Backend restored: ${CURRENT_BACKEND}"
echo "[PASS] Active Static restored:  ${CURRENT_STATIC}"
echo "=============================================================================="
echo " ROLLBACK COMPLETE: TEST environment restored to pre-UAT release."
echo "=============================================================================="
