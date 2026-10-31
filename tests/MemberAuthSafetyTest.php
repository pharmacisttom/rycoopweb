<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\{Auth, Csrf, Database, Request, Response, Router, Session};
use App\Middlewares\{CsrfMiddleware, MemberMiddleware, RoleMiddleware, AuthMiddleware};
use App\Services\{MemberPortalService, AuditService};
use App\Controllers\Member\MemberPortalController;
use App\Controllers\Api\SpaApiController;
use App\Controllers\Member\DividendController;

final class TestResponse extends Response
{
    public int $status = 200;
    public array $body = [];
    public ?string $redirectUrl = null;

    public function json(array $data, int $status = 200): void
    {
        $this->body = $data;
        $this->status = $status;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->redirectUrl = $url;
        $this->status = $status;
    }
}

$sessionDirectory = dirname(__DIR__) . '/storage/tmp/member-auth-test-sessions';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDirectory);
putenv('SESSION_SECURE=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/member/dashboard',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_USER_AGENT' => 'SafetyTest/1.0',
];
$_GET = $_POST = $_FILES = [];

// Start session before any tests
Session::start();

// Isolated SQLite in-memory database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
$pdo->sqliteCreateFunction('YEAR', fn($d) => date('Y', strtotime((string)$d)));
(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT,
        email TEXT,
        status TEXT,
        password TEXT,
        session_version INTEGER DEFAULT 1,
        created_at TEXT,
        updated_at TEXT,
        deleted_at TEXT
    );

    CREATE TABLE roles (
        id INTEGER PRIMARY KEY,
        name TEXT,
        slug TEXT
    );

    CREATE TABLE user_roles (
        user_id INTEGER,
        role_id INTEGER
    );

    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY,
        module TEXT,
        action TEXT
    );

    CREATE TABLE role_permissions (
        role_id INTEGER,
        permission_id INTEGER
    );

    CREATE TABLE members (
        id INTEGER PRIMARY KEY,
        user_id INTEGER,
        member_no TEXT,
        prefix TEXT,
        first_name TEXT,
        last_name TEXT,
        department TEXT,
        position TEXT,
        id_card TEXT,
        phone TEXT,
        email TEXT,
        address TEXT,
        status TEXT,
        created_at TEXT,
        updated_at TEXT
    );

    CREATE TABLE member_shares (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        share_count INTEGER,
        total_shares INTEGER DEFAULT 0,
        total_amount REAL,
        monthly_share REAL
    );

    CREATE TABLE deposit_accounts (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        account_no TEXT,
        account_name TEXT,
        account_type TEXT,
        balance REAL,
        accrued_interest REAL DEFAULT 0.0,
        status TEXT
    );

    CREATE TABLE deposit_transactions (
        id INTEGER PRIMARY KEY,
        account_id INTEGER,
        member_id INTEGER,
        amount REAL,
        balance_after REAL,
        transaction_type TEXT,
        transaction_date TEXT
    );

    CREATE TABLE loan_contracts (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        contract_no TEXT,
        loan_type TEXT,
        loan_name TEXT DEFAULT '',
        principal_amount REAL,
        principal_balance REAL,
        interest_rate REAL,
        monthly_installment REAL,
        status TEXT
    );

    CREATE TABLE loan_amortization_schedules (
        id INTEGER PRIMARY KEY,
        loan_id INTEGER,
        period_no INTEGER,
        due_date TEXT,
        principal_amount REAL,
        interest_amount REAL,
        total_amount REAL,
        balance REAL,
        status TEXT
    );

    CREATE TABLE receipts (
        id INTEGER PRIMARY KEY,
        receipt_no TEXT,
        member_id INTEGER,
        amount REAL,
        total_amount REAL DEFAULT 0.0,
        billing_year INTEGER,
        billing_month INTEGER,
        loan_interest REAL,
        created_at TEXT
    );

    CREATE TABLE beneficiaries (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        name TEXT,
        relationship TEXT,
        id_card TEXT,
        percentage REAL,
        priority_order INTEGER DEFAULT 1,
        status TEXT DEFAULT 'active'
    );

    CREATE TABLE notifications (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        title TEXT,
        message TEXT,
        is_read INTEGER DEFAULT 0,
        created_at TEXT
    );

    CREATE TABLE online_requests (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        request_type TEXT,
        status TEXT,
        created_at TEXT
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY,
        member_id INTEGER,
        year INTEGER,
        details_json TEXT,
        imported_at TEXT
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        module TEXT,
        action TEXT,
        record_id TEXT,
        old_values TEXT,
        new_values TEXT,
        ip_address TEXT,
        user_agent TEXT,
        created_at TEXT
    );

    CREATE TABLE login_activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        ip_address TEXT,
        user_agent TEXT,
        status TEXT,
        created_at TEXT
    );

    CREATE TABLE line_connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        line_user_id TEXT,
        created_at TEXT
    );

    CREATE TABLE share_change_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER,
        new_monthly_amount REAL,
        status TEXT,
        created_at TEXT
    );
");

// Populate roles
$pdo->exec("
    INSERT INTO roles (id, name, slug) VALUES 
    (1, 'Member', 'member'),
    (2, 'Staff', 'staff'),
    (3, 'Super Admin', 'super_admin');
");

// Users:
// 10: Active member user (linked to member 101)
// 11: Suspended member user (linked to member 102)
// 12: Member user with unlinked/missing member record
// 13: Member user linked to suspended member record (member 103)
// 14: Member user linked to resigned member record (member 104)
// 20: Another active member user (Member B, linked to member 201)
// 30: Staff user (role: staff)
// 31: Super admin user (role: super_admin)
$pdo->exec("
    INSERT INTO users (id, name, email, status, session_version) VALUES
    (10, 'Active Member A', 'memberA@example.com', 'active', 1),
    (11, 'Suspended User', 'suspended_user@example.com', 'suspended', 1),
    (12, 'Unlinked User', 'unlinked@example.com', 'active', 1),
    (13, 'Member Suspended Status', 'member_suspended@example.com', 'active', 1),
    (14, 'Member Resigned Status', 'member_resigned@example.com', 'active', 1),
    (20, 'Active Member B', 'memberB@example.com', 'active', 1),
    (30, 'Staff Person', 'staff@example.com', 'active', 1),
    (31, 'Super Admin', 'admin@example.com', 'active', 1);

    INSERT INTO user_roles (user_id, role_id) VALUES
    (10, 1),
    (11, 1),
    (12, 1),
    (13, 1),
    (14, 1),
    (20, 1),
    (30, 2),
    (31, 3);
");

// Members:
// 101: linked to user 10 (status: active)
// 102: linked to user 11 (status: active, but user suspended)
// 103: linked to user 13 (status: suspended)
// 104: linked to user 14 (status: resigned)
// 201: linked to user 20 (status: active)
$pdo->exec("
    INSERT INTO members (id, user_id, member_no, prefix, first_name, last_name, id_card, phone, email, address, status, created_at, updated_at) VALUES
    (101, 10, 'MEM-00101', 'นาย', 'สมชาย', 'ใจดี', '1234567890123', '0812345678', 'memberA@example.com', '123 ระยอง', 'active', datetime('now'), datetime('now')),
    (102, 11, 'MEM-00102', 'นาง', 'สมหญิง', 'รักดี', '1234567890124', '0812345679', 'suspended_user@example.com', '124 ระยอง', 'active', datetime('now'), datetime('now')),
    (103, 13, 'MEM-00103', 'นาย', 'สมศักดิ์', 'มีสุข', '1234567890125', '0812345680', 'member_suspended@example.com', '125 ระยอง', 'suspended', datetime('now'), datetime('now')),
    (104, 14, 'MEM-00104', 'นาง', 'สมพร', 'เจริญ', '1234567890126', '0812345681', 'member_resigned@example.com', '126 ระยอง', 'resigned', datetime('now'), datetime('now')),
    (201, 20, 'MEM-00201', 'นาย', 'วิชัย', 'มั่นคง', '9876543210987', '0899999999', 'memberB@example.com', '999 กรุงเทพ', 'active', datetime('now'), datetime('now'));
");

// Populate Member A (101) data vs Member B (201) data
$pdo->exec("
    INSERT INTO member_shares (member_id, share_count, total_amount, monthly_share) VALUES
    (101, 500, 50000.0, 1000.0),
    (201, 9999, 999900.0, 5000.0);

    INSERT INTO deposit_accounts (id, member_id, account_no, account_name, account_type, balance, accrued_interest, status) VALUES
    (1, 101, 'DEP-A-01', 'เงินฝากสมชาย', 'saving', 25000.0, 150.0, 'active'),
    (2, 201, 'DEP-B-01', 'เงินฝากวิชัย', 'saving', 888888.0, 5500.0, 'active');

    INSERT INTO loan_contracts (id, member_id, contract_no, loan_type, principal_amount, principal_balance, interest_rate, monthly_installment, status) VALUES
    (1, 101, 'LN-A-01', 'ฉุกเฉิน', 30000.0, 15000.0, 6.5, 3000.0, 'active'),
    (2, 201, 'LN-B-01', 'พิเศษ', 2000000.0, 1800000.0, 4.5, 25000.0, 'active');

    INSERT INTO receipts (receipt_no, member_id, amount, billing_year, billing_month, loan_interest, created_at) VALUES
    ('RCP-A-001', 101, 3500.0, 2026, 10, 500.0, datetime('now')),
    ('RCP-B-001', 201, 30000.0, 2026, 10, 5000.0, datetime('now'));

    INSERT INTO notifications (member_id, title, message, is_read, created_at) VALUES
    (101, 'แจ้งเตือนสมาชิก A', 'ข้อความเฉพาะสมาชิก A', 0, datetime('now')),
    (201, 'แจ้งเตือนลับสมาชิก B', 'ข้อความลับเฉพาะสมาชิก B', 0, datetime('now'));

    INSERT INTO beneficiaries (member_id, name, relationship, id_card, percentage, priority_order, status) VALUES
    (101, 'ทายาท A', 'บุตร', '1111111111111', 100.0, 1, 'active'),
    (201, 'ทายาท B', 'คู่สมรส', '2222222222222', 100.0, 1, 'active');

    INSERT INTO member_dividends (member_id, year, details_json, imported_at) VALUES
    (101, 2025, '{\"dividend_shares\":2500,\"patronage_refund\":500,\"total_payout\":3000}', datetime('now')),
    (201, 2025, '{\"dividend_shares\":90000,\"patronage_refund\":15000,\"total_payout\":105000}', datetime('now'));
");

$testCount = 0;
$failCount = 0;
$testResults = [];

function assertCheck(bool $condition, string $description): void
{
    global $testCount, $failCount, $testResults;
    $testCount++;
    if ($condition) {
        $testResults[] = "PASS [{$testCount}]: {$description}";
    } else {
        $failCount++;
        $testResults[] = "FAIL [{$testCount}]: {$description}";
    }
}

function loginUser(int $userId, string $roleSlug): void
{
    Auth::logout();
    $rawUser = Database::first("SELECT u.*, r.slug as role_slug, r.name as role_name FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id WHERE u.id = ?", [$userId]);
    if ($rawUser) {
        Auth::login($rawUser, true);
    }
}

// =========================================================================
// TEST GROUP 1: Middleware & Authorization Gates
// =========================================================================

// Test 1: Guest is denied by MemberMiddleware
Auth::logout();
$req = new Request();
$res = new TestResponse();
$middleware = new MemberMiddleware();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 401 && ($res->body['code'] ?? '') === 'UNAUTHORIZED', 'Guest blocked with 401 UNAUTHORIZED on MemberMiddleware');

// Test 2: Staff role is denied by MemberMiddleware
loginUser(30, 'staff');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 403 && ($res->body['code'] ?? '') === 'FORBIDDEN', 'Staff role blocked with 403 FORBIDDEN on MemberMiddleware');

// Test 3: Super Admin is denied by MemberMiddleware (Member portal is strictly for member role)
loginUser(31, 'super_admin');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 403 && ($res->body['code'] ?? '') === 'FORBIDDEN', 'Super Admin blocked with 403 FORBIDDEN on MemberMiddleware');

// Test 4: Suspended User Account (users.status = 'suspended') is blocked
loginUser(11, 'member');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 403 && ($res->body['code'] ?? '') === 'ACCOUNT_SUSPENDED', 'Suspended user account blocked with 403 ACCOUNT_SUSPENDED');

// Test 5: Unlinked Member Account (User has role 'member' but 0 rows in members table)
loginUser(12, 'member');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 403 && ($res->body['code'] ?? '') === 'MEMBER_NOT_LINKED', 'Unlinked user (missing member record) blocked with 403 MEMBER_NOT_LINKED');

// Test 6: Member with members.status = 'suspended' is blocked
loginUser(13, 'member');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 403 && ($res->body['code'] ?? '') === 'MEMBER_NOT_LINKED', 'Member with suspended status blocked with 403 MEMBER_NOT_LINKED');

// Test 7: Member with members.status = 'resigned' is blocked
loginUser(14, 'member');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === false && $res->status === 403 && ($res->body['code'] ?? '') === 'MEMBER_NOT_LINKED', 'Member with resigned status blocked with 403 MEMBER_NOT_LINKED');

// Test 8: Active Member A passes MemberMiddleware
loginUser(10, 'member');
$res = new TestResponse();
$passed = $middleware->handle($req, $res);
assertCheck($passed === true && MemberPortalService::getCurrentMember() !== null && (int)MemberPortalService::getCurrentMember()['id'] === 101, 'Active Member A passes MemberMiddleware and stashes current member (ID 101)');

// =========================================================================
// TEST GROUP 2: In-Depth Controller Guards (Even without Middleware)
// =========================================================================

// Test 9: MemberPortalController halts if unlinked user calls dashboard directly
loginUser(12, 'member'); // Unlinked user
$controller = new MemberPortalController($req, $res);
$resDashboard = new TestResponse();
// Controller initialized with memberId = 0
$controllerRefl = new ReflectionObject($controller);
$propMemberId = $controllerRefl->getProperty('memberId');
$propMemberId->setAccessible(true);
assertCheck($propMemberId->getValue($controller) === 0, 'MemberPortalController initializes memberId to 0 for unlinked user');

$methodDashboard = $controllerRefl->getMethod('dashboard');
$controllerResProp = $controllerRefl->getProperty('response');
$controllerResProp->setAccessible(true);
$controllerResProp->setValue($controller, $resDashboard);
$methodDashboard->invoke($controller);
assertCheck($resDashboard->status === 403 && ($resDashboard->body['code'] ?? '') === 'MEMBER_NOT_LINKED', 'MemberPortalController::dashboard halts execution immediately when member is unlinked');

// Test 10: SpaApiController guards getAuthenticatedMember against missing/unlinked/suspended accounts
loginUser(13, 'member'); // Suspended member
$resSpa = new TestResponse();
$spaApi = new SpaApiController($req, $resSpa);
$spaRefl = new ReflectionObject($spaApi);
$methodGetAuthMember = $spaRefl->getMethod('getAuthenticatedMember');
$methodGetAuthMember->setAccessible(true);
$authMemberResult = $methodGetAuthMember->invoke($spaApi);
assertCheck($authMemberResult === null && $resSpa->status === 404 && ($resSpa->body['code'] ?? '') === 'MEMBER_NOT_LINKED', 'SpaApiController::getAuthenticatedMember rejects suspended member with 404 MEMBER_NOT_LINKED');

// Test 11: DividendController index() rejects unlinked/suspended member
$resDiv = new TestResponse();
$dividendCtrl = new DividendController($req, $resDiv);
$dividendCtrl->index();
assertCheck($resDiv->status === 404 && ($resDiv->body['code'] ?? '') === 'MEMBER_NOT_LINKED', 'DividendController rejects suspended member with 404 MEMBER_NOT_LINKED');

// =========================================================================
// TEST GROUP 3: CSRF Protection on Member Mutations
// =========================================================================

// Test 12: Route registration for POST /member/notifications/read-all enforces CsrfMiddleware
$router = new Router();
require __DIR__ . '/../config/routes.php';
$routes = (new ReflectionProperty(Router::class, 'routes'))->getValue($router);

$readAllRouteFound = false;
$hasCsrf = false;
foreach ($routes as $route) {
    if ($route['path'] === '/member/notifications/read-all' && $route['method'] === 'POST') {
        $readAllRouteFound = true;
        foreach ($route['middlewares'] as $mw) {
            if ($mw === CsrfMiddleware::class || $mw instanceof CsrfMiddleware) {
                $hasCsrf = true;
                break;
            }
        }
    }
}
assertCheck($readAllRouteFound && $hasCsrf, 'POST /member/notifications/read-all is strictly protected by CsrfMiddleware');

// Test 13: Executing CSRF check without valid token fails
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
$resCsrf = new TestResponse();
$csrfMiddleware = new CsrfMiddleware();
$csrfPassed = $csrfMiddleware->handle(new Request(), $resCsrf);
assertCheck($csrfPassed === false && $resCsrf->status === 419, 'CsrfMiddleware blocks request missing CSRF token with 419');

// Test 14: Executing CSRF check with valid token succeeds
$validToken = Csrf::token();
$_POST['csrf_token'] = $validToken;
$resCsrfValid = new TestResponse();
$csrfPassedValid = $csrfMiddleware->handle(new Request(), $resCsrfValid);
assertCheck($csrfPassedValid === true, 'CsrfMiddleware allows request with valid CSRF token');

// =========================================================================
// TEST GROUP 4: IDOR Prevention & Strict Member Isolation
// =========================================================================

// Member A (User 10, Member 101) logs in
loginUser(10, 'member');

// Test 15: Member A fetches dashboard summary -> contains ONLY Member A data
$summaryA = MemberPortalService::getDashboardSummary(101);
assertCheck(
    (float)$summaryA['shares']['total_amount'] === 50000.0 &&
    (float)$summaryA['deposits']['total_balance'] === 25000.0 &&
    (float)$summaryA['loans']['total_principal'] === 15000.0,
    'Member A dashboard summary isolated to Member A financial figures'
);

// Test 16: Member A queries notifications -> only receives Member A notifications (not Member B)
$notifsA = MemberPortalService::getNotifications(101);
$allNotifTitles = array_column($notifsA['list'], 'title');
assertCheck(
    in_array('แจ้งเตือนสมาชิก A', $allNotifTitles, true) &&
    !in_array('แจ้งเตือนลับสมาชิก B', $allNotifTitles, true),
    'Member A notifications only contain Member A messages and do not leak Member B notifications'
);

// Test 17: Member A cannot view or print Member B receipt
$receiptB = \App\Services\ReceiptService::getReceiptDetails('RCP-B-001');
assertCheck(
    $receiptB !== null && (int)$receiptB['member_id'] === 201,
    'Member B receipt exists in system'
);

// Now test MemberPortalController::printReceipt enforcement
$_SERVER['REQUEST_METHOD'] = 'GET';
$ctrlA = new MemberPortalController(new Request(), new TestResponse());
// Call printReceipt for RCP-B-001 as Member A
$_SESSION['__flash'] = [];
$ctrlA->printReceipt('RCP-B-001');
$flashError = Session::getFlash('error') ?? '';
assertCheck(
    str_contains($flashError, 'ไม่มีสิทธิ์เข้าถึง'),
    'Member A blocked from printing Member B receipt (IDOR prevented with unauthorized flash message)'
);

// Test 18: Member A can print Member A receipt
$reachedView = false;
try {
    $ctrlA->printReceipt('RCP-A-001');
    $reachedView = true;
} catch (\RuntimeException $e) {
    $reachedView = str_contains($e->getMessage(), 'View file not found');
}
assertCheck(
    $reachedView,
    'Member A successfully accesses own receipt RCP-A-001 without authorization error (passes IDOR gate to render)'
);

// =========================================================================
// TEST GROUP 5: Profile Validation & PII Masking in Audit Logs
// =========================================================================

// Test 19: Profile update with invalid phone number format is rejected
loginUser(10, 'member');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'phone' => '12345', // Invalid: only 5 digits
    'email' => 'valid@example.com',
    'address' => '123 Valid Address'
];
$ctrlProfile = new MemberPortalController(new Request(), new TestResponse());
$_SESSION['__flash'] = [];
$ctrlProfile->updateProfile();
$phoneFlashError = Session::getFlash('error') ?? '';
assertCheck(str_contains($phoneFlashError, 'หมายเลขโทรศัพท์ไม่ถูกต้อง'), 'Profile update rejects invalid phone length (< 9 digits)');

// Test 20: Profile update with invalid email format is rejected
$_POST = [
    'phone' => '0812345678',
    'email' => 'invalid-email-no-domain',
    'address' => '123 Valid Address'
];
$ctrlProfile = new MemberPortalController(new Request(), new TestResponse());
$_SESSION['__flash'] = [];
$ctrlProfile->updateProfile();
$emailFlashError = Session::getFlash('error') ?? '';
assertCheck(str_contains($emailFlashError, 'รูปแบบอีเมลไม่ถูกต้อง'), 'Profile update rejects malformed email address');

// Test 21: Valid profile update saves clean data AND masks PII in audit_logs
$cleanPhone = '0891234567';
$cleanEmail = 'somchai.jaidee@example.com';
$cleanAddress = '888/99 ถ.สุขุมวิท ต.เนินพระ อ.เมือง จ.ระยอง';
$_POST = [
    'phone' => $cleanPhone,
    'email' => $cleanEmail,
    'address' => $cleanAddress
];
$ctrlProfile = new MemberPortalController(new Request(), new TestResponse());
$_SESSION['__flash'] = [];
$ctrlProfile->updateProfile();

// Verify database was updated
$updatedMember = Database::first("SELECT phone, email, address FROM members WHERE id = 101");
assertCheck(
    $updatedMember['phone'] === $cleanPhone &&
    $updatedMember['email'] === $cleanEmail &&
    $updatedMember['address'] === $cleanAddress,
    'Valid profile update correctly updates members database record'
);

// Verify audit log exists and phone/email are MASKED (no raw PII leak)
$auditLog = Database::first("SELECT new_values FROM audit_logs WHERE module = 'member' AND action = 'update_profile' ORDER BY id DESC LIMIT 1");
$auditPayload = json_decode($auditLog['new_values'] ?? '{}', true);

$phoneMasked = isset($auditPayload['phone']) && !str_contains($auditPayload['phone'], '0891234567') && str_contains($auditPayload['phone'], 'XXXX');
$emailMasked = isset($auditPayload['email']) && !str_contains($auditPayload['email'], 'somchai.jaidee@example.com') && str_contains($auditPayload['email'], '***@');

assertCheck($phoneMasked && $emailMasked, 'Audit log masks phone (089XXXX567) and email (so***@example.com) to prevent sensitive PII leakage');

// =========================================================================
// TEST GROUP 6: Session Invalidation & Revocation Safety
// =========================================================================

// Test 22: revokeSessions() calls Auth::revokeAllSessions and increments session_version
loginUser(10, 'member');
$initialVersion = (int)Database::value("SELECT session_version FROM users WHERE id = 10");
assertCheck($initialVersion === 1, 'Initial session_version for User 10 is 1');

$ctrlRevoke = new MemberPortalController(new Request(), new TestResponse());
$ctrlRevoke->revokeSessions();

$newVersion = (int)Database::value("SELECT session_version FROM users WHERE id = 10");
assertCheck($newVersion === 2, 'revokeSessions() successfully incremented session_version from 1 to 2 in users table');

// Test 23: Subsequent Auth::check() with old session_version (1) fails/logs out user
Session::set('session_version', 1); // Stale session version
$authCheckResult = Auth::check();
assertCheck($authCheckResult === false, 'Auth::check() invalidates stale session with outdated session_version');

// =========================================================================
// TEST GROUP 7: Super Admin and Staff Route Safety Regression
// =========================================================================

// Test 24: Super admin route /api/admin/staff/ remains intact and requires super_admin
$staffRouteFound = false;
foreach ($routes as $route) {
    if ($route['path'] === '/api/admin/staff/' && $route['method'] === 'GET') {
        $staffRouteFound = true;
        break;
    }
}
assertCheck($staffRouteFound, 'Staff list endpoint /api/admin/staff/ remains registered and intact');

// Test 25: MemberMiddleware is applied to member routes and does NOT interfere with /admin or /staff routes
$adminHasMemberMw = false;
foreach ($routes as $route) {
    if (str_starts_with($route['path'], '/admin') || str_starts_with($route['path'], '/staff')) {
        foreach ($route['middlewares'] as $mw) {
            if ($mw === MemberMiddleware::class || $mw instanceof MemberMiddleware) {
                $adminHasMemberMw = true;
                break 2;
            }
        }
    }
}
assertCheck(!$adminHasMemberMw, 'MemberMiddleware is strictly scoped to member routes and does not affect admin or staff routes');

// Flush buffered output and print clean test output
ob_end_clean();

foreach ($testResults as $line) {
    echo $line . "\n";
}

echo "=========================================================================\n";
if ($failCount === 0) {
    echo "Member Authorization & Session Safety Suite: All {$testCount} checks PASSED!\n";
    exit(0);
} else {
    echo "Member Authorization & Session Safety Suite: {$failCount} / {$testCount} checks FAILED!\n";
    exit(1);
}
