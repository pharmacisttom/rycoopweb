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

function assertSubcommittee(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 39 Subcommittee Integrity Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 is imported
BoardTermService::importTerm38();
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$term38Id = (int)$term38['id'];

// Clean Term 39
$existing39 = Database::first("SELECT id FROM board_terms WHERE term_number = 39");
if ($existing39) {
    Database::execute("DELETE FROM board_terms WHERE id = ?", [$existing39['id']]);
}

// 2. In Term 38, find the first group and its members
$group38 = Database::first("SELECT * FROM board_term_groups WHERE term_id = ? ORDER BY sort_order ASC", [$term38Id]);
assertSubcommittee($group38 !== null, "1.1 Term 38 has at least one subgroup");

$group38Members = Database::query(
    "SELECT gm.*, m.name, m.person_id FROM board_term_group_members gm JOIN board_term_members m ON gm.member_id = m.id WHERE gm.group_id = ? ORDER BY gm.sort_order ASC",
    [$group38['id']]
);
assertSubcommittee(count($group38Members) >= 4, "1.2 Term 38 subgroup has at least 4 members (actual: " . count($group38Members) . ")");

// Select only 2 members of this group for transfer
$selectedForTransfer = array_slice($group38Members, 0, 2);
$selectedMemberIds = array_map(fn($gm) => (int)$gm['member_id'], $selectedForTransfer);
$omittedMemberIds = array_map(fn($gm) => (int)$gm['member_id'], array_slice($group38Members, 2));

assertSubcommittee(count($selectedMemberIds) === 2, "2.1 Selected strictly 2 members for transfer");
assertSubcommittee(count($omittedMemberIds) >= 2, "2.2 Omitted at least 2 members from transfer");

// 3. Execute selective transfer
$transferResult = BoardTermService::selectiveTransfer(
    $term38Id,
    39,
    $selectedMemberIds,
    ['transfer_groups' => true, 'term_name' => 'ชุดที่ 39']
);

$term39Id = (int)$transferResult['target_term_id'];
assertSubcommittee($term39Id > 0, "3.1 Target Term 39 created");

// 4. Inspect subgroup in Term 39
$group39 = Database::first("SELECT * FROM board_term_groups WHERE term_id = ? AND title = ?", [$term39Id, $group38['title']]);
assertSubcommittee($group39 !== null, "4.1 Subgroup '{$group38['title']}' created in Term 39");

$group39Members = Database::query(
    "SELECT gm.*, m.name, m.term_id FROM board_term_group_members gm JOIN board_term_members m ON gm.member_id = m.id WHERE gm.group_id = ?",
    [$group39['id']]
);

assertSubcommittee(count($group39Members) === 2, "4.2 Subgroup in Term 39 has strictly 2 members (only transferred members)");

// 5. Verify referential integrity: ALL members in Term 39 group point strictly to Term 39 members
foreach ($group39Members as $gm39) {
    assertSubcommittee((int)$gm39['term_id'] === $term39Id, "4.3 Group member points to Term 39 member ID, NOT Term 38");
}

// 6. Verify omitted members are NOT present in Term 39 group
$allGroup39MemberIds = array_column($group39Members, 'member_id');
foreach ($omittedMemberIds as $omitId) {
    assertSubcommittee(!in_array($omitId, $allGroup39MemberIds, true), "4.4 Omitted Term 38 member #{$omitId} is NOT in Term 39 group (no dangling references)");
}

// Clean up
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);

echo "=========================================================================\n";
echo "Term 39 Subcommittee Integrity Test Suite PASSED successfully!\n";
