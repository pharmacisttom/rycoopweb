<?php

declare(strict_types=1);

/**
 * RYCOOPWEB — ROLLBACK TEST MEMBER ACCOUNT ACTIVATION CLI
 *
 * Reverts activated member accounts back to 'inactive' status using a snapshot file.
 *
 * Rules:
 * - Strictly restricted to 'rycoopweb_test' database.
 * - Prevents modifying any account not present in the verified activation snapshot.
 * - Protects all staff, admin, and non-member accounts.
 *
 * Usage:
 *   php bin/rollback-test-activation.php [--snapshot=storage/backups/...]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Services\SecurityAuditService;

echo "================================================================================\n";
echo " RYCOOPWEB — ROLLBACK TEST MEMBER ACCOUNT ACTIVATION\n";
echo " Target Database: rycoopweb_test (Strictly Enforced)\n";
echo "================================================================================\n\n";

$options = getopt('', ['snapshot:', 'admin-id:']);

$pdo = Database::connect();
$currentDb = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
echo "[DIAGNOSTIC] Connected Database: {$currentDb}\n";

// RULE 8: Require exact rycoopweb_test database identity
if ($currentDb !== 'rycoopweb_test') {
    echo "[ABORT] Safety Guardrail Active: Rollback is strictly restricted to database 'rycoopweb_test'.\n";
    echo "        Current database is '{$currentDb}'. Rollback refused.\n";
    exit(1);
}
echo "[PASS] Database confirmed as verified TEST database: rycoopweb_test\n\n";

$backupDir = dirname(__DIR__) . '/storage/backups';
$snapshotFile = $options['snapshot'] ?? null;

if (!$snapshotFile) {
    // Locate the latest snapshot file
    $files = glob("{$backupDir}/member_activation_pre_snapshot_*.json");
    if (!empty($files)) {
        rsort($files);
        $snapshotFile = $files[0];
    }
}

if (!$snapshotFile || !file_exists($snapshotFile)) {
    echo "[ERROR] No valid snapshot file found in {$backupDir}!\n";
    exit(1);
}

echo "[DIAGNOSTIC] Inspecting snapshot: {$snapshotFile}\n";
$data = json_decode(file_get_contents($snapshotFile), true);

// Verify snapshot database matches rycoopweb_test
if (($data['database'] ?? '') !== 'rycoopweb_test') {
    echo "[ERROR] Snapshot indicates database was '{$data['database']}', not 'rycoopweb_test'. Rollback rejected.\n";
    exit(1);
}

// RULE 10: Prevent rollback from changing accounts not included in the verified activation snapshot
$userIds = array_map('intval', $data['user_ids'] ?? []);
if (empty($userIds)) {
    echo "[ERROR] Snapshot contains no user IDs to revert.\n";
    exit(1);
}

echo "[DIAGNOSTIC] Snapshot contains " . count($userIds) . " verified target member account IDs.\n";
echo "[DIAGNOSTIC] Executing selective rollback within a database transaction...\n";

$adminId = (int)($options['admin-id'] ?? 1);
$pdo->beginTransaction();

try {
    // Revert ONLY accounts that:
    // 1. Are explicitly present in the snapshot user_ids list
    // 2. Are currently 'active'
    // 3. Belong strictly to the 'member' role
    // 4. Do NOT belong to any staff, super_admin, or administrative roles
    $revertStmt = $pdo->prepare("
        UPDATE users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        SET u.status = 'inactive', u.updated_at = NOW()
        WHERE u.id = ?
          AND u.status = 'active'
          AND r.slug = 'member'
          AND u.id NOT IN (
              SELECT ur2.user_id
              FROM user_roles ur2
              JOIN roles r2 ON ur2.role_id = r2.id
              WHERE r2.slug IN ('super_admin', 'staff', 'admin', 'auditor', 'pr_officer', 'finance', 'it_admin')
          )
    ");

    $reverted = 0;
    foreach ($userIds as $id) {
        $revertStmt->execute([$id]);
        $reverted += $revertStmt->rowCount();
    }

    // Mandatory security audit log for rollback
    $auditStmt = $pdo->prepare("
        INSERT INTO security_audit_logs (
            event_id, timestamp_utc, actor_user_id, actor_type, role,
            event_type, target_resource, result, correlation_id,
            session_fingerprint, ip_address, metadata
        ) VALUES (?, UTC_TIMESTAMP(), ?, 'staff', 'super_admin', 'ADMIN_ACCOUNT_ACTIVATION', 'members:batch_rollback', 'success', ?, ?, '127.0.0.1', ?)
    ");
    $auditStmt->execute([
        bin2hex(random_bytes(16)),
        $adminId,
        bin2hex(random_bytes(16)),
        hash('sha256', 'cli-batch-rollback'),
        json_encode([
            'reverted_count' => $reverted,
            'snapshot_file' => basename($snapshotFile)
        ], JSON_THROW_ON_ERROR)
    ]);

    $pdo->commit();

    echo "[SUCCESS] Successfully reverted {$reverted} member accounts back to 'inactive'.\n";
    echo "================================================================================\n";
    echo " ROLLBACK COMPLETE: Accounts restored to inactive baseline state.\n";
    echo "================================================================================\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[FATAL ERROR] Rollback failed: " . $e->getMessage() . "\n";
    exit(1);
}
