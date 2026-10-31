<?php
declare(strict_types=1);
// Source inspection only: no session, database connection, migration or network request.
require dirname(__DIR__).'/vendor/autoload.php';
$router=new \App\Core\Router(); require dirname(__DIR__).'/config/routes.php';
$routes=(new ReflectionProperty($router,'routes'))->getValue($router);
echo "# Admin endpoint source inventory\n\nGenerated with `php scripts/audit-crud-inventory.php`. Source registration is not proof of working CRUD. Tables/services are controller-level references, not runtime query traces.\n\n| Method | URL | Handler | Middleware | Controller references |\n|---|---|---|---|---|\n";
foreach($routes as $route){
    if(!preg_match('#^/(api/admin|admin|staff)(/|$)#',$route['path']))continue;
    $handler=is_string($route['handler'])?$route['handler']:'closure';
    $class=explode('@',$handler)[0];$class=preg_replace('/\\\\+/','/',$class);
    $file=dirname(__DIR__).'/app/Controllers/'.$class.'.php';$source=is_file($file)?file_get_contents($file):'';
    preg_match_all('/(?:FROM|INTO|UPDATE|JOIN)\s+`?([a-z_][a-z_0-9]*)/i',$source,$tables);
    preg_match_all('/use App\\\\Services\\\\([A-Za-z0-9_]+)/',$source,$services);
    $middleware=array_map(static fn($m)=>is_object($m)?get_class($m):(string)$m,$route['middlewares']);
    echo '| '.$route['method'].' | `'.$route['path'].'` | `'.$handler.'` | '.implode(', ',array_map(static fn($m)=>str_replace('App\\Middlewares\\','',$m),$middleware)).' | '.implode(', ',array_unique(array_merge($tables[1],$services[1])))." |\n";
}
echo "\n## Frontend mutation controls (source locations)\n\n";
foreach(glob(dirname(__DIR__).'/src/pages/*.jsx') as $file){
    if(!preg_match('/(?:Admin|Content)/',basename($file)))continue;
    foreach(file($file) as $line=>$text){
        if(preg_match('/onSubmit|onClick.*(?:save|delete|disable|publish|approve|import|mutate|setRole|setAccount|setEdit)|memberAdminApi\(/i',$text)){
            echo '- `src/pages/'.basename($file).':'.($line+1).'` '.substr(trim(preg_replace('/\s+/',' ',$text)),0,180)."\n";
        }
    }
}
