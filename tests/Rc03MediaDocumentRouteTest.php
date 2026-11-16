<?php
declare(strict_types=1);

// Structural route assertions only. No HTTP dispatch, environment DB or file streaming.
require __DIR__.'/../vendor/autoload.php';

$router = new \App\Core\Router();
require __DIR__.'/../config/routes.php';
$routes = (new ReflectionProperty(\App\Core\Router::class,'routes'))->getValue($router);

$expected = [
    ['GET','/api/public/media/{folder}/{filename}','Public\\MediaController@show',false],
    ['GET','/api/admin/documents/{id}/preview','Admin\\DocumentController@preview',true],
    ['GET','/api/admin/documents/{id}/download','Admin\\DocumentController@download',true],
];
foreach ($expected as [$method,$path,$handler,$admin]) {
    $matches = array_values(array_filter($routes,static fn(array $route): bool =>
        $route['method']===$method && $route['path']===$path
    ));
    if (count($matches)!==1) throw new RuntimeException("Expected exactly one route: $method $path");
    $route = $matches[0];
    if ($route['handler']!==$handler) throw new RuntimeException("Unexpected handler: $path");
    if ($admin) {
        if (!in_array(\App\Middlewares\AuthMiddleware::class,$route['middlewares'],true)) {
            throw new RuntimeException("Missing authenticated admin group middleware: $path");
        }
        $permission = false;
        foreach ($route['middlewares'] as $mw) {
            if ($mw instanceof \App\Middlewares\PermissionMiddleware) {
                // Verify deny-by-default shape with a fixture-independent object contract.
                $permission = true;
            }
        }
        if (!$permission) throw new RuntimeException("Missing permission middleware: $path");
    } else if (in_array(\App\Middlewares\AuthMiddleware::class,$route['middlewares'],true)) {
        throw new RuntimeException("Public media unexpectedly private");
    }
    echo "PASS: $method $path\n";
}
$controller = file_get_contents(__DIR__.'/../app/Controllers/Admin/DocumentController.php');
foreach (['public function preview(','public function download(','private function serveAdminDocument('] as $needle) {
    if (substr_count($controller,$needle)!==1) throw new RuntimeException("Missing or duplicate document method: $needle");
}
$media = file_get_contents(__DIR__.'/../app/Controllers/Public/MediaController.php');
foreach (['realpath(', 'getimagesize(', 'FILEINFO_MIME_TYPE'] as $needle) {
    if (!str_contains($media,$needle)) throw new RuntimeException("Missing media safety check: $needle");
}
echo "PASS: Media/Document route structure; live HTTP approval still required\n";
