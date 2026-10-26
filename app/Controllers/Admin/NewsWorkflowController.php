<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Services\NewsWorkflowService;
class NewsWorkflowController extends Controller {
    public function publish(string $id):void {
        header('Cache-Control: no-store, private');
        try {NewsWorkflowService::publish((int)$id,$this->request->all());$this->json(['success'=>true]);}
        catch(\Throwable $e){$status=$e instanceof \InvalidArgumentException?422:(in_array($e->getCode(),[401,403,404,409],true)?$e->getCode():500);$this->json(['success'=>false,'message'=>$status===500?'บันทึกไม่สำเร็จ กรุณาลองใหม่':$e->getMessage()],$status);}
    }
}
