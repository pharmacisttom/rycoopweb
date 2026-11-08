<?php

declare(strict_types=1);

ob_start();

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

use App\Controllers\Admin\ControlCenterApiController;
use App\Core\{Auth, Database, Request, Response};

final class ApiContractCapturedResponse extends Response
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

function assertContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
    echo "PASS: {$message}\n";
}

$sessionDir = dirname(__DIR__) . '/storage/tmp/contract-test-sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
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
        two_factor_confirmed_at TEXT,
        last_login_at TEXT,
        locked_until TEXT,
        created_at TEXT,
        deleted_at TEXT
    );

    CREATE TABLE user_mfa_recovery_codes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        code_hash TEXT NOT NULL,
        used_at TEXT NULL,
        created_at TEXT NULL
    );

    CREATE TABLE member_profile_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_no TEXT,
        member_id INTEGER,
        status TEXT,
        created_at TEXT
    );

    CREATE TABLE public_content_revisions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        section TEXT,
        state TEXT,
        effective_on TEXT,
        note TEXT,
        created_at TEXT
    );

    CREATE TABLE led_check_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        member_no TEXT,
        status TEXT,
        reviewed_at TEXT
    );

    CREATE TABLE approval_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_no TEXT,
        module TEXT,
        action_key TEXT,
        target_type TEXT,
        target_id TEXT,
        reason TEXT,
        status TEXT,
        requested_by INTEGER,
        created_at TEXT
    );

    CREATE TABLE roles (
        id INTEGER PRIMARY KEY,
        name TEXT,
        slug TEXT UNIQUE,
        description TEXT,
        created_at TEXT
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

    CREATE TABLE user_roles (
        user_id INTEGER,
        role_id INTEGER
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

    CREATE TABLE schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    INSERT INTO roles VALUES (1, 'Super Admin', 'super_admin', 'Administrator', '2026-10-01 00:00:00');
    INSERT INTO users VALUES (1, 'admin', 'Super Administrator', 'admin@example.invalid', 'IT', 'active', 1, 'SECRET', '2026-10-01 00:00:00', '2026-10-28 10:00:00', NULL, '2026-01-01 00:00:00', NULL);
    INSERT INTO user_roles VALUES (1, 1);
");

Auth::login([
    'id' => 1,
    'username' => 'admin',
    'name' => 'Super Administrator',
    'email' => 'admin@example.invalid',
    'role_slug' => 'super_admin',
], true);

function testEndpoint(string $methodName, string $endpointPath, array $expectedRootKeys): void
{
    $req = new Request();
    $resp = new ApiContractCapturedResponse();
    $controller = new ControlCenterApiController($req, $resp);
    $controller->{$methodName}();

    assertContract($resp->status === 200, "{$endpointPath} returns HTTP 200");
    assertContract($resp->body['success'] === true, "{$endpointPath} response has success === true");
    assertContract(isset($resp->body['correlation_id']), "{$endpointPath} response contains correlation_id");
    assertContract(isset($resp->body['data']) && is_array($resp->body['data']), "{$endpointPath} response contains data object envelope");

    foreach ($expectedRootKeys as $key) {
        assertContract(isset($resp->body[$key]), "{$endpointPath} has backward-compatible root key: '{$key}'");
        assertContract(isset($resp->body['data'][$key]), "{$endpointPath} has forward-compatible data key: '{$key}'");
    }
}

echo "--- Testing Admin Control Center API Contract Matrix ---\n";

testEndpoint('summary', '/api/admin/dashboard/summary', ['kpis', 'pending_breakdown']);
testEndpoint('trends', '/api/admin/dashboard/trends', ['charts', 'filter_days']);
testEndpoint('operations', '/api/admin/operations/overview', ['summary', 'queues']);
testEndpoint('departments', '/api/admin/departments', ['staff_departments', 'pending_by_department']);
testEndpoint('accessPermissions', '/api/admin/access/permissions', ['db_roles', 'enterprise_role_map', 'permission_catalog']);
testEndpoint('securityOverview', '/api/admin/security/overview', ['health_score', 'disclaimer']);
testEndpoint('securityMfa', '/api/admin/security/mfa', ['summary', 'accounts']);
testEndpoint('backups', '/api/admin/backups', ['freshness_status', 'retention_policy']);
testEndpoint('systemHealth', '/api/admin/system/health', ['overall_status', 'resources', 'services']);
testEndpoint('reportsCatalog', '/api/admin/reports/catalog', ['catalog']);

echo "\nAll 10 Admin API contract endpoints passed dual-envelope verification!\n";
