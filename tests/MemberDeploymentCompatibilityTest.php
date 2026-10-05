<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';
use App\Core\Database;
use App\Services\DividendImportService;
use App\Services\MemberSystemHealth;

function checkDeployment(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}
function readController(string $database,string $method,array $query=[],?int $id=null):array {
    $code='require "vendor/autoload.php"; $_GET=json_decode($argv[2],true); $_POST=[]; $_SERVER["REQUEST_METHOD"]="GET"; $c=new App\\Controllers\\Admin\\MemberManagementController(new App\\Core\\Request,new App\\Core\\Response); $method=$argv[1]; if($method==="show"){$c->show($argv[3]);}else{$c->$method();}';
    $process=proc_open([PHP_BINARY,'-r',$code,$method,json_encode($query),(string)$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),array_merge(getenv(),['DB_DATABASE'=>$database,'APP_DEBUG'=>'false']));
    if(!is_resource($process))throw new RuntimeException('Cannot start controller test');
    $body=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    if($exit!==0)throw new RuntimeException('Controller process failed: '.$stderr);
    return json_decode($body,true,512,JSON_THROW_ON_ERROR);
}

$original=Database::connect();$name='rycoop_member_test_'.bin2hex(random_bytes(6));
$original->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$config=config('database.connections.mysql');
$test=new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$name};charset=utf8mb4",$config['username'],$config['password'],$config['options']);
$instance=new ReflectionProperty(Database::class,'instance');
try {
    foreach(['users','roles','user_roles','members','member_dividends','member_data_changes','member_import_runs'] as $table)$test->exec($original->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1]);
    $instance->setValue(null,$test);
    Database::execute("INSERT INTO roles(name,slug) VALUES ('Member','member'),('Member admin','member_admin')");
    $sample=[2568,'1234567890123','00001','Test member','Test department',1000,200,0,0,1200,100,0,0,0,0,0,100,1100,1100,5.3,8];
    DividendImportService::import(DividendImportService::normalize([DividendImportService::HEADERS,$sample],2568)['records']);
    $id=(int)Database::value('SELECT id FROM members');
    $password=Database::value('SELECT password FROM users');$report=Database::value('SELECT details_json FROM member_dividends');
    foreach(['prefix','department','phone','email','address','updated_at'] as $column)$test->exec('ALTER TABLE members DROP COLUMN '.$column);
    $test->exec('DROP TABLE member_data_changes');$test->exec('DROP TABLE member_import_runs');
    $health=MemberSystemHealth::inspect();
    checkDeployment(!$health['ready'] && in_array('members.prefix',$health['missing'],true),'legacy schema is diagnosed before writes');
    $dashboard=readController($name,'dashboard');
    checkDeployment($dashboard['success'] && $dashboard['partial'] && (int)$dashboard['members']['total']===1 && count($dashboard['years'])===1 && $dashboard['runs']===null,'missing history tables do not blank existing dashboard data');
    $members=readController($name,'index',['q'=>'Test','year'=>'2568','limit'=>50]);
    checkDeployment($members['success'] && $members['total']===1 && $members['items'][0]['net']==='1100.00' && $members['warnings'],'search and annual amounts work without legacy optional fields');
    $detail=readController($name,'show',[],$id);
    checkDeployment($detail['success'] && $detail['read_only'] && count($detail['dividends'])===1,'profile and dividends remain readable with writes disabled until migration');
    $test->exec(file_get_contents(__DIR__.'/../database/migrations/014_member_admin_history.sql'));
    $migration=file_get_contents(__DIR__.'/../database/migrations/016_repair_member_admin_profile_columns.sql');
    $test->exec($migration);$test->exec($migration);
    checkDeployment(MemberSystemHealth::inspect()['ready'],'repair migration is repeatable and restores complete schema');
    $detail=readController($name,'show',[],$id);
    checkDeployment($detail['success'] && !$detail['read_only'] && !$detail['warnings'],'member editing becomes available after migration');
    checkDeployment(Database::value('SELECT password FROM users')===$password && Database::value('SELECT details_json FROM member_dividends')===$report,'migration preserves passwords and historical financial records');
    echo "Member deployment compatibility tests passed.\n";
}finally {
    $instance->setValue(null,$original);
    if(!preg_match('/^rycoop_member_test_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Unsafe cleanup target');
    $original->exec("DROP DATABASE `{$name}`");
}
