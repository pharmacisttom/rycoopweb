<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Services\MemberAdminService;
use App\Services\MemberDataException;

class MemberManagementController extends Controller
{
    private function respond(callable $work): void
    {
        header('Cache-Control: no-store, private');
        try { $this->json(['success'=>true]+$work()); }
        catch (\Throwable $e) {
            if (!$e instanceof MemberDataException) Logger::error('Member management failure: '.$e->getMessage());
            $this->json(['success'=>false,'message'=>$e instanceof MemberDataException ? $e->getMessage() : 'ดำเนินการไม่สำเร็จ กรุณาลองใหม่หรือติดต่อผู้ดูแล'],$e instanceof MemberDataException?422:500);
        }
    }
    private function id(string $id): int { if(!ctype_digit($id) || (int)$id<1) throw new MemberDataException('เลขรายการไม่ถูกต้อง'); return (int)$id; }
    public function dashboard(): void
    {
        $this->respond(function() {
            $members=Database::first("SELECT COUNT(*) AS total,SUM(status='active') AS active,SUM(status='suspended') AS suspended,SUM(status='resigned') AS resigned,SUM(user_id IS NULL) AS no_account FROM members");
            $years=Database::query("SELECT year,COUNT(*) AS records,SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.total_income')) AS DECIMAL(18,2))) AS total_income,SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.total_deductions')) AS DECIMAL(18,2))) AS total_deductions,SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.net')) AS DECIMAL(18,2))) AS net_total,MAX(imported_at) AS updated_at FROM member_dividends GROUP BY year ORDER BY year DESC");
            $runs=Database::query('SELECT i.*,u.name AS actor FROM member_import_runs i LEFT JOIN users u ON u.id=i.user_id ORDER BY i.id DESC LIMIT 20');
            $changes=Database::query('SELECT c.id,c.member_id,c.action,c.reason,c.created_at,m.member_no,CONCAT(COALESCE(m.prefix,\'\'),m.first_name,\' \',m.last_name) AS member_name,u.name AS actor FROM member_data_changes c JOIN members m ON m.id=c.member_id LEFT JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT 20');
            return compact('members','years','runs','changes');
        });
    }
    public function index(): void
    {
        $this->respond(function() {
            $page=max(1,(int)$this->request->query('page',1)); $limit=25;
            $q=trim((string)$this->request->query('q','')); if(mb_strlen($q)>100) throw new MemberDataException('คำค้นยาวเกินกำหนด');
            $where=['1=1'];$params=[];
            if($q!=='') { $where[]="(m.member_no LIKE ? OR m.id_card LIKE ? OR CONCAT(COALESCE(m.prefix,''),m.first_name,' ',m.last_name) LIKE ? OR m.department LIKE ?)"; array_push($params,...array_fill(0,4,'%'.$q.'%')); }
            $status=(string)$this->request->query('status','');
            if($status!=='') { if(!in_array($status,['active','resigned','suspended'],true)) throw new MemberDataException('สถานะไม่ถูกต้อง'); $where[]='m.status=?';$params[]=$status; }
            $year=(string)$this->request->query('year','');
            if($year!=='') { if(!preg_match('/^\d{4}$/D',$year)) throw new MemberDataException('ปีไม่ถูกต้อง'); $where[]='EXISTS(SELECT 1 FROM member_dividends d WHERE d.member_id=m.id AND d.year=?)';$params[]=$year; }
            $sql=implode(' AND ',$where);
            $total=(int)Database::value('SELECT COUNT(*) FROM members m WHERE '.$sql,$params);
            $page=min($page,max(1,(int)ceil($total/$limit)));$offset=($page-1)*$limit;
            $items=Database::query("SELECT m.id,m.member_no,CONCAT(COALESCE(m.prefix,''),m.first_name,' ',m.last_name) AS name,m.department,m.status,CONCAT('*********',RIGHT(m.id_card,4)) AS id_card_masked,(SELECT COUNT(*) FROM member_dividends d WHERE d.member_id=m.id) AS record_count FROM members m WHERE {$sql} ORDER BY m.member_no LIMIT {$limit} OFFSET {$offset}",$params);
            return compact('items','total','page','limit');
        });
    }
    public function show(string $id): void
    {
        $this->respond(function()use($id){
            $member=MemberAdminService::profile($this->id($id));$version=MemberAdminService::version($member);
            $rows=Database::query('SELECT year,details_json,imported_at FROM member_dividends WHERE member_id=? ORDER BY year DESC',[$member['id']]);
            $dividends=array_map(static function($r){$record=json_decode($r['details_json'],true,512,JSON_THROW_ON_ERROR);return ['record'=>$record,'version'=>MemberAdminService::version($record),'updated_at'=>$r['imported_at']];},$rows);
            $history=Database::query('SELECT c.id,c.action,c.reason,c.before_json,c.after_json,c.created_at,u.name AS actor FROM member_data_changes c LEFT JOIN users u ON u.id=c.user_id WHERE c.member_id=? ORDER BY c.id DESC LIMIT 50',[$member['id']]);
            return compact('member','version','dividends','history');
        });
    }
    public function update(string $id): void
    {
        $this->respond(function()use($id){MemberAdminService::updateProfile($this->id($id),$this->request->all());return ['message'=>'บันทึกข้อมูลสมาชิกแล้ว'];});
    }
    public function updateDividend(string $id,string $year): void
    {
        $this->respond(function()use($id,$year){if(!preg_match('/^\d{4}$/D',$year))throw new MemberDataException('ปีไม่ถูกต้อง');MemberAdminService::updateDividend($this->id($id),(int)$year,$this->request->all());return ['message'=>'บันทึกข้อมูลปันผลแล้ว'];});
    }
}
