#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CI Database & Least-Privilege Security Accounts Provisioner.
 *
 * Dedicated to GitHub Actions CI quality gate:
 * 1. Connects using the CI MySQL administrative account (root) strictly for initial setup.
 * 2. Creates the isolated CI database (e.g. `rayongcoop_ci`).
 * 3. Creates the dedicated CI migration user (`rycoop_ci_migrator`) with database-scoped DDL/DML.
 * 4. Creates the dedicated CI application user (`rycoop_ci_app`) with database-scoped DML.
 * 5. Strictly enforces that credentials are never logged or exposed.
 */

$host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('DB_PORT') ?: 3306);
$adminUser = (string) (getenv('MYSQL_ADMIN_USER') ?: 'root');
$adminPassword = (string) (getenv('MYSQL_ADMIN_PASSWORD') ?: (getenv('MYSQL_ROOT_PASSWORD') ?: 'root'));
$database = (string) (getenv('DB_DATABASE') ?: 'rayongcoop_ci');

$migratorUser = (string) (getenv('CI_MIGRATOR_USER') ?: 'rycoop_ci_migrator');
$migratorPassword = (string) (getenv('CI_MIGRATOR_PASSWORD') ?: '');

$appUser = (string) (getenv('CI_APP_USER') ?: (getenv('DB_USERNAME') ?: 'rycoop_ci_app'));
$appPassword = (string) (getenv('CI_APP_PASSWORD') ?: (getenv('DB_PASSWORD') ?: ''));

if ($migratorPassword === '') {
    fwrite(STDERR, "✗ Security Error: CI_MIGRATOR_PASSWORD must be provided via environment.\n");
    exit(1);
}

if ($appPassword === '') {
    fwrite(STDERR, "✗ Security Error: CI_APP_PASSWORD (or DB_PASSWORD) must be provided via environment.\n");
    exit(1);
}

// Ensure database name is safe identifier
if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
    fwrite(STDERR, "✗ Security Error: Invalid database name `{$database}`.\n");
    exit(1);
}

// Prohibit provisioning against system databases
$systemDbs = ['information_schema', 'mysql', 'performance_schema', 'sys'];
if (in_array(strtolower($database), $systemDbs, true)) {
    fwrite(STDERR, "✗ Security Error: Prohibited system database `{$database}`.\n");
    exit(1);
}

// Ensure usernames are safe identifiers and not root/admin
$prohibitedUsers = ['root', 'admin', 'administrator'];
if (in_array(strtolower($migratorUser), $prohibitedUsers, true)) {
    fwrite(STDERR, "✗ Security Error: Migration user cannot be `{$migratorUser}`.\n");
    exit(1);
}
if (in_array(strtolower($appUser), $prohibitedUsers, true)) {
    fwrite(STDERR, "✗ Security Error: Application user cannot be `{$appUser}`.\n");
    exit(1);
}

echo "====================================================================\n";
echo "RYCOOPWEB CI Security Provisioning\n";
echo "====================================================================\n";
echo "Target Host:       {$host}:{$port}\n";
echo "Target Database:   {$database}\n";
echo "Admin Account:     {$adminUser}\n";
echo "Migration Account: {$migratorUser} (database-scoped DDL/DML)\n";
echo "App Account:       {$appUser} (database-scoped DML)\n";
echo "====================================================================\n\n";

$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $adminUser, $adminPassword, $options);
} catch (PDOException $e) {
    $sanitized = preg_replace('/:[^:@]+@/', ':***@', $e->getMessage());
    fwrite(STDERR, "✗ Failed to connect to MySQL administrative account: {$sanitized}\n");
    exit(1);
}

try {
    // 1. Create isolated CI database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "✓ Isolated database `{$database}` created or already exists.\n";

    // 2. Provision migration account (DDL + DML, database-scoped only)
    $quotedMigratorPass = $pdo->quote($migratorPassword);
    $hosts = ['%', 'localhost'];
    foreach ($hosts as $h) {
        $userHost = sprintf("`%s`@`%s`", str_replace('`', '``', $migratorUser), str_replace('`', '``', $h));
        $pdo->exec("CREATE USER IF NOT EXISTS {$userHost} IDENTIFIED BY {$quotedMigratorPass};");
        $pdo->exec("ALTER USER {$userHost} IDENTIFIED BY {$quotedMigratorPass};");
        $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE ON `{$database}`.* TO {$userHost};");
    }
    echo "✓ Provisioned dedicated migration account `{$migratorUser}` with database-scoped DDL/DML privileges.\n";

    // 3. Provision application account (DML only, database-scoped only)
    $quotedAppPass = $pdo->quote($appPassword);
    foreach ($hosts as $h) {
        $userHost = sprintf("`%s`@`%s`", str_replace('`', '``', $appUser), str_replace('`', '``', $h));
        $pdo->exec("CREATE USER IF NOT EXISTS {$userHost} IDENTIFIED BY {$quotedAppPass};");
        $pdo->exec("ALTER USER {$userHost} IDENTIFIED BY {$quotedAppPass};");
        $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE ON `{$database}`.* TO {$userHost};");
    }
    echo "✓ Provisioned dedicated application account `{$appUser}` with database-scoped runtime privileges.\n";

    // 4. Flush privileges
    $pdo->exec("FLUSH PRIVILEGES;");
    echo "✓ Privileges flushed successfully.\n";

    echo "\nCI Database Security Provisioning Completed Successfully.\n";
} catch (PDOException $e) {
    $sanitized = preg_replace('/:[^:@]+@/', ':***@', $e->getMessage());
    fwrite(STDERR, "✗ Provisioning failed: {$sanitized}\n");
    exit(1);
}
