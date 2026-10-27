<?php
declare(strict_types=1);
namespace App\Middlewares;
use App\Core\Auth;use App\Core\Request;use App\Core\Response;
class PermissionMiddleware
{
    public function __construct(private string $module,private string $action){}
    public function handle(Request $request,Response $response): bool {
        if(!Auth::check() || !Auth::user()){$response->json(['success'=>false,'code'=>'UNAUTHORIZED','message'=>'กรุณาเข้าสู่ระบบใหม่'],401);return false;}
        if(!Auth::hasPermission($this->module,$this->action)){$response->json(['success'=>false,'code'=>'FORBIDDEN','message'=>'ไม่มีสิทธิ์ดำเนินการนี้'],403);return false;}return true;
    }
}
