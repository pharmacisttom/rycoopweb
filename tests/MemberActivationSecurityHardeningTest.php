<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\MemberManagementController;
use App\Controllers\Api\SpaApiController;
use App\Controllers\Member\DividendController;
use App\Controllers\Member\MemberPdpaController;
use App\Controllers\Member\MemberPortalController;
use App\Core\{Auth, Csrf, Database, Request, Response, Router, Session};
use App\Middlewares\{AuthMiddleware, CsrfMiddleware, FeatureFlagMiddleware, MemberMiddleware, MemberPdpaMiddleware, PermissionMiddleware, RoleMiddleware};
use App\Services\{AuditService, MemberAdminService, MemberDataException, MemberPdpaService, MemberPortalService, MemberSecurityService};

final class HardeningTestResponse extends Response
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

$sessionDir = sys_get_temp_dir() . '/rycoop-uat-member-hardening-test-sessions-' . getmypid() . '-' . bin2hex(random_bytes(4));
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
    'HTTP_USER_AGENT' => 'HardeningTestRunner/1.0',
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
        status TEXT NOT NULL DEFAULT 'inactive',
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
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        module TEXT NOT NULL,
        action TEXT NOT NULL,
        description TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE role_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        permission_id INTEGER NOT NULL
    );

    CREATE TABLE user_roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role_id INTEGER NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE members (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT NOT NULL UNIQUE,
        user_id INTEGER NULL,
        member_no TEXT NOT NULL UNIQUE,
        id_card TEXT NOT NULL UNIQUE,
        prefix TEXT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        department TEXT NULL,
        phone TEXT NULL,
        email TEXT NULL,
        address TEXT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_dividends (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        year INTEGER NOT NULL,
        details_json TEXT NOT NULL,
        imported_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_import_runs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL,
        year INTEGER NOT NULL,
        source_name TEXT NOT NULL,
        record_count INTEGER NOT NULL DEFAULT 0,
        created_count INTEGER NOT NULL DEFAULT 0,
        updated_count INTEGER NOT NULL DEFAULT 0,
        unchanged_count INTEGER NOT NULL DEFAULT 0,
        net_total REAL NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE member_data_changes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_id INTEGER NOT NULL,
        user_id INTEGER NULL,
        action TEXT NOT NULL,
        reason TEXT NOT NULL,
        before_json TEXT NULL,
        after_json TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    );

    CREATE TABLE audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NULL,
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
        ip_address TEXT NULL,
        user_agent TEXT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
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
");

// Seed Roles
$pdo->exec("
    INSERT INTO roles (id, name, slug) VALUES
    (1, 'Super Admin', 'super_admin'),
    (2, 'Staff', 'staff'),
    (3, 'Member', 'member'),
    (4, 'Member Admin', 'member_admin');
");

// Seed Permissions
$pdo->exec("
    INSERT INTO permissions (id, name, module, action) VALUES
    (1, 'View Members', 'members', 'view'),
    (2, 'Update Members', 'members', 'update'),
    (3, 'Import Dividends', 'dividends', 'import'),
    (4, 'Update Dividends', 'dividends', 'update');

    INSERT INTO role_permissions (role_id, permission_id) VALUES
    (1, 1), (1, 2), (1, 3), (1, 4),
    (4, 1), (4, 2), (4, 4);
");

// Seed Active PDPA Policy
$policyContent = 'ประกาศความเป็นส่วนตัวสำหรับสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด';
$policyHash = hash('sha256', $policyContent);
$pdo->exec("
    INSERT INTO member_privacy_policies (id, version, title, content, content_sha256, effective_at, is_active)
    VALUES (1, '3.0', 'ประกาศความเป็นส่วนตัว', '{$policyContent}', '{$policyHash}', '2026-01-01 00:00:00', 1);
");

MemberSecurityService::resetSchemaCache();

$testResults = [];
$totalPassed = 0;
$totalFailed = 0;

$runHardeningTest = function(string $title, callable $test) use (&$testResults, &$totalPassed, &$totalFailed): void
{
    try {
        $test();
        $totalPassed++;
        $testResults[] = ['title' => $title, 'status' => 'PASS', 'error' => null];
        echo " [PASS] {$title}\n";
    } catch (\Throwable $e) {
        $totalFailed++;
        $testResults[] = ['title' => $title, 'status' => 'FAIL', 'error' => $e->getMessage()];
        echo " [FAIL] {$title}: {$e->getMessage()}\n";
    }
};

// -------------------------------------------------------------------------
// Helper setup
// -------------------------------------------------------------------------

$superAdminPass = password_hash('Admin@Secured2026', PASSWORD_DEFAULT);
$pdo->exec("
    INSERT INTO users (id, uuid, name, username, email, password, status)
    VALUES (1, 'uuid-admin-1', 'Super Admin', 'superadmin', 'admin@rycoop.test', '{$superAdminPass}', 'active');
    INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);
");

$staffPass = password_hash('Staff@Secured2026', PASSWORD_DEFAULT);
$pdo->exec("
    INSERT INTO users (id, uuid, name, username, email, password, status)
    VALUES (2, 'uuid-staff-2', 'Staff Member', 'staffuser', 'staff@rycoop.test', '{$staffPass}', 'active');
    INSERT INTO user_roles (user_id, role_id) VALUES (2, 2);
");

// =========================================================================
// TEST SUITE: 15 Scenarios
// =========================================================================

// 1. Inactive member with valid verification can activate
$runHardeningTest('1. Inactive member with valid verification can activate', function() use ($pdo) {
    $hashed = password_hash('Temp@Inactive1234', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (101, 'uuid-m-101', 'นายสมศักดิ์ รักดี', '1111111111101', 'somsak101@rycoop.test', '{$hashed}', 'inactive');
        INSERT INTO user_roles (user_id, role_id) VALUES (101, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (101, 'uuid-mem-101', 101, '00101', '1111111111101', 'นาย', 'สมศักดิ์', 'รักดี', 'active');
    ");

    $res = MemberAdminService::activateAccount(101, [
        'verification_method' => 'in_person',
        'reason' => 'สมาชิกเดินทางมาแสดงตนและยืนยันตัวตนด้วยบัตรประชาชนตัวจริง ณ สำนักงานสหกรณ์',
    ]);

    if (!($res['success'] ?? false) || ($res['new_status'] ?? '') !== 'active') {
        throw new RuntimeException('activateAccount did not return success=true or new_status=active');
    }

    $u = Database::first('SELECT status, must_change_password FROM users WHERE id = 101');
    if ($u['status'] !== 'active') {
        throw new RuntimeException("Expected user status active, got {$u['status']}");
    }
    if ((int)$u['must_change_password'] !== 1) {
        throw new RuntimeException('Expected must_change_password = 1 upon activation');
    }

    $change = Database::first("SELECT * FROM member_data_changes WHERE member_id = 101 AND action = 'account_activation'");
    if (!$change) {
        throw new RuntimeException('No account_activation recorded in member_data_changes');
    }

    $audit = Database::first("SELECT * FROM audit_logs WHERE module = 'member_management' AND action = 'activate_account' AND record_id = '101'");
    if (!$audit) {
        throw new RuntimeException('No activate_account audit log found');
    }
});

// 2. Suspended member activation rejected
$runHardeningTest('2. Suspended member activation rejected', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (102, 'uuid-m-102', 'นายสมชาย ถูกระงับ', '1111111111102', 'somchai102@rycoop.test', 'hash', 'suspended');
        INSERT INTO user_roles (user_id, role_id) VALUES (102, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (102, 'uuid-mem-102', 102, '00102', '1111111111102', 'นาย', 'สมชาย', 'ถูกระงับ', 'active');
    ");

    $caught = false;
    try {
        MemberAdminService::activateAccount(102, [
            'verification_method' => 'official_document',
            'reason' => 'พยายามเปิดใช้งานบัญชีที่ถูกระงับ',
        ]);
    } catch (MemberDataException $e) {
        $caught = true;
        if (!str_contains($e->getMessage(), 'ถูกระงับ')) {
            throw new RuntimeException("Unexpected error message: {$e->getMessage()}");
        }
    }

    if (!$caught) {
        throw new RuntimeException('Expected MemberDataException when activating suspended account, but none was thrown');
    }

    $u = Database::first('SELECT status FROM users WHERE id = 102');
    if ($u['status'] !== 'suspended') {
        throw new RuntimeException("User status was altered to {$u['status']}, should stay suspended");
    }
});

// 3. Already-active member remains unchanged
$runHardeningTest('3. Already-active member remains unchanged', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (103, 'uuid-m-103', 'นางสมหวัง ปกติ', '1111111111103', 'somwang103@rycoop.test', 'hash', 'active');
        INSERT INTO user_roles (user_id, role_id) VALUES (103, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (103, 'uuid-mem-103', 103, '00103', '1111111111103', 'นาง', 'สมหวัง', 'ปกติ', 'active');
    ");

    $res = MemberAdminService::activateAccount(103, [
        'verification_method' => 'branch_visit',
        'reason' => 'เปิดใช้งานซ้ำเพื่อทดสอบ no-op',
    ]);

    if (!($res['success'] ?? false) || !($res['already_active'] ?? false)) {
        throw new RuntimeException('Expected already_active = true');
    }

    $u = Database::first('SELECT status FROM users WHERE id = 103');
    if ($u['status'] !== 'active') {
        throw new RuntimeException("User status altered to {$u['status']}");
    }
});

// 4. Staff account activation rejected
$runHardeningTest('4. Staff account activation rejected', function() use ($pdo) {
    // Case A: User with staff role only
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (104, 'uuid-m-104', 'จนท. สหกรณ์', '1111111111104', 'staff104@rycoop.test', 'hash', 'inactive');
        INSERT INTO user_roles (user_id, role_id) VALUES (104, 2);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (104, 'uuid-mem-104', 104, '00104', '1111111111104', 'นาย', 'จนท', 'สหกรณ์', 'active');
    ");

    $caught = false;
    try {
        MemberAdminService::activateAccount(104, [
            'verification_method' => 'in_person',
            'reason' => 'ทดสอบเปิดใช้งานบัญชีเจ้าหน้าที่',
        ]);
    } catch (MemberDataException $e) {
        $caught = true;
    }
    if (!$caught) {
        throw new RuntimeException('Staff activation without member role was not rejected');
    }

    // Case B: User with both member role and staff role
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (1041, 'uuid-m-1041', 'จนท. สหกรณ์มีสิทธิ์สมาชิก', '1111111111041', 'staff1041@rycoop.test', 'hash', 'inactive');
        INSERT INTO user_roles (user_id, role_id) VALUES (1041, 3), (1041, 2);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (1041, 'uuid-mem-1041', 1041, '01041', '1111111111041', 'นาย', 'จนท', 'มีสิทธิ์สมาชิก', 'active');
    ");

    $caughtStaff = false;
    try {
        MemberAdminService::activateAccount(1041, [
            'verification_method' => 'in_person',
            'reason' => 'ทดสอบเปิดใช้งานบัญชีที่มีสิทธิ์เจ้าหน้าที่',
        ]);
    } catch (MemberDataException $e) {
        $caughtStaff = true;
        if (!str_contains($e->getMessage(), 'เจ้าหน้าที่')) {
            throw new RuntimeException("Unexpected exception: {$e->getMessage()}");
        }
    }
    if (!$caughtStaff) {
        throw new RuntimeException('Dual-role staff activation was not rejected');
    }
});

// 5. Invalid verification method rejected
$runHardeningTest('5. Invalid verification method rejected (identity_verified, empty, unknown)', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (105, 'uuid-m-105', 'ทดสอบ วิธีผิด', '1111111111105', 't105@rycoop.test', 'hash', 'inactive');
        INSERT INTO user_roles (user_id, role_id) VALUES (105, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (105, 'uuid-mem-105', 105, '00105', '1111111111105', 'นาย', 'ทดสอบ', 'วิธีผิด', 'active');
    ");

    $badMethods = ['identity_verified', '', 'unknown_method', 'online_bypass', 'sms_otp'];
    foreach ($badMethods as $badMethod) {
        $caught = false;
        try {
            MemberAdminService::activateAccount(105, [
                'verification_method' => $badMethod,
                'reason' => 'ทดสอบส่งวิธีการผิด',
            ]);
        } catch (MemberDataException $e) {
            $caught = true;
        }
        if (!$caught) {
            throw new RuntimeException("Verification method '{$badMethod}' was unexpectedly accepted");
        }
    }
});

// 6. Missing verification reason rejected
$runHardeningTest('6. Missing verification reason rejected', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (106, 'uuid-m-106', 'ทดสอบ เหตุผลว่าง', '1111111111106', 't106@rycoop.test', 'hash', 'inactive');
        INSERT INTO user_roles (user_id, role_id) VALUES (106, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (106, 'uuid-mem-106', 106, '00106', '1111111111106', 'นาย', 'ทดสอบ', 'เหตุผลว่าง', 'active');
    ");

    $badReasons = ['', '   ', "\t\n"];
    foreach ($badReasons as $r) {
        $caught = false;
        try {
            MemberAdminService::activateAccount(106, [
                'verification_method' => 'in_person',
                'reason' => $r,
            ]);
        } catch (MemberDataException $e) {
            $caught = true;
        }
        if (!$caught) {
            throw new RuntimeException("Empty reason was unexpectedly accepted");
        }
    }
});

// 7. Audit logging failure rolls back transaction
$runHardeningTest('7. Audit logging failure rolls back transaction', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status)
        VALUES (107, 'uuid-m-107', 'ทดสอบ โรลแบ็ค', '1111111111107', 't107@rycoop.test', 'hash', 'inactive');
        INSERT INTO user_roles (user_id, role_id) VALUES (107, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (107, 'uuid-mem-107', 107, '00107', '1111111111107', 'นาย', 'ทดสอบ', 'โรลแบ็ค', 'active');
    ");

    AuditService::setTestFaultInjector(function() {
        throw new \RuntimeException('Simulated Audit Failure: Disk full or remote audit down');
    });

    $caught = false;
    try {
        MemberAdminService::activateAccount(107, [
            'verification_method' => 'call_verification',
            'reason' => 'โทรศัพท์ยืนยันตัวตนกับสมาชิกตามหมายเลขที่บันทึกไว้',
        ]);
    } catch (\Throwable $e) {
        $caught = true;
    } finally {
        AuditService::setTestFaultInjector(null);
    }

    if (!$caught) {
        throw new RuntimeException('Expected audit failure exception');
    }

    // Verify database transaction rolled back
    $u = Database::first('SELECT status, must_change_password FROM users WHERE id = 107');
    if ($u['status'] !== 'inactive') {
        throw new RuntimeException("Transaction was NOT rolled back: user status is {$u['status']}");
    }
    if ((int)$u['must_change_password'] !== 0) {
        throw new RuntimeException("Transaction was NOT rolled back: must_change_password is {$u['must_change_password']}");
    }
    $change = Database::first("SELECT * FROM member_data_changes WHERE member_id = 107 AND action = 'account_activation'");
    if ($change) {
        throw new RuntimeException("member_data_changes row was committed despite audit failure");
    }
});

// 8. Password changed before activation does not count
$runHardeningTest('8. Password changed before activation does not count', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
        VALUES (108, 'uuid-m-108', 'ทดสอบ รหัสเก่า', '1111111111108', 't108@rycoop.test', 'hash', 'inactive', 0);
        INSERT INTO user_roles (user_id, role_id) VALUES (108, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (108, 'uuid-mem-108', 108, '00108', '1111111111108', 'นาย', 'ทดสอบ', 'รหัสเก่า', 'active');
    ");

    // Insert historical password change prior to activation
    $pdo->exec("
        INSERT INTO audit_logs (user_id, module, action, created_at)
        VALUES ('108', 'auth', 'password_change', '2025-01-01 10:00:00');
    ");

    // Activate today
    MemberAdminService::activateAccount(108, [
        'verification_method' => 'in_person',
        'reason' => 'เปิดใช้งานและบังคับตั้งรหัสผ่านใหม่',
    ]);

    // Check if password change is required
    $required = MemberSecurityService::isPasswordChangeRequired(108);
    if (!$required) {
        throw new RuntimeException('Historical password change before activation incorrectly counted as valid password setup');
    }
});

// 9. Password change after activation clears requirement
$runHardeningTest('9. Password change after activation clears requirement', function() use ($pdo) {
    $currentHash = password_hash('TempPass@109', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
        VALUES (109, 'uuid-m-109', 'ทดสอบ เปลี่ยนรหัส', '1111111111109', 't109@rycoop.test', '{$currentHash}', 'active', 1);
        INSERT INTO user_roles (user_id, role_id) VALUES (109, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (109, 'uuid-mem-109', 109, '00109', '1111111111109', 'นาย', 'ทดสอบ', 'เปลี่ยนรหัส', 'active');
    ");

    $user = Database::first('SELECT * FROM users WHERE id = 109');
    $user['role_slug'] = 'member';
    Auth::login($user, true);

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'current_password' => 'TempPass@109',
        'new_password' => 'SecurePass#2026X',
        'confirm_password' => 'SecurePass#2026X',
        'ajax' => '1',
    ];
    $req = new Request();
    $res = new HardeningTestResponse();

    $ctl = new MemberPortalController($req, $res);
    $ctl->changePassword();

    if ($res->status !== 200 || !($res->body['success'] ?? false)) {
        throw new RuntimeException("changePassword failed with status {$res->status}: " . json_encode($res->body));
    }

    // Assert database state
    $u = Database::first('SELECT must_change_password, password FROM users WHERE id = 109');
    if ((int)$u['must_change_password'] !== 0) {
        throw new RuntimeException('must_change_password was not cleared in database');
    }
    if (!password_verify('SecurePass#2026X', $u['password'])) {
        throw new RuntimeException('New password hash not persisted in database');
    }

    if (MemberSecurityService::isPasswordChangeRequired(109)) {
        throw new RuntimeException('isPasswordChangeRequired still returned true after valid password change');
    }
});

// 10. Session flag tampering cannot bypass financial gate
$runHardeningTest('10. Session flag tampering cannot bypass financial gate', function() use ($pdo) {
    $currentHash = password_hash('Pass@110', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
        VALUES (110, 'uuid-m-110', 'ทดสอบ แฮกเซสชัน', '1111111111110', 't110@rycoop.test', '{$currentHash}', 'active', 1);
        INSERT INTO user_roles (user_id, role_id) VALUES (110, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (110, 'uuid-mem-110', 110, '00110', '1111111111110', 'นาย', 'ทดสอบ', 'แฮกเซสชัน', 'active');
    ");

    $user = Database::first('SELECT * FROM users WHERE id = 110');
    $user['role_slug'] = 'member';
    Auth::login($user, true);

    // Tamper PHP session
    Session::set('requires_password_change', false);

    // Request dividend endpoint
    $req = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $res = new HardeningTestResponse();

    $ctl = new DividendController($req, $res);
    $ctl->index();

    if ($res->status !== 403 || ($res->body['code'] ?? '') !== 'PASSWORD_CHANGE_REQUIRED') {
        throw new RuntimeException("Financial gate was bypassed by tampering session flag! Got status {$res->status}, code: " . ($res->body['code'] ?? ''));
    }
});

// 11. Direct financial API access is blocked for all financial endpoints
$runHardeningTest('11. Direct financial API access is blocked across all financial endpoints', function() use ($pdo) {
    $currentHash = password_hash('Pass@111', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
        VALUES (111, 'uuid-m-111', 'ทดสอบ ทุกเอพีไอการเงิน', '1111111111111', 't111@rycoop.test', '{$currentHash}', 'active', 1);
        INSERT INTO user_roles (user_id, role_id) VALUES (111, 3);
        INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
        VALUES (111, 'uuid-mem-111', 111, '00111', '1111111111111', 'นาย', 'ทดสอบ', 'ทุกเอพีไอ', 'active');
    ");

    $user = Database::first('SELECT * FROM users WHERE id = 111');
    $user['role_slug'] = 'member';
    Auth::login($user, true);

    $endpoints = [
        'memberDashboard',
        'memberFinancialSummary',
        'memberShares',
        'memberDeposits',
        'memberLoans',
        'memberReceipts',
    ];

    foreach ($endpoints as $method) {
        $req = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $res = new HardeningTestResponse();
        $api = new SpaApiController($req, $res);
        $api->$method();

        if ($res->status !== 403 || ($res->body['code'] ?? '') !== 'PASSWORD_CHANGE_REQUIRED') {
            throw new RuntimeException("Endpoint {$method} was not blocked! Status: {$res->status}, body: " . json_encode($res->body));
        }
    }
});

// 12. Member cannot call admin activation endpoint
$runHardeningTest('12. Member cannot call admin activation endpoint', function() use ($pdo) {
    $user = Database::first('SELECT * FROM users WHERE id = 101');
    $user['role_slug'] = 'member';
    Auth::login($user, true);

    $req = new Request([], ['verification_method' => 'in_person', 'reason' => 'พยายามเรียก endpoint admin'], [], [], ['REQUEST_METHOD' => 'POST', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $res = new HardeningTestResponse();

    $middleware = new PermissionMiddleware('members', 'update');
    $allowed = $middleware->handle($req, $res);

    if ($allowed !== false || $res->status !== 403) {
        throw new RuntimeException("Member was not blocked from admin activation route! Status: {$res->status}");
    }
});

// 13. PDPA gate remains mandatory
$runHardeningTest('13. PDPA gate remains mandatory on new login session', function() use ($pdo) {
    $user = Database::first('SELECT * FROM users WHERE id = 109');
    $user['role_slug'] = 'member';
    Auth::login($user, true);

    // New login session: nonce is created, but acknowledgment is not yet stored
    MemberPdpaService::initMemberSession($user);

    $req = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $res = new HardeningTestResponse();

    $gate = new MemberPdpaMiddleware();
    $passed = $gate->handle($req, $res);

    if ($passed !== false || $res->status !== 403 || ($res->body['code'] ?? '') !== 'PDPA_ACK_REQUIRED') {
        throw new RuntimeException("PDPA gate did not enforce policy acknowledgment on new login session! Status: {$res->status}, code: " . ($res->body['code'] ?? ''));
    }
});

// 14. Existing 2,508 inactive accounts remain unchanged
$runHardeningTest('14. Existing inactive accounts remain unchanged (no bulk activation)', function() use ($pdo) {
    // Populate batch of inactive accounts to represent bulk state
    for ($i = 200; $i < 220; $i++) {
        $pdo->exec("
            INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
            VALUES ({$i}, 'uuid-batch-{$i}', 'สมาชิก {$i}', '111111111{$i}', 'batch{$i}@rycoop.test', 'hash', 'inactive', 0);
            INSERT INTO user_roles (user_id, role_id) VALUES ({$i}, 3);
            INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
            VALUES ({$i}, 'uuid-mem-{$i}', {$i}, '00{$i}', '111111111{$i}', 'นาย', 'สมาชิก', '{$i}', 'active');
        ");
    }

    $inactiveCountBefore = (int)Database::value("SELECT COUNT(*) FROM users WHERE status = 'inactive'");

    // Activate only one specific member (200)
    MemberAdminService::activateAccount(200, [
        'verification_method' => 'in_person',
        'reason' => 'เปิดใช้งานเฉพาะรายบุคคลที่ผ่านการยืนยันตัวตน',
    ]);

    $inactiveCountAfter = (int)Database::value("SELECT COUNT(*) FROM users WHERE status = 'inactive'");
    if ($inactiveCountAfter !== ($inactiveCountBefore - 1)) {
        throw new RuntimeException("Inactive count changed unexpectedly from {$inactiveCountBefore} to {$inactiveCountAfter}");
    }

    // Verify all other accounts in batch remain strictly inactive
    $stillInactive = (int)Database::value("SELECT COUNT(*) FROM users WHERE id BETWEEN 201 AND 219 AND status = 'inactive'");
    if ($stillInactive !== 19) {
        throw new RuntimeException("Other inactive accounts were mutated! Remaining inactive: {$stillInactive} / 19");
    }
});

// 15. Existing super_admin access remains unchanged
$runHardeningTest('15. Existing super_admin access remains unchanged', function() use ($pdo) {
    $admin = Database::first('SELECT * FROM users WHERE id = 1');
    $admin['role_slug'] = 'super_admin';
    Auth::login($admin, true);

    // Verify super_admin is not subjected to member PDPA gate
    $req = new Request([], [], [], [], ['REQUEST_METHOD' => 'GET', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    $res = new HardeningTestResponse();

    $gate = new MemberPdpaMiddleware();
    $passed = $gate->handle($req, $res);
    if (!$passed) {
        throw new RuntimeException('Super Admin was incorrectly blocked by Member PDPA Gate');
    }

    // Verify super_admin has permission to view and update members
    $permView = new PermissionMiddleware('members', 'view');
    if (!$permView->handle($req, $res)) {
        throw new RuntimeException('Super Admin lost members.view permission');
    }

    $permUpdate = new PermissionMiddleware('members', 'update');
    if (!$permUpdate->handle($req, $res)) {
        throw new RuntimeException('Super Admin lost members.update permission');
    }
});

// 16. Invalid userId rejects financial access (fails closed)
$runHardeningTest('16. Invalid userId rejects financial access (fails closed)', function() {
    $badIds = [0, -1, -99, -9999];
    foreach ($badIds as $id) {
        if (!MemberSecurityService::isPasswordChangeRequired($id)) {
            throw new RuntimeException("Invalid userId {$id} unexpectedly permitted financial access");
        }
    }
});

// 17. Missing user record rejects financial access (fails closed)
$runHardeningTest('17. Missing user record rejects financial access (fails closed)', function() {
    $nonExistentIds = [999998, 999999];
    foreach ($nonExistentIds as $id) {
        if (!MemberSecurityService::isPasswordChangeRequired($id)) {
            throw new RuntimeException("Non-existent user {$id} unexpectedly permitted financial access");
        }
    }
});

// 18. Inactive or suspended user rejects financial access even if flag is 0
$runHardeningTest('18. Inactive or suspended user rejects financial access even if flag is 0', function() use ($pdo) {
    $pdo->exec("
        INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
        VALUES (301, 'uuid-m-301', 'ระงับแต่แฟล็กศูนย์', '1111111111301', 't301@rycoop.test', 'hash', 'suspended', 0);
        INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
        VALUES (302, 'uuid-m-302', 'รอเปิดแต่แฟล็กศูนย์', '1111111111302', 't302@rycoop.test', 'hash', 'inactive', 0);
    ");

    if (!MemberSecurityService::isPasswordChangeRequired(301)) {
        throw new RuntimeException('Suspended user with flag 0 unexpectedly permitted financial access');
    }
    if (!MemberSecurityService::isPasswordChangeRequired(302)) {
        throw new RuntimeException('Inactive user with flag 0 unexpectedly permitted financial access');
    }
});

// 19. Fail closed on database exceptions
$runHardeningTest('19. Fail closed on database exceptions', function() use ($pdo) {
    $prop = new ReflectionProperty(Database::class, 'instance');
    $originalPdo = $prop->getValue();

    try {
        $badPdo = new class extends PDO {
            public function __construct() {}
            public function prepare(string $query, array $options = []): \PDOStatement|false {
                throw new \PDOException('Simulated database connection loss');
            }
        };
        $prop->setValue(null, $badPdo);

        $blocked = MemberSecurityService::isPasswordChangeRequired(101);
        if (!$blocked) {
            throw new RuntimeException('isPasswordChangeRequired failed open on database exception!');
        }
    } finally {
        $prop->setValue(null, $originalPdo);
    }
});

// 20. Missing must_change_password column never grants access without verifiable activation & password change
$runHardeningTest('20. Missing must_change_password column never grants access without verifiable activation & password change', function() use ($pdo) {
    MemberSecurityService::setCachedHasMustChangeColumn(false);

    try {
        // Case A: Unactivated member (no activation record in member_data_changes or audit_logs)
        $pdo->exec("
            INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
            VALUES (303, 'uuid-m-303', 'สมาชิกไม่มีประวัติเปิดใช้งาน', '1111111111303', 't303@rycoop.test', 'hash', 'active', 0);
            INSERT INTO user_roles (user_id, role_id) VALUES (303, 3);
            INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
            VALUES (303, 'uuid-mem-303', 303, '00303', '1111111111303', 'นาย', 'ไม่มี', 'ประวัติ', 'active');
        ");

        if (!MemberSecurityService::isPasswordChangeRequired(303)) {
            throw new RuntimeException('Missing column allowed unverified member without activation record to access financial data!');
        }

        // Case B: Activated member but never changed password after activation
        $pdo->exec("
            INSERT INTO users (id, uuid, name, username, email, password, status, must_change_password)
            VALUES (304, 'uuid-m-304', 'เปิดใช้งานแต่ยังไม่เปลี่ยนรหัส', '1111111111304', 't304@rycoop.test', 'hash', 'active', 0);
            INSERT INTO user_roles (user_id, role_id) VALUES (304, 3);
            INSERT INTO members (id, uuid, user_id, member_no, id_card, prefix, first_name, last_name, status)
            VALUES (304, 'uuid-mem-304', 304, '00304', '1111111111304', 'นาย', 'เปิดแล้ว', 'ไม่เปลี่ยนรหัส', 'active');
            INSERT INTO member_data_changes (member_id, user_id, action, reason, after_json, created_at)
            VALUES (304, 1, 'account_activation', 'เปิดใช้งาน', '{}', '2026-11-01 10:00:00');
        ");

        if (!MemberSecurityService::isPasswordChangeRequired(304)) {
            throw new RuntimeException('Missing column allowed activated member with no password change to access financial data!');
        }

        // Case C: Activated member who completed password change AFTER activation
        $pdo->exec("
            INSERT INTO audit_logs (user_id, module, action, created_at)
            VALUES ('304', 'auth', 'password_change', '2026-11-01 10:05:00');
        ");

        if (MemberSecurityService::isPasswordChangeRequired(304)) {
            throw new RuntimeException('Missing column incorrectly blocked member who completed post-activation password setup');
        }
    } finally {
        MemberSecurityService::resetSchemaCache();
    }
});

// 21. Admin authentication and password change remain intact and unbroken
$runHardeningTest('21. Admin authentication and password change remain intact and unbroken', function() use ($pdo) {
    $admin = Database::first('SELECT * FROM users WHERE id = 1');
    $admin['role_slug'] = 'super_admin';
    Auth::login($admin, true);

    // Validate password change for admin
    $err = MemberSecurityService::validatePasswordSecurity('AdminNewPass@2026!', 1);
    if ($err !== null) {
        throw new RuntimeException("validatePasswordSecurity rejected valid admin password: {$err}");
    }

    // Changing admin password clears requirement flag safely
    MemberSecurityService::clearPasswordChangeRequired(1);

    $u = Database::first('SELECT must_change_password FROM users WHERE id = 1');
    if ((int)$u['must_change_password'] !== 0) {
        throw new RuntimeException('Admin must_change_password was altered unexpectedly');
    }
});

echo "\n=======================================================\n";
echo "SUMMARY: {$totalPassed} PASSED, {$totalFailed} FAILED (TOTAL " . ($totalPassed + $totalFailed) . ")\n";
echo "=======================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
