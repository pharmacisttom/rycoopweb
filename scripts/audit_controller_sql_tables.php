<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

// Fetch all tables and their columns in rayongcoop_db
$tables = [];
$cols = Database::query("
    SELECT TABLE_NAME, COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'rayongcoop_db'
");

foreach ($cols as $row) {
    $tables[$row['TABLE_NAME']][] = strtolower($row['COLUMN_NAME']);
}

echo "=== CHECKING CONTROLLER SQL QUERIES AGAINST MYSQL SCHEMA ===" . PHP_EOL;

$controllerDir = __DIR__ . '/../app/Controllers/Admin';
$serviceDir = __DIR__ . '/../app/Services';
$files = array_merge(glob($controllerDir . '/*.php'), glob($serviceDir . '/*.php'));

$issues = [];

foreach ($files as $file) {
    $filename = basename($file);
    $content = file_get_contents($file);

    // Extract SQL queries in Database::query/first/execute/value("...")
    preg_match_all('/Database::(?:query|first|execute|value)\(\s*["\']([^"\']+)["\']/i', $content, $matches);

    foreach ($matches[1] as $sql) {
        // Check FROM <table> or JOIN <table> or UPDATE <table> or INTO <table>
        if (preg_match('/(?:FROM|JOIN|INTO|UPDATE)\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $tblMatch)) {
            $tableName = strtolower($tblMatch[1]);
            if (!isset($tables[$tableName])) {
                // Table doesn't exist
                $issues[] = [
                    'file' => $filename,
                    'type' => 'TABLE_NOT_FOUND',
                    'detail' => "Table '{$tableName}' does not exist in rayongcoop_db",
                    'sql' => trim($sql)
                ];
                continue;
            }

            // Check columns in WHERE or SET
            $knownCols = $tables[$tableName];

            // Check for deleted_at
            if (str_contains(strtolower($sql), 'deleted_at') && !in_array('deleted_at', $knownCols, true)) {
                $issues[] = [
                    'file' => $filename,
                    'type' => 'COLUMN_NOT_FOUND',
                    'detail' => "Column 'deleted_at' does not exist on table '{$tableName}'",
                    'sql' => trim($sql)
                ];
            }

            // Check for icon
            if (str_contains(strtolower($sql), 'icon') && !in_array('icon', $knownCols, true)) {
                $issues[] = [
                    'file' => $filename,
                    'type' => 'COLUMN_NOT_FOUND',
                    'detail' => "Column 'icon' does not exist on table '{$tableName}'",
                    'sql' => trim($sql)
                ];
            }
        }
    }
}

echo "Total SQL issues found in app/Controllers/Admin: " . count($issues) . PHP_EOL;
foreach ($issues as $issue) {
    echo " [{$issue['type']}] {$issue['file']}: {$issue['detail']}" . PHP_EOL;
    echo "   SQL: " . substr($issue['sql'], 0, 90) . "..." . PHP_EOL;
}
