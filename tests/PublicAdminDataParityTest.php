<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

use App\Core\Database;
use App\Services\PublicContentService;

echo "========================================================================\n";
echo " RYCOOPWEB — PUBLIC / ADMIN DATA PARITY & RECONCILIATION TEST\n";
echo "========================================================================\n\n";

$passed = 0;
$failed = 0;

function assertParity(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$label}\n";
        $passed++;
    } else {
        echo " [FAIL] {$label}\n";
        $failed++;
    }
}

// 1. Core Invariants Verification (Strictly Immutable Fixtures)
$membersCount = (int) (Database::query("SELECT COUNT(*) AS total FROM members")[0]['total'] ?? 0);
$dividendsCount = (int) (Database::query("SELECT COUNT(*) AS total FROM member_dividends")[0]['total'] ?? 0);
$usersCount = (int) (Database::query("SELECT COUNT(*) AS total FROM users")[0]['total'] ?? 0);

assertParity("1.1 Core invariant: members count equals exactly 2,508", $membersCount === 2508);
assertParity("1.2 Core invariant: member_dividends count equals exactly 4,855", $dividendsCount === 4855);
assertParity("1.3 Core invariant: users count equals exactly 2,510", $usersCount === 2510);

// 2. Financial & Product Master Data Parity
$loanProductsCount = (int) (Database::query("SELECT COUNT(*) AS total FROM loan_products WHERE status = 'active' AND deleted_at IS NULL")[0]['total'] ?? 0);
$depositProductsCount = (int) (Database::query("SELECT COUNT(*) AS total FROM deposit_products WHERE status = 'active' AND deleted_at IS NULL")[0]['total'] ?? 0);
$welfareCount = (int) (Database::query("SELECT COUNT(*) AS total FROM welfare WHERE status = 'active' AND deleted_at IS NULL")[0]['total'] ?? 0);
$financialStatsCount = (int) (Database::query("SELECT COUNT(*) AS total FROM financial_statistics")[0]['total'] ?? 0);

assertParity("2.1 Active loan products in database >= 10 ({$loanProductsCount} found)", $loanProductsCount >= 10);
assertParity("2.2 Active deposit products in database >= 4 ({$depositProductsCount} found)", $depositProductsCount >= 4);
assertParity("2.3 Active welfare types in database >= 3 ({$welfareCount} found)", $welfareCount >= 3);
assertParity("2.4 Financial statistics records exist in database ({$financialStatsCount} found)", $financialStatsCount >= 1);

// 3. Document Management Parity
$docCategoriesCount = (int) (Database::query("SELECT COUNT(*) AS total FROM document_categories")[0]['total'] ?? 0);
$docsCount = (int) (Database::query("SELECT COUNT(*) AS total FROM documents WHERE status = 'active' AND deleted_at IS NULL")[0]['total'] ?? 0);

assertParity("3.1 Document categories defined in database ({$docCategoriesCount} found)", $docCategoriesCount >= 5);
assertParity("3.2 Active documents available in database ({$docsCount} found)", $docsCount >= 10);

// 4. Public Content Catalog Synchronization
$catalog = PublicContentService::catalog();

assertParity("4.1 Public catalog successfully resolved from database", is_array($catalog) && !empty($catalog));
assertParity("4.2 Public catalog contains board members from term 38", isset($catalog['boardMembers']) && count($catalog['boardMembers']) === 15);
assertParity("4.3 Public catalog contains staff members", isset($catalog['staffMembers']) && count($catalog['staffMembers']) === 6);
assertParity("4.4 Public catalog contains site settings", isset($catalog['siteSettings']) || isset($catalog['coopInfo']));

// 5. Hero Slides & E-Services Parity
$heroSlidesCount = (int) (Database::query("SELECT COUNT(*) AS total FROM hero_slides WHERE status = 'active' AND deleted_at IS NULL")[0]['total'] ?? 0);
$eservicesCount = (int) (Database::query("SELECT COUNT(*) AS total FROM eservice_links WHERE deleted_at IS NULL")[0]['total'] ?? 0);

assertParity("5.1 Active hero slides in database ({$heroSlidesCount} found)", $heroSlidesCount >= 3);
assertParity("5.2 E-services registered in database ({$eservicesCount} found)", $eservicesCount >= 5);

echo "\n========================================================================\n";
echo " PARITY VERIFICATION SUMMARY: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================================\n";

if ($failed > 0) {
    exit(1);
}
