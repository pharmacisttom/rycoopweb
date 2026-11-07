<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$routesFile = __DIR__ . '/../config/routes.php';
$content = file_get_contents($routesFile);

// Match $router->get('/path', 'Controller@method', ...); or $r->get(...)
// or $router->post(...)
$pattern = '/\$(?:router|r)->(get|post|put|patch|delete)\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)@([^\'"]+)[\'"]/i';

preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

$total = count($matches);
$valid = 0;
$missingClass = [];
$missingMethod = [];
$controllersChecked = [];

foreach ($matches as $m) {
    $httpMethod = strtoupper($m[1]);
    $uri = $m[2];
    $controllerName = str_replace('\\\\', '\\', $m[3]);
    $action = $m[4];

    // Normalize controller class
    $fullClass = str_starts_with($controllerName, 'App\\')
        ? $controllerName
        : 'App\\Controllers\\' . ltrim($controllerName, '\\');
    $fullClass = str_replace('\\\\', '\\', $fullClass);

    if (!class_exists($fullClass, false)) {
        try {
            class_exists($fullClass, true);
        } catch (\Throwable $e) {
            $missingClass[] = [
                'http_method' => $httpMethod,
                'uri' => $uri,
                'controller' => $fullClass,
                'method' => $action,
                'error' => $e->getMessage()
            ];
            continue;
        }
    }

    if (!class_exists($fullClass, false)) {
        $missingClass[] = [
            'http_method' => $httpMethod,
            'uri' => $uri,
            'controller' => $fullClass,
            'method' => $action,
        ];
        continue;
    }

    $ref = new ReflectionClass($fullClass);
    if (!$ref->hasMethod($action)) {
        $missingMethod[] = [
            'http_method' => $httpMethod,
            'uri' => $uri,
            'controller' => $fullClass,
            'method' => $action,
        ];
        continue;
    }

    $valid++;
    $controllersChecked[$fullClass][] = $action;
}

echo "=== BACKEND ROUTE & CONTROLLER AUDIT ===" . PHP_EOL;
echo "Total routes scanned: {$total}" . PHP_EOL;
echo "Valid Controller@method: {$valid}" . PHP_EOL;
echo "Missing Controller class: " . count($missingClass) . PHP_EOL;
echo "Missing Controller method: " . count($missingMethod) . PHP_EOL;

if (!empty($missingClass)) {
    echo "\n--- MISSING CONTROLLER CLASSES ---" . PHP_EOL;
    foreach ($missingClass as $item) {
        echo "[MISSING CLASS] {$item['http_method']} {$item['uri']} -> {$item['controller']}" . PHP_EOL;
    }
}

if (!empty($missingMethod)) {
    echo "\n--- MISSING CONTROLLER METHODS ---" . PHP_EOL;
    foreach ($missingMethod as $item) {
        echo "[MISSING METHOD] {$item['http_method']} {$item['uri']} -> {$item['controller']}@{$item['method']}" . PHP_EOL;
    }
}
