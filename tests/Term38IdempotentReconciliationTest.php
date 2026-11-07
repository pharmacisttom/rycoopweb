<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING);

require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Core/Session.php';
require_once __DIR__ . '/../app/Core/Logger.php';
require_once __DIR__ . '/../app/Core/Helpers.php';
require_once __DIR__ . '/../app/Core/Auth.php';
require_once __DIR__ . '/../app/Services/AccessService.php';
require_once __DIR__ . '/../app/Services/AuditService.php';
require_once __DIR__ . '/../app/Services/PublicContentService.php';
require_once __DIR__ . '/../app/Services/BoardTermService.php';

use App\Core\Database;
use App\Services\BoardTermService;

function assertIdempotent(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 38 Idempotent Reconciliation Test Suite...\n";
echo "=========================================================================\n";

// 1. Initial import of Term 38
$result1 = BoardTermService::importTerm38(['preserve_custom_photos' => true, 'preserve_admin_edits' => true]);
assertIdempotent($result1['status'] === 'success', "1.1 First import completed successfully");
assertIdempotent($result1['term_number'] === 38, "1.2 Term number is 38");
assertIdempotent($result1['directors'] === 15, "1.3 Directors count is 15");
assertIdempotent($result1['advisors'] === 3, "1.4 Advisors count is 3");
assertIdempotent($result1['auditors'] === 3, "1.5 Auditors count is 3");
assertIdempotent($result1['groups'] === 3, "1.6 Groups count is 3");

$termId = (int)$result1['term_id'];

// Check database counts
$dirCount1 = (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'director'", [$termId]);
$advCount1 = (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'advisor'", [$termId]);
$audCount1 = (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'auditor'", [$termId]);
$grpCount1 = (int)Database::value("SELECT COUNT(*) FROM board_term_groups WHERE term_id = ?", [$termId]);

assertIdempotent($dirCount1 === 15, "1.7 Initial DB director count is exactly 15");
assertIdempotent($advCount1 === 3, "1.8 Initial DB advisor count is exactly 3");
assertIdempotent($audCount1 === 3, "1.9 Initial DB auditor count is exactly 3");
assertIdempotent($grpCount1 === 3, "1.10 Initial DB group count is exactly 3");

// 2. Second import of Term 38 (idempotency verification)
$result2 = BoardTermService::importTerm38(['preserve_custom_photos' => true, 'preserve_admin_edits' => true]);
assertIdempotent($result2['status'] === 'success', "2.1 Second import completed successfully");
assertIdempotent($result2['term_id'] === $termId, "2.2 Term ID is unchanged");

// Check database counts after second run
$dirCount2 = (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'director'", [$termId]);
$advCount2 = (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'advisor'", [$termId]);
$audCount2 = (int)Database::value("SELECT COUNT(*) FROM board_term_members WHERE term_id = ? AND category = 'auditor'", [$termId]);
$grpCount2 = (int)Database::value("SELECT COUNT(*) FROM board_term_groups WHERE term_id = ?", [$termId]);

assertIdempotent($dirCount2 === 15, "2.3 DB director count remains strictly 15 (no duplicate directors)");
assertIdempotent($advCount2 === 3, "2.4 DB advisor count remains strictly 3 (no duplicate advisors)");
assertIdempotent($audCount2 === 3, "2.5 DB auditor count remains strictly 3 (no duplicate auditors)");
assertIdempotent($grpCount2 === 3, "2.6 DB group count remains strictly 3 (no duplicate groups)");

// Check term status remains published and current
$term = Database::first("SELECT * FROM board_terms WHERE id = ?", [$termId]);
assertIdempotent($term['status'] === 'published', "3.1 Term 38 status remains strictly 'published'");
assertIdempotent((int)$term['is_current'] === 1, "3.2 Term 38 is_current remains strictly 1");

// Verify immutable system invariants
$dividendCount = (int)Database::value("SELECT COUNT(*) FROM member_dividends");
$memberCount = (int)Database::value("SELECT COUNT(*) FROM members");
assertIdempotent($dividendCount === 4855, "4.1 Financial invariant: member_dividends strictly 4,855 (actual: {$dividendCount})");
assertIdempotent($memberCount === 2508, "4.2 Member invariant: members strictly 2,508 (actual: {$memberCount})");

echo "=========================================================================\n";
echo "Term 38 Idempotent Reconciliation Test Suite PASSED successfully!\n";
