<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use App\Services\MegaMenuService as Menu;
function check(bool $condition,string $name):void {
 if(!$condition)throw new RuntimeException('FAIL: '.$name);
 echo 'PASS: '.$name."\n";
}
function reject(array $value,string $name):void {
 try { Menu::validate($value); throw new RuntimeException('FAIL: accepted '.$name); }
 catch(InvalidArgumentException $expected) { echo 'PASS: rejected '.$name."\n"; }
}
$baseline=Menu::template();
check(count($baseline)===4,'four stable categories');
check(Menu::validate($baseline)===$baseline,'initial menu accepted');
$hidden=$baseline;$hidden[2]['enabled']=false;$hidden[2]['items'][0]['enabled']=false;
check(Menu::validate($hidden)===$hidden,'disable category and item');
$added=$baseline;$added[1]['items'][]=['id'=>'new_official','title'=>'ประกาศใหม่','description'=>'',
  'group'=>'บริการทางการเงิน','icon'=>'file-text','path'=>'/documents','enabled'=>false];
check(count(Menu::validate($added)[1]['items'])===7,'add disabled draft item');
$unsafe=$baseline;$unsafe[0]['items'][0]['path']='https://evil.example';
reject($unsafe,'external URL');
$unsafe=$baseline;$unsafe[0]['items'][0]['path']='/eservice';
reject($unsafe,'unreleased e-services');
$unsafe=$baseline;$unsafe[0]['items'][0]['path']='javascript:alert(1)';
reject($unsafe,'javascript URL');
$unsafe=$baseline;$unsafe[0]['items'][1]['id']=$unsafe[0]['items'][0]['id'];
reject($unsafe,'duplicate item ID');
$unsafe=$baseline;$unsafe[0]['title']='<script>';
reject($unsafe,'HTML injection in title');
$unsafe=$baseline;$unsafe[0]['enabled']='false';
reject($unsafe,'invalid visibility type');
$unsafe=$baseline;$unsafe[3]['id']='about';
reject($unsafe,'duplicate category ID');
echo "MEGA_MENU_VALIDATION_TEST=PASS\n";
