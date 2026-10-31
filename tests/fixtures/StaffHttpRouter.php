<?php
declare(strict_types=1);
// Isolated loopback-only test server. Never installed in the public document root.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'], true)
    || strlen((string)getenv('STAFF_HTTP_FIXTURE_TOKEN')) < 32) { http_response_code(403); exit; }
require __DIR__.'/../WorkflowFixture.php';
use App\Core\{Auth, Request, Response, Router};
$sessionPath=(string)getenv('STAFF_HTTP_SESSION_PATH');
if(!$sessionPath || !is_dir($sessionPath)){http_response_code(503);exit;}
putenv('SESSION_SAVE_PATH='.$sessionPath);
$pdo->exec("ALTER TABLE users ADD COLUMN username TEXT;
ALTER TABLE users ADD COLUMN uuid TEXT;
ALTER TABLE users ADD COLUMN department TEXT;
ALTER TABLE users ADD COLUMN created_at TEXT;
ALTER TABLE roles ADD COLUMN description TEXT;
CREATE TABLE user_google_identities(user_id INTEGER,google_sub TEXT,google_email TEXT,linked_at TEXT,last_login_at TEXT);
CREATE TABLE staff_invitations(id INTEGER,intended_role_id INTEGER,invited_by INTEGER,created_at TEXT);
UPDATE users SET username='fixture_'||id,uuid='fixture-uuid-'||id;");
ob_end_clean();$response=new Response();
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/__fixture/session'){
    if($_SERVER['REQUEST_METHOD']!=='POST'||!hash_equals((string)getenv('STAFF_HTTP_FIXTURE_TOKEN'),$_SERVER['HTTP_X_FIXTURE_TOKEN']??'')){$response->json(['success'=>false],403);}
    $mode=$_POST['mode']??'verified';
    if(!in_array($mode,['verified','pending','member','guest'],true))$response->json(['success'=>false],422);
    if($mode==='guest')Auth::logout();else workflowLogin($mode==='member'?4:1,$mode!=='pending',$mode==='pending');
    $response->json(['success'=>true]);
}
// Identity transport for the real AuthProvider, backed by the real PHP test session.
// Staff requests below always dispatch the unmodified application controllers/middleware.
if($path==='/api/auth/me'){
    $authenticated=Auth::check();$user=$authenticated?Auth::user():null;
    $response->json(['success'=>true,'authenticated'=>$authenticated,'user'=>$user?['id'=>(int)$user['id'],'name'=>$user['name'],'role'=>$user['role_slug'],'permissions'=>[],'effective_permissions'=>[]]:null]);
}
$router=new Router();require __DIR__.'/../../config/routes.php';$router->dispatch(new Request(),$response);
