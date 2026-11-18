<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\Controller;
use App\Services\{AdminCrudService,DynamicLayoutService as Layout,LayoutImageService};

final class DynamicLayoutPublicController extends Controller
{
    public function layout(): void {AdminCrudService::respond($this->response,fn()=>['data'=>Layout::published()]);}
    public function albums(): void {AdminCrudService::respond($this->response,fn()=>['data'=>Layout::publicAlbums((int)$this->request->input('page',1),$this->request->input('category'))]);}
    public function album(string $slug): void {AdminCrudService::respond($this->response,function()use($slug){$result=Layout::publicAlbums(1,null,$slug);if(!$result['items'])throw new \App\Exceptions\SecurityGateException('ไม่พบอัลบั้มเผยแพร่',404,'NOT_FOUND');return ['data'=>$result['items'][0]];});}
    public function file(string $revision,string $image,string $size): void {
        try{if(!preg_match('/^[1-9][0-9]*$/D',$revision)||filter_var($revision,FILTER_VALIDATE_INT)===false)throw new \InvalidArgumentException('รหัสเวอร์ชันไม่ถูกต้อง');Layout::imageAllowed($image,(int)$revision);$file=LayoutImageService::path($image,$size);
            if(!is_file($file))throw new \App\Exceptions\SecurityGateException('ไม่พบไฟล์',404,'NOT_FOUND');
            $this->response->header('Content-Type','image/webp')->header('Cache-Control','public, max-age=86400, immutable')->header('Content-Security-Policy',"default-src 'none'; sandbox")->setContent(file_get_contents($file))->send();
        }catch(\Throwable $e){AdminCrudService::respond($this->response,fn()=>throw $e);}
    }
}
