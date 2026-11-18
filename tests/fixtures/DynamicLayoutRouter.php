<?php
declare(strict_types=1);
// CLI loopback-only integration harness. Never registered in application routing.
if(PHP_SAPI!=='cli-server'||getenv('DYNAMIC_LAYOUT_HTTP')!=='1'||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(404);exit;}
require __DIR__.'/../DynamicLayoutFixture.php';
use App\Core\{Auth,Csrf,Database,Request,Response,Router};
$file=getenv('DYNAMIC_LAYOUT_FIXTURE_FILE');$base=realpath(__DIR__.'/../../storage/tmp');$parent=$file?realpath(dirname($file)):false;
if(!$base||!$parent||!str_starts_with(str_replace('\\','/',$parent).'/',str_replace('\\','/',$base).'/'))throw new RuntimeException('Fixture database must stay in storage/tmp');
$disk=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if(!$disk->query("SELECT name FROM sqlite_master WHERE name='dynamic_layout_state'")->fetch()){
    foreach($pdo->query("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll() as $table){$disk->exec($table['sql']);foreach($pdo->query('SELECT * FROM "'.$table['name'].'"')->fetchAll() as $row){$columns=implode(',',array_map(fn($k)=>'"'.$k.'"',array_keys($row)));$q=$disk->prepare('INSERT INTO "'.$table['name'].'"('.$columns.') VALUES('.implode(',',array_fill(0,count($row),'?')).')');$q->execute(array_values($row));}}
    $disk->exec("UPDATE users SET two_factor_enabled=1,two_factor_confirmed_at=CURRENT_TIMESTAMP,two_factor_secret='JBSWY3DPEHPK3PXP' WHERE id=1");
}
$disk->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));(new ReflectionProperty(Database::class,'instance'))->setValue(null,$disk);Database::resetTableCache();
$request=new Request();$response=new Response();$router=new Router();
$router->post('/__fixture/login',function(Request $r,Response $s){$id=(int)$r->input('id',1);$u=Database::first('SELECT u.*,r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=?',[$id]);if(!$u)throw new RuntimeException('Invalid fixture identity');Auth::logout();Auth::login($u,(bool)$r->input('mfa',true));$s->json(['success'=>true,'token'=>Csrf::token()]);});
$router->get('/csrf-token',fn(Request $r,Response $s)=>$s->json(['token'=>Csrf::token()]));
$router->post('/api/auth/mfa/step-up','Admin\\MfaController@stepUp',[\App\Middlewares\AuthMiddleware::class,\App\Middlewares\CsrfMiddleware::class]);
require __DIR__.'/../../config/dynamic-layout-routes.php';
$router->dispatch($request,$response);
