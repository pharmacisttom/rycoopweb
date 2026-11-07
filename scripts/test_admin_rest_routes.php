<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

$sessPath = __DIR__ . '/../storage/tmp/admin-audit-sessions';
if (!is_dir($sessPath)) mkdir($sessPath, 0700, true);
putenv('SESSION_SAVE_PATH=' . $sessPath);
ini_set('session.save_path', $sessPath);

Session::start();

$adminUser = Database::first("
    SELECT u.*, r.slug AS role_slug, r.name AS role_name
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE u.id = 1
");

Auth::login($adminUser, true, false);

class MockWriteResponse extends Response
{
    public int $statusCode = 200;
    public mixed $bodyData = null;

    public function json(array|object $data, int $status = 200, array $headers = []): void
    {
        $this->statusCode = $status;
        $this->bodyData = $data;
    }

    public function setStatusCode(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        return $this;
    }
}

// Generate valid CSRF token
$csrfToken = Csrf::token();

$routesToTest = [
    // Loan products REST
    ['GET', '/api/admin/loans', []],
    ['POST', '/api/admin/loans', []], // Should return 422 (validation error: missing name)
    ['PUT', '/api/admin/loans/999999', ['name' => 'Test']], // Should return 404 (product not found)
    ['DELETE', '/api/admin/loans/999999', []], // Should return 404 (product not found)

    // Deposit products REST
    ['POST', '/api/admin/deposits', []], // Should return 422
    ['PUT', '/api/admin/deposits/999999', ['name' => 'Test']], // Should return 404
    ['DELETE', '/api/admin/deposits/999999', []], // Should return 404

    // EServices REST
    ['PUT', '/api/admin/eservices/999999', ['name' => 'Test', 'url' => 'https://example.com']], // Should return 404
    ['DELETE', '/api/admin/eservices/999999', []], // Should return 404

    // Board & Staff REST
    ['PUT', '/api/admin/board-staff/board/999999', ['name' => 'Test', 'position' => 'Board']], // Should return 404
    ['DELETE', '/api/admin/board-staff/board/999999', []], // Should return 404
    ['PUT', '/api/admin/board-staff/staff/999999', ['name' => 'Test', 'position' => 'Staff']], // Should return 404
    ['DELETE', '/api/admin/board-staff/staff/999999', []], // Should return 404

    // Document Categories REST
    ['PUT', '/api/admin/document-categories/999999', ['name' => 'Test']], // Should return 404
    ['DELETE', '/api/admin/document-categories/999999', []], // Should return 404
];

echo "========================================================================" . PHP_EOL;
echo " TESTING RESTFUL WRITE & MUTATION WITH VALID CSRF TOKEN" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

$pass = 0;
$fail = 0;

foreach ($routesToTest as $req) {
    [$method, $path, $postData] = $req;

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
    $_POST = $postData;

    $router = new Router();
    require __DIR__ . '/../config/routes.php';

    $request = new Request();
    $response = new MockWriteResponse();

    try {
        $router->dispatch($request, $response);
        // Valid controller responses: 200, 201, 404 (item not found in db), 409, 422 (validation failed)
        // Invalid responses: 403 (forbidden/CSRF fail), 500 (SQL crash)
        if ($response->statusCode === 200 || $response->statusCode === 201 || $response->statusCode === 404 || $response->statusCode === 422) {
            $msg = $response->bodyData['message'] ?? 'OK';
            echo " [PASS] {$method} {$path} -> Controller Reached (HTTP {$response->statusCode}: {$msg})" . PHP_EOL;
            $pass++;
        } else {
            echo " [FAIL] {$method} {$path} -> Unexpected HTTP {$response->statusCode}: " . json_encode($response->bodyData, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $fail++;
        }
    } catch (\Throwable $e) {
        echo " [ERROR] {$method} {$path} -> Exception: {$e->getMessage()}" . PHP_EOL;
        $fail++;
    }
}

echo "========================================================================" . PHP_EOL;
echo " TOTAL REST WRITE TESTS: {$pass} PASSED, {$fail} FAILED out of " . count($routesToTest) . PHP_EOL;
echo "========================================================================" . PHP_EOL;

if ($fail > 0) exit(1);
