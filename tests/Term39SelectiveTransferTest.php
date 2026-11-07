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

function assertTransfer(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 39 Selective Transfer Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 exists and clean up Term 39
BoardTermService::importTerm38();
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$term38Id = (int)$term38['id'];

$existing39 = Database::first("SELECT id FROM board_terms WHERE term_number = 39");
if ($existing39) {
    Database::execute("DELETE FROM board_terms WHERE id = ?", [$existing39['id']]);
}

// 2. Test getSelectiveTransferPreview()
$preview = BoardTermService::getSelectiveTransferPreview($term38Id, 39);
assertTransfer(is_array($preview), "1.1 Preview returns an array");
assertTransfer($preview['source_term']['term_number'] === 38, "1.2 Source term is Term 38");
assertTransfer($preview['target_term_number'] === 39, "1.3 Target term is Term 39");
assertTransfer(count($preview['candidates']['directors']) === 15, "1.4 Preview shows 15 director candidates");

// 3. Select 6 specific directors out of 15
$term38Directors = Database::query(
    "SELECT id, person_id, name, position FROM board_term_members WHERE term_id = ? AND category = 'director' ORDER BY sort_order ASC",
    [$term38Id]
);

$selectedDirectors = array_slice($term38Directors, 0, 6);
$selectedIds = array_map(fn($d) => (int)$d['id'], $selectedDirectors);
$selectedNames = array_map(fn($d) => $d['name'], $selectedDirectors);

assertTransfer(count($selectedIds) === 6, "2.1 Selected exactly 6 directors for transfer");

// 4. Execute selectiveTransfer()
$transferResult = BoardTermService::selectiveTransfer(
    $term38Id,
    39,
    $selectedIds,
    ['transfer_groups' => true, 'term_name' => 'ชุดที่ 39']
);

assertTransfer($transferResult['status'] === 'success', "3.1 Transfer succeeded");
assertTransfer($transferResult['target_term_number'] === 39, "3.2 Target term number is 39");
assertTransfer($transferResult['transferred_count'] === 6, "3.3 Transferred count is strictly 6");

$term39Id = (int)$transferResult['target_term_id'];

// 5. Verify target Term 39 in DB
$term39 = Database::first("SELECT * FROM board_terms WHERE id = ?", [$term39Id]);
assertTransfer($term39['status'] === 'draft', "4.1 Term 39 status is strictly 'draft'");
assertTransfer((int)$term39['is_current'] === 0, "4.2 Term 39 is_current is strictly 0");

$term39Members = Database::query("SELECT * FROM board_term_members WHERE term_id = ?", [$term39Id]);
assertTransfer(count($term39Members) === 6, "4.3 Term 39 has strictly 6 members");

// Verify all transferred members match selected
foreach ($term39Members as $m39) {
    assertTransfer(in_array($m39['name'], $selectedNames, true), "4.4 Transferred member '{$m39['name']}' is among selected");
}

// 6. Verify Term 38 is untouched
$term38DirectorsAfter = Database::query("SELECT id FROM board_term_members WHERE term_id = ? AND category = 'director'", [$term38Id]);
assertTransfer(count($term38DirectorsAfter) === 15, "5.1 Source Term 38 retains all 15 directors intact");

// Clean up Term 39 draft
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);

echo "=========================================================================\n";
echo "Term 39 Selective Transfer Test Suite PASSED successfully!\n";
