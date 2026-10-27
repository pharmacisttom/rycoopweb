<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Hardened Database Migration Engine for RYCOOPWEB.
 *
 * Provides:
 * - Strict database targeting verification (SELECT DATABASE()).
 * - Concurrency locking via MySQL GET_LOCK().
 * - SHA-256 migration file checksum verification.
 * - Path traversal prevention & allowlisted directory enforcement.
 * - Idempotency safeguards.
 * - Preflight / Dry-run execution mode.
 * - Destructive and financial migration confirmation safeguards.
 * - Execution diagnostics and detailed logging.
 */
class MigrationRunner
{
    public const LOCK_NAME = 'rycoopweb_migration_lock';
    public const LOCK_TIMEOUT_SECONDS = 10;

    private PDO $pdo;
    private string $migrationsDir;
    private string $expectedDatabase;
    private bool $dryRun = false;
    private bool $confirmFinancial = false;
    private bool $confirmDestructive = false;
    private bool $forceRetryFailed = false;
    private array $logs = [];

    public function __construct(
        ?PDO $pdo = null,
        ?string $migrationsDir = null,
        ?string $expectedDatabase = null
    ) {
        $this->migrationsDir = realpath($migrationsDir ?? (__DIR__ . '/../../database/migrations')) ?: '';
        if ($this->migrationsDir === '' || !is_dir($this->migrationsDir)) {
            throw new RuntimeException("Invalid migrations directory: {$migrationsDir}");
        }

        if ($pdo !== null) {
            $this->pdo = $pdo;
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->expectedDatabase = $expectedDatabase ?? 'sqlite';
            } elseif ($expectedDatabase !== null && $expectedDatabase !== '') {
                $this->expectedDatabase = $expectedDatabase;
            } else {
                throw new InvalidArgumentException(
                    "Security violation: MigrationRunner requires an explicit expected database name when injecting a PDO instance."
                );
            }
        } else {
            $conn = MigrationConnectionFactory::createConnection();
            $this->pdo = $conn['pdo'];
            $this->expectedDatabase = $expectedDatabase ?? $conn['database'];
        }
    }

    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function setConfirmFinancial(bool $confirm): self
    {
        $this->confirmFinancial = $confirm;
        return $this;
    }

    public function setConfirmDestructive(bool $confirm): self
    {
        $this->confirmDestructive = $confirm;
        return $this;
    }

    public function setForceRetryFailed(bool $force): self
    {
        $this->forceRetryFailed = $force;
        return $this;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Sanitize sensitive connection parameters, passwords, and tokens from error messages.
     */
    public static function sanitizeErrorMessage(string $message): string
    {
        $sanitized = preg_replace('/(password|pwd|pass|secret)=[^; &]+/i', '$1=******', $message);
        $sanitized = preg_replace('/(:)[^\/:@]+(@)/i', '$1******$2', (string) $sanitized);
        return (string) $sanitized;
    }

    /**
     * Read-only inspection to check if migrations tracking table exists.
     */
    public function hasMigrationsTable(): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'");
                return (bool) ($stmt && $stmt->fetchColumn());
            }

            $stmt = $this->pdo->query("SHOW TABLES LIKE 'migrations'");
            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable $e) {
            $sanitized = self::sanitizeErrorMessage($e->getMessage());
            throw new RuntimeException("Database error checking migration history table: {$sanitized}", (int) $e->getCode(), $e);
        }
    }

    /**
     * Read-only inspection of columns in the migrations table.
     *
     * @return array<string, bool> Map of column names
     */
    public function getMigrationTableColumns(): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $cols = [];
        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query("PRAGMA table_info('migrations')");
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $cols[$row['name']] = true;
                    }
                }
                return $cols;
            }

            $stmt = $this->pdo->query("SHOW COLUMNS FROM `migrations`");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[$row['Field']] = true;
                }
            }
            return $cols;
        } catch (\Throwable $e) {
            $sanitized = self::sanitizeErrorMessage($e->getMessage());
            throw new RuntimeException("Database error inspecting migration history schema: {$sanitized}", (int) $e->getCode(), $e);
        }
    }

    /**
     * Verify database target against currently active connection.
     */
    public function verifyDatabaseTarget(): string
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $currentDb = 'sqlite';
        } else {
            $stmt = $this->pdo->query('SELECT DATABASE()');
            $currentDb = (string) ($stmt ? $stmt->fetchColumn() : '');
        }

        if ($currentDb === '') {
            throw new RuntimeException('Safety violation: No active database is selected on this connection.');
        }

        // Prohibit running on MySQL system databases
        $systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];
        if (in_array(strtolower($currentDb), $systemDbs, true)) {
            throw new RuntimeException("Safety violation: Refusing to execute migrations against system database `{$currentDb}`.");
        }

        if ($this->expectedDatabase !== '' && strtolower($currentDb) !== strtolower($this->expectedDatabase)) {
            throw new RuntimeException(
                "Database target mismatch! Active database is `{$currentDb}`, but expected database is `{$this->expectedDatabase}`."
            );
        }

        $this->log("✓ Verified database target: `{$currentDb}`");
        return $currentDb;
    }

    /**
     * Acquire concurrency lock.
     */
    public function acquireLock(): bool
    {
        if ($this->dryRun) {
            return true;
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return true;
        }

        $stmt = $this->pdo->prepare('SELECT GET_LOCK(?, ?)');
        $stmt->execute([self::LOCK_NAME, self::LOCK_TIMEOUT_SECONDS]);
        $acquired = (int) $stmt->fetchColumn() === 1;

        if (!$acquired) {
            throw new RuntimeException(
                "Could not acquire migration lock `" . self::LOCK_NAME . "` within " . self::LOCK_TIMEOUT_SECONDS . "s. Another migration process may be running."
            );
        }

        $this->log("✓ Acquired migration lock `" . self::LOCK_NAME . "`");
        return true;
    }

    /**
     * Release concurrency lock.
     */
    public function releaseLock(): void
    {
        if ($this->dryRun) {
            return;
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->execute([self::LOCK_NAME]);
            $this->log("✓ Released migration lock `" . self::LOCK_NAME . "`");
        } catch (\Throwable $e) {
            // Ignore lock release failures on shutdown
        }
    }

    /**
     * Ensure the migrations tracking table exists and has all audit columns.
     * In dry-run mode, this method strictly performs ZERO schema modifications.
     */
    public function ensureMigrationTable(): void
    {
        if ($this->dryRun) {
            return;
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `checksum` CHAR(64) NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `execution_time_ms` INTEGER NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'success',
                `error_message` TEXT NULL
            );");
            return;
        }

        // 1. Base table compatible with legacy schema (id, migration, applied_at)
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Extend with additive audit columns
        $columns = $this->getMigrationTableColumns();

        if (!isset($columns['checksum'])) {
            $this->pdo->exec("ALTER TABLE `migrations` ADD COLUMN `checksum` CHAR(64) NULL AFTER `migration`");
        }
        if (!isset($columns['execution_time_ms'])) {
            $this->pdo->exec("ALTER TABLE `migrations` ADD COLUMN `execution_time_ms` INT UNSIGNED NULL AFTER `applied_at`");
        }
        if (!isset($columns['status'])) {
            $this->pdo->exec("ALTER TABLE `migrations` ADD COLUMN `status` ENUM('success', 'failed') NOT NULL DEFAULT 'success' AFTER `execution_time_ms`");
        }
        if (!isset($columns['error_message'])) {
            $this->pdo->exec("ALTER TABLE `migrations` ADD COLUMN `error_message` TEXT NULL AFTER `status`");
        }
    }

    /**
     * Get list of applied migrations with metadata.
     * Fail-closed: database query failures throw a controlled RuntimeException,
     * preventing errors from being silently interpreted as an empty migration history.
     *
     * @return array<string, array{id: int, migration: string, checksum: ?string, applied_at: string, status: string, error_message: ?string}>
     */
    public function getAppliedMigrations(): array
    {
        if (!$this->hasMigrationsTable()) {
            return [];
        }

        $cols = $this->getMigrationTableColumns();
        $hasChecksum = isset($cols['checksum']);
        $hasStatus = isset($cols['status']);
        $hasErrorMsg = isset($cols['error_message']);

        $selectFields = ['id', 'migration', 'applied_at'];
        if ($hasChecksum) {
            $selectFields[] = 'checksum';
        }
        if ($hasStatus) {
            $selectFields[] = 'status';
        }
        if ($hasErrorMsg) {
            $selectFields[] = 'error_message';
        }

        $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM `migrations` ORDER BY id ASC';

        try {
            $stmt = $this->pdo->query($sql);
            if (!$stmt) {
                throw new RuntimeException("Database returned empty statement when reading migration history.");
            }
            $applied = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $applied[$row['migration']] = [
                    'id' => (int) $row['id'],
                    'migration' => (string) $row['migration'],
                    'applied_at' => (string) $row['applied_at'],
                    'checksum' => $hasChecksum && isset($row['checksum']) ? (string) $row['checksum'] : null,
                    'status' => $hasStatus && isset($row['status']) ? (string) $row['status'] : 'success',
                    'error_message' => $hasErrorMsg && isset($row['error_message']) ? (string) $row['error_message'] : null,
                ];
            }
            return $applied;
        } catch (\Throwable $e) {
            $sanitized = self::sanitizeErrorMessage($e->getMessage());
            throw new RuntimeException("Failed to read migration history from database: {$sanitized}", (int) $e->getCode(), $e);
        }
    }

    /**
     * Validate and resolve a migration file path strictly within the allowlisted directory.
     */
    public function resolveMigrationFile(string $filename): string
    {
        $cleanName = basename($filename);
        if ($cleanName !== $filename || !preg_match('/^[0-9]{3}[a-z0-9_]*\.sql$/i', $cleanName)) {
            throw new InvalidArgumentException("Invalid migration filename format: `{$filename}`. Must match `000_name.sql` pattern.");
        }

        $fullPath = $this->migrationsDir . DIRECTORY_SEPARATOR . $cleanName;
        $realPath = realpath($fullPath);

        if ($realPath === false || !file_exists($realPath)) {
            throw new RuntimeException("Migration file does not exist: `{$cleanName}`");
        }

        // Verify path traversal constraint: must strictly reside inside migrations directory
        if (!str_starts_with($realPath, $this->migrationsDir)) {
            throw new RuntimeException("Path traversal attempt detected: `{$filename}` is outside allowed directory.");
        }

        return $realPath;
    }

    /**
     * Analyze SQL content for destructive or financial operations.
     */
    public function analyzeSql(string $sql): array
    {
        $analysis = [
            'is_destructive' => false,
            'destructive_reasons' => [],
            'is_financial' => false,
            'financial_reasons' => [],
            'limitations' => 'Preflight dry-run inspects safety boundaries, destructive operations, and financial tables via read-only analysis. Full MySQL DDL syntax validation, foreign key constraints, and multi-statement runtime compatibility can only be verified upon actual execution.',
        ];

        // Destructive patterns: DROP TABLE, DROP DATABASE, TRUNCATE, ALTER TABLE ... DROP COLUMN
        if (preg_match('/\bDROP\s+(TABLE|DATABASE)\b/i', $sql, $m)) {
            $analysis['is_destructive'] = true;
            $analysis['destructive_reasons'][] = "Contains `{$m[0]}`";
        }
        if (preg_match('/\bTRUNCATE\s+(TABLE\s+)?([`\w]+)/i', $sql, $m)) {
            $analysis['is_destructive'] = true;
            $analysis['destructive_reasons'][] = "Contains `TRUNCATE TABLE {$m[2]}`";
        }
        if (preg_match('/\bALTER\s+TABLE\s+[`\w]+\s+DROP\s+(COLUMN\s+)?[`\w]+/i', $sql, $m)) {
            $analysis['is_destructive'] = true;
            $analysis['destructive_reasons'][] = "Contains `{$m[0]}`";
        }

        // Financial patterns: Direct updates to interest_rates, deposit_products, loan_products, member_dividends
        if (preg_match('/\b(UPDATE|DELETE|INSERT\s+INTO)\s+[`\']?(interest_rates|deposit_products|loan_products|member_dividends)[`\']?/i', $sql, $m)) {
            $analysis['is_financial'] = true;
            $analysis['financial_reasons'][] = "Mutates financial table `{$m[2]}` via {$m[1]}";
        }

        return $analysis;
    }

    /**
     * Perform comprehensive preflight security inspection before executing a migration.
     *
     * @return array{
     *   migration: string,
     *   checksum: string,
     *   active_database: string,
     *   expected_database: string,
     *   database_identity_ok: bool,
     *   history_accessible: bool,
     *   is_already_applied: bool,
     *   analysis: array,
     *   limitations: string
     * }
     */
    public function getPreflightInfo(string $filename): array
    {
        $realPath = $this->resolveMigrationFile($filename);
        $basename = basename($realPath);
        $activeDb = $this->verifyDatabaseTarget();

        $sql = (string) file_get_contents($realPath);
        $checksum = hash('sha256', $sql);
        $analysis = $this->analyzeSql($sql);

        // Fail-closed history read
        $applied = $this->getAppliedMigrations();
        $isAlreadyApplied = isset($applied[$basename]);

        return [
            'migration' => $basename,
            'checksum' => $checksum,
            'active_database' => $activeDb,
            'expected_database' => $this->expectedDatabase,
            'database_identity_ok' => strtolower($activeDb) === strtolower($this->expectedDatabase),
            'history_accessible' => true,
            'is_already_applied' => $isAlreadyApplied,
            'analysis' => $analysis,
            'limitations' => $analysis['limitations'],
        ];
    }

    /**
     * Run a single migration file safely.
     */
    public function runSingle(string $filename): array
    {
        $realPath = $this->resolveMigrationFile($filename);
        $basename = basename($realPath);

        $this->verifyDatabaseTarget();
        $this->ensureMigrationTable();

        $sql = (string) file_get_contents($realPath);
        $checksum = hash('sha256', $sql);
        $analysis = $this->analyzeSql($sql);

        // Check if already applied or failed
        $applied = $this->getAppliedMigrations();
        if (isset($applied[$basename])) {
            $existing = $applied[$basename];
            $status = $existing['status'] ?? 'success';

            if ($status === 'failed') {
                if (!$this->forceRetryFailed) {
                    $prevError = $existing['error_message'] ?? 'Unknown error';
                    throw new RuntimeException(
                        "Migration `{$basename}` previously failed or was partially applied on {$existing['applied_at']}! " .
                        "Due to MySQL implicit DDL commits, automatic retry is BLOCKED to prevent schema corruption. " .
                        "Manual schema inspection, reconciliation, and explicit approval (--force-retry-failed) are required. " .
                        "Previous error: {$prevError}"
                    );
                }
                $this->log("⚠ Warning: Forcing retry of previously failed migration `{$basename}` as requested (--force-retry-failed).");
            } else {
                if (!empty($existing['checksum']) && $existing['checksum'] !== $checksum) {
                    throw new RuntimeException(
                        "Integrity error: Migration `{$basename}` was already applied on {$existing['applied_at']}, but its checksum has changed! " .
                        "Recorded: {$existing['checksum']}, Current: {$checksum}."
                    );
                }
                $this->log("⏭ Migration `{$basename}` is already applied. Skipping.");
                return [
                    'migration' => $basename,
                    'status' => 'skipped',
                    'checksum' => $checksum,
                    'execution_time_ms' => 0,
                ];
            }
        }

        // Check confirmation gates
        if ($analysis['is_destructive'] && !$this->confirmDestructive) {
            $reasons = implode(', ', $analysis['destructive_reasons']);
            throw new RuntimeException(
                "Destructive migration safety gate triggered for `{$basename}`: [{$reasons}]. " .
                "Explicit confirmation (--confirm-destructive) is required."
            );
        }

        if ($analysis['is_financial'] && !$this->confirmFinancial) {
            $reasons = implode(', ', $analysis['financial_reasons']);
            throw new RuntimeException(
                "Financial migration safety gate triggered for `{$basename}`: [{$reasons}]. " .
                "Explicit financial confirmation (--confirm-financial) is required."
            );
        }

        if ($this->dryRun) {
            $this->log("🔍 [DRY-RUN] Preflight OK for `{$basename}` (Checksum: " . substr($checksum, 0, 12) . "...)");
            $this->log("ℹ [DRY-RUN] Note: Dry-run does not execute DDL/DML. Runtime MySQL constraints and multi-statement syntax can only be verified on execution.");
            return [
                'migration' => $basename,
                'status' => 'dry_run_passed',
                'checksum' => $checksum,
                'analysis' => $analysis,
                'limitations' => $analysis['limitations'],
                'execution_time_ms' => 0,
            ];
        }

        // Execute live migration
        $this->acquireLock();
        $startTime = microtime(true);

        try {
            // Note: MySQL DDL causes implicit commits, so full DDL rollback cannot be guaranteed.
            $this->pdo->exec($sql);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            // Record execution in migrations table (update if retrying a failed attempt, else insert)
            $stmt = $this->pdo->prepare("SELECT id FROM `migrations` WHERE `migration` = ?");
            $stmt->execute([$basename]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $updateStmt = $this->pdo->prepare(
                    "UPDATE `migrations` 
                     SET `checksum` = ?, `execution_time_ms` = ?, `status` = 'success', `error_message` = NULL, `applied_at` = CURRENT_TIMESTAMP 
                     WHERE `id` = ?"
                );
                $updateStmt->execute([$checksum, $durationMs, $existingId]);
            } else {
                $insertStmt = $this->pdo->prepare(
                    "INSERT INTO `migrations` (`migration`, `checksum`, `execution_time_ms`, `status`, `applied_at`) 
                     VALUES (?, ?, ?, 'success', CURRENT_TIMESTAMP)"
                );
                $insertStmt->execute([$basename, $checksum, $durationMs]);
            }

            $this->log("✓ Successfully applied `{$basename}` in {$durationMs}ms (Checksum: " . substr($checksum, 0, 12) . "...)");
            Logger::info("Database migration `{$basename}` applied successfully in {$durationMs}ms.");

            return [
                'migration' => $basename,
                'status' => 'applied',
                'checksum' => $checksum,
                'execution_time_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $sanitizedErr = self::sanitizeErrorMessage($e->getMessage());

            $this->log("✗ Error applying `{$basename}`: " . $sanitizedErr);
            Logger::error("Migration `{$basename}` failed after {$durationMs}ms: " . $sanitizedErr);

            // Record partial DDL / failure state in migrations table so subsequent runs cannot silently skip or blindly retry
            try {
                $stmt = $this->pdo->prepare("SELECT id FROM `migrations` WHERE `migration` = ?");
                $stmt->execute([$basename]);
                $existingId = $stmt->fetchColumn();

                if ($existingId) {
                    $updateStmt = $this->pdo->prepare(
                        "UPDATE `migrations` 
                         SET `checksum` = ?, `execution_time_ms` = ?, `status` = 'failed', `error_message` = ?, `applied_at` = CURRENT_TIMESTAMP 
                         WHERE `id` = ?"
                    );
                    $updateStmt->execute([$checksum, $durationMs, $sanitizedErr, $existingId]);
                } else {
                    $insertStmt = $this->pdo->prepare(
                        "INSERT INTO `migrations` (`migration`, `checksum`, `execution_time_ms`, `status`, `applied_at`, `error_message`) 
                         VALUES (?, ?, ?, 'failed', CURRENT_TIMESTAMP, ?)"
                    );
                    $insertStmt->execute([$basename, $checksum, $durationMs, $sanitizedErr]);
                }
            } catch (\Throwable $recErr) {
                Logger::error("Could not record failure state in migrations table: " . self::sanitizeErrorMessage($recErr->getMessage()));
            }

            throw new RuntimeException("Migration `{$basename}` failed: " . $sanitizedErr, (int) $e->getCode(), $e);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Run all pending migrations in order.
     */
    public function runAll(): array
    {
        $this->verifyDatabaseTarget();
        $this->ensureMigrationTable();

        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);

        $applied = $this->getAppliedMigrations();
        $results = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if (isset($applied[$basename]) && ($applied[$basename]['status'] ?? 'success') === 'success') {
                continue;
            }
            $results[] = $this->runSingle($basename);
        }

        return $results;
    }

    private function log(string $msg): void
    {
        $this->logs[] = $msg;
    }
}
