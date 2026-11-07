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

function assertSec(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function assertThrowsEx(callable $fn, string $expectedClass, string $message): void {
    try {
        $fn();
        echo "FAIL: {$message} (No exception thrown)\n";
        exit(1);
    } catch (\Throwable $e) {
        if ($e instanceof $expectedClass) {
            echo "PASS: {$message}\n";
        } else {
            echo "FAIL: {$message} (Expected {$expectedClass}, got " . get_class($e) . ": {$e->getMessage()})\n";
            exit(1);
        }
    }
}

echo "Running Selective Transfer Security & Invariant Test Suite...\n";
echo "=========================================================================\n";

// 1. Verify system invariants before test
$membersCountBefore = (int)Database::value("SELECT COUNT(*) FROM members");
$usersCountBefore = (int)Database::value("SELECT COUNT(*) FROM users");
$dividendsCountBefore = (int)Database::value("SELECT COUNT(*) FROM member_dividends");

assertSec($membersCountBefore === 2508, "1.1 Initial members count invariant is 2,508");
assertSec($usersCountBefore === 2510, "1.2 Initial users count invariant is 2,510");
assertSec($dividendsCountBefore === 4855, "1.3 Initial member_dividends count invariant is 4,855");

// 2. Ensure Term 38 exists
BoardTermService::importTerm38();
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$term38Id = (int)$term38['id'];

// 3. Security Gate: Empty selection rejected
assertThrowsEx(function () use ($term38Id) {
    BoardTermService::selectiveTransfer($term38Id, 39, []);
}, \InvalidArgumentException::class, "2.1 Empty selection throws InvalidArgumentException");

// 4. Security Gate: Invalid source term ID rejected
assertThrowsEx(function () {
    BoardTermService::selectiveTransfer(999999, 39, [1]);
}, \InvalidArgumentException::class, "2.2 Non-existent source term throws InvalidArgumentException");

// 5. Security Gate: Cannot transfer into an already published term
$publishedTerm = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
assertThrowsEx(function () use ($term38Id) {
    BoardTermService::selectiveTransfer($term38Id, 38, [1]);
}, \InvalidArgumentException::class, "2.3 Transferring into a published term (Term 38) is strictly blocked");

// 6. Test Audit Logging
$auditBeforeCount = (int)Database::value(
    "SELECT COUNT(*) FROM audit_logs WHERE module = 'board_terms' AND action = 'selective_transfer'"
);

$term38Directors = Database::query("SELECT id FROM board_term_members WHERE term_id = ? AND category = 'director'", [$term38Id]);
$transferResult = BoardTermService::selectiveTransfer(
    $term38Id,
    39,
    [(int)$term38Directors[0]['id'], (int)$term38Directors[1]['id']],
    ['term_name' => 'ชุดที่ 39']
);
assertSec($transferResult['success'] === true, "3.1 Transfer executed successfully");

$auditAfterCount = (int)Database::value(
    "SELECT COUNT(*) FROM audit_logs WHERE module = 'board_terms' AND action = 'selective_transfer'"
);
assertSec($auditAfterCount === $auditBeforeCount + 1, "3.2 Audit log recorded for selective_transfer event");

$latestLog = Database::first(
    "SELECT * FROM audit_logs WHERE module = 'board_terms' AND action = 'selective_transfer' ORDER BY id DESC LIMIT 1"
);
$logDetails = json_decode($latestLog['new_values'] ?? $latestLog['details'] ?? '{}', true);
assertSec($logDetails['source_term_id'] === $term38Id, "3.3 Audit log records correct source_term_id");
assertSec($logDetails['target_term_number'] === 39, "3.4 Audit log records target_term_number = 39");
assertSec($logDetails['transferred_members'] === 2, "3.5 Audit log records transferred_members = 2");

// Clean up Term 39 draft
Database::execute("DELETE FROM board_terms WHERE id = ?", [(int)$transferResult['target_term_id']]);

// 7. Verify system invariants after test
$membersCountAfter = (int)Database::value("SELECT COUNT(*) FROM members");
$usersCountAfter = (int)Database::value("SELECT COUNT(*) FROM users");
$dividendsCountAfter = (int)Database::value("SELECT COUNT(*) FROM member_dividends");

assertSec($membersCountAfter === 2508, "4.1 Post-transfer members count is strictly 2,508 (UNTOUCHED)");
assertSec($usersCountAfter === 2510, "4.2 Post-transfer users count is strictly 2,510 (UNTOUCHED)");
assertSec($dividendsCountAfter === 4855, "4.3 Post-transfer member_dividends count is strictly 4,855 (UNTOUCHED)");

echo "=========================================================================\n";
echo "Selective Transfer Security & Invariant Test Suite PASSED successfully!\n";
