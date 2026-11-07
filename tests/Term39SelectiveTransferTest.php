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

// Verify all transferred members match selected and have source_member_id set correctly
foreach ($term39Members as $m39) {
    assertTransfer(in_array($m39['name'], $selectedNames, true), "4.4 Transferred member '{$m39['name']}' is among selected");
    assertTransfer(!empty($m39['source_member_id']), "4.5 Transferred member '{$m39['name']}' has non-null source_member_id");
    assertTransfer(in_array((int)$m39['source_member_id'], $selectedIds, true), "4.6 source_member_id {$m39['source_member_id']} belongs to selected IDs");
}

// 6. Test candidates endpoint & already_transferred badge logic
$candidatesData = BoardTermService::getSelectionCandidates($term38Id, 39);
assertTransfer(is_array($candidatesData), "5.1 getSelectionCandidates returns array");
assertTransfer($candidatesData['target_term']['id'] === $term39Id, "5.2 Target term matches Term 39");

$directorsCandidates = $candidatesData['candidates']['directors'];
$transferredCandidateCount = 0;
foreach ($directorsCandidates as $cand) {
    if (in_array((int)$cand['id'], $selectedIds, true)) {
        assertTransfer($cand['already_transferred'] === true, "5.3 Candidate {$cand['name']} is flagged as already_transferred = true");
        assertTransfer($cand['target_member'] !== null, "5.4 Candidate {$cand['name']} includes target_member details");
        assertTransfer($cand['target_member']['source_member_id'] === (int)$cand['id'], "5.5 target_member source_member_id links back to candidate");
        $transferredCandidateCount++;
    } else {
        assertTransfer($cand['already_transferred'] === false, "5.6 Candidate {$cand['name']} is NOT transferred");
        assertTransfer($cand['target_member'] === null, "5.7 Candidate {$cand['name']} has null target_member");
    }
}
assertTransfer($transferredCandidateCount === 6, "5.8 Exactly 6 candidates flagged as already_transferred");

// 7. Test getSelectiveCopyPreview with customizations
$previewSelections = [
    [
        'source_member_id' => $selectedIds[0],
        'position' => 'ประธานกรรมการ (ปรับตำแหน่ง)',
        'role_type' => 'president',
        'is_chairperson' => true
    ],
    [
        'source_member_id' => $selectedIds[1],
        'position' => 'รองประธานกรรมการคนที่ 1',
        'role_type' => 'vice_president',
        'is_chairperson' => false
    ]
];
$copyPreview = BoardTermService::getSelectiveCopyPreview($term38Id, 39, $previewSelections);
assertTransfer($copyPreview['selected_count'] === 2, "6.1 getSelectiveCopyPreview reflects 2 selected items");
assertTransfer($copyPreview['items'][0]['target_position'] === 'ประธานกรรมการ (ปรับตำแหน่ง)', "6.2 Preview contains customized target position");
assertTransfer($copyPreview['items'][0]['action'] === 'update', "6.3 Preview detects existing member as 'update'");

// 8. Test re-transfer with custom positions: ensure idempotency & unique constraint prevents duplicates
$customResult = BoardTermService::selectiveTransfer(
    $term38Id,
    39,
    $previewSelections,
    ['transfer_groups' => true]
);
assertTransfer($customResult['success'] === true, "7.1 Re-transfer with customizations succeeded");

// Member count should still be 6 (no duplicate rows created due to uq_term_source_member)
$term39MembersAfterUpdate = Database::query("SELECT * FROM board_term_members WHERE term_id = ?", [$term39Id]);
assertTransfer(count($term39MembersAfterUpdate) === 6, "7.2 Member count remains strictly 6 with zero duplicate rows");

$updatedMember0 = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND source_member_id = ?", [$term39Id, $selectedIds[0]]);
assertTransfer($updatedMember0['position'] === 'ประธานกรรมการ (ปรับตำแหน่ง)', "7.3 Member position was updated in-place");
assertTransfer($updatedMember0['role_type'] === 'president', "7.4 Member role_type was updated in-place");
assertTransfer((int)$updatedMember0['is_chairperson'] === 1, "7.5 Member is_chairperson was updated in-place");

// 9. Verify Term 38 remains untouched
$term38DirectorsAfter = Database::query("SELECT id FROM board_term_members WHERE term_id = ? AND category = 'director'", [$term38Id]);
assertTransfer(count($term38DirectorsAfter) === 15, "8.1 Source Term 38 retains all 15 directors intact");

// Clean up Term 39 draft
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);

echo "=========================================================================\n";
echo "Term 39 Selective Transfer Test Suite PASSED successfully!\n";
