<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';

use App\Core\Database;
use App\Services\DividendImportService as Import;
use App\Services\MemberAdminService as Admin;
use App\Services\MemberDataException;

function verify(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: {$label}\n";
}
function rejected(callable $work, string $label): void {
    try { $work(); } catch (MemberDataException $e) { verify(true,$label); return; }
    verify(false,$label);
}

// Clone schemas into a disposable database. No live member rows are copied or edited.
$original = Database::connect();
$name = 'rycoop_member_test_'.bin2hex(random_bytes(6));
$original->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$config = config('database.connections.mysql');
$test = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$name};charset=utf8mb4",$config['username'],$config['password'],$config['options']);
$instance = new ReflectionProperty(Database::class,'instance');
try {
    foreach (['users','roles','user_roles','members','member_dividends','member_data_changes','member_import_runs'] as $table) {
        $schema=$original->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
        $test->exec($schema);
    }
    $instance->setValue(null,$test);
    Database::execute("INSERT INTO roles(name,slug) VALUES ('Member','member'),('Admin','super_admin')");
    $row=[2565,'1234567890123','00001','Test member','Test department',1000,200,0,0,1200,100,0,0,0,0,0,100,1100,1100,5.3,8];
    $records=Import::normalize([Import::HEADERS,$row],2565)['records'];
    $preview=Import::databasePreview($records);
    verify($preview['counts']['created']===1 && $preview['counts']['new_members']===1,'preview detects new historical year and member');
    Import::import($records,['snapshots'=>$preview['snapshots'],'source_name'=>'test.xlsx']);
    $member=Database::first('SELECT id,user_id FROM members'); $id=(int)$member['id'];
    $hash=Database::value('SELECT password FROM users WHERE id=?',[$member['user_id']]);
    verify(password_verify('00001',$hash),'new account keeps leading zero password');
    verify((int)Database::value('SELECT COUNT(*) FROM member_data_changes')===1,'import audit saved');
    $preview=Import::databasePreview($records);
    verify($preview['counts']['unchanged']===1,'preview recognises unchanged amounts');
    Import::import($records,['snapshots'=>$preview['snapshots']]);
    verify((int)Database::value('SELECT COUNT(*) FROM member_dividends')===1 && Database::value('SELECT password FROM users WHERE id=?',[$member['user_id']])===$hash,'repeat import has no duplicates or password reset');

    $before=Admin::profile($id);
    $input=$before+['version'=>Admin::version($before),'reason'=>'Correct member profile'];
    $input['first_name']='Corrected';$input['id_card']='1234567890124';$input['member_no']='2';$input['status']='suspended';
    Admin::updateProfile($id,$input);
    $after=Admin::profile($id);
    $user=Database::first('SELECT username,status,password FROM users WHERE id=?',[$member['user_id']]);
    verify($after['member_no']==='00002' && $user['username']===$after['id_card'] && $user['status']==='suspended' && $user['password']===$hash,'profile synchronises login and suspension while preserving password');
    rejected(fn()=>Admin::updateProfile($id,$input),'stale profile version rejected');
    $current=$after+['version'=>Admin::version($after),'reason'=>''];
    rejected(fn()=>Admin::updateProfile($id,$current),'profile requires reason');

    $old=json_decode(Database::value('SELECT details_json FROM member_dividends WHERE member_id=?',[$id]),true);
    $edit=$old+['version'=>Admin::version($old),'reason'=>'Correct amounts'];
    foreach($edit['income'] as $key=>$v)$edit['income'][$key]='0';
    foreach($edit['deductions'] as $key=>$v)$edit['deductions'][$key]='0';
    $edit['income'][Import::INCOME[0]]='0.10';$edit['income'][Import::INCOME[1]]='0.20';
    Admin::updateDividend($id,2565,$edit);
    $saved=json_decode(Database::value('SELECT details_json FROM member_dividends WHERE member_id=?',[$id]),true);
    verify($saved['net']==='0.30' && $saved['total_income']==='0.30','financial totals computed in exact cents');
    rejected(fn()=>Admin::updateDividend($id,2565,$edit),'stale dividend version rejected');
    $edit['version']=Admin::version($saved);$edit['income'][Import::INCOME[0]]='0.123';
    rejected(fn()=>Admin::updateDividend($id,2565,$edit),'fractional cents rejected');

    $records[0]['id_card']=$after['id_card'];$records[0]['member_no']=$after['member_no'];
    $preview=Import::databasePreview($records);
    $current=$after+['version'=>Admin::version($after),'reason'=>'Update contact'];$current['phone']='0100000000';
    Admin::updateProfile($id,$current);
    rejected(fn()=>Import::import($records,['snapshots'=>$preview['snapshots'],'update_profiles'=>true]),'import refuses overwriting profile changed after preview');
    $first=$records[0];$first['id_card']='2234567890123';$first['member_no']='00003';
    $conflict=$records[0];$conflict['id_card']='3234567890123';
    $runCount=Database::value('SELECT COUNT(*) FROM member_import_runs');
    rejected(fn()=>Import::import([$first,$conflict]),'conflicting member identity aborts entire batch');
    verify((int)Database::value('SELECT COUNT(*) FROM members')===1 && Database::value('SELECT COUNT(*) FROM member_import_runs')===$runCount,'failed batch rolls back new accounts and import history');

    Import::import([$first]);
    $current=Admin::profile($id);$input=$current+['version'=>Admin::version($current),'reason'=>'Duplicate check'];$input['id_card']=$first['id_card'];
    rejected(fn()=>Admin::updateProfile($id,$input),'duplicate national identity rejected');
    $memberRole=Database::value("SELECT id FROM roles WHERE slug='super_admin'");
    Database::execute('INSERT INTO user_roles(user_id,role_id) VALUES (?,?)',[$member['user_id'],$memberRole]);
    rejected(fn()=>Import::import($records,['update_profiles'=>true]),'member import cannot edit staff account');
    $newYear=$first;$newYear['year']=2564;Import::import([$newYear]);
    verify((int)Database::value('SELECT COUNT(*) FROM member_dividends')===3,'other historical years preserved');
    echo "Member administration integration tests passed.\n";
} finally {
    $instance->setValue(null,$original);
    // The random test-only name is the sole allowed deletion target.
    if (!preg_match('/^rycoop_member_test_[a-f0-9]{12}$/D',$name)) throw new RuntimeException('Unsafe cleanup target');
    $original->exec("DROP DATABASE `{$name}`");
}
