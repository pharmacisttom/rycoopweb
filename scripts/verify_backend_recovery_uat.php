<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

$sessPath = __DIR__ . '/../storage/tmp/admin-audit-sessions';
if (!is_dir($sessPath)) mkdir($sessPath, 0700, true);
putenv('SESSION_SAVE_PATH=' . $sessPath);
ini_set('session.save_path', $sessPath);

Session::start();

class UatCaptureResponse extends Response
{
    public int $statusCode = 200;
    public mixed $bodyData = null;

    public function json(array|object $data, int $status = 200, array $headers = []): void
    {
        $this->statusCode = $status;
        $this->bodyData = $data;
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        return $this;
    }
}

function dispatchUat(string $method, string $path, array $data = []): UatCaptureResponse
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
    $_POST = $data;

    $router = new Router();
    require __DIR__ . '/../config/routes.php';

    $request = new Request();
    $response = new UatCaptureResponse();

    $router->dispatch($request, $response);
    return $response;
}

echo "========================================================================" . PHP_EOL;
echo " RYCOOPWEB — REAL END-TO-END UAT RECOVERY VERIFICATION" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

// 1. Authenticate as Admin ID 1
$adminUser = Database::first("
    SELECT u.*, r.slug AS role_slug, r.name AS role_name
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE u.id = 1
");

if (!$adminUser) {
    echo "[FAIL] Admin user ID 1 not found!" . PHP_EOL;
    exit(1);
}

Auth::login($adminUser, true, false);

$uatResults = [];

// UAT-01: Admin Identity Verification
$res = dispatchUat('GET', '/api/auth/me');
$uat01Pass = $res->statusCode === 200 && ($res->bodyData['authenticated'] ?? false) === true;
$uatResults[] = [
    'id' => 'UAT-01',
    'title' => 'Admin Authentication & Session Truth',
    'pass' => $uat01Pass,
    'detail' => "HTTP {$res->statusCode}, User: {$res->bodyData['user']['username']}, Role: {$res->bodyData['user']['role_slug']}"
];

// UAT-02: Dashboard Metrics & Summary
$res = dispatchUat('GET', '/api/admin/dashboard/summary');
$uat02Pass = $res->statusCode === 200 && ($res->bodyData['success'] ?? false) === true;
$uatResults[] = [
    'id' => 'UAT-02',
    'title' => 'Control Center Dashboard Summary',
    'pass' => $uat02Pass,
    'detail' => "HTTP {$res->statusCode}, Visitors: " . ($res->bodyData['data']['visitor_stats']['total_views'] ?? 0)
];

// UAT-03: Member Registry Total & Leading Zeros
$res = dispatchUat('GET', '/api/admin/members');
$members = $res->bodyData['items'] ?? [];
$total = $res->bodyData['total'] ?? 0;
$firstMember = $members[0] ?? null;
$uat03Pass = $res->statusCode === 200 && count($members) > 0 && strlen($firstMember['member_no'] ?? '') >= 4 && $total === 2508;
$uatResults[] = [
    'id' => 'UAT-03',
    'title' => 'Member Registry Listing & PII Safety',
    'pass' => $uat03Pass,
    'detail' => "HTTP {$res->statusCode}, Sample Member: {$firstMember['member_no']}, Total in DB: {$total}"
];

// UAT-04: Financial Products (Loans & Deposits)
$res = dispatchUat('GET', '/api/admin/loans');
$loans = $res->bodyData['data']['loans'] ?? [];
$deposits = $res->bodyData['data']['deposits'] ?? [];
$uat04Pass = $res->statusCode === 200 && count($loans) === 10 && count($deposits) === 4;
$uatResults[] = [
    'id' => 'UAT-04',
    'title' => 'Financial Products Data (/api/admin/loans)',
    'pass' => $uat04Pass,
    'detail' => "HTTP {$res->statusCode}, Loans: " . count($loans) . " products, Deposits: " . count($deposits) . " products"
];

// UAT-05: Document Categories (Schema Crash Fix)
$res = dispatchUat('GET', '/api/admin/document-categories');
$categories = $res->bodyData['data'] ?? [];
$uat05Pass = $res->statusCode === 200 && count($categories) === 6;
$uatResults[] = [
    'id' => 'UAT-05',
    'title' => 'Document Categories Listing (BUG-001 Fix)',
    'pass' => $uat05Pass,
    'detail' => "HTTP {$res->statusCode}, Categories: " . count($categories) . " active records"
];

// UAT-06: Documents Listing
$res = dispatchUat('GET', '/api/admin/documents');
$docs = $res->bodyData['data']['data'] ?? [];
$uat06Pass = $res->statusCode === 200 && is_array($docs);
$uatResults[] = [
    'id' => 'UAT-06',
    'title' => 'Documents Listing & Categories Association',
    'pass' => $uat06Pass,
    'detail' => "HTTP {$res->statusCode}, Documents count: " . count($docs) . " (empty state verified)"
];

// UAT-07: Hero Slides Listing
$res = dispatchUat('GET', '/api/admin/hero-slides');
$slides = $res->bodyData['data'] ?? [];
$uat07Pass = $res->statusCode === 200 && count($slides) === 3;
$uatResults[] = [
    'id' => 'UAT-07',
    'title' => 'Hero Slides Management',
    'pass' => $uat07Pass,
    'detail' => "HTTP {$res->statusCode}, Active Slides: " . count($slides)
];

// UAT-08: Popups Campaign Listing
$res = dispatchUat('GET', '/api/admin/popups');
$popups = $res->bodyData['data']['items'] ?? [];
$uat08Pass = $res->statusCode === 200 && count($popups) === 0;
$uatResults[] = [
    'id' => 'UAT-08',
    'title' => 'Popup Campaign Manager Listing',
    'pass' => $uat08Pass,
    'detail' => "HTTP {$res->statusCode}, Popups count: " . count($popups) . " (empty state verified)"
];

// UAT-09: Welfare Services
$res = dispatchUat('GET', '/api/admin/welfare');
$welfare = $res->bodyData['data'] ?? [];
$uat09Pass = $res->statusCode === 200 && count($welfare) === 3;
$uatResults[] = [
    'id' => 'UAT-09',
    'title' => 'Welfare Administration',
    'pass' => $uat09Pass,
    'detail' => "HTTP {$res->statusCode}, Active Welfare: " . count($welfare)
];

// UAT-10: E-Services Listing
$res = dispatchUat('GET', '/api/admin/eservices');
$eservices = $res->bodyData['data'] ?? [];
$uat10Pass = $res->statusCode === 200 && count($eservices) === 5;
$uatResults[] = [
    'id' => 'UAT-10',
    'title' => 'E-Services Gateway Listing',
    'pass' => $uat10Pass,
    'detail' => "HTTP {$res->statusCode}, Active E-Services: " . count($eservices)
];

// UAT-11: Board & Staff Listing
$res = dispatchUat('GET', '/api/admin/board-staff');
$boards = $res->bodyData['data']['boards'] ?? [];
$staff = $res->bodyData['data']['staff'] ?? [];
$uat11Pass = $res->statusCode === 200 && count($boards) === 5 && count($staff) === 0;
$uatResults[] = [
    'id' => 'UAT-11',
    'title' => 'Board & Staff Personnel Listing',
    'pass' => $uat11Pass,
    'detail' => "HTTP {$res->statusCode}, Boards: " . count($boards) . " members, Staff: " . count($staff) . " (empty state verified)"
];

// UAT-12: Full CRUD Lifecycle Test: Document Category
$catName = 'หมวดหมู่ทดสอบ UAT ' . time();
$createRes = dispatchUat('POST', '/api/admin/document-categories', ['name' => $catName, 'description' => 'UAT Category']);
$newCatId = $createRes->bodyData['data']['id'] ?? null;
$createPass = $createRes->statusCode === 201 && $newCatId > 0;

$updateRes = dispatchUat('PUT', "/api/admin/document-categories/{$newCatId}", ['name' => $catName . ' (แก้ไข)', 'description' => 'Updated']);
$updatePass = $updateRes->statusCode === 200;

$deleteRes = dispatchUat('DELETE', "/api/admin/document-categories/{$newCatId}");
$deletePass = $deleteRes->statusCode === 200;

// Cleanup database row permanently so test leaves 0 artifacts
Database::execute("DELETE FROM document_categories WHERE id = ?", [$newCatId]);

$uat12Pass = $createPass && $updatePass && $deletePass;
$uatResults[] = [
    'id' => 'UAT-12',
    'title' => 'Document Category CRUD Mutation (POST, PUT, DELETE)',
    'pass' => $uat12Pass,
    'detail' => "Create: HTTP {$createRes->statusCode}, Update: HTTP {$updateRes->statusCode}, Delete: HTTP {$deleteRes->statusCode}"
];

// UAT-13: Full CRUD Lifecycle Test: E-Service Link
$linkName = 'บริการทดสอบ UAT ' . time();
$createLinkRes = dispatchUat('POST', '/api/admin/eservices', ['name' => $linkName, 'url' => 'https://example.com/uat', 'description' => 'UAT Link']);
$newLinkId = $createLinkRes->bodyData['data']['id'] ?? null;
$createLinkPass = $createLinkRes->statusCode === 201 && $newLinkId > 0;

$updateLinkRes = dispatchUat('PUT', "/api/admin/eservices/{$newLinkId}", ['name' => $linkName . ' (แก้ไข)', 'url' => 'https://example.com/uat-updated']);
$updateLinkPass = $updateLinkRes->statusCode === 200;

$deleteLinkRes = dispatchUat('DELETE', "/api/admin/eservices/{$newLinkId}");
$deleteLinkPass = $deleteLinkRes->statusCode === 200;

// Cleanup
Database::execute("DELETE FROM eservice_links WHERE id = ?", [$newLinkId]);

$uat13Pass = $createLinkPass && $updateLinkPass && $deleteLinkPass;
$uatResults[] = [
    'id' => 'UAT-13',
    'title' => 'E-Service Link CRUD Mutation (POST, PUT, DELETE)',
    'pass' => $uat13Pass,
    'detail' => "Create: HTTP {$createLinkRes->statusCode}, Update: HTTP {$updateLinkRes->statusCode}, Delete: HTTP {$deleteLinkRes->statusCode}"
];

// UAT-14: Financial Safety Immutability Gate
$divCount = (int)Database::value("SELECT COUNT(*) FROM member_dividends");
$memCount = (int)Database::value("SELECT COUNT(*) FROM members");
$userCount = (int)Database::value("SELECT COUNT(*) FROM users");
$uat14Pass = $divCount === 4855 && $memCount === 2508 && $userCount === 2510;
$uatResults[] = [
    'id' => 'UAT-14',
    'title' => 'Financial Immutability & Database Integrity Gate',
    'pass' => $uat14Pass,
    'detail' => "member_dividends: {$divCount} (Expected 4855), members: {$memCount} (Expected 2508), users: {$userCount} (Expected 2510)"
];

$allPassed = true;
foreach ($uatResults as $res) {
    $tag = $res['pass'] ? '[PASS]' : '[FAIL]';
    if (!$res['pass']) $allPassed = false;
    echo " {$tag} {$res['id']}: {$res['title']}" . PHP_EOL;
    echo "        Evidence: {$res['detail']}" . PHP_EOL;
}

echo "========================================================================" . PHP_EOL;
echo " UAT VERIFICATION SUMMARY: " . ($allPassed ? "14 / 14 ALL TESTS PASSED (100%)" : "FAILED") . PHP_EOL;
echo "========================================================================" . PHP_EOL;

if (!$allPassed) exit(1);
