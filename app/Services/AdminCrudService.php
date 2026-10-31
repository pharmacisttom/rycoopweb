<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth,Database,Response};
use App\Exceptions\SecurityGateException;

/** Shared persistence for the existing CMS controllers/tables, with optimistic locks and required audit. */
final class AdminCrudService
{
    private const TABLES=['faqs'=>'faqs','announcements'=>'important_announcements','documents'=>'documents','news'=>'news'];
    public static function lock(): string {return Database::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';}
    public static function version(array $row): array {unset($row['version']);return [...$row,'version'=>AccessService::version($row)];}
    public static function checkVersion(array $row,array $input): void {
        if(!hash_equals(AccessService::version($row),(string)($input['version']??'')))throw new SecurityGateException('ข้อมูลเปลี่ยนแปลงแล้ว กรุณาโหลดล่าสุดก่อนบันทึก',409,'STALE_VERSION');
    }
    private static function permission(string $module,string $action): void {
        if(!Auth::check())throw new SecurityGateException('กรุณาเข้าสู่ระบบและยืนยัน MFA',401,'UNAUTHORIZED');
        if(!Auth::hasPermission($module,'view')||!Auth::hasPermission($module,$action))throw new SecurityGateException('ไม่มีสิทธิ์ดำเนินการ',403,'FORBIDDEN');
    }
    public static function respond(Response $response,callable $work,int $status=200): void {
        if(!headers_sent())header('Cache-Control: no-store, private');
        try {$result=$work();$response->json(['success'=>true,...$result],$status);}
        catch(SecurityGateException $e){$response->json(['success'=>false,'code'=>$e->getErrorCode(),'message'=>$e->getMessage()],$e->getHttpStatus());}
        catch(\InvalidArgumentException $e){$response->json(['success'=>false,'code'=>'VALIDATION_ERROR','message'=>$e->getMessage()],422);}
        catch(\PDOException $e){$conflict=$e->getCode()==='23000';$response->json(['success'=>false,'code'=>$conflict?'CONFLICT':'DATABASE_ERROR','message'=>$conflict?'ข้อมูลซ้ำหรือมีรายการอ้างอิง':'ฐานข้อมูลไม่พร้อม กรุณาลองใหม่'],$conflict?409:500);}
        catch(\Throwable $e){$response->json(['success'=>false,'code'=>'INTERNAL_ERROR','message'=>'บันทึกไม่สำเร็จ กรุณาลองใหม่'],500);}
    }
    public static function listing(string $module): array {
        self::permission($module,'view');$table=self::TABLES[$module];
        return array_map([self::class,'version'],Database::query("SELECT * FROM $table".($module==='faqs'?'':' WHERE deleted_at IS NULL').' ORDER BY id DESC'));
    }
    private static function row(string $module,int $id): array {
        $table=self::TABLES[$module];$row=Database::first("SELECT * FROM $table WHERE id=?".($module==='faqs'?'':' AND deleted_at IS NULL').self::lock(),[$id]);
        if(!$row)throw new SecurityGateException('ไม่พบรายการ',404,'NOT_FOUND');return $row;
    }
    public static function save(string $module,array $input,?int $id=null,?array $file=null): array {
        self::permission($module,$id===null?'create':($module==='faqs'?'update':'edit'));
        $uploaded=null;
        try {
            return Database::transaction(function()use($module,$input,$id,$file,&$uploaded){
                $old=$id===null?null:self::row($module,$id);if($old)self::checkVersion($old,$input);
                $merged=array_replace($old??[],$input);
                if($module==='faqs'){
                    $data=['question'=>trim((string)($merged['question']??'')),'answer'=>RichTextService::clean((string)($merged['answer']??'')),'category'=>(string)($merged['category']??'general'),'status'=>(string)($merged['status']??'active')];
                    if($data['question']===''||mb_strlen($data['question'])>255||RichTextService::text($data['answer'])===''||strlen($data['answer'])>60000||mb_strlen($data['category'])>50||!in_array($data['status'],['active','inactive'],true))throw new \InvalidArgumentException('กรุณาตรวจคำถาม คำตอบ หมวด และสถานะ');
                }else{
                    $title=trim((string)($merged['title']??''));if($title===''||mb_strlen($title)>255)throw new \InvalidArgumentException('กรุณากรอกชื่อไม่เกิน 255 ตัวอักษร');
                    $data=['title'=>$title];
                    if($module==='announcements'){
                        $content=RichTextService::clean((string)($merged['content']??''));$date=(string)($merged['publication_date']??'');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                        $state=(string)($merged['status']??'draft');$link=trim((string)($merged['related_link']??''));
                        if(RichTextService::text($content)===''||strlen($content)>60000||!$parsed||$parsed->format('Y-m-d')!==$date||!in_array($state,['draft','published','archived'],true)||($link!==''&&(!filter_var($link,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($link,PHP_URL_SCHEME)),['http','https'],true))))throw new \InvalidArgumentException('กรุณาตรวจเนื้อหา วันที่ สถานะ และลิงก์ประกาศ');
                        if(($state==='published'||($old['status']??'')==='published')&&!Auth::hasPermission('announcements','publish'))throw new SecurityGateException('ไม่มีสิทธิ์เปลี่ยนประกาศที่เผยแพร่',403,'FORBIDDEN');
                        $data+=['content'=>$content,'publication_date'=>$date,'status'=>$state,'summary'=>(string)($merged['summary']??''),'related_link'=>$link,'priority'=>(string)($merged['priority']??'general'),'is_pinned'=>empty($merged['is_pinned'])?0:1];
                        foreach(['effective_start_date','expiry_date'] as $key){$value=$merged[$key]??null;if($value!==null&&$value!==''){$parsedDate=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$value);if(!$parsedDate||$parsedDate->format('Y-m-d')!==$value)throw new \InvalidArgumentException('วันที่เริ่มหรือสิ้นสุดไม่ถูกต้อง');}$data[$key]=$value?:null;}
                        $data['resolution_no']=(string)($merged['resolution_no']??'');if(mb_strlen($data['resolution_no'])>100)throw new \InvalidArgumentException('เลขที่ประกาศยาวเกินกำหนด');

                        if(!in_array($data['priority'],['general','important','urgent'],true))throw new \InvalidArgumentException('ระดับความสำคัญไม่ถูกต้อง');
                    }elseif($module==='documents'){
                        $category=(int)($merged['category_id']??0);$state=(string)($merged['status']??'inactive');$year=(int)($merged['year']??(date('Y')+543));
                        if(!Database::value("SELECT id FROM document_categories WHERE id=? AND status='active'",[$category])||!in_array($state,['active','inactive'],true)||$year<2400||$year>2800)throw new \InvalidArgumentException('กรุณาตรวจหมวดเอกสาร ปี พ.ศ. และสถานะ');
                        $data+=['category_id'=>$category,'status'=>$state,'year'=>$year,'document_number'=>(string)($merged['document_number']??''),'tag'=>(string)($merged['tag']??''),'file_path'=>$old['file_path']??'','file_size'=>$old['file_size']??0,'file_type'=>$old['file_type']??'application/pdf'];
                        if(mb_strlen($data['document_number'])>100||mb_strlen($data['tag'])>100)throw new \InvalidArgumentException('เลขเอกสารหรือแท็กยาวเกินกำหนด');
                    }
                    if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                        try{$uploaded=MediaService::upload($file,$module);}catch(\InvalidArgumentException $e){throw new \InvalidArgumentException('ไฟล์ไม่ถูกต้องหรือไม่สามารถอัปโหลดได้');}
                        if($module==='documents'){$data['file_path']=$uploaded['path'];$data['file_size']=$uploaded['size'];$data['file_type']=$uploaded['mime'];}
                        else{$data['attachment_path']=$uploaded['path'];$data['attachment_name']=basename((string)$file['name']);}
                    }
                    if($module==='documents'&&$data['status']==='active'&&$data['file_path']==='')throw new \InvalidArgumentException('ต้องแนบไฟล์จริงก่อนเปิดใช้งานเอกสาร');
                    if($id===null)$data+=['slug'=>str_slug($title).'-'.bin2hex(random_bytes(6)),'created_by'=>Auth::id()];
                    else $data['updated_by']=Auth::id();
                }
                $table=self::TABLES[$module];$columns=array_keys($data);
                if($id===null){$names=implode(',',$columns);$marks=implode(',',array_fill(0,count($columns),'?'));$id=(int)Database::insert("INSERT INTO $table ($names,created_at,updated_at) VALUES ($marks,NOW(),NOW())",array_values($data));}
                else{$sets=implode(',',array_map(static fn($key)=>"$key=?",$columns));Database::execute("UPDATE $table SET $sets,updated_at=NOW() WHERE id=?",[...array_values($data),$id]);}
                AuditService::log($table,$old?'update':'create',(string)$id,$old,$data,required:true);
                return self::version(self::row($module,$id));
            });
        }catch(\Throwable $e){if($uploaded)@unlink(storage_upload_path($uploaded['path']));throw $e;}
    }
    public static function disable(string $module,int $id,array $input): void {
        self::permission($module,'delete');Database::transaction(function()use($module,$id,$input){$old=self::row($module,$id);self::checkVersion($old,$input);$table=self::TABLES[$module];
            if($module==='faqs')Database::execute("UPDATE faqs SET status='inactive',updated_at=NOW() WHERE id=?",[$id]);
            else Database::execute("UPDATE $table SET deleted_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=?",[Auth::id(),$id]);
            AuditService::log($table,'disable',(string)$id,$old,['disabled'=>true],required:true);
        });
    }
}
