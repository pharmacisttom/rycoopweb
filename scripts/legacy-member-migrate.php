#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\LegacyMemberMigrationService;

echo "====================================================================\n";
echo "RYCOOPWEB 3.0 — Legacy Member Migration & Dividend Recovery CLI\n";
echo "====================================================================\n\n";

$isDryRun = in_array('--dry-run', $argv, true) || !in_array('--commit', $argv, true);
$sourcePath = null;
$batchTag = 'MIG-' . date('Ymd-His');
$rollbackBatch = null;
$years = [2567, 2568];
$enforceTestDb = true;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $sourcePath = substr($arg, 9);
    } elseif (str_starts_with($arg, '--batch-tag=')) {
        $batchTag = substr($arg, 12);
    } elseif (str_starts_with($arg, '--rollback=')) {
        $rollbackBatch = substr($arg, 11);
    } elseif (str_starts_with($arg, '--years=')) {
        $years = array_map('intval', explode(',', substr($arg, 8)));
    }
}

if ($rollbackBatch !== null) {
    echo "Initiating surgical batch rollback for: {$rollbackBatch}...\n";
    $result = LegacyMemberMigrationService::rollbackBatch($rollbackBatch);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit($result['success'] ? 0 : 1);
}

try {
    if ($sourcePath === null || $sourcePath === '') {
        throw new InvalidArgumentException('Explicit --source=/secure/path/rayongcoop_db.sql is required.');
    }
    $resolvedSource = LegacyMemberMigrationService::resolveDumpPath($sourcePath);
} catch (\Throwable $e) {
    fwrite(STDERR, "✗ Configuration error: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Source Dump: {$resolvedSource}\n";
echo "Target Years: " . implode(', ', $years) . "\n";
echo "Batch Tag: {$batchTag}\n";
echo "Database allowlist enforcement: ENABLED (TEST or disposable CI only)\n";
echo "Execution Mode: " . ($isDryRun ? "DRY-RUN (Simulate only, zero DB writes)" : "COMMITTED (Live DB writes)") . "\n";
echo "--------------------------------------------------------------------\n\n";

try {
    $result = LegacyMemberMigrationService::executeMigration([
        'source_path' => $resolvedSource,
        'dry_run' => $isDryRun,
        'batch_tag' => $batchTag,
        'years' => $years,
        'enforce_test_db' => $enforceTestDb,
    ]);

    echo "Status: " . $result['status'] . "\n";
    echo "Database: " . $result['database'] . "\n\n";

    echo "=== RECONCILIATION TOTALS ===\n";
    foreach ($result['source_reconciliation'] as $y => $stat) {
        echo "Year {$y}:\n";
        echo "  - Records: " . number_format($stat['record_count']) . "\n";
        echo "  - เงินปันผลรวม: " . number_format((float)$stat['sum_dividend'], 2) . " บาท\n";
        echo "  - เงินเฉลี่ยคืนรวม: " . number_format((float)$stat['sum_refund'], 2) . " บาท\n";
        echo "  - รายรับรวม: " . number_format((float)$stat['sum_total_income'], 2) . " บาท\n";
        echo "  - รายหักรวม: " . number_format((float)$stat['sum_total_deductions'], 2) . " บาท\n";
        echo "  - ยอดคงเหลือสุทธิ (Net): " . number_format((float)$stat['sum_net'], 2) . " บาท\n";
        echo "  - ยอดเงินที่ได้รับจริง: " . number_format((float)$stat['sum_received'], 2) . " บาท\n";
    }

    echo "\n=== MIGRATION SUMMARY ===\n";
    echo "Users: Source=" . $result['migration_summary']['users']['source_total'] .
         ", Inserted=" . $result['migration_summary']['users']['inserted'] .
         ", Updated=" . $result['migration_summary']['users']['updated'] . "\n";
    echo "Members: Source=" . $result['migration_summary']['members']['source_total'] .
         ", Inserted=" . $result['migration_summary']['members']['inserted'] .
         ", Updated=" . $result['migration_summary']['members']['updated'] . "\n";
    echo "Dividends: Source=" . $result['migration_summary']['dividends']['source_total'] .
         ", Inserted=" . $result['migration_summary']['dividends']['inserted'] .
         ", Updated=" . $result['migration_summary']['dividends']['updated'] . "\n";

    echo "\nCompleted successfully.\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "\n✗ Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
