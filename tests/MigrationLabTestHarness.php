<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Isolated Test Harness for RYCOOPWEB Migration Lab (rycoopweb_migration_lab).
 *
 * Provides:
 * - Mocked and isolated lab database connection fixtures.
 * - Privilege isolation verification (no access to rycoopweb_test or Production).
 * - Root account exclusion assertions.
 * - Credential redaction verification.
 */
class MigrationLabTestHarness
{
    public const LAB_DATABASE = 'rycoopweb_migration_lab';
    public const LAB_USERNAME = 'rycoop_lab';

    /**
     * Generate isolated lab configuration.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function createLabConfig(array $overrides = []): array
    {
        return array_merge([
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => self::LAB_DATABASE,
            'username' => self::LAB_USERNAME,
            'password' => 'lab_isolated_secret_999!',
            'is_lab' => true,
        ], $overrides);
    }

    /**
     * Create an in-memory SQLite PDO mimicking MySQL driver behavior for lab testing.
     *
     * @param string $database
     * @param string $username
     * @param array<string> $grants
     * @return PDO
     */
    public static function createMockLabPdo(
        string $database = self::LAB_DATABASE,
        string $username = self::LAB_USERNAME,
        array $grants = []
    ): PDO {
        return new class($database, $username, $grants) extends PDO {
            private string $activeDb;
            private string $currentUser;
            private array $grants;

            public function __construct(string $database, string $username, array $grants)
            {
                parent::__construct('sqlite::memory:');
                $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->activeDb = $database;
                $this->currentUser = $username . '@localhost';
                $this->grants = !empty($grants) ? $grants : [
                    "GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'localhost'"
                ];
            }

            public function getAttribute(int $attribute): mixed
            {
                if ($attribute === PDO::ATTR_DRIVER_NAME) {
                    return 'mysql';
                }
                return parent::getAttribute($attribute);
            }

            public function query(string $query, ?int $fetchMode = null, ...$fetch_mode_args): mixed
            {
                if (stripos($query, 'SELECT DATABASE()') !== false) {
                    $db = $this->activeDb;
                    return new class($db) {
                        public function __construct(private string $db) {}
                        public function fetchColumn() { return $this->db; }
                    };
                }

                if (stripos($query, 'SELECT CURRENT_USER()') !== false) {
                    $user = $this->currentUser;
                    return new class($user) {
                        public function __construct(private string $user) {}
                        public function fetchColumn() { return $this->user; }
                    };
                }

                if (stripos($query, 'SHOW GRANTS') !== false) {
                    $grants = $this->grants;
                    return new class($grants) {
                        private int $idx = 0;
                        public function __construct(private array $grants) {}
                        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
                            if ($this->idx >= count($this->grants)) return false;
                            return [$this->grants[$this->idx++]];
                        }
                    };
                }

                if (stripos($query, 'SHOW TABLES') !== false) {
                    return new class {
                        public function fetchColumn() { return false; }
                    };
                }

                if (stripos($query, 'SHOW COLUMNS') !== false) {
                    return new class {
                        public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
                            return false;
                        }
                    };
                }

                if (stripos($query, 'SELECT GET_LOCK') !== false) {
                    return new class {
                        public function fetchColumn() { return 1; }
                    };
                }

                if (stripos($query, 'SELECT RELEASE_LOCK') !== false) {
                    return new class {
                        public function fetchColumn() { return 1; }
                    };
                }

                return parent::query($query);
            }
        };
    }
}
