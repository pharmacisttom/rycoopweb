<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MigrationRunner;

// Helper assertion functions
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

echo "Running MigrationRunner Comprehensive Unit, Safety & Security Tests...\n";
echo "=========================================================================\n";

$migrationsDir = realpath(__DIR__ . '/../database/migrations');

// --------------------------------------------------------------------------
// 1. Path traversal protection test
// --------------------------------------------------------------------------
$sqlitePdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$runner = new MigrationRunner($sqlitePdo, $migrationsDir, 'sqlite');

assertThrows(function () use ($runner) {
    $runner->resolveMigrationFile('../../etc/passwd');
}, 'Invalid migration filename format', '1.1 Path traversal attempt with ../.. is rejected');

assertThrows(function () use ($runner) {
    $runner->resolveMigrationFile('001_initial_schema.php');
}, 'Invalid migration filename format', '1.2 Non-sql file extension is rejected');

assertThrows(function () use ($runner) {
    $runner->resolveMigrationFile('999_non_existent.sql');
}, 'Migration file does not exist', '1.3 Non-existent migration file is safely reported');

$resolved = $runner->resolveMigrationFile('001_initial_schema.sql');
assertTrue(file_exists($resolved), '1.4 Valid migration file resolves strictly inside migrations directory');

// --------------------------------------------------------------------------
// 2. Destructive & Financial SQL analysis test
// --------------------------------------------------------------------------
$destructiveSql = "ALTER TABLE users DROP COLUMN old_col; DROP TABLE legacy_logs;";
$analysis = $runner->analyzeSql($destructiveSql);
assertTrue($analysis['is_destructive'] === true, '2.1 Destructive statements (DROP TABLE, DROP COLUMN) are detected');

$financialSql = "UPDATE interest_rates SET rate = 1.75 WHERE id = 1;";
$analysisFin = $runner->analyzeSql($financialSql);
assertTrue($analysisFin['is_financial'] === true, '2.2 Financial table mutations (interest_rates) are detected');

// --------------------------------------------------------------------------
// 3. Destructive and Financial confirmation gates
// --------------------------------------------------------------------------
$runner->setDryRun(true);
assertThrows(function () use ($runner) {
    $runner->setConfirmFinancial(false);
    $runner->runSingle('019_cleanup_legacy_interest_rates.sql');
}, 'Financial migration safety gate triggered', '3.1 Financial migration without confirmation is blocked');

$runner->setConfirmFinancial(true);
$result = $runner->runSingle('019_cleanup_legacy_interest_rates.sql');
assertTrue($result['status'] === 'dry_run_passed', '3.2 Dry-run with financial confirmation passes preflight');

// --------------------------------------------------------------------------
// 4. Database target verification logic
// --------------------------------------------------------------------------
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

$runnerSysDb = new MigrationRunner($mockPdoTargetFail, $migrationsDir, 'test_db');
assertThrows(function () use ($runnerSysDb) {
    $runnerSysDb->verifyDatabaseTarget();
}, 'Safety violation: Refusing to execute migrations against system database', '4.1 System database target is rejected');

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

$runnerWrongDb = new MigrationRunner($mockPdoWrongDb, $migrationsDir, 'intended_test_db');
assertThrows(function () use ($runnerWrongDb) {
    $runnerWrongDb->verifyDatabaseTarget();
}, 'Database target mismatch', '4.2 Mismatched database name is rejected immediately');

// --------------------------------------------------------------------------
// 5. Fail-closed Migration History Read & Credential Sanitization
// --------------------------------------------------------------------------
$mockPdoHistoryFail = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function getAttribute(int $attribute): mixed {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'sqlite';
        return parent::getAttribute($attribute);
    }
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): mixed {
        if (str_contains($query, 'sqlite_master')) {
            return new class {
                public function fetchColumn() { return 'migrations'; }
            };
        }
        if (str_contains($query, 'table_info')) {
            // Return empty columns to simulate legacy table inspection
            return new class {
                public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
                    return false;
                }
            };
        }
        if (str_contains($query, 'SELECT') && str_contains($query, 'migrations')) {
            throw new PDOException("SQLSTATE[HY000]: Disk I/O error or permission denied (password=SuperSecretPassword123!)");
        }
        return parent::query($query);
    }
};

$runnerHistoryFail = new MigrationRunner($mockPdoHistoryFail, $migrationsDir, 'sqlite');
assertThrows(function () use ($runnerHistoryFail) {
    $runnerHistoryFail->getAppliedMigrations();
}, 'Failed to read migration history from database', '5.1 History query failure throws fail-closed exception');

try {
    $runnerHistoryFail->getAppliedMigrations();
} catch (\Throwable $e) {
    assertTrue(!str_contains($e->getMessage(), 'SuperSecretPassword123!'), '5.2 Credentials in DB error messages are redacted');
    assertTrue(str_contains($e->getMessage(), 'password=******'), '5.3 Sensitive credentials replaced with asterisks');
}

// 5.4 Test table existence query failure fail-closed behavior
$mockPdoCheckFail = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function getAttribute(int $attribute): mixed {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'sqlite';
        return parent::getAttribute($attribute);
    }
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): mixed {
        throw new PDOException("Connection lost during table check (secret=MySecretToken123)");
    }
};
$runnerCheckFail = new MigrationRunner($mockPdoCheckFail, $migrationsDir, 'sqlite');
assertThrows(function () use ($runnerCheckFail) {
    $runnerCheckFail->hasMigrationsTable();
}, 'Database error checking migration history table', '5.4 Table check failure throws fail-closed exception');

// --------------------------------------------------------------------------
// 6. Missing Checksum Column (Legacy Migrations Table)
// --------------------------------------------------------------------------
$legacyPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$legacyPdo->exec("CREATE TABLE `migrations` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);");
$legacyPdo->exec("INSERT INTO `migrations` (`migration`, `applied_at`) VALUES ('001_initial_schema.sql', '2026-01-01 12:00:00');");

$runnerLegacy = new MigrationRunner($legacyPdo, $migrationsDir, 'sqlite');
$appliedLegacy = $runnerLegacy->getAppliedMigrations();
assertTrue(isset($appliedLegacy['001_initial_schema.sql']), '6.1 Legacy migrations table read successfully');
assertTrue($appliedLegacy['001_initial_schema.sql']['checksum'] === null, '6.2 Missing checksum column is represented as null');
assertTrue($appliedLegacy['001_initial_schema.sql']['status'] === 'success', '6.3 Missing status column defaults to success');

// --------------------------------------------------------------------------
// 7. Strict Dry-Run Guarantee with Legacy Migrations Table
// --------------------------------------------------------------------------
$dryRunLegacyPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$dryRunLegacyPdo->exec("CREATE TABLE `migrations` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);");
$dryRunLegacyPdo->exec("INSERT INTO `migrations` (`migration`, `applied_at`) VALUES ('001_initial_schema.sql', '2026-01-01 12:00:00');");

$runnerDryLegacy = new MigrationRunner($dryRunLegacyPdo, $migrationsDir, 'sqlite');
$runnerDryLegacy->setDryRun(true);

$dryResult = $runnerDryLegacy->runSingle('001_initial_schema.sql');
assertTrue($dryResult['status'] === 'skipped', '7.1 Dry-run recognizes applied migration in legacy table');

// Verify table schema was NOT altered during dry run
$stmtCols = $dryRunLegacyPdo->query("PRAGMA table_info('migrations')");
$colNames = [];
while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
    $colNames[] = $col['name'];
}
assertTrue(count($colNames) === 3, '7.2 Legacy migrations table schema was NOT modified during dry-run (still 3 columns)');
assertTrue(!in_array('checksum', $colNames, true), '7.3 No checksum column added during dry-run');

// --------------------------------------------------------------------------
// 8. Strict Dry-Run Guarantee with No Migrations Table
// --------------------------------------------------------------------------
$emptyPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$runnerEmpty = new MigrationRunner($emptyPdo, $migrationsDir, 'sqlite');
$runnerEmpty->setDryRun(true);

$dryNewResult = $runnerEmpty->runSingle('001_initial_schema.sql');
assertTrue($dryNewResult['status'] === 'dry_run_passed', '8.1 Dry-run passed on empty database without migrations table');
assertTrue(isset($dryNewResult['limitations']), '8.2 Dry-run explicitly reports validation limitations');

// Verify migrations table was NOT created during dry run
$stmtTable = $emptyPdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
$hasTable = (bool) ($stmtTable && $stmtTable->fetchColumn());
assertTrue(!$hasTable, '8.3 Migrations table was NOT created during dry-run on empty database');

// --------------------------------------------------------------------------
// 9. Concurrency Lock Timeout / Contention Test
// --------------------------------------------------------------------------
$mockPdoLockFail = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function getAttribute(int $attribute): mixed {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'mysql';
        return parent::getAttribute($attribute);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new class extends PDOStatement {
            public function execute(?array $params = null): bool { return true; }
            public function fetchColumn(int $column = 0): mixed { return 0; } // Lock not acquired
        };
    }
};
$runnerLockFail = new MigrationRunner($mockPdoLockFail, $migrationsDir, 'test_db');
assertThrows(function () use ($runnerLockFail) {
    $runnerLockFail->acquireLock();
}, 'Could not acquire migration lock', '9.1 Lock acquisition failure triggers concurrency exception');

// --------------------------------------------------------------------------
// 10. Failed Multi-Statement DDL Handling & Retry Gating
// --------------------------------------------------------------------------
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rycoop_mig_test_' . bin2hex(random_bytes(6));
mkdir($tempDir, 0777, true);
file_put_contents($tempDir . DIRECTORY_SEPARATOR . '001_failing_migration.sql', "CREATE TABLE sample (id INT); INVALID SQL SYNTAX ERROR;");

$failingPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$runnerFailing = new MigrationRunner($failingPdo, $tempDir, 'sqlite');

// Initial live run should fail
assertThrows(function () use ($runnerFailing) {
    $runnerFailing->runSingle('001_failing_migration.sql');
}, 'Migration `001_failing_migration.sql` failed', '10.1 Failing migration throws execution exception');

// Verify failed status was recorded in migrations table
$appliedAfterFail = $runnerFailing->getAppliedMigrations();
assertTrue(isset($appliedAfterFail['001_failing_migration.sql']), '10.2 Failed migration recorded in migrations table');
assertTrue($appliedAfterFail['001_failing_migration.sql']['status'] === 'failed', '10.3 Failed status is explicitly recorded');
assertTrue(!empty($appliedAfterFail['001_failing_migration.sql']['error_message']), '10.4 Error message is stored in migrations table');

// Automatic retry without explicit confirmation must be BLOCKED
assertThrows(function () use ($runnerFailing) {
    $runnerFailing->runSingle('001_failing_migration.sql');
}, 'automatic retry is BLOCKED', '10.5 Automatic retry of failed migration is strictly BLOCKED');

// Retry with --force-retry-failed allows re-execution
// Fix SQL first to simulate manual reconciliation
file_put_contents($tempDir . DIRECTORY_SEPARATOR . '001_failing_migration.sql', "CREATE TABLE IF NOT EXISTS sample (id INT);");
$runnerFailing->setForceRetryFailed(true);
$retryResult = $runnerFailing->runSingle('001_failing_migration.sql');
assertTrue($retryResult['status'] === 'applied', '10.6 Force retry allows execution after manual reconciliation');

$appliedAfterSuccess = $runnerFailing->getAppliedMigrations();
assertTrue($appliedAfterSuccess['001_failing_migration.sql']['status'] === 'success', '10.7 Status updated to success upon successful retry');
assertTrue($appliedAfterSuccess['001_failing_migration.sql']['error_message'] === null, '10.8 Error message cleared on successful retry');

// Cleanup temp test directory
@unlink($tempDir . DIRECTORY_SEPARATOR . '001_failing_migration.sql');
@rmdir($tempDir);

// --------------------------------------------------------------------------
// 11. Checksum Mismatch Integrity Test
// --------------------------------------------------------------------------
$checksumPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$runnerChecksum = new MigrationRunner($checksumPdo, $migrationsDir, 'sqlite');
$runnerChecksum->ensureMigrationTable();

// Manually insert 001 with altered checksum
$tamperedChecksum = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$checksumPdo->prepare("INSERT INTO `migrations` (`migration`, `checksum`, `status`, `applied_at`) VALUES (?, ?, 'success', CURRENT_TIMESTAMP)")
             ->execute(['001_initial_schema.sql', $tamperedChecksum]);

assertThrows(function () use ($runnerChecksum) {
    $runnerChecksum->runSingle('001_initial_schema.sql');
}, 'Integrity error: Migration `001_initial_schema.sql` was already applied', '11.1 Checksum mismatch triggers integrity exception');

// --------------------------------------------------------------------------
// 12. Duplicate Migration Idempotency Test
// --------------------------------------------------------------------------
$dupPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$runnerDup = new MigrationRunner($dupPdo, $migrationsDir, 'sqlite');
$runnerDup->ensureMigrationTable();

$actualSql = (string) file_get_contents($migrationsDir . '/001_initial_schema.sql');
$actualChecksum = hash('sha256', $actualSql);
$dupPdo->prepare("INSERT INTO `migrations` (`migration`, `checksum`, `status`, `applied_at`) VALUES (?, ?, 'success', CURRENT_TIMESTAMP)")
       ->execute(['001_initial_schema.sql', $actualChecksum]);

$dupResult = $runnerDup->runSingle('001_initial_schema.sql');
assertTrue($dupResult['status'] === 'skipped', '12.1 Previously applied migration is skipped idempotently');

echo "=========================================================================\n";
echo "All MigrationRunner Unit, Safety & Security tests PASSED successfully!\n";

