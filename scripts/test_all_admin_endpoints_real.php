<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Auth;
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

// Load Super Admin User
$adminUser = Database::first("
    SELECT u.*, r.slug AS role_slug, r.name AS role_name
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE u.id = 1
");

if (!$adminUser) {
    echo "ERROR: Admin user ID 1 not found in database!" . PHP_EOL;
    exit(1);
}

Auth::login($adminUser, true, false);

// Build router by loading routes.php
$router = new Router();
require __DIR__ . '/../config/routes.php';

$testRequests = [
    // 1. Dashboard & Workspace
    ['GET', '/api/admin/dashboard/summary'],
    ['GET', '/api/admin/dashboard/trends'],
    ['GET', '/api/admin/system/health'],
    ['GET', '/api/admin/analytics/visitors'],
    ['GET', '/api/admin/operations/overview'],

    // 2. Member Registry
    ['GET', '/api/admin/members/dashboard'],
    ['GET', '/api/admin/members'],
    ['GET', '/api/admin/members/health'],
    ['GET', '/api/admin/members/1'],
    ['GET', '/api/admin/members/requests'],

    // 3. Security & Access Control
    ['GET', '/api/admin/security/overview'],
    ['GET', '/api/admin/security/sessions'],
    ['GET', '/api/admin/security/mfa'],
    ['GET', '/api/admin/security/dashboard'],
    ['GET', '/api/admin/audit-logs/center'],
    ['GET', '/api/admin/backups'],
    ['GET', '/api/admin/access/permissions'],
    ['GET', '/api/admin/access/roles'],
    ['GET', '/api/admin/access/users'],
    ['GET', '/api/admin/staff'],
    ['GET', '/api/admin/departments'],
    ['GET', '/api/admin/reports/catalog'],

    // 4. Content & CMS
    ['GET', '/api/admin/hero-slides'],
    ['GET', '/api/admin/popups'],
    ['GET', '/api/admin/welfare'],
    ['GET', '/api/admin/eservices'],
    ['GET', '/api/admin/board-staff'],
    ['GET', '/api/admin/products'],
    ['GET', '/api/admin/document-categories'],
    ['GET', '/api/admin/documents'],
    ['GET', '/api/admin/announcements'],
    ['GET', '/api/admin/news'],
    ['GET', '/api/admin/faqs'],
    ['GET', '/api/admin/settings/content'],

    // 5. LED Open Data
    ['GET', '/api/admin/led/dashboard'],
    ['GET', '/api/admin/led/schema'],
    ['GET', '/api/admin/led/review'],

    // 6. Endpoints called by frontend with mismatch potential
    ['GET', '/api/admin/loans'],
];

class CaptureResponse extends Response
{
    public int $statusCode = 200;
    public mixed $bodyData = null;
    public string $rawOutput = '';

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

echo "========================================================================" . PHP_EOL;
echo " RYCOOPWEB — REAL BACKEND ROUTER DISPATCH AUDIT (MySQL Database)" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

$results = [];
$passCount = 0;
$failCount = 0;

foreach ($testRequests as $req) {
    [$method, $path] = $req;

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_GET = [];
    $_POST = [];

    $request = new Request();
    $response = new CaptureResponse();

    ob_start();
    try {
        $router->dispatch($request, $response);
        $output = ob_get_clean();

        $code = $response->statusCode;
        $body = $response->bodyData;

        // If bodyData wasn't set via json(), check if output was printed
        if ($body === null && $output !== '') {
            $parsed = json_decode($output, true);
            if ($parsed !== null) $body = $parsed;
        }

        $ok = ($code >= 200 && $code < 300);
        if ($ok && is_array($body) && isset($body['success']) && $body['success'] === false) {
            $ok = false;
        }

        if ($ok) $passCount++; else $failCount++;

        $results[] = [
            'method' => $method,
            'path' => $path,
            'code' => $code,
            'ok' => $ok,
            'error' => is_array($body) ? ($body['message'] ?? '') : substr($output, 0, 100),
            'body_keys' => is_array($body) ? array_keys($body) : []
        ];

        printf(" [%s] %-6s %-38s -> HTTP %d %s\n",
            $ok ? 'PASS' : 'FAIL',
            $method,
            $path,
            $code,
            !$ok ? "(Error: " . (is_array($body) ? ($body['message'] ?? 'Failed') : substr($output, 0, 60)) . ")" : ""
        );

    } catch (\Throwable $e) {
        ob_end_clean();
        $failCount++;
        $results[] = [
            'method' => $method,
            'path' => $path,
            'code' => 500,
            'ok' => false,
            'error' => $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine(),
            'body_keys' => []
        ];
        printf(" [FAIL] %-6s %-38s -> EXCEPTION: %s (%s:%d)\n",
            $method,
            $path,
            $e->getMessage(),
            basename($e->getFile()),
            $e->getLine()
        );
    }
}

echo "========================================================================" . PHP_EOL;
echo " TOTAL AUDIT RESULTS: {$passCount} PASSED, {$failCount} FAILED out of " . count($testRequests) . PHP_EOL;
echo "========================================================================" . PHP_EOL;
