<?php
declare(strict_types=1);
if (getenv('APP_ENV') !== 'testing' || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('CRUD_DISPOSABLE_MYSQL') !== '1' || getenv('DB_DATABASE') !== 'rayongcoop_ci'
    || !in_array(getenv('DB_HOST'), ['127.0.0.1','localhost'], true)
    || getenv('DB_USERNAME') !== 'rycoop_ci_app') {
    fwrite(STDERR, "REFUSED: disposable CI MySQL only\n"); exit(2);
}
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Database;
use App\Services\DynamicLayoutService as Layout;
function checkDynamic(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label); echo "PASS: $label\n";}
$keys=['dynamic_home_sections_v1','dynamic_home_sections_draft_v1'];
$before=[];
foreach($keys as $key)$before[$key]=Database::first('SELECT * FROM site_settings WHERE '.chr(96).'key'.chr(96).'=?',[$key]);
$node=static fn(string $id,string $title,array $children=[]):array=>[
'id'=>$id,'title'=>$title,'type'=>'gallery','layout'=>'grid','visible'=>true,
'description'=>'กิจกรรมสหกรณ์','link'=>'/activities','album_ids'=>[1,3],'children'=>$children];
try {
 foreach($keys as $key)Database::execute('DELETE FROM site_settings WHERE '.chr(96).'key'.chr(96).'=?',[$key]);
 checkDynamic(Layout::published()===[],'public defaults empty');
 $tree=[$node('main-activity','รูปกิจกรรมของสหกรณ์',[$node('meeting-2569','ประชุมใหญ่'),$node('social-activity','จิตอาสา')])];
 $initial=Layout::state();
 $saved=Layout::draft(['version'=>$initial['version'],'sections'=>$tree]);
 checkDynamic(count($saved['draft'][0]['children'])===2,'nested draft persists');
 checkDynamic(Layout::published()===[],'draft stays private');
 try {Layout::draft(['version'=>$initial['version'],'sections'=>$tree]);throw new RuntimeException('stale accepted');}
 catch(RuntimeException $e){if($e->getCode()!==409)throw $e;}
 checkDynamic(true,'stale version rejected');
 Layout::publish(['version'=>$saved['version']]);
 checkDynamic(Layout::published()[0]['title']==='รูปกิจกรรมของสหกรณ์','published snapshot persists');
 $invalid=[$node('duplicate-id','parent',[$node('duplicate-id','child')])];
 try{Layout::validate($invalid);throw new RuntimeException('duplicate accepted');}
 catch(InvalidArgumentException $e){checkDynamic(true,'duplicate IDs rejected');}
 $invalid=[$node('valid-id','unsafe')];$invalid[0]['link']='javascript:alert(1)';
 try{Layout::validate($invalid);throw new RuntimeException('unsafe URL accepted');}
 catch(InvalidArgumentException $e){checkDynamic(true,'unsafe URL rejected');}
 $invalid=[$node('valid-id','invalid album')];$invalid[0]['album_ids']=[1,'2'];
 try{Layout::validate($invalid);throw new RuntimeException('invalid album accepted');}
 catch(InvalidArgumentException $e){checkDynamic(true,'album ID validation');}
 checkDynamic((int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='dynamic_layout'")>=2,'audit logs recorded');
} finally {
 foreach($keys as $key){
  Database::execute('DELETE FROM site_settings WHERE '.chr(96).'key'.chr(96).'=?',[$key]);
  if($before[$key]){$r=$before[$key];Database::execute(
   'INSERT INTO site_settings ('.chr(96).'key'.chr(96).','.chr(96).'value'.chr(96).','.chr(96).'group'.chr(96).',is_public,created_at,updated_at) VALUES (?,?,?,?,?,?)',
   [$r['key'],$r['value'],$r['group'],$r['is_public'],$r['created_at'],$r['updated_at']]);}
 }
}
echo "PASS: disposable MySQL dynamic layout contract complete\n";
