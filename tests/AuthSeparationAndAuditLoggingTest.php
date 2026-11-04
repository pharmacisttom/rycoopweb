<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\MfaController;
use App\Controllers\Member\DividendController;
use App\Controllers\Member\MemberPdpaController;
use App\Core\{Auth, Csrf, Database, Request, Response, Session};
use App\Middlewares\{AuthMiddleware, RoleMiddleware};
use App\Services\{MemberPdpaService, MemberPortalService, SecurityAuditService, TwoFactorService};

final class TestResponseCapture extends Response
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

$sessionDir = sys_get_temp_dir() . '/rycoop-uat-auth-separation-audit-test-sessions-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
putenv('SESSION_SECURE=false');
putenv('TWO_FACTOR_ENCRYPTION_KEY=1234567890123456789012345678901234');
putenv('FEATURE_MEMBER_PORTAL=true');
putenv('FEATURE_MEMBER_LOGIN=true');
putenv('GOOGLE_AUTH_ENABLED=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/admin/members',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'REMOTE_ADDR' => '192.168.1.100',
    'HTTP_USER_AGENT' => 'AuthSeparationTestRunner/1.0',
    'HTTP_X_CORRELATION_ID' => 'test-correlation-uuid-12345678',
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
        status TEXT NOT NULL DEFAULT 'inactive',
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
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        year INTEGER NOT NULL,
        details_json TEXT NOT NULL,
        imported_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE login_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        email TEXT NOT NULL,
        status TEXT NOT NULL,
        failure_reason TEXT NULL,
        ip_address TEXT NOT NULL,
        user_agent TEXT NULL,
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
    (1, 'ผู้ดูแลระบบสูงสุด', 'super_admin'),
    (2, 'เจ้าหน้าที่สินเชื่อ/การเงิน', 'staff'),
    (3, 'สมาชิกสหกรณ์', 'member'),
    (4, 'ผู้ดูแลระบบสมาชิก', 'member_admin'),
    (5, 'ผู้ตรวจสอบกิจการ', 'auditor');
");

// Seed Active Member Policy 3.0
$policyContent = 'ประกาศความเป็นส่วนตัวฉบับทดสอบ 3.0 สำหรับสมาชิก';
$policyHash = hash('sha256', $policyContent);
$pdo->prepare("
    INSERT INTO member_privacy_policies (id, version, title, content, content_sha256, effective_at, is_active)
    VALUES (1, '3.0', 'ประกาศความเป็นส่วนตัว 3.0', ?, ?, '2026-10-01 00:00:00', 1)
")->execute([$policyContent, $policyHash]);

// Seed Member User (Active)
$memberPasswordHash = password_hash('random-secure-hash', PASSWORD_BCRYPT);
$pdo->prepare("
    INSERT INTO users (id, uuid, name, username, email, password, status, session_version)
    VALUES (101, 'uuid-member-101', 'นายสมคิด จิตดี', '1234567890123', 'member101@test.local', ?, 'active', 1)
")->execute([$memberPasswordHash]);
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (101, 3)"); // member

// Seed Member Record
$pdo->prepare("
    INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
    VALUES (201, 'uuid-rec-201', 101, '00042', '1234567890123', 'นาย', 'สมคิด', 'จิตดี', 'active')
")->execute();

// Seed Dividend for Member
$dividendData = json_encode([
    'year' => 2568,
    'dividend' => '1500.00',
    'patronage' => '500.00',
    'net' => '2000.00',
    'id_card' => '1234567890123',
    'member_no' => '00042',
]);
$pdo->prepare("INSERT INTO member_dividends (member_id, year, details_json) VALUES (201, 2568, ?)")->execute([$dividendData]);

// Seed Backend User 1: super_admin without 2FA enrolled
$staffPasswordHash = password_hash('Admin@2026Secret', PASSWORD_BCRYPT);
$pdo->prepare("
    INSERT INTO users (id, uuid, name, username, email, password, status, session_version, two_factor_enabled, two_factor_secret)
    VALUES (301, 'uuid-admin-301', 'แอดมิน สูงสุด', 'superadmin', 'admin@rayongcoop.com', ?, 'active', 1, 0, NULL)
")->execute([$staffPasswordHash]);
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (301, 1)"); // super_admin

// Seed Backend User 2: member_admin with 2FA enrolled
$totpSecret = TwoFactorService::generateSecret(32);
$encryptedTotp = TwoFactorService::encryptSecret($totpSecret);
$pdo->prepare("
    INSERT INTO users (id, uuid, name, username, email, password, status, session_version, two_factor_enabled, two_factor_secret, two_factor_confirmed_at)
    VALUES (302, 'uuid-staff-302', 'เจ้าหน้าที่ จัดการสมาชิก', 'memberadmin', 'memberadmin@rayongcoop.com', ?, 'active', 1, 1, ?, '2026-10-01 00:00:00')
")->execute([$staffPasswordHash, $encryptedTotp]);
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (302, 4)"); // member_admin

// Seed Backend User 3: auditor with 2FA enrolled
$auditorTotpSecret = TwoFactorService::generateSecret(32);
$auditorEncryptedTotp = TwoFactorService::encryptSecret($auditorTotpSecret);
$pdo->prepare("
    INSERT INTO users (id, uuid, name, username, email, password, status, session_version, two_factor_enabled, two_factor_secret, two_factor_confirmed_at)
    VALUES (303, 'uuid-auditor-303', 'ผู้ตรวจสอบ บัญชี', 'auditor01', 'auditor@rayongcoop.com', ?, 'active', 1, 1, ?, '2026-10-01 00:00:00')
")->execute([$staffPasswordHash, $auditorEncryptedTotp]);
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (303, 5)"); // auditor

// Test runner helper
$assertionsPassed = 0;
function testAssert(bool $condition, string $description): void
{
    global $assertionsPassed;
    if (!$condition) {
        throw new RuntimeException("Assertion FAILED: {$description}");
    }
    $assertionsPassed++;
    echo "  [PASS] {$description}\n";
}

echo "\n--- RYCOOPWEB: AUTHENTICATION SEPARATION & AUDIT LOGGING TEST SUITE ---\n\n";

// =========================================================================
// TEST SUITE 1: MEMBER LOGIN & AUDIT LOGGING
// =========================================================================
echo "1. Member Login Flow & Audit Trails:\n";

// 1.1 Member login with Citizen ID + 5-digit member number
$_POST = [
    'username' => '1234567890123',
    'password' => '00042',
    'ajax' => '1',
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/login';
Session::destroy();
Session::start();

$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

testAssert($res->status === 200, "Member login succeeds with 200 OK");
testAssert($res->body['success'] === true, "Member login returns success: true");
testAssert(Auth::check() === true, "Member session is active (Auth::check() is true)");
testAssert(Session::get('auth_context') === 'member', "Session auth_context is strictly 'member'");
testAssert(Session::get('2fa_verified') === true, "Member session 2fa_verified is true without TOTP challenge");

// Verify Audit Log: MEMBER_LOGIN_SUCCESS
$log = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_LOGIN_SUCCESS]);
testAssert($log !== null, "Audit log records MEMBER_LOGIN_SUCCESS");
testAssert($log['actor_type'] === 'member', "Actor type is 'member'");
testAssert((int)$log['actor_user_id'] === 101, "Actor user ID matches member user 101");
testAssert($log['result'] === 'success', "Audit result is 'success'");
testAssert(!empty($log['event_id']), "Event ID is generated");
testAssert(!empty($log['session_fingerprint']), "Session fingerprint is present");
testAssert(strpos($log['metadata'] ?? '', '1234567890123') === false, "Raw 13-digit citizen ID is NEVER present in metadata");

// 1.2 Member Failed Login
$_POST = [
    'username' => '1234567890123',
    'password' => 'wrongpass',
    'ajax' => '1',
];
Session::destroy();
Session::start();
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

testAssert($res->status === 401, "Invalid password returns 401");
$failedLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_LOGIN_FAILED]);
testAssert($failedLog !== null, "Audit log records MEMBER_LOGIN_FAILED");
testAssert($failedLog['actor_type'] === 'member', "Actor type is 'member' on failed member login");
testAssert($failedLog['result'] === 'failure', "Audit result is 'failure'");

// =========================================================================
// TEST SUITE 2: PRIVILEGE ISOLATION & ACCESS CONTROL (MEMBER -> BACKEND)
// =========================================================================
echo "\n2. Member Privilege Isolation & IDOR Protection:\n";

// Login member again
$_POST = [
    'username' => '1234567890123',
    'password' => '00042',
    'ajax' => '1',
];
Session::destroy();
Session::start();
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

// 2.1 Member attempts to access super_admin route
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/admin/dashboard';
$roleMiddleware = new RoleMiddleware(['super_admin']);
$allowed = $roleMiddleware->handle(new Request(), $res = new TestResponseCapture());

testAssert($allowed === false, "Member is blocked by RoleMiddleware from super_admin routes");
testAssert($res->status === 403, "Blocked response returns 403 Forbidden");

// Verify Audit Log: MEMBER_ACCESS_DENIED
$deniedLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_ACCESS_DENIED]);
testAssert($deniedLog !== null, "Audit log records MEMBER_ACCESS_DENIED");
testAssert($deniedLog['actor_type'] === 'member', "Actor type is 'member'");
testAssert($deniedLog['result'] === 'denied', "Audit result is 'denied'");
testAssert($deniedLog['target_resource'] === '/api/admin/dashboard', "Target resource matches requested admin route");

// 2.2 Member attempts to access staff/backoffice route
$_SERVER['REQUEST_URI'] = '/api/admin/members';
$backofficeMiddleware = new RoleMiddleware(['staff', 'member_admin', 'auditor']);
$allowedStaff = $backofficeMiddleware->handle(new Request(), $res = new TestResponseCapture());
testAssert($allowedStaff === false, "Member is blocked by RoleMiddleware from backoffice staff routes");

// =========================================================================
// TEST SUITE 3: BACKEND MANDATORY 2FA ENFORCEMENT
// =========================================================================
echo "\n3. Backend Mandatory 2FA Enforcement for ALL Privileged Roles:\n";

// 3.1 Backend user without 2FA (superadmin) -> MUST NOT gain privileged session
$_POST = [
    'username' => 'superadmin',
    'password' => 'Admin@2026Secret',
    'ajax' => '1',
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/login';
Session::destroy();
Session::start();

$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

testAssert($res->status === 200, "Password check succeeds");
testAssert($res->body['mfa_required'] === true, "mfa_required is true");
testAssert($res->body['requires_enrollment'] === true, "requires_enrollment is true");
testAssert($res->body['redirect'] === '/admin/mfa/enroll', "Redirects to /admin/mfa/enroll");
testAssert(Auth::check() === false, "super_admin CANNOT access privileged routes (Auth::check() is FALSE)");
testAssert(Session::get('2fa_verified') === false, "2fa_verified is FALSE in session");
testAssert(Session::get('auth_context') === 'backend', "Session auth_context is 'backend'");

// Verify Audit Log: ADMIN_LOGIN_SUCCESS with challenge
$adminLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::ADMIN_LOGIN_SUCCESS]);
testAssert($adminLog !== null, "Audit log records ADMIN_LOGIN_SUCCESS");
testAssert($adminLog['actor_type'] === 'staff', "Actor type is 'staff'");
testAssert((int)$adminLog['actor_user_id'] === 301, "Actor user ID is 301");
testAssert($adminLog['role'] === 'super_admin', "Role is 'super_admin'");

// 3.2 Try to access admin API with partial enrollment session -> BLOCKED
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/admin/members';
$authMiddleware = new AuthMiddleware();
$mwAllowed = $authMiddleware->handle(new Request(), $res = new TestResponseCapture());
testAssert($mwAllowed === false, "AuthMiddleware blocks unverified 2FA session from admin API");
testAssert($res->status === 403, "Response status is 403 Forbidden");
testAssert(($res->body['code'] ?? '') === 'MFA_ENROLLMENT_REQUIRED', "Code is MFA_ENROLLMENT_REQUIRED");

// 3.3 Backend user with 2FA enrolled (member_admin) -> MUST require TOTP verification
$_POST = [
    'username' => 'memberadmin',
    'password' => 'Admin@2026Secret',
    'ajax' => '1',
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/login';
Session::destroy();
Session::start();
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

testAssert($res->body['mfa_required'] === true, "member_admin requires MFA");
testAssert($res->body['requires_enrollment'] === false, "requires_enrollment is false");
testAssert($res->body['redirect'] === '/admin/mfa/verify', "Redirects to /admin/mfa/verify");
testAssert(Auth::check() === false, "member_admin CANNOT access privileged routes before TOTP verification");
testAssert(Session::get('2fa_verified') === false, "2fa_verified is FALSE in session");

// 3.4 Auditor role also requires 2FA
$_POST = [
    'username' => 'auditor01',
    'password' => 'Admin@2026Secret',
    'ajax' => '1',
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/admin/login';
Session::destroy();
Session::start();
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();
testAssert($res->body['mfa_required'] === true, "auditor role strictly requires MFA");
testAssert(Auth::check() === false, "auditor role is blocked until 2FA completes");

// =========================================================================
// TEST SUITE 4: 2FA VERIFICATION & ELEVATION TO PRIVILEGED SESSION
// =========================================================================
echo "\n4. TOTP 2FA Verification & Session Elevation:\n";

// Login as member_admin to establish pending 2FA challenge
$_POST = [
    'username' => 'memberadmin',
    'password' => 'Admin@2026Secret',
    'ajax' => '1',
];
Session::destroy();
Session::start();
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

// 4.1 Submit INVALID 2FA code
$_POST = ['code' => '000000'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/auth/mfa/verify';
$mfaCtrl = new MfaController(new Request(), $res = new TestResponseCapture());
$mfaCtrl->verify();

testAssert($res->status === 400, "Invalid 2FA code returns 400 Bad Request");
testAssert(Auth::check() === false, "Session remains unauthenticated on invalid 2FA");

// Verify Audit Log: ADMIN_2FA_FAILED
$mfaFailLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::ADMIN_2FA_FAILED]);
testAssert($mfaFailLog !== null, "Audit log records ADMIN_2FA_FAILED");
testAssert($mfaFailLog['actor_type'] === 'staff', "Actor type is 'staff'");
testAssert($mfaFailLog['result'] === 'failure', "Result is 'failure'");

// 4.2 Submit VALID TOTP code
// Generate valid TOTP code using secret
$calcMethod = new ReflectionMethod(TwoFactorService::class, 'calculateCode');
$validTotpCode = $calcMethod->invoke(null, $totpSecret, (int)floor(time() / 30));
$_POST = ['code' => $validTotpCode];
$mfaCtrl = new MfaController(new Request(), $res = new TestResponseCapture());
$mfaCtrl->verify();

testAssert($res->status === 200, "Valid 2FA code returns 200 OK");
testAssert(Auth::check() === true, "Session is now fully authenticated after valid 2FA");
testAssert(Session::get('2fa_verified') === true, "Session 2fa_verified is TRUE");
testAssert(Session::get('auth_context') === 'backend', "Session auth_context is 'backend'");

// Verify Audit Log: ADMIN_2FA_SUCCESS
$mfaSuccessLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::ADMIN_2FA_SUCCESS]);
testAssert($mfaSuccessLog !== null, "Audit log records ADMIN_2FA_SUCCESS");
testAssert($mfaSuccessLog['actor_type'] === 'staff', "Actor type is 'staff'");
testAssert($mfaSuccessLog['result'] === 'success', "Result is 'success'");
testAssert(strpos($mfaSuccessLog['metadata'] ?? '', $validTotpCode) === false, "TOTP code is NEVER stored in audit logs");

// =========================================================================
// TEST SUITE 5: MEMBER PDPA & FINANCIAL AUDIT LOGS
// =========================================================================
echo "\n5. Member PDPA & Financial Access Audit Events:\n";

// Login as member
$_POST = [
    'username' => '1234567890123',
    'password' => '00042',
    'ajax' => '1',
];
Session::destroy();
Session::start();
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->login();

// 5.1 Member views PDPA notice
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/member/pdpa/status';
$pdpaCtrl = new MemberPdpaController(new Request(), $res = new TestResponseCapture());
$pdpaCtrl->status();

testAssert($res->status === 200, "PDPA status endpoint returns 200 OK");
$pdpaViewLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_PDPA_VIEWED]);
testAssert($pdpaViewLog !== null, "Audit log records MEMBER_PDPA_VIEWED");
testAssert($pdpaViewLog['actor_type'] === 'member', "Actor type is 'member'");

// 5.2 Member explicitly acknowledges PDPA v3.0
$_POST = [
    'acknowledgement' => true,
    'policy_version' => '3.0',
    'policy_hash' => $policyHash,
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/member/pdpa/acknowledge';
$pdpaCtrl = new MemberPdpaController(new Request(), $res = new TestResponseCapture());
$pdpaCtrl->acknowledge();

testAssert($res->status === 200, "PDPA acknowledgement succeeds with 200 OK");
$pdpaAckLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_PDPA_ACKNOWLEDGED]);
testAssert($pdpaAckLog !== null, "Audit log records MEMBER_PDPA_ACKNOWLEDGED");
testAssert($pdpaAckLog['actor_type'] === 'member', "Actor type is 'member'");
testAssert($pdpaAckLog['result'] === 'success', "Result is 'success'");

// 5.3 Member views financial records (dividends)
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/member/dividends';
$dividendCtrl = new DividendController(new Request(), $res = new TestResponseCapture());
$dividendCtrl->index();

testAssert($res->status === 200, "Dividend endpoint returns 200 OK");
testAssert($res->body['member']['member_no'] === '00*42', "Displayed member number is masked as 00*42");

$finLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_FINANCIAL_VIEW]);
testAssert($finLog !== null, "Audit log records MEMBER_FINANCIAL_VIEW");
testAssert($finLog['actor_type'] === 'member', "Actor type is 'member'");
testAssert($finLog['target_resource'] === '/api/member/dividends', "Target resource is /api/member/dividends");
testAssert(strpos($finLog['metadata'] ?? '', '2000.00') === false, "Financial amounts are NEVER logged in audit trail");

// 5.4 Member Logout
$authCtrl = new AuthController(new Request(), $res = new TestResponseCapture());
$authCtrl->logout();

$memberLogoutLog = Database::first("SELECT * FROM security_audit_logs WHERE event_type = ? ORDER BY id DESC LIMIT 1", [SecurityAuditService::MEMBER_LOGOUT]);
testAssert($memberLogoutLog !== null, "Audit log records MEMBER_LOGOUT");
testAssert($memberLogoutLog['actor_type'] === 'member', "Actor type is 'member'");

// =========================================================================
// TEST SUITE 6: DATA SCRUBBING & INTEGRITY AUDIT
// =========================================================================
echo "\n6. Comprehensive Audit Scrubbing Verification:\n";

// Check all security_audit_logs rows
$allLogs = Database::query("SELECT * FROM security_audit_logs");
testAssert(count($allLogs) >= 8, "All key authentication and access events are recorded in security_audit_logs");

foreach ($allLogs as $entry) {
    $meta = $entry['metadata'] ?? '';
    // Check no unencrypted passwords
    testAssert(strpos($meta, 'Admin@2026Secret') === false, "Row ID {$entry['id']}: No staff password in logs");
    testAssert(strpos($meta, '00042') === false, "Row ID {$entry['id']}: No unmasked member initial password in logs");
    // Check no raw citizen IDs
    testAssert(strpos($meta, '1234567890123') === false, "Row ID {$entry['id']}: No raw citizen ID in logs");
    // Check no TOTP secrets
    testAssert(strpos($meta, $totpSecret) === false, "Row ID {$entry['id']}: No TOTP secret in logs");
    // Check required fields
    testAssert(in_array($entry['actor_type'], ['member', 'staff'], true), "Row ID {$entry['id']}: Valid actor_type");
    testAssert(in_array($entry['result'], ['success', 'failure', 'denied'], true), "Row ID {$entry['id']}: Valid result");
    testAssert(!empty($entry['correlation_id']), "Row ID {$entry['id']}: Non-empty correlation_id");
    testAssert(!empty($entry['session_fingerprint']), "Row ID {$entry['id']}: Non-empty session_fingerprint");
}

echo "\n======================================================\n";
echo "SUCCESS: ALL {$assertionsPassed} AUTHENTICATION SEPARATION & AUDIT ASSERTIONS PASSED!\n";
echo "======================================================\n";
