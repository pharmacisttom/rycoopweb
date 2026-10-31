<?php

declare(strict_types=1);

/**
 * Comprehensive Automated Security Test Suite for RYCOOPWEB 3.0 Dual-Control (Maker-Checker),
 * Server-Side Step-Up MFA, RBAC Enforcement, and Non-Transactional Backup Restore Gate.
 */
putenv('APP_KEY=isolated-dual-control-key-not-for-production-0001');
putenv('SESSION_SECURE=false');

$sessionDirectory = dirname(__DIR__) . '/storage/tmp/dual-control-test-sessions';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDirectory);

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\AccessController;
use App\Controllers\Admin\BackupController;
use App\Controllers\Admin\ControlCenterApiController;
use App\Controllers\Admin\MfaController;
use App\Controllers\Admin\StaffManagementController;
use App\Core\{Auth, Database, Request, Response, Session};
use App\Exceptions\SecurityGateException;
use App\Services\{AdminControlCenterService, DualControlSecurityService, TwoFactorService};

final class DualControlCapturedResponse extends Response
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

$tempBackupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rycoop_dc_backups_' . bin2hex(random_bytes(6));
mkdir($tempBackupDir, 0700, true);
$tempJournalFile = $tempBackupDir . DIRECTORY_SEPARATOR . 'restore_execution_journal.jsonl';
DualControlSecurityService::setJournalPath($tempJournalFile);

$validDumpFile = 'backup_2026_10_28_valid.sql';
$otherDumpFile = 'backup_2026_10_28_other.sql';
$tamperedDumpFile = 'backup_2026_10_28_tampered.sql';
file_put_contents(
    $tempBackupDir . DIRECTORY_SEPARATOR . $validDumpFile,
    "-- Valid SQL Dump\nCREATE TABLE IF NOT EXISTS restored_canary (id INTEGER PRIMARY KEY, note TEXT);\nINSERT INTO restored_canary (note) VALUES ('restored_ok');\n"
);
file_put_contents(
    $tempBackupDir . DIRECTORY_SEPARATOR . $otherDumpFile,
    "-- Other SQL Dump\nCREATE TABLE IF NOT EXISTS other_canary (id INTEGER PRIMARY KEY);\n"
);
file_put_contents(
    $tempBackupDir . DIRECTORY_SEPARATOR . $tamperedDumpFile,
    "-- Original untampered dump\nSELECT 1;\n"
);

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

// Create isolated schema matching MySQL 8.0 + Migration 025
$pdo->exec("
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    uuid TEXT,
    username TEXT,
    name TEXT,
    email TEXT,
    password TEXT,
    department TEXT,
    status TEXT DEFAULT 'active',
    session_version INTEGER DEFAULT 1,
    two_factor_enabled INTEGER DEFAULT 0,
    two_factor_secret TEXT,
    two_factor_confirmed_at TEXT,
    two_factor_last_used_slice INTEGER NULL,
    last_login_at TEXT,
    last_login_ip TEXT,
    created_at TEXT,
    updated_at TEXT,
    deleted_at TEXT
);
CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    updated_at TEXT
);
CREATE TABLE user_roles (
    user_id INTEGER NOT NULL,
    role_id INTEGER NOT NULL
);
CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    module TEXT NOT NULL,
    action TEXT NOT NULL,
    UNIQUE(module, action)
);
CREATE TABLE role_permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    permission_id INTEGER NOT NULL,
    UNIQUE(role_id, permission_id)
);
CREATE TABLE members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    member_no TEXT
);
CREATE TABLE approval_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_no TEXT NOT NULL UNIQUE,
    module TEXT NOT NULL,
    action_key TEXT NOT NULL,
    target_type TEXT NULL,
    target_id TEXT NULL,
    payload_json TEXT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    requested_by INTEGER NOT NULL,
    reviewed_by INTEGER NULL,
    reviewed_at TEXT NULL,
    review_note TEXT NULL,
    expires_at TEXT NULL,
    executed_at TEXT NULL,
    created_at TEXT NULL,
    updated_at TEXT NULL
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
    created_at TEXT NULL
);
CREATE TABLE rate_limit_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rate_key TEXT NOT NULL,
    ip_address TEXT NOT NULL,
    window_started_at TEXT NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL,
    UNIQUE(rate_key, ip_address)
);
CREATE TABLE login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    email TEXT NULL,
    status TEXT NULL,
    failure_reason TEXT NULL,
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    created_at TEXT NULL
);
");

// Seed base roles from 001_initial_schema.sql, then load Migration 025 RBAC seeds
$pdo->exec("
INSERT INTO roles (name, slug, description) VALUES
    ('ผู้ดูแลระบบสูงสุด', 'super_admin', 'Super Administrator'),
    ('สมาชิกสหกรณ์', 'member', 'Coop Member');
");

$sql025 = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/025_create_admin_control_center_tables.sql');
if (preg_match_all('/INSERT\s+INTO\s+`(roles|permissions|role_permissions)`[\s\S]*?;/i', $sql025, $matches)) {
    foreach ($matches[0] as $stmtSql) {
        $pdo->exec($stmtSql);
    }
}

// Create TOTP secrets for test accounts
$totpSecrets = [
    1 => TwoFactorService::generateSecret(32), // super_admin #1 (Maker/Admin)
    2 => TwoFactorService::generateSecret(32), // super_admin #2 (Checker/Admin)
    3 => TwoFactorService::generateSecret(32), // it_admin (Maker for backup restore)
    4 => TwoFactorService::generateSecret(32), // security_admin (Checker for backup restore)
    5 => TwoFactorService::generateSecret(32), // auditor (Read-only)
    6 => TwoFactorService::generateSecret(32), // staff (Minimal)
];

$roleIdsBySlug = [];
foreach ($pdo->query("SELECT id, slug FROM roles")->fetchAll() as $r) {
    $roleIdsBySlug[$r['slug']] = (int) $r['id'];
}

$insertUserStmt = $pdo->prepare("
    INSERT INTO users (id, uuid, username, name, email, status, session_version, two_factor_enabled, two_factor_secret, two_factor_confirmed_at, created_at)
    VALUES (?, ?, ?, ?, ?, 'active', 1, ?, ?, '2026-10-01 09:00:00', '2026-01-01 00:00:00')
");
$insertUserRoleStmt = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");

$testUsers = [
    [1, 'superadmin1', 'Super Admin One', 'sa1@rayongcoop.test', 'super_admin', 1],
    [2, 'superadmin2', 'Super Admin Two', 'sa2@rayongcoop.test', 'super_admin', 1],
    [3, 'itadmin1', 'IT Admin Maker', 'it@rayongcoop.test', 'it_admin', 1],
    [4, 'secadmin1', 'Security Admin Checker', 'sec@rayongcoop.test', 'security_admin', 1],
    [5, 'auditor1', 'Internal Auditor', 'auditor@rayongcoop.test', 'auditor', 1],
    [6, 'staff1', 'General Staff', 'staff@rayongcoop.test', 'staff', 1],
    [7, 'member1', 'Coop Member', 'member@rayongcoop.test', 'member', 0],
];

foreach ($testUsers as [$uid, $uname, $name, $email, $roleSlug, $mfaEnabled]) {
    $encSecret = isset($totpSecrets[$uid]) ? TwoFactorService::encryptSecret($totpSecrets[$uid]) : null;
    $insertUserStmt->execute([
        $uid,
        bin2hex(random_bytes(16)),
        $uname,
        $name,
        $email,
        $mfaEnabled,
        $encSecret,
    ]);
    $insertUserRoleStmt->execute([$uid, $roleIdsBySlug[$roleSlug]]);
}

$passed = 0;
$results = [];
$issuedTotpCodes = [];

function assertGate(bool $condition, string $label): void
{
    global $passed, $results;
    if (!$condition) {
        throw new RuntimeException('SECURITY GATE TEST FAILED: ' . $label);
    }
    $passed++;
    $results[] = "PASS [{$passed}]: {$label}";
}

function loginGateUser(?int $userId, bool $twoFactorVerified = true): void
{
    Auth::logout();
    Session::start();
    Session::remove('mfa_step_up_verified_at');
    Session::remove('mfa_step_up_expires_at');
    Session::remove('mfa_step_up_user_id');
    Session::remove('mfa_step_up_session_version');
    Session::remove('mfa_step_up_slice');
    Session::remove('mfa_step_up_scope');
    Session::remove('mfa_step_up_consumed');

    if ($userId === null) {
        return;
    }

    $user = Database::first(
        "SELECT u.*, r.slug AS role_slug
         FROM users u
         JOIN user_roles ur ON ur.user_id = u.id
         JOIN roles r ON r.id = ur.role_id
         WHERE u.id = ?",
        [$userId]
    );
    Auth::login($user, $twoFactorVerified, false);
}

/**
 * Generate a fresh, unconsumed 6-digit TOTP code for $userId by setting two_factor_last_used_slice
 * to ($currentSlice - 1) so the test can deterministically test fresh vs replayed TOTP codes.
 */
function issueFreshTotpCode(int $userId, int $sliceOffset = 0): string
{
    global $totpSecrets, $issuedTotpCodes;
    $secret = $totpSecrets[$userId];
    $currentSlice = (int) floor(time() / 30) + $sliceOffset;
    Database::execute("UPDATE users SET two_factor_last_used_slice = ? WHERE id = ?", [$currentSlice - 1, $userId]);
    $calc = new ReflectionMethod(TwoFactorService::class, 'calculateCode');
    $code = (string) $calc->invoke(null, $secret, $currentSlice);
    $issuedTotpCodes[] = $code;
    return $code;
}

function makeGateRequest(string $method, string $uri, array $post = [], array $query = []): Request
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_POST = $post;
    $_GET = $query;
    return new Request();
}

function callMfaStepUp(array $post): DualControlCapturedResponse
{
    $req = makeGateRequest('POST', '/api/auth/mfa/step-up', $post);
    $res = new DualControlCapturedResponse();
    (new MfaController($req, $res))->stepUp();
    return $res;
}

function callControlCenter(string $action, array $post = [], array $query = [], ?string $idArg = null): DualControlCapturedResponse
{
    $method = str_starts_with($action, 'approvalsList') ? 'GET' : 'POST';
    $req = makeGateRequest($method, '/api/admin/' . $action, $post, $query);
    $res = new DualControlCapturedResponse();
    $controller = new ControlCenterApiController($req, $res);
    if ($idArg !== null) {
        $controller->{$action}($idArg);
    } else {
        $controller->{$action}();
    }
    return $res;
}

try {
    // =========================================================================
    // 1. DEFAULT KILL-SWITCH POLICY VERIFICATION
    // =========================================================================
    assertGate(
        config('security.dual_control.backup_restore_execution_enabled') === false,
        'Default config security.dual_control.backup_restore_execution_enabled is strictly false'
    );
    assertGate(
        DualControlSecurityService::isRestoreExecutionEnabled() === false,
        'DualControlSecurityService::isRestoreExecutionEnabled() defaults to false'
    );

    // =========================================================================
    // 2. UNAUTHENTICATED, PENDING-2FA & LEAST-PRIVILEGE BYPASS BLOCKING
    // =========================================================================
    loginGateUser(null);
    assertGate(callMfaStepUp(['code' => '123456'])->status === 401, 'Anonymous user blocked (401) on POST /api/auth/mfa/step-up');
    assertGate(callControlCenter('approvalsList')->status === 401, 'Anonymous user blocked (401) on GET /api/admin/approvals');
    assertGate(callControlCenter('createRestoreRequest', ['file' => $validDumpFile, 'reason' => 'Unauthorized restore attempt'])->status === 401, 'Anonymous user blocked (401) on POST /api/admin/backups/restore-request');
    assertGate(callControlCenter('executeRestore', ['approval_request_id' => 1, 'file' => $validDumpFile])->status === 401, 'Anonymous user blocked (401) on POST /api/admin/backups/restore-execute');

    // Pending login-time 2FA session (not yet verified)
    loginGateUser(1, false);
    assertGate(callMfaStepUp(['code' => '123456'])->status === 401, 'Pending-2FA session blocked (401) on POST /api/auth/mfa/step-up');
    assertGate(callControlCenter('createRestoreRequest', ['file' => $validDumpFile, 'reason' => 'Pending 2FA bypass attempt'])->status === 401, 'Pending-2FA session blocked (401) on POST /api/admin/backups/restore-request');

    // Member role blocked from all Dual-Control endpoints
    loginGateUser(7, true);
    assertGate(callControlCenter('approvalsList')->status === 403, 'Member role blocked (403) on GET /api/admin/approvals');
    assertGate(callControlCenter('createRestoreRequest', ['file' => $validDumpFile, 'reason' => 'Member restore request bypass'])->status === 403, 'Member role blocked (403) on POST /api/admin/backups/restore-request');
    assertGate(callControlCenter('executeRestore', ['approval_request_id' => 1, 'file' => $validDumpFile])->status === 403, 'Member role blocked (403) on POST /api/admin/backups/restore-execute');

    // General staff & Auditor blocked from creating or approving backup restore requests
    foreach ([5 => 'auditor', 6 => 'staff'] as $uid => $rSlug) {
        loginGateUser($uid, true);
        $resCreate = callControlCenter('createApprovalRequest', [
            'action_key' => 'backup.restore.request',
            'target_type' => 'backup_archive',
            'target_id' => $validDumpFile,
            'reason' => "Unauthorized {$rSlug} restore request attempt",
        ]);
        assertGate(
            $resCreate->status === 403 && ($resCreate->body['code'] ?? '') === 'MAKER_PERMISSION_DENIED',
            "Role '{$rSlug}' blocked (403 MAKER_PERMISSION_DENIED) from creating backup.restore.request"
        );
    }

    // =========================================================================
    // 3. SEPARATION OF DUTIES (MAKER VS CHECKER ROLES)
    // =========================================================================
    // security_admin (Checker-only) MUST NOT be able to create backup.restore.request
    loginGateUser(4, true);
    $secCreateRes = callControlCenter('createApprovalRequest', [
        'action_key' => 'backup.restore.request',
        'target_type' => 'backup_archive',
        'target_id' => $validDumpFile,
        'reason' => 'Security Admin trying to act as Maker',
    ]);
    assertGate(
        $secCreateRes->status === 403 && ($secCreateRes->body['code'] ?? '') === 'MAKER_PERMISSION_DENIED',
        'security_admin (Checker-only) is blocked (403 MAKER_PERMISSION_DENIED) from creating backup.restore.request'
    );

    // it_admin (Maker) creates two valid backup.restore.request records (#1 and #2)
    loginGateUser(3, true);
    $itRequest1 = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'DR rehearsal restore verification for Q4 audit (#1)',
        'backup_archive',
        $validDumpFile,
        ['ticket' => 'CHG-2026-0101', 'password' => 'ShouldBeRedacted123!'],
        3600,
        $tempBackupDir
    );
    $req1Id = (int) $itRequest1['id'];
    $itRequest2 = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'Second restore request to test single-use Step-Up consumption (#2)',
        'backup_archive',
        $validDumpFile,
        ['ticket' => 'CHG-2026-0102'],
        3600,
        $tempBackupDir
    );
    $req2Id = (int) $itRequest2['id'];

    assertGate(
        $req1Id > 0 && $itRequest1['status'] === 'pending' && (int) $itRequest1['requested_by'] === 3,
        'it_admin (Maker) successfully creates pending backup.restore.request with SHA-256 pinning'
    );
    assertGate(
        !empty($itRequest1['payload']['file_sha256'])
        && $itRequest1['payload']['file_sha256'] === hash_file('sha256', $tempBackupDir . DIRECTORY_SEPARATOR . $validDumpFile),
        'Created approval request pins exact SHA-256 hash of target backup SQL file'
    );

    // it_admin (Maker-only) MUST NOT be able to approve backup.restore.request
    $itCode = issueFreshTotpCode(3);
    $itApproveRes = callControlCenter('approveRequest', ['review_note' => 'IT Admin trying to approve', 'totp_code' => $itCode], [], (string) $req1Id);
    assertGate(
        $itApproveRes->status === 403 && ($itApproveRes->body['code'] ?? '') === 'CHECKER_PERMISSION_DENIED',
        'it_admin (Maker-only) is strictly blocked (403 CHECKER_PERMISSION_DENIED) from approving backup.restore.request'
    );

    // =========================================================================
    // 4. SERVER-SIDE STEP-UP MFA FRESHNESS, REPLAY & SINGLE-USE CONSUMPTION
    // =========================================================================
    loginGateUser(4, true);
    assertGate(Auth::check() === true, 'security_admin has completed normal login-time 2FA');

    // Attempt to approve using only login-time MFA -> MUST fail with 403 STEP_UP_MFA_REQUIRED
    $noStepUpApprove = callControlCenter('approveRequest', ['review_note' => 'Approve without Step-Up MFA'], [], (string) $req1Id);
    assertGate(
        $noStepUpApprove->status === 403 && ($noStepUpApprove->body['code'] ?? '') === 'STEP_UP_MFA_REQUIRED',
        'Login-time MFA alone is REJECTED (403 STEP_UP_MFA_REQUIRED) when approving Dual-Control request'
    );

    // Attempt Step-Up MFA with invalid format / wrong code
    $badFormatStepUp = callMfaStepUp(['code' => '12ab']);
    assertGate(
        $badFormatStepUp->status === 422 && ($badFormatStepUp->body['code'] ?? '') === 'STEP_UP_MFA_FORMAT_INVALID',
        'Non-6-digit Step-Up MFA code rejected (422 STEP_UP_MFA_FORMAT_INVALID)'
    );

    $wrongCodeStepUp = callMfaStepUp(['code' => '000000', 'scope' => 'dual_control.approve']);
    assertGate(
        $wrongCodeStepUp->status === 403 && ($wrongCodeStepUp->body['code'] ?? '') === 'STEP_UP_MFA_INVALID',
        'Invalid 6-digit Step-Up MFA code rejected (403 STEP_UP_MFA_INVALID)'
    );

    // Perform valid Step-Up MFA via POST /api/auth/mfa/step-up
    $validSecCode = issueFreshTotpCode(4);
    $okStepUp = callMfaStepUp(['code' => $validSecCode, 'scope' => 'dual_control.approve']);
    assertGate(
        $okStepUp->status === 200 && ($okStepUp->body['code'] ?? '') === 'STEP_UP_MFA_VERIFIED',
        'Valid 6-digit TOTP code succeeds on POST /api/auth/mfa/step-up (200 STEP_UP_MFA_VERIFIED)'
    );

    // Attempt to REPLAY the exact same 6-digit TOTP code in the same time slice -> MUST fail
    $replayStepUp = callMfaStepUp(['code' => $validSecCode, 'scope' => 'dual_control.approve']);
    assertGate(
        $replayStepUp->status === 403 && ($replayStepUp->body['code'] ?? '') === 'STEP_UP_MFA_INVALID',
        'Replaying the same 6-digit TOTP code in the same time slice is BLOCKED (403 STEP_UP_MFA_INVALID)'
    );

    // Simulate expired Step-Up MFA in server session (> 300 seconds old)
    Session::set('mfa_step_up_verified_at', time() - 360);
    $expiredStepUpApprove = callControlCenter('approveRequest', ['review_note' => 'Approve with expired Step-Up'], [], (string) $req1Id);
    assertGate(
        $expiredStepUpApprove->status === 403 && ($expiredStepUpApprove->body['code'] ?? '') === 'STEP_UP_MFA_EXPIRED',
        'Expired server-side Step-Up MFA (> 300s) is REJECTED (403 STEP_UP_MFA_EXPIRED)'
    );

    // Re-verify fresh Step-Up MFA for security_admin and test Target/Action mismatch before approving
    $freshSecCode = issueFreshTotpCode(4);
    assertGate(callMfaStepUp(['code' => $freshSecCode, 'scope' => 'dual_control.approve'])->status === 200, 'Re-verified fresh Step-Up MFA for security_admin');

    $targetMismatchApprove = callControlCenter('approveRequest', [
        'review_note' => 'Wrong target check',
        'expected_target' => $otherDumpFile,
    ], [], (string) $req1Id);
    assertGate(
        $targetMismatchApprove->status === 409 && ($targetMismatchApprove->body['code'] ?? '') === 'TARGET_MISMATCH',
        'Approving with mismatched expected_target is REJECTED (409 TARGET_MISMATCH)'
    );

    $actionMismatchApprove = callControlCenter('approveRequest', [
        'review_note' => 'Wrong action check',
        'expected_action' => 'roles.manage',
    ], [], (string) $req1Id);
    assertGate(
        $actionMismatchApprove->status === 409 && ($actionMismatchApprove->body['code'] ?? '') === 'ACTION_MISMATCH',
        'Approving with mismatched expected_action is REJECTED (409 ACTION_MISMATCH)'
    );

    // Approve request #1 with valid Checker (security_admin #4 != Maker it_admin #3) + fresh Step-Up MFA
    $approvedRes1 = callControlCenter('approveRequest', [
        'review_note' => 'Verified SHA-256 and change ticket CHG-2026-0101',
        'expected_action' => 'backup.restore.request',
        'expected_target' => $validDumpFile,
    ], [], (string) $req1Id);
    assertGate(
        $approvedRes1->status === 200
        && ($approvedRes1->body['data']['status'] ?? '') === 'approved'
        && (int) ($approvedRes1->body['data']['reviewed_by'] ?? 0) === 4,
        'security_admin (Checker) approves it_admin (Maker) request with fresh Step-Up MFA'
    );

    // Single-use consumption check: attempting to approve request #2 using the already-consumed Step-Up session MUST fail!
    $reusedStepUpApprove = callControlCenter('approveRequest', [
        'review_note' => 'Attempting to reuse consumed Step-Up session on request #2',
    ], [], (string) $req2Id);
    assertGate(
        $reusedStepUpApprove->status === 403 && ($reusedStepUpApprove->body['code'] ?? '') === 'STEP_UP_MFA_ALREADY_CONSUMED',
        'Single-use Step-Up MFA session cannot be reused for a second critical action (403 STEP_UP_MFA_ALREADY_CONSUMED)'
    );

    // =========================================================================
    // 5. SELF-APPROVAL STRICTLY FORBIDDEN (MAKER !== CHECKER, INCLUDING SUPER_ADMIN)
    // =========================================================================
    loginGateUser(1, true); // super_admin #1
    $sa1Request = DualControlSecurityService::createRequest(
        1,
        'backup.restore.request',
        'Super Admin #1 initiating backup restore request',
        'backup_archive',
        $validDumpFile,
        ['initiated_by' => 'super_admin_1'],
        3600,
        $tempBackupDir
    );
    $sa1ReqId = (int) $sa1Request['id'];
    assertGate($sa1ReqId > 0 && (int) $sa1Request['requested_by'] === 1, 'super_admin #1 creates Dual-Control request as Maker');

    // super_admin #1 completes fresh Step-Up MFA and attempts to approve their own request -> MUST BE BLOCKED!
    $sa1Totp = issueFreshTotpCode(1);
    $sa1SelfApprove = callControlCenter('approveRequest', [
        'review_note' => 'Super Admin #1 trying to self-approve own request',
        'totp_code' => $sa1Totp,
    ], [], (string) $sa1ReqId);
    assertGate(
        $sa1SelfApprove->status === 403 && ($sa1SelfApprove->body['code'] ?? '') === 'SELF_APPROVAL_FORBIDDEN',
        'Self-Approval is STRICTLY FORBIDDEN even for super_admin (403 SELF_APPROVAL_FORBIDDEN)'
    );

    // Verify request remains 'pending' after blocked self-approval attempt
    $sa1RowAfterSelfAttempt = Database::first("SELECT status, reviewed_by FROM approval_requests WHERE id = ?", [$sa1ReqId]);
    assertGate(
        ($sa1RowAfterSelfAttempt['status'] ?? '') === 'pending' && $sa1RowAfterSelfAttempt['reviewed_by'] === null,
        'Request remains pending with reviewed_by = NULL after blocked super_admin self-approval attempt'
    );

    // Distinct super_admin #2 (Checker != Maker) with fresh Step-Up MFA can approve super_admin #1's request
    loginGateUser(2, true);
    $sa2Totp = issueFreshTotpCode(2);
    $sa2ApproveRes = callControlCenter('approveRequest', [
        'review_note' => 'Independent review by Super Admin #2',
        'totp_code' => $sa2Totp,
    ], [], (string) $sa1ReqId);
    assertGate(
        $sa2ApproveRes->status === 200
        && ($sa2ApproveRes->body['data']['status'] ?? '') === 'approved'
        && (int) ($sa2ApproveRes->body['data']['reviewed_by'] ?? 0) === 2,
        'Distinct super_admin #2 (Checker != Maker) successfully approves super_admin #1 request'
    );

    // =========================================================================
    // 6. DUPLICATE APPROVAL & CONCURRENT APPROVAL RACE PROTECTION
    // =========================================================================
    $sa2TotpDup = issueFreshTotpCode(2);
    $duplicateApproveRes = callControlCenter('approveRequest', [
        'review_note' => 'Duplicate approval attempt on already-approved request',
        'totp_code' => $sa2TotpDup,
    ], [], (string) $sa1ReqId);
    assertGate(
        $duplicateApproveRes->status === 409 && ($duplicateApproveRes->body['code'] ?? '') === 'APPROVAL_NOT_PENDING',
        'Duplicate approval on an already-approved request is REJECTED (409 APPROVAL_NOT_PENDING)'
    );

    // =========================================================================
    // 7. EXPIRED APPROVAL ENFORCEMENT (AT APPROVAL TIME & AT EXECUTION TIME)
    // =========================================================================
    loginGateUser(3, true); // it_admin Maker
    $expiringReq = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'Request that will expire before Checker approves',
        'backup_archive',
        $validDumpFile,
        [],
        300,
        $tempBackupDir
    );
    $expiringReqId = (int) $expiringReq['id'];
    // Backdate expires_at to 10 minutes in the past
    Database::execute("UPDATE approval_requests SET expires_at = ? WHERE id = ?", [date('Y-m-d H:i:s', time() - 600), $expiringReqId]);

    loginGateUser(4, true); // security_admin Checker
    $secExpCode = issueFreshTotpCode(4);
    $expiredApproveRes = callControlCenter('approveRequest', [
        'review_note' => 'Trying to approve an expired request',
        'totp_code' => $secExpCode,
    ], [], (string) $expiringReqId);
    assertGate(
        $expiredApproveRes->status === 410 && ($expiredApproveRes->body['code'] ?? '') === 'APPROVAL_EXPIRED',
        'Approving an expired request is REJECTED (410 APPROVAL_EXPIRED)'
    );
    assertGate(
        Database::value("SELECT status FROM approval_requests WHERE id = ?", [$expiringReqId]) === 'expired',
        'Expired request status is automatically transitioned to expired in database'
    );

    // Also test executing a restore whose approval expired after approval
    Database::execute("UPDATE approval_requests SET expires_at = ? WHERE id = ?", [date('Y-m-d H:i:s', time() - 60), $sa1ReqId]);
    loginGateUser(1, true);
    $sa1ExecExpCode = issueFreshTotpCode(1);
    try {
        DualControlSecurityService::executeApprovedRestore($sa1ReqId, 1, $validDumpFile, $sa1ExecExpCode, $tempBackupDir, true);
        assertGate(false, 'Should have thrown APPROVAL_EXPIRED during restore execution');
    } catch (SecurityGateException $e) {
        assertGate(
            $e->getHttpStatus() === 410 && $e->getErrorCode() === 'APPROVAL_EXPIRED',
            'Executing a restore after approval expiration is REJECTED (410 APPROVAL_EXPIRED)'
        );
    }

    // =========================================================================
    // 8. TARGET MISMATCH & TOCTOU SHA-256 FILE TAMPERING BLOCKED AT RESTORE
    // =========================================================================
    // Attempt to use approved request #1 (which was approved for $validDumpFile) to restore $otherDumpFile
    loginGateUser(3, true);
    $itExecCode1 = issueFreshTotpCode(3);
    try {
        DualControlSecurityService::executeApprovedRestore($req1Id, 3, $otherDumpFile, $itExecCode1, $tempBackupDir, true);
        assertGate(false, 'Should have thrown TARGET_MISMATCH');
    } catch (SecurityGateException $e) {
        assertGate(
            $e->getHttpStatus() === 403 && $e->getErrorCode() === 'TARGET_MISMATCH',
            'Attempting to restore a different backup file than approved is BLOCKED (403 TARGET_MISMATCH)'
        );
    }

    // Create & approve a request for $tamperedDumpFile, then modify the file on disk before restore execution (TOCTOU)
    loginGateUser(3, true);
    $tamperReq = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'Restore request for TOCTOU integrity test',
        'backup_archive',
        $tamperedDumpFile,
        [],
        3600,
        $tempBackupDir
    );
    $tamperReqId = (int) $tamperReq['id'];

    loginGateUser(4, true);
    $secTamperCode = issueFreshTotpCode(4);
    DualControlSecurityService::approveRequest($tamperReqId, 4, 'Approved before file tampering', $secTamperCode, 'backup.restore.request', $tamperedDumpFile);

    // Tamper with the SQL file on disk after approval!
    file_put_contents($tempBackupDir . DIRECTORY_SEPARATOR . $tamperedDumpFile, "-- TAMPERED MALICIOUS SQL\nDROP TABLE users;\n");

    loginGateUser(3, true);
    $itTamperExecCode = issueFreshTotpCode(3);
    try {
        DualControlSecurityService::executeApprovedRestore($tamperReqId, 3, $tamperedDumpFile, $itTamperExecCode, $tempBackupDir, true);
        assertGate(false, 'Should have thrown BACKUP_FILE_INTEGRITY_MISMATCH');
    } catch (SecurityGateException $e) {
        assertGate(
            $e->getHttpStatus() === 409 && $e->getErrorCode() === 'BACKUP_FILE_INTEGRITY_MISMATCH',
            'Modifying backup SQL file after approval (TOCTOU) is BLOCKED by SHA-256 check (409 BACKUP_FILE_INTEGRITY_MISMATCH)'
        );
    }

    // =========================================================================
    // 9. UNAUTHORIZED RESTORE & DEFAULT POLICY KILL-SWITCH (423 LOCKED)
    // =========================================================================
    // 9.1 Calling BackupController@restoreBackup without approval_request_id -> 403 APPROVAL_REQUIRED
    loginGateUser(1, true);
    $rawBackupReq = makeGateRequest('POST', '/admin/backups/restore', ['file' => $validDumpFile]);
    $rawBackupRes = new DualControlCapturedResponse();
    (new BackupController($rawBackupReq, $rawBackupRes))->restoreBackup();
    assertGate(
        $rawBackupRes->status === 403 && ($rawBackupRes->body['code'] ?? '') === 'APPROVAL_REQUIRED',
        'Direct POST /admin/backups/restore without approval_request_id is BLOCKED (403 APPROVAL_REQUIRED)'
    );

    // 9.2 Calling executeApprovedRestore on unapproved (pending) request #2 -> 403 APPROVAL_NOT_APPROVED
    $sa1PendingExecCode = issueFreshTotpCode(1);
    try {
        DualControlSecurityService::executeApprovedRestore($req2Id, 1, $validDumpFile, $sa1PendingExecCode, $tempBackupDir, true);
        assertGate(false, 'Should have thrown APPROVAL_NOT_APPROVED');
    } catch (SecurityGateException $e) {
        assertGate(
            $e->getHttpStatus() === 403 && $e->getErrorCode() === 'APPROVAL_NOT_APPROVED',
            'Attempting to execute restore on a pending/unapproved request is BLOCKED (403 APPROVAL_NOT_APPROVED)'
        );
    }

    // 9.3 Calling executeApprovedRestore on fully approved request #1 while BACKUP_RESTORE_EXECUTION_ENABLED=false (default!)
    loginGateUser(3, true);
    $itDefaultGateCode = issueFreshTotpCode(3);
    try {
        // Note: $executionEnabledOverride = null -> uses default config (false)
        DualControlSecurityService::executeApprovedRestore($req1Id, 3, $validDumpFile, $itDefaultGateCode, $tempBackupDir, null);
        assertGate(false, 'Should have thrown RESTORE_EXECUTION_DISABLED_BY_POLICY');
    } catch (SecurityGateException $e) {
        assertGate(
            $e->getHttpStatus() === 423 && $e->getErrorCode() === 'RESTORE_EXECUTION_DISABLED_BY_POLICY',
            'Even with valid Dual-Control approval + fresh Step-Up MFA, Restore Execution is LOCKED by default (423 RESTORE_EXECUTION_DISABLED_BY_POLICY)'
        );
    }

    // Verify restored_canary table was NOT created while kill-switch is false
    $canaryTableExists = Database::value("SELECT name FROM sqlite_master WHERE type='table' AND name='restored_canary'");
    assertGate($canaryTableExists === false || $canaryTableExists === null, 'SQL dump is NOT executed while BACKUP_RESTORE_EXECUTION_ENABLED is false');
    assertGate(
        Database::value("SELECT executed_at FROM approval_requests WHERE id = ?", [$req1Id]) === null,
        'Approval request executed_at remains NULL when blocked by default policy kill-switch'
    );

    // =========================================================================
    // 10. TWO-PHASE NON-TRANSACTIONAL RESTORE EXECUTION & DURABLE JOURNAL REPLAY GATE
    // =========================================================================
    // Now test controlled execution ($executionEnabledOverride = true) on approved request #1
    $itLiveExecCode = issueFreshTotpCode(3);
    $restoreResult = DualControlSecurityService::executeApprovedRestore(
        $req1Id,
        3,
        $validDumpFile,
        $itLiveExecCode,
        $tempBackupDir,
        true
    );
    assertGate(
        ($restoreResult['executed'] ?? false) === true && ($restoreResult['request_id'] ?? 0) === $req1Id,
        'Two-Phase Restore Execution succeeds when Dual-Control approved + Step-Up MFA verified + Execution explicitly enabled'
    );
    assertGate(
        (int) Database::value("SELECT COUNT(*) FROM restored_canary WHERE note = 'restored_ok'") === 1,
        'SQL dump statements executed during Phase 2'
    );
    assertGate(
        Database::value("SELECT executed_at FROM approval_requests WHERE id = ?", [$req1Id]) !== null,
        'approval_requests.executed_at is stamped and reconciled after restore completion'
    );
    assertGate(
        file_exists($tempJournalFile) && str_contains((string) file_get_contents($tempJournalFile), '"phase":"CLAIMED"') && str_contains((string) file_get_contents($tempJournalFile), '"phase":"COMPLETED"'),
        'Durable filesystem journal records both CLAIMED (Phase 1) and COMPLETED (Phase 2) entries'
    );

    // Simulate non-transactional SQL dump side-effect where the restored SQL dump overwrote approval_requests.executed_at back to NULL!
    Database::execute("UPDATE approval_requests SET executed_at = NULL WHERE id = ?", [$req1Id]);
    $itReplayRestoreCode = issueFreshTotpCode(3);
    try {
        DualControlSecurityService::executeApprovedRestore($req1Id, 3, $validDumpFile, $itReplayRestoreCode, $tempBackupDir, true);
        assertGate(false, 'Should have thrown APPROVAL_ALREADY_EXECUTED from durable journal');
    } catch (SecurityGateException $e) {
        assertGate(
            $e->getHttpStatus() === 409 && $e->getErrorCode() === 'APPROVAL_ALREADY_EXECUTED',
            'Durable filesystem journal blocks replay of a restore approval even if SQL dump overwrote executed_at in DB (409 APPROVAL_ALREADY_EXECUTED)'
        );
    }

    // =========================================================================
    // 11. ROLE / PERMISSION & STAFF SECURITY MUTATIONS REQUIRE STEP-UP MFA
    // =========================================================================
    loginGateUser(1, true); // super_admin #1 (login-time 2FA verified, but NO fresh Step-Up MFA)
    $roleCreateReqNoStepUp = makeGateRequest('POST', '/api/admin/access/roles', [
        'name' => 'Test Custom Role',
        'slug' => 'test_custom_role',
        'description' => 'Testing Step-Up enforcement',
        'permissions' => ['news.view'],
    ]);
    $roleCreateResNoStepUp = new DualControlCapturedResponse();
    (new AccessController($roleCreateReqNoStepUp, $roleCreateResNoStepUp))->createRole();
    assertGate(
        $roleCreateResNoStepUp->status === 403 && ($roleCreateResNoStepUp->body['code'] ?? '') === 'STEP_UP_MFA_REQUIRED',
        'POST /api/admin/access/roles (createRole) rejects login-only MFA and requires fresh Step-Up MFA (403 STEP_UP_MFA_REQUIRED)'
    );

    // StaffManagementController@updateRole without Step-Up MFA -> 403 STEP_UP_MFA_REQUIRED
    $staffRoleReqNoStepUp = makeGateRequest('POST', '/api/admin/staff/6/role', [
        'role_id' => $roleIdsBySlug['auditor'],
        'department' => 'Audit Dept',
    ]);
    $staffRoleResNoStepUp = new DualControlCapturedResponse();
    (new StaffManagementController($staffRoleReqNoStepUp, $staffRoleResNoStepUp))->updateRole('6');
    assertGate(
        $staffRoleResNoStepUp->status === 403 && ($staffRoleResNoStepUp->body['code'] ?? '') === 'STEP_UP_MFA_REQUIRED',
        'POST /api/admin/staff/{id}/role (updateRole) rejects login-only MFA and requires fresh Step-Up MFA (403 STEP_UP_MFA_REQUIRED)'
    );

    // StaffManagementController@updateRole WITH fresh Step-Up MFA -> 200 OK
    $sa1StaffRoleCode = issueFreshTotpCode(1);
    $staffRoleReqWithStepUp = makeGateRequest('POST', '/api/admin/staff/6/role', [
        'version' => \App\Services\AccessService::staffState(6)['version'],
        'role_id' => $roleIdsBySlug['auditor'],
        'department' => 'Audit Dept',
        'totp_code' => $sa1StaffRoleCode,
    ]);
    $staffRoleResWithStepUp = new DualControlCapturedResponse();
    (new StaffManagementController($staffRoleReqWithStepUp, $staffRoleResWithStepUp))->updateRole('6');
    assertGate(
        $staffRoleResWithStepUp->status === 200 && ($staffRoleResWithStepUp->body['success'] ?? false) === true,
        'POST /api/admin/staff/{id}/role succeeds when fresh Step-Up MFA is provided'
    );

    // =========================================================================
    // 11B. REJECT, CANCEL & CONCURRENT APPROVAL RACE CONDITION SIMULATION
    // =========================================================================
    // Maker (it_admin #3) creates a request to test non-Maker cancel block & Checker rejection
    loginGateUser(3, true);
    $rejectableReq = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'Request to test non-maker cancel block and checker rejection',
        'backup_archive',
        $validDumpFile,
        [],
        3600,
        $tempBackupDir
    );
    $rejectableReqId = (int) $rejectableReq['id'];

    // Non-maker (security_admin #4) cannot cancel Maker #3's request -> 403 NOT_REQUEST_MAKER
    loginGateUser(4, true);
    $nonMakerCancelRes = callControlCenter('cancelRequest', ['reason' => 'Checker trying to cancel Maker request'], [], (string) $rejectableReqId);
    assertGate(
        $nonMakerCancelRes->status === 403 && ($nonMakerCancelRes->body['code'] ?? '') === 'NOT_REQUEST_MAKER',
        'Non-Maker cannot cancel another user approval request (403 NOT_REQUEST_MAKER)'
    );

    // Checker (security_admin #4) rejects the pending request -> status becomes 'rejected'
    $rejectRes = callControlCenter('rejectRequest', ['review_note' => 'Rejected due to maintenance window reschedule'], [], (string) $rejectableReqId);
    assertGate(
        $rejectRes->status === 200 && ($rejectRes->body['data']['status'] ?? '') === 'rejected',
        'Checker successfully rejects pending Dual-Control request (status = rejected)'
    );

    // Attempting to approve an already-rejected request -> 409 APPROVAL_NOT_PENDING
    $secCodeAfterReject = issueFreshTotpCode(4);
    $approveAfterRejectRes = callControlCenter('approveRequest', ['review_note' => 'Try approve rejected', 'totp_code' => $secCodeAfterReject], [], (string) $rejectableReqId);
    assertGate(
        $approveAfterRejectRes->status === 409 && ($approveAfterRejectRes->body['code'] ?? '') === 'APPROVAL_NOT_PENDING',
        'Rejected request cannot be approved afterwards (409 APPROVAL_NOT_PENDING)'
    );

    // Maker (it_admin #3) creates and cancels own pending request -> status becomes 'cancelled'
    loginGateUser(3, true);
    $cancellableReq = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'Request that Maker will cancel before review',
        'backup_archive',
        $validDumpFile,
        [],
        3600,
        $tempBackupDir
    );
    $cancellableReqId = (int) $cancellableReq['id'];
    $cancelRes = callControlCenter('cancelRequest', ['reason' => 'Maker cancelled before review'], [], (string) $cancellableReqId);
    assertGate(
        $cancelRes->status === 200 && ($cancelRes->body['data']['status'] ?? '') === 'cancelled',
        'Maker can cancel own pending approval request (status = cancelled)'
    );

    // Simulate Concurrent Approval Race Condition:
    // Use a SQLite BEFORE UPDATE trigger on users (which runs during inline Step-Up MFA verification inside approveRequest,
    // AFTER approval_requests was read as 'pending' and BEFORE the atomic CAS UPDATE on approval_requests)
    // to simulate another concurrent Checker transaction winning the race!
    loginGateUser(3, true);
    $raceReq = DualControlSecurityService::createRequest(
        3,
        'backup.restore.request',
        'Request to test concurrent approval CAS race condition',
        'backup_archive',
        $validDumpFile,
        [],
        3600,
        $tempBackupDir
    );
    $raceReqId = (int) $raceReq['id'];

    loginGateUser(4, true);
    $secRaceCode = issueFreshTotpCode(4);

    $pdo->exec("
        CREATE TEMP TRIGGER simulate_concurrent_checker_race
        AFTER UPDATE OF two_factor_last_used_slice ON users
        BEGIN
            UPDATE approval_requests
            SET status = 'approved', reviewed_by = 2, reviewed_at = CURRENT_TIMESTAMP
            WHERE id = {$raceReqId} AND status = 'pending';
        END;
    ");

    $concurrentRaceRes = callControlCenter('approveRequest', [
        'review_note' => 'Concurrent checker losing atomic CAS race',
        'totp_code' => $secRaceCode,
    ], [], (string) $raceReqId);
    $pdo->exec("DROP TRIGGER IF EXISTS simulate_concurrent_checker_race;");

    assertGate(
        $concurrentRaceRes->status === 409 && ($concurrentRaceRes->body['code'] ?? '') === 'CONCURRENT_APPROVAL_CONFLICT',
        'Atomic CAS UPDATE detects and blocks concurrent approval race condition (409 CONCURRENT_APPROVAL_CONFLICT)'
    );

    // =========================================================================
    // 12. AUDIT LOGS, CORRELATION ID & ZERO SECRET LEAKAGE VERIFICATION
    // =========================================================================
    $auditRows = Database::query("SELECT * FROM audit_logs ORDER BY id ASC");
    assertGate(count($auditRows) >= 10, 'Security actions and Dual-Control transitions are recorded in audit_logs');

    $serializedAudit = json_encode($auditRows, JSON_UNESCAPED_UNICODE);
    $serializedApprovals = json_encode(Database::query("SELECT * FROM approval_requests"), JSON_UNESCAPED_UNICODE);
    $journalRaw = (string) file_get_contents($tempJournalFile);

    assertGate(
        str_contains($serializedAudit, 'correlation_id'),
        'Audit log records include correlation_id for end-to-end traceability'
    );
    assertGate(
        !str_contains($serializedApprovals, 'ShouldBeRedacted123!') && str_contains($serializedApprovals, '[REDACTED]'),
        'approval_requests.payload_json redacts sensitive keys with [REDACTED]'
    );

    // Ensure NO TOTP secrets or issued 6-digit OTP codes appear in audit_logs, approval_requests, or journal
    foreach ($totpSecrets as $uid => $rawBase32Secret) {
        assertGate(
            !str_contains($serializedAudit, $rawBase32Secret)
            && !str_contains($serializedApprovals, $rawBase32Secret)
            && !str_contains($journalRaw, $rawBase32Secret),
            "User #{$uid} TOTP secret never appears in audit_logs, approval_requests, or restore journal"
        );
    }

    foreach (array_unique($issuedTotpCodes) as $otpCode) {
        assertGate(
            !str_contains($serializedAudit, '"code":"' . $otpCode . '"')
            && !str_contains($serializedAudit, '"totp_code":"' . $otpCode . '"')
            && !str_contains($serializedApprovals, $otpCode)
            && !str_contains($journalRaw, $otpCode),
            "Issued 6-digit OTP code ({$otpCode}) is never stored in audit_logs, approval_requests, or restore journal"
        );
    }

    $results[] = "=========================================================================";
    $results[] = "Dual-Control & Step-Up MFA Security Gate Suite: {$passed} checks passed.";
} finally {
    Auth::logout();
    DualControlSecurityService::setJournalPath(null);
    (new ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
    @unlink($tempBackupDir . DIRECTORY_SEPARATOR . $validDumpFile);
    @unlink($tempBackupDir . DIRECTORY_SEPARATOR . $otherDumpFile);
    @unlink($tempBackupDir . DIRECTORY_SEPARATOR . $tamperedDumpFile);
    @unlink($tempJournalFile);
    @rmdir($tempBackupDir);
}

echo implode(PHP_EOL, $results) . PHP_EOL;
