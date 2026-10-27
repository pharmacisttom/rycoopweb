<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MigrationRunner;

// Helper assertion function
function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function assertThrows(callable $fn, string $expectedMessageSubstring, string $testName): void
{
    try {
        $fn();
        echo "FAIL: {$testName} (Expected exception containing '{$expectedMessageSubstring}', but none was thrown)\n";
        exit(1);
    } catch (\Throwable $e) {
        if (!str_contains($e->getMessage(), $expectedMessageSubstring)) {
            echo "FAIL: {$testName} (Expected substring '{$expectedMessageSubstring}', got '{$e->getMessage()}')\n";
            exit(1);
        }
        echo "PASS: {$testName}\n";
    }
}

echo "Running MigrationRunner Unit & Safety Tests...\n";
echo "--------------------------------------------------\n";

// Create an isolated in-memory SQLite PDO instance to test runner logic safely
$sqlitePdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 1. Path traversal protection test
$runner = new MigrationRunner($sqlitePdo, __DIR__ . '/../database/migrations', 'sqlite');

assertThrows(function () use ($runner) {
    $runner->resolveMigrationFile('../../etc/passwd');
}, 'Invalid migration filename format', 'Path traversal attempt with ../.. is rejected');

assertThrows(function () use ($runner) {
    $runner->resolveMigrationFile('001_initial_schema.php');
}, 'Invalid migration filename format', 'Non-sql file extension is rejected');

assertThrows(function () use ($runner) {
    $runner->resolveMigrationFile('999_non_existent.sql');
}, 'Migration file does not exist', 'Non-existent migration file is safely reported');

// Valid file resolution
$resolved = $runner->resolveMigrationFile('001_initial_schema.sql');
assertTrue(file_exists($resolved), 'Valid migration file resolves strictly inside migrations directory');

// 2. Destructive SQL analysis test
$destructiveSql = "ALTER TABLE users DROP COLUMN old_col; DROP TABLE legacy_logs;";
$analysis = $runner->analyzeSql($destructiveSql);
assertTrue($analysis['is_destructive'] === true, 'Destructive statements (DROP TABLE, DROP COLUMN) are detected');

$financialSql = "UPDATE interest_rates SET rate = 1.75 WHERE id = 1;";
$analysisFin = $runner->analyzeSql($financialSql);
assertTrue($analysisFin['is_financial'] === true, 'Financial table mutations (interest_rates) are detected');

// 3. Destructive confirmation gate
$runner->setDryRun(true);
assertThrows(function () use ($runner) {
    // 019 has UPDATE interest_rates
    $runner->setConfirmFinancial(false);
    $runner->runSingle('019_cleanup_legacy_interest_rates.sql');
}, 'Financial migration safety gate triggered', 'Financial migration without confirmation is blocked');

// With confirmation in dry-run mode
$runner->setConfirmFinancial(true);
$result = $runner->runSingle('019_cleanup_legacy_interest_rates.sql');
assertTrue($result['status'] === 'dry_run_passed', 'Dry-run with financial confirmation passes preflight');

// 4. Test database target verification logic
$mockPdoTargetFail = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function getAttribute(int $attribute): mixed {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'mysql';
        return parent::getAttribute($attribute);
    }
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): mixed {
        return new class {
            public function fetchColumn() { return 'information_schema'; }
        };
    }
};

$runnerSysDb = new MigrationRunner($mockPdoTargetFail, __DIR__ . '/../database/migrations', 'test_db');
assertThrows(function () use ($runnerSysDb) {
    $runnerSysDb->verifyDatabaseTarget();
}, 'Safety violation: Refusing to execute migrations against system database', 'System database target is rejected');

$mockPdoWrongDb = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function getAttribute(int $attribute): mixed {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'mysql';
        return parent::getAttribute($attribute);
    }
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): mixed {
        return new class {
            public function fetchColumn() { return 'wrong_production_db'; }
        };
    }
};

$runnerWrongDb = new MigrationRunner($mockPdoWrongDb, __DIR__ . '/../database/migrations', 'intended_test_db');
assertThrows(function () use ($runnerWrongDb) {
    $runnerWrongDb->verifyDatabaseTarget();
}, 'Database target mismatch', 'Mismatched database name is rejected immediately');

echo "--------------------------------------------------\n";
echo "All MigrationRunner Unit & Safety tests passed!\n";
