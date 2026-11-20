<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{Auth,Controller,Logger};
use App\Services\{HomeCompositionService,DualControlSecurityService};
final class HomeCompositionController extends Controller
{
    private function respond(callable $callback):void
    {
        header('Cache-Control: no-store');
        try {$this->json(['success'=>true,'data'=>$callback()]);}
        catch(\App\Exceptions\SecurityGateException $e) {$this->json(['success'=>false,'message'=>$e->getMessage()],$e->getHttpStatus());}
        catch(\InvalidArgumentException $e) {$this->json(['success'=>false,'message'=>$e->getMessage()],422);}
        catch(\Throwable $e) {
            $code=(int)$e->getCode();$status=in_array($code,[401,403,409],true)?$code:500;
            if ($status===500) Logger::error('Home composition error '.get_class($e));
            $this->json(['success'=>false,'message'=>$status===500?'จัดการผังหน้าแรกไม่สำเร็จ':$e->getMessage()],$status);
        }
    }
    private function authorize(bool $write=false):void
    {
        if(!Auth::check()||!Auth::user())throw new \RuntimeException('UNAUTHORIZED',401);
        if((Auth::user()['role_slug']??'')!=='super_admin')throw new \RuntimeException('FORBIDDEN',403);
        if($write)DualControlSecurityService::requireFreshStepUpMfa(
            (int)(Auth::id()??0),
            $this->request->input('totp_code')!==null?(string)$this->request->input('totp_code'):null,
            'settings.update',true,$this->request->ip()
        );
    }
    public function published():void
    {
        header('Cache-Control: no-cache');
        $this->respond(static fn()=>HomeCompositionService::published());
    }
    public function show():void {$this->respond(function(){$this->authorize();return HomeCompositionService::state();});}
    public function draft():void {$this->respond(function(){$this->authorize(true);return HomeCompositionService::draft($this->request->all());});}
    public function publish():void {$this->respond(function(){$this->authorize(true);return HomeCompositionService::publish($this->request->all());});}
}
