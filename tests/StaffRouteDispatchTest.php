<?php
declare(strict_types=1);
require __DIR__.'/WorkflowFixture.php';
use App\Core\{Auth, Request, Router};
$pdo->exec("ALTER TABLE users ADD COLUMN username TEXT;
ALTER TABLE users ADD COLUMN uuid TEXT;
ALTER TABLE users ADD COLUMN department TEXT;
ALTER TABLE users ADD COLUMN created_at TEXT;
ALTER TABLE roles ADD COLUMN description TEXT;
CREATE TABLE user_google_identities(user_id INTEGER,google_sub TEXT,google_email TEXT,linked_at TEXT,last_login_at TEXT);
CREATE TABLE staff_invitations(id INTEGER,intended_role_id INTEGER,invited_by INTEGER,created_at TEXT);
UPDATE users SET username='fixture_'||id,uuid='fixture-uuid-'||id;");
$router=new Router();require __DIR__.'/../config/routes.php';
function staffDispatch(Router $router,string $uri):WorkflowResponse {
    $_SERVER=['REQUEST_METHOD'=>'GET','REQUEST_URI'=>$uri,'SCRIPT_NAME'=>'/index.php','HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'];
    $_GET=$_POST=$_FILES=[];$response=new WorkflowResponse();$router->dispatch(new Request(),$response);return $response;
}
try {
    foreach(['/api/admin/staff','/api/admin/staff/'] as $uri){
        Auth::logout();$response=staffDispatch($router,$uri);
        workflowCheck($response->status===401&&($response->data['code']??'')==='UNAUTHORIZED',"guest hits staff authentication rather than 404: $uri");
        workflowLogin(1,false,true);$response=staffDispatch($router,$uri);
        workflowCheck($response->status===403&&($response->data['code']??'')==='MFA_ENROLLMENT_REQUIRED',"pending MFA cannot enter staff endpoint: $uri");
        foreach([2,4] as $id){workflowLogin($id);$response=staffDispatch($router,$uri);workflowCheck($response->status===403&&($response->data['code']??'')==='FORBIDDEN',"non-admin role denied: $uri / fixture $id");}
        workflowLogin(1);$response=staffDispatch($router,$uri);
        workflowCheck($response->status===200&&($response->data['success']??false)&&count($response->data['staff']??[])===3,"verified admin reaches actual staff controller: $uri");
        workflowCheck(isset($response->data['staff'][0]['mfa'],$response->data['staff'][0]['google']), 'staff nested MFA/Google response contract remains intact');
        workflowCheck(!array_key_exists('two_factor_secret',$response->data['staff'][0]),'staff response does not expose encrypted MFA secret');
    }
    $routes=(new ReflectionProperty(Router::class,'routes'))->getValue($router);
    foreach($routes as $route)workflowCheck($route['path']==='/'||!str_ends_with($route['path'],'/'),'registered path agrees with Request normalization: '.$route['path']);
    foreach(['/api/admin/staff'=>'@index','/admin/led'=>'@index'] as $uri=>$method){
        $matched=array_values(array_filter($routes,fn($route)=>$route['method']==='GET'&&preg_match($route['regex'],$uri)));
        workflowCheck(count($matched)===1&&str_ends_with($matched[0]['handler'],$method),'group index has one canonical match: '.$uri);
    }
    $called=false;$root=new Router();$root->get('/',function($request,$response)use(&$called){$called=true;});staffDispatch($root,'/');workflowCheck($called,'root route remains reachable');
} finally {workflowFinish();}
