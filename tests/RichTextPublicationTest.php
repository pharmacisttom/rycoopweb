<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Core\Database;
function publicationCall(string $db,string $method,array $body=[],string $id=''): array {
    $code='require "vendor/autoload.php"; App\\Core\\Session::set("user_id",1); $_GET=[]; $_POST=json_decode($argv[2],true); $_SERVER["REQUEST_METHOD"]="POST"; $c=new App\\Controllers\\Api\\SpaApiController(new App\\Core\\Request,new App\\Core\\Response);$m=$argv[1]; if(in_array($m,["deleteAdminFaq","updateAdminFaq","updateAdminNews"])){$c->$m($argv[3]);}else{$c->$m();}';
    $p=proc_open([PHP_BINARY,'-d','display_errors=stderr','-r',$code,$method,json_encode($body),$id],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__),array_merge(getenv(),['DB_DATABASE'=>$db,'APP_DEBUG'=>'false']));
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($p);
    if($status!==0)throw new RuntimeException($errors);return json_decode($output,true,512,JSON_THROW_ON_ERROR);
}
function publicationCheck(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
$original=Database::connect();$name='rycoop_content_test_'.bin2hex(random_bytes(6));
$original->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$cfg=config('database.connections.mysql');$test=new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$name};charset=utf8mb4",$cfg['username'],$cfg['password'],$cfg['options']);
try {
    foreach(['users','news_categories','news','faqs','audit_logs'] as $table)$test->exec($original->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1]);
    $test->exec("INSERT INTO users(uuid,name,username,email,password,status) VALUES ('content-test','Editor test','editor-test','editor@example.invalid','unused','active')");
    $test->exec("INSERT INTO news_categories(name,slug,status) VALUES ('ข่าวประชาสัมพันธ์','test-news','active')");
    $body=['title'=>'ข่าวทดสอบในฐานข้อมูลชั่วคราว','category'=>'ข่าวประชาสัมพันธ์','excerpt'=>'สรุปข่าว','content'=>'<h2>หัวข้อ</h2><p><strong>ข่าวฉบับเต็ม</strong></p><script>alert(1)</script>','image'=>''];
    $result=publicationCall($name,'createAdminNews',$body);
    publicationCheck($result['success']??false,'news publication API saves to isolated database');
    $saved=$test->query('SELECT summary,content FROM news')->fetch(PDO::FETCH_ASSOC);
    publicationCheck($saved['summary']==='สรุปข่าว' && str_contains($saved['content'],'<strong>') && !str_contains($saved['content'],'<script'),'full formatted content stored separately from plain summary and sanitized');
    $public=publicationCall($name,'news');
    publicationCheck($public['success'] && $public['data'][0]['content']===$saved['content'],'public news API returns full saved article');
    $body['content']='<p><em>แก้ไขข่าวเดิม</em></p>';
    $updated=publicationCall($name,'updateAdminNews',$body,(string)$result['data']['id']);
    publicationCheck($updated['success'] && str_contains($updated['data']['content'],'<em>') && (int)$test->query('SELECT COUNT(*) FROM news')->fetchColumn()===1,'editing news replaces content without creating a duplicate article');
    $body['content']='<p><br></p>';$result=publicationCall($name,'createAdminNews',$body);
    publicationCheck(!$result['success'] && (int)$test->query('SELECT COUNT(*) FROM news')->fetchColumn()===1,'empty formatted news rejected without publication');
    $result=publicationCall($name,'createAdminFaq',['question'=>'คำถามทดสอบ','answer'=>'<ul><li>คำตอบ</li></ul><img src=x onerror=alert(1)>']);
    publicationCheck($result['success'] && str_contains($result['data']['answer'],'<ul>') && !str_contains($result['data']['answer'],'onerror'),'FAQ answer persists with safe formatting');
    $updatedFaq=publicationCall($name,'updateAdminFaq',['question'=>'คำถามทดสอบ','answer'=>'<p><strong>แก้ไขคำตอบ</strong></p>'],(string)$result['data']['id']);
    publicationCheck($updatedFaq['success'] && str_contains($updatedFaq['data']['answer'],'<strong>'),'existing FAQ answer can be edited with formatting');
    publicationCall($name,'deleteAdminFaq',[],(string)$result['data']['id']);
    publicationCheck($test->query('SELECT status FROM faqs')->fetchColumn()==='inactive','FAQ removal is reversible through stored inactive record');
    echo 'Rich text publication tests passed.'.PHP_EOL;
} finally {
    if(!preg_match('/^rycoop_content_test_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Unsafe cleanup target');
    $original->exec("DROP DATABASE `{$name}`");
}
