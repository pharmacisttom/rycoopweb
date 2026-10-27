<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Auth;use App\Core\Database;use App\Services\AccessService;
class AccessController extends \App\Core\Controller
{
    private function run(callable $work): void {header('Cache-Control: no-store, private');try{$this->json(['success'=>true,...$work()]);}catch(\InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}catch(\PDOException $e){\App\Core\Logger::error('Access management: '.$e->getMessage());$this->json(['success'=>false,'message'=>$e->getCode()==='23000'?'ชื่อผู้ใช้ อีเมล หรือรหัส Role ซ้ำ':'ฐานข้อมูลสิทธิ์ไม่พร้อม กรุณาตรวจ migration'],422);}catch(\Throwable $e){\App\Core\Logger::error('Access management: '.$e->getMessage());$this->json(['success'=>false,'message'=>'ดำเนินการไม่สำเร็จ'],500);}}
    public function me(): void {$this->run(function(){if(!Auth::user())throw new \InvalidArgumentException('ไม่พบบัญชีที่ใช้งานได้');return ['permissions'=>AccessService::permissions((int)Auth::id()),'user'=>['id'=>Auth::id(),'name'=>Auth::user()['name'],'role'=>Auth::user()['role_slug']]];});}
    public function index(): void {$this->run(function(){return ['roles'=>array_map(fn($r)=>AccessService::role((int)$r['id']),Database::query('SELECT id FROM roles ORDER BY id')),'modules'=>AccessService::MODULES,'actions'=>AccessService::ACTIONS,'catalog'=>AccessService::CATALOG];});}
    public function createRole(): void {$this->run(fn()=>['role'=>AccessService::saveRole($this->request->all())]);}
    public function updateRole(string $id): void {$this->run(fn()=>['role'=>AccessService::saveRole($this->request->all(),(int)$id)]);}
    public function deleteRole(string $id): void {$this->run(function()use($id){AccessService::deleteRole((int)$id,$this->request->all());return [];});}
    public function users(): void {$this->run(function(){$q=mb_substr(trim((string)$this->request->query('q','')),0,100);$page=max(1,(int)$this->request->query('page',1));$where='u.deleted_at IS NULL AND NOT EXISTS(SELECT 1 FROM members m WHERE m.user_id=u.id)';$args=[];if($q!==''){$where.=' AND (u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';$args=array_fill(0,3,'%'.$q.'%');}$total=(int)Database::value("SELECT COUNT(*) FROM users u WHERE $where",$args);$page=min($page,max(1,(int)ceil($total/25)));$offset=($page-1)*25;$users=Database::query("SELECT u.id FROM users u WHERE $where ORDER BY u.id DESC LIMIT 25 OFFSET $offset",$args);return ['users'=>array_map(fn($u)=>AccessService::account((int)$u['id']),$users),'total'=>$total,'page'=>$page];});}
    public function createUser(): void {$this->run(fn()=>['user'=>AccessService::saveAccount($this->request->all())]);}
    public function updateUser(string $id): void {$this->run(fn()=>['user'=>AccessService::saveAccount($this->request->all(),(int)$id)]);}
    public function deleteUser(string $id): void {$this->run(function()use($id){AccessService::deleteAccount((int)$id,$this->request->all());return [];});}
}
