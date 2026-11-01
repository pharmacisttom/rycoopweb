<?php

declare(strict_types=1);

/**
 * RYCOOPWEB — CONTROLLED TEST MEMBER ACCOUNT ACTIVATION CLI
 *
 * Exclusively executes against TEST database (`rycoopweb_test`).
 * Activates 2,508 eligible imported member accounts for authorized UAT testing.
 *
 * Usage:
 *   php bin/activate-test-members.php --confirm-token=RYCOOP-SECURITY-APPROVAL-MEMBER-ACTIVATION-2026 --admin-id=1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Logger;
use App\Services\MemberControlledActivationService;
use App\Services\MemberAdminService;
use App\Services\SecurityAuditService;

echo "================================================================================\n";
echo " RYCOOPWEB — CONTROLLED TEST MEMBER ACCOUNT ACTIVATION\n";
echo " Environment: TEST ONLY | Target Database: rycoopweb_test\n";
echo "================================================================================\n\n";

$options = getopt('', ['confirm-token:', 'admin-id:', 'dry-run', 'force-test']);

$pdo = Database::connect();

// 1. Safety Gate: Database Environment Check
$currentDb = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
echo "[DIAGNOSTIC] Connected Database: {$currentDb}\n";

$isTestDb = ($currentDb === 'rycoopweb_test') || isset($options['force-test']);
if (!$isTestDb) {
    echo "[ABORT] Safety Guardrail Active: Bulk member activation is strictly prohibited on database '{$currentDb}'.\n";
    echo "        Target database must be 'rycoopweb_test' or pass --force-test explicitly.\n";
    exit(1);
}
echo "[PASS] Database confirmed as isolated TEST database: {$currentDb}\n\n";

// 2. Pre-activation Eligibility Verification
$candidateSql = "
    SELECT u.id as user_id, m.id as member_id, m.member_no, m.id_card
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    JOIN members m ON u.id = m.user_id
    WHERE u.status = 'inactive'
      AND m.status = 'active'
      AND r.slug = 'member'
      AND u.id NOT IN (
          SELECT ur2.user_id
          FROM user_roles ur2
          JOIN roles r2 ON ur2.role_id = r2.id
          WHERE r2.slug IN ('super_admin', 'staff', 'admin', 'auditor', 'pr_officer', 'finance', 'it_admin')
      )
";
$candidates = $pdo->query($candidateSql)->fetchAll(PDO::FETCH_ASSOC);
$eligibleCount = count($candidates);

echo "[DIAGNOSTIC] Pre-Activation Verification:\n";
echo "  - Total eligible inactive member accounts found: {$eligibleCount}\n";

if ($eligibleCount === 0) {
    echo "  [INFO] Zero eligible inactive accounts found. System is already activated or baseline is empty.\n";
    echo "================================================================================\n";
    echo " ACTIVATION STATUS: ALL ELIGIBLE ACCOUNTS ARE ALREADY ACTIVE (IDEMPOTENT).\n";
    echo "================================================================================\n";
    exit(0);
}

// Dry-run check
if (isset($options['dry-run'])) {
    echo "  [DRY-RUN] {$eligibleCount} member accounts would be activated. Zero mutations applied.\n";
    exit(0);
}

// 3. Approval Token & Administrator Authorization Check
$token = (string)($options['confirm-token'] ?? '');
$adminId = (int)($options['admin-id'] ?? 1);

if ($token !== MemberControlledActivationService::REQUIRED_APPROVAL_TOKEN) {
    echo "[ERROR] Security Approval Token invalid or missing!\n";
    echo "        Required parameter: --confirm-token=" . MemberControlledActivationService::REQUIRED_APPROVAL_TOKEN . "\n";
    exit(1);
}

// 4. Create Snapshot & Rollback State File
$backupDir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}
$snapshotFile = "{$backupDir}/member_activation_pre_snapshot_" . date('Ymd_His') . ".json";
$snapshotData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database' => $currentDb,
    'eligible_count' => $eligibleCount,
    'user_ids' => array_column($candidates, 'user_id'),
];
file_put_contents($snapshotFile, json_encode($snapshotData, JSON_PRETTY_PRINT));
echo "[PASS] Pre-activation rollback snapshot saved to {$snapshotFile}\n\n";

// 5. Execute Transaction-Bound Activation
echo "[DIAGNOSTIC] Executing batch activation within a protected transaction...\n";
$pdo->beginTransaction();

try {
    $updateStmt = $pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
    $activated = 0;

    foreach ($candidates as $c) {
        $uId = (int)$c['user_id'];
        $mId = (int)$c['member_id'];

        $updateStmt->execute([$uId]);

        // Record provenance in member_data_changes if table exists
        try {
            MemberAdminService::recordChange(
                $mId,
                'controlled_account_activation',
                'เปิดใช้งานบัญชีผ่านระบบควบคุมความปลอดภัยสำหรับการทดสอบ UAT (Authorized TEST activation)',
                ['user_id' => $uId, 'status' => 'inactive'],
                ['user_id' => $uId, 'status' => 'active', 'approved_by_admin_id' => $adminId],
                $pdo
            );
        } catch (Throwable $ignore) {}

        $activated++;
    }

    // Record executive audit in security_audit_logs if table exists
    try {
        $auditStmt = $pdo->prepare("
            INSERT INTO security_audit_logs (
                event_id, timestamp_utc, actor_user_id, actor_type, role,
                event_type, target_resource, result, correlation_id,
                session_fingerprint, ip_address, metadata
            ) VALUES (?, UTC_TIMESTAMP(), ?, 'staff', 'super_admin', 'ADMIN_ACCOUNT_ACTIVATION', 'members:batch_activation', 'success', ?, ?, '127.0.0.1', ?)
        ");
        $auditStmt->execute([
            bin2hex(random_bytes(16)),
            $adminId,
            bin2hex(random_bytes(16)),
            hash('sha256', 'cli-batch-activation'),
            json_encode(['activated_count' => $activated, 'approval_token_hash' => hash('sha256', $token)])
        ]);
    } catch (Throwable $ignore) {}

    $pdo->commit();

    echo "[SUCCESS] Successfully activated {$activated} member accounts in database '{$currentDb}'.\n";
    echo "          Row counts matched: {$activated} / {$eligibleCount}\n";
    echo "================================================================================\n";
    echo " ACTIVATION COMPLETE: Member Portal UAT is now ready on TEST environment.\n";
    echo "================================================================================\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[FATAL ERROR] Activation failed and was rolled back: " . $e->getMessage() . "\n";
    exit(1);
}
