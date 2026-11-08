<?php

declare(strict_types=1);

ob_start();

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Core/Helpers.php';

use App\Controllers\Admin\ControlCenterApiController;
use App\Core\{Auth, Database, Request, Response};

final class PermBoundaryCapturedResponse extends Response
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

function assertBoundary(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
    echo "PASS: {$message}\n";
}

$sessionDir = dirname(__DIR__) . '/storage/tmp/boundary-test-sessions';
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

    INSERT INTO roles VALUES
        (1, 'สมาชิก', 'member', 'Member', '2026-10-01 00:00:00'),
        (2, 'เจ้าหน้าที่ประชาสัมพันธ์', 'pr_officer', 'PR Officer', '2026-10-01 00:00:00'),
        (3, 'ผู้ดูแลระบบสูงสุด', 'super_admin', 'Super Admin', '2026-10-01 00:00:00');

    INSERT INTO users VALUES
        (1, 'member01', 'Member User', 'm@example.invalid', 'Member Dept', 'active', 0, NULL, NULL, NULL, NULL, '2026-10-01 00:00:00', NULL),
        (2, 'pr01', 'PR Officer User', 'pr@example.invalid', 'PR Dept', 'active', 1, 'SEC_PR', '2026-10-01 00:00:00', NULL, NULL, '2026-10-01 00:00:00', NULL),
        (3, 'admin01', 'Super Admin User', 'adm@example.invalid', 'IT Dept', 'active', 1, 'SEC_ADM', '2026-10-01 00:00:00', NULL, NULL, '2026-10-01 00:00:00', NULL);

    INSERT INTO user_roles VALUES (1, 1), (2, 2), (3, 3);
    INSERT INTO permissions VALUES (1, 'news', 'view'), (2, 'news', 'create');
    INSERT INTO role_permissions VALUES (2, 1), (2, 2);
");

function invokeEndpoint(string $method): PermBoundaryCapturedResponse
{
    $req = new Request();
    $resp = new PermBoundaryCapturedResponse();
    $controller = new ControlCenterApiController($req, $resp);
    $controller->{$method}();
    return $resp;
}

echo "--- 1. Testing Unauthenticated / Anonymous Access (401 Expected) ---\n";
Auth::logout();
$endpoints = ['summary', 'trends', 'systemHealth', 'backups', 'securityOverview', 'securityMfa', 'operations'];
foreach ($endpoints as $ep) {
    $resp = invokeEndpoint($ep);
    assertBoundary($resp->status === 401, "Anonymous user denied (401) on {$ep}");
    assertBoundary($resp->body['success'] === false, "Anonymous response {$ep} has success === false");
}

echo "\n--- 2. Testing Member Role Access (403 Forbidden Expected) ---\n";
Auth::login([
    'id' => 1,
    'username' => 'member01',
    'name' => 'Member User',
    'email' => 'm@example.invalid',
    'role_slug' => 'member',
], true);

foreach ($endpoints as $ep) {
    $resp = invokeEndpoint($ep);
    assertBoundary($resp->status === 403, "Member role strictly denied (403) on {$ep}");
    assertBoundary($resp->body['success'] === false, "Member response {$ep} has success === false");
}

echo "\n--- 3. Testing Least-Privilege Role Boundary (PR Officer) ---\n";
Auth::login([
    'id' => 2,
    'username' => 'pr01',
    'name' => 'PR Officer User',
    'email' => 'pr@example.invalid',
    'role_slug' => 'pr_officer',
], true);

// PR officer does NOT have backup, health, security, or access permissions
$restrictedForPr = ['backups', 'systemHealth', 'securityOverview', 'securityMfa', 'accessPermissions'];
foreach ($restrictedForPr as $ep) {
    $resp = invokeEndpoint($ep);
    assertBoundary($resp->status === 403, "PR Officer without system permission denied (403) on {$ep}");
}

echo "\n--- 4. Testing Super Admin Access (Allowed) ---\n";
Auth::login([
    'id' => 3,
    'username' => 'admin01',
    'name' => 'Super Admin User',
    'email' => 'adm@example.invalid',
    'role_slug' => 'super_admin',
], true);

foreach ($restrictedForPr as $ep) {
    $resp = invokeEndpoint($ep);
    assertBoundary($resp->status === 200, "Super Admin permitted (200) on {$ep}");
}

echo "\nAdminPermissionBoundaryTest passed all assertions successfully.\n";
