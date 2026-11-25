<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$router=new \App\Core\Router();
require dirname(__DIR__).'/config/routes.php';
$routes=(new ReflectionProperty(\App\Core\Router::class,'routes'))->getValue($router);
$matches=array_values(array_filter($routes,static fn(array $r):bool=>$r['method']==='POST'&&$r['path']==='/api/admin/media/people'));
if(count($matches)!==1)throw new RuntimeException('Missing or duplicated staff photo endpoint');
$route=$matches[0];
if($route['handler']!=='Api\\MediaLibraryController@uploadPeople')throw new RuntimeException('Incorrect portrait handler');
if(!in_array(\App\Middlewares\AuthMiddleware::class,$route['middlewares'],true))throw new RuntimeException('Authentication not enforced');
if(!in_array(\App\Middlewares\CsrfMiddleware::class,$route['middlewares'],true))throw new RuntimeException('CSRF not enforced');
$rbac=array_values(array_filter($route['middlewares'],static fn($m):bool=>$m instanceof \App\Middlewares\PermissionMiddleware));
if(count($rbac)!==1)throw new RuntimeException('Expected exact people.update RBAC gate');
$module=(new ReflectionProperty(\App\Middlewares\PermissionMiddleware::class,'module'))->getValue($rbac[0]);
$action=(new ReflectionProperty(\App\Middlewares\PermissionMiddleware::class,'action'))->getValue($rbac[0]);
if($module!=='people'||$action!=='update')throw new RuntimeException('Portrait RBAC must be people.update only');
$source=file_get_contents(dirname(__DIR__).'/app/Controllers/Api/MediaLibraryController.php');
foreach(["'staff','board'",'uploadPortraitOnly',"Auth::hasPermission('people', 'update')",'Portraits may be stored only in staff or board folders','is_uploaded_file','imagewebp','AuditService::log'] as $must){
    if(!str_contains($source,$must))throw new RuntimeException('Missing upload safety guard: '.$must);
}
$api=file_get_contents(dirname(__DIR__).'/src/services/mediaLibraryApi.js');
if(!str_contains($api,'/api/admin/media/people'))throw new RuntimeException('React photo upload missing restricted API');
echo "RC23_STAFF_PHOTO_RBAC_CSRF_FOLDER_ISOLATION=PASS\n";
