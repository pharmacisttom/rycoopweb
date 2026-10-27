<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Migration Configuration & Database Isolation Policy Validator.
 *
 * Enforces:
 * - Explicit DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.
 * - Strict rejection of silent default database ('rayongcoop_db').
 * - Strict rejection of default 'root' account to prevent privilege escalation.
 * - Port and database naming standard validation.
 * - Dedicated Lab database isolation policy (rycoopweb_migration_lab).
 */
class MigrationConfigValidator
{
    public const PROHIBITED_USERS = ['root', 'admin', 'administrator'];
    public const SYSTEM_DATABASES = ['information_schema', 'mysql', 'performance_schema', 'sys'];
    public const PROTECTED_DATABASES = ['rycoopweb_test', 'rayongcoop_db', 'rycoop_prod', 'rayongcoop_production'];
    public const PROHIBITED_LAB_USERS = ['root', 'admin', 'rycoop_app', 'rycoop_prod_user'];

    /**
     * Validate database configuration for migration CLI execution.
     *
     * @param array<string, mixed> $config
     * @return array{host: string, port: int, database: string, username: string, password: string, is_lab: bool}
     * @throws InvalidArgumentException
     */
    public static function validate(array $config = []): array
    {
        // 1. Resolve host
        $rawHost = $config['host'] ?? getenv('DB_HOST');
        $host = trim((string) ($rawHost !== false ? $rawHost : ''));
        if ($host === '') {
            throw new InvalidArgumentException(
                "Migration configuration error: DB_HOST is required. Silent default host is prohibited."
            );
        }

        // 2. Resolve port
        $rawPort = $config['port'] ?? getenv('DB_PORT');
        if ($rawPort === false || $rawPort === null || trim((string) $rawPort) === '') {
            throw new InvalidArgumentException(
                "Migration configuration error: DB_PORT is required. Explicit port must be configured."
            );
        }
        $port = (int) $rawPort;
        if ($port <= 0 || $port > 65535) {
            throw new InvalidArgumentException(
                "Migration configuration error: Invalid DB_PORT `{$rawPort}`. Port must be between 1 and 65535."
            );
        }

        // 3. Resolve database name
        $rawDb = $config['database'] ?? getenv('DB_DATABASE');
        $database = trim((string) ($rawDb !== false ? $rawDb : ''));
        if ($database === '') {
            throw new InvalidArgumentException(
                "Migration configuration error: DB_DATABASE is required. Silent default database is prohibited."
            );
        }

        if (in_array(strtolower($database), self::SYSTEM_DATABASES, true)) {
            throw new InvalidArgumentException(
                "Migration security violation: Cannot target system database `{$database}` for migrations."
            );
        }

        // Prohibit silent fallback to default placeholder 'rayongcoop_db' unless explicitly authorized
        if (strtolower($database) === 'rayongcoop_db' && ($config['explicit_database'] ?? false) !== true && getenv('MIGRATION_ALLOW_DEFAULT_DB') !== 'true') {
            throw new InvalidArgumentException(
                "Migration security violation: Default database `rayongcoop_db` cannot be used silently. " .
                "Explicitly configure DB_DATABASE or pass --db-name to confirm intended target."
            );
        }

        // 4. Resolve username
        $rawUser = $config['username'] ?? getenv('DB_USERNAME');
        $username = trim((string) ($rawUser !== false ? $rawUser : ''));
        if ($username === '') {
            throw new InvalidArgumentException(
                "Migration configuration error: DB_USERNAME is required. Silent default username is prohibited."
            );
        }

        if (in_array(strtolower($username), self::PROHIBITED_USERS, true)) {
            throw new InvalidArgumentException(
                "Migration security violation: Executing migrations as '{$username}' is strictly prohibited. " .
                "Use a dedicated migration account adhering to principle of least privilege."
            );
        }

        // 5. Resolve password
        $hasPassword = array_key_exists('password', $config) || getenv('DB_PASSWORD') !== false;
        if (!$hasPassword) {
            throw new InvalidArgumentException(
                "Migration configuration error: DB_PASSWORD must be explicitly provided in environment or configuration."
            );
        }
        $rawPassword = $config['password'] ?? getenv('DB_PASSWORD');
        $password = (string) ($rawPassword !== false ? $rawPassword : '');

        // 6. Check lab isolation constraints
        $isLab = strtolower($database) === 'rycoopweb_migration_lab' || ($config['is_lab'] ?? false) === true;
        if ($isLab) {
            self::validateLabIsolation($database, $username, $config);
        }

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'is_lab' => $isLab,
        ];
    }

    /**
     * Validate Lab Database Isolation rules (rycoopweb_migration_lab).
     */
    public static function validateLabIsolation(string $database, string $username, array $config = []): void
    {
        if (strtolower($database) !== 'rycoopweb_migration_lab') {
            throw new InvalidArgumentException(
                "Lab isolation violation: Lab migrations must strictly target `rycoopweb_migration_lab`, got `{$database}`."
            );
        }

        // Prohibit targeting test or production DBs from lab config
        if (in_array(strtolower($database), self::PROTECTED_DATABASES, true)) {
            throw new InvalidArgumentException(
                "Lab isolation violation: Cannot target protected database `{$database}` under Lab configuration."
            );
        }

        // Lab username must not be application/production account
        if (in_array(strtolower($username), self::PROHIBITED_LAB_USERS, true)) {
            throw new InvalidArgumentException(
                "Lab isolation violation: Reusing application/root credentials `{$username}` in Lab environment is prohibited."
            );
        }
    }
}
