<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Api\SpaApiController;
use App\Controllers\Member\DividendController;
use App\Controllers\Member\MemberPdpaController;
use App\Controllers\Member\MemberPortalController;
use App\Core\{Auth, Csrf, Database, Request, Response, Router, Session};
use App\Middlewares\{AuthMiddleware, CsrfMiddleware, FeatureFlagMiddleware, MemberMiddleware, MemberPdpaMiddleware, RoleMiddleware};
use App\Services\{AuditService, MemberPdpaService, MemberPortalService};

final class PdpaTestResponse extends Response
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

$sessionDir = sys_get_temp_dir() . '/rycoop-uat-member-pdpa-test-sessions-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
putenv('SESSION_SECURE=false');
putenv('FEATURE_MEMBER_PORTAL=true');
putenv('FEATURE_MEMBER_LOGIN=true');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/member/dividends',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_USER_AGENT' => 'PdpaTestRunner/1.0',
];
$_GET = $_POST = $_FILES = [];

Session::start();

// Isolated SQLite in-memory database
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('UTC_TIMESTAMP', fn() => gmdate('Y-m-d H:i:s'));
$pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'));
$pdo->sqliteCreateFunction('YEAR', fn($d) => date('Y', strtotime((string)$d)));
$pdo->sqliteCreateFunction('CONCAT', fn(...$args) => implode('', $args));

(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

// Initialize isolated test schema
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
        department TEXT NULL,
        position TEXT NULL,
        phone TEXT NULL,
        email TEXT NULL,
        address TEXT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_privacy_policies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        version TEXT NOT NULL,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        content_sha256 TEXT NOT NULL,
        effective_at TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_pdpa_acknowledgements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        member_id INTEGER NOT NULL,
        policy_id INTEGER NOT NULL,
        policy_version TEXT NOT NULL,
        login_nonce_hash TEXT NOT NULL,
        acknowledged_at TEXT NOT NULL,
        ip_hash TEXT NULL,
        user_agent_hash TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE (user_id, login_nonce_hash, policy_id)
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        module TEXT NOT NULL,
        action TEXT NOT NULL,
        record_id TEXT NULL,
        old_values TEXT NULL,
        new_values TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        email TEXT NOT NULL,
        status TEXT NOT NULL,
        failure_reason TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE user_google_identities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        google_email TEXT NOT NULL,
        google_sub TEXT NOT NULL,
        linked_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        year INTEGER NOT NULL,
        details_json TEXT NOT NULL,
        imported_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
");

// Seed Roles
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (1, 'Super Admin', 'super_admin')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (2, 'Member', 'member')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (3, 'Staff', 'staff')");

// Seed Super Admin (User 1)
$adminHash = password_hash('Admin@123456', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (1, 'admin-uuid-001', 'Super Admin', 'admin', 'admin@example.com', '{$adminHash}', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (1, 1)");

// Seed Member A (User 10, Member 101, Citizen ID: 3219900100959, Member No: 00004)
$memberHashA = password_hash('00004', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (10, 'member-uuid-010', 'สมชาย ใจดี', '3219900100959', 'somchai@example.com', '{$memberHashA}', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (10, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, first_name, last_name, status, phone, department) VALUES (101, 'mem-uuid-101', 10, '00004', '3219900100959', 'สมชาย', 'ใจดี', 'active', '0812345678', 'โรงพยาบาลระยอง')");
$pdo->exec("INSERT INTO member_dividends (member_id, year, details_json) VALUES (101, 2568, '{\"year\":2568,\"total_income\":25000.00,\"total_deductions\":5000.00,\"net\":20000.00,\"received\":20000.00}')");

// Seed Member B (User 20, Member 201)
$memberHashB = password_hash('00006', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (20, 'member-uuid-020', 'สมหญิง รักดี', '3209900034158', 'somying@example.com', '{$memberHashB}', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (20, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, first_name, last_name, status, phone, department) VALUES (201, 'mem-uuid-201', 20, '00006', '3209900034158', 'สมหญิง', 'รักดี', 'active', '0898765432', 'สสจ.ระยอง')");

// Seed Initial Approved Privacy Policy Version 3.0
$policyText = 'ประกาศความเป็นส่วนตัวสำหรับสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด ฉบับ 3.0';
$policyHash = hash('sha256', $policyText);
$pdo->exec("INSERT INTO member_privacy_policies (id, version, title, content, content_sha256, effective_at, is_active) VALUES (1, '3.0', 'ประกาศความเป็นส่วนตัวสำหรับสมาชิก', '{$policyText}', '{$policyHash}', '2026-10-01 00:00:00', 1)");

// Initialize Router and Routes
$router = new Router();
require dirname(__DIR__) . '/config/routes.php';

function testDispatch(string $method, string $uri, array $input = [], array $headers = []): PdpaTestResponse
{
    global $router;
    $_SERVER['REQUEST_METHOD'] = strtoupper($method);
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = $input;
    foreach ($headers as $k => $v) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $_SERVER[$serverKey] = $v;
    }
    $req = new Request();
    $res = new PdpaTestResponse();
    $router->dispatch($req, $res);
    return $res;
}

$passCount = 0;
function tAssert(bool $condition, string $label): void
{
    global $passCount;
    if (!$condition) {
        throw new RuntimeException("Assertion FAILED: {$label}");
    }
    $passCount++;
    echo "PASS [T" . sprintf('%02d', $passCount) . "]: {$label}\n";
}

echo "=========================================================================\n";
echo "RYCOOPWEB — Member PDPA Session Gate Automated Test Suite (T01 - T20)\n";
echo "=========================================================================\n";

// -------------------------------------------------------------------------
// T01: Unauthenticated member endpoint -> 401
// -------------------------------------------------------------------------
Auth::logout();
$resT01 = testDispatch('GET', '/api/member/dividends');
tAssert($resT01->status === 401, "T01: Unauthenticated request to /api/member/dividends returns HTTP 401");

// -------------------------------------------------------------------------
// T02: Successful member login -> PDPA required
// -------------------------------------------------------------------------
$csrfToken = Csrf::token();
$resT02 = testDispatch('POST', '/login', [
    'username' => '3219900100959',
    'password' => '00004',
    '_csrf_token' => $csrfToken,
    'ajax' => '1',
]);
tAssert($resT02->status === 200 && ($resT02->body['redirect'] ?? '') === '/member/privacy-ack', "T02: Member login succeeds and directs to /member/privacy-ack");
tAssert(Auth::check() && Auth::id() === 10, "T02: Member session established for User 10");
$nonceT02 = MemberPdpaService::getCurrentSessionNonce();
tAssert(!empty($nonceT02) && strlen($nonceT02) === 64, "T02: Cryptographically random 64-char login nonce generated in session");

// -------------------------------------------------------------------------
// T03: Dashboard direct URL before acknowledge -> blocked
// -------------------------------------------------------------------------
$resT03 = testDispatch('GET', '/member/dashboard');
tAssert($resT03->status === 403 && ($resT03->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T03: Direct URL to /member/dashboard blocked with 403 PDPA_ACK_REQUIRED");

// -------------------------------------------------------------------------
// T04: API access before acknowledge -> 403 PDPA_ACK_REQUIRED
// -------------------------------------------------------------------------
$resT04 = testDispatch('GET', '/api/member/dashboard');
tAssert($resT04->status === 403 && ($resT04->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T04: API access /api/member/dashboard returns 403 PDPA_ACK_REQUIRED");
tAssert(($resT04->body['redirect'] ?? '') === '/member/privacy-ack', "T04: 403 response contains redirect: /member/privacy-ack");

// -------------------------------------------------------------------------
// T05: Missing CSRF on acknowledgement -> rejected
// -------------------------------------------------------------------------
$resT05 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
]);
tAssert($resT05->status === 419, "T05: Missing CSRF token rejected with HTTP 419");

// -------------------------------------------------------------------------
// T06: Missing checkbox on acknowledge -> rejected
// -------------------------------------------------------------------------
$resT06 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '0',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT06->status === 422 && ($resT06->body['code'] ?? '') === 'ACKNOWLEDGEMENT_REQUIRED', "T06: Unchecked acknowledgement checkbox rejected with 422 ACKNOWLEDGEMENT_REQUIRED");

// -------------------------------------------------------------------------
// T07: Valid acknowledgement -> saved in DB & audit logged
// -------------------------------------------------------------------------
$resT07 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT07->status === 200 && ($resT07->body['code'] ?? '') === 'PDPA_ACKNOWLEDGED', "T07: Valid acknowledgement returns HTTP 200 PDPA_ACKNOWLEDGED");

$ackRecord = $pdo->query("SELECT * FROM member_pdpa_acknowledgements WHERE user_id = 10 AND policy_version = '3.0'")->fetch();
tAssert(!empty($ackRecord) && (int)$ackRecord['member_id'] === 101, "T07: Acknowledgement record persisted in MySQL table with member_id 101");
tAssert(hash_equals(MemberPdpaService::hashNonce($nonceT02), (string)$ackRecord['login_nonce_hash']), "T07: Nonce hash matches session nonce SHA-256");

$auditEntry = $pdo->query("SELECT * FROM audit_logs WHERE module = 'member_pdpa' AND action = 'acknowledge' ORDER BY id DESC LIMIT 1")->fetch();
tAssert(!empty($auditEntry) && (int)$auditEntry['user_id'] === 10, "T07: Audit log recorded with module=member_pdpa and action=acknowledge");

// -------------------------------------------------------------------------
// T08: Repeat POST -> idempotent
// -------------------------------------------------------------------------
$resT08 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT08->status === 200 && ($resT08->body['idempotent'] ?? false) === true, "T08: Repeat POST is idempotent and returns success without error");

$ackCount = (int) $pdo->query("SELECT COUNT(*) FROM member_pdpa_acknowledgements WHERE user_id = 10")->fetchColumn();
tAssert($ackCount === 1, "T08: Database contains exactly 1 acknowledgement record (no duplicates)");

// -------------------------------------------------------------------------
// T09: Member API after acknowledgement -> permitted
// -------------------------------------------------------------------------
$resT09 = testDispatch('GET', '/api/member/dividends');
tAssert($resT09->status === 200 && ($resT09->body['success'] ?? false) === true, "T09: /api/member/dividends permitted after acknowledgement");
tAssert(!empty($resT09->body['records']), "T09: Member dividend financial records accessible");

// -------------------------------------------------------------------------
// T10: Logout and new login -> acknowledgement required again
// -------------------------------------------------------------------------
testDispatch('POST', '/logout', ['_csrf_token' => Csrf::token()]);
tAssert(!Auth::check(), "T10: User logged out successfully");

// New Login session
$resT10Login = testDispatch('POST', '/login', [
    'username' => '3219900100959',
    'password' => '00004',
    '_csrf_token' => Csrf::token(),
    'ajax' => '1',
]);
$nonceT10 = MemberPdpaService::getCurrentSessionNonce();
tAssert($nonceT10 !== $nonceT02, "T10: New login session generated a fresh new login nonce");

// Direct access with new session before acknowledgement must be blocked!
$resT10Check = testDispatch('GET', '/api/member/dividends');
tAssert($resT10Check->status === 403 && ($resT10Check->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T10: New login session strictly requires acknowledgement again");

// Acknowledge for new session
testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
$resT10Allowed = testDispatch('GET', '/api/member/dividends');
tAssert($resT10Allowed->status === 200, "T10: Access restored after new session acknowledgement");

// -------------------------------------------------------------------------
// T11: Policy version changed -> acknowledgement required
// -------------------------------------------------------------------------
// Admin releases Policy 3.1
$newPolicyText = 'ประกาศความเป็นส่วนตัวฉบับปรับปรุงใหม่ 3.1';
$newPolicyHash = hash('sha256', $newPolicyText);
$pdo->exec("UPDATE member_privacy_policies SET is_active = 0 WHERE id = 1");
$pdo->exec("INSERT INTO member_privacy_policies (id, version, title, content, content_sha256, effective_at, is_active) VALUES (2, '3.1', 'ประกาศความเป็นส่วนตัว (ปรับปรุง 3.1)', '{$newPolicyText}', '{$newPolicyHash}', '2026-11-01 00:00:00', 1)");

// Immediate check on active session: must fail because policy 3.1 was not acknowledged
$resT11 = testDispatch('GET', '/api/member/dividends');
tAssert($resT11->status === 403 && ($resT11->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T11: Material policy revision immediately re-locks member access");

// Attempting to acknowledge old 3.0 version must be rejected with 409
$resT11Conflict = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT11Conflict->status === 409 && ($resT11Conflict->body['code'] ?? '') === 'POLICY_VERSION_MISMATCH', "T11: Acknowledging outdated policy version rejected with 409 POLICY_VERSION_MISMATCH");

// Acknowledge new 3.1 version
$resT11Ack = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.1',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT11Ack->status === 200 && ($resT11Ack->body['code'] ?? '') === 'PDPA_ACKNOWLEDGED', "T11: Acknowledging new version 3.1 succeeds");

$resT11Allowed = testDispatch('GET', '/api/member/dividends');
tAssert($resT11Allowed->status === 200, "T11: Member API accessible after acknowledging revised policy");

// -------------------------------------------------------------------------
// T12: Admin login -> existing flow unaffected
// -------------------------------------------------------------------------
testDispatch('POST', '/logout', ['_csrf_token' => Csrf::token()]);
$adminUser = $pdo->query("SELECT u.*, r.slug as role_slug, r.name as role_name FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE u.id = 1")->fetch();
Auth::login($adminUser, true);
tAssert(Auth::check() && Auth::id() === 1, "T12: Admin authenticated successfully");
$resT12Me = testDispatch('GET', '/api/auth/me');
tAssert(($resT12Me->body['user']['role'] ?? '') === 'super_admin', "T12: Admin user profile unaffected");
tAssert(($resT12Me->body['user']['pdpa_ack_required'] ?? false) === false, "T12: Admin has pdpa_ack_required = false");

// -------------------------------------------------------------------------
// T13: Another member session -> no acknowledgement reuse
// -------------------------------------------------------------------------
testDispatch('POST', '/logout', ['_csrf_token' => Csrf::token()]);
// Login Member B (User 20)
testDispatch('POST', '/login', [
    'username' => '3209900034158',
    'password' => '00006',
    '_csrf_token' => Csrf::token(),
    'ajax' => '1',
]);
tAssert(Auth::id() === 20, "T13: Member B logged in");
// Member B has NOT acknowledged policy 3.1 yet
$resT13 = testDispatch('GET', '/api/member/dividends');
tAssert($resT13->status === 403 && ($resT13->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T13: Member B blocked, cannot reuse Member A acknowledgement");

// -------------------------------------------------------------------------
// T14: SQL insert fails / Audit fails -> fail closed
// -------------------------------------------------------------------------
// Simulate audit failure using fault injector
AuditService::setTestFaultInjector(function ($module, $action, $recordId, $old, $new, $required) {
    if ($module === 'member_pdpa') {
        throw new RuntimeException("Simulated Audit Logging Database Failure");
    }
});

$resT14 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.1',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT14->status === 500 && ($resT14->body['code'] ?? '') === 'PERSISTENCE_FAILURE', "T14: Audit failure triggers fail-closed HTTP 500");

// Verify Member B is STILL blocked after failed persistence
$resT14Blocked = testDispatch('GET', '/api/member/dividends');
tAssert($resT14Blocked->status === 403 && ($resT14Blocked->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T14: Fail-closed verified: Member B remains strictly blocked");

// Clear fault injector and acknowledge properly
AuditService::setTestFaultInjector(null);
$resT14Fixed = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.1',
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT14Fixed->status === 200, "T14: Acknowledgement succeeds after restoring audit service");

// -------------------------------------------------------------------------
// T15: Session timeout -> login required
// -------------------------------------------------------------------------
Session::destroy();
$resT15 = testDispatch('GET', '/api/member/dividends');
tAssert($resT15->status === 401, "T15: Destroyed/timed-out session returns 401 UNAUTHORIZED");

// -------------------------------------------------------------------------
// T16: Direct dividend API -> protected
// -------------------------------------------------------------------------
// Login Member A again fresh
Session::start();
testDispatch('POST', '/login', [
    'username' => '3219900100959',
    'password' => '00004',
    '_csrf_token' => Csrf::token(),
    'ajax' => '1',
]);
$resT16 = testDispatch('GET', '/api/member/dividends');
tAssert($resT16->status === 403 && ($resT16->body['code'] ?? '') === 'PDPA_ACK_REQUIRED', "T16: Direct /api/member/dividends protected on new session");

// -------------------------------------------------------------------------
// T17: Malformed request -> rejected
// -------------------------------------------------------------------------
$resT17 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '', // Empty version
    '_csrf_token' => Csrf::token(),
]);
tAssert($resT17->status === 422 && ($resT17->body['code'] ?? '') === 'MISSING_POLICY_VERSION', "T17: Empty policy version rejected with 422 MISSING_POLICY_VERSION");

// -------------------------------------------------------------------------
// T18: Member role cannot access staff admin APIs
// -------------------------------------------------------------------------
// Even with valid member session, member cannot touch admin endpoints
$resT18 = testDispatch('GET', '/api/admin/staff');
tAssert($resT18->status === 403, "T18: Authenticated member strictly forbidden from /api/admin/staff");

// -------------------------------------------------------------------------
// T19: Password change requirement remains enforced
// -------------------------------------------------------------------------
// Member changes password
$resT19Wrong = testDispatch('POST', '/api/change-password', [
    'current_password' => 'wrong_pass',
    'new_password' => 'NewSecurePassword123!',
    'confirm_password' => 'NewSecurePassword123!',
    '_csrf_token' => Csrf::token(),
    'ajax' => '1',
]);
tAssert($resT19Wrong->status === 400, "T19: Password change with incorrect current password rejected");

$resT19Correct = testDispatch('POST', '/api/change-password', [
    'current_password' => '00004',
    'new_password' => 'NewSecurePassword123!',
    'confirm_password' => 'NewSecurePassword123!',
    '_csrf_token' => Csrf::token(),
    'ajax' => '1',
]);
tAssert($resT19Correct->status === 200, "T19: Password change with valid credentials accepted");

// -------------------------------------------------------------------------
// T20: Login nonce survives routine session ID rotation
// -------------------------------------------------------------------------
// Member A acknowledges policy 3.1
testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.1',
    '_csrf_token' => Csrf::token(),
]);
$resT20Before = testDispatch('GET', '/api/member/dividends');
tAssert($resT20Before->status === 200, "T20: Member A acknowledged and accessing dividends");

$nonceBeforeRotate = MemberPdpaService::getCurrentSessionNonce();

// Perform routine session ID rotation (like 30-minute age rotation)
Session::regenerate();

$nonceAfterRotate = MemberPdpaService::getCurrentSessionNonce();
tAssert($nonceBeforeRotate === $nonceAfterRotate, "T20: Session ID rotation preserved login session nonce");

$resT20After = testDispatch('GET', '/api/member/dividends');
tAssert($resT20After->status === 200, "T20: Member remains acknowledged after routine session ID rotation");

echo "=========================================================================\n";
echo "All {$passCount} Automated PDPA Session Gate Tests (T01 - T20) PASSED!\n";
echo "=========================================================================\n";
