<?php
declare(strict_types=1);
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Services\MemberAdminService;
use App\Services\MemberDataException;
use App\Services\MemberSchemaException;
use App\Services\MemberSystemHealth;

class MemberManagementController extends Controller
{
    private function failure(\Throwable $error): array
    {
        if ($error instanceof MemberSchemaException) {
            return ['code'=>'MEMBER_SCHEMA_NOT_READY','message'=>$error->getMessage(),'missing'=>$error->missing];
        }
        if ($error instanceof MemberDataException) return ['code'=>'VALIDATION_ERROR','message'=>$error->getMessage()];
        $reference=bin2hex(random_bytes(6));
        Logger::error('Member management failure '.$reference.': '.$error->getMessage());
        $schema=$error instanceof \PDOException && in_array((int)($error->errorInfo[1]??0),[1054,1146],true);
        return ['code'=>$schema?'MEMBER_SCHEMA_NOT_READY':'MEMBER_DATABASE_ERROR','reference'=>$reference,'message'=>$schema?'ฐานข้อมูลระบบสมาชิกยังอัปเดตไม่ครบ กรุณาให้ผู้ดูแลเซิร์ฟเวอร์ตรวจการอัปเดตฐานข้อมูล':'อ่านหรือบันทึกข้อมูลไม่สำเร็จ กรุณาแจ้งผู้ดูแลพร้อมรหัสอ้างอิง '.$reference];
    }

    private function respond(callable $work): void
    {
        header('Cache-Control: no-store, private');
        try { $this->json(['success'=>true]+$work()); }
        catch (\Throwable $error) {
            $failure=$this->failure($error);
            $this->json(['success'=>false]+$failure,$failure['code']==='MEMBER_SCHEMA_NOT_READY'?503:($error instanceof MemberDataException?422:500));
        }
    }

    private function section(string $name, callable $work, array &$warnings): mixed
    {
        try { return $work(); }
        catch (\Throwable $error) { $warnings[]=['section'=>$name]+$this->failure($error);return null; }
    }

    private function id(string $id): int
    {
        if(!ctype_digit($id)||(int)$id<1) throw new MemberDataException('เลขรายการไม่ถูกต้อง');
        return (int)$id;
    }

    private function nameSql(array $columns): string
    {
        $prefix=in_array('prefix',$columns['members']??[],true)?"COALESCE(m.prefix,'')":"''";
        return "TRIM(CONCAT({$prefix},COALESCE(m.first_name,''),' ',COALESCE(m.last_name,'')))";
    }

    public function health(): void
    {
        $this->respond(fn()=>['health'=>MemberSystemHealth::inspect()]);
    }

    public function dashboard(): void
    {
        $this->respond(function() {
            $columns=MemberSystemHealth::columns();$warnings=[];
            $members=$this->section('members',function()use($columns) {
                MemberSystemHealth::requireColumns(['members'=>['id','status','user_id']],$columns);
                return Database::first("SELECT COUNT(*) AS total,SUM(status='active') AS active,SUM(status='suspended') AS suspended,SUM(status='resigned') AS resigned,SUM(user_id IS NULL) AS no_account FROM members");
            },$warnings);
            $years=$this->section('years',function()use($columns) {
                MemberSystemHealth::requireColumns(['member_dividends'=>['year','details_json','imported_at']],$columns);
                return Database::query("SELECT year,COUNT(*) AS records,SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.total_income')) AS DECIMAL(18,2))) AS total_income,SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.total_deductions')) AS DECIMAL(18,2))) AS total_deductions,SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(details_json,'$.net')) AS DECIMAL(18,2))) AS net_total,MAX(imported_at) AS updated_at FROM member_dividends GROUP BY year ORDER BY year DESC");
            },$warnings);
            $runs=$this->section('runs',function()use($columns) {
                MemberSystemHealth::requireColumns(['member_import_runs'=>MemberSystemHealth::REQUIRED['member_import_runs'],'users'=>['id','name']],$columns);
                return Database::query('SELECT i.*,u.name AS actor FROM member_import_runs i LEFT JOIN users u ON u.id=i.user_id ORDER BY i.id DESC LIMIT 20');
            },$warnings);
            $changes=$this->section('changes',function()use($columns) {
                MemberSystemHealth::requireColumns(['member_data_changes'=>['id','member_id','user_id','action','reason','created_at'],'members'=>['id','member_no','first_name','last_name'],'users'=>['id','name']],$columns);
                $name=$this->nameSql($columns);
                return Database::query("SELECT c.id,c.member_id,c.action,c.reason,c.created_at,m.member_no,{$name} AS member_name,u.name AS actor FROM member_data_changes c JOIN members m ON m.id=c.member_id LEFT JOIN users u ON u.id=c.user_id ORDER BY c.id DESC LIMIT 20");
            },$warnings);
            return ['members'=>$members,'years'=>$years,'runs'=>$runs,'changes'=>$changes,'warnings'=>$warnings,'partial'=>(bool)$warnings];
        });
    }

    public function index(): void
    {
        $this->respond(function() {
            $columns=MemberSystemHealth::columns();$warnings=[];
            MemberSystemHealth::requireColumns(['members'=>['id','member_no','id_card','first_name','last_name','status','user_id']],$columns);
            $page=max(1,(int)$this->request->query('page',1));
            $requestedLimit=(int)$this->request->query('limit',25);
            $limit=in_array($requestedLimit,[25,50,100],true)?$requestedLimit:25;
            $q=trim((string)$this->request->query('q',''));
            $length=function_exists('mb_strlen')?mb_strlen($q):preg_match_all('/./us',$q);
            if($length>100) throw new MemberDataException('คำค้นยาวเกินกำหนด');
            $name=$this->nameSql($columns);
            $department=in_array('department',$columns['members'],true)?'m.department':"NULL";
            $where=['1=1'];$params=[];
            if($q!=='') {
                $where[]="(m.member_no LIKE ? OR m.id_card LIKE ? OR {$name} LIKE ? OR {$department} LIKE ?)";
                array_push($params,...array_fill(0,4,'%'.$q.'%'));
            }
            $status=(string)$this->request->query('status','');
            if($status!=='') {
                if(!in_array($status,['active','resigned','suspended'],true)) throw new MemberDataException('สถานะไม่ถูกต้อง');
                $where[]='m.status=?';$params[]=$status;
            }
            $year=(string)$this->request->query('year','');
            $hasReports=isset($columns['member_dividends']);
            if($year!=='' || $hasReports) MemberSystemHealth::requireColumns(['member_dividends'=>['member_id','year','details_json','imported_at']],$columns);
            if($year!=='') {
                if(!preg_match('/^\d{4}$/D',$year)||(int)$year<2400||(int)$year>2800) throw new MemberDataException('ปีต้องเป็น พ.ศ. 2400–2800');
                $where[]='EXISTS(SELECT 1 FROM member_dividends d WHERE d.member_id=m.id AND d.year=?)';$params[]=$year;
            }
            $sql=implode(' AND ',$where);
            $total=(int)Database::value('SELECT COUNT(*) FROM members m WHERE '.$sql,$params);
            $page=min($page,max(1,(int)ceil($total/$limit)));$offset=($page-1)*$limit;
            $reportCount=$hasReports?'(SELECT COUNT(*) FROM member_dividends d WHERE d.member_id=m.id)':'NULL';
            $join='';$finance='';$queryParams=$params;
            if($year!=='') {
                $join=' LEFT JOIN member_dividends selected_d ON selected_d.member_id=m.id AND selected_d.year=?';
                $queryParams=[$year,...$params];
                foreach(['total_income','total_deductions','net'] as $field) $finance.=",JSON_UNQUOTE(JSON_EXTRACT(selected_d.details_json,'$.{$field}')) AS {$field}";
                $finance.=',selected_d.imported_at AS dividend_updated_at';
            }
            $items=Database::query("SELECT m.id,m.member_no,{$name} AS name,{$department} AS department,m.status,m.user_id IS NOT NULL AS has_account,CONCAT('*********',RIGHT(m.id_card,4)) AS id_card_masked,{$reportCount} AS record_count{$finance} FROM members m{$join} WHERE {$sql} ORDER BY m.member_no,m.id LIMIT {$limit} OFFSET {$offset}",$queryParams);
            $missing=array_values(array_diff(['prefix','department'],$columns['members']));
            if($missing)$warnings[]=['section'=>'members','code'=>'MEMBER_SCHEMA_NOT_READY','message'=>'ข้อมูลบางช่องยังไม่พร้อม กรุณาตรวจการอัปเดตฐานข้อมูล','missing'=>array_map(fn($f)=>'members.'.$f,$missing)];
            if(!$hasReports)$warnings[]=['section'=>'years','code'=>'MEMBER_SCHEMA_NOT_READY','message'=>'ยังไม่มีตารางปันผล กรุณาอัปเดตฐานข้อมูลก่อนนำเข้า'];
            $availableYears=$hasReports?Database::query('SELECT DISTINCT year FROM member_dividends ORDER BY year DESC'):[];
            return ['items'=>$items,'total'=>$total,'page'=>$page,'limit'=>$limit,'year'=>$year,'available_years'=>array_column($availableYears,'year'),'warnings'=>$warnings];
        });
    }

    public function show(string $id): void
    {
        $this->respond(function()use($id) {
            $columns=MemberSystemHealth::columns();$warnings=[];
            $member=MemberAdminService::profile($this->id($id),false,$columns);$version=MemberAdminService::version($member);
            $dividends=$this->section('years',function()use($columns,$member) {
                MemberSystemHealth::requireColumns(['member_dividends'=>MemberSystemHealth::REQUIRED['member_dividends']],$columns);
                $rows=Database::query('SELECT year,details_json,imported_at FROM member_dividends WHERE member_id=? ORDER BY year DESC',[$member['id']]);
                return array_map(static function($r){$record=json_decode($r['details_json'],true,512,JSON_THROW_ON_ERROR);return ['record'=>$record,'version'=>MemberAdminService::version($record),'updated_at'=>$r['imported_at']];},$rows);
            },$warnings);
            $history=$this->section('changes',function()use($columns,$member) {
                MemberSystemHealth::requireColumns(['member_data_changes'=>MemberSystemHealth::REQUIRED['member_data_changes']],$columns);
                return Database::query('SELECT c.id,c.action,c.reason,c.before_json,c.after_json,c.created_at,u.name AS actor FROM member_data_changes c LEFT JOIN users u ON u.id=c.user_id WHERE c.member_id=? ORDER BY c.id DESC LIMIT 50',[$member['id']]);
            },$warnings);
            $readOnly=false;
            try{MemberSystemHealth::requireWrites();}catch(MemberSchemaException $error){$readOnly=true;$warnings[]=['section'=>'profile']+$this->failure($error);}
            return ['member'=>$member,'version'=>$version,'dividends'=>$dividends??[],'history'=>$history??[],'read_only'=>$readOnly,'warnings'=>$warnings];
        });
    }

    public function update(string $id): void
    {
        $this->respond(function()use($id){MemberSystemHealth::requireWrites();MemberAdminService::updateProfile($this->id($id),$this->request->all());return ['message'=>'บันทึกข้อมูลสมาชิกแล้ว'];});
    }

    public function updateDividend(string $id,string $year): void
    {
        $this->respond(function()use($id,$year){MemberSystemHealth::requireWrites();if(!preg_match('/^\d{4}$/D',$year))throw new MemberDataException('ปีไม่ถูกต้อง');MemberAdminService::updateDividend($this->id($id),(int)$year,$this->request->all());return ['message'=>'บันทึกข้อมูลปันผลแล้ว'];});
    }
}
