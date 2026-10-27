<?php

declare(strict_types=1);

/**
 * Hardened CLI Migration Runner for Single Migration Files.
 *
 * Usage:
 *   php database/run_migration.php <filename.sql> [options]
 *
 * Options:
 *   --dry-run               Preflight mode: validate database and checksums without executing.
 *   --confirm-financial     Acknowledge and authorize changes to financial product rates.
 *   --confirm-destructive   Acknowledge and authorize destructive schema changes (DROP/TRUNCATE).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MigrationRunner;

// Parse command-line arguments
$options = [
    'dry-run' => in_array('--dry-run', $argv, true),
    'confirm-financial' => in_array('--confirm-financial', $argv, true),
    'confirm-destructive' => in_array('--confirm-destructive', $argv, true),
    'force-retry-failed' => in_array('--force-retry-failed', $argv, true),
];

// Extract filename (first non-flag argument)
$targetFile = null;
for ($i = 1; $i < count($argv); $i++) {
    if (!str_starts_with($argv[$i], '--')) {
        $targetFile = $argv[$i];
        break;
    }
}

if ($targetFile === null || $targetFile === '' || in_array($targetFile, ['-h', '--help', 'help'], true)) {
    echo "====================================================================\n";
    echo "RYCOOPWEB Hardened Migration Runner\n";
    echo "====================================================================\n";
    echo "Usage: php database/run_migration.php <migration_file.sql> [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run               Simulate and validate migration without applying.\n";
    echo "  --confirm-financial     Required for migrations altering financial rates (019, 020).\n";
    echo "  --confirm-destructive   Required for destructive migrations (DROP/TRUNCATE).\n";
    echo "  --force-retry-failed    Force retry of a failed migration after manual schema reconciliation.\n\n";
    echo "Example:\n";
    echo "  php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql --dry-run\n";
    echo "  php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql\n";
    exit(1);
}

try {
    $runner = new MigrationRunner();
    $runner->setDryRun($options['dry-run'])
           ->setConfirmFinancial($options['confirm-financial'])
           ->setConfirmDestructive($options['confirm-destructive'])
           ->setForceRetryFailed($options['force-retry-failed']);

    echo "Running migration checks for: {$targetFile}...\n";
    $result = $runner->runSingle($targetFile);

    foreach ($runner->getLogs() as $log) {
        echo "{$log}\n";
    }

    if ($result['status'] === 'dry_run_passed') {
        echo "\n✓ Preflight validation passed successfully! (Dry-run mode — no changes made)\n";
    } elseif ($result['status'] === 'applied') {
        echo "\n✓ Migration {$targetFile} applied and recorded successfully ({$result['execution_time_ms']}ms)!\n";
    } elseif ($result['status'] === 'skipped') {
        echo "\n⏭ Migration was already applied previously.\n";
    }

    exit(0);
} catch (\Throwable $e) {
    echo "\n✗ MIGRATION SAFETY ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
