<?php
declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\CsrfMiddleware;

$router = new Router();
require __DIR__.'/../config/routes.php';
$routes = (new ReflectionProperty(Router::class,'routes'))->getValue($router);

$expected = [
  ['GET','/api/public/popup-campaign',false,false],
  ['GET','/api/public/popup-campaign/image',false,false],
  ['GET','/api/admin/popup-campaigns',true,false],
  ['POST','/api/admin/popup-campaigns',true,true],
  ['POST','/api/admin/popup-campaigns/{id}/update',true,true],
  ['POST','/api/admin/popup-campaigns/{id}/disable',true,true],
  ['POST','/api/admin/popup-campaigns/{id}/delete',true,true]
];

foreach ($expected as [$method,$path,$private,$write]) {
  $matches = array_values(array_filter($routes,fn($route)=>$route['method']===$method&&$route['path']===$path));
  if(count($matches)!==1)throw new RuntimeException("Missing or duplicate route: $method $path");
  $middleware=$matches[0]['middlewares'];
  if($private){
    if(!in_array(AuthMiddleware::class,$middleware,true))throw new RuntimeException("Missing AuthMiddleware: $path");
    $roles=array_values(array_filter($middleware,fn($m)=>$m instanceof RoleMiddleware));
    if(count($roles)!==1)throw new RuntimeException("Missing RoleMiddleware: $path");
    $allowed=(new ReflectionProperty(RoleMiddleware::class,'allowedRoles'))->getValue($roles[0]);
    if($allowed!==['super_admin'])throw new RuntimeException("Unsafe role scope: $path");
  }
  if($write&&!in_array(CsrfMiddleware::class,$middleware,true))throw new RuntimeException("Missing CSRF: $path");
}
$source=file_get_contents(__DIR__.'/../src/components/common/CampaignModal.jsx');
if(!str_contains($source,"/api/public/popup-campaign") || str_contains($source,"coop_popup_campaign"))
    throw new RuntimeException('CampaignModal must consume database API only');
$admin=file_get_contents(__DIR__.'/../src/pages/AdminDashboardPage.jsx');
if(!str_contains($admin,"path: '/admin/popups'"))throw new RuntimeException('Missing CMS Popup card');
echo "POPUP_CMS_CONTRACT=PASS\n";
