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

use App\Core\Auth;
use App\Core\Database;
use App\Services\BoardTermService;
use App\Services\PublicContentService;

function assertTerm(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Comprehensive Board Term Management & Unification Tests...\n";
echo "=========================================================================\n";

// 1. Table schema verification
assertTerm(BoardTermService::isTableAvailable(), "1.1 board_terms and related tables are available");

$pdo = Database::connect();
$tables = ['board_terms', 'board_term_members', 'board_term_groups', 'board_term_group_members'];
foreach ($tables as $tbl) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$tbl}'");
    assertTerm($stmt !== false && $stmt->rowCount() > 0, "1.2 Table {$tbl} exists in database");
}

// 2. Reconcile Term 38 from legacyReference.json
$reconcile = BoardTermService::reconcileTerm38(true);
assertTerm($reconcile['status'] === 'success', "2.1 Reconcile Term 38 completed with success status");
assertTerm($reconcile['term_number'] === 38, "2.2 Reconciled term is Term 38");
assertTerm($reconcile['directors'] === 15, "2.3 Reconciled exactly 15 directors matching legacyReference");
assertTerm($reconcile['advisors'] === 3, "2.4 Reconciled exactly 3 advisors matching legacyReference");
assertTerm($reconcile['auditors'] === 3, "2.5 Reconciled exactly 3 auditors matching legacyReference");
assertTerm($reconcile['groups'] === 3, "2.6 Reconciled exactly 3 subgroups matching legacyReference");

// 2.7 Verify active public term is Term 38
$activeTerm = BoardTermService::getPublicActiveTerm();
assertTerm($activeTerm !== null, "2.7 getPublicActiveTerm() returns active term");
assertTerm($activeTerm['term_number'] === 38, "2.8 Active public term is Term 38");
assertTerm(count($activeTerm['boardMembers']) === 15, "2.9 Active public term has 15 board members");
assertTerm($activeTerm['boardMembers'][0]['is_chairperson'] === true, "2.10 First board member is marked chairperson");
assertTerm(count($activeTerm['boardGroups']) === 3, "2.11 Active public term has 3 subgroups");
assertTerm(count($activeTerm['advisors']) === 3, "2.12 Active public term has 3 advisors");
assertTerm(count($activeTerm['auditors']) === 3, "2.13 Active public term has 3 auditors");

// 2.14 Verify PublicContentService::catalog() integration
$catalog = PublicContentService::catalog();
assertTerm(isset($catalog['boardMembers']) && count($catalog['boardMembers']) === 15, "2.14 PublicContentService::catalog() overlay has 15 boardMembers");
assertTerm(isset($catalog['currentTerm']) && $catalog['currentTerm'] === 'ชุดที่ 38', "2.15 PublicContentService::catalog() currentTerm is 'ชุดที่ 38'");
assertTerm(count($catalog['boardGroups']) === 3, "2.16 PublicContentService::catalog() boardGroups has 3 groups");

// 3. Multi-Term Creation & Draft Isolation
$existingTerm39 = Database::first("SELECT id FROM board_terms WHERE term_number = 39");
if ($existingTerm39) {
    Database::execute("DELETE FROM board_terms WHERE id = ?", [$existingTerm39['id']]);
}

$term39Id = BoardTermService::createTerm([
    'term_number' => 39,
    'term_name' => 'ชุดที่ 39',
    'year_start_be' => 2568,
    'year_end_be' => 2569,
    'note' => 'ทดสอบร่างวาระที่ 39',
], 1);

assertTerm($term39Id > 0, "3.1 Term 39 created in database with valid ID");

$term39 = BoardTermService::getTerm($term39Id);
assertTerm($term39 !== null, "3.2 Fetch Term 39 details succeeds");
assertTerm($term39['term']['status'] === 'draft', "3.3 Term 39 status is strictly 'draft'");
assertTerm($term39['term']['is_current'] === false, "3.4 Term 39 is_current is strictly false");

// Draft isolation: public site must STILL be Term 38
$publicTermAfterDraft = BoardTermService::getPublicActiveTerm();
assertTerm($publicTermAfterDraft['term_number'] === 38, "3.5 Public active term remains strictly Term 38 after creating Term 39 draft");
$publicCatalogAfterDraft = PublicContentService::catalog();
assertTerm($publicCatalogAfterDraft['currentTerm'] === 'ชุดที่ 38', "3.6 PublicContentService::catalog() still serves Term 38");

// 4. Copy Term (Clone 38 into 39)
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);
$term38Id = (int)Database::value("SELECT id FROM board_terms WHERE term_number = 38");
$clonedTerm39Id = BoardTermService::copyTerm($term38Id, 39, 'ชุดที่ 39', 1);

assertTerm($clonedTerm39Id > 0, "4.1 Cloned Term 39 created successfully");
$cloned39 = BoardTermService::getTerm($clonedTerm39Id);
assertTerm(count($cloned39['directors']) === 15, "4.2 Cloned Term 39 has 15 cloned directors");
assertTerm(count($cloned39['advisors']) === 3, "4.3 Cloned Term 39 has 3 cloned advisors");
assertTerm(count($cloned39['auditors']) === 3, "4.4 Cloned Term 39 has 3 cloned auditors");
assertTerm(count($cloned39['groups']) === 3, "4.5 Cloned Term 39 has 3 cloned subgroups");

// Verify subgroup member IDs remapped to cloned member IDs
$firstGroup = $cloned39['groups'][0];
$firstGroupMemberId = (int)$firstGroup['members'][0]['member_id'];
$directorIdsIn39 = array_column($cloned39['directors'], 'id');
assertTerm(in_array($firstGroupMemberId, array_map('intval', $directorIdsIn39), true), "4.6 Subgroup members in Term 39 point to cloned member IDs in Term 39, not Term 38");

// 5. Editing Term 39 draft members and testing referential integrity
$firstDirector = $cloned39['directors'][0];
$testDirectors = $cloned39['directors'];
$testDirectors[0]['position'] = 'ประธานกรรมการชุดใหม่';

BoardTermService::saveTermMembers($clonedTerm39Id, $testDirectors, 'director', 1);
$updated39 = BoardTermService::getTerm($clonedTerm39Id);
assertTerm($updated39['directors'][0]['position'] === 'ประธานกรรมการชุดใหม่', "5.1 Draft director position updated in Term 39");

// Public page must NOT have this updated position because Term 39 is still draft!
$catalogStill38 = PublicContentService::catalog();
assertTerm($catalogStill38['boardMembers'][0]['position'] !== 'ประธานกรรมการชุดใหม่', "5.2 Public catalog completely shielded from draft changes");

// Referential integrity: attempt to delete a director who is assigned to a subgroup
$assignedMemberId = (int)$cloned39['groups'][0]['members'][0]['member_id'];
$directorsWithoutAssigned = array_filter($updated39['directors'], fn($d) => (int)$d['id'] !== $assignedMemberId);

$referentialBlocked = false;
try {
    BoardTermService::saveTermMembers($clonedTerm39Id, array_values($directorsWithoutAssigned), 'director', 1);
} catch (InvalidArgumentException $e) {
    $referentialBlocked = true;
    echo "    Ref check message: {$e->getMessage()}\n";
}
assertTerm($referentialBlocked, "5.3 Attempting to delete a member who belongs to a subgroup is safely blocked by referential integrity check");

// 6. Preview Capability
$preview39 = BoardTermService::getTermPreview($clonedTerm39Id);
assertTerm($preview39 !== null, "6.1 getTermPreview() generates preview for draft term");
assertTerm($preview39['term_number'] === 39, "6.2 Preview reflects Term 39");
assertTerm($preview39['boardMembers'][0]['position'] === 'ประธานกรรมการชุดใหม่', "6.3 Preview reflects modified draft member position");
// And live public term is still 38:
assertTerm(BoardTermService::getPublicActiveTerm()['term_number'] === 38, "6.4 Live public term remains Term 38 while previewing Term 39");

// 7. Scheduled Publication Isolation
$futureDate = date('Y-m-d H:i:s', time() + 86400 * 30);
BoardTermService::scheduleTerm($clonedTerm39Id, $futureDate, 1);
$scheduled39 = BoardTermService::getTerm($clonedTerm39Id);
assertTerm($scheduled39['term']['status'] === 'scheduled', "7.1 Term 39 status is 'scheduled'");
assertTerm(BoardTermService::getPublicActiveTerm()['term_number'] === 38, "7.2 Public active term remains strictly Term 38 when Term 39 is scheduled for future");

// 8. Publishing Term 39 (Live Transition)
BoardTermService::publishTerm($clonedTerm39Id, 1);

$published39 = BoardTermService::getTerm($clonedTerm39Id);
assertTerm($published39['term']['status'] === 'published', "8.1 Term 39 status is 'published'");
assertTerm($published39['term']['is_current'] === true, "8.2 Term 39 is_current is true");

$archived38 = BoardTermService::getTerm($term38Id);
assertTerm($archived38['term']['status'] === 'archived', "8.3 Term 38 status transitioned to 'archived'");
assertTerm($archived38['term']['is_current'] === false, "8.4 Term 38 is_current is false");

// Now public site serves Term 39!
$publicAfterPublish = BoardTermService::getPublicActiveTerm();
assertTerm($publicAfterPublish['term_number'] === 39, "8.5 Public active term transitioned to Term 39");
$catalogAfterPublish = PublicContentService::catalog();
assertTerm($catalogAfterPublish['currentTerm'] === 'ชุดที่ 39', "8.6 PublicContentService::catalog() serves Term 39");
assertTerm($catalogAfterPublish['boardMembers'][0]['position'] === 'ประธานกรรมการชุดใหม่', "8.7 PublicContentService::catalog() contains updated Term 39 director position");

// Historical terms listing
$history = BoardTermService::getHistoricalTerms();
assertTerm(count($history) >= 2, "8.8 Historical terms contains both terms");
assertTerm($history[0]['term_number'] === 39 && $history[0]['is_current'] === true, "8.9 Historical terms list has Term 39 current at top");
assertTerm($history[1]['term_number'] === 38 && $history[1]['is_current'] === false, "8.10 Historical terms list has Term 38 archived");

// Clean up: Re-promote Term 38 so the baseline remains Term 38
BoardTermService::publishTerm($term38Id, 1);
Database::execute("DELETE FROM board_terms WHERE id = ?", [$clonedTerm39Id]);
$cleanCatalog = PublicContentService::catalog();
assertTerm($cleanCatalog['currentTerm'] === 'ชุดที่ 38', "8.11 Baseline safely reset to Term 38");

// 9. Audit Logging verification
$auditCount = (int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module = 'board_terms'");
assertTerm($auditCount > 0, "9.1 Audit logs recorded board_terms actions ({$auditCount} entries recorded)");

// 10. Financial Data Immutability
$dividendCount = (int)Database::value("SELECT COUNT(*) FROM member_dividends");
assertTerm($dividendCount === 4855, "10.1 member_dividends count is strictly 4,855 (actual: {$dividendCount})");

$memberCount = (int)Database::value("SELECT COUNT(*) FROM members");
assertTerm($memberCount === 2508, "10.2 members count is strictly 2,508 (actual: {$memberCount})");

$userCount = (int)Database::value("SELECT COUNT(*) FROM users");
assertTerm($userCount === 2510, "10.3 users count is strictly 2,510 (actual: {$userCount})");

echo "=========================================================================\n";
echo "All Board Term Management & Public Unification tests PASSED successfully!\n";
