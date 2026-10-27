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

// --------------------------------------------------------------------------
// 13. Database Isolation, Config Validator & Lab Isolation Tests
// --------------------------------------------------------------------------
require_once __DIR__ . '/MigrationLabTestHarness.php';
use App\Services\MigrationConfigValidator;
use App\Services\MigrationConnectionFactory;
use Tests\MigrationLabTestHarness;

// 13.1 Missing host
assertThrows(function () {
    MigrationConfigValidator::validate(['host' => '']);
}, 'DB_HOST is required', '13.1 Missing DB_HOST is rejected before opening connection');

// 13.2 Missing database port
assertThrows(function () {
    MigrationConfigValidator::validate(['host' => '127.0.0.1', 'port' => '', 'database' => 'test_db']);
}, 'DB_PORT is required', '13.2 Missing DB_PORT is rejected');

assertThrows(function () {
    MigrationConfigValidator::validate(['host' => '127.0.0.1', 'port' => 99999, 'database' => 'test_db']);
}, 'Port must be between 1 and 65535', '13.3 Out of range DB_PORT is rejected');

// 13.4 Missing database name
assertThrows(function () {
    MigrationConfigValidator::validate(['host' => '127.0.0.1', 'port' => 3306, 'database' => '']);
}, 'DB_DATABASE is required', '13.4 Missing DB_DATABASE is rejected');

// 13.5 System database rejection
assertThrows(function () {
    MigrationConfigValidator::validate(['host' => '127.0.0.1', 'port' => 3306, 'database' => 'mysql', 'username' => 'migrator', 'password' => 'pass']);
}, 'Cannot target system database', '13.5 System database target is rejected');

// 13.6 Default root username rejection
assertThrows(function () {
    MigrationConfigValidator::validate([
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'my_custom_db',
        'username' => 'root',
        'password' => 'secret123',
    ]);
}, "Executing migrations as 'root' is strictly prohibited", '13.6 Default root username is strictly rejected');

// 13.7 Silent default rayongcoop_db rejection
assertThrows(function () {
    MigrationConfigValidator::validate([
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rayongcoop_db',
        'username' => 'rycoop_migrator',
        'password' => 'secret123',
    ]);
}, 'Default database `rayongcoop_db` cannot be used silently', '13.7 Silent default database rayongcoop_db is rejected');

// 13.8 Valid explicit configuration passes
$validConfig = MigrationConfigValidator::validate([
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'rayongcoop_db',
    'explicit_database' => true,
    'username' => 'rycoop_migrator',
    'password' => 'secret123',
]);
assertTrue($validConfig['database'] === 'rayongcoop_db', '13.8 Explicitly confirmed database configuration passes');

// 13.9 Lab Database Isolation Tests
assertThrows(function () {
    MigrationConfigValidator::validate([
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rycoopweb_test',
        'is_lab' => true,
        'username' => 'rycoop_lab',
        'password' => 'lab_pass',
    ]);
}, 'Lab migrations must strictly target `rycoopweb_migration_lab`', '13.9 Lab configuration targeting rycoopweb_test is rejected');

assertThrows(function () {
    MigrationConfigValidator::validate([
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'rycoopweb_migration_lab',
        'is_lab' => true,
        'username' => 'rycoop_app',
        'password' => 'lab_pass',
    ]);
}, 'Reusing application/root credentials `rycoop_app` in Lab environment is prohibited', '13.10 Reusing application credentials in Lab is rejected');

// 13.11 Lab privilege cross-database leakage rejection
$mockLabPdoWithLeak = MigrationLabTestHarness::createMockLabPdo(
    'rycoopweb_migration_lab',
    'rycoop_lab',
    [
        "GRANT ALL PRIVILEGES ON `rycoopweb_migration_lab`.* TO 'rycoop_lab'@'localhost'",
        "GRANT SELECT ON `rycoopweb_test`.* TO 'rycoop_lab'@'localhost'",
    ]
);
assertThrows(function () use ($mockLabPdoWithLeak) {
    MigrationConnectionFactory::validatePrivileges($mockLabPdoWithLeak, 'rycoopweb_migration_lab', 'rycoop_lab', true);
}, 'Lab privilege violation: Migration user `rycoop_lab` has unauthorized grant on protected database `rycoopweb_test`', '13.11 Lab user grant on protected database is rejected');

// 13.12 Connection failure credential redaction
try {
    // Attempt connecting to invalid host to trigger PDOException
    $badConfig = [
        'host' => 'nonexistent.invalid.host.local',
        'port' => 3306,
        'database' => 'rycoopweb_migration_lab',
        'is_lab' => true,
        'username' => 'rycoop_lab',
        'password' => 'SuperSecretP@ssword999!',
    ];
    MigrationConnectionFactory::createConnection($badConfig);
    echo "FAIL: Expected connection failure, but succeeded\n";
    exit(1);
} catch (\Throwable $e) {
    assertTrue(!str_contains($e->getMessage(), 'SuperSecretP@ssword999!'), '13.12 Passwords in connection errors are redacted');
    assertTrue(str_contains($e->getMessage(), 'Failed to establish dedicated migration connection'), '13.13 Controlled connection failure exception');
}

// 13.14 Preflight info diagnostic verification (No secrets exposed)
$mockLabPdoClean = MigrationLabTestHarness::createMockLabPdo('rycoopweb_migration_lab', 'rycoop_lab');
$runnerLab = new MigrationRunner($mockLabPdoClean, $migrationsDir, 'rycoopweb_migration_lab');
$preflight = $runnerLab->getPreflightInfo('001_initial_schema.sql');
assertTrue($preflight['active_database'] === 'rycoopweb_migration_lab', '13.14 Preflight identifies active database');
assertTrue($preflight['database_identity_ok'] === true, '13.15 Preflight validates database identity match');
assertTrue(!isset($preflight['password']), '13.16 Preflight never exposes passwords or secrets');

// 13.17 MySQL mode requires explicit database name on injected PDO
assertThrows(function () use ($mockLabPdoClean, $migrationsDir) {
    new MigrationRunner($mockLabPdoClean, $migrationsDir, '');
}, 'Security violation: MigrationRunner requires an explicit expected database name', '13.17 Empty expected database with MySQL PDO is rejected');

// --------------------------------------------------------------------------
// 14. TOCTOU Race Condition & In-Lock Authoritative History Check
// --------------------------------------------------------------------------
$sharedDbFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rycoop_shared_toctou_' . bin2hex(random_bytes(6)) . '.sqlite';
$sharedPdoA = new PDO('sqlite:' . $sharedDbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$execCount = 0;
$spyPdoB = new class($sharedDbFile, $execCount) extends PDO {
    private int $countRef;
    public function __construct(string $file, int &$count) {
        parent::__construct('sqlite:' . $file, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->countRef = &$count;
    }
    public function exec(string $statement): int|false {
        if (str_contains($statement, 'CREATE TABLE toctou_users')) {
            $this->countRef++;
        }
        return parent::exec($statement);
    }
};

$toctouTempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rycoop_toctou_dir_' . bin2hex(random_bytes(6));
mkdir($toctouTempDir, 0777, true);
file_put_contents($toctouTempDir . DIRECTORY_SEPARATOR . '001_toctou_test.sql', "CREATE TABLE toctou_users (id INT PRIMARY KEY, name VARCHAR(50));");

$runnerA = new MigrationRunner($sharedPdoA, $toctouTempDir, 'sqlite');
$runnerB = new MigrationRunner($spyPdoB, $toctouTempDir, 'sqlite');

// Step 1: Runner B inspects migration before lock (TOCTOU "Time-of-Check")
$preflightB = $runnerB->getPreflightInfo('001_toctou_test.sql');
assertTrue($preflightB['is_already_applied'] === false, '14.1 Runner B preflight sees migration as unapplied before lock');

// Step 2: Runner A acquires lock and runs migration first (Runner A wins race)
$resultA = $runnerA->runSingle('001_toctou_test.sql');
assertTrue($resultA['status'] === 'applied', '14.2 Runner A applies migration successfully');

// Step 3: Runner B executes runSingle('001_toctou_test.sql') ("Time-of-Use")
// Under the fix, Runner B acquires lock, rechecks migration history UNDER LOCK, sees it was applied, and SKIPS it!
$resultB = $runnerB->runSingle('001_toctou_test.sql');
assertTrue($resultB['status'] === 'skipped', '14.3 Runner B detects in-lock applied status and cleanly skips (TOCTOU resolved)');
assertTrue($execCount === 0, '14.4 Runner B never executed migration SQL DDL after acquiring lock');

// 14.5 EnsureMigrationTable Concurrency Protection test
$mockMetadataPdo = new class extends PDO {
    public int $getLockCalls = 0;
    public int $releaseLockCalls = 0;
    public function __construct() { parent::__construct('sqlite::memory:'); }
    public function getAttribute(int $attribute): mixed {
        if ($attribute === PDO::ATTR_DRIVER_NAME) return 'mysql';
        return parent::getAttribute($attribute);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $parent = $this;
        if (str_contains($query, 'GET_LOCK')) {
            return new class($parent) extends PDOStatement {
                private $p;
                public function __construct($p) { $this->p = $p; }
                public function execute(?array $params = null): bool {
                    $this->p->getLockCalls++;
                    return true;
                }
                public function fetchColumn(int $column = 0): mixed { return 1; }
            };
        }
        if (str_contains($query, 'RELEASE_LOCK')) {
            return new class($parent) extends PDOStatement {
                private $p;
                public function __construct($p) { $this->p = $p; }
                public function execute(?array $params = null): bool {
                    $this->p->releaseLockCalls++;
                    return true;
                }
            };
        }
        return parent::prepare($query, $options);
    }
    public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): mixed {
        if (str_contains($query, 'SHOW COLUMNS')) {
            return new class {
                public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
                    return false;
                }
            };
        }
        return parent::query($query);
    }
    public function exec(string $statement): int|false {
        return 1;
    }
};

$runnerMeta = new MigrationRunner($mockMetadataPdo, $migrationsDir, 'test_db');
$runnerMeta->ensureMigrationTable();
assertTrue($mockMetadataPdo->getLockCalls === 1, '14.5 ensureMigrationTable standalone acquires advisory lock');
assertTrue($mockMetadataPdo->releaseLockCalls === 1, '14.6 ensureMigrationTable standalone releases advisory lock in finally');

// Cleanup TOCTOU temp files
@unlink($toctouTempDir . DIRECTORY_SEPARATOR . '001_toctou_test.sql');
@rmdir($toctouTempDir);
@unlink($sharedDbFile);

// --------------------------------------------------------------------------
// 15. Failure Recording Safety & Critical Incident Marker
// --------------------------------------------------------------------------
$incidentDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rycoop_incident_' . bin2hex(random_bytes(6));
mkdir($incidentDir, 0777, true);
$incidentFile = $incidentDir . DIRECTORY_SEPARATOR . 'incident_marker.json';
file_put_contents($incidentDir . DIRECTORY_SEPARATOR . '001_broken_incident.sql', "CREATE TABLE bad (id INT); INVALID SYNTAX;");

// Mock PDO where migration exec fails AND subsequent history recording INSERT fails!
$mockIncidentPdo = new class extends PDO {
    public function __construct() { parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public function exec(string $statement): int|false {
        if (str_contains($statement, 'CREATE TABLE bad')) {
            throw new PDOException("SQLSTATE[42000]: Syntax error in migration SQL");
        }
        return parent::exec($statement);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_contains($query, 'INSERT INTO `migrations`') || str_contains($query, 'UPDATE `migrations`')) {
            throw new PDOException("SQLSTATE[HY000]: Database disk is full or table locked - cannot record failure");
        }
        return parent::prepare($query, $options);
    }
};

$runnerIncident = new MigrationRunner($mockIncidentPdo, $incidentDir, 'sqlite');
$runnerIncident->setIncidentMarkerPath($incidentFile);

// 15.1 Verify that dual failure raises MigrationIncidentException (fail-closed)
assertThrows(function () use ($runnerIncident) {
    $runnerIncident->runSingle('001_broken_incident.sql');
}, 'CRITICAL MIGRATION INCIDENT', '15.1 Failure in both SQL and failure-recording raises MigrationIncidentException');

// 15.2 Verify durable incident marker was written to disk
assertTrue(file_exists($incidentFile), '15.2 Durable incident marker file was created on disk');
$markerContent = json_decode((string) file_get_contents($incidentFile), true);
assertTrue(is_array($markerContent), '15.3 Incident marker contains valid JSON');
assertTrue($markerContent['migration'] === '001_broken_incident.sql', '15.4 Incident marker records correct migration name');
assertTrue($markerContent['severity'] === 'CRITICAL', '15.5 Incident marker records CRITICAL severity');
assertTrue(str_contains($markerContent['recording_error'], 'Database disk is full'), '15.6 Recording error is captured in incident marker');

// 15.7 Subsequent executions are BLOCKED by active incident
assertThrows(function () use ($runnerIncident) {
    $runnerIncident->runSingle('001_broken_incident.sql');
}, 'CRITICAL MIGRATION INCIDENT ACTIVE', '15.7 Subsequent migration execution is strictly blocked while incident marker is active');

// 15.8 Preflight identifies active incident
$incidentPreflight = $runnerIncident->getPreflightInfo('001_broken_incident.sql');
assertTrue($incidentPreflight['has_active_incident'] === true, '15.8 Preflight detects active incident state');

// 15.9 Force retry of reconciled migration clears incident marker
$mockReconciledPdo = new class extends PDO {
    public function __construct() {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->exec("CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `checksum` CHAR(64) NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `execution_time_ms` INTEGER NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'success',
            `error_message` TEXT NULL
        );");
    }
};

file_put_contents($incidentDir . DIRECTORY_SEPARATOR . '001_broken_incident.sql', "CREATE TABLE IF NOT EXISTS reconciled (id INT);");
$runnerReconciled = new MigrationRunner($mockReconciledPdo, $incidentDir, 'sqlite');
$runnerReconciled->setIncidentMarkerPath($incidentFile);
$runnerReconciled->setForceRetryFailed(true);

$reconciledResult = $runnerReconciled->runSingle('001_broken_incident.sql');
assertTrue($reconciledResult['status'] === 'applied', '15.9 Reconciled migration succeeds with --force-retry-failed');
assertTrue(!file_exists($incidentFile), '15.10 Incident marker file automatically cleared after successful reconciliation');

// Cleanup incident test temp files
@unlink($incidentDir . DIRECTORY_SEPARATOR . '001_broken_incident.sql');
@unlink($incidentFile);
@rmdir($incidentDir);

echo "=========================================================================\n";
echo "All MigrationRunner Unit, Safety & Security tests PASSED successfully!\n";


