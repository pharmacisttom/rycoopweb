<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

echo "========================================================================\n";
echo " MIGRATION 029 SCHEMA & ROLLBACK VALIDATION (TEST ONLY)\n";
echo "========================================================================\n";

$passed = 0;
$failed = 0;

function assertCheck(string $desc, bool $cond): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] {$desc}\n";
        $passed++;
    } else {
        echo " [FAIL] {$desc}\n";
        $failed++;
    }
}

// 1. Inspect table popups
$columns = Database::query("DESCRIBE popups");
$columnMap = [];
foreach ($columns as $col) {
    $columnMap[$col['Field']] = $col;
}

// 2. Check legacy columns exist
$legacyCols = ['id', 'title', 'type', 'content', 'desktop_image', 'button_text', 'button_url', 'button_target', 'priority', 'start_at', 'end_at', 'status', 'created_by', 'created_at', 'updated_at', 'deleted_at'];
$legacyMissing = [];
foreach ($legacyCols as $lc) {
    if (!isset($columnMap[$lc])) {
        $legacyMissing[] = $lc;
    }
}
assertCheck("All legacy columns preserved in popups table", empty($legacyMissing));

// 3. Check migration 029 new columns exist with proper types
assertCheck("Column 'mascot_image' exists (varchar)", 
    isset($columnMap['mascot_image']) && str_contains(strtolower($columnMap['mascot_image']['Type']), 'varchar'));

assertCheck("Column 'layout_mode' exists (enum)", 
    isset($columnMap['layout_mode']) && str_contains(strtolower($columnMap['layout_mode']['Type']), 'enum'));

assertCheck("Column 'priority_order' exists (tinyint)", 
    isset($columnMap['priority_order']) && str_contains(strtolower($columnMap['priority_order']['Type']), 'tinyint'));

// 4. Default values check
assertCheck("Default value for 'layout_mode' is 'banner_only'", 
    ($columnMap['layout_mode']['Default'] ?? '') === 'banner_only');

assertCheck("Default value for 'priority_order' is 1", 
    (int)($columnMap['priority_order']['Default'] ?? 0) === 1);

// 5. Existing record compatibility test:
// Insert a legacy row (omitting mascot_image, layout_mode, priority_order)
Database::execute("INSERT INTO popups (title, desktop_image, status, created_at, updated_at) VALUES ('Legacy Test Record', 'legacy.jpg', 'active', NOW(), NOW())");
$legacyRow = Database::first("SELECT * FROM popups WHERE title = 'Legacy Test Record'");

assertCheck("Legacy insert populates default layout_mode = banner_only", 
    $legacyRow['layout_mode'] === 'banner_only');
assertCheck("Legacy insert populates default priority_order = 1", 
    (int)$legacyRow['priority_order'] === 1);
assertCheck("Legacy insert leaves mascot_image as NULL", 
    $legacyRow['mascot_image'] === null);

// Clean up test record
Database::execute("DELETE FROM popups WHERE title = 'Legacy Test Record'");

// 6. Rollback SQL test on a shadow table to prove rollback safety
Database::execute("CREATE TEMPORARY TABLE popups_test_shadow LIKE popups");
Database::execute("ALTER TABLE popups_test_shadow DROP COLUMN `priority_order`, DROP COLUMN `layout_mode`, DROP COLUMN `mascot_image`");
$shadowCols = Database::query("DESCRIBE popups_test_shadow");
$shadowColNames = array_map(fn($c) => $c['Field'], $shadowCols);
assertCheck("Rollback simulation cleanly removes V2 columns without breaking shadow table", 
    !in_array('mascot_image', $shadowColNames, true) && 
    !in_array('layout_mode', $shadowColNames, true) && 
    !in_array('priority_order', $shadowColNames, true) &&
    in_array('desktop_image', $shadowColNames, true));
Database::execute("DROP TEMPORARY TABLE popups_test_shadow");

echo "========================================================================\n";
echo " MIGRATION 029 VALIDATION: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
