<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

$sessPath = __DIR__ . '/../storage/tmp/diag-sessions';
if (!is_dir($sessPath)) mkdir($sessPath, 0700, true);
putenv('SESSION_SAVE_PATH=' . $sessPath);
ini_set('session.save_path', $sessPath);

// Start session
Session::start();

// Authenticate as Super Admin (user id 1)
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

$endpointsToTest = [
    // Dashboard & Workspace
    ['GET', '/api/admin/dashboard/summary', 'App\\Controllers\\Admin\\ControlCenterApiController@dashboardSummary'],
    ['GET', '/api/admin/dashboard/trends', 'App\\Controllers\\Admin\\ControlCenterApiController@dashboardTrends'],
    ['GET', '/api/admin/workspace/summary', 'App\\Controllers\\Admin\\AdminWorkspaceController@summary'],
    ['GET', '/api/admin/workspace/tasks', 'App\\Controllers\\Admin\\AdminWorkspaceController@tasks'],

    // Member Registry
    ['GET', '/api/admin/members/dashboard', 'App\\Controllers\\Admin\\AdminMembersApiController@dashboard'],
    ['GET', '/api/admin/members', 'App\\Controllers\\Admin\\AdminMembersApiController@index'],
    ['GET', '/api/admin/members/health', 'App\\Controllers\\Admin\\AdminMembersApiController@health'],
    ['GET', '/api/admin/members/1', 'App\\Controllers\\Admin\\AdminMembersApiController@show', ['id' => 1]],
    ['GET', '/api/admin/members/1/identity-mapping', 'App\\Controllers\\Admin\\AdminMembersApiController@identityMapping', ['id' => 1]],
    ['GET', '/api/admin/members/1/status-summary', 'App\\Controllers\\Admin\\AdminMembersApiController@statusSummary', ['id' => 1]],
    ['GET', '/api/admin/members/1/dividends', 'App\\Controllers\\Admin\\AdminMembersApiController@dividends', ['id' => 1]],

    // Content & CMS
    ['GET', '/api/admin/hero-slides', 'App\\Controllers\\Admin\\HeroSlidesController@index'],
    ['GET', '/api/admin/popups', 'App\\Controllers\\Admin\\PopupCampaignController@index'],
    ['GET', '/api/admin/popups/stats', 'App\\Controllers\\Admin\\PopupCampaignController@stats'],
    ['GET', '/api/admin/welfare', 'App\\Controllers\\Admin\\WelfareAdminController@index'],
    ['GET', '/api/admin/eservices', 'App\\Controllers\\Admin\\EServicesAdminController@index'],
    ['GET', '/api/admin/board-staff', 'App\\Controllers\\Admin\\BoardStaffAdminController@index'],
    ['GET', '/api/admin/products', 'App\\Controllers\\Admin\\ProductsAdminController@index'],
    ['GET', '/api/admin/document-categories', 'App\\Controllers\\Admin\\DocumentCategoriesAdminController@index'],
    ['GET', '/api/admin/documents', 'App\\Controllers\\Admin\\DocumentAdminController@index'],
    ['GET', '/api/admin/content/news', 'App\\Controllers\\Admin\\NewsAdminController@index'],
    ['GET', '/api/admin/public-content/summary', 'App\\Controllers\\Admin\\PublicContentAdminController@summary'],

    // Security & Operations
    ['GET', '/api/admin/security/dashboard', 'App\\Controllers\\Admin\\StaffManagementController@securityDashboard'],
    ['GET', '/api/admin/security/mfa', 'App\\Controllers\\Admin\\ControlCenterApiController@securityMfa'],
    ['GET', '/api/admin/audit-logs/center', 'App\\Controllers\\Admin\\ControlCenterApiController@auditLogs'],
    ['GET', '/api/admin/backups', 'App\\Controllers\\Admin\\ControlCenterApiController@backups'],
    ['GET', '/api/admin/health/status', 'App\\Controllers\\Admin\\ControlCenterApiController@healthStatus'],
    ['GET', '/api/admin/operations/stats', 'App\\Controllers\\Admin\\ControlCenterApiController@operationsStats'],
    ['GET', '/api/admin/staff', 'App\\Controllers\\Admin\\StaffManagementController@index'],

    // LED
    ['GET', '/api/admin/led/dashboard', 'App\\Controllers\\Admin\\AdminLedApiController@dashboard'],
    ['GET', '/api/admin/led/schema', 'App\\Controllers\\Admin\\AdminLedApiController@schema'],
];

class MockResponse extends Response
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
}

echo "========================================================================" . PHP_EOL;
echo " RYCOOPWEB — ADMIN BACKEND API DIAGNOSTICS & DATA RECONCILIATION" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

$passed = 0;
$failed = 0;
$diagnostics = [];

foreach ($endpointsToTest as $test) {
    [$method, $uri, $handler] = $test;
    $params = $test[3] ?? [];

    [$controllerClass, $action] = explode('@', $handler);

    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_GET = $params;
    $_POST = [];

    $mockResponse = new MockResponse();
    $mockRequest = new Request();

    $statusStr = '';
    $errDetail = '';

    try {
        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller class {$controllerClass} does not exist");
        }

        $controller = new $controllerClass($mockRequest, $mockResponse);

        if (!method_exists($controller, $action)) {
            throw new \RuntimeException("Method {$action} does not exist on {$controllerClass}");
        }

        // Call controller action
        $controller->$action();

        $httpCode = $mockResponse->statusCode;
        $body = $mockResponse->bodyData;

        $isSuccess = ($httpCode >= 200 && $httpCode < 300) && (is_array($body) ? ($body['success'] ?? true) : true);

        if ($isSuccess) {
            $statusStr = "OK ({$httpCode})";
            $passed++;
        } else {
            $statusStr = "FAIL ({$httpCode})";
            $errDetail = is_array($body) ? ($body['message'] ?? json_encode($body, JSON_UNESCAPED_UNICODE)) : 'Failed';
            $failed++;
        }

        $diagnostics[] = [
            'method' => $method,
            'uri' => $uri,
            'handler' => $handler,
            'code' => $httpCode,
            'success' => $isSuccess,
            'error' => $errDetail,
            'data_summary' => is_array($body) ? summarizeData($body) : gettype($body)
        ];

        printf(" [%s] %-6s %-35s -> %s %s\n",
            $isSuccess ? 'PASS' : 'FAIL',
            $method,
            $uri,
            $statusStr,
            $errDetail ? "(Error: {$errDetail})" : ""
        );

    } catch (\Throwable $e) {
        $failed++;
        $diagnostics[] = [
            'method' => $method,
            'uri' => $uri,
            'handler' => $handler,
            'code' => 500,
            'success' => false,
            'error' => $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine(),
            'data_summary' => 'EXCEPTION'
        ];
        printf(" [FAIL] %-6s %-35s -> EXCEPTION: %s (%s:%d)\n",
            $method,
            $uri,
            $e->getMessage(),
            basename($e->getFile()),
            $e->getLine()
        );
    }
}

echo "========================================================================" . PHP_EOL;
echo " DIAGNOSTICS SUMMARY: {$passed} PASSED, {$failed} FAILED (Total: " . count($endpointsToTest) . ")" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

function summarizeData(array $data): string
{
    $keys = array_keys($data);
    $summary = [];
    foreach ($keys as $k) {
        if ($k === 'success') continue;
        if (is_array($data[$k])) {
            $count = count($data[$k]);
            $summary[] = "{$k}:[{$count}]";
        } elseif (is_scalar($data[$k])) {
            $val = is_bool($data[$k]) ? ($data[$k] ? 'true' : 'false') : (string)$data[$k];
            if (strlen($val) > 20) $val = substr($val, 0, 17) . '...';
            $summary[] = "{$k}={$val}";
        }
    }
    return implode(', ', $summary);
}
