<?php
declare(strict_types=1);

// Isolated no-database regression for unknown API routes. Run from the repo root:
// php tests/RouterErrorContractTest.php
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

function failTest(string $message): never {
    fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
    exit(1);
}

// The Router's error contract should not depend on a client supplying AJAX headers.
$source = file_get_contents($root . '/app/Core/Router.php');
if (!is_string($source) || !str_contains($source, "str_starts_with(\$uri, '/api/')")) {
    failTest('API fallback guard is missing');
}
if (!str_contains($source, 'catch (\Throwable $e)')) {
    failTest('HTML 404 safe fallback is missing');
}

$code = <<<'PHP'
require $argv[1] . '/vendor/autoload.php';
$_SERVER['REQUEST_METHOD']='GET';
$_SERVER['REQUEST_URI']='/api/admin/welfare/1/update';
$_SERVER['SCRIPT_NAME']='/index.php';
unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
$router = new \App\Core\Router();
$router->dispatch(new \App\Core\Request(), new \App\Core\Response());
PHP;
$command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' ' . escapeshellarg($root);
$output = [];
$status = 0;
exec($command, $output, $status);
$payload = json_decode(implode("\n", $output), true);
if ($status !== 0 || !is_array($payload) || ($payload['success'] ?? null) !== false || !isset($payload['message'])) {
    failTest('unknown API GET must return JSON without AJAX headers');
}
echo "ROUTER_UNKNOWN_API_JSON=PASS\n";
echo "ROUTER_HTML_404_FALLBACK_GUARD=PASS\n";
