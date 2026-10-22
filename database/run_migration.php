<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

new App\Core\App();

$migration = $argv[1] ?? '020_sync_deposit_rates.sql';
$file = __DIR__ . '/migrations/' . $migration;

if (!file_exists($file)) {
    echo "Migration file not found: {$file}\n";
    exit(1);
}

$sql = file_get_contents($file);
App\Core\Database::pdo()->exec($sql);
echo "Migration {$migration} applied successfully!\n";
