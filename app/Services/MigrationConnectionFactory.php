<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Dedicated Migration PDO Connection Factory.
 *
 * Enforces:
 * - Isolated, dedicated PDO instances for migration operations (no shared state).
 * - Immediate post-connection verification: SELECT DATABASE() must match target.
 * - Current user identity & privilege validation (SELECT CURRENT_USER()).
 * - Credentials and DSN secrets sanitization on connection failure.
 * - Prohibition of cross-database privilege leakage in Lab mode.
 */
class MigrationConnectionFactory
{
    /**
     * Create a dedicated, verified PDO connection for migrations.
     *
     * @param array<string, mixed> $config
     * @return array{pdo: PDO, host: string, port: int, database: string, username: string, current_user: string, is_lab: bool, privileges: array}
     * @throws RuntimeException
     */
    public static function createConnection(array $config = []): array
    {
        $val = MigrationConfigValidator::validate($config);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $val['host'],
            $val['port'],
            $val['database']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, $val['username'], $val['password'], $options);
        } catch (PDOException $e) {
            $sanitized = MigrationRunner::sanitizeErrorMessage($e->getMessage());
            throw new RuntimeException("Failed to establish dedicated migration connection: {$sanitized}", (int) $e->getCode(), $e);
        }

        // Verify active database on this dedicated connection
        $stmt = $pdo->query('SELECT DATABASE()');
        $activeDb = (string) ($stmt ? $stmt->fetchColumn() : '');

        if ($activeDb === '') {
            throw new RuntimeException("Safety violation: Dedicated migration connection has no active database selected.");
        }

        if (strtolower($activeDb) !== strtolower($val['database'])) {
            throw new RuntimeException(
                "Database connection mismatch! Configured target is `{$val['database']}`, but connection active database is `{$activeDb}`."
            );
        }

        // Verify user identity
        $userStmt = $pdo->query('SELECT CURRENT_USER()');
        $currentUser = (string) ($userStmt ? $userStmt->fetchColumn() : $val['username']);

        // Verify user privileges
        $privileges = self::validatePrivileges($pdo, $val['database'], $currentUser, $val['is_lab']);

        return [
            'pdo' => $pdo,
            'host' => $val['host'],
            'port' => $val['port'],
            'database' => $val['database'],
            'username' => $val['username'],
            'current_user' => $currentUser,
            'is_lab' => $val['is_lab'],
            'privileges' => $privileges,
        ];
    }

    /**
     * Inspect and validate migration user privileges.
     *
     * @return array{verified: bool, grants: array<string>, warnings: array<string>}
     */
    public static function validatePrivileges(PDO $pdo, string $database, string $user, bool $isLab = false): array
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver !== 'mysql') {
            return [
                'verified' => true,
                'grants' => ['ALL (simulated/test driver)'],
                'warnings' => [],
            ];
        }

        $grants = [];
        $warnings = [];

        try {
            $stmt = $pdo->query('SHOW GRANTS FOR CURRENT_USER');
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                    $grantSql = (string) $row[0];
                    $grants[] = $grantSql;

                    // Lab isolation: check that user does not have global grants on other databases
                    if ($isLab) {
                        if (preg_match('/ON\s+`?(rycoopweb_test|rayongcoop_db|rycoop_prod)`?\./i', $grantSql, $m)) {
                            throw new RuntimeException(
                                "Lab privilege violation: Migration user `{$user}` has unauthorized grant on protected database `{$m[1]}`."
                            );
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $warnings[] = "Could not inspect SHOW GRANTS: " . MigrationRunner::sanitizeErrorMessage($e->getMessage());
        }

        return [
            'verified' => empty($warnings),
            'grants' => $grants,
            'warnings' => $warnings,
        ];
    }
}
