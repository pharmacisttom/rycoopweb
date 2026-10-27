<?php
declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use App\Core\{Auth,Database};
use App\Services\PublicContentService as Content;

date_default_timezone_set('Asia/Bangkok');
$sessions=dirname(__DIR__).'/storage/tmp/content-test-sessions';if(!is_dir($sessions))mkdir($sessions,0700,true);
putenv('SESSION_SAVE_PATH='.$sessions);putenv('SESSION_SECURE=false');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$pdo);
$pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,email TEXT,status TEXT,deleted_at TEXT);
CREATE TABLE roles(id INTEGER PRIMARY KEY,name TEXT,slug TEXT);CREATE TABLE user_roles(user_id INTEGER,role_id INTEGER);
CREATE TABLE permissions(id INTEGER PRIMARY KEY,module TEXT,action TEXT);CREATE TABLE role_permissions(role_id INTEGER,permission_id INTEGER);
CREATE TABLE site_settings(id INTEGER PRIMARY KEY,`key` TEXT UNIQUE,`value` TEXT,`group` TEXT,is_public INTEGER);
CREATE TABLE public_content_revisions(id INTEGER PRIMARY KEY AUTOINCREMENT,section TEXT,payload TEXT,previous_payload TEXT,base_version TEXT,source_url TEXT,effective_on TEXT,expires_on TEXT,note TEXT,state TEXT,created_by INTEGER,published_by INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP,published_at TEXT);
CREATE TABLE audit_logs(id INTEGER PRIMARY KEY,user_id INTEGER,module TEXT,action TEXT,record_id TEXT,old_values TEXT,new_values TEXT,ip_address TEXT,user_agent TEXT,created_at TEXT);
INSERT INTO users VALUES(1,'Fixture admin','admin@example.invalid','active',NULL),(2,'Fixture editor','editor@example.invalid','active',NULL),(3,'Fixture publisher','publisher@example.invalid','active',NULL),(4,'Fixture member','member@example.invalid','active',NULL);
INSERT INTO roles VALUES(1,'Admin','super_admin'),(2,'Editor','content_editor'),(3,'Publisher','content_publisher'),(4,'Member','member');
INSERT INTO user_roles VALUES(1,1),(2,2),(3,3),(4,4);");
$id=1;foreach(['coop_info','people','rates','products'] as $module)foreach(['view','update','publish'] as $action){Database::execute('INSERT INTO permissions VALUES(?,?,?)',[$id,$module,$action]);foreach([2,3] as $role)if($action==='view'||($role===2&&$action==='update')||($role===3&&$action==='publish'))Database::execute('INSERT INTO role_permissions VALUES(?,?)',[$role,$id]);$id++;}
$checks=[];
function checkContent(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks[]='PASS: '.$label;}
function fixtureLogin(int $id,string $role):void{Auth::logout();Auth::login(['id'=>$id,'email'=>'fixture@example.invalid','name'=>'Fixture','role_slug'=>$role],true);}
function expectFailure(callable $work,int $code,string $label):void{try{$work();}catch(Throwable $e){checkContent($code===422?$e instanceof InvalidArgumentException:$e->getCode()===$code,$label);return;}throw new RuntimeException($label);}
function draftInput(string $section,array $value,array $extra=[]):array{return [...['value'=>$value,'version'=>Content::section($section)['version'],'source_url'=>'https://rayongcoop.com/','effective_on'=>date('Y-m-d'),'note'=>'Synthetic test change'],...$extra];}
try{
    fixtureLogin(4,'member');expectFailure(fn()=>Content::section('profile'),403,'member cannot read admin revisions');
    fixtureLogin(2,'content_editor');$before=Content::catalog()['coopInfo'];$changed=$before;$changed['phone']='038-000-001';
    $draft=Content::saveDraft('profile',draftInput('profile',$changed));
    checkContent(Content::catalog()['coopInfo']===$before,'saving draft does not alter public data');
    expectFailure(fn()=>Content::publish($draft),403,'editor cannot publish');
    fixtureLogin(3,'content_publisher');expectFailure(fn()=>Content::saveDraft('profile',draftInput('profile',$changed)),403,'publisher cannot edit drafts');
    Content::publish($draft);checkContent(Content::catalog()['coopInfo']['phone']==='038-000-001','publisher activates approved profile');
    expectFailure(fn()=>Content::publish($draft),409,'repeat publication rejected');
    fixtureLogin(2,'content_editor');$changed['phone']='038-000-002';$input=draftInput('profile',$changed);$a=Content::saveDraft('profile',$input);$b=Content::saveDraft('profile',$input);
    fixtureLogin(3,'content_publisher');Content::publish($a);expectFailure(fn()=>Content::publish($b),409,'stale draft cannot overwrite newer publication');
    checkContent(Database::value('SELECT state FROM public_content_revisions WHERE id=?',[$b])==='draft','failed stale publication retains draft');
    fixtureLogin(2,'content_editor');$future=$changed;$future['phone']='038-000-099';$id=Content::saveDraft('profile',draftInput('profile',$future,['effective_on'=>date('Y-m-d',strtotime('+2 days'))]));
    fixtureLogin(3,'content_publisher');Content::publish($id);checkContent(Content::catalog()['coopInfo']['phone']==='038-000-002','future publication does not activate early');
    fixtureLogin(2,'content_editor');$expired=$changed;$expired['phone']='038-000-088';$id=Content::saveDraft('profile',draftInput('profile',$expired,['effective_on'=>date('Y-m-d',strtotime('-2 days')),'expires_on'=>date('Y-m-d',strtotime('-1 day'))]));fixtureLogin(3,'content_publisher');Content::publish($id);checkContent(Content::catalog()['coopInfo']['phone']==='038-000-002','expired publication not shown');
    fixtureLogin(2,'content_editor');$rates=Content::catalog()['interestRates']['loans'];$rates[0]['rate']='5.75%';$id=Content::saveDraft('loan_rates',draftInput('loan_rates',$rates));fixtureLogin(3,'content_publisher');Content::publish($id);
    checkContent(Content::catalog()['loanProducts'][0]['rateValue']===5.75,'loan display and calculator follow published interest rate');
    fixtureLogin(2,'content_editor');expectFailure(fn()=>Content::saveDraft('profile',draftInput('profile',$changed,['source_url'=>'javascript:alert(1)'])),422,'unsafe source URL rejected');
    expectFailure(fn()=>Content::saveDraft('profile',draftInput('profile',$changed,['version'=>'stale'])),409,'stale editor version rejected');
    expectFailure(fn()=>Content::saveDraft('profile',draftInput('profile',[...$changed,'password'=>'not-allowed'])),422,'unknown profile fields rejected');
    $invalid=Content::catalog()['interestRates']['deposits'];$invalid[0]['rate']='-1%';expectFailure(fn()=>Content::saveDraft('deposits',draftInput('deposits',$invalid)),422,'negative rate rejected');
    expectFailure(fn()=>Content::saveDraft('profile',draftInput('profile',$changed,['expires_on'=>date('Y-m-d',strtotime('-1 day'))])),422,'expiry before effective date rejected');
    $board=Content::catalog()['boardMembers'];array_shift($board);expectFailure(fn()=>Content::saveDraft('boards',draftInput('boards',$board)),422,'cannot remove person still referenced in a committee');
    $restore=Content::saveDraft('profile',draftInput('profile',$before,['note'=>'Restore previous approved content']));fixtureLogin(3,'content_publisher');Content::publish($restore);checkContent(Content::catalog()['coopInfo']===$before,'restore through new reviewed revision succeeds');
    fixtureLogin(2,'content_editor');$products=Content::catalog()['loanProducts'];$products[0]['title']='Renamed fixture product';$id=Content::saveDraft('products',draftInput('products',$products));fixtureLogin(3,'content_publisher');Content::publish($id);
    fixtureLogin(2,'content_editor');$rates=Content::catalog()['interestRates']['loans'];$rates[0]['rate']='6.75%';$id=Content::saveDraft('loan_rates',draftInput('loan_rates',$rates));fixtureLogin(3,'content_publisher');Content::publish($id);
    checkContent(Content::catalog()['loanProducts'][0]['rateValue']===6.75,'stable product ID preserves rate sync after renaming');
    checkContent(Content::apiRates(Content::catalog())['loan'][0]['rate']===6.75,'legacy public API adapter uses published rates');
    checkContent(Content::apiProducts(Content::catalog())[0]['interest_rate']===6.75,'product API and calculator use the same published rate');
    checkContent((int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='public_content'")>=10,'before/after audit records persisted');
    fixtureLogin(2,'content_editor');$id=Content::saveDraft('profile',draftInput('profile',$changed));$pdo->exec('DROP TABLE audit_logs');fixtureLogin(3,'content_publisher');try{Content::publish($id);throw new RuntimeException('expected audit failure');}catch(PDOException $e){}
    checkContent(Database::value('SELECT state FROM public_content_revisions WHERE id=?',[$id])==='draft','audit failure rolls back publication');
    checkContent(Content::catalog()['coopInfo']===$before,'audit failure leaves public data unchanged');
}finally{Auth::logout();(new ReflectionProperty(Database::class,'instance'))->setValue(null,null);}
echo implode(PHP_EOL,$checks).PHP_EOL;
