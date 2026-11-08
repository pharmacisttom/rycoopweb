<?php

declare(strict_types=1);

ob_start();

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

use App\Controllers\Admin\ControlCenterApiController;
use App\Core\{Auth, Database, Request, Response};
use App\Services\AdminControlCenterService;

final class HealthCapturedResponse extends Response
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

$sessionDir = dirname(__DIR__) . '/storage/tmp/health-test-sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}
putenv('SESSION_SAVE_PATH=' . $sessionDir);
putenv('SESSION_SECURE=false');

$_SERVER = [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/api/admin/system/health',
    'SCRIPT_NAME' => '/index.php',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
];
$_GET = $_POST = $_FILES = [];

function assertHealth(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
    echo "PASS: {$message}\n";
}

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

    CREATE TABLE schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
        (1, 'ผู้ตรวจสอบกิจการ', 'auditor', 'Internal auditor', '2026-10-01 00:00:00'),
        (2, 'ผู้ดูแลระบบสูงสุด', 'super_admin', 'Super administrator', '2026-10-01 00:00:00');

    INSERT INTO users VALUES
        (1, 'auditor01', 'Internal Auditor', 'auditor@example.invalid', 'คณะกรรมการตรวจสอบ', 'active', 1, 'SEC_1', '2026-10-12 10:00:00', '2026-10-28 10:00:00', NULL, '2026-03-01 00:00:00', NULL),
        (2, 'superadmin', 'Super Admin', 'admin@example.invalid', 'ไอที', 'active', 1, 'SEC_2', '2026-10-01 10:00:00', '2026-10-28 11:00:00', NULL, '2026-01-01 00:00:00', NULL);

    INSERT INTO user_roles VALUES (1, 1), (2, 2);
    INSERT INTO permissions VALUES (1, 'system', 'health'), (2, 'system', 'manage');
    INSERT INTO role_permissions VALUES (1, 1), (2, 1), (2, 2);
");

echo "--- 1. Testing AdminControlCenterService::getSystemHealth() directly ---\n";
$health = AdminControlCenterService::getSystemHealth(false);

assertHealth(is_array($health), "Health payload is an array");
assertHealth(isset($health['overall_status']), "Payload contains overall_status");
assertHealth(isset($health['overall_label']), "Payload contains overall_label");
assertHealth(isset($health['summary_text']), "Payload contains summary_text");
assertHealth(isset($health['services']) && is_array($health['services']), "Payload contains services list");
assertHealth(isset($health['resources']) && is_array($health['resources']), "Payload contains resources object");
assertHealth(isset($health['scheduled_jobs']) && is_array($health['scheduled_jobs']), "Payload contains scheduled_jobs list");
assertHealth(isset($health['recent_errors']) && is_array($health['recent_errors']), "Payload contains recent_errors list");

$res = $health['resources'];
assertHealth(array_key_exists('php_memory_usage_mb', $res), "Resources contains php_memory_usage_mb");
assertHealth(array_key_exists('php_memory_limit', $res), "Resources contains php_memory_limit");
assertHealth(array_key_exists('disk_free_gb', $res), "Resources contains disk_free_gb");
assertHealth(array_key_exists('disk_used_percent', $res), "Resources contains disk_used_percent");
assertHealth(array_key_exists('cpu_load', $res), "Resources contains cpu_load");

// Verify no secrets leaked in health payload
$serialized = json_encode($health);
assertHealth(!str_contains($serialized, 'DB_PASS'), "No DB_PASS leaked in health payload");
assertHealth(!str_contains($serialized, 'APP_KEY'), "No APP_KEY leaked in health payload");
assertHealth(!str_contains($serialized, 'SEC_1'), "No 2FA secret leaked in health payload");

echo "\n--- 2. Testing ControlCenterApiController Dual-Envelope REST Response ---\n";
Auth::login([
    'id' => 1,
    'username' => 'auditor01',
    'name' => 'Internal Auditor',
    'email' => 'auditor@example.invalid',
    'role_slug' => 'auditor',
], true);

$req = new Request();
$resp = new HealthCapturedResponse();
$controller = new ControlCenterApiController($req, $resp);
$controller->systemHealth();

assertHealth($resp->status === 200, "API returned HTTP 200");
assertHealth($resp->body['success'] === true, "Response has success === true");
assertHealth(isset($resp->body['correlation_id']), "Response contains correlation_id");
assertHealth(isset($resp->body['data']) && is_array($resp->body['data']), "Response contains data envelope");

// Dual-Envelope checks: Both root level and data level must contain resources and services
assertHealth(isset($resp->body['resources']) && is_array($resp->body['resources']), "Root level contains resources for legacy frontend callers");
assertHealth(isset($resp->body['services']) && is_array($resp->body['services']), "Root level contains services for legacy frontend callers");
assertHealth(isset($resp->body['data']['resources']) && is_array($resp->body['data']['resources']), "Data level contains resources for standardized callers");
assertHealth(isset($resp->body['data']['services']) && is_array($resp->body['data']['services']), "Data level contains services for standardized callers");

assertHealth($resp->body['resources'] === $resp->body['data']['resources'], "Root resources exactly matches data.resources");

echo "\nSystemHealthRuntimeTest passed all assertions successfully.\n";
