<?php
declare(strict_types=1);

/**
 * RYCOOPWEB static admin API route audit. Does not dispatch requests or read DB.
 * Run: php scripts/export-admin-route-inventory.php
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;

$router = new Router();
require dirname(__DIR__) . '/config/routes.php';

$reflection = new ReflectionClass($router);
$property = $reflection->getProperty('routes');
$routes = $property->getValue($router);

$output = dirname(__DIR__) . '/docs/audit/ADMIN_API_ROUTE_MATRIX.md';
$rows = [];
$findings = [];
foreach ($routes as $route) {
    $path = (string)$route['path'];
    if (!str_starts_with($path, '/api/admin/')) {
        continue;
    }
    $method = (string)$route['method'];
    $middlewares = [];
    foreach ($route['middlewares'] as $middleware) {
        $middlewares[] = is_object($middleware) ? get_class($middleware) : (string)$middleware;
    }
    $auth = in_array(AuthMiddleware::class, $middlewares, true);
    $csrf = in_array(CsrfMiddleware::class, $middlewares, true);
    $permission = array_values(array_filter($middlewares, static fn(string $name): bool =>
        str_contains($name, 'PermissionMiddleware') || str_contains($name, 'RoleMiddleware')));
    $handler = is_string($route['handler']) ? $route['handler'] : '[callable/array]';
    if (!$auth) $findings[] = "$method $path — no AuthMiddleware";
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) && !$csrf) {
        $findings[] = "$method $path — no CsrfMiddleware";
    }
    $rows[] = [$method, $path, $handler, $auth ? 'YES' : 'NO',
        $csrf ? 'YES' : 'NO', $permission ? 'YES' : 'CHECK',
        implode(', ', array_map('basename', $permission))];
}
usort($rows, static fn(array $a, array $b): int => [$a[1],$a[0]] <=> [$b[1],$b[0]]);
$lines = [
    '# Admin API Route Inventory — Static Audit',
    '',
    'Generated from config/routes.php by Router reflection; **no network or database access**.',
    '',
    'Auth/CSRF markers reflect registered middleware, not proof of runtime enforcement.',
    '',
    '| Method | API path | Handler | Auth | CSRF | Role/Permission |',
    '|---|---|---|---|---|---|',
];
foreach ($rows as [$method,$path,$handler,$auth,$csrf,$hasPermission]) {
    $lines[] = "| $method | `$path` | `$handler` | $auth | $csrf | $hasPermission |";
}
$lines[] = '';
$lines[] = '## Findings (manual review required)';
$lines[] = '';
foreach ($findings as $finding) $lines[] = '- ' . $finding;
if (!$findings) $lines[] = 'No missing auth/CSRF middleware detected by this basic static check.';
$lines[] = '';
$lines[] = 'CRUD completeness, handler existence, transaction safety and permission semantics require separate tests.';
file_put_contents($output, implode("\n", $lines) . "\n");
printf("Admin API routes: %d; basic auth/CSRF findings: %d\n",count($rows),count($findings));
echo "Output: docs/audit/ADMIN_API_ROUTE_MATRIX.md\n";
