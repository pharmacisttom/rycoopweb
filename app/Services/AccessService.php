<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Core\Auth;

class AccessService
{
    public const MODULES=['news'=>'ข่าวประชาสัมพันธ์','faqs'=>'คำถามที่พบบ่อย','members'=>'ข้อมูลสมาชิก','dividends'=>'เงินปันผล'];
    public const ACTIONS=['view'=>'ดูข้อมูล','create'=>'เพิ่ม','update'=>'แก้ไข','delete'=>'ลบ','import'=>'นำเข้า (สร้าง/อัปเดต)'];
    public const CATALOG=['news'=>['view','create','update','delete'],'faqs'=>['view','create','update','delete'],'members'=>['view','update'],'dividends'=>['update','import']];
    public static function keys(): array { $keys=[];foreach(self::CATALOG as $m=>$actions)foreach($actions as $a)$keys[]="$m.$a";return $keys; }
    public static function permissions(int $id): array {
        $user=Database::first("SELECT u.id,r.slug FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.id=? AND u.status='active' AND u.deleted_at IS NULL",[$id]);
        if(!$user)return [];
        if($user['slug']==='super_admin')return self::keys();
        return array_values(array_intersect(self::keys(),array_column(Database::query('SELECT DISTINCT CONCAT(p.module,\'.\',p.action) AS permission FROM user_roles ur JOIN role_permissions rp ON rp.role_id=ur.role_id JOIN permissions p ON p.id=rp.permission_id WHERE ur.user_id=?',[$id]),'permission')));
    }
    public static function version(array $record): string {return hash('sha256',json_encode($record,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));}
    public static function role(int $id): array {
        $r=Database::first('SELECT id,name,slug,description,updated_at FROM roles WHERE id=?',[$id]);if(!$r)throw new \InvalidArgumentException('ไม่พบ Role');
        $r['permissions']=array_column(Database::query('SELECT CONCAT(p.module,\'.\',p.action) AS permission FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.module,p.action',[$id]),'permission');
        $r['users']=(int)Database::value('SELECT COUNT(*) FROM user_roles WHERE role_id=?',[$id]);
        $r['builtin']=isset(config('permissions.roles',[])[$r['slug']]) || in_array($r['slug'],['super_admin','member','staff'],true);$r['protected']=in_array($r['slug'],['super_admin','member'],true);$r['version']=self::version($r);return $r;
    }
    private static function transaction(callable $work): mixed {Database::beginTransaction();try{$r=$work();Database::commit();return $r;}catch(\Throwable $e){Database::rollBack();throw $e;}}
    private static function assertVersion(array $r,array $data): void {if(!isset($data['version']) || !hash_equals($r['version'],(string)$data['version']))throw new \InvalidArgumentException('ข้อมูลเปลี่ยนแปลงแล้ว กรุณาโหลดล่าสุดก่อนแก้ไข');}
    public static function saveRole(array $data,?int $id=null): array {
        $name=trim((string)($data['name']??''));$slug=trim((string)($data['slug']??''));$description=trim((string)($data['description']??''));
        if($name==='' || mb_strlen($name)>50 || strlen($slug)>50 || !preg_match('/^[a-z][a-z0-9_]*$/D',$slug) || mb_strlen($description)>255)throw new \InvalidArgumentException('กรอกชื่อ Role และรหัสภาษาอังกฤษให้ถูกต้อง');
        $permissions=$data['permissions']??[];if(!is_array($permissions) || array_diff($permissions,self::keys()))throw new \InvalidArgumentException('สิทธิ์ที่เลือกไม่ถูกต้อง');
        foreach(['news','faqs'] as $m)if(array_intersect(["$m.create","$m.update","$m.delete"],$permissions) && !in_array("$m.view",$permissions,true))throw new \InvalidArgumentException('สิทธิ์เพิ่ม/แก้ไข/ลบต้องเลือกสิทธิ์ดูข้อมูลด้วย');
        if(array_intersect(['members.update','dividends.update','dividends.import'],$permissions) && !in_array('members.view',$permissions,true))throw new \InvalidArgumentException('การดูแลสมาชิก/ปันผลต้องมีสิทธิ์ดูสมาชิก');
        return self::transaction(function()use($data,$id,$name,$slug,$description,$permissions){
            if($id){Database::first('SELECT id FROM roles WHERE id=? FOR UPDATE',[$id]);$old=self::role($id);self::assertVersion($old,$data);if($old['protected'])throw new \InvalidArgumentException('Role หลักนี้สงวนไว้สำหรับระบบ');if($slug!==$old['slug'])throw new \InvalidArgumentException('ไม่สามารถเปลี่ยนรหัส Role ที่มีอยู่แล้ว');Database::execute('UPDATE roles SET name=?,description=?,updated_at=NOW() WHERE id=?',[$name,$description,$id]);}
            else{if(isset(config('permissions.roles',[])[$slug]) || in_array($slug,['super_admin','member','staff'],true))throw new \InvalidArgumentException('รหัส Role นี้สงวนไว้');$id=(int)Database::insert('INSERT INTO roles(name,slug,description) VALUES(?,?,?)',[$name,$slug,$description]);}
            foreach(self::keys() as $key){[$m,$a]=explode('.',$key);$permission=Database::value('SELECT id FROM permissions WHERE module=? AND action=?',[$m,$a]);if(!$permission)throw new \InvalidArgumentException('กรุณาอัปเดตฐานข้อมูลสิทธิ์ก่อน');Database::execute('DELETE FROM role_permissions WHERE role_id=? AND permission_id=?',[$id,$permission]);if(in_array($key,$permissions,true))Database::execute('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)',[$id,$permission]);}
            AuditService::log('roles',isset($old)?'update':'create',(string)$id,$old??null,['name'=>$name,'permissions'=>$permissions]);return self::role($id);
        });
    }
    public static function deleteRole(int $id,array $data): void {self::transaction(function()use($id,$data){Database::first('SELECT id FROM roles WHERE id=? FOR UPDATE',[$id]);$r=self::role($id);self::assertVersion($r,$data);if($r['builtin'] || $r['users'])throw new \InvalidArgumentException('ลบได้เฉพาะ Role ที่สร้างเองและยังไม่มีผู้ใช้งาน');Database::execute('DELETE FROM roles WHERE id=?',[$id]);AuditService::log('roles','delete',(string)$id,$r,null);});}
    public static function account(int $id): array {
        $u=Database::first('SELECT id,name,username,email,status,updated_at,deleted_at FROM users WHERE id=? AND deleted_at IS NULL',[$id]);if(!$u)throw new \InvalidArgumentException('ไม่พบผู้ใช้งาน');
        $u['roles']=Database::query('SELECT r.id,r.slug,r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? ORDER BY r.id',[$id]);$u['is_member']=(bool)Database::value('SELECT COUNT(*) FROM members WHERE user_id=?',[$id]);$u['version']=self::version($u);return $u;
    }
    private static function lockAdministrators(): void {Database::query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='super_admin' ORDER BY u.id FOR UPDATE");}
    private static function protectAdministrator(array $old,bool $removing): void {
        if(!$removing)return;
        if((int)$old['id']===(int)Auth::id())throw new \InvalidArgumentException('ไม่สามารถลบ ระงับ หรือเปลี่ยน Role ของตนเอง');
        if(in_array('super_admin',array_column($old['roles'],'slug'),true)){
            $count=(int)Database::value("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='super_admin' AND u.status='active' AND u.deleted_at IS NULL");
            if($old['status']==='active' && $count<=1)throw new \InvalidArgumentException('ต้องมีแอดมินหลักที่ใช้งานได้อย่างน้อยหนึ่งบัญชี');
        }
    }
    public static function saveAccount(array $data,?int $id=null): array {
        $name=trim((string)($data['name']??''));$username=trim((string)($data['username']??''));$email=trim((string)($data['email']??''));$status=(string)($data['status']??'active');$roleId=(int)($data['role_id']??0);
        if($name==='' || mb_strlen($name)>100 || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,49}$/D',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100 || !in_array($status,['active','inactive','suspended'],true))throw new \InvalidArgumentException('กรอกชื่อ ชื่อผู้ใช้ อีเมล และสถานะให้ถูกต้อง');
        $password=(string)($data['password']??'');if(!$id && (strlen($password)<12 || strlen($password)>200))throw new \InvalidArgumentException('บัญชีใหม่ต้องมีรหัสผ่าน 12–200 ตัวอักษร');
        return self::transaction(function()use($data,$id,$name,$username,$email,$status,$roleId,$password){
            self::lockAdministrators();Database::first('SELECT id FROM roles WHERE id=? FOR UPDATE',[$roleId]);$role=self::role($roleId);
            if($role['slug']==='member')throw new \InvalidArgumentException('บัญชีสมาชิกให้จัดการผ่านระบบสมาชิก');
            if($id){Database::first('SELECT id FROM users WHERE id=? FOR UPDATE',[$id]);$old=self::account($id);self::assertVersion($old,$data);if($old['is_member'])throw new \InvalidArgumentException('บัญชีนี้เชื่อมกับสมาชิก ให้แก้ไขผ่านระบบสมาชิก');
                self::protectAdministrator($old,$status!=='active' || count($old['roles'])!==1 || (int)$old['roles'][0]['id']!==$roleId);
                Database::execute('UPDATE users SET name=?,username=?,email=?,status=?,updated_at=NOW() WHERE id=?',[$name,$username,$email,$status,$id]);
                Database::execute('DELETE FROM user_roles WHERE user_id=?',[$id]);
            }else{$id=(int)Database::insert('INSERT INTO users(uuid,name,username,email,password,status) VALUES(?,?,?,?,?,?)',[bin2hex(random_bytes(16)),$name,$username,$email,password_hash($password,PASSWORD_ARGON2ID),$status]);}
            Database::execute('INSERT INTO user_roles(user_id,role_id) VALUES(?,?)',[$id,$roleId]);AuditService::log('users',isset($old)?'update':'create',(string)$id,isset($old)?['name'=>$old['name'],'status'=>$old['status'],'roles'=>$old['roles']]:null,['name'=>$name,'status'=>$status,'role_id'=>$roleId]);return self::account($id);
        });
    }
    public static function deleteAccount(int $id,array $data): void {self::transaction(function()use($id,$data){self::lockAdministrators();Database::first('SELECT id FROM users WHERE id=? FOR UPDATE',[$id]);$u=self::account($id);self::assertVersion($u,$data);if($u['is_member'])throw new \InvalidArgumentException('บัญชีสมาชิกให้จัดการผ่านระบบสมาชิก');self::protectAdministrator($u,true);Database::execute("UPDATE users SET status='inactive',deleted_at=NOW() WHERE id=?",[$id]);AuditService::log('users','delete',(string)$id,['name'=>$u['name']],null);});}
}
