<?php
declare(strict_types=1);
// Static contract only; never connects to a database or changes uploads.
require dirname(__DIR__) . '/vendor/autoload.php';
$router = new \App\Core\Router();
require dirname(__DIR__) . '/config/routes.php';
$routes = (new ReflectionProperty(\App\Core\Router::class, 'routes'))->getValue($router);

$cases = [
    ['GET','/api/admin/media','index','view'],
    ['GET','/api/admin/media/{id}','show','view'],
    ['GET','/api/admin/media/{id}/image','image','view'],
    ['POST','/api/admin/media','upload','create'],
    ['POST','/api/admin/media/{id}/update','update','update'],
    ['POST','/api/admin/media/{id}/delete','destroy','delete'],
];
foreach ($cases as [$method,$path,$action,$permission]) {
    $matches = array_values(array_filter($routes,static fn(array $r): bool =>
      $r['method']===$method && $r['path']===$path));
    if(count($matches)!==1)throw new RuntimeException("Missing or duplicate route: $method $path");
    $r=$matches[0];
    if($r['handler']!=="Api\\MediaLibraryController@$action")throw new RuntimeException("Handler mismatch: $method $path");
    $middleware=$r['middlewares'];
    if(!in_array(\App\Middlewares\AuthMiddleware::class,$middleware,true))throw new RuntimeException("Auth missing: $method $path");
    if(!array_filter($middleware,static fn($m):bool=>$m instanceof \App\Middlewares\PermissionMiddleware))throw new RuntimeException("RBAC missing: $method $path");
    if($method==='POST'&&!in_array(\App\Middlewares\CsrfMiddleware::class,$middleware,true))throw new RuntimeException("CSRF missing: $method $path");
    echo "PASS: $method $path\n";
}
$source=file_get_contents(dirname(__DIR__).'/app/Controllers/Api/MediaLibraryController.php');
foreach(['imagecreatefromstring', 'imagewebp', 'getimagesize', 'is_uploaded_file', 'AuditService::log', 'deleted_at=NOW()', 'STALE_VERSION', 'required: true'] as $check) {
  if(!str_contains($source,$check))throw new RuntimeException("Missing safety token: $check");
}
echo "PASS: static media security contract; TEST MySQL integration and browser UAT still required\n";
