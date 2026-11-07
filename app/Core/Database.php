<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static ?array $tablesCache = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $config = config('database.connections.mysql');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    $config['options']
                );
            } catch (PDOException $e) {
                Logger::error('Database Connection Error: ' . $e->getMessage());
                if (config('app.debug')) {
                    throw new RuntimeException('Database Connection Failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
                } else {
                    die('ระบบฐานข้อมูลขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้งในภายหลัง');
                }
            }
        }

        return self::$instance;
    }

    public static function pdo(): PDO
    {
        return self::connect();
    }

    public static function resetTableCache(): void
    {
        self::$tablesCache = null;
    }

    public static function hasTable(string $table): bool
    {
        if (self::$tablesCache === null) {
            try {
                $driver = self::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME);
                if ($driver === 'sqlite') {
                    $tables = self::query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                } else {
                    $tables = self::query("SHOW TABLES");
                }
                self::$tablesCache = array_map(fn($t) => strtolower((string)array_values($t)[0]), $tables);
            } catch (\Throwable) {
                return false;
            }
        }
        return in_array(strtolower($table), self::$tablesCache, true);
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): string|false
    {
        $pdo = self::connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    }

    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollBack(): bool
    {
        return self::connect()->rollBack();
    }

    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }
}
