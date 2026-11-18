<?php
declare(strict_types=1);
// Explicit disposable database only. Never consumes .env or the application connection config.
$dsn=getenv('DYNAMIC_LAYOUT_MYSQL_DSN')?:'';
if(getenv('DYNAMIC_LAYOUT_ISOLATED')!=='1'||!preg_match('/^mysql:host=127\.0\.0\.1;port=[0-9]+;dbname=rycoopweb_test$/D',$dsn))throw new RuntimeException('Require explicit isolated loopback MySQL rycoopweb_test DSN');
putenv('APP_KEY=isolated-layout-mysql-fixture-00000000000000000000');putenv('SESSION_SECURE=false');
$root=__DIR__.'/../storage/tmp/dynamic-layout-mysql';if(!is_dir($root))mkdir($root,0700,true);putenv('SESSION_SAVE_PATH='.$root);
require __DIR__.'/../vendor/autoload.php';
use App\Core\{Auth,Database};
use App\Services\{DynamicLayoutService as Layout,TwoFactorService,DualControlSecurityService};
$pdo=new PDO($dsn,getenv('DYNAMIC_LAYOUT_MYSQL_USER')?:'root',getenv('DYNAMIC_LAYOUT_MYSQL_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$pdo);
if(($argv[1]??'')==='worker'){
    $id=(int)$argv[2];Auth::login(Database::first('SELECT u.*,r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=?',[$id]),true);
    $secret='JBSWY3DPEHPK3PXP';$code=(new ReflectionMethod(TwoFactorService::class,'calculateCode'))->invoke(null,$secret,(int)floor(time()/30));DualControlSecurityService::verifyAndRecordStepUpMfa($id,$code,'layout.publish');
    file_put_contents($root.'/ready-'.$id,'ready');
    try{$r=Layout::publish(['version'=>1]);echo 'SUCCESS '.$r['revision_id'];}catch(\App\Exceptions\SecurityGateException $e){if($e->getHttpStatus()!==409)throw $e;echo 'STALE';}exit;
}
if(Database::query('SHOW TABLES'))throw new RuntimeException('Refusing a nonempty database: provision a fresh disposable MySQL service');
echo 'ENGINE: '.$pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
$pdo->exec("CREATE TABLE users(id BIGINT PRIMARY KEY,name VARCHAR(100),email VARCHAR(100),status VARCHAR(20),deleted_at DATETIME NULL,session_version INT DEFAULT 1,two_factor_enabled INT,two_factor_confirmed_at DATETIME,two_factor_secret VARCHAR(100),two_factor_last_used_slice BIGINT NULL,updated_at DATETIME);
CREATE TABLE roles(id BIGINT PRIMARY KEY,name VARCHAR(100),slug VARCHAR(50));CREATE TABLE user_roles(user_id BIGINT,role_id BIGINT);
CREATE TABLE permissions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,module VARCHAR(50),action VARCHAR(50),description VARCHAR(255),UNIQUE(module,action));CREATE TABLE role_permissions(role_id BIGINT,permission_id BIGINT);
CREATE TABLE audit_logs(id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id BIGINT,module VARCHAR(50),action VARCHAR(50),record_id VARCHAR(100),old_values LONGTEXT,new_values LONGTEXT,ip_address VARCHAR(100),user_agent TEXT,created_at DATETIME);
CREATE TABLE rate_limit_attempts(id BIGINT AUTO_INCREMENT PRIMARY KEY,rate_key VARCHAR(190),ip_address VARCHAR(100),window_started_at DATETIME,attempts INT,updated_at DATETIME,UNIQUE(rate_key,ip_address));
INSERT INTO roles VALUES(1,'Fixture admin','super_admin');
INSERT INTO users VALUES(1,'Fixture A','a@example.invalid','active',NULL,1,1,NOW(),'JBSWY3DPEHPK3PXP',NULL,NOW()),(2,'Fixture B','b@example.invalid','active',NULL,1,1,NOW(),'JBSWY3DPEHPK3PXP',NULL,NOW());INSERT INTO user_roles VALUES(1,1),(2,1);");
$sql=file_get_contents(__DIR__.'/../database/migrations/032_dynamic_layout_gallery.sql');$pdo->exec($sql);$pdo->exec($sql);
if((int)Database::value('SELECT COUNT(*) FROM dynamic_layout_state')!==1||(int)Database::value("SELECT COUNT(*) FROM permissions WHERE module='layout'")!==3)throw new RuntimeException('Migration idempotency failed');
echo 'PASS: forward migration runs twice without overwriting or duplicate permissions'.PHP_EOL;
foreach([1,2] as $id){$ready=$root.'/ready-'.$id;if(is_file($ready))unlink($ready);}
$pdo->beginTransaction();$pdo->query('SELECT id FROM dynamic_layout_state WHERE id=1 FOR UPDATE')->fetch();$workers=[];
try{
    foreach([1,2] as $id){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($process))throw new RuntimeException('Cannot start worker');fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $deadline=microtime(true)+15;while((!is_file($root.'/ready-1')||!is_file($root.'/ready-2'))&&microtime(true)<$deadline)usleep(20000);
    if(!is_file($root.'/ready-1')||!is_file($root.'/ready-2'))throw new RuntimeException('Workers did not reach publication barrier');
    $pdo->commit();$results=[];foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);if($code!==0)throw new RuntimeException($err);$results[]=$out;}$workers=[];
    if(count(array_filter($results,fn($s)=>str_starts_with($s,'SUCCESS ')))!==1||count(array_filter($results,fn($s)=>$s==='STALE'))!==1)throw new RuntimeException('Unexpected concurrent result: '.json_encode($results));
    if((int)Database::value('SELECT COUNT(*) FROM dynamic_layout_revisions')!==1||(int)Database::value('SELECT version FROM dynamic_layout_state WHERE id=1')!==2||(int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='dynamic_layout' AND action='publish'")!==1)throw new RuntimeException('Non-atomic publication result');
    echo 'PASS: two independent real-TOTP publishers serialize; exactly one succeeds, one stale rejection, one immutable revision, one current pointer and one audit'.PHP_EOL;
}finally{if($pdo->inTransaction())$pdo->rollBack();foreach($workers as [$process,$pipes]){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}}
