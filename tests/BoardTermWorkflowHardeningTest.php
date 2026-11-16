<?php
declare(strict_types=1);
ob_start();
require __DIR__.'/../vendor/autoload.php';
use App\Core\{Auth,Database};
use App\Services\PublicContentService as Content;

date_default_timezone_set('Asia/Bangkok');
$sessions=dirname(__DIR__).'/storage/tmp/content-test-sessions';if(!is_dir($sessions))mkdir($sessions,0700,true);
putenv('SESSION_SAVE_PATH='.$sessions);putenv('SESSION_SECURE=false');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$pdo);
$pdo->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
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

use App\Services\BoardTermService as Board;
$schema=file_get_contents(__DIR__.'/../database/migrations/030_create_board_term_management_tables.sql');

// Schema fixtures only: never loads environment database configuration.
$schema=preg_replace('/--[^\n]*/','',$schema);
foreach(explode(';',$schema) as $sql){
    if(!str_contains($sql,'CREATE TABLE'))continue;
    $sql=preg_replace('/BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY/','INTEGER PRIMARY KEY AUTOINCREMENT',$sql);
    $sql=preg_replace('/BIGINT UNSIGNED|INT UNSIGNED|TINYINT\(1\)/','INTEGER',$sql);
    $sql=preg_replace("/ENUM\([^)]+\)/",'TEXT',$sql);
    $sql=preg_replace('/ ON UPDATE CURRENT_TIMESTAMP/','',$sql);
    $sql=preg_replace('/^\s*(?:UNIQUE KEY|INDEX|CONSTRAINT)[^\n]*\n?/m','',$sql);
    $sql=preg_replace('/,\s*\)/',')',$sql);
    $sql=preg_replace('/ENGINE=InnoDB.*$/s','',$sql);
    $pdo->exec($sql);
}
function ok(bool $value,string $label):void{if(!$value)throw new RuntimeException($label);echo "PASS: $label\n";}
function loginBoard(int $id,string $role,bool $mfa=true):void{Auth::logout();Auth::login(['id'=>$id,'email'=>'fixture@example.invalid','name'=>'Fixture','role_slug'=>$role],$mfa);}
function rejected(callable $fn,string $label):void{try{$fn();}catch(\InvalidArgumentException|\App\Exceptions\SecurityGateException $e){ok(true,$label);return;}throw new RuntimeException($label);}
function snapshotBoard(int $id):array{return [Database::query('SELECT * FROM board_terms WHERE id=?',[$id]),Database::query('SELECT * FROM board_term_members WHERE term_id=? ORDER BY id',[$id]),Database::query('SELECT * FROM board_term_groups WHERE term_id=? ORDER BY id',[$id]),Database::query('SELECT * FROM board_term_group_members ORDER BY id')];}
loginBoard(1,'super_admin');
$before=Content::catalog();$writes=$pdo->query('SELECT total_changes()')->fetchColumn();
$preview=Board::getTerm38ImportPreview();
ok($pdo->query('SELECT total_changes()')->fetchColumn()===$writes,'import preview performs zero writes');
ok([$preview['directors'],$preview['advisors'],$preview['auditors'],$preview['groups']] === [15,3,3,3],'legacy preview counts and contract');
ok(Board::getPublicActiveTerm()===null,'no published term returns fallback');
// Import calls below are exclusively into this disposable in-memory PDO.
\App\Services\AuditService::setTestFaultInjector(function($module,$action){if($action==='import_draft')throw new RuntimeException('Injected import failure');});
try{Board::importTerm38();throw new LogicException('expected rollback');}catch(RuntimeException $e){ok($e->getMessage()==='Injected import failure','import fault injected');}
\App\Services\AuditService::setTestFaultInjector(null);
foreach(['board_terms','board_term_members','board_term_groups','board_term_group_members'] as $table)ok((int)Database::value("SELECT COUNT(*) FROM $table")===0,"import rollback leaves $table empty");
$r=Board::importTerm38();$id=(int)$r['term_id'];
ok(Content::catalog()===$before,'draft import leaves public catalog unchanged');
ok(Database::value('SELECT status FROM board_terms WHERE id=?',[$id])==='draft','term 38 imported as draft');
Database::execute("UPDATE board_term_members SET name='Admin edited',image_url='/uploads/custom.webp' WHERE id=(SELECT MIN(id) FROM board_term_members)");
$snapshot=snapshotBoard($id);Board::importTerm38();
ok(snapshotBoard($id)===$snapshot,'re-import preserves every row and custom edit/photo');
rejected(fn()=>Board::reconcileTerm38(true),'destructive reconciliation rejected');
$members=Board::getSelectionCandidates($id)['members'];$ids=array_column(array_slice($members,0,2),'id');
$copy=Board::getSelectiveTransferPreview($id,39,$ids);$source=snapshotBoard($id);
rejected(fn()=>Board::selectiveTransfer($id,39,$ids,['version'=>'stale']),'stale copy rejected');
rejected(fn()=>Board::getSelectiveTransferPreview($id,39,[$ids[0],$ids[0]]),'duplicate selection rejected');
rejected(fn()=>Board::getSelectiveTransferPreview($id,38,$ids),'same source and destination rejected');
rejected(fn()=>Board::getSelectiveTransferPreview($id,40,$ids),'copy to unapproved destination term number rejected');
rejected(fn()=>Board::getSelectiveTransferPreview(999,39,$ids),'invalid source rejected');
rejected(fn()=>Board::getSelectiveTransferPreview($id,39,[999]),'foreign source member rejected');
\App\Services\AuditService::setTestFaultInjector(function($module,$action){if($action==='selective_copy')throw new RuntimeException('Injected copy failure');});
try{Board::selectiveTransfer($id,39,$ids,['version'=>$copy['version']]);throw new LogicException('expected rollback');}catch(RuntimeException $e){ok($e->getMessage()==='Injected copy failure','copy fault injected');}
\App\Services\AuditService::setTestFaultInjector(null);
ok(!Database::first('SELECT id FROM board_terms WHERE term_number=39') && snapshotBoard($id)===$source,'failed copy rolls back destination and preserves source');
$copied=Board::selectiveTransfer($id,39,$ids,['version'=>$copy['version']]);$dest=(int)$copied['term_id'];
ok(snapshotBoard($id)[0]===$source[0] && snapshotBoard($id)[1]===$source[1] && snapshotBoard($id)[2]===$source[2],'selective copy preserves source rows');
$d=Board::getTerm($dest);ok($d['term']['status']==='draft' && !$d['term']['is_current'],'term 39 stays separate draft');
ok($d['directors'][0]['image_url']===$members[0]['image_url'],'member photo preserved');
ok(count($d['groups'])===count($copy['groups']),'selected group relationships copied');
rejected(fn()=>Board::selectiveTransfer($id,39,$ids,['version'=>$copy['version']]),'repeat copy rejected');
rejected(fn()=>Board::publishTerm($dest),'unapproved draft cannot publish');
loginBoard(2,'content_editor');rejected(fn()=>Board::publishTerm($dest),'editor cannot publish');
loginBoard(1,'super_admin',false);rejected(fn()=>Board::approveTerm($dest),'pending MFA rejected');
loginBoard(4,'member');rejected(fn()=>Board::importTerm38(),'member cannot import');
loginBoard(1,'super_admin');Board::approveTerm($dest);
Database::execute("UPDATE board_term_members SET position='Changed after approval' WHERE term_id=?",[$dest]);
rejected(fn()=>Board::publishTerm($dest),'changes invalidate approval');
Board::approveTerm($dest);Board::publishTerm($dest);
ok((int)Database::value("SELECT COUNT(*) FROM board_terms WHERE status='published' AND is_current=1")===1,'exactly one current published term');
Database::execute("INSERT INTO board_terms(term_number,term_name,status,is_current,publish_at) VALUES(40,'Due but not executed','scheduled',0,'2000-01-01')");
$writes=$pdo->query('SELECT total_changes()')->fetchColumn();Board::getPublicActiveTerm();Content::catalog();
ok($writes===$pdo->query('SELECT total_changes()')->fetchColumn(),'public GET paths perform zero database writes');
Board::importTerm38();ok(Database::value('SELECT is_current FROM board_terms WHERE id=?',[$dest])==1,'re-import cannot demote current term');
rejected(fn()=>Board::saveTermMembers($dest,[]),'published term cannot be edited');
Board::approveTerm($id);
\App\Services\AuditService::setTestFaultInjector(function($module,$action){if($module==='board_terms'&&$action==='publish')throw new RuntimeException('Injected audit failure');});
try{Board::publishTerm($id);throw new LogicException('expected failure');}catch(RuntimeException $e){ok($e->getMessage()==='Injected audit failure','required audit failure propagated');}
\App\Services\AuditService::setTestFaultInjector(null);
ok(Database::value('SELECT status FROM board_terms WHERE id=?',[$id])==='draft' && Database::value('SELECT is_current FROM board_terms WHERE id=?',[$dest])==1,'failed publication rolls back promotion and demotion');
Board::publishTerm($id);
ok((int)Database::value("SELECT COUNT(*) FROM board_terms WHERE status='published' AND is_current=1")===1,'replacement publication keeps one current term');
ok(Database::value('SELECT status FROM board_terms WHERE id=?',[$dest])==='archived','previous current archived only by explicit publication');
$public=Content::catalog();ok($public['boardMembers']===Board::getPublicActiveTerm()['boardMembers'],'public API uses published board term');
Database::execute("INSERT INTO public_content_revisions(section,payload,state,effective_on) VALUES ('boards','[]','published','2000-01-01')");
ok(Content::catalog()['boardMembers']===$public['boardMembers'],'BoardTerm takes precedence over published board revisions');

class BoardResponse extends \App\Core\Response {public int $status=200;public array $payload=[];public function json(array $data,int $status=200):void{$this->status=$status;$this->payload=$data;}}
$_POST=[];$_GET=[];$_SERVER['REQUEST_METHOD']='POST';$_SERVER['REQUEST_URI']='/api/admin/board-terms/invalid/publish';
$response=new BoardResponse();$controller=new \App\Controllers\Admin\BoardTermWorkflowController(new \App\Core\Request(),$response);
$controller->apiTermPublish('1junk');ok($response->status===422 && $response->payload['code']==='VALIDATION_ERROR','API rejects malformed term IDs with 422 contract');
loginBoard(2,'content_editor');$controller->apiTermPublish((string)$dest);ok($response->status===403 && $response->payload['code']==='FORBIDDEN','API unauthorized publication returns 403');
loginBoard(1,'super_admin');
$pdo->exec("CREATE TABLE document_categories(id INTEGER PRIMARY KEY,name TEXT,slug TEXT);CREATE TABLE documents(id INTEGER PRIMARY KEY,title TEXT,slug TEXT,category_id INTEGER,file_path TEXT,file_size INTEGER,file_type TEXT,download_count INTEGER,created_at TEXT,status TEXT,deleted_at TEXT,sort_order INTEGER);
INSERT INTO document_categories VALUES(1,'Fixture category','fixture');
INSERT INTO documents VALUES(1,'Fixture document','fixture',1,'documents/fixture.pdf',123,'application/pdf',0,CURRENT_TIMESTAMP,'active',NULL,0);
INSERT INTO documents VALUES(2,'Hidden draft','hidden',1,'documents/hidden.pdf',123,'application/pdf',0,CURRENT_TIMESTAMP,'inactive',NULL,0);");
$_SERVER['REQUEST_METHOD']='GET';$response=new BoardResponse();$controller=new \App\Controllers\Api\SpaApiController(new \App\Core\Request(),$response);$controller->documents();
ok($response->status===200 && $response->payload['success'] && count($response->payload['data'])===1,'Document API keeps active-only list contract');
foreach(['id','title','category','category_slug','file_path','file_size','download_count','file_url'] as $key)ok(array_key_exists($key,$response->payload['data'][0]),'Document API field '.$key);
$controller->downloadDocument('2');ok($response->status===404,'Document API refuses inactive download');
$router=new \App\Core\Router();require __DIR__.'/../config/routes.php';
$routes=(new ReflectionProperty($router,'routes'))->getValue($router);
foreach($routes as $route){
 if(!str_starts_with($route['path'],'/api/admin/board-terms'))continue;
 $middleware=$route['middlewares'];
 ok(in_array(\App\Middlewares\AuthMiddleware::class,$middleware,true),'board route authenticated '.$route['path']);
 if(in_array($route['method'],['POST','PUT','DELETE'],true))ok(in_array(\App\Middlewares\CsrfMiddleware::class,$middleware,true),'board mutation CSRF '.$route['path']);
}


$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';$_POST=[];
$csrfResponse=new BoardResponse();$csrfRequest=new \App\Core\Request();
ok(!(new \App\Middlewares\CsrfMiddleware())->handle($csrfRequest,$csrfResponse) && $csrfResponse->status===419,'state-changing request without CSRF is rejected');
loginBoard(1,'super_admin');
\App\Services\AuditService::setTestFaultInjector(function($module,$action){
    if($module==='board_terms' && $action==='create') throw new RuntimeException('Injected draft create audit failure');
});
try { Board::createTerm(['term_number'=>41]); throw new LogicException('expected audit failure'); }
catch (RuntimeException $e) { ok($e->getMessage()==='Injected draft create audit failure','draft creation aborts on required audit failure'); }
\App\Services\AuditService::setTestFaultInjector(null);
ok(!Database::first('SELECT id FROM board_terms WHERE term_number=41'),'required audit failure leaves no draft record');
$actorTerm=Board::createTerm(['term_number'=>41],999);
ok((int)Database::value('SELECT created_by FROM board_terms WHERE id=?',[$actorTerm])===1,'caller-supplied actor ID ignored in favor of authenticated session');

Auth::logout();(new ReflectionProperty(Database::class,'instance'))->setValue(null,null);
