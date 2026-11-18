<?php
declare(strict_types=1);
if (getenv('APP_ENV') !== 'testing' || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('CRUD_DISPOSABLE_MYSQL') !== '1' || getenv('DB_DATABASE') !== 'rayongcoop_ci'
    || !in_array(getenv('DB_HOST'), ['127.0.0.1','localhost'], true)
    || getenv('DB_USERNAME') !== 'rycoop_ci_app') {
    fwrite(STDERR,"REFUSED: disposable MySQL only\n");exit(2);
}
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Database;
use App\Services\SiteChromeVisibilityService as Chrome;
function chk(bool $ok,string $label):void {
 if(!$ok)throw new RuntimeException($label);
 echo "PASS: $label\n";
}
$q=chr(96);
$keys=['site_chrome_visibility_v1','site_chrome_visibility_draft_v1'];
$before=[];
foreach($keys as $key)$before[$key]=Database::first('SELECT * FROM site_settings WHERE '.$q.'key'.$q.'=?',[$key]);
try{
 foreach($keys as $key)Database::execute('DELETE FROM site_settings WHERE '.$q.'key'.$q.'=?',[$key]);
 $defaults=Chrome::published();
 chk(count($defaults)===6 && !in_array(false,$defaults,true),'all optional groups visible by default');
 $state=Chrome::state();
 $draft=$defaults;$draft['nav_menu']=false;$draft['footer_finance']=false;
 $saved=Chrome::draft(['version'=>$state['version'],'sections'=>$draft]);
 chk(Chrome::published()['nav_menu']===true,'draft does not leak to public');
 chk($saved['draft']['nav_menu']===false,'draft persists to database');
 try{Chrome::draft(['version'=>$state['version'],'sections'=>$draft]);throw new RuntimeException('stale version accepted');}
 catch(RuntimeException $e){if((int)$e->getCode()!==409)throw $e;}
 chk(true,'stale editor rejected');
 Chrome::publish(['version'=>$saved['version']]);
 chk(Chrome::published()['nav_menu']===false && Chrome::published()['footer_finance']===false,'publish changes public settings');
 chk(Chrome::state()['draft']===null,'draft cleared after publication');
 try{$invalid=$defaults;$invalid['footer_brand']='0';Chrome::validate($invalid);throw new RuntimeException('invalid type accepted');}
 catch(InvalidArgumentException $e){chk(true,'nonboolean settings rejected');}
 try{$invalid=$defaults;$invalid['unknown']=true;Chrome::validate($invalid);throw new RuntimeException('unknown key accepted');}
 catch(InvalidArgumentException $e){chk(true,'unapproved keys rejected');}
 chk((int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='site_chrome'")>=2,'audit trail persisted');
}finally{
 foreach($keys as $key){
   Database::execute('DELETE FROM site_settings WHERE '.$q.'key'.$q.'=?',[$key]);
   if($before[$key]){
     $r=$before[$key];
     Database::execute('INSERT INTO site_settings ('.$q.'key'.$q.','.$q.'value'.$q.','.$q.'group'.$q.',is_public,created_at,updated_at) VALUES (?,?,?,?,?,?)',
       [$r['key'],$r['value'],$r['group'],$r['is_public'],$r['created_at'],$r['updated_at']]);
   }
 }
}
echo "PASS: Site Chrome visibility MySQL contract complete\n";
