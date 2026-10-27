<?php

declare(strict_types=1);

/**
 * Hardened CLI Migration Runner for Single Migration Files.
 *
 * Usage:
 *   php database/run_migration.php <filename.sql> [options]
 *
 * Options:
 *   --dry-run               Simulate and validate migration without applying DDL/DML.
 *   --confirm-financial     Acknowledge and authorize changes to financial product rates.
 *   --confirm-destructive   Acknowledge and authorize destructive schema changes (DROP/TRUNCATE).
 *   --force-retry-failed    Acknowledge and force retry of a previously failed migration after manual reconciliation.
 *   --lab                   Target isolated lab database (rycoopweb_migration_lab).
 *   --db-host=<host>        Explicit database host (overrides DB_HOST).
 *   --db-port=<port>        Explicit database port (overrides DB_PORT).
 *   --db-name=<name>        Explicit database name (overrides DB_DATABASE).
 *   --db-user=<user>        Explicit database user (overrides DB_USERNAME; root prohibited).
 *   --db-pass=<pass>        Explicit database password (overrides DB_PASSWORD).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MigrationConfigValidator;
use App\Services\MigrationConnectionFactory;
use App\Services\MigrationRunner;

// Parse command-line arguments and configuration overrides
$options = [
    'dry-run' => in_array('--dry-run', $argv, true),
    'confirm-financial' => in_array('--confirm-financial', $argv, true),
    'confirm-destructive' => in_array('--confirm-destructive', $argv, true),
    'force-retry-failed' => in_array('--force-retry-failed', $argv, true),
    'lab' => in_array('--lab', $argv, true),
];

$dbOverrides = [];
if ($options['lab']) {
    $dbOverrides['database'] = 'rycoopweb_migration_lab';
    $dbOverrides['is_lab'] = true;
    $dbOverrides['explicit_database'] = true;
}

$targetFile = null;
for ($i = 1; $i < count($argv); $i++) {
    $arg = $argv[$i];
    if (str_starts_with($arg, '--db-host=')) {
        $dbOverrides['host'] = substr($arg, 10);
    } elseif (str_starts_with($arg, '--db-port=')) {
        $dbOverrides['port'] = (int) substr($arg, 10);
    } elseif (str_starts_with($arg, '--db-name=')) {
        $dbOverrides['database'] = substr($arg, 10);
        $dbOverrides['explicit_database'] = true;
    } elseif (str_starts_with($arg, '--db-user=')) {
        $dbOverrides['username'] = substr($arg, 10);
    } elseif (str_starts_with($arg, '--db-pass=')) {
        $dbOverrides['password'] = substr($arg, 10);
    } elseif (!str_starts_with($arg, '--')) {
        $targetFile = $arg;
    }
}

if ($targetFile === null || $targetFile === '' || in_array($targetFile, ['-h', '--help', 'help'], true)) {
    echo "====================================================================\n";
    echo "RYCOOPWEB Hardened Migration CLI — Database Isolation\n";
    echo "====================================================================\n";
    echo "Usage: php database/run_migration.php <migration_file.sql> [options]\n\n";
    echo "Options:\n";
    echo "  --dry-run               Simulate and validate migration without applying DDL/DML.\n";
    echo "  --confirm-financial     Required for migrations altering financial rates (019, 020).\n";
    echo "  --confirm-destructive   Required for destructive migrations (DROP/TRUNCATE).\n";
    echo "  --force-retry-failed    Force retry of a failed migration after manual schema reconciliation.\n";
    echo "  --lab                   Target isolated lab database (rycoopweb_migration_lab).\n";
    echo "  --db-host=<host>        Explicit target database host.\n";
    echo "  --db-port=<port>        Explicit target database port.\n";
    echo "  --db-name=<name>        Explicit target database name.\n";
    echo "  --db-user=<user>        Explicit target database user (root prohibited).\n";
    echo "  --db-pass=<pass>        Explicit target database password.\n\n";
    echo "Example:\n";
    echo "  php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql --dry-run\n";
    echo "  php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql --lab\n";
    exit(1);
}

try {
    // 1. Establish dedicated, isolated PDO connection with security validation
    $conn = MigrationConnectionFactory::createConnection($dbOverrides);
    $dedicatedPdo = $conn['pdo'];
    $targetDb = $conn['database'];

    // 2. Instantiate hardened runner with dedicated PDO and explicit expected database
    $runner = new MigrationRunner($dedicatedPdo, null, $targetDb);
    $runner->setDryRun($options['dry-run'])
           ->setConfirmFinancial($options['confirm-financial'])
           ->setConfirmDestructive($options['confirm-destructive'])
           ->setForceRetryFailed($options['force-retry-failed']);

    // 3. Preflight security inspection
    $preflight = $runner->getPreflightInfo($targetFile);

    echo "====================================================================\n";
    echo "RYCOOPWEB Migration CLI — Preflight Security Inspection\n";
    echo "====================================================================\n";
    echo "Target Host:        {$conn['host']}:{$conn['port']}\n";
    echo "Target Database:    {$conn['database']}" . ($conn['is_lab'] ? " [LAB ISOLATED]" : "") . "\n";
    echo "Database User:      {$conn['username']} (Connected as: {$conn['current_user']})\n";
    echo "Database Identity:  ✓ Verified (Active: {$preflight['active_database']})\n";
    echo "User Privileges:    " . (empty($conn['privileges']['warnings']) ? "✓ Verified" : "⚠ Warnings present") . "\n";
    echo "Target File:        {$preflight['migration']}\n";
    echo "Checksum (SHA-256): " . substr($preflight['checksum'], 0, 16) . "...\n";
    echo "Safety Gates:       " . ($preflight['analysis']['is_destructive'] ? "[DESTRUCTIVE] " : "") . ($preflight['analysis']['is_financial'] ? "[FINANCIAL] " : "") . ($preflight['analysis']['is_destructive'] || $preflight['analysis']['is_financial'] ? "" : "[NORMAL]") . "\n";
    echo "History Status:     " . ($preflight['is_already_applied'] ? "Already applied" : "Pending execution") . "\n";
    echo "====================================================================\n\n";

    echo "Running migration for: {$targetFile}...\n";
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
    echo "\n✗ MIGRATION SAFETY ERROR: " . MigrationRunner::sanitizeErrorMessage($e->getMessage()) . "\n";
    exit(1);
}
