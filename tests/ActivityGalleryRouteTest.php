<?php
declare(strict_types=1);

// Isolated contract checks; does not read an operational database or upload files.
require __DIR__.'/../vendor/autoload.php';

$router=new \App\Core\Router();
require __DIR__.'/../config/routes.php';
$routes=(new ReflectionProperty(\App\Core\Router::class,'routes'))->getValue($router);

$targets=[
 'GET /api/public/activities',
 'GET /api/public/activities/{id}',
 'GET /api/public/activities/{album}/photos/{photo}',
 'GET /api/admin/activities',
 'GET /api/admin/activities/{id}',
 'POST /api/admin/activities',
 'POST /api/admin/activities/{id}/update',
 'POST /api/admin/activities/{id}/photos',
 'POST /api/admin/activities/{id}/publish',
 'POST /api/admin/activities/{id}/archive',
 'POST /api/admin/activities/{id}/reorder',
 'POST /api/admin/activities/{id}/photos/{photo}/cover',
 'POST /api/admin/activities/{id}/photos/{photo}/caption',
 'POST /api/admin/activities/{id}/photos/{photo}/remove',
 'GET /api/admin/activities/{album}/photos/{photo}',
];
foreach($targets as $target){
    [$method,$path]=explode(' ',$target,2);
    $matches=array_values(array_filter($routes,static fn(array $r):bool=>$r['method']===$method&&$r['path']===$path));
    if(count($matches)!==1)throw new RuntimeException("Missing/duplicate: $target");
    $r=$matches[0];
    if(!str_contains((string)$r['handler'],'ActivityGalleryController@'))throw new RuntimeException("Handler mismatch: $target");
    if(str_starts_with($path,'/api/admin/')){
        if(!in_array(\App\Middlewares\AuthMiddleware::class,$r['middlewares'],true))throw new RuntimeException("Missing auth: $target");
        if(!array_filter($r['middlewares'],static fn($x):bool=>$x instanceof \App\Middlewares\PermissionMiddleware))throw new RuntimeException("Missing RBAC: $target");
        if($method==='POST'&&!in_array(\App\Middlewares\CsrfMiddleware::class,$r['middlewares'],true))throw new RuntimeException("Missing CSRF: $target");
    }
    echo "PASS: $target\n";
}
$service=file_get_contents(__DIR__.'/../app/Controllers/Api/ActivityGalleryController.php');
foreach(["status='published'","photoRoot()","Auth::check()","imagewebp(","required:true"] as $guard){
    if(!str_contains($service,$guard))throw new RuntimeException("Missing guard: $guard");
}
echo "PASS: Activity Gallery route and draft/public structural gates (live verification pending)\n";
