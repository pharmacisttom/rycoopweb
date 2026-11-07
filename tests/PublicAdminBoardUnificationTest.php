<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\PublicContentService;
use App\Controllers\Admin\BoardStaffController;
use App\Controllers\Admin\PublicContentController;

class MockResponse extends Response
{
    public int $statusCode = 200;
    public array $responseData = [];

    public function json(mixed $data, int $status = 200): void
    {
        $this->statusCode = $status;
        $this->responseData = is_array($data) ? $data : ['data' => $data];
    }
}

echo "========================================================================\n";
echo " RYCOOPWEB — PUBLIC & ADMIN BOARD DATA UNIFICATION TEST SUITE\n";
echo "========================================================================\n\n";

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
// Test 1: Authoritative Published Data Source & Count Consistency
// ─────────────────────────────────────────────────────────────────────────────
$catalog = PublicContentService::catalog();
$boardMembers = $catalog['boardMembers'] ?? [];
$staffMembers = $catalog['staffMembers'] ?? [];
$advisors = $catalog['advisors'] ?? [];
$auditors = $catalog['auditors'] ?? [];
$boardGroups = $catalog['boardGroups'] ?? [];

assertCheck("1.1 Public catalog contains exactly 15 published board members", count($boardMembers) === 15);
assertCheck("1.2 Public catalog contains exactly 6 published staff members", count($staffMembers) === 6);
assertCheck("1.3 Public catalog contains 3 advisors and 3 auditors", count($advisors) === 3 && count($auditors) === 3);
assertCheck("1.4 Public catalog contains 3 board groups", count($boardGroups) === 3);

// ─────────────────────────────────────────────────────────────────────────────
// Test 2: Published Names, Positions, Images and Terms Match
// ─────────────────────────────────────────────────────────────────────────────
$firstBoard = $boardMembers[0];
assertCheck("2.1 Board president is นางสาวอุษา อิศรางกูร ณ อยุธยา", $firstBoard['name'] === 'นางสาวอุษา อิศรางกูร ณ อยุธยา');
assertCheck("2.2 Board president position is ประธานกรรมการ", $firstBoard['position'] === 'ประธานกรรมการ');
assertCheck("2.3 Board president term is ชุดที่ 38", $firstBoard['term'] === 'ชุดที่ 38');
assertCheck("2.4 Board president image path resolves to valid assets URL",
    resolve_media_url($firstBoard['image']) === '/assets/img/committee/committee-01-president.webp');

$firstStaff = $staffMembers[0];
assertCheck("2.5 Staff manager is นางนริศษา แย้มกลิ่น", $firstStaff['name'] === 'นางนริศษา แย้มกลิ่น');
assertCheck("2.6 Staff manager position is ผู้จัดการ", $firstStaff['position'] === 'ผู้จัดการ');
assertCheck("2.7 Staff manager image path resolves to valid assets URL",
    resolve_media_url($firstStaff['image']) === '/assets/img/officer/officer-01-manager.jpg');

// ─────────────────────────────────────────────────────────────────────────────
// Test 3: BoardStaffController Unified API Response
// ─────────────────────────────────────────────────────────────────────────────
Auth::login(['id' => 1, 'name' => 'Admin Test', 'email' => 'admin@example.com', 'role_slug' => 'super_admin'], true);

$req = new Request();
$res = new MockResponse();
$controller = new BoardStaffController($req, $res);
$controller->apiIndex();

assertCheck("3.1 BoardStaffController::apiIndex returns success true", ($res->responseData['success'] ?? false) === true);
assertCheck("3.2 BoardStaffController returns exactly 15 board members", count($res->responseData['data']['boards'] ?? []) === 15);
assertCheck("3.3 BoardStaffController returns exactly 6 staff members", count($res->responseData['data']['staff'] ?? []) === 6);
assertCheck("3.4 BoardStaffController board records match public catalog president name",
    ($res->responseData['data']['boards'][0]['name'] ?? '') === $firstBoard['name']);
assertCheck("3.5 BoardStaffController board records match public catalog president term",
    ($res->responseData['data']['boards'][0]['term_years'] ?? '') === 'ชุดที่ 38');

// ─────────────────────────────────────────────────────────────────────────────
// Test 4: Image URL Resolution & Safe Normalization
// ─────────────────────────────────────────────────────────────────────────────
assertCheck("4.1 Relative ./assets/ committee URL resolves to /assets/",
    resolve_media_url('./assets/img/committee/committee-02-vice-president.webp') === '/assets/img/committee/committee-02-vice-president.webp');
assertCheck("4.2 Relative ./assets/ officer URL resolves to /assets/",
    resolve_media_url('./assets/img/officer/officer-02-finance.jpg') === '/assets/img/officer/officer-02-finance.jpg');
assertCheck("4.3 Uploaded personnel URL in storage resolves without prefix duplication",
    resolve_media_url('/storage/uploads/personnel/sample.webp') === '/storage/uploads/personnel/sample.webp');
assertCheck("4.4 Relative uploads path resolves cleanly",
    resolve_media_url('storage/uploads/personnel/sample.webp') === '/storage/uploads/personnel/sample.webp');
assertCheck("4.5 Null image safely returns fallback or null",
    resolve_media_url(null, '/assets/img/board_placeholder.jpg') === '/assets/img/board_placeholder.jpg');

// ─────────────────────────────────────────────────────────────────────────────
// Test 5: Draft Isolation — Saving Draft Does NOT Alter Public Data
// ─────────────────────────────────────────────────────────────────────────────
$initialPresidentName = PublicContentService::catalog()['boardMembers'][0]['name'];

$draftBoardList = $boardMembers;
$draftBoardList[0]['name'] = 'นางสาวอุษา อิศรางกูร ณ อยุธยา (ร่างแก้ไข)';

$sectionInfo = PublicContentService::section('boards');
$draftInput = [
    'value' => $draftBoardList,
    'version' => $sectionInfo['version'],
    'note' => 'ทดสอบการสร้างร่างโดยไม่กระทบข้อมูลจริง',
    'source_url' => 'https://rayongcoop.com/board',
    'effective_on' => date('Y-m-d')
];

$draftId = PublicContentService::saveDraft('boards', $draftInput);
assertCheck("5.1 Draft saved successfully with ID #{$draftId}", $draftId > 0);

$publicCatalogAfterDraft = PublicContentService::catalog();
assertCheck("5.2 Public catalog president name remains strictly unchanged while in draft",
    $publicCatalogAfterDraft['boardMembers'][0]['name'] === $initialPresidentName);

// ─────────────────────────────────────────────────────────────────────────────
// Test 6: Authorized Publication Updates Public Content Correctly
// ─────────────────────────────────────────────────────────────────────────────
PublicContentService::publish($draftId);

$publicCatalogAfterPublish = PublicContentService::catalog();
assertCheck("6.1 Public catalog president name updated immediately after publication",
    $publicCatalogAfterPublish['boardMembers'][0]['name'] === 'นางสาวอุษา อิศรางกูร ณ อยุธยา (ร่างแก้ไข)');

// Revert back cleanly to original state
$revertSectionInfo = PublicContentService::section('boards');
$revertInput = [
    'value' => $boardMembers, // original 15 board members
    'version' => $revertSectionInfo['version'],
    'note' => 'คืนค่าข้อมูลทางการกลับสู่ชุดที่ 38',
    'source_url' => 'https://rayongcoop.com/board',
    'effective_on' => date('Y-m-d')
];
$revertDraftId = PublicContentService::saveDraft('boards', $revertInput);
PublicContentService::publish($revertDraftId);

$revertedCatalog = PublicContentService::catalog();
assertCheck("6.2 Public catalog successfully restored to original official president name",
    $revertedCatalog['boardMembers'][0]['name'] === $initialPresidentName);

// ─────────────────────────────────────────────────────────────────────────────
// Test 7: Unauthorized Publication & Role RBAC (403 Forbidden)
// ─────────────────────────────────────────────────────────────────────────────
Auth::logout();
Auth::login(['id' => 999, 'name' => 'Member Test', 'email' => 'member@example.com', 'role_slug' => 'member'], true);

$isUnauthorizedBlocked = false;
try {
    PublicContentService::section('boards');
} catch (\Throwable $e) {
    if ($e->getCode() === 403 || str_contains($e->getMessage(), 'FORBIDDEN')) {
        $isUnauthorizedBlocked = true;
    }
}
assertCheck("7.1 Unprivileged member cannot view admin section revisions (403)", $isUnauthorizedBlocked);

$isDraftBlocked = false;
try {
    PublicContentService::saveDraft('boards', $draftInput);
} catch (\Throwable $e) {
    if ($e->getCode() === 403 || str_contains($e->getMessage(), 'FORBIDDEN')) {
        $isDraftBlocked = true;
    }
}
assertCheck("7.2 Unprivileged member cannot save drafts (403)", $isDraftBlocked);

// ─────────────────────────────────────────────────────────────────────────────
// Test 8: Public Content API Endpoints Return 200 Without 404 / 500
// ─────────────────────────────────────────────────────────────────────────────
Auth::logout(); // Public anonymous request
$pubReq = new Request();
$pubRes = new MockResponse();
$pubController = new PublicContentController($pubReq, $pubRes);
$pubController->publicCatalog();

assertCheck("8.1 GET /api/public/content returns HTTP 200 with success = true", ($pubRes->responseData['success'] ?? false) === true);
assertCheck("8.2 GET /api/public/content data contains coopInfo and boardMembers",
    isset($pubRes->responseData['data']['coopInfo']) && is_array($pubRes->responseData['data']['boardMembers']));

// ─────────────────────────────────────────────────────────────────────────────
// Test 9: Referential Integrity & Sub-committee Constraints
// ─────────────────────────────────────────────────────────────────────────────
Auth::login(['id' => 1, 'name' => 'Admin Test', 'email' => 'admin@example.com', 'role_slug' => 'super_admin'], true);

$shrunkBoard = array_slice($boardMembers, 1); // remove president who is in group 1
$referentialBlocked = false;
try {
    PublicContentService::validate('boards', $shrunkBoard);
} catch (\Throwable $e) {
    if (str_contains($e->getMessage(), 'คณะย่อย')) {
        $referentialBlocked = true;
    }
}
assertCheck("9.1 Removing board member who is currently in a sub-committee is strictly blocked", $referentialBlocked);

// ─────────────────────────────────────────────────────────────────────────────
// Test 10: Financial & Core Member Immutability Protection
// ─────────────────────────────────────────────────────────────────────────────
$dividendCount = (int) Database::value("SELECT COUNT(*) FROM member_dividends");
$memberCount = (int) Database::value("SELECT COUNT(*) FROM members");
$userCount = (int) Database::value("SELECT COUNT(*) FROM users");

assertCheck("10.1 Protected core member_dividends count remains strictly 4,855", $dividendCount === 4855);
assertCheck("10.2 Protected core members count intact (2,508)", $memberCount === 2508);
assertCheck("10.3 Protected core users count intact (2,510)", $userCount === 2510);

Auth::logout();

echo "\n========================================================================\n";
echo " DATA UNIFICATION RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
