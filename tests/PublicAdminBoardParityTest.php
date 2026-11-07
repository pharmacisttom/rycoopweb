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

function assertParity(bool $condition, string $message): void {
    if (!$condition) {
        echo "FAIL: {$message}\n";
        exit(1);
    }
    echo "PASS: {$message}\n";
}

echo "Running Public/Admin Board Data Parity Test Suite...\n";
echo "=========================================================================\n";

// 1. Ensure Term 38 is imported
$import = BoardTermService::importTerm38();
assertParity($import['status'] === 'success', "1.1 Term 38 imported successfully");
$term38Id = (int)$import['term_id'];

// 2. Fetch from BoardTermService
$adminTerm = BoardTermService::getTerm($term38Id);
$publicTerm = BoardTermService::getPublicActiveTerm();
$catalog = PublicContentService::catalog();

assertParity($adminTerm !== null, "2.1 Admin getTerm() returns Term 38");
assertParity($publicTerm !== null, "2.2 Public getPublicActiveTerm() returns Term 38");
assertParity(isset($catalog['boardMembers']), "2.3 Public catalog contains boardMembers");

// 3. Parity checks for Directors
assertParity(count($adminTerm['directors']) === 15, "3.1 Admin directors count is 15");
assertParity(count($publicTerm['boardMembers']) === 15, "3.2 Public active term directors count is 15");
assertParity(count($catalog['boardMembers']) === 15, "3.3 Catalog boardMembers count is 15");

for ($i = 0; $i < 15; $i++) {
    $adminDir = $adminTerm['directors'][$i];
    $pubDir = $publicTerm['boardMembers'][$i];
    $catDir = $catalog['boardMembers'][$i];

    assertParity($adminDir['name'] === $pubDir['name'], "3.4 Director #{$i} name matches between Admin and Public term ('{$adminDir['name']}')");
    assertParity($pubDir['name'] === $catDir['name'], "3.5 Director #{$i} name matches between Public term and Catalog");
    assertParity($adminDir['position'] === $pubDir['position'], "3.6 Director #{$i} position matches between Admin and Public term");
}

// 4. Parity checks for Advisors & Auditors
assertParity(count($adminTerm['advisors']) === 3, "4.1 Admin advisors count is 3");
assertParity(count($publicTerm['advisors']) === 3, "4.2 Public advisors count is 3");
assertParity(count($catalog['advisors']) === 3, "4.3 Catalog advisors count is 3");

assertParity(count($adminTerm['auditors']) === 3, "4.4 Admin auditors count is 3");
assertParity(count($publicTerm['auditors']) === 3, "4.5 Public auditors count is 3");
assertParity(count($catalog['auditors']) === 3, "4.6 Catalog auditors count is 3");

// 5. Parity checks for Subcommittees
assertParity(count($adminTerm['groups']) === 3, "5.1 Admin groups count is 3");
assertParity(count($publicTerm['boardGroups']) === 3, "5.2 Public groups count is 3");
assertParity(count($catalog['boardGroups']) === 3, "5.3 Catalog groups count is 3");

for ($g = 0; $g < 3; $g++) {
    $adminG = $adminTerm['groups'][$g];
    $pubG = $publicTerm['boardGroups'][$g];
    $catG = $catalog['boardGroups'][$g];

    assertParity($adminG['title'] === $pubG['title'], "5.4 Group #{$g} title matches between Admin and Public ('{$adminG['title']}')");
    assertParity($pubG['title'] === $catG['title'], "5.5 Group #{$g} title matches between Public and Catalog");
    assertParity(count($adminG['members']) === count($pubG['members']), "5.6 Group #{$g} members count matches between Admin and Public");
}

// 6. Test live modification propagation
$originalPosition = $adminTerm['directors'][1]['position'];
$newPosition = 'รองประธานกรรมการคนที่ ๑ (แก้ไขสดโดยแอดมิน)';

$updatedDirectors = $adminTerm['directors'];
$updatedDirectors[1]['position'] = $newPosition;
BoardTermService::saveTermMembers($term38Id, $updatedDirectors, 'director', 1);

$catalogAfterEdit = PublicContentService::catalog();
assertParity($catalogAfterEdit['boardMembers'][1]['position'] === $newPosition, "6.1 Edited director position in Term 38 propagates immediately to PublicContentService::catalog()");

// Revert position
$updatedDirectors[1]['position'] = $originalPosition;
BoardTermService::saveTermMembers($term38Id, $updatedDirectors, 'director', 1);

echo "=========================================================================\n";
echo "Public/Admin Board Data Parity Test Suite PASSED successfully!\n";
