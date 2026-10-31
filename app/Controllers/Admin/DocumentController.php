<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Services\AdminCrudService as Crud;
use App\Core\Database;
final class DocumentController extends \App\Core\Controller {
    public function index():void {
        if(!$this->request->isAjax()){$this->redirect(url('admin/workspace'));return;}
        Crud::respond($this->response,fn()=>['data'=>Crud::listing('documents'),'categories'=>Database::query("SELECT id,name FROM document_categories WHERE status='active' ORDER BY sort_order,id")]);
    }
    public function store():void {$this->save();}
    public function update(string $id):void {$this->save((int)$id);}
    private function save(?int $id=null):void {Crud::respond($this->response,fn()=>['data'=>Crud::save('documents',$this->request->all(),$id,$this->request->file('file'))],$id===null?201:200);}
    public function destroy(string $id):void {Crud::respond($this->response,function()use($id){Crud::disable('documents',(int)$id,$this->request->all());return [];});}
}
