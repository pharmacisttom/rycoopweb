<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Controller;
use App\Core\Database;
use InvalidArgumentException;
use Throwable;

/** Isolated public welfare CMS. Auth + super_admin + CSRF at route level. */
final class WelfareCmsController extends Controller
{
    private const FIELDS = ['category','title','slug','short_description','full_description','benefit_amount','eligibility','required_documents','application_process','contact_info','sort_order','status'];
    private function version(array $r): string {return hash('sha256', $r['id'].'|'.$r['updated_at']);}
    public function index(): void
    {
        try {
            $rows=Database::query("SELECT id,category,title,slug,short_description,full_description,benefit_amount,eligibility,required_documents,application_process,contact_info,sort_order,status,updated_at FROM welfare WHERE deleted_at IS NULL ORDER BY sort_order,id");
            foreach ($rows as &$row) $row['version']=$this->version($row);
            unset($row);
            $this->json(['success'=>true,'data'=>$rows]);
        } catch(Throwable $e) {$this->json(['success'=>false,'message'=>'อ่านข้อมูลสวัสดิการไม่สำเร็จ'],503);}
    }
    private function validated(array $data): array
    {
        $out=[];
        foreach(self::FIELDS as $key) {
            if($key==='sort_order') {
                $v=filter_var($data[$key]??0,FILTER_VALIDATE_INT);
                if($v===false||$v<0||$v>100000)throw new InvalidArgumentException('ลำดับไม่ถูกต้อง');
                $out[$key]=$v; continue;
            }
            if($key==='status') {
                $v=(string)($data[$key]??'inactive');
                if(!in_array($v,['active','inactive'],true))throw new InvalidArgumentException('สถานะไม่ถูกต้อง');
                $out[$key]=$v;continue;
            }
            if(!is_string($data[$key]??''))throw new InvalidArgumentException('ข้อมูลไม่ถูกต้อง');
            $v=trim($data[$key]??'');
            $max=match($key){'full_description'=>20000,'eligibility','required_documents','application_process'=>5000,'title','slug','short_description','benefit_amount','contact_info'=>255,default=>100};
            if(mb_strlen($v)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$v))throw new InvalidArgumentException('ข้อความไม่ถูกต้อง');
            if(in_array($key,['category','title','slug'],true)&&$v==='')throw new InvalidArgumentException('กรอกหมวด ชื่อ และ slug');
            if($key==='slug'&&!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$v))throw new InvalidArgumentException('slug ต้องใช้ภาษาอังกฤษตัวเล็ก ตัวเลข และขีดกลาง');
            $out[$key]=$v===''&&!in_array($key,['category','title','slug'],true)?null:$v;
        }
        return $out;
    }
    public function store(): void {$this->save(null);}
    public function update(string $id): void {$this->save((int)$id);}
    private function save(?int $id): void
    {
        try {
            $data=$this->validated($this->request->all());
            if($id!==null) {
                $old=Database::first('SELECT id,updated_at FROM welfare WHERE id=? AND deleted_at IS NULL',[$id]);
                if(!$old){$this->json(['success'=>false,'message'=>'ไม่พบรายการ'],404);return;}
                if(!hash_equals($this->version($old),(string)($this->request->input('version')??''))){$this->json(['success'=>false,'message'=>'ข้อมูลถูกแก้ไขไปแล้ว กรุณาโหลดใหม่'],409);return;}
                $assignment=implode(',',array_map(static fn($k)=>$k.'=?',self::FIELDS));
                Database::execute("UPDATE welfare SET $assignment, updated_at=NOW() WHERE id=? AND deleted_at IS NULL",[...array_values($data),$id]);
            }else{
                $data['status']='inactive';
                $cols=implode(',',self::FIELDS);
                $marks=implode(',',array_fill(0,count(self::FIELDS),'?'));
                $id=(int)Database::insert("INSERT INTO welfare ($cols) VALUES ($marks)",array_values($data));
            }
            $saved=Database::first('SELECT id,updated_at FROM welfare WHERE id=?',[$id]);
            $this->json(['success'=>true,'data'=>['id'=>$id,'version'=>$this->version($saved)]]);
        }catch(InvalidArgumentException $e){$this->json(['success'=>false,'message'=>$e->getMessage()],422);}
        catch(Throwable $e){$this->json(['success'=>false,'message'=>'บันทึกไม่สำเร็จ ตรวจสอบ Slug ซ้ำหรือข้อผิดพลาดเซิร์ฟเวอร์'],409);}
    }
    public function disable(string $id): void
    {
        try {
            $old=Database::first('SELECT id,updated_at FROM welfare WHERE id=? AND deleted_at IS NULL',[(int)$id]);
            if(!$old){$this->json(['success'=>false,'message'=>'ไม่พบรายการ'],404);return;}
            if(!hash_equals($this->version($old),(string)($this->request->input('version')??''))){$this->json(['success'=>false,'message'=>'ข้อมูลเปลี่ยนไป กรุณาโหลดใหม่'],409);return;}
            Database::execute("UPDATE welfare SET status='inactive',deleted_at=NOW(),updated_at=NOW() WHERE id=? AND deleted_at IS NULL",[(int)$id]);
            $this->json(['success'=>true,'message'=>'ปิดรายการแล้ว']);
        }catch(Throwable $e){$this->json(['success'=>false,'message'=>'ปิดรายการไม่สำเร็จ'],503);}
    }
}
