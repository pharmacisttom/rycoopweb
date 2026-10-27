<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Core\Database;
function portalAssert(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
function portalCall(string $db,int $user,string $method,array $body=[]): array {
    $code='require "vendor/autoload.php"; App\\Core\\Session::set("user_id",(int)$argv[1]);App\\Core\\Session::set("2fa_verified",true);$_POST=json_decode($argv[3],true);$_SERVER["REQUEST_METHOD"]="POST";if($argv[2]==="approveProfileRequest"){echo json_encode(App\\Services\\MemberRequestService::approveProfileRequest((int)$_POST["id"],(int)$argv[1]));exit;}$c=new App\\Controllers\\Api\\SpaApiController(new App\\Core\\Request,new App\\Core\\Response);$m=$argv[2];$c->$m();';
    $p=proc_open([PHP_BINARY,'-d','display_errors=stderr','-r',$code,(string)$user,$method,json_encode($body)],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),array_merge(getenv(),['DB_DATABASE'=>$db,'APP_DEBUG'=>'false']));
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);
    if($status!==0)throw new RuntimeException($errors);return json_decode($output,true,512,JSON_THROW_ON_ERROR);
}
$original=Database::connect();$name='rycoop_portal_test_'.bin2hex(random_bytes(6));
$original->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$cfg=config('database.connections.mysql');$test=new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$name};charset=utf8mb4",$cfg['username'],$cfg['password'],$cfg['options']);
try {
    foreach(['users','roles','user_roles','members','audit_logs','member_data_changes'] as $table)$test->exec($original->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1]);
    $test->exec(ltrim(file_get_contents(__DIR__.'/../database/migrations/018_member_profile_requests.sql'),"\xEF\xBB\xBF"));
    $test->exec("INSERT INTO roles(id,name,slug) VALUES(1,'Member','member')");
    foreach([1,2] as $id){
        $test->exec("INSERT INTO users(id,uuid,name,username,email,password,status) VALUES({$id},'portal-test-{$id}','Test member {$id}','test-member-{$id}','member{$id}@example.invalid','unused','active')");
        $test->exec("INSERT INTO user_roles(user_id,role_id) VALUES({$id},1)");
        $test->exec("INSERT INTO members(id,uuid,user_id,member_no,id_card,first_name,last_name,status) VALUES({$id},'portal-test-{$id}',{$id},'TEST-{$id}','TEST-NOT-CITIZEN-{$id}','Test','Member','active')");
    }
    $result=portalCall($name,1,'memberSubmitProfileRequest',['member_id'=>2,'phone'=>'0890000000','email'=>'updated@example.invalid','address'=>'Test address']);
    portalAssert($result['success']??false,'profile request endpoint persists a request');
    $saved=$test->query('SELECT member_id,status FROM member_profile_requests')->fetch(PDO::FETCH_ASSOC);
    portalAssert((int)$saved['member_id']===1 && $saved['status']==='submitted','forged member id cannot change request ownership');
    portalAssert($test->query('SELECT email FROM members WHERE id=1')->fetchColumn()===null,'submission does not update profile before approval');
    $own=portalCall($name,1,'memberGetRequests');$other=portalCall($name,2,'memberGetRequests');
    portalAssert(count($own['data'])===1 && count($other['data'])===0,'member request list isolates two accounts');
    $bad=portalCall($name,1,'memberSubmitProfileRequest',['email'=>'invalid-email']);
    portalAssert(!$bad['success'] && (int)$test->query('SELECT COUNT(*) FROM member_profile_requests')->fetchColumn()===1,'invalid input cannot create a request');
    $test->exec("UPDATE members SET status='suspended' WHERE id=1");
    $blocked=portalCall($name,1,'memberSubmitProfileRequest',['phone'=>'0890000000']);
    portalAssert(!$blocked['success'],'suspended member cannot submit requests');
    $test->exec("UPDATE members SET status='active' WHERE id=1");
    $test->exec("INSERT INTO users(id,uuid,name,username,email,password,status) VALUES(3,'portal-admin','Test administrator','test-administrator','admin@example.invalid','unused','active')");
    $approved=portalCall($name,3,'approveProfileRequest',['id'=>1]);
    portalAssert(($approved['status']??'')==='APPROVED' && $test->query('SELECT email FROM members WHERE id=1')->fetchColumn()==='updated@example.invalid','approval commits requested profile changes');
    portalAssert($test->query('SELECT email FROM members WHERE id=2')->fetchColumn()===null && (int)$test->query('SELECT COUNT(*) FROM member_data_changes WHERE member_id=1 AND user_id=3')->fetchColumn()===1,'approval preserves other members and records administrator history');
} finally {
    $original->exec("DROP DATABASE `{$name}`");
}
