<?php
declare(strict_types=1);
if (getenv('APP_ENV') !== 'testing' || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('CRUD_DISPOSABLE_MYSQL') !== '1' || getenv('DB_DATABASE') !== 'rayongcoop_ci'
    || !in_array(getenv('DB_HOST'), ['127.0.0.1','localhost'], true)
    || getenv('DB_USERNAME') !== 'rycoop_ci_app') {
    fwrite(STDERR,"REFUSED: disposable CI MySQL only\n");exit(2);
}
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Database;
use App\Services\MegaMenuService as Menu;
function verifyMenu(bool $ok,string $message):void{
    if(!$ok)throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message."\n";
}
$keys=['mega_menu_published_v1','mega_menu_draft_v1','mega_menu_previous_v1'];
try{
 foreach($keys as $key)Database::execute('DELETE FROM site_settings WHERE `key`=?',[$key]);
 verifyMenu(Menu::published()===null,'no published menu retains legacy Navbar');
 $initial=Menu::state();$draft=$initial['template'];
 $draft[0]['items'][]=['id'=>'qa_test','title'=>'CI Test Item','description'=>'Disposable only',
   'group'=>'QA','icon'=>'file-text','path'=>'/documents','enabled'=>false];
 $saved=Menu::draft(['version'=>$initial['version'],'categories'=>$draft]);
 verifyMenu($saved['draft'][0]['items'][5]['id']==='qa_test','draft stores new submenu item');
 verifyMenu(Menu::published()===null,'draft does not leak to public');
 try{Menu::draft(['version'=>$initial['version'],'categories'=>$draft]);throw new RuntimeException('accepted stale version');}
 catch(RuntimeException $e){verifyMenu((int)$e->getCode()===409,'stale update returns conflict');}
 $pub=Menu::publish(['version'=>$saved['version']]);
 verifyMenu(Menu::published()[0]['items'][5]['id']==='qa_test','published menu persisted');
 verifyMenu($pub['draft']===null,'draft cleared after publish');
 $second=$pub['published'];
 $second[0]['items'][5]['enabled']=true;
 $saved2=Menu::draft(['version'=>$pub['version'],'categories'=>$second]);
 $pub2=Menu::publish(['version'=>$saved2['version']]);
 verifyMenu(Menu::published()[0]['items'][5]['enabled']===true,'second publish updates item');
 $reverted=Menu::rollback(['version'=>$pub2['version']]);
 verifyMenu(Menu::published()[0]['items'][5]['enabled']===false,'rollback restores previous publication');
 verifyMenu($reverted['published'][0]['items'][5]['enabled']===false,'rollback response matches persistence');
 verifyMenu((int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='mega_menu'")>=5,
            'draft publish and rollback audited');
}finally{
 foreach($keys as $key)Database::execute('DELETE FROM site_settings WHERE `key`=?',[$key]);
}
echo "MEGA_MENU_DISPOSABLE_MYSQL_CRUD=PASS\n";
