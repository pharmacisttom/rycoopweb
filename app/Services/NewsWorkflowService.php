<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth, Database};

class NewsWorkflowService
{
    private static function permission(string $action): void {
        if (!Auth::check()) throw new \RuntimeException('กรุณาเข้าสู่ระบบ',401);
        if (!Auth::hasPermission('news','view') || !Auth::hasPermission('news',$action)) throw new \RuntimeException('ไม่มีสิทธิ์ดำเนินการ',403);
    }
    public static function version(array $row): string { return AccessService::version($row); }
    private static function row(int $id, bool $lock=false): array {
        $suffix=$lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $row=Database::first('SELECT * FROM news WHERE id=? AND deleted_at IS NULL'.$suffix,[$id]);
        if(!$row)throw new \RuntimeException('ไม่พบข่าว',404);
        return $row;
    }
    private static function checkVersion(array $row,array $input): void {
        if(!hash_equals(self::version($row),(string)($input['version']??'')))throw new \RuntimeException('ข่าวเปลี่ยนแปลงแล้ว กรุณาโหลดข้อมูลล่าสุด',409);
    }
    private static function audit(string $action,int $id,?array $old,array $new):void {
        Database::insert('INSERT INTO audit_logs(user_id,module,action,record_id,old_values,new_values,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',[Auth::id(),'news',$action,(string)$id,$old?json_encode($old,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,json_encode($new,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$_SERVER['REMOTE_ADDR']??'127.0.0.1','news-workflow']);
    }
    public static function listing(): array {
        self::permission('view');
        return array_map(function($row){$row['version']=self::version($row);$row['excerpt']=$row['summary'];$row['image']=$row['cover_image'];$row['category']=Database::value('SELECT name FROM news_categories WHERE id=?',[$row['category_id']]);return $row;},Database::query('SELECT * FROM news WHERE deleted_at IS NULL ORDER BY id DESC'));
    }
    public static function save(array $input,string $image,?int $id=null): array {
        self::permission($id?'update':'create');
        $title=trim((string)($input['title']??''));$summary=trim((string)($input['excerpt']??''));$content=RichTextService::clean((string)($input['content']??''));
        if($title===''||mb_strlen($title)>255||RichTextService::text($content)===''||strlen($content)>200000||mb_strlen($summary)>5000)throw new \InvalidArgumentException('กรุณากรอกหัวข้อและเนื้อหาข่าวให้ครบถ้วน');
        $status=(string)($input['workflow_status']??'draft');
        if(!in_array($status,['draft','submitted'],true))throw new \InvalidArgumentException('บันทึกได้เฉพาะร่างหรือส่งตรวจ ให้เผยแพร่จากขั้นตอนอนุมัติ');
        $category=Database::value("SELECT id FROM news_categories WHERE name=? AND status='active'",[(string)($input['category']??'ข่าวประชาสัมพันธ์')]);
        if(!$category)throw new \InvalidArgumentException('ไม่พบหมวดหมู่ข่าว');
        return Database::transaction(function()use($input,$image,$id,$title,$summary,$content,$status,$category){
            $old=null;
            if($id){$old=self::row($id,true);self::checkVersion($old,$input);Database::execute('UPDATE news SET category_id=?,title=?,summary=?,content=?,cover_image=?,workflow_status=?,publish_at=NULL,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$category,$title,$summary,$content,$image,$status,Auth::id(),$id]);}
            else $id=(int)Database::insert('INSERT INTO news(category_id,title,slug,summary,content,cover_image,workflow_status,author_id,created_by,publish_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,NULL,CURRENT_TIMESTAMP)',[$category,$title,str_slug($title).'-'.bin2hex(random_bytes(6)),$summary,$content,$image,$status,Auth::id(),Auth::id()]);
            $row=self::row($id);self::audit($old?'update':'create',$id,$old,$row);$row['version']=self::version($row);return $row;
        });
    }
    public static function publish(int $id,array $input):void {
        self::permission('publish');
        Database::transaction(function()use($id,$input){$row=self::row($id,true);self::checkVersion($row,$input);
            if($row['workflow_status']!=='submitted')throw new \RuntimeException('ต้องส่งข่าวตรวจสอบก่อนเผยแพร่',409);
            Database::execute("UPDATE news SET workflow_status='published',publish_at=CURRENT_TIMESTAMP,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?",[Auth::id(),$id]);
            self::audit('publish',$id,$row,self::row($id));
        });
    }
}
