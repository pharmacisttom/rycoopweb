#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seeder = file_get_contents($root . '/database/Seeders/DatabaseSeeder.php');
if ($seeder === false) {
    fwrite(STDERR, "Unable to read DatabaseSeeder.php\n");
    exit(1);
}

preg_match_all("~['\"](/assets/[^'\"]+|assets/[^'\"]+)['\"]~", $seeder, $matches);
$missing = [];
foreach (array_unique($matches[1]) as $url) {
    $relative = ltrim($url, '/');
    $path = $root . '/public/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        $missing[] = '/' . $relative;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Missing seeded static assets:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo 'Validated ' . count(array_unique($matches[1])) . " seeded static asset(s).\n";
