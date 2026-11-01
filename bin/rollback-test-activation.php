<?php

declare(strict_types=1);

/**
 * RYCOOPWEB — ROLLBACK TEST MEMBER ACCOUNT ACTIVATION CLI
 *
 * Reverts activated member accounts back to 'inactive' status using a snapshot file.
 *
 * Usage:
 *   php bin/rollback-test-activation.php [--snapshot=storage/backups/...]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

echo "================================================================================\n";
echo " RYCOOPWEB — ROLLBACK TEST MEMBER ACCOUNT ACTIVATION\n";
echo "================================================================================\n\n";

$options = getopt('', ['snapshot:', 'force']);

$pdo = Database::connect();
$currentDb = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
echo "[DIAGNOSTIC] Connected Database: {$currentDb}\n";

$backupDir = dirname(__DIR__) . '/storage/backups';
$snapshotFile = $options['snapshot'] ?? null;

if (!$snapshotFile) {
    // Find the latest snapshot file
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

echo "[DIAGNOSTIC] Using snapshot: {$snapshotFile}\n";
$data = json_decode(file_get_contents($snapshotFile), true);
$userIds = $data['user_ids'] ?? [];

if (empty($userIds)) {
    echo "[ERROR] Snapshot contains no user IDs to revert.\n";
    exit(1);
}

echo "[DIAGNOSTIC] Reverting " . count($userIds) . " user accounts back to 'inactive'...\n";

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?");
    $reverted = 0;
    foreach ($userIds as $id) {
        $stmt->execute([(int)$id]);
        $reverted++;
    }
    $pdo->commit();
    echo "[SUCCESS] Successfully reverted {$reverted} accounts to 'inactive'.\n";
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
