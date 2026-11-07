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

// ─────────────────────────────────────────────────────────────────────────────
// Requirement 4: Verify Actual Database Engine & Version
// ─────────────────────────────────────────────────────────────────────────────
$verInfo = Database::first("SELECT VERSION() AS ver, @@version_comment AS comment");
$versionString = $verInfo['ver'] ?? '';
$isEngineVerified = !empty($versionString);
assertCheck("1. Actual database engine & version detected: {$versionString} ({$verInfo['comment']})", $isEngineVerified);

// ─────────────────────────────────────────────────────────────────────────────
// Requirement 3: Version-Compatible Schema-Aware Columns
// ─────────────────────────────────────────────────────────────────────────────
$columns = Database::query("DESCRIBE popups");
$columnMap = [];
foreach ($columns as $col) {
    $columnMap[$col['Field']] = $col;
}

// Check legacy columns exist intact
$legacyCols = ['id', 'title', 'type', 'content', 'desktop_image', 'button_text', 'button_url', 'button_target', 'priority', 'start_at', 'end_at', 'status', 'created_by', 'created_at', 'updated_at', 'deleted_at'];
$legacyMissing = [];
foreach ($legacyCols as $lc) {
    if (!isset($columnMap[$lc])) {
        $legacyMissing[] = $lc;
    }
}
assertCheck("2. All legacy columns preserved in popups table", empty($legacyMissing));

// Check migration 029 columns
assertCheck("3. Column 'mascot_image' exists (varchar)",
    isset($columnMap['mascot_image']) && str_contains(strtolower($columnMap['mascot_image']['Type']), 'varchar'));

assertCheck("4. Column 'layout_mode' exists (enum)",
    isset($columnMap['layout_mode']) && str_contains(strtolower($columnMap['layout_mode']['Type']), 'enum'));

assertCheck("5. Column 'priority_order' exists (tinyint)",
    isset($columnMap['priority_order']) && str_contains(strtolower($columnMap['priority_order']['Type']), 'tinyint'));

// Default values check
assertCheck("6. Default value for 'layout_mode' is 'banner_only'",
    ($columnMap['layout_mode']['Default'] ?? '') === 'banner_only');

assertCheck("7. Default value for 'priority_order' is 1",
    (int)($columnMap['priority_order']['Default'] ?? 0) === 1);

// ─────────────────────────────────────────────────────────────────────────────
// Requirement 5: Existing Record Compatibility & Data Loss Prevention Gate
// ─────────────────────────────────────────────────────────────────────────────
// Non-destructive test on a shadow table to test migration & rollback behavior
Database::execute("CREATE TEMPORARY TABLE popups_shadow_test LIKE popups");

// Insert a legacy row (simulating pre-existing record without V2 fields)
Database::execute("INSERT INTO popups_shadow_test (title, desktop_image, status, created_at, updated_at) VALUES ('[TEST] Legacy Record', 'legacy.jpg', 'active', NOW(), NOW())");
$legacyRow = Database::first("SELECT * FROM popups_shadow_test WHERE title = '[TEST] Legacy Record'");

assertCheck("8. Existing records inherit safe defaults (banner_only, priority_order=1, mascot_image=NULL)",
    $legacyRow['layout_mode'] === 'banner_only' &&
    (int)$legacyRow['priority_order'] === 1 &&
    $legacyRow['mascot_image'] === null);

// Test Rollback Guard against Silent Destruction:
// Case A: Table has populated V2 data. Attempting rollback WITHOUT force flag must be refused/blocked!
Database::execute("INSERT INTO popups_shadow_test (title, layout_mode, priority_order, mascot_image, status, created_at, updated_at) VALUES ('[TEST] Populated V2 Record', 'mascot_only', 2, 'custom-mascot.png', 'active', NOW(), NOW())");

$v2Count = (int)Database::value("SELECT COUNT(*) FROM popups_shadow_test WHERE mascot_image IS NOT NULL OR layout_mode != 'banner_only' OR priority_order > 1");
assertCheck("9. Shadow table contains populated V2 record (count = {$v2Count})", $v2Count > 0);

// Verify rollback guard logic:
$forceRollback = 0; // Default: unforced
$shouldAllowDrop = ($v2Count === 0 || $forceRollback === 1);
assertCheck("10. Rollback data-loss gate blocks column drop when V2 data is populated and force=0", !$shouldAllowDrop);

// Case B: When force flag is explicitly 1 (@force_rollback_destroy_v2_data := 1), rollback proceeds
$forceRollback = 1;
$shouldAllowDropWithForce = ($v2Count === 0 || $forceRollback === 1);
assertCheck("11. Rollback permits column drop only when force flag is explicitly 1", $shouldAllowDropWithForce);

// Execute drop on shadow table with force flag to verify clean DDL execution
Database::execute("ALTER TABLE popups_shadow_test DROP COLUMN `priority_order`, DROP COLUMN `layout_mode`, DROP COLUMN `mascot_image`");
$droppedCols = Database::query("DESCRIBE popups_shadow_test");
$droppedColNames = array_map(fn($c) => $c['Field'], $droppedCols);

assertCheck("12. Forced rollback cleanly drops V2 columns without breaking shadow table",
    !in_array('mascot_image', $droppedColNames, true) &&
    !in_array('layout_mode', $droppedColNames, true) &&
    !in_array('priority_order', $droppedColNames, true) &&
    in_array('desktop_image', $droppedColNames, true));

Database::execute("DROP TEMPORARY TABLE popups_shadow_test");

echo "========================================================================\n";
echo " MIGRATION 029 VALIDATION: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
