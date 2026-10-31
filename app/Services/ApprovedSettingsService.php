<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Auth,Database};
final class ApprovedSettingsService {
    public const KEYS=['site_title','site_subtitle','site_phone','site_fax','site_email','site_address','office_hours','facebook_url','line_oa'];
    public static function read(bool $lock=false):array {
        $marks=implode(',',array_fill(0,count(self::KEYS),'?'));
        $rows=Database::query("SELECT `key`,`value` FROM site_settings WHERE `key` IN ($marks) ORDER BY `key`".($lock?AdminCrudService::lock():''),self::KEYS);
        $settings=array_fill_keys(self::KEYS,'');foreach($rows as $row)$settings[$row['key']]=(string)$row['value'];ksort($settings);
        return ['settings'=>$settings,'version'=>AccessService::version($settings)];
    }
    public static function save(array $input):array {
        $values=$input['settings']??null;
        if(!is_array($values)||!$values||array_diff(array_keys($values),self::KEYS))throw new \InvalidArgumentException('แก้ไขได้เฉพาะข้อมูลเว็บไซต์ที่อนุญาต');
        foreach($values as $key=>$value){
            if(!is_string($value)||mb_strlen($value)>2000)throw new \InvalidArgumentException('ข้อความตั้งค่าไม่ถูกต้อง');
            if($key==='site_email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('อีเมลไม่ถูกต้อง');
            if($key==='facebook_url'&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||parse_url($value,PHP_URL_SCHEME)!=='https'))throw new \InvalidArgumentException('ลิงก์ต้องเป็น HTTPS');
        }
        return Database::transaction(function()use($input,$values){
            $old=self::read(true);if(!hash_equals($old['version'],(string)($input['version']??'')))throw new \App\Exceptions\SecurityGateException('ข้อมูลเปลี่ยนแปลงแล้ว กรุณาโหลดล่าสุด',409,'STALE_VERSION');
            foreach($values as $key=>$value)Database::execute("INSERT INTO site_settings (`key`,`value`,`group`,is_public,updated_at) VALUES (?,?,'general',1,NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),updated_at=NOW()",[$key,$value]);
            AuditService::log('settings','update',null,$old['settings'],$values,required:true);return self::read();
        });
    }
}
