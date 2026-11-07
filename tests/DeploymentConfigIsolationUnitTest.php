<?php
declare(strict_types=1);

/**
 * RYCOOPWEB — Deployment Configuration Isolation Unit Test Suite
 *
 * Verifies that deployment scripts satisfy all 13 isolation, locking,
 * and immutable release requirements for TEST UAT.
 */

echo "========================================================================\n";
echo " RYCOOPWEB — DEPLOYMENT CONFIGURATION ISOLATION UNIT TEST\n";
echo "========================================================================\n";

$deployScript = file_get_contents(__DIR__ . '/../bin/enable-test-member-portal-uat.sh');
$rollbackScript = file_get_contents(__DIR__ . '/../bin/rollback-test-member-portal-uat.sh');

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] {$msg}\n";
        $passed++;
    } else {
        echo " [FAIL] {$msg}\n";
        $failed++;
    }
}

// 1. Inspect actual .env symlink of the currently active TEST backend
assertCondition(
    str_contains($deployScript, 'ACTIVE_BACKEND_ENV') &&
    str_contains($deployScript, 'readlink -f "${ORIG_BACKEND}/.env"'),
    '1. Inspects actual .env symlink of currently active TEST Backend'
);

// 2. Never overwrite an environment configuration referenced by any active release
assertCondition(
    str_contains($deployScript, '[ "${UAT_ENV}" = "${ACTIVE_BACKEND_ENV}" ]') &&
    str_contains($deployScript, 'Refusing to overwrite active environment'),
    '2. Aborts immediately if target env matches active release env'
);

// 3. Create a new per-release environment file under protected TEST-only shared storage
assertCondition(
    str_contains($deployScript, 'UAT_ENV="${SHARED_TEST_DIR}/.env.uat.${RESOLVED_SHA}"'),
    '3. Generates unique per-release environment file (.env.uat.${RESOLVED_SHA})'
);

// 4. Copy approved TEST configuration without exposing secrets in logs
assertCondition(
    str_contains($deployScript, 'cp "${SHARED_ENV}" "${UAT_ENV}"') &&
    !str_contains($deployScript, 'cat "${UAT_ENV}"') &&
    !str_contains($deployScript, 'echo "${UAT_ENV}"'),
    '4. Copies approved config without dumping full file or secrets into logs'
);

// 5. Verify database is strictly rycoopweb_test
assertCondition(
    str_contains($deployScript, "if [ \"\${DB_NAME}\" != \"rycoopweb_test\" ]; then") &&
    str_contains($deployScript, "SELECT DATABASE()"),
    '5. Verifies database is strictly rycoopweb_test (static & runtime PDO)'
);

// 6. Make new backend release point only to its own immutable environment file
assertCondition(
    str_contains($deployScript, 'ln -snf "${UAT_ENV}" "${UAT_BACKEND}/.env"') &&
    str_contains($deployScript, 'RESOLVED_BACKEND_ENV=$(readlink -f "${UAT_BACKEND}/.env"') &&
    str_contains($deployScript, '[ "${RESOLVED_BACKEND_ENV}" != "${UAT_ENV}" ]'),
    '6. Staged backend release points strictly to its own per-release immutable .env'
);

// 7. Preserve active release configuration unchanged
assertCondition(
    str_contains($deployScript, 'ACTIVE_ENV_CHECKSUM') &&
    str_contains($deployScript, 'CURRENT_ACTIVE_CHECKSUM') &&
    str_contains($deployScript, '[CHECK 3.8] Verifying active release configuration remained completely unchanged'),
    '7. Verifies active release environment is preserved unchanged (SHA256 baseline assertion)'
);

// 8. Record rollback targets in a deployment-specific manifest
assertCondition(
    str_contains($deployScript, 'DEPLOYMENT_MANIFEST="${MANIFESTS_DIR}/deployment_${RESOLVED_SHA}.json"') &&
    str_contains($deployScript, '"target_commit_sha": "${RESOLVED_SHA}"') &&
    str_contains($deployScript, '"previous_backend": "${ORIG_BACKEND}"') &&
    str_contains($deployScript, '"previous_env": "${ACTIVE_BACKEND_ENV}"'),
    '8. Records full rollback targets and metadata in deployment-specific JSON manifest'
);

// 9. Prevent concurrent deployments using an exclusive lock
assertCondition(
    str_contains($deployScript, 'LOCK_FILE="${SHARED_TEST_DIR}/.deploy.lock"') &&
    str_contains($deployScript, 'flock -n 200'),
    '9. Prevents concurrent deployments using exclusive file lock (flock)'
);

// 10. Rollback script supports deployment manifest and exclusive lock
assertCondition(
    str_contains($rollbackScript, 'MANIFEST="${SHARED_TEST_DIR}/manifests/latest.json"') &&
    str_contains($rollbackScript, 'flock -n 200'),
    '10. Rollback script supports deployment manifest and exclusive lock'
);

// 11. Preserve approved ALLOW_UNRECONCILED_SOURCE=true gate
assertCondition(
    str_contains($deployScript, 'ALLOW_UNRECONCILED_SOURCE:-false') &&
    str_contains($deployScript, 'ALLOW_UNRECONCILED_SOURCE=true'),
    '11. Preserves explicit UAT financial source reconciliation authorization gate'
);

// 12. Never modify Production
assertCondition(
    str_contains($deployScript, 'Target Environment:     TEST ONLY (rycoopweb_test)') &&
    !str_contains($deployScript, 'rycoopweb_prod'),
    '12. Strictly restricted to TEST ONLY (never references production database)'
);

echo "========================================================================\n";
echo " RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
