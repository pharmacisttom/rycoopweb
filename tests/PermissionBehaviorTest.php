<?php
declare(strict_types=1);

// Isolated SQLite memory fixture: never opens the configured MySQL connection.
require __DIR__ . '/../vendor/autoload.php';

use App\Core\{Auth, Csrf, Database, Request, Response, Router, Session};
use App\Middlewares\{CsrfMiddleware, PermissionMiddleware, RoleMiddleware};

final class CapturedResponse extends Response
{
    public int $status = 200;
    public array $body = [];
    public function json(array $data, int $status = 200): void { $this->body = $data; $this->status = $status; }
    public function redirect(string $url, int $status = 302): void { $this->status = $status; }
}

$sessionDirectory = dirname(__DIR__) . '/storage/tmp/permission-test-sessions';
if (!is_dir($sessionDirectory)) mkdir($sessionDirectory, 0700, true);
putenv('SESSION_SAVE_PATH=' . $sessionDirectory);
putenv('SESSION_SECURE=false');
$_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/probe', 'SCRIPT_NAME' => '/index.php', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
$_GET = $_POST = $_FILES = [];
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
(new ReflectionProperty(Database::class, 'instance'))->setValue(null, $pdo);
$pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT,status TEXT,deleted_at TEXT);
CREATE TABLE roles(id INTEGER PRIMARY KEY,name TEXT,slug TEXT);
CREATE TABLE user_roles(user_id INTEGER,role_id INTEGER);
CREATE TABLE permissions(id INTEGER PRIMARY KEY,module TEXT,action TEXT);
CREATE TABLE role_permissions(role_id INTEGER,permission_id INTEGER);
INSERT INTO users VALUES(1,'Fixture member','member@example.invalid','active',NULL),(2,'Fixture editor','editor@example.invalid','active',NULL),(3,'Fixture admin','admin@example.invalid','active',NULL),(4,'Fixture disabled','disabled@example.invalid','inactive',NULL),(5,'Fixture no role','norole@example.invalid','active',NULL);
INSERT INTO roles VALUES(1,'Member','member'),(2,'Editor','custom_editor'),(3,'Admin','super_admin');
INSERT INTO user_roles VALUES(1,1),(2,2),(3,3),(4,2);
INSERT INTO permissions VALUES(1,'news','view'),(2,'news','update');
INSERT INTO role_permissions VALUES(2,1),(2,2);");
$results = [];
function expect(bool $condition, string $message): void {
    global $results;
    if (!$condition) throw new RuntimeException($message);
    $results[] = 'PASS: ' . $message;
}
function loginFixture(int $id, string $role, bool $verified = true): void {
    Auth::logout();
    Auth::login(['id' => $id, 'email' => 'fixture@example.invalid', 'name' => 'Fixture', 'role_slug' => $role], $verified);
}
function permissionResult(string $module, string $action): array {
    $response = new CapturedResponse();
    $allowed = (new PermissionMiddleware($module, $action))->handle(new Request(), $response);
    return [$allowed, $response->status];
}
try {
    expect(permissionResult('news', 'view') === [false, 401], 'guest denied');
    loginFixture(2, 'custom_editor', false);
    expect(permissionResult('news', 'view') === [false, 401], 'pending 2FA denied despite session user id');
    loginFixture(4, 'custom_editor');
    expect(permissionResult('news', 'view') === [false, 401], 'inactive account denied');
    loginFixture(5, '');
    expect(permissionResult('news', 'view') === [false, 403], 'account without role cannot inherit permissions');
    loginFixture(1, 'member');
    expect(permissionResult('members', 'view') === [false, 403], 'member denied administrative member listing');
    loginFixture(2, 'custom_editor');
    expect(permissionResult('news', 'view') === [true, 200], 'custom editor may view news');
    expect(permissionResult('news', 'update') === [true, 200], 'custom editor may update news');
    expect(permissionResult('news', 'delete') === [false, 403], 'custom editor may not delete news');
    expect(permissionResult('dividends', 'import') === [false, 403], 'editor denied financial import');
    $response = new CapturedResponse();
    expect(!(new RoleMiddleware(['super_admin']))->handle(new Request(), $response) && $response->status === 403, 'custom role denied access administration');
    loginFixture(3, 'super_admin');
    expect(permissionResult('dividends', 'import') === [true, 200], 'super admin retains import permission');
    // Exercise the actual registered middleware, not just source text assertions.
    $router = new Router();
    require dirname(__DIR__) . '/config/routes.php';
    $routes = (new ReflectionProperty(Router::class, 'routes'))->getValue($router);
    $route = array_values(array_filter($routes, fn($r) => $r['method'] === 'GET' && preg_match($r['regex'], '/api/admin/members/requests')))[0];
    expect(str_ends_with($route['handler'], '@requests'), 'requests list is not shadowed by generic member id');
    foreach ([['/api/admin/dividends/confirm','POST',2,'custom_editor',403], ['/api/admin/members','GET',1,'member',403], ['/api/admin/access/roles','GET',2,'custom_editor',403], ['/api/admin/news/{id}/update','POST',2,'custom_editor',200]] as [$path,$method,$id,$role,$status]) {
        loginFixture($id, $role);
        $_SERVER['REQUEST_METHOD'] = $method;
        $_POST = ['_csrf_token' => Csrf::token()];
        $request = new Request(); $response = new CapturedResponse();
        $registered = array_values(array_filter($routes, fn($r) => $r['path'] === $path && $r['method'] === $method))[0];
        foreach ($registered['middlewares'] as $definition) {
            $guard = is_object($definition) ? $definition : new $definition();
            if ($guard->handle($request, $response) === false) break;
        }
        expect($response->status === $status, "$method $path enforces effective middleware for $role");
    }
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/spoof';
    $_POST = ['_method' => 'DELETE'];
    $called = false; $spoof = new Router();
    $spoof->delete('/spoof', function () use (&$called): void { $called = true; }, [CsrfMiddleware::class]);
    $response = new CapturedResponse();
    $spoof->dispatch(new Request(), $response);
    expect(!$called && $response->status === 419, 'POST method spoof does not bypass CSRF');
    $_POST['_csrf_token'] = Csrf::token();
    $spoof->dispatch(new Request(), new CapturedResponse());
    expect($called, 'method spoof with valid CSRF still works');
} finally {
    Auth::logout();
    (new ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
}
echo implode(PHP_EOL, $results) . PHP_EOL;
