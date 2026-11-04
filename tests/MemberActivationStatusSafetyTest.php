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
use App\Middlewares\{AuthMiddleware, CsrfMiddleware, FeatureFlagMiddleware, MemberMiddleware, MemberPdpaMiddleware, PermissionMiddleware, RoleMiddleware};
use App\Services\{AuditService, MemberAdminService, MemberPdpaService, MemberPortalService};

final class ActivationTestResponse extends Response
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

$sessionDir = sys_get_temp_dir() . '/rycoop-uat-member-activation-test-sessions-' . getmypid() . '-' . bin2hex(random_bytes(4));
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
putenv('SESSION_SECURE=false');
putenv('FEATURE_MEMBER_PORTAL=true');
putenv('FEATURE_MEMBER_LOGIN=true');
putenv('GOOGLE_AUTH_ENABLED=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/member/dividends',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_USER_AGENT' => 'ActivationTestRunner/1.0',
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
$pdo->sqliteCreateFunction('RIGHT', fn($str, $len) => substr((string)$str, -$len));
$pdo->sqliteCreateFunction('JSON_EXTRACT', fn($json, $path) => null);
$pdo->sqliteCreateFunction('JSON_UNQUOTE', fn($val) => (string)$val);

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
        id_card TEXT NOT NULL UNIQUE,
        prefix TEXT NULL DEFAULT '',
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        phone TEXT NULL,
        email TEXT NULL,
        address TEXT NULL,
        department TEXT NULL,
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
        email TEXT NULL,
        status TEXT NOT NULL,
        failure_reason TEXT NULL,
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_data_changes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        user_id INTEGER NULL,
        action TEXT NOT NULL,
        reason TEXT NULL,
        before_json TEXT NULL,
        after_json TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_import_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        year INTEGER NULL,
        source_name TEXT NOT NULL,
        record_count INTEGER NOT NULL,
        created_count INTEGER NOT NULL DEFAULT 0,
        updated_count INTEGER NOT NULL DEFAULT 0,
        unchanged_count INTEGER NOT NULL DEFAULT 0,
        net_total REAL NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        year INTEGER NOT NULL,
        details_json TEXT NOT NULL,
        imported_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE user_google_identities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        google_email TEXT NOT NULL,
        google_sub TEXT NOT NULL,
        linked_at TEXT NOT NULL DEFAULT (datetime('now'))
    );
");

// Seed Roles
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (1, 'Super Admin', 'super_admin')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (2, 'Member', 'member')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (3, 'Staff', 'staff')");
$pdo->exec("INSERT INTO roles (id, name, slug) VALUES (4, 'Member Admin', 'member_admin')");

// Seed Permissions for Staff / Member Admin
$pdo->exec("INSERT INTO permissions (id, name, module, action) VALUES (1, 'View Members', 'members', 'view')");
$pdo->exec("INSERT INTO permissions (id, name, module, action) VALUES (2, 'Update Members', 'members', 'update')");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (4, 1)");
$pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (4, 2)");

// Seed Staff without update permission (role 3 has only view)
$pdo->exec("INSERT INTO role_permissions (role_id, permission_id) VALUES (3, 1)");

// Seed Users
// 1. Super Admin (User 1)
$adminHash = password_hash('Admin@123456', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (1, 'admin-uuid-001', 'Super Admin', 'admin', 'admin@example.com', '{$adminHash}', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (1, 1)");

// 2. Member Admin Staff (User 2 - has members.view & members.update)
$staffHash = password_hash('Staff@123456', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (2, 'staff-uuid-002', 'เจ้าหน้าที่ทะเบียน', 'staff_admin', 'staff@example.com', '{$staffHash}', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (2, 4)");

// 3. Regular Staff without update permission (User 3)
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (3, 'staff-uuid-003', 'เจ้าหน้าที่ทั่วไป', 'staff_viewer', 'viewer@example.com', '{$staffHash}', 'active')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (3, 3)");

// 4. Authorized UAT Inactive Member Account (User 10, Member 101)
// Citizen ID: 3210100120959, Member No: 00123. Initial status: INACTIVE (as imported by LegacyMemberMigrationService)
$defaultHash = password_hash('00123', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (10, 'uat-user-010', 'สมศักดิ์ มั่นคง', '3210100120959', 'somsak@example.com', '{$defaultHash}', 'inactive')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (10, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, first_name, last_name, status, phone, email, department) VALUES (101, 'uat-mem-101', 10, '00123', '3210100120959', 'สมศักดิ์', 'มั่นคง', 'active', '0812345678', 'somsak@example.com', 'รพ.ระยอง')");
$pdo->exec("INSERT INTO member_dividends (member_id, year, details_json) VALUES (101, 2568, '{\"year\":2568,\"total_income\":30000.00,\"total_deductions\":5000.00,\"net\":25000.00,\"received\":25000.00}')");

// 5. Suspended Member Account (User 20, Member 201)
$suspendedHash = password_hash('00456', PASSWORD_BCRYPT);
$pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES (20, 'susp-user-020', 'สมปอง ใจเย็น', '3210200456789', 'sompong@example.com', '{$suspendedHash}', 'suspended')");
$pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES (20, 2)");
$pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, first_name, last_name, status, phone, department) VALUES (201, 'susp-mem-201', 20, '00456', '3210200456789', 'สมปอง', 'ใจเย็น', 'suspended', '0898765432', 'สสจ.ระยอง')");

// 6. Bulk preserved inactive member accounts (simulating the 2,508 inactive legacy members)
for ($i = 30; $i <= 35; $i++) {
    $cid = "32100000000" . $i;
    $mno = str_pad((string)$i, 5, '0', STR_PAD_LEFT);
    $h = password_hash($mno, PASSWORD_BCRYPT);
    $pdo->exec("INSERT INTO users (id, uuid, name, username, email, password, status) VALUES ({$i}, 'bulk-uuid-{$i}', 'สมาชิก จำลอง{$i}', '{$cid}', 'mem{$i}@example.com', '{$h}', 'inactive')");
    $pdo->exec("INSERT INTO user_roles (user_id, role_id) VALUES ({$i}, 2)");
    $pdo->exec("INSERT INTO members (id, uuid, user_id, member_no, id_card, first_name, last_name, status) VALUES ({$i}, 'bulk-mem-{$i}', {$i}, '{$mno}', '{$cid}', 'จำลอง', '{$i}', 'active')");
}

// Seed Active Privacy Policy Version 3.0
$policyText = 'ประกาศความเป็นส่วนตัวสำหรับสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด';
$policyHash = hash('sha256', $policyText);
$pdo->exec("INSERT INTO member_privacy_policies (id, version, title, content, content_sha256, effective_at, is_active) VALUES (1, '3.0', 'ประกาศความเป็นส่วนตัว', '{$policyText}', '{$policyHash}', '2026-10-01 00:00:00', 1)");

// Router initialization
$router = new Router();
require dirname(__DIR__) . '/config/routes.php';

function testDispatch(string $method, string $uri, array $input = [], array $headers = []): ActivationTestResponse
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
    $res = new ActivationTestResponse();
    $router->dispatch($req, $res);
    return $res;
}

$passCount = 0;
function testAssert(bool $condition, string $label): void
{
    global $passCount;
    if (!$condition) {
        throw new RuntimeException("Assertion FAILED: {$label}");
    }
    $passCount++;
    echo "PASS [T" . sprintf('%02d', $passCount) . "]: {$label}\n";
}

echo "=========================================================================\n";
echo "RYCOOPWEB — Member Activation & Login Status Safety Test Suite (T01 - T18)\n";
echo "=========================================================================\n";

// -------------------------------------------------------------------------
// T01: Inactive member login returns HTTP 403 ACCOUNT_INACTIVE
// -------------------------------------------------------------------------
Auth::logout();
$resT01 = testDispatch('POST', '/login', [
    'username' => '3210100120959',
    'password' => '00123',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT01->status === 403, "T01: Inactive member login returns HTTP 403");
testAssert(in_array($resT01->body['code'] ?? '', ['ACCOUNT_INACTIVE', 'IDENTITY_VERIFICATION_REQUIRED'], true), "T01: Error code is ACCOUNT_INACTIVE or IDENTITY_VERIFICATION_REQUIRED");
testAssert(
    str_contains($resT01->body['message'] ?? '', 'อยู่ระหว่างรอการเปิดใช้งาน'),
    "T01: Correct message: บัญชีผู้ใช้งานนี้อยู่ระหว่างรอการเปิดใช้งาน กรุณาติดต่อเจ้าหน้าที่เพื่อยืนยันตัวตน"
);

// -------------------------------------------------------------------------
// T02: Suspended member login returns HTTP 403 ACCOUNT_SUSPENDED
// -------------------------------------------------------------------------
$resT02 = testDispatch('POST', '/login', [
    'username' => '3210200456789',
    'password' => '00456',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT02->status === 403, "T02: Suspended member login returns HTTP 403");
testAssert(($resT02->body['code'] ?? '') === 'ACCOUNT_SUSPENDED', "T02: Error code is ACCOUNT_SUSPENDED");
testAssert(
    str_contains($resT02->body['message'] ?? '', 'ถูกระงับ'),
    "T02: Correct message: บัญชีผู้ใช้งานนี้ถูกระงับ กรุณาติดต่อสหกรณ์"
);

// -------------------------------------------------------------------------
// T03: Failed login with 13-digit Thai Citizen ID masks PII in login_logs
// -------------------------------------------------------------------------
$rawCitizenId = '3210100999999';
$resT03 = testDispatch('POST', '/login', [
    'username' => $rawCitizenId,
    'password' => 'WrongPassword@123',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT03->status === 401, "T03: Wrong password returns HTTP 401");

$logEntry = Database::first("SELECT email FROM login_logs WHERE status = 'failed' ORDER BY id DESC LIMIT 1");
testAssert(!empty($logEntry), "T03: Failed login logged in login_logs table");
testAssert(!str_contains($logEntry['email'], $rawCitizenId), "T03: Raw 13-digit citizen ID is NEVER written to login_logs.email");
testAssert($logEntry['email'] === '321XXXXXX9999', "T03: Citizen ID properly pseudonymized as 321XXXXXX9999");

// -------------------------------------------------------------------------
// T04: Formatted Thai Citizen ID with hyphens is also pseudonymized
// -------------------------------------------------------------------------
testDispatch('POST', '/login', [
    'username' => '3-2101-00888-88-8',
    'password' => 'WrongPass',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
$logFormatted = Database::first("SELECT email FROM login_logs WHERE status = 'failed' ORDER BY id DESC LIMIT 1");
testAssert($logFormatted['email'] === '321XXXXXX8888', "T04: Formatted Citizen ID masked to 321XXXXXX8888 in login_logs");

// -------------------------------------------------------------------------
// T05: Blocked inactive user login also pseudonymizes identifier in login_logs
// -------------------------------------------------------------------------
$logInactive = Database::first("SELECT email, failure_reason FROM login_logs WHERE status = 'locked_out' AND user_id = 10 ORDER BY id DESC LIMIT 1");
testAssert(!empty($logInactive), "T05: Inactive login blocked event recorded in login_logs");
testAssert(!str_contains($logInactive['email'], '3210100120959'), "T05: Inactive member's raw Citizen ID not leaked in login_logs");
testAssert($logInactive['email'] === '321XXXXXX0959', "T05: Inactive member's login_logs entry masked as 321XXXXXX0959");

// -------------------------------------------------------------------------
// T06: Email login pseudonymization
// -------------------------------------------------------------------------
testAssert(AuthController::pseudonymizeIdentifier('somchai@example.com') === 'so***i@example.com', "T06: Email masked with pseudonymizeIdentifier");
testAssert(AuthController::pseudonymizeIdentifier('admin') === 'admin', "T06: Staff username preserved");

// -------------------------------------------------------------------------
// T07: Unauthorized user / Guest cannot activate member account
// -------------------------------------------------------------------------
Auth::logout();
$resT07 = testDispatch('POST', '/api/admin/members/101/activate', [
    'verification_method' => 'in_person',
    'reason' => 'Guest attempt',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT07->status === 401, "T07: Guest denied member activation (HTTP 401)");

// -------------------------------------------------------------------------
// T08: Staff without members.update permission cannot activate member account
// -------------------------------------------------------------------------
$viewer = Database::first("SELECT u.*, r.slug as role_slug FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE u.id = 3");
Auth::login($viewer, true);
$resT08 = testDispatch('POST', '/api/admin/members/101/activate', [
    'verification_method' => 'in_person',
    'reason' => 'Viewer staff attempt',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT08->status === 403, "T08: Staff without update permission denied activation (HTTP 403)");

// -------------------------------------------------------------------------
// T09: Activation requires valid verification method
// -------------------------------------------------------------------------
$staff = Database::first("SELECT u.*, r.slug as role_slug FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE u.id = 2");
Auth::login($staff, true);
$resT09 = testDispatch('POST', '/api/admin/members/101/activate', [
    'verification_method' => 'invalid_method',
    'reason' => 'Testing validation',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT09->status === 422, "T09: Invalid verification method rejected (HTTP 422)");

// -------------------------------------------------------------------------
// T10: Authorized staff activates specific UAT member account (member 101, user 10)
// -------------------------------------------------------------------------
$resT10 = testDispatch('POST', '/api/admin/members/101/activate', [
    'verification_method' => 'in_person',
    'reason' => 'สมาชิกมาแสดงตนพร้อมบัตรประชาชนตัวจริงที่สำนักงานสหกรณ์',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT10->status === 200, "T10: Authorized staff activation succeeds (HTTP 200)");
testAssert(($resT10->body['new_status'] ?? '') === 'active', "T10: New status is active");

// Verify database status
$userAfter = Database::first("SELECT status FROM users WHERE id = 10");
testAssert($userAfter['status'] === 'active', "T10: User 10 status in users table is now active");

// Verify audit trail in audit_logs
$audit = Database::first("SELECT * FROM audit_logs WHERE module = 'member_management' AND action = 'activate_account' AND record_id = '101' ORDER BY id DESC LIMIT 1");
testAssert(!empty($audit), "T10: Audit trail recorded in audit_logs");
testAssert((int)$audit['user_id'] === 2, "T10: Staff actor recorded in audit log");

// Verify provenance in member_data_changes
$change = Database::first("SELECT * FROM member_data_changes WHERE member_id = 101 AND action = 'account_activation' ORDER BY id DESC LIMIT 1");
testAssert(!empty($change), "T10: Change history recorded in member_data_changes");
testAssert(str_contains($change['reason'], 'บัตรประชาชนตัวจริง'), "T10: Reason and notes recorded in member_data_changes");

// -------------------------------------------------------------------------
// T11: Newly activated member can now log in -> gated by PDPA gate
// -------------------------------------------------------------------------
Auth::logout();
$resT11 = testDispatch('POST', '/login', [
    'username' => '3210100120959',
    'password' => '00123',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT11->status === 200, "T11: Newly activated member login succeeds (HTTP 200)");
testAssert(($resT11->body['redirect'] ?? '') === '/member/privacy-ack', "T11: Member redirected to PDPA privacy gate");
testAssert(Auth::check() && (int)Auth::id() === 10, "T11: Session established for User 10");

// -------------------------------------------------------------------------
// T12: Member acknowledges PDPA policy
// -------------------------------------------------------------------------
$resT12 = testDispatch('POST', '/api/member/pdpa/acknowledge', [
    'acknowledgement' => '1',
    'policy_version' => '3.0',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT12->status === 200, "T12: PDPA policy acknowledged successfully");

// -------------------------------------------------------------------------
// T13: Financial access is BLOCKED until secure password setup is completed
// -------------------------------------------------------------------------
$resT13 = testDispatch('GET', '/api/member/dividends');
testAssert($resT13->status === 403, "T13: Financial dividends blocked before password setup (HTTP 403)");
testAssert(($resT13->body['code'] ?? '') === 'PASSWORD_CHANGE_REQUIRED', "T13: Error code is PASSWORD_CHANGE_REQUIRED");
testAssert(
    str_contains($resT13->body['message'] ?? '', 'กรุณาเปลี่ยนรหัสผ่าน'),
    "T13: Message requires password change: กรุณาเปลี่ยนรหัสผ่านเริ่มต้นเป็นรหัสผ่านส่วนตัวก่อนเข้าถึงข้อมูลทางการเงิน"
);

// -------------------------------------------------------------------------
// T14: Password change rejects member number or citizen ID as new password
// -------------------------------------------------------------------------
$resT14A = testDispatch('POST', '/api/change-password', [
    'current_password' => '00123',
    'new_password' => '00123',
    'confirm_password' => '00123',
    '_csrf_token' => Csrf::token(),
]);
testAssert(in_array($resT14A->status, [400, 422], true), "T14: Setting password to short member number 00123 rejected (HTTP 400/422)");

$resT14B = testDispatch('POST', '/api/change-password', [
    'current_password' => '00123',
    'new_password' => '3210100120959',
    'confirm_password' => '3210100120959',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT14B->status === 422, "T14: Setting password to Citizen ID rejected (HTTP 422)");

// -------------------------------------------------------------------------
// T15: Member sets up a secure personal password
// -------------------------------------------------------------------------
$resT15 = testDispatch('POST', '/api/change-password', [
    'current_password' => '00123',
    'new_password' => 'SecurePass#2026',
    'confirm_password' => 'SecurePass#2026',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT15->status === 200, "T15: Secure password change succeeds (HTTP 200)");

$pwAudit = Database::first("SELECT * FROM audit_logs WHERE module = 'auth' AND action = 'password_change' AND user_id = 10 ORDER BY id DESC LIMIT 1");
testAssert(!empty($pwAudit), "T15: Password change recorded in audit_logs");

// -------------------------------------------------------------------------
// T16: Financial access is NOW GRANTED after secure password setup
// -------------------------------------------------------------------------
$resT16 = testDispatch('GET', '/api/member/dividends');
testAssert($resT16->status === 200, "T16: Financial dividends now accessible (HTTP 200)");
testAssert(!empty($resT16->body['records']), "T16: Member dividend records retrieved");

// -------------------------------------------------------------------------
// T17: Super Admin and Staff logins remain completely functional
// -------------------------------------------------------------------------
Auth::logout();
$resT17Admin = testDispatch('POST', '/login', [
    'username' => 'admin',
    'password' => 'Admin@123456',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT17Admin->status === 200 && ($resT17Admin->body['success'] ?? false) === true, "T17: Super Admin login succeeds");
testAssert(in_array($resT17Admin->body['redirect'] ?? '', ['/admin/dashboard', '/admin/mfa/enroll', '/admin/mfa/verify'], true), "T17: Admin redirect to dashboard/MFA gate");

Auth::logout();
$resT17Staff = testDispatch('POST', '/login', [
    'username' => 'staff_admin',
    'password' => 'Staff@123456',
    'ajax' => '1',
    '_csrf_token' => Csrf::token(),
]);
testAssert($resT17Staff->status === 200 && ($resT17Staff->body['success'] ?? false) === true, "T17: Staff login succeeds");
testAssert(in_array($resT17Staff->body['redirect'] ?? '', ['/admin/members/dashboard', '/admin/mfa/enroll', '/admin/mfa/verify'], true), "T17: Member admin redirect to dashboard/MFA gate");

// -------------------------------------------------------------------------
// T18: Bulk inactive legacy member accounts remain preserved (zero mass activation)
// -------------------------------------------------------------------------
$bulkInactiveCount = (int)Database::value("SELECT COUNT(*) FROM users WHERE status = 'inactive'");
testAssert($bulkInactiveCount === 6, "T18: All 6 simulated legacy accounts remain strictly inactive (no bulk activation)");

$mappingCount = (int)Database::value("SELECT COUNT(*) FROM members m JOIN users u ON m.user_id = u.id");
testAssert($mappingCount === 8, "T18: All member-to-user mappings remain intact and preserved");

echo "=========================================================================\n";
echo "All 18 Member Activation & Login Status Safety Tests (T01 - T18) PASSED!\n";
echo "=========================================================================\n";
