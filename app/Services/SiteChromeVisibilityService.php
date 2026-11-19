<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

final class SiteChromeVisibilityService {
    private const LIVE='site_chrome_visibility_v1';
    private const DRAFT='site_chrome_visibility_draft_v1';
    public const LABELS=[
      'nav_menu'=>'เมนูนำทางด้านบน',
      'footer_brand'=>'Footer ข้อมูลสหกรณ์',
      'footer_finance'=>'Footer บริการทางการเงิน',
      'footer_welfare'=>'Footer สวัสดิการและสมาชิก',
      'footer_contact'=>'Footer ติดต่อสำนักงาน',
      'footer_counter'=>'Footer สถิติผู้เข้าชม'
    ];
    public static function defaults():array {return array_fill_keys(array_keys(self::LABELS),true);}
    public static function validate(mixed $raw):array {
      if(!is_array($raw) || array_keys($raw)!==array_keys(self::LABELS))throw new InvalidArgumentException('Invalid site chrome settings');
      foreach($raw as $v)if(!is_bool($v))throw new InvalidArgumentException('Invalid visibility flag');
      return $raw;
    }
    private static function load(string $key,bool $lock=false):?array {
      $suffix=$lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
      $q=chr(96);
      $row=Database::first('SELECT '.$q.'value'.$q.' FROM site_settings WHERE '.$q.'key'.$q.'=?'.$suffix,[$key]);
      return $row?self::validate(json_decode((string)$row['value'],true,512,JSON_THROW_ON_ERROR)):null;
    }
    public static function published():array {return self::load(self::LIVE)??self::defaults();}
    public static function state(bool $lock=false):array {
      $published=self::load(self::LIVE,$lock)??self::defaults();
      $draft=self::load(self::DRAFT,$lock);
      return ['published'=>$published,'draft'=>$draft,'sections'=>self::LABELS,
       'version'=>hash('sha256',json_encode([$published,$draft],JSON_THROW_ON_ERROR))];
    }
    private static function store(string $key,array $value):void {
      $q=chr(96);
      Database::execute('INSERT INTO site_settings ('.$q.'key'.$q.','.$q.'value'.$q.','.$q.'group'.$q.',is_public,updated_at) VALUES (?,?,?,0,NOW()) ON DUPLICATE KEY UPDATE '.$q.'value'.$q.'=VALUES('.$q.'value'.$q.'),updated_at=NOW()',
        [$key,json_encode($value,JSON_THROW_ON_ERROR),'layout']);
    }
    public static function draft(array $input):array {
      $new=self::validate($input['sections']??null);
      return Database::transaction(static function()use($input,$new){
        $old=self::state(true);
        if(!hash_equals($old['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
        self::store(self::DRAFT,$new);
        AuditService::log('site_chrome','draft',null,$old['draft'],$new,required:true);
        return self::state();
      });
    }
    public static function publish(array $input):array {
      return Database::transaction(static function()use($input){
        $old=self::state(true);
        if(!hash_equals($old['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
        if($old['draft']===null)throw new InvalidArgumentException('No draft to publish');
        self::store(self::LIVE,$old['draft']);
        $q=chr(96);Database::execute('DELETE FROM site_settings WHERE '.$q.'key'.$q.'=?',[self::DRAFT]);
        AuditService::log('site_chrome','publish',null,$old['published'],$old['draft'],required:true);
        return self::state();
      });
    }
}
