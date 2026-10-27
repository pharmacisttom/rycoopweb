<?php
declare(strict_types=1);
putenv('APP_KEY=isolated-workflow-key-not-for-production-0001');
putenv('SESSION_SECURE=false');
$path=__DIR__.'/../storage/tmp/workflow-test-sessions';if(!is_dir($path))mkdir($path,0700,true);putenv('SESSION_SAVE_PATH='.$path);
require __DIR__.'/../vendor/autoload.php';
use App\Core\{Auth,Database,Request,Response};
ob_start();
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$pdo);
$pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT,status TEXT,deleted_at TEXT,session_version INTEGER DEFAULT 1,two_factor_secret TEXT,two_factor_enabled INTEGER DEFAULT 0,two_factor_confirmed_at TEXT,two_factor_last_used_slice INTEGER,last_login_at TEXT,last_login_ip TEXT,updated_at TEXT);
CREATE TABLE roles(id INTEGER PRIMARY KEY,name TEXT,slug TEXT);CREATE TABLE user_roles(user_id INTEGER,role_id INTEGER);
CREATE TABLE permissions(id INTEGER PRIMARY KEY,module TEXT,action TEXT);CREATE TABLE role_permissions(role_id INTEGER,permission_id INTEGER);
CREATE TABLE audit_logs(id INTEGER PRIMARY KEY,user_id INTEGER,module TEXT,action TEXT,record_id TEXT,old_values TEXT,new_values TEXT,ip_address TEXT,user_agent TEXT,created_at TEXT);
CREATE TABLE user_mfa_recovery_codes(id INTEGER PRIMARY KEY,user_id INTEGER,code_hash TEXT,created_at TEXT,used_at TEXT);
CREATE TABLE rate_limit_attempts(`key` TEXT PRIMARY KEY,attempts INTEGER,expires_at TEXT);
INSERT INTO users(id,name,email,status) VALUES(1,'Fixture administrator','admin@example.invalid','active'),(2,'Fixture editor','editor@example.invalid','active'),(3,'Fixture publisher','publisher@example.invalid','active'),(4,'Fixture member','member@example.invalid','active');
INSERT INTO roles VALUES(1,'Admin','super_admin'),(2,'Editor','custom_editor'),(3,'Publisher','custom_publisher'),(4,'Member','member');
INSERT INTO user_roles VALUES(1,1),(2,2),(3,3),(4,4);");
$checks=[];
function workflowCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]='PASS: '.$label;}
function workflowLogin(int $id,bool $verified=true,bool $enroll=false):void {Auth::logout();Auth::login(Database::first('SELECT u.*,r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=?',[$id]),$verified,$enroll);}
function workflowFail(callable $work,int $code,string $label):void {try{$work();}catch(Throwable $e){workflowCheck($code===422?$e instanceof InvalidArgumentException:$e->getCode()===$code,$label);return;}throw new RuntimeException($label);}
class WorkflowResponse extends Response { public array $data=[];public int $status=200;public function json(array $data,int $status=200):void{$this->data=$data;$this->status=$status;} }
function workflowRequest(array $body=[]):Request {$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/api/auth/mfa/verify';$_POST=$body;$_GET=[];return new Request();}
function workflowFinish():void {global $checks;Auth::logout();(new ReflectionProperty(Database::class,'instance'))->setValue(null,null);ob_end_clean();echo implode(PHP_EOL,$checks).PHP_EOL;}
