<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\AuditLogController;
use App\Core\{Auth, Database, Request, Response, Session};
use App\Services\{AdminControlCenterService, DualControlSecurityService, MemberPiiMaskingService, SecurityAuditService, TwoFactorService};

final class TestAuditResponseCapture extends Response
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

$sessionDir = sys_get_temp_dir() . '/rycoop-uat-security-audit-viewer-test-sessions-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
putenv('SESSION_SECURE=false');
putenv('TWO_FACTOR_ENCRYPTION_KEY=1234567890123456789012345678901234');
putenv('FEATURE_MEMBER_PORTAL=true');
putenv('FEATURE_MEMBER_LOGIN=true');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/admin/audit-logs',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'REMOTE_ADDR' => '192.168.1.100',
    'HTTP_USER_AGENT' => 'SecurityAuditViewerTestRunner/1.0',
    'HTTP_X_CORRELATION_ID' => 'test-audit-viewer-uuid-0001',
];
$_GET = $_POST = $_FILES = [];

Session::start();

// In-memory SQLite database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('UTC_TIMESTAMP', fn() => gmdate('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
$pdo->sqliteCreateFunction('CONCAT', fn(...$args) => implode('', $args));
$pdo->sqliteCreateFunction('REPLACE', fn($str, $from, $to) => str_replace($from, $to, (string)$str));

(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

// Initialize schema
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        must_change_password INTEGER NOT NULL DEFAULT 0,
        session_version INTEGER NOT NULL DEFAULT 1,
        two_factor_enabled INTEGER NOT NULL DEFAULT 0,
        two_factor_secret TEXT NULL,
        two_factor_confirmed_at TEXT NULL,
        two_factor_last_used_slice INTEGER NULL,
        last_login_at TEXT NULL,
        last_login_ip TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        deleted_at TEXT NULL
    );

    CREATE TABLE roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE user_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role_id INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        module TEXT NOT NULL,
        action TEXT NOT NULL,
        description TEXT NULL
    );

    CREATE TABLE role_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL
    );

    CREATE TABLE members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT NOT NULL UNIQUE,
        user_id INTEGER NULL,
        member_no TEXT NOT NULL UNIQUE,
        id_card TEXT NOT NULL,
        prefix TEXT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'inactive',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE rate_limit_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rate_key TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 1,
        window_started_at TEXT NOT NULL DEFAULT (datetime('now')),
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        module TEXT NOT NULL,
        action TEXT NOT NULL,
        record_id TEXT NULL,
        old_values TEXT NULL,
        new_values TEXT NULL,
        ip_address TEXT NOT NULL,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE security_audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id TEXT NOT NULL,
        timestamp_utc TEXT NOT NULL,
        actor_user_id INTEGER NULL,
        actor_type TEXT NOT NULL,
        role TEXT NOT NULL,
        event_type TEXT NOT NULL,
        target_resource TEXT NULL,
        result TEXT NOT NULL,
        correlation_id TEXT NOT NULL,
        session_fingerprint TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        user_agent TEXT NULL,
        metadata TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
");

// Seed Roles
$pdo->exec("
    INSERT INTO roles (id, name, slug) VALUES
        (1, 'Super Administrator', 'super_admin'),
        (2, 'Auditor', 'auditor'),
        (3, 'IT Admin', 'it_admin'),
        (4, 'Member', 'member');
");

// Seed Permissions for auditor
$pdo->exec("
    INSERT INTO permissions (id, name, module, action) VALUES
        (1, 'View Audit', 'audit', 'view'),
        (2, 'Export Audit', 'audit', 'export'),
        (3, 'View Security', 'security', 'view');

    INSERT INTO role_permissions (role_id, permission_id) VALUES
        (2, 1), (2, 2), (2, 3);
");

// Seed Users
// 1. Super Admin with 2FA
$adminSecret = TwoFactorService::generateSecret();
$adminEncSecret = TwoFactorService::encryptSecret($adminSecret);
$pdo->prepare("
    INSERT INTO users (id, uuid, name, username, email, password, status, two_factor_enabled, two_factor_secret, two_factor_confirmed_at)
    VALUES (1, 'u-admin-1', 'นายศักดิ์ชัย แอดมิน', 'superadmin', 'admin@rayongcoop.com', 'hashed_pw', 'active', 1, ?, datetime('now'))
")->execute([$adminEncSecret]);
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);");

// 2. Member User
$pdo->exec("
    INSERT INTO users (id, uuid, name, username, email, password, status)
    VALUES (2, 'u-member-1', 'นายสมคิด ยอดนิยม', '3100100200300', 'member1@rayongcoop.com', 'hashed_pw', 'active');
    INSERT INTO user_roles (user_id, role_id) VALUES (2, 4);
    INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
    VALUES (1, 'm-0001', 2, '00042', '3100100200300', 'นาย', 'สมคิด', 'ยอดนิยม', 'active');
");

// Seed security_audit_logs data for tests
$nowUtc = gmdate('Y-m-d H:i:s');
$pdo->exec("
    INSERT INTO security_audit_logs (id, event_id, timestamp_utc, actor_user_id, actor_type, role, event_type, target_resource, result, correlation_id, session_fingerprint, ip_address, user_agent, metadata)
    VALUES
        (1, 'evt-001', '{$nowUtc}', 2, 'member', 'member', 'MEMBER_LOGIN_SUCCESS', 'member:00042', 'success', 'cor-m-01', 'fp-01', '192.168.1.10', 'Mozilla/5.0', '{\"auth_method\":\"citizen_id\"}'),
        (2, 'evt-002', '{$nowUtc}', 2, 'member', 'member', 'MEMBER_PDPA_ACKNOWLEDGED', 'policy:3.0', 'success', 'cor-m-02', 'fp-01', '192.168.1.10', 'Mozilla/5.0', '{\"policy_version\":\"3.0\"}'),
        (3, 'evt-003', '{$nowUtc}', 2, 'member', 'member', 'MEMBER_FINANCIAL_VIEW', 'shares', 'success', 'cor-m-03', 'fp-01', '192.168.1.10', 'Mozilla/5.0', '{\"resource_type\":\"shares\"}'),
        (4, 'evt-004', '{$nowUtc}', 1, 'staff', 'super_admin', 'STAFF_LOGIN_SUCCESS', 'auth/login', 'success', 'cor-s-01', 'fp-02', '10.0.0.1', 'Mozilla/5.0', '{\"auth_method\":\"credentials\"}'),
        (5, 'evt-005', '{$nowUtc}', 1, 'staff', 'super_admin', 'STAFF_2FA_SUCCESS', 'auth/2fa', 'success', 'cor-s-02', 'fp-02', '10.0.0.1', 'Mozilla/5.0', '{\"method\":\"totp\"}'),
        (6, 'evt-006', '{$nowUtc}', 1, 'staff', 'super_admin', 'STAFF_MEMBER_VIEW', 'member:3100100200300', 'success', 'cor-s-03', 'fp-02', '10.0.0.1', 'Mozilla/5.0', '{\"target_member_id\":1}'),
        (7, 'evt-007', '{$nowUtc}', NULL, 'member', 'unknown', 'MEMBER_LOGIN_FAILED', 'auth/login', 'failure', 'cor-m-04', 'fp-03', '182.52.1.20', 'Mozilla/5.0', '{\"reason\":\"invalid_credentials\"}'),
        (8, 'evt-008', '{$nowUtc}', 2, 'member', 'member', 'MEMBER_ACCESS_DENIED', '/api/admin/audit-logs', 'denied', 'cor-m-05', 'fp-01', '192.168.1.10', 'Mozilla/5.0', '{\"reason\":\"member_forbidden\"}');
");

// Seed legacy audit_logs for backward compatibility test
$pdo->exec("
    INSERT INTO audit_logs (id, user_id, module, action, record_id, old_values, new_values, ip_address, user_agent, created_at)
    VALUES
        (1, 1, 'settings', 'update', '1', '{\"site_name\":\"Old Name\"}', '{\"site_name\":\"New Name\"}', '10.0.0.1', 'LegacyClient/1.0', '2025-01-01 10:00:00'),
        (2, 1, 'rates', 'update', '1', '{\"interest_rate\":\"4.5\"}', '{\"interest_rate\":\"5.0\"}', '10.0.0.1', 'LegacyClient/1.0', '2025-01-02 11:00:00');
");

$passed = 0;
$totalTests = 13;

function assertTest(bool $condition, string $title): void
{
    global $passed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$title}\n";
    } else {
        echo " [FAIL] {$title}\n";
    }
}

echo "========================================================================\n";
echo " SECURITY AUDIT VIEWER INTEGRATION TEST SUITE\n";
echo "========================================================================\n\n";

// Helper: login as user
function loginAs(int $userId, string $roleSlug, string $authContext, bool $mfaVerified): void
{
    $_SESSION = [];
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_context'] = $authContext;
    $_SESSION['2fa_verified'] = $mfaVerified;
    $_SESSION['role'] = $roleSlug;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['session_version'] = 1;
    $_SESSION['__last_activity'] = time();

    $user = Database::first("SELECT * FROM users WHERE id = ?", [$userId]);
    $user['role_slug'] = $roleSlug;
    (new ReflectionProperty(Auth::class, 'user'))->setValue(null, $user);
}

// --------------------------------------------------------------------------
// TEST 1: Unauthenticated request to AuditLogController returns 401
// --------------------------------------------------------------------------
$_SESSION = [];
(new ReflectionProperty(Auth::class, 'user'))->setValue(null, null);

$req = new Request();
$res = new TestAuditResponseCapture();
$ctrl = new AuditLogController($req, $res);

try {
    $ctrl->index();
} catch (\Throwable $e) {}

assertTest($res->status === 401, "Test 1: Unauthenticated request returns HTTP 401 Unauthorized");

// --------------------------------------------------------------------------
// TEST 2: Incomplete 2FA session returns 403 TWO_FACTOR_REQUIRED
// --------------------------------------------------------------------------
$_SESSION = [];
$_SESSION['user_id'] = 1;
$_SESSION['auth_context'] = 'staff';
$_SESSION['2fa_verified'] = false;
$_SESSION['pending_2fa_user_id'] = 1;
$_SESSION['mfa_started_at'] = time();
$_SESSION['session_version'] = 1;
$_SESSION['__last_activity'] = time();

$user = Database::first("SELECT * FROM users WHERE id = 1");
$user['role_slug'] = 'super_admin';
(new ReflectionProperty(Auth::class, 'user'))->setValue(null, $user);

$req = new Request();
$res = new TestAuditResponseCapture();
$ctrl = new AuditLogController($req, $res);

try {
    $ctrl->index();
} catch (\Throwable $e) {}

assertTest(
    $res->status === 403 && (($res->body['code'] ?? '') === 'TWO_FACTOR_REQUIRED' || ($res->body['code'] ?? '') === 'UNAUTHORIZED'),
    "Test 2: Backend user with unverified 2FA blocked with 403/TWO_FACTOR_REQUIRED"
);

// --------------------------------------------------------------------------
// TEST 3: Member session access blocked fail-closed with 403 & MEMBER_ACCESS_DENIED logged
// --------------------------------------------------------------------------
loginAs(2, 'member', 'member', true);

$beforeDeniedCount = (int) Database::value("SELECT COUNT(*) FROM security_audit_logs WHERE event_type = 'MEMBER_ACCESS_DENIED'");

$req = new Request();
$res = new TestAuditResponseCapture();
$ctrl = new AuditLogController($req, $res);

try {
    $ctrl->index();
} catch (\Throwable $e) {}

$afterDeniedCount = (int) Database::value("SELECT COUNT(*) FROM security_audit_logs WHERE event_type = 'MEMBER_ACCESS_DENIED'");

assertTest(
    $res->status === 403 && ($res->body['code'] ?? '') === 'FORBIDDEN' && $afterDeniedCount > $beforeDeniedCount,
    "Test 3: Member session blocked with 403 FORBIDDEN and MEMBER_ACCESS_DENIED audit recorded"
);

// --------------------------------------------------------------------------
// TEST 4: Authorized backend staff with verified 2FA can view audit logs (200 OK)
// --------------------------------------------------------------------------
loginAs(1, 'super_admin', 'staff', true);

$req = new Request();
$res = new TestAuditResponseCapture();
$ctrl = new AuditLogController($req, $res);
$ctrl->index();

assertTest(
    $res->status === 200 && ($res->body['success'] ?? false) === true && count($res->body['records'] ?? []) > 0,
    "Test 4: Authorized super_admin with verified 2FA views audit logs successfully (HTTP 200)"
);

// --------------------------------------------------------------------------
// TEST 5: Server-side filtering by actor_type ('member' vs 'staff')
// --------------------------------------------------------------------------
// Filter: member
$_GET['actor_type'] = 'member';
$reqMember = new Request();
$resMember = new TestAuditResponseCapture();
$ctrlMember = new AuditLogController($reqMember, $resMember);
$ctrlMember->index();

$allMembers = true;
foreach ($resMember->body['records'] ?? [] as $rec) {
    if ($rec['actor_type'] !== 'member') {
        $allMembers = false;
        break;
    }
}

// Filter: staff
$_GET['actor_type'] = 'staff';
$reqStaff = new Request();
$resStaff = new TestAuditResponseCapture();
$ctrlStaff = new AuditLogController($reqStaff, $resStaff);
$ctrlStaff->index();

$allStaff = true;
foreach ($resStaff->body['records'] ?? [] as $rec) {
    if ($rec['actor_type'] !== 'staff') {
        $allStaff = false;
        break;
    }
}
unset($_GET['actor_type']);

assertTest(
    $allMembers && $allStaff && count($resMember->body['records'] ?? []) > 0 && count($resStaff->body['records'] ?? []) > 0,
    "Test 5: Server-side filtering by actor_type (member vs staff) returns correct subsets"
);

// --------------------------------------------------------------------------
// TEST 6: Server-side filtering by result ('success', 'failure', 'denied')
// --------------------------------------------------------------------------
$_GET['result'] = 'failure';
$reqFail = new Request();
$resFail = new TestAuditResponseCapture();
$ctrlFail = new AuditLogController($reqFail, $resFail);
$ctrlFail->index();

$_GET['result'] = 'denied';
$reqDenied = new Request();
$resDenied = new TestAuditResponseCapture();
$ctrlDenied = new AuditLogController($reqDenied, $resDenied);
$ctrlDenied->index();
unset($_GET['result']);

$failOk = count($resFail->body['records'] ?? []) > 0 && ($resFail->body['records'][0]['result'] ?? '') === 'failure';
$deniedOk = count($resDenied->body['records'] ?? []) > 0 && ($resDenied->body['records'][0]['result'] ?? '') === 'denied';

assertTest($failOk && $deniedOk, "Test 6: Server-side filtering by result (failure and denied) works accurately");

// --------------------------------------------------------------------------
// TEST 7: Server-side filtering by date range and search term q
// --------------------------------------------------------------------------
$_GET['q'] = 'PDPA';
$reqQ = new Request();
$resQ = new TestAuditResponseCapture();
$ctrlQ = new AuditLogController($reqQ, $resQ);
$ctrlQ->index();
unset($_GET['q']);

$qMatched = count($resQ->body['records'] ?? []) > 0;
foreach ($resQ->body['records'] ?? [] as $rec) {
    if (!str_contains($rec['event_type'], 'PDPA') && !str_contains($rec['target_resource'], 'PDPA')) {
        $qMatched = false;
    }
}

assertTest($qMatched, "Test 7: Parameterized search query (q=PDPA) returns matching records");

// --------------------------------------------------------------------------
// TEST 8: Server-side pagination & dashboard KPI stats structure
// --------------------------------------------------------------------------
$_GET['per_page'] = 3;
$_GET['page'] = 1;
$reqPage = new Request();
$resPage = new TestAuditResponseCapture();
$ctrlPage = new AuditLogController($reqPage, $resPage);
$ctrlPage->index();
unset($_GET['per_page'], $_GET['page']);

$pageCount = count($resPage->body['records'] ?? []);
$stats = $resPage->body['stats'] ?? [];
$hasStats = isset($stats['total_events'], $stats['member_events'], $stats['staff_events'], $stats['failed_events'], $stats['denied_events'], $stats['legacy_total']);

assertTest(
    $pageCount === 3 && $resPage->body['total'] > 3 && $hasStats,
    "Test 8: Server-side pagination bounded correctly and KPI stats contain all required metrics"
);

// --------------------------------------------------------------------------
// TEST 9: PII Masking: Member names, Citizen IDs, and IPs are masked
// --------------------------------------------------------------------------
$_GET['actor_type'] = 'member';
$reqMask = new Request();
$resMask = new TestAuditResponseCapture();
$ctrlMask = new AuditLogController($reqMask, $resMask);
$ctrlMask->index();
unset($_GET['actor_type']);

$piiProtected = true;
foreach ($resMask->body['records'] ?? [] as $rec) {
    // Member full name must NOT contain raw unmasked name
    if (str_contains($rec['actor_name'], 'สมคิด ยอดนิยม')) {
        $piiProtected = false;
    }
    // Citizen ID must be masked
    if (str_contains($rec['target_resource'], '3100100200300')) {
        $piiProtected = false;
    }
    // IP must end with xxx or …
    if (!str_contains($rec['ip_address'], 'xxx') && !str_contains($rec['ip_address'], '…')) {
        $piiProtected = false;
    }
}

assertTest($piiProtected, "Test 9: Member PII (full name, citizen ID, IP address) strictly masked in responses");

// --------------------------------------------------------------------------
// TEST 10: Prohibit TOTP in URL & Enforce POST-Only Privileged Export
// --------------------------------------------------------------------------
// 10.1 GET request attempting export is rejected with HTTP 405 Method Not Allowed
$_SERVER['REQUEST_METHOD'] = 'GET';
$reqGetExport = new Request();
$resGetExport = new TestAuditResponseCapture();
$ctrlGetExport = new AuditLogController($reqGetExport, $resGetExport);
$ctrlGetExport->export();

$rejectedGet = $resGetExport->status === 405 && ($resGetExport->body['code'] ?? '') === 'METHOD_NOT_ALLOWED';

// 10.2 TOTP code in Query String is rejected with HTTP 400 CREDENTIAL_IN_URL_PROHIBITED
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['totp_code'] = '123456';
$reqUrlTotp = new Request();
$resUrlTotp = new TestAuditResponseCapture();
$ctrlUrlTotp = new AuditLogController($reqUrlTotp, $resUrlTotp);
$ctrlUrlTotp->export();
unset($_GET['totp_code']);

$rejectedUrlTotp = $resUrlTotp->status === 400 && ($resUrlTotp->body['code'] ?? '') === 'CREDENTIAL_IN_URL_PROHIBITED';

// 10.3 POST request without Step-Up MFA code returns HTTP 403
$_POST = [];
$reqNoMfa = new Request();
$resNoMfa = new TestAuditResponseCapture();
$ctrlNoMfa = new AuditLogController($reqNoMfa, $resNoMfa);
$ctrlNoMfa->export();

$blockedNoMfa = $resNoMfa->status === 403 && ($resNoMfa->body['requires_step_up_mfa'] ?? false) === true;

// 10.4 POST request with invalid TOTP code returns HTTP 403
$_POST['totp_code'] = '000000';
$reqBadMfa = new Request();
$resBadMfa = new TestAuditResponseCapture();
$ctrlBadMfa = new AuditLogController($reqBadMfa, $resBadMfa);
$ctrlBadMfa->export();

$blockedBadMfa = $resBadMfa->status === 403;

// 10.5 POST request with valid TOTP code in body succeeds
$timeSlice = (int) floor(time() / 30);
$calcCodeMethod = new ReflectionMethod(TwoFactorService::class, 'calculateCode');
$validTotp = $calcCodeMethod->invoke(null, $adminSecret, $timeSlice);

$_POST['totp_code'] = $validTotp;
$exportExportCountBefore = (int) Database::value("SELECT COUNT(*) FROM security_audit_logs WHERE event_type = 'ADMIN_FINANCIAL_EXPORT'");

$reqValidPost = new Request();
$resValidPost = new TestAuditResponseCapture();
$ctrlValidPost = new AuditLogController($reqValidPost, $resValidPost);

ob_start();
try {
    $ctrlValidPost->export();
} catch (\Throwable $e) {}
$csvOutput = ob_get_clean();
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';

$exportExportCountAfter = (int) Database::value("SELECT COUNT(*) FROM security_audit_logs WHERE event_type = 'ADMIN_FINANCIAL_EXPORT'");
$validExportSuccess = str_contains($csvOutput, 'Event ID') && str_contains($csvOutput, 'Correlation ID') && $exportExportCountAfter > $exportExportCountBefore;

assertTest(
    $rejectedGet && $rejectedUrlTotp && $blockedNoMfa && $blockedBadMfa && $validExportSuccess,
    "Test 10: Prohibits TOTP in URL (400), rejects GET export (405), and accepts fresh MFA strictly via POST body"
);

// --------------------------------------------------------------------------
// TEST 11: AdminControlCenterService getAuditLogs defaults to security_audit_logs & legacy fallback
// --------------------------------------------------------------------------
$defaultLogs = AdminControlCenterService::getAuditLogs(['per_page' => 10]);
$legacyLogs = AdminControlCenterService::getAuditLogs(['source' => 'legacy', 'per_page' => 10]);

$defaultUsesSecurity = isset($defaultLogs['records'][0]['event_type']) && isset($defaultLogs['stats']['total_events']);
$legacyUsesLegacy = !empty($legacyLogs['records'])
    && isset($legacyLogs['records'][0]['action'], $legacyLogs['records'][0]['module'])
    && str_starts_with($legacyLogs['records'][0]['event_id'] ?? '', 'leg-');

assertTest(
    $defaultUsesSecurity && $legacyUsesLegacy,
    "Test 11: AdminControlCenterService::getAuditLogs queries security_audit_logs by default and preserves legacy audit_logs"
);

// --------------------------------------------------------------------------
// TEST 12: Audit Query Failure Must Fail Closed (HTTP 500 & AUDIT_QUERY_FAILED)
// --------------------------------------------------------------------------
// Simulate database failure by dropping the audit table
$pdo->exec("DROP TABLE security_audit_logs;");

$reqFailQuery = new Request();
$resFailQuery = new TestAuditResponseCapture();
$ctrlFailQuery = new AuditLogController($reqFailQuery, $resFailQuery);
$ctrlFailQuery->index();

$queryFailureFailedClosed = $resFailQuery->status === 500
    && ($resFailQuery->body['success'] ?? true) === false
    && ($resFailQuery->body['code'] ?? '') === 'AUDIT_QUERY_FAILED'
    && !str_contains(json_encode($resFailQuery->body), 'PDOException')
    && !str_contains(json_encode($resFailQuery->body), 'no such table');

assertTest(
    $queryFailureFailedClosed,
    "Test 12: Database query failure fails closed with HTTP 500 and AUDIT_QUERY_FAILED without leaking SQL details"
);

// --------------------------------------------------------------------------
// TEST 13: Stats Endpoint Fails Closed on Database Failure
// --------------------------------------------------------------------------
$reqFailStats = new Request();
$resFailStats = new TestAuditResponseCapture();
$ctrlFailStats = new AuditLogController($reqFailStats, $resFailStats);
$ctrlFailStats->stats();

$statsFailedClosed = $resFailStats->status === 500
    && ($resFailStats->body['success'] ?? true) === false
    && ($resFailStats->body['code'] ?? '') === 'AUDIT_QUERY_FAILED';

assertTest(
    $statsFailedClosed,
    "Test 13: Stats endpoint fails closed with HTTP 500 on database failure"
);

echo "\n========================================================================\n";
echo " TEST SUMMARY: {$passed}/{$totalTests} PASSED\n";
echo "========================================================================\n";

if ($passed !== $totalTests) {
    exit(1);
}
