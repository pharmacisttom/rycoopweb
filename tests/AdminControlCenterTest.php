<?php

declare(strict_types=1);

// Isolated SQLite memory test suite for RYCOOPWEB 3.0 Admin Control Center.
require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\Admin\ControlCenterApiController;
use App\Core\{Auth, Database, Request, Response};
use App\Services\AdminControlCenterService;

final class ControlCenterCapturedResponse extends Response
{
    public int $status = 200;
    public array $body = [];

    public function json(array $data, int $status = 200): void
    {
        $this->body = $data;
        $this->status = $status;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $this->status = $status;
    }
}

$sessionDirectory = dirname(__DIR__) . '/storage/tmp/acc-test-sessions';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDirectory);
putenv('SESSION_SECURE=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/admin/dashboard/summary',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
];
$_GET = $_POST = $_FILES = [];

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);

$pdo->exec("
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    username TEXT,
    name TEXT,
    email TEXT,
    department TEXT,
    status TEXT,
    two_factor_enabled INTEGER DEFAULT 0,
    two_factor_secret TEXT,
    two_factor_recovery_codes TEXT,
    two_factor_confirmed_at TEXT,
    last_login_at TEXT,
    locked_until TEXT,
    created_at TEXT,
    deleted_at TEXT
);
CREATE TABLE roles (
    id INTEGER PRIMARY KEY,
    name TEXT,
    slug TEXT,
    description TEXT,
    updated_at TEXT
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
    member_no TEXT,
    first_name TEXT,
    last_name TEXT,
    org_name TEXT,
    status TEXT
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
CREATE TABLE security_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    event_type TEXT,
    severity TEXT,
    ip_address TEXT,
    user_agent TEXT,
    details TEXT,
    created_at TEXT
);

INSERT INTO roles VALUES
    (1, 'สมาชิกสหกรณ์', 'member', 'Member role', '2026-10-01 00:00:00'),
    (2, 'บรรณาธิการข่าว', 'custom_editor', 'Custom news editor', '2026-10-01 00:00:00'),
    (3, 'ผู้ตรวจสอบกิจการ', 'auditor', 'Internal auditor', '2026-10-01 00:00:00'),
    (4, 'ผู้ดูแลระบบสูงสุด', 'super_admin', 'Super administrator', '2026-10-01 00:00:00');

INSERT INTO users VALUES
    (1, 'member01', 'Member User', 'member@example.invalid', 'รพ.ระยอง', 'active', 0, NULL, NULL, NULL, '2026-10-28 08:00:00', NULL, '2026-01-01 00:00:00', NULL),
    (2, 'editor01', 'Custom Editor', 'editor@example.invalid', 'สสจ.ระยอง', 'active', 1, 'ENCRYPTED_TOTP_SECRET_DO_NOT_LEAK', '[\"HASH_CODE_1\",\"HASH_CODE_2\"]', '2026-10-10 09:00:00', '2026-10-28 09:00:00', NULL, '2026-02-01 00:00:00', NULL),
    (3, 'auditor01', 'Internal Auditor', 'auditor@example.invalid', 'คณะกรรมการตรวจสอบ', 'active', 1, 'ENCRYPTED_TOTP_SECRET_2', '[\"HASH_CODE_A\"]', '2026-10-12 10:00:00', '2026-10-28 10:00:00', NULL, '2026-03-01 00:00:00', NULL),
    (4, 'superadmin', 'Super Admin', 'admin@example.invalid', 'สหกรณ์ออมทรัพย์สาธารณสุขระยอง', 'active', 1, 'ENCRYPTED_TOTP_SECRET_3', '[\"HASH_1\",\"HASH_2\",\"HASH_3\"]', '2026-10-01 10:00:00', '2026-10-28 11:00:00', NULL, '2026-01-01 00:00:00', NULL);

INSERT INTO user_roles VALUES (1, 1), (2, 2), (3, 3), (4, 4);
INSERT INTO permissions VALUES (1, 'news', 'view'), (2, 'news', 'update');
INSERT INTO role_permissions VALUES (2, 1), (2, 2);

INSERT INTO members VALUES
    (1, '00001', 'สมชาย', 'ใจดี', 'โรงพยาบาลระยอง', 'active'),
    (2, '00002', 'สมหญิง', 'รักงาน', 'สำนักงานสาธารณสุขจังหวัดระยอง', 'active');

INSERT INTO audit_logs (user_id, module, action, record_id, old_values, new_values, ip_address, user_agent, created_at) VALUES
    (4, 'auth', 'login_success', '4', NULL, '{\"password\":\" PlaintextPassword123! \",\"two_factor_secret\":\"BASE32SECRET\",\"note\":\"ok\"}', '127.0.0.1', 'PHPUnit', datetime('now'));
");

$passed = 0;
$results = [];
function assertAcc(bool $condition, string $label): void
{
    global $passed, $results;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $label);
    }
    $passed++;
    $results[] = "PASS: {$label}";
}

function loginAs(?int $id, string $role = '', bool $twoFactorVerified = true): void
{
    Auth::logout();
    if ($id !== null) {
        Auth::login([
            'id' => $id,
            'username' => 'user' . $id,
            'name' => 'User ' . $id,
            'email' => "user{$id}@example.invalid",
            'role_slug' => $role,
        ], $twoFactorVerified);
    }
}

function callEndpoint(string $method): ControlCenterCapturedResponse
{
    $request = new Request();
    $response = new ControlCenterCapturedResponse();
    $controller = new ControlCenterApiController($request, $response);
    $controller->{$method}();
    return $response;
}

try {
    // 1. Anonymous user must receive 401
    loginAs(null);
    $res = callEndpoint('summary');
    assertAcc($res->status === 401 && ($res->body['success'] ?? true) === false, 'Anonymous user denied (401) on /api/admin/dashboard/summary');

    // 2. Pending 2FA session must receive 401
    loginAs(4, 'super_admin', false);
    $res = callEndpoint('summary');
    assertAcc($res->status === 401, 'Pending 2FA session denied (401) on /api/admin/dashboard/summary');

    // 3. Member role must NEVER gain admin privileges (403)
    loginAs(1, 'member');
    foreach (['summary', 'securityOverview', 'securityMfa', 'auditLogs', 'backups', 'systemHealth'] as $ep) {
        $res = callEndpoint($ep);
        assertAcc($res->status === 403, "Member role strictly denied (403) on {$ep}");
    }

    // 4. Custom role (news.view only) least-privilege checks
    loginAs(2, 'custom_editor');
    $resSummary = callEndpoint('summary');
    assertAcc($resSummary->status === 200, 'Custom editor with news.view can view basic dashboard summary');
    foreach (['securityOverview', 'securityMfa', 'auditLogs', 'backups', 'systemHealth', 'accessPermissions'] as $restrictedEp) {
        $resRestricted = callEndpoint($restrictedEp);
        assertAcc($resRestricted->status === 403, "Custom editor denied (403) on restricted endpoint {$restrictedEp}");
    }

    // 5. Auditor read-only access to security, MFA, audit logs, backups, health, reports
    loginAs(3, 'auditor');
    foreach (['summary', 'trends', 'securityOverview', 'securityMfa', 'auditLogs', 'backups', 'systemHealth', 'accessPermissions', 'departments', 'reportsCatalog'] as $auditorEp) {
        $resAuditor = callEndpoint($auditorEp);
        assertAcc($resAuditor->status === 200 && ($resAuditor->body['success'] ?? false) === true, "Auditor permitted (200) on {$auditorEp}");
    }

    // 6. Super Admin full access + verify no secret leakage in MFA endpoint
    loginAs(4, 'super_admin');
    $mfaRes = callEndpoint('securityMfa');
    assertAcc($mfaRes->status === 200, 'Super Admin permitted (200) on securityMfa');
    $serializedMfa = json_encode($mfaRes->body, JSON_UNESCAPED_UNICODE);
    assertAcc(!str_contains($serializedMfa, 'ENCRYPTED_TOTP_SECRET'), 'MFA endpoint never leaks two_factor_secret');
    assertAcc(!str_contains($serializedMfa, 'HASH_CODE_1'), 'MFA endpoint never leaks raw recovery code hashes');
    $editorMfaRow = array_values(array_filter($mfaRes->body['data']['staff'] ?? [], fn($r) => (int)$r['id'] === 2))[0] ?? null;
    assertAcc($editorMfaRow !== null && $editorMfaRow['mfa_enrolled'] === true && $editorMfaRow['remaining_recovery_codes'] === 2, 'MFA endpoint accurately counts remaining recovery codes (2)');

    // 7. Verify Audit Logs automatic secret redaction
    $auditRes = callEndpoint('auditLogs');
    assertAcc($auditRes->status === 200, 'Super Admin permitted (200) on auditLogs');
    $serializedAudit = json_encode($auditRes->body, JSON_UNESCAPED_UNICODE);
    assertAcc(!str_contains($serializedAudit, 'PlaintextPassword123!'), 'Audit logs redact plaintext passwords');
    assertAcc(!str_contains($serializedAudit, 'BASE32SECRET'), 'Audit logs redact two_factor_secret values');
    assertAcc(str_contains($serializedAudit, '[REDACTED]'), 'Audit logs replace sensitive keys with [REDACTED]');

    // 8. Verify honest non-fabricated metrics
    $summaryBody = callEndpoint('summary')->body['data'] ?? [];
    $kpiByKey = array_column($summaryBody['kpis'] ?? [], null, 'key');
    assertAcc(($kpiByKey['total_users']['value'] ?? null) === 4, 'KPI total_users matches exact DB count (4)');
    assertAcc(($kpiByKey['active_staff']['value'] ?? null) === 3, 'KPI active_staff matches exact non-member count (3)');
    assertAcc(($kpiByKey['active_members']['value'] ?? null) === 2, 'KPI active_members matches exact active member count (2)');

    $results[] = "Admin Control Center test suite: {$passed} checks passed.";
} finally {
    Auth::logout();
    (new ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
}
echo implode(PHP_EOL, $results) . PHP_EOL;
