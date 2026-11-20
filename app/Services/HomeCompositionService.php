<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use InvalidArgumentException;
use RuntimeException;

/** Published-only homepage arrangement; independent from legacy visibility keys. */
final class HomeCompositionService
{
    private const LIVE = 'home_composition_rc15_live';
    private const DRAFT = 'home_composition_rc15_draft';
    public const BLOCKS = ['hero','highlights','rates_news','quick_services','statistics','products','calculator','welfare'];
    private const THEMES = ['royal-blue','ocean','midnight'];
    private const SURFACES = ['original','light','navy','ocean'];
    private const SPACINGS = ['compact','comfortable','spacious'];
    private static function defaultLayers(): array
    {
        return array_fill_keys(self::BLOCKS, ['surface'=>'original','spacing'=>'comfortable']);
    }
    public static function defaults(): array
    {
        return ['order'=>self::BLOCKS,'visible'=>array_fill_keys(self::BLOCKS,true),
            'theme'=>'royal-blue','autoplay_seconds'=>6,'slides'=>[], 'layers'=>self::defaultLayers()];
    }
    public static function validate(mixed $input): array
    {
        if (!is_array($input)) throw new InvalidArgumentException('รูปแบบผังหน้าแรกไม่ถูกต้อง');
        $order=$input['order']??null; $visible=$input['visible']??null;
        if (!is_array($order)||!array_is_list($order)||count($order)!==count(self::BLOCKS)
            ||array_diff(self::BLOCKS,$order)||array_diff($order,self::BLOCKS)||count(array_unique($order))!==count($order)) {
            throw new InvalidArgumentException('ลำดับส่วนประกอบหน้าแรกไม่ถูกต้อง');
        }
        if (!is_array($visible)||count($visible)!==count(self::BLOCKS)
            ||array_diff(array_keys($visible),self::BLOCKS)
            ||array_diff(self::BLOCKS,array_keys($visible))) throw new InvalidArgumentException('การตั้งค่าการแสดงผลไม่ถูกต้อง');
        foreach ($visible as $flag) if (!is_bool($flag)) throw new InvalidArgumentException('สถานะการแสดงผลต้องเป็น boolean');
        $theme=$input['theme']??null;
        if (!in_array($theme,self::THEMES,true)) throw new InvalidArgumentException('ธีมไม่รองรับ');
        $seconds=$input['autoplay_seconds']??null;
        if (!is_int($seconds)||$seconds<4||$seconds>15) throw new InvalidArgumentException('ระยะเวลาเปลี่ยนสไลด์ต้อง 4–15 วินาที');
        $slides=$input['slides']??null;
        if (!is_array($slides)||!array_is_list($slides)||count($slides)>6) throw new InvalidArgumentException('รองรับสไลด์ไม่เกิน 6 รายการ');
        $ids=[];$normalized=[];
        foreach ($slides as $slide) {
            if (!is_array($slide)) throw new InvalidArgumentException('ข้อมูลสไลด์ไม่ถูกต้อง');
            $id=$slide['id']??null;$title=$slide['title']??null;$body=$slide['body']??null;$link=$slide['link']??'';
            if (!is_string($id)||!preg_match('/^[a-z0-9-]{4,64}$/D',$id)||isset($ids[$id])
                ||!is_string($title)||trim($title)===''||mb_strlen($title)>100
                ||!is_string($body)||trim($body)===''||mb_strlen($body)>320
                ||!is_string($link)||strlen($link)>180
                ||($link!==''&&!preg_match('~^/(?:about|news|welfare|documents|deposits|loans|statistics)(?:/[^?#]*)?$~D',$link))) {
                throw new InvalidArgumentException('หัวข้อ รายละเอียด หรือลิงก์สไลด์ไม่ถูกต้อง');
            }
            $ids[$id]=true;
            $normalized[]=['id'=>$id,'title'=>trim($title),'body'=>trim($body),'link'=>$link];
        }
        // Only predefined appearance values are accepted, never arbitrary CSS or HTML.
        $layers=$input['layers']??self::defaultLayers();
        if (!is_array($layers)||array_diff(array_keys($layers),self::BLOCKS)
            ||array_diff(self::BLOCKS,array_keys($layers))) {
            throw new InvalidArgumentException('ข้อมูล Layer ไม่ตรงกับส่วนที่รองรับ');
        }
        $normalizedLayers=[];
        foreach (self::BLOCKS as $block) {
            $layer=$layers[$block]??null;
            if (!is_array($layer) || array_diff(array_keys($layer),['surface','spacing'])) {
                throw new InvalidArgumentException('ข้อมูลตกแต่ง Layer ไม่ถูกต้อง');
            }
            $surface=$layer['surface']??null;
            $spacing=$layer['spacing']??null;
            if (!in_array($surface,self::SURFACES,true)||!in_array($spacing,self::SPACINGS,true)) {
                throw new InvalidArgumentException('ธีมพื้นหลังหรือระยะห่างไม่รองรับ');
            }
            $normalizedLayers[$block]=['surface'=>$surface,'spacing'=>$spacing];
        }
        return ['order'=>array_values($order),'visible'=>$visible,'theme'=>$theme,
            'autoplay_seconds'=>$seconds,'slides'=>$normalized,'layers'=>$normalizedLayers];
    }
    private static function read(string $key,bool $lock=false): ?array
    {
        $sql='SELECT `value` FROM site_settings WHERE `key`=?'
            .($lock&&Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'');
        $row=Database::first($sql,[$key]);
        if (!$row) return null;
        return self::validate(json_decode((string)$row['value'],true,512,JSON_THROW_ON_ERROR));
    }
    public static function published(): array {return self::read(self::LIVE)??self::defaults();}
    public static function state(bool $lock=false): array
    {
        $live=self::read(self::LIVE,$lock)??self::defaults();
        $draft=self::read(self::DRAFT,$lock);
        return ['published'=>$live,'draft'=>$draft,
            'version'=>hash('sha256',json_encode([$live,$draft],JSON_THROW_ON_ERROR))];
    }
    private static function store(string $key,array $content): void
    {
        Database::execute("INSERT INTO site_settings (`key`,`value`,`group`,is_public,updated_at)
          VALUES (?,?,'layout',0,NOW()) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`),updated_at=NOW()",
          [$key,json_encode($content,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    }
    public static function draft(array $input): array
    {
        $draft=self::validate($input['composition']??null);
        return Database::transaction(static function() use($draft,$input):array {
            $state=self::state(true);
            if (!hash_equals($state['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
            self::store(self::DRAFT,$draft);
            AuditService::log('home_composition','draft',null,$state['draft'],$draft,required:true);
            return self::state();
        });
    }
    public static function publish(array $input): array
    {
        return Database::transaction(static function()use($input):array{
            $state=self::state(true);
            if (!hash_equals($state['version'],(string)($input['version']??'')))throw new RuntimeException('VERSION_CONFLICT',409);
            if ($state['draft']===null)throw new InvalidArgumentException('ยังไม่มีร่าง');
            $draft=$state['draft'];
            if ($draft['visible']['highlights'] && count($draft['slides'])<5)
                throw new InvalidArgumentException('ต้องมีข้อความ Highlight 5–6 รายการก่อนเผยแพร่');
            self::store(self::LIVE,$draft);
            Database::execute('DELETE FROM site_settings WHERE `key`=?',[self::DRAFT]);
            AuditService::log('home_composition','publish',null,$state['published'],$draft,required:true);
            return self::state();
        });
    }
}
