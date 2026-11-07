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

function assertImmutable(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 38 Strict Immutability Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 is imported
BoardTermService::importTerm38();
$term38 = Database::first("SELECT * FROM board_terms WHERE term_number = 38");
assertImmutable($term38 !== null, "1.1 Term 38 exists in database");
$term38Id = (int)$term38['id'];

// Snapshot Term 38 before transfer
$membersBefore = Database::query(
    "SELECT id, term_id, person_id, category, name, position, role_type, workplace, department, image_url, bio, sort_order, is_chairperson
     FROM board_term_members WHERE term_id = ? ORDER BY id ASC",
    [$term38Id]
);
$groupsBefore = Database::query(
    "SELECT id, term_id, title, description, sort_order FROM board_term_groups WHERE term_id = ? ORDER BY id ASC",
    [$term38Id]
);
$groupMembersBefore = Database::query(
    "SELECT gm.id, gm.group_id, gm.member_id, gm.role_in_group, gm.sort_order
     FROM board_term_group_members gm
     JOIN board_term_groups g ON gm.group_id = g.id
     WHERE g.term_id = ? ORDER BY gm.id ASC",
    [$term38Id]
);

assertImmutable(count($membersBefore) === 21, "1.2 Term 38 snapshot contains strictly 21 members (15 directors, 4 advisors, 2 auditors)");
assertImmutable(count($groupsBefore) === 3, "1.3 Term 38 snapshot contains strictly 3 subcommittees");

// Clean up any existing Term 39
$existing39 = Database::first("SELECT id FROM board_terms WHERE term_number = 39");
if ($existing39) {
    Database::execute("DELETE FROM board_terms WHERE id = ?", [$existing39['id']]);
}

// 2. Perform selective transfer of 5 members with customized positions and role changes
$selectedIds = array_slice(array_column($membersBefore, 'id'), 0, 5);
$customizations = [
    $selectedIds[0] => ['position' => 'ที่ปรึกษากิตติมศักดิ์', 'role_type' => 'advisor'],
    $selectedIds[1] => ['position' => 'ประธานกรรมการชุดใหม่', 'role_type' => 'president', 'is_chairperson' => 1],
];

$result = BoardTermService::selectiveTransfer(
    $term38Id,
    39,
    $selectedIds,
    [
        'transfer_groups' => true,
        'customizations' => $customizations,
        'term_name' => 'ชุดที่ 39 (วาระทดสอบ)'
    ]
);

assertImmutable($result['success'] === true, "2.1 Selective transfer executed successfully");
$term39Id = (int)$result['target_term_id'];

// 3. Snapshot Term 38 after transfer and assert 100% strict equality
$term38After = Database::first("SELECT * FROM board_terms WHERE term_number = 38");
assertImmutable($term38After['status'] === 'published', "3.1 Term 38 status remains strictly 'published'");
assertImmutable((int)$term38After['is_current'] === 1, "3.2 Term 38 is_current remains strictly 1");
assertImmutable($term38After['term_name'] === $term38['term_name'], "3.3 Term 38 term_name is unchanged");
assertImmutable($term38After['year_start_be'] === $term38['year_start_be'], "3.4 Term 38 year_start_be is unchanged");
assertImmutable($term38After['year_end_be'] === $term38['year_end_be'], "3.5 Term 38 year_end_be is unchanged");

$membersAfter = Database::query(
    "SELECT id, term_id, person_id, category, name, position, role_type, workplace, department, image_url, bio, sort_order, is_chairperson
     FROM board_term_members WHERE term_id = ? ORDER BY id ASC",
    [$term38Id]
);
assertImmutable($membersBefore === $membersAfter, "3.6 All 21 Term 38 members are 100% byte-for-byte identical to pre-transfer snapshot");

$groupsAfter = Database::query(
    "SELECT id, term_id, title, description, sort_order FROM board_term_groups WHERE term_id = ? ORDER BY id ASC",
    [$term38Id]
);
assertImmutable($groupsBefore === $groupsAfter, "3.7 All Term 38 subcommittees are 100% identical to pre-transfer snapshot");

$groupMembersAfter = Database::query(
    "SELECT gm.id, gm.group_id, gm.member_id, gm.role_in_group, gm.sort_order
     FROM board_term_group_members gm
     JOIN board_term_groups g ON gm.group_id = g.id
     WHERE g.term_id = ? ORDER BY gm.id ASC",
    [$term38Id]
);
assertImmutable($groupMembersBefore === $groupMembersAfter, "3.8 All Term 38 subcommittee member links are 100% identical");

// Clean up Term 39 draft
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);

echo "=========================================================================\n";
echo "Term 38 Strict Immutability Test Suite PASSED successfully!\n";
