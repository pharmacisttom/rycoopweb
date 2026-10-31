<?php
declare(strict_types=1);
// Refuse before autoload/config/PDO. This suite is only for a fresh disposable CI MySQL service.
if(getenv('APP_ENV')!=='testing'||getenv('GITHUB_ACTIONS')!=='true'||getenv('CRUD_DISPOSABLE_MYSQL')!=='1'||getenv('DB_DATABASE')!=='rayongcoop_ci'||!in_array(getenv('DB_HOST'),['127.0.0.1','localhost'],true)||getenv('DB_USERNAME')!=='rycoop_ci_app'){
    fwrite(STDERR,"REFUSED: requires fresh GitHub Actions disposable rayongcoop_ci MySQL 8, never TEST/Production.\n");exit(2);
}
$sessions=dirname(__DIR__).'/storage/tmp/crud-mysql-sessions';if(!is_dir($sessions))mkdir($sessions,0700,true);putenv('SESSION_SAVE_PATH='.$sessions);putenv('SESSION_SECURE=false');
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\{Auth,Csrf,Database,Request,Response,Router,Session};
use App\Services\{AccessService,AdminCrudService,StaffInvitationService,TwoFactorService};
final class CrudResponse extends Response {public array $body=[];public int $status=200;public function json(array $data,int $status=200):void{$this->body=$data;$this->status=$status;}public function redirect(string $url,int $status=302):void{$this->status=$status;}}
function crudAssert(bool $condition,string $label):void {if(!$condition)throw new RuntimeException($label);echo "PASS: $label\n";}
$pdo=Database::pdo();$actualDatabase=Database::value('SELECT DATABASE()');$server=(string)Database::value('SELECT VERSION()');
if($actualDatabase!=='rayongcoop_ci'||!preg_match('/^8\./',$server)||stripos($server,'mariadb')!==false)throw new RuntimeException('Wrong disposable database or MySQL major');
$router=new Router();require dirname(__DIR__).'/config/routes.php';
function crudRequest(string $method,string $url,array $input=[],bool $csrf=true):CrudResponse {
    global $router;$_SERVER=['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$url,'SCRIPT_NAME'=>'/index.php','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest','REMOTE_ADDR'=>'127.0.0.1'];$_GET=$_FILES=[];$_POST=$input;
    if($csrf)$_SERVER['HTTP_X_CSRF_TOKEN']=Csrf::token();$response=new CrudResponse();$router->dispatch(new Request(),$response);return $response;
}
$run=bin2hex(random_bytes(5));$actors=[];$sequence=0;
function crudActor(string $role='super_admin',?string $scope=null):array {
    global $run,$actors,$sequence;
    Auth::logout();$index=++$sequence;$email="crud_{$run}_{$index}@example.invalid";
    $id=(int)Database::insert("INSERT INTO users(uuid,name,username,email,password,status) VALUES(?,?,?,?,?,'active')",[bin2hex(random_bytes(16)),"CRUD fixture $index","crud_{$run}_{$index}",$email,password_hash(bin2hex(random_bytes(12)),PASSWORD_ARGON2ID)]);
    Database::execute('INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE slug=?',[$id,$role]);$actors[]=$id;
    $row=Database::first('SELECT u.*,r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=?',[$id]);if(!$row)throw new RuntimeException('Required role missing');
    Auth::login($row,false,true);$enroll=crudRequest('POST','/api/auth/mfa/enroll');if($enroll->status!==200)throw new RuntimeException('Fixture MFA enrollment failed');
    $secret=$enroll->body['secret'];$calculate=new ReflectionMethod(TwoFactorService::class,'calculateCode');$code=$calculate->invoke(null,$secret,(int)floor(time()/30));
    if(crudRequest('POST','/api/auth/mfa/confirm',['code'=>$code])->status!==200||!Auth::check())throw new RuntimeException('Real fixture MFA confirmation failed');
    if($scope){$step=$calculate->invoke(null,$secret,(int)floor(time()/30)+1);$response=crudRequest('POST','/api/auth/mfa/step-up',['code'=>$step,'scope'=>$scope]);if($response->status!==200)throw new RuntimeException('Real fixture Step-Up failed');}
    return ['id'=>$id,'secret'=>$secret]; // In-memory synthetic verifier only; never logged.
}
function crudCritical(string $url,string $scope,array $input):CrudResponse {crudActor('super_admin',$scope);return crudRequest('POST',$url,$input);}

// Separate processes, separately verified MFA sessions and an explicit start barrier exercise real row locking.
if(($argv[1]??'')==='--race'){
    $input=json_decode(trim((string)fgets(STDIN)),true,512,JSON_THROW_ON_ERROR);ob_start();
    $row=Database::first('SELECT u.*,r.slug AS role_slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=?',[$input['actor']]);Auth::login($row,false,false);
    $verify=crudRequest('POST','/api/auth/mfa/verify',['code'=>$input['code']]);if($verify->status!==200)throw new RuntimeException('Race actor MFA failed');ob_end_clean();echo "READY\n";fflush(STDOUT);
    if(trim((string)fgets(STDIN))!=='GO')exit(3);
    $r=crudRequest('POST','/api/admin/faqs/'.$input['id'].'/update',$input['payload']);echo json_encode(['status'=>$r->status])."\n";exit(0);
}
ob_start();$results=[];$trigger=null;
try {
    crudAssert(true,'actual MySQL 8 disposable schema, not SQLite or live TEST');
    foreach(['users'=>['session_version','department'],'roles'=>['slug'],'staff_invitations'=>['token_hash','status'],'user_google_identities'=>['google_sub'],'faqs'=>['status','updated_at'],'documents'=>['file_path','category_id'],'important_announcements'=>['publication_date','deleted_at'],'public_content_revisions'=>['base_version'],'site_settings'=>['key','value']] as $table=>$required){
        $columns=array_column(Database::query('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]),'COLUMN_NAME');crudAssert(!array_diff($required,$columns),'committed schema compatibility: '.$table);
    }
    Auth::logout();crudAssert(crudRequest('GET','/api/admin/staff/')->status===401,'guest Staff list denied');
    $actor=crudActor();crudAssert(crudRequest('POST','/api/admin/access/roles',['name'=>'No step','slug'=>'crud_no_step'])->status===403,'fresh login MFA alone is not Step-Up');
    crudAssert(crudRequest('POST','/api/admin/staff/invite',['name'=>'No CSRF'],false)->status===419,'invitation CSRF enforced');
    $email="invite_$run@example.invalid";$role=(int)Database::value("SELECT id FROM roles WHERE slug='pr_officer'");
    $r=crudRequest('POST','/api/admin/staff/invite',['name'=>'CRUD PR','email'=>$email,'department'=>'PR','role_id'=>$role]);crudAssert($r->status===200&&isset($r->body['invitation']),'create invitation through real controller');$inv=$r->body['invitation'];
    crudAssert(crudRequest('POST','/api/admin/staff/invite',['name'=>'Duplicate','email'=>$email,'role_id'=>$role])->status===422,'duplicate invitation rejected on MySQL');
    try{StaffInvitationService::acceptInvitation($inv['id'],'crud-sub-'.$run,'wrong@example.invalid','Wrong');throw new RuntimeException('mismatch accepted');}catch(InvalidArgumentException $e){crudAssert(true,'Google email mismatch denied');}
    Database::execute('UPDATE staff_invitations SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id=?',[$inv['id']]);crudAssert(StaffInvitationService::findValidByToken($inv['token'])===null,'expired token rejected');
    $renew=crudRequest('POST',"/api/admin/staff/invitations/{$inv['id']}/resend");crudAssert($renew->status===200,'renew invitation');
    $accepted=StaffInvitationService::acceptInvitation($inv['id'],'crud-sub-'.$run,$email,'CRUD PR');$staffId=$accepted['user_id'];$actors[]=$staffId;
    $r=crudRequest('GET','/api/admin/staff/');crudAssert($r->status===200&&in_array($staffId,array_column($r->body['staff'],'id'),true),'accepted account appears in real Staff directory');
    $state=AccessService::staffState($staffId);$r=crudCritical("/api/admin/staff/$staffId/suspend",'staff.suspend',['version'=>$state['version']]);crudAssert($r->status===200&&Database::value('SELECT status FROM users WHERE id=?',[$staffId])==='suspended','suspend staff with fresh Step-Up');
    $r=crudCritical("/api/admin/staff/$staffId/activate",'staff.activate',['version'=>$state['version']]);crudAssert($r->status===409,'stale staff update conflicts');
    $r=crudCritical("/api/admin/staff/$staffId/activate",'staff.activate',['version'=>AccessService::staffState($staffId)['version']]);crudAssert($r->status===200,'activate staff');
    $before=Database::value('SELECT session_version FROM users WHERE id=?',[$staffId]);$r=crudCritical("/api/admin/staff/$staffId/role",'staff.role.update',['version'=>AccessService::staffState($staffId)['version'],'role_id'=>$role,'department'=>'Updated PR']);crudAssert($r->status===200&&Database::value('SELECT session_version FROM users WHERE id=?',[$staffId])>$before,'role/department update revokes sessions atomically');
    $r=crudCritical('/api/admin/access/roles','roles.create',['name'=>'CRUD '.$run,'slug'=>'crud_'.$run,'permissions'=>['news.view']]);crudAssert($r->status===200,'create role on MySQL');$custom=$r->body['role'];
    $r=crudCritical('/api/admin/access/roles','roles.create',['name'=>'Duplicate','slug'=>'crud_'.$run,'permissions'=>[]]);crudAssert($r->status===409,'unique role conflict is 409');
    $unsupported=(int)Database::value("SELECT id FROM permissions WHERE module='documents' AND action='view'");Database::execute('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)',[$custom['id'],$unsupported]);$custom=AccessService::role($custom['id']);
    $r=crudCritical('/api/admin/access/roles/'.$custom['id'].'/update','roles.update',[...$custom,'name'=>'Updated role','permissions'=>['news.view']]);crudAssert($r->status===200&&in_array('documents.view',$r->body['role']['permissions'],true),'unsupported role mappings survive editing');$custom=$r->body['role'];
    $r=crudCritical('/api/admin/access/roles/'.$custom['id'].'/delete','roles.delete',['version'=>$custom['version']]);crudAssert($r->status===200,'delete unused custom role');
    $legacyRole=(int)Database::value("SELECT id FROM roles WHERE slug='auditor'");
    $userData=['name'=>'CRUD account','username'=>'new_'.$run,'email'=>'new_'.$run.'@example.invalid','status'=>'active','role_id'=>$legacyRole,'password'=>bin2hex(random_bytes(16))];
    $r=crudCritical('/api/admin/access/users','users.create',$userData);crudAssert($r->status===200,'create account using canonical AccessService');$account=$r->body['user'];$actors[]=$account['id'];
    crudAssert(crudCritical('/api/admin/access/users','users.create',$userData)->status===409,'duplicate username/email conflicts on MySQL');
    $r=crudCritical('/api/admin/access/users/'.$account['id'].'/update','users.update',[...$account,'role_id'=>$legacyRole,'department'=>'PR office','email'=>'changed_'.$run.'@example.invalid']);crudAssert($r->status===200&&$r->body['user']['department']==='PR office','update account and department');$account=$r->body['user'];
    $r=crudCritical('/api/admin/access/users/'.$account['id'].'/delete','users.delete',['version'=>$account['version']]);crudAssert($r->status===200,'disable account');
    crudActor();$r=crudRequest('POST','/api/admin/faqs',['question'=>'CRUD FAQ '.$run,'answer'=>'<p>Safe answer</p>']);crudAssert($r->status===201,'create FAQ');$faq=$r->body['data'];
    $r=crudRequest('POST','/api/admin/faqs/'.$faq['id'].'/update',[...$faq,'answer'=>'<p>Updated answer</p>']);crudAssert($r->status===200,'update FAQ');$current=$r->body['data'];
    crudAssert(crudRequest('POST','/api/admin/faqs/'.$faq['id'].'/update',$faq)->status===409,'stale FAQ conflict');
    $raceActors=[crudActor(),crudActor()];session_write_close();$workers=[];
    foreach($raceActors as $index=>$raceActor){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--race'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,dirname(__DIR__));if(!is_resource($process))throw new RuntimeException('Cannot start race worker');$code=(new ReflectionMethod(TwoFactorService::class,'calculateCode'))->invoke(null,$raceActor['secret'],(int)floor(time()/30)+1);fwrite($pipes[0],json_encode(['actor'=>$raceActor['id'],'code'=>$code,'id'=>$faq['id'],'payload'=>[...$current,'question'=>'Race '.$index]])."\n");$workers[]=[$process,$pipes];}
    foreach($workers as [$process,$pipes]){stream_set_timeout($pipes[1],30);if(trim((string)fgets($pipes[1]))!=='READY')throw new RuntimeException('Race worker not ready');}
    foreach($workers as [$process,$pipes]){fwrite($pipes[0],"GO\n");fflush($pipes[0]);}
    $statuses=[];foreach($workers as [$process,$pipes]){$statuses[]=json_decode(trim((string)fgets($pipes[1])),true)['status'];foreach($pipes as $pipe)fclose($pipe);if(proc_close($process)!==0)throw new RuntimeException('Race worker failed');}sort($statuses);crudAssert($statuses===[200,409],'real concurrent MySQL FAQ update has one winner and one conflict');
    crudActor();$faq=AdminCrudService::listing('faqs');$faq=array_values(array_filter($faq,fn($f)=>$f['id']===$current['id']))[0];crudAssert(crudRequest('POST','/api/admin/faqs/'.$faq['id'].'/delete',['version'=>$faq['version']])->status===200,'disable FAQ');
    crudAssert(in_array('inactive',array_column(crudRequest('GET','/api/admin/faqs')->body['data'],'status'),true),'admin list includes disabled FAQ for reactivation');
    $r=crudRequest('POST','/api/admin/announcements',['title'=>'CRUD announcement '.$run,'content'=>'<p>Announcement</p>','publication_date'=>date('Y-m-d'),'status'=>'draft']);crudAssert($r->status===201,'create announcement');$ann=$r->body['data'];
    $r=crudRequest('POST','/api/admin/announcements/'.$ann['id'].'/update',[...$ann,'status'=>'published']);crudAssert($r->status===200,'update/publish announcement');
    $category=(int)Database::value("SELECT id FROM document_categories WHERE status='active' LIMIT 1");$doc=['title'=>'CRUD draft '.$run,'category_id'=>$category,'year'=>2569,'status'=>'inactive'];
    $r=crudRequest('POST','/api/admin/documents',$doc);crudAssert($r->status===201,'create draft document');$doc=$r->body['data'];
    $r=crudRequest('POST','/api/admin/documents/'.$doc['id'].'/update',[...$doc,'title'=>'Updated document']);crudAssert($r->status===200,'update document metadata');$doc=$r->body['data'];
    crudAssert(crudRequest('POST','/api/admin/documents/'.$doc['id'].'/update',[...$doc,'status'=>'active'])->status===422,'active document requires real file');
    crudAssert(crudRequest('POST','/api/admin/documents',[...$doc,'category_id'=>PHP_INT_MAX])->status===422,'invalid foreign category rejected');
    crudAssert(crudRequest('POST','/api/admin/documents/'.$doc['id'].'/delete',['version'=>$doc['version']])->status===200,'soft-delete document');
    crudAssert(crudRequest('POST','/api/admin/documents/'.$doc['id'].'/update',$doc)->status===404,'deleted document cannot be edited');
    $settings=crudRequest('GET','/api/admin/settings/content');crudAssert($settings->status===200&&!isset($settings->body['settings']['APP_KEY']),'approved settings read excludes secrets');
    $r=crudCritical('/api/admin/settings/content','settings.update',['version'=>$settings->body['version'],'settings'=>['site_title'=>'CRUD isolated CI']]);crudAssert($r->status===200,'save approved settings with Step-Up');
    $r=crudCritical('/api/admin/settings/content','settings.update',['version'=>$r->body['version'],'settings'=>['APP_KEY'=>'forbidden']]);crudAssert($r->status===422,'unapproved settings rejected');
    foreach(['profile','boards','staff','deposits','loan_rates','products'] as $section){
        crudActor();$snapshot=crudRequest('GET','/api/admin/public-content/sections/'.$section);crudAssert($snapshot->status===200,'read real public CMS section '.$section);
        $draft=crudRequest('POST','/api/admin/public-content/sections/'.$section.'/drafts',['value'=>$snapshot->body['value'],'version'=>$snapshot->body['version'],'source_url'=>'https://example.invalid/qa','effective_on'=>date('Y-m-d'),'note'=>'Synthetic disposable CI compatibility check']);
        crudAssert($draft->status===200,'save draft public CMS '.$section);
        crudActor();crudAssert(crudRequest('POST','/api/admin/public-content/revisions/'.$draft->body['id'].'/publish')->status===200,'publish authorized public CMS '.$section);
    }
    crudActor('pr_officer');foreach(['/api/admin/staff/','/api/admin/documents','/api/admin/settings/content','/api/admin/members'] as $url)crudAssert(crudRequest('GET',$url)->status===403,'PR denied '.$url);
    Session::set('authenticated_at',time()-7201);crudAssert(crudRequest('GET','/api/admin/faqs')->status===401,'expired authenticated session denied');
    crudActor();$before=(int)Database::value('SELECT COUNT(*) FROM faqs');
    $ddl=new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname=rayongcoop_ci;charset=utf8mb4',getenv('CI_MIGRATOR_USER'),getenv('CI_MIGRATOR_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $trigger='crud_audit_'.$run;$ddl->exec("CREATE TRIGGER `$trigger` BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit failure'");
    try{$r=crudRequest('POST','/api/admin/faqs',['question'=>'Must rollback','answer'=>'<p>Rollback</p>']);crudAssert($r->status===500&&(int)Database::value('SELECT COUNT(*) FROM faqs')===$before,'audit failure rolls back real MySQL transaction');}finally{$ddl->exec("DROP TRIGGER `$trigger`");$trigger=null;}
    crudAssert((int)Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='faqs'")>0,'successful mutations audited');
    $fk=new CrudResponse();AdminCrudService::respond($fk,fn()=>Database::transaction(function(){Database::execute('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)',[PHP_INT_MAX,PHP_INT_MAX]);return [];}));crudAssert($fk->status===409,'native MySQL foreign-key conflict maps to conflict without leaking SQL');

    $output=ob_get_clean();echo $output;echo "PASS: disposable MySQL CRUD suite complete; fixture database destroyed with CI service\n";
}catch(\Throwable $failure){if(ob_get_level()){echo ob_get_clean();}throw $failure;}finally{
    if($trigger&&isset($ddl))$ddl->exec("DROP TRIGGER `$trigger`");
    Auth::logout();if(ob_get_level())ob_end_clean();
    // No destructive cleanup against a shared database: the guarded CI service is disposable.
}
