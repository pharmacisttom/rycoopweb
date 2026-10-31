<?php
declare(strict_types=1);
putenv('STAFF_MFA_REQUIRED=false'); putenv('STAFF_GOOGLE_LOGIN_REQUIRED=false');
require __DIR__.'/WorkflowFixture.php';
use App\Core\{Auth,Csrf,Database,Request,Router};
use App\Services\{StaffInvitationService as Invitations,PrOfficerPolicy,AdminControlCenterService,TwoFactorService};
use App\Controllers\Admin\MfaController;

$pdo->sqliteCreateFunction('CONCAT',fn(...$parts)=>implode('',$parts));
$pdo->exec("ALTER TABLE users ADD COLUMN username TEXT; ALTER TABLE users ADD COLUMN uuid TEXT;
ALTER TABLE users ADD COLUMN password TEXT; ALTER TABLE users ADD COLUMN department TEXT; ALTER TABLE users ADD COLUMN created_at TEXT;
ALTER TABLE roles ADD COLUMN description TEXT;
CREATE TABLE user_google_identities(id INTEGER PRIMARY KEY,user_id INTEGER UNIQUE,google_sub TEXT UNIQUE,google_email TEXT,email_verified INTEGER,linked_at TEXT,last_login_at TEXT,created_at TEXT,updated_at TEXT);
CREATE TABLE staff_invitations(id INTEGER PRIMARY KEY,email TEXT,name TEXT,department TEXT,intended_role_id INTEGER,invited_by INTEGER,token_hash TEXT UNIQUE,expires_at TEXT,status TEXT,created_at TEXT,updated_at TEXT,accepted_at TEXT,revoked_at TEXT);
CREATE TABLE important_announcements(id INTEGER PRIMARY KEY,title TEXT,slug TEXT UNIQUE,content TEXT,summary TEXT,attachment_path TEXT,attachment_name TEXT,publication_date TEXT,effective_start_date TEXT,expiry_date TEXT,priority TEXT,resolution_no TEXT,related_link TEXT,status TEXT,is_pinned INTEGER,created_by INTEGER,updated_by INTEGER,created_at TEXT,updated_at TEXT,deleted_at TEXT);
INSERT INTO roles(id,name,slug) VALUES(5,'PR Officer','pr_officer');
UPDATE users SET username='fixture_'||id,uuid='fixture_'||id;");
class PrRedirectResponse extends WorkflowResponse { public string $redirected=''; public function redirect(string $url,int $status=302):void {$this->redirected=$url;$this->status=$status;} }
$router=new Router();require __DIR__.'/../config/routes.php';
function prDispatch(string $method,string $uri,array $body=[],bool $csrf=true):WorkflowResponse {
    global $router;
    $_SERVER=['REQUEST_METHOD'=>$method,'REQUEST_URI'=>$uri,'SCRIPT_NAME'=>'/index.php','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'];
    if($csrf)$_SERVER['HTTP_X_CSRF_TOKEN']=Csrf::token();$_POST=$body;$_GET=$_FILES=[];
    $response=new WorkflowResponse();$router->dispatch(new Request(),$response);return $response;
}
try {
    workflowLogin(1);
    $r=prDispatch('POST','/api/admin/staff/invite',['name'=>'PR Fixture','email'=>'pr@example.invalid','department'=>'PR','role_id'=>5],false);
    workflowCheck($r->status===403||$r->status===419,'invitation requires CSRF');
    $r=prDispatch('POST','/api/admin/staff/invite',['name'=>'PR Fixture','email'=>'pr@example.invalid','department'=>'PR','role_id'=>5]);
    workflowCheck($r->status===201||$r->status===200,'Super Admin creates invitation through real route');
    $inv=$r->data['invitation'];
    workflowCheck(Invitations::findValidByToken($inv['token'])['role_slug']==='pr_officer','hashed invitation resolves intended PR role');
    workflowCheck(Database::value('SELECT token_hash FROM staff_invitations WHERE id=?',[$inv['id']])!==$inv['token'],'raw token is not stored');
    workflowFail(fn()=>Invitations::createInvitation('Duplicate','PR@example.invalid',5,'PR',1),422,'duplicate pending email rejected');
    workflowFail(fn()=>Invitations::acceptInvitation($inv['id'],'google-mismatch','other@example.invalid','Other'),422,'Google email mismatch rejected');
    Database::execute("UPDATE staff_invitations SET expires_at='2000-01-01' WHERE id=?",[$inv['id']]);
    workflowCheck(Invitations::findValidByToken($inv['token'])===null,'expired token cannot resolve');
    workflowFail(fn()=>Invitations::acceptInvitation($inv['id'],'sub-pr','pr@example.invalid','PR'),422,'expired invitation cannot be accepted');
    $renewed=Invitations::resendInvitation($inv['id'],1);
    workflowCheck(Invitations::findValidByToken($inv['token'])===null,'renewal invalidates old token');
    $accepted=Invitations::acceptInvitation($inv['id'],'sub-pr','PR@example.invalid','PR');$id=$accepted['user_id'];
    workflowCheck(Database::value('SELECT role_id FROM user_roles WHERE user_id=?',[$id])==5,'acceptance assigns PR role');
    workflowCheck(Database::value('SELECT email_verified FROM user_google_identities WHERE user_id=?',[$id])==1,'acceptance binds Google identity');
    workflowFail(fn()=>Invitations::resendInvitation($inv['id'],1),422,'accepted invitation cannot reopen');
    workflowFail(fn()=>Invitations::createInvitation('Existing','member@example.invalid',5,'PR',1),422,'member account cannot be converted through invitation');
    workflowFail(fn()=>Invitations::createInvitation('Existing','pr@example.invalid',5,'PR',1),422,'existing staff duplicate rejected');
    workflowFail(fn()=>Invitations::acceptInvitation($inv['id'],'sub-pr','pr@example.invalid','PR'),422,'accepted token cannot replay');
    $keys=['news.view','news.create','news.update','news.publish','content.create','content.publish','faqs.view','users.create','users.update','users.disable','roles.manage','permissions.manage','finance.view','finance.export','backup.restore.request','backup.restore.approve','security.sessions.revoke','members.view'];
    foreach($keys as $index=>$key){[$module,$action]=explode('.',$key,2);Database::execute('INSERT INTO permissions(id,module,action) VALUES(?,?,?)',[$index+1,$module,$action]);Database::execute('INSERT INTO role_permissions(role_id,permission_id) VALUES(5,?)',[$index+1]);}
    $identity=Database::first('SELECT * FROM users WHERE id=?',[$id]);$identity['user_id']=$id;$identity['role_slug']='pr_officer';$identity['google_email']='pr@example.invalid';
    Auth::logout();$redirectResponse=new PrRedirectResponse();$google=new \App\Controllers\Admin\GoogleAuthController(workflowRequest(),$redirectResponse);
    (new ReflectionMethod($google,'handleUserSessionAndMfa'))->invoke($google,$identity,'127.0.0.1','isolated-test','sub-pr');
    workflowCheck(!Auth::check()&&Auth::checkPendingMfaEnrollment()&&str_contains($redirectResponse->redirected,'mfa/enroll'),'Google session requires PR enrollment despite global MFA disabled');
    workflowLogin($id,false,true);
    workflowCheck(prDispatch('GET','/api/admin/news')->data['code']==='MFA_ENROLLMENT_REQUIRED','PR CMS denied until enrollment');
    $response=new WorkflowResponse();(new MfaController(workflowRequest(),$response))->status();workflowCheck($response->data['mandatory']===true,'PR MFA status is mandatory');
    $response=new WorkflowResponse();(new MfaController(workflowRequest(),$response))->enroll();$secret=$response->data['secret'];
    workflowCheck(str_contains($response->data['qr_code']??$response->data['qr_svg']??json_encode($response->data),'svg'),'existing QR enrollment generated');
    $code=(new ReflectionMethod(TwoFactorService::class,'calculateCode'))->invoke(null,$secret,(int)floor(time()/30));
    $response=new WorkflowResponse();(new MfaController(workflowRequest(['code'=>$code]),$response))->confirm();
    workflowCheck($response->status===200&&Auth::check()&&count($response->data['recovery_codes'])===8,'PR TOTP confirmation and recovery complete authenticated session');
    workflowCheck(Auth::hasPermission('news','create'),'approved PR content grant retained');
    foreach(array_slice($keys,7) as $key){[$module,$action]=explode('.',$key,2);workflowCheck(!Auth::hasPermission($module,$action)&&!in_array($key,AdminControlCenterService::effectivePermissions(),true),'PR ceiling denies '.$key);}
    workflowCheck((int)Database::value('SELECT COUNT(*) FROM role_permissions WHERE role_id=5')===count($keys),'runtime ceiling preserves stored permission mappings');
    workflowCheck(prDispatch('GET','/api/admin/staff/')->status===403,'PR cannot list staff');
    foreach(['/api/admin/dashboard/summary','/api/admin/dashboard/trends','/staff/dashboard','/staff/reports','/staff/billing','/api/admin/led/dashboard','/admin/led'] as $uri)workflowCheck(prDispatch('GET',$uri)->status===403,'PR cannot enter legacy finance/member operations: '.$uri);

    workflowCheck(prDispatch('POST','/api/admin/staff/1/role',['role_id'=>5])->status===403,'PR cannot change roles');
    workflowCheck(prDispatch('POST','/api/admin/staff/invite',['name'=>'No','email'=>'no@example.invalid','role_id'=>1])->status===403,'PR cannot invite privileged users');
    $r=prDispatch('POST','/api/admin/announcements',['title'=>'PR announcement','content'=>'<p>Safe content</p><script>alert(1)</script>','publication_date'=>date('Y-m-d'),'status'=>'draft']);
    workflowCheck($r->status===201,'PR creates draft via existing AnnouncementController');
    workflowCheck(!str_contains((string)Database::value('SELECT content FROM important_announcements'),'script'),'announcement HTML sanitized');
    workflowCheck(prDispatch('GET','/api/admin/announcements')->status===200,'verified PR reads announcements');
    $announcementId=(int)Database::value('SELECT id FROM important_announcements');
    workflowCheck(prDispatch('POST',"/api/admin/announcements/$announcementId/update",['version'=>\App\Services\AccessService::version(Database::first('SELECT * FROM important_announcements WHERE id=?',[$announcementId])),'title'=>'Updated PR','content'=>'<p>Updated</p>','publication_date'=>date('Y-m-d'),'status'=>'published'])->status===200,'PR edits and publishes through approved content grants');
    workflowCheck(prDispatch('POST',"/api/admin/announcements/$announcementId/update",['title'=>'CSRF denied'],false)->status===419,'announcement update requires CSRF');
    Database::execute("DELETE FROM role_permissions WHERE role_id=5 AND permission_id=(SELECT id FROM permissions WHERE module='content' AND action='publish')");
    workflowCheck(prDispatch('POST','/api/admin/announcements',['title'=>'Forbidden publish','content'=>'<p>Text</p>','publication_date'=>date('Y-m-d'),'status'=>'published'])->status===403,'content create cannot substitute for publish permission');

    workflowCheck(prDispatch('POST','/api/admin/announcements',['title'=>'Unsafe','content'=>'<p>text</p>','publication_date'=>date('Y-m-d'),'related_link'=>'javascript:alert(1)','status'=>'published'])->status===422,'unsafe announcement links rejected');
    workflowCheck(Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='staff_invitation' AND action='create'")>0&&Database::value("SELECT COUNT(*) FROM audit_logs WHERE module='staff_invitation' AND action='accept'")>0,'invitation creation and acceptance audited');
    $audit=json_encode(Database::query('SELECT * FROM audit_logs'));workflowCheck(!str_contains($audit,$secret)&&!str_contains($audit,$inv['token']),'audit excludes enrollment secret and invitation token');
    workflowLogin(1);$r=prDispatch('GET','/api/admin/staff/');workflowCheck($r->status===200&&count($r->data['staff'])===4,'Super Admin directory access retained');
    workflowCheck(!str_contains(json_encode($r->data),$secret)&&!str_contains(json_encode($r->data),$renewed['token']),'staff response exposes status only');
    workflowLogin(4);workflowCheck(prDispatch('GET','/api/admin/announcements')->status===403,'non-content member denied announcement API');
    workflowCheck(PrOfficerPolicy::requiresMfa('pr_officer'),'PR mandatory MFA policy');
    workflowLogin(1);$rollback=Invitations::createInvitation('Rollback fixture','rollback@example.invalid',5,'PR',1);
    $pdo->exec('DROP TABLE audit_logs');
    try {Invitations::revokeInvitation($rollback['id'],1);throw new RuntimeException('expected audit failure');}catch(PDOException $exception){}
    workflowCheck(Database::value('SELECT status FROM staff_invitations WHERE id=?',[$rollback['id']])==='pending','audit failure rolls back invitation state change');

    $pdo->exec('CREATE TABLE members(user_id INTEGER,member_no TEXT,id_card TEXT)');
    Database::execute('UPDATE users SET password=? WHERE id=?',[password_hash('synthetic-password-only',PASSWORD_ARGON2ID),$id]);
    Auth::logout();$r=new WorkflowResponse();(new \App\Controllers\Admin\AuthController(workflowRequest(['username'=>'pr@example.invalid','password'=>'synthetic-password-only']),$r))->login();
    workflowCheck($r->status===403&&!Auth::check()&&str_contains($r->data['message'],'Google'),'PR password login denied even with global Google/MFA switches disabled');

} finally {workflowFinish();}
