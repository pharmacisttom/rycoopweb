<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\{Auth, Controller, Logger};
use App\Services\PublicContentService as Content;

class PublicContentController extends Controller
{
    private function respond(callable $work): void
    {
        header('Cache-Control: no-store, private');
        try { $this->json(['success'=>true,...$work()]); }
        catch (\InvalidArgumentException $e) { $this->json(['success'=>false,'message'=>$e->getMessage(),'code'=>'VALIDATION_ERROR'],422); }
        catch (\Throwable $e) {
            $status=in_array($e->getCode(),[401,403,409],true)?$e->getCode():500;
            if($status===500)Logger::error('Public CMS unavailable: '.get_class($e));
            $this->json(['success'=>false,'code'=>match($status){401=>'UNAUTHORIZED',403=>'FORBIDDEN',409=>'VERSION_CONFLICT',default=>'CONTENT_UNAVAILABLE'},'message'=>match($status){401=>'กรุณาเข้าสู่ระบบใหม่',403=>'ไม่มีสิทธิ์ดำเนินการ',409=>$e->getMessage(),default=>'ระบบข้อมูลยังไม่พร้อม กรุณาตรวจการติดตั้ง migration และลองใหม่'}],$status);
        }
    }
    public function index(): void
    {
        $this->respond(function(){
            $sections=[];
            foreach(Content::SECTIONS as $key=>$section)if(Auth::hasPermission($section['module'],'view'))$sections[]=['key'=>$key,'label'=>$section['label'],'module'=>$section['module'],'canEdit'=>Auth::hasPermission($section['module'],'update'),'canPublish'=>Auth::hasPermission($section['module'],'publish')];
            return ['sections'=>$sections];
        });
    }
    public function show(string $section): void { $this->respond(fn()=>Content::section($section)); }
    public function draft(string $section): void { $this->respond(fn()=>['id'=>Content::saveDraft($section,$this->request->all())]); }
    public function publish(string $id): void { $this->respond(function()use($id){Content::publish((int)$id);return [];}); }
    public function publicCatalog(): void
    {
        header('Cache-Control: no-cache');
        try { $catalog=Content::catalog(); $this->json(['success'=>true,'data'=>$catalog]); }
        catch (\Throwable $e) { Logger::error('Public content unavailable: '.get_class($e));$this->json(['success'=>false,'code'=>'CONTENT_UNAVAILABLE','message'=>'ยังโหลดข้อมูลเผยแพร่ไม่ได้'],503); }
    }
}
