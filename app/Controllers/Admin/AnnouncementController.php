<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Services\AdminCrudService as Crud;
final class AnnouncementController extends \App\Core\Controller {
    public function index():void {if(!$this->request->isAjax()){$this->redirect(url('admin/workspace'));return;}Crud::respond($this->response,fn()=>['data'=>Crud::listing('announcements')]);}
    public function create():void {$this->redirect(url('admin/workspace'));}
    public function edit(string $id):void {$this->redirect(url('admin/workspace'));}
    public function store():void {$this->save();}
    public function update(string $id):void {$this->save((int)$id);}
    private function save(?int $id=null):void {Crud::respond($this->response,fn()=>['data'=>Crud::save('announcements',$this->request->all(),$id,$this->request->file('attachment'))],$id===null?201:200);}
    public function destroy(string $id):void {Crud::respond($this->response,function()use($id){Crud::disable('announcements',(int)$id,$this->request->all());return [];});}
}
