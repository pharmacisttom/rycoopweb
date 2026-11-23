<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Versioned site mega-menu configuration, independent of legacy Navbar and navigation-copy.
 * No migration: uses existing site_settings JSON storage. Public defaults stay on legacy UI
 * until an explicit MFA-approved publication.
 */
final class MegaMenuService {
    private const LIVE = 'mega_menu_published_v1';
    private const DRAFT = 'mega_menu_draft_v1';
    private const HISTORY = 'mega_menu_previous_v1';
    private const IDS = ['about', 'finance', 'welfare', 'news_docs'];
    private const PATHS = [
        '/', '/about', '/board_committee', '/board_officer', '/statistics',
        '/deposits', '/rates', '/loans', '/calculator', '/loan-calculator',
        '/loans/calculator', '/loan-checklist', '/loan-readiness',
        '/loans/checklist', '/dividend-estimator', '/welfare', '/documents',
        '/news', '/announcements', '/events', '/calendar', '/contact',
        '/faqs', '/verify-receipt', '/privacy/policy', '/privacy/cookies',
        '/service-unavailable',
    ];
    private const ICONS = [
        'landmark', 'award', 'users', 'shield', 'wallet', 'calculator',
        'file-text', 'newspaper', 'megaphone', 'heart', 'graduation',
        'stethoscope', 'download', 'globe', 'calendar', 'receipt',
        'piggy-bank', 'banknote', 'check-circle', 'book-open',
    ];

    public static function template(): array {
        return [
            ['id'=>'about','title'=>'เกี่ยวกับสหกรณ์','enabled'=>true,'items'=>[
                self::item('history','ประวัติและวิสัยทัศน์','/about','landmark','ข้อมูลและโครงสร้างองค์กร'),
                self::item('board','คณะกรรมการดำเนินการ','/board_committee','award','ข้อมูลและโครงสร้างองค์กร'),
                self::item('officers','ฝ่ายจัดการและเจ้าหน้าที่','/board_officer','users','ข้อมูลและโครงสร้างองค์กร'),
                self::item('finance_stats','ฐานะและสถิติทางการเงิน','/statistics','wallet','ความมั่นคงและธรรมาภิบาล'),
                self::item('rules','ข้อบังคับและระเบียบ','/documents','file-text','ความมั่นคงและธรรมาภิบาล'),
            ]],
            ['id'=>'finance','title'=>'บริการทางการเงิน','enabled'=>true,'items'=>[
                self::item('deposits','เงินฝากออมทรัพย์','/deposits','piggy-bank','เงินฝากและผลตอบแทน'),
                self::item('rates','อัตราดอกเบี้ยเงินฝาก','/rates','banknote','เงินฝากและผลตอบแทน'),
                self::item('estimator','คำนวณเงินปันผล','/dividend-estimator','calculator','เงินฝากและผลตอบแทน'),
                self::item('loan','ผลิตภัณฑ์สินเชื่อ','/loans','wallet','เงินกู้และสินเชื่อ'),
                self::item('loan_check','เช็คความพร้อมขอกู้','/loan-checklist','check-circle','เงินกู้และสินเชื่อ'),
                self::item('calculator','คำนวณเงินกู้','/calculator','calculator','เงินกู้และสินเชื่อ'),
            ]],
            ['id'=>'welfare','title'=>'สวัสดิการ','enabled'=>true,'items'=>[
                self::item('welfare_six','สวัสดิการสงเคราะห์ 6 ประเภท','/welfare','heart','กองทุนสวัสดิการสมาชิก'),
                self::item('education','ทุนการศึกษาบุตรสมาชิก','/welfare','graduation','กองทุนสวัสดิการสมาชิก'),
                self::item('medical','สวัสดิการรักษาพยาบาล','/welfare','stethoscope','กองทุนสวัสดิการสมาชิก'),
                self::item('senior','สวัสดิการผู้สูงอายุ','/welfare','award','ความคุ้มครองสมาชิก'),
                self::item('forms','แบบฟอร์มขอสวัสดิการ','/documents','download','ความคุ้มครองสมาชิก'),
            ]],
            ['id'=>'news_docs','title'=>'ข่าวสารและเอกสาร','enabled'=>true,'items'=>[
                self::item('news','ข่าวประชาสัมพันธ์และกิจกรรม','/news','newspaper','ข่าวสารและกิจกรรม'),
                self::item('announcements','ประกาศสำคัญ','/announcements','megaphone','ข่าวสารและกิจกรรม'),
                self::item('calendar','ปฏิทินกิจกรรม','/calendar','calendar','ข่าวสารและกิจกรรม'),
                self::item('docs','ดาวน์โหลดแบบฟอร์มและรายงาน','/documents','download','เอกสารและบริการดิจิทัล'),
                self::item('receipt','ตรวจสอบใบเสร็จ','/verify-receipt','receipt','เอกสารและบริการดิจิทัล'),
            ]],
        ];
    }
    private static function item(string $id,string $title,string $path,string $icon,string $group):array {
        return compact('id','title','path','icon','group') +
            ['description'=>'','enabled'=>true];
    }
    public static function validate(mixed $raw): array {
        if (!is_array($raw) || count($raw)!==4 || array_keys($raw)!==range(0,3)) {
            throw new InvalidArgumentException('ต้องมีเมนูหลัก 4 หมวด');
        }
        $seen = []; $total = 0;
        foreach ($raw as &$category) {
            if(!is_array($category) || !in_array($category['id']??null,self::IDS,true)
                || isset($seen[$category['id']]) || !is_bool($category['enabled']??null)
                || !self::text($category['title']??null,80)
                || !is_array($category['items']??null) || count($category['items'])>30
                || array_keys($category['items'])!==(count($category['items']) ? range(0,count($category['items'])-1) : [])) {
                throw new InvalidArgumentException('รูปแบบหมวดเมนูไม่ถูกต้อง');
            }
            $seen[$category['id']]=true; $ids=[];
            $category=array_intersect_key($category,array_flip(['id','title','enabled','items']));
            foreach($category['items'] as &$item) {
                if(!is_array($item)||!self::text($item['id']??null,48)
                    || !preg_match('/^[a-z][a-z0-9_]*$/D',(string)$item['id'])
                    || isset($ids[$item['id']])
                    || !self::text($item['title']??null,100)
                    || !is_string($item['description']??null) || mb_strlen($item['description'])>240
                    || !self::text($item['group']??null,90)
                    || !in_array($item['icon']??null,self::ICONS,true)
                    || !in_array($item['path']??null,self::PATHS,true)
                    || !is_bool($item['enabled']??null)) {
                    throw new InvalidArgumentException('ข้อมูลเมนูย่อยไม่ถูกต้อง (ลิงก์ต้องอยู่ในรายการที่อนุญาต)');
                }
                $ids[$item['id']]=true;
                $item=array_intersect_key($item,array_flip(['id','title','description','group','icon','path','enabled']));
                $total++;
            }
            unset($item);
        }
        unset($category);
        if (count($seen)!==4 || $total>100) throw new InvalidArgumentException('จำนวนรายการเมนูเกินกำหนด');
        return $raw;
    }
    private static function text(mixed $value,int $max):bool {
        return is_string($value)&&trim($value)!==''&&mb_strlen($value)<=$max
            &&!preg_match('/[\x00-\x1f\x7f<>]/u',$value);
    }
    private static function load(string $key,bool $lock=false):?array {
        $suffix=$lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql' ? ' FOR UPDATE':'';
        $row=Database::first('SELECT `value` FROM site_settings WHERE `key`=?'.$suffix,[$key]);
        if(!$row)return null;
        return self::validate(json_decode((string)$row['value'],true,512,JSON_THROW_ON_ERROR));
    }
    private static function store(string $key,array $value):void {
        Database::execute("INSERT INTO site_settings (`key`,`value`,`group`,is_public,updated_at)
            VALUES (?,?,?,0,NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),updated_at=NOW()",
            [$key,json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'layout']);
    }
    public static function published(): ?array { return self::load(self::LIVE); }
    public static function state(bool $lock=false):array {
        $live=self::load(self::LIVE,$lock);
        $draft=self::load(self::DRAFT,$lock);
        return ['published'=>$live,'draft'=>$draft,'template'=>self::template(),
           'version'=>hash('sha256',json_encode([$live,$draft],JSON_THROW_ON_ERROR))];
    }
    public static function draft(array $input):array {
        $value=self::validate($input['categories']??null);
        return Database::transaction(static function()use($input,$value) {
            $old=self::state(true);
            if(!hash_equals($old['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
            self::store(self::DRAFT,$value);
            AuditService::log('mega_menu','draft',null,$old['draft'],$value,required:true);
            return self::state();
        });
    }
    public static function publish(array $input):array {
        return Database::transaction(static function()use($input) {
            $old=self::state(true);
            if(!hash_equals($old['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
            if(!$old['draft'])throw new InvalidArgumentException('ยังไม่มีร่างเมนู');
            if($old['published']) self::store(self::HISTORY,$old['published']);
            self::store(self::LIVE,$old['draft']);
            Database::execute('DELETE FROM site_settings WHERE `key`=?',[self::DRAFT]);
            AuditService::log('mega_menu','publish',null,$old['published'],$old['draft'],required:true);
            return self::state();
        });
    }
    public static function rollback(array $input):array {
        return Database::transaction(static function()use($input) {
            $old=self::state(true);
            if(!hash_equals($old['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
            $prev=self::load(self::HISTORY,true);
            if(!$prev)throw new InvalidArgumentException('ไม่มีเวอร์ชันก่อนหน้า');
            self::store(self::LIVE,$prev);
            self::store(self::HISTORY,$old['published']);
            AuditService::log('mega_menu','rollback',null,$old['published'],$prev,required:true);
            return self::state();
        });
    }
}
