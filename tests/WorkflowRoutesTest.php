<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Core\Router;
use App\Middlewares\{AuthMiddleware, PermissionMiddleware, CsrfMiddleware, RoleMiddleware};
$router=new Router();require __DIR__.'/../config/routes.php';
$routes=(new ReflectionProperty(Router::class,'routes'))->getValue($router);
$tests=0;
function routeCheck(bool $ok,string $label):void {global $tests;if(!$ok)throw new RuntimeException($label);$tests++;echo "PASS: $label\n";}
foreach($routes as $route)if(is_string($route['handler'])&&str_contains($route['handler'],'@')){[$class,$method]=explode('@',$route['handler']);routeCheck(method_exists('App\\Controllers\\'.$class,$method),'registered handler '.$route['path']);}
foreach(['/api/admin/members/requests'=>'@requests','/api/admin/public-content/sections'=>'@index','/api/admin/public-content/sections/profile'=>'@show'] as $path=>$target){$matched=null;foreach($routes as $r)if($r['method']==='GET'&&preg_match($r['regex'],$path)){$matched=$r;break;}routeCheck($matched&&str_ends_with($matched['handler'],$target),'first-match '.$path);}
foreach($routes as $route){
 if(preg_match('#^/api/admin/(news|faqs|members|dividends)(?:/|$)#',$route['path'])){
  routeCheck(in_array(AuthMiddleware::class,$route['middlewares'],true),'auth guard '.$route['path']);
  routeCheck(count(array_filter($route['middlewares'],fn($m)=>$m instanceof RoleMiddleware))===0,'custom roles use permissions '.$route['path']);
  routeCheck(count(array_filter($route['middlewares'],fn($m)=>$m instanceof PermissionMiddleware))>0,'permission guard '.$route['path']);
  if($route['method']!=='GET')routeCheck(in_array(CsrfMiddleware::class,$route['middlewares'],true),'CSRF guard '.$route['path']);
 }
}
echo "Workflow route checks: $tests passed\n";
