<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\{AdminCrudService,DynamicLayoutService as Layout,LayoutImageService,LayoutSchema};

final class DynamicLayoutController extends Controller
{
    private function number(string $id): int {if(!preg_match('/^[1-9][0-9]*$/D',$id)||filter_var($id,FILTER_VALIDATE_INT)===false)throw new \InvalidArgumentException('รหัสรายการไม่ถูกต้อง');return (int)$id;}
    private function body(): array {return $this->request->all();}
    private function reply(callable $fn): void {AdminCrudService::respond($this->response,fn()=>['data'=>$fn()]);}
    public function draft(): void {$this->reply(fn()=>Layout::draft());}
    public function saveDraft(): void {$this->reply(fn()=>Layout::saveDraft($this->body()));}
    public function create(): void {$this->reply(fn()=>Layout::section($this->body()));}
    public function subsection(string $id): void {$this->reply(fn()=>Layout::section([...$this->body(),'parent_id'=>LayoutSchema::id($id)]));}
    public function show(string $id): void {$this->reply(function()use($id){$id=LayoutSchema::id($id);foreach(Layout::draft()['state']['sections'] as $n)if($n['id']===$id)return $n;throw new \App\Exceptions\SecurityGateException('ไม่พบ section',404,'NOT_FOUND');});}
    public function update(string $id): void {$this->reply(fn()=>Layout::section($this->body(),$id));}
    public function delete(string $id): void {$this->reply(fn()=>Layout::section($this->body(),$id,'delete'));}
    public function reorder(): void {$this->reply(fn()=>Layout::reorder($this->body()));}
    public function category(): void {$this->reply(fn()=>Layout::category($this->body()));}
    public function updateCategory(string $id): void {$this->reply(fn()=>Layout::category($this->body(),$id));}
    public function deleteCategory(string $id): void {$this->reply(fn()=>Layout::category($this->body(),$id,true));}
    public function publish(): void {$this->reply(fn()=>Layout::publish($this->body()));}
    public function rollback(string $id): void {$this->reply(fn()=>Layout::publish($this->body(),$this->number($id)));}
    public function albums(): void {$this->reply(function(){ $data=Layout::albums((int)$this->request->input('page',1),$this->request->input('category'),(bool)$this->request->input('preview',false));$data['items']=array_map(fn($a)=>Layout::imageUrls($a),$data['items']);return $data;});}
    public function album(string $id): void {$this->reply(fn()=>Layout::imageUrls(Layout::album($this->number($id))));}
    public function createAlbum(): void {$this->reply(fn()=>Layout::saveAlbum($this->body()));}
    public function updateAlbum(string $id): void {$this->reply(fn()=>Layout::saveAlbum($this->body(),$this->number($id)));}
    public function deleteAlbum(string $id): void {$this->reply(fn()=>Layout::saveAlbum($this->body(),$this->number($id),true));}
    public function upload(string $id): void {$this->reply(function()use($id){$input=$this->request->file('images')??[];$files=[];
        if(isset($input['name'])&&is_array($input['name']))foreach(array_keys($input['name']) as $i){$file=[];foreach(['name','tmp_name','error','size','type'] as $key)$file[$key]=$input[$key][$i]??null;$files[]=$file;}
        return Layout::upload($this->number($id),$files,$this->body());});}
    public function image(string $id,string $image): void {$this->reply(fn()=>Layout::image($this->number($id),$image,$this->body()));}
    public function removeImage(string $id,string $image): void {$this->reply(fn()=>Layout::image($this->number($id),$image,$this->body(),true));}
    public function orderImages(string $id): void {$this->reply(fn()=>Layout::orderImages($this->number($id),$this->body()));}
    public function file(string $image,string $size): void {
        try{Layout::imageAllowed($image);$file=LayoutImageService::path($image,$size);if(!is_file($file))throw new \App\Exceptions\SecurityGateException('ไม่พบไฟล์',404,'NOT_FOUND');
            $this->response->header('Content-Type','image/webp')->header('Cache-Control','private, no-store')->header('Content-Security-Policy',"default-src 'none'; sandbox")->setContent(file_get_contents($file))->send();
        }catch(\Throwable $e){$this->reply(fn()=>throw $e);}
    }
}
