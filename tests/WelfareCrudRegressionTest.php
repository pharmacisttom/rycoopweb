<?php
declare(strict_types=1);
require_once __DIR__.'/../vendor/autoload.php';

use App\Controllers\Admin\WelfareCmsController;
use App\Core\Router;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CsrfMiddleware;
use App\Middlewares\RoleMiddleware;

function check(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL $message\n"); exit(1); }
    echo "PASS $message\n";
}
function sample(string $slug='welfare-sample'): array {
    return ['category'=>'การศึกษา','title'=>'ทุนการศึกษา','slug'=>$slug,
        'short_description'=>'คำอธิบาย','full_description'=>'รายละเอียด',
        'benefit_amount'=>'1,000 บาท','eligibility'=>'สมาชิก','required_documents'=>'สำเนาเอกสาร',
        'application_process'=>'ยื่นที่สำนักงาน','contact_info'=>'ติดต่อสหกรณ์',
        'sort_order'=>1,'status'=>'active'];
}
$validate = new ReflectionMethod(WelfareCmsController::class,'validated');
$valid = $validate->invoke(null,sample());
check($valid['slug']==='welfare-sample', 'normal data validates');
check($valid['status']==='active', 'status field preserved for explicit update');
try { $validate->invoke(null,sample('INValid slug'));check(false,'bad slug must be rejected'); }
catch (InvalidArgumentException $e) {check(true,'bad slug rejected');}
try { $bad=sample();$bad['status']='published';$validate->invoke(null,$bad);check(false,'bad status must be rejected'); }
catch (InvalidArgumentException $e) {check(true,'bad status rejected');}
$version=new ReflectionMethod(WelfareCmsController::class,'version');
$base=array_merge(sample(),['id'=>3,'created_at'=>'2026-11-10 10:00:00','updated_at'=>'2026-11-10 10:00:00','deleted_at'=>null]);
$changed=$base;$changed['benefit_amount']='2,000 บาท';
check($version->invoke(null,$base)!==$version->invoke(null,$changed),'version detects content changes within same second');

$router=new Router();
require __DIR__.'/../config/routes.php';
$routes=(new ReflectionProperty(Router::class,'routes'))->getValue($router);
foreach ([
    ['GET','/api/admin/welfare'],
    ['POST','/api/admin/welfare'],
    ['POST','/api/admin/welfare/{id}/update'],
    ['POST','/api/admin/welfare/{id}/delete'],
] as [$method,$path]) {
    $route=null;
    foreach($routes as $candidate)if($candidate['path']===$path&&$candidate['method']===$method){$route=$candidate;break;}
    check(is_array($route),"$method $path registered");
    check(in_array(AuthMiddleware::class,$route['middlewares'],true),"$path needs authentication");
    $roleMiddleware=null;
    foreach($route['middlewares'] as $m)if($m instanceof RoleMiddleware){$roleMiddleware=$m;break;}
    check($roleMiddleware!==null,"$path guarded by role");
    $roles=(new ReflectionProperty(RoleMiddleware::class,'allowedRoles'))->getValue($roleMiddleware);
    check($roles===['super_admin'],"$path only super_admin");
    if($method==='POST')check(in_array(CsrfMiddleware::class,$route['middlewares'],true),"$path requires CSRF");
}
$controller=file_get_contents(__DIR__.'/../app/Controllers/Admin/WelfareCmsController.php');
check(str_contains($controller,'AuditService::log('),'CRUD records audit');
check(str_contains($controller,'required:true'),'audit required on welfare mutations');
check(str_contains($controller,'Database::transaction('),'welfare mutations transactional');
check(!preg_match('/\b(?:UPDATE|DELETE|INSERT INTO)\s+(?:members|users|member_dividends)\b/i',$controller),'no member/dividend write SQL');
echo "Welfare CRUD regression checks completed.\n";
