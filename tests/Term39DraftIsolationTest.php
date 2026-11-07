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
use App\Services\PublicContentService;

function assertIsolation(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Term 39 Draft Isolation Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 is imported
BoardTermService::importTerm38();
$term38 = Database::first("SELECT id FROM board_terms WHERE term_number = 38");
$term38Id = (int)$term38['id'];

// 2. Clean and create Term 39 draft
$existing39 = Database::first("SELECT id FROM board_terms WHERE term_number = 39");
if ($existing39) {
    Database::execute("DELETE FROM board_terms WHERE id = ?", [$existing39['id']]);
}

$all38Members = Database::query("SELECT id FROM board_term_members WHERE term_id = ? AND category = 'director'", [$term38Id]);
$memberIds = array_map(fn($m) => (int)$m['id'], $all38Members);

$transferResult = BoardTermService::selectiveTransfer($term38Id, 39, $memberIds, ['term_name' => 'ชุดที่ 39']);
$term39Id = (int)$transferResult['target_term_id'];

// 3. Make radical changes in Term 39 draft
Database::execute(
    "UPDATE board_term_members SET name = 'นายทดสอบ การแยกฉบับร่าง', position = 'ประธานกรรมการชุดใหม่ (ฉบับร่าง)' WHERE term_id = ? AND person_id = 1",
    [$term39Id]
);

$draftMember = Database::first("SELECT * FROM board_term_members WHERE term_id = ? AND person_id = 1", [$term39Id]);
assertIsolation($draftMember['name'] === 'นายทดสอบ การแยกฉบับร่าง', "1.1 Draft member name was modified in Term 39");

// 4. Verify public active term is completely isolated and strictly Term 38
$activeTerm = BoardTermService::getPublicActiveTerm();
assertIsolation($activeTerm !== null, "2.1 getPublicActiveTerm() returns active term");
assertIsolation($activeTerm['term_number'] === 38, "2.2 Active public term is strictly Term 38");
assertIsolation($activeTerm['term_name'] === 'ชุดที่ 38', "2.3 Active public term name is 'ชุดที่ 38'");
assertIsolation(count($activeTerm['boardMembers']) === 15, "2.4 Active public term has all 15 members");

$firstPublicMember = $activeTerm['boardMembers'][0];
assertIsolation(str_contains($firstPublicMember['name'], 'อุษา'), "2.5 Public president is strictly Term 38 original ('อุษา'), NOT draft name");
assertIsolation($firstPublicMember['position'] === 'ประธานกรรมการ', "2.6 Public position is strictly Term 38 original, NOT draft position");

// 5. Verify PublicContentService::catalog() overlay
$catalog = PublicContentService::catalog();
assertIsolation($catalog['currentTerm'] === 'ชุดที่ 38', "3.1 PublicContentService::catalog() currentTerm is 'ชุดที่ 38'");
assertIsolation(str_contains($catalog['boardMembers'][0]['name'], 'อุษา'), "3.2 Public catalog first member is 'อุษา'");
assertIsolation($catalog['boardMembers'][0]['name'] !== 'นายทดสอบ การแยกฉบับร่าง', "3.3 Draft modifications do NOT leak into public catalog");

// Clean up Term 39 draft
Database::execute("DELETE FROM board_terms WHERE id = ?", [$term39Id]);

echo "=========================================================================\n";
echo "Term 39 Draft Isolation Test Suite PASSED successfully!\n";
