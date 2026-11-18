<?php
declare(strict_types=1);
namespace App\Services;

use InvalidArgumentException;

/** Closed schema: no SQL identifiers, JSX, CSS, script or filesystem paths from editors. */
final class LayoutSchema
{
    public const TYPES=['hero','news','gallery','banner','cards','statistics','quick_links','rich_text','feature'];
    public const FEATURES=['hero','quick_access','introduction','statistics_news','statistics','loans','calculators','welfare'];
    public const DISPLAYS=['grid','carousel','masonry','cards','full'];
    public static function text(mixed $value,int $max=255,bool $required=false): string {
        if(!is_string($value)||mb_strlen($value)>$max||($required&&trim($value)===''))throw new InvalidArgumentException('กรุณากรอกข้อความให้ถูกต้องและไม่เกินความยาวที่กำหนด');
        return trim($value);
    }
    public static function id(mixed $id): string {
        if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new InvalidArgumentException('รหัสรายการไม่ถูกต้อง');return $id;
    }
    public static function slug(mixed $slug): string {
        if(!is_string($slug)||strlen($slug)>160||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))throw new InvalidArgumentException('Slug ต้องเป็นตัวอักษรอังกฤษพิมพ์เล็ก ตัวเลข และขีดกลาง');return $slug;
    }
    public static function url(mixed $url): string {
        $url=self::text($url,1000);
        if($url===''||preg_match('#^/(?!/)[^\s\\\\]*$#D',$url)||preg_match('#^https://[^\s]+$#D',$url)&&filter_var($url,FILTER_VALIDATE_URL))return $url;
        throw new InvalidArgumentException('ลิงก์ต้องเป็นเส้นทางภายในหรือ HTTPS ที่ปลอดภัย');
    }
    public static function defaults(): array {
        $sections=[];
        foreach(self::FEATURES as $i=>$feature)$sections[]=['id'=>str_pad(dechex($i+1),32,'0',STR_PAD_LEFT),'parent_id'=>null,'title'=>['ภาพหลัก','บริการด่วน','เกี่ยวกับสหกรณ์','สถิติและข่าว','สถิติ','ผลิตภัณฑ์สินเชื่อ','เครื่องคำนวณ','สวัสดิการ'][$i],'slug'=>str_replace('_','-',$feature),'type'=>'feature','visible'=>true,'archived'=>false,'home'=>true,'navigation'=>false,'config'=>['feature'=>$feature],'responsive'=>self::responsive([])];
        return ['sections'=>$sections,'categories'=>[]];
    }
    public static function responsive(array $input): array {
        $out=[];foreach(['desktop'=>3,'tablet'=>2,'mobile'=>1] as $device=>$count){$value=$input[$device]??[];if(!is_array($value))throw new InvalidArgumentException('การตั้งค่าหน้าจอไม่ถูกต้อง');$display=$value['display']??'grid';$columns=$value['columns']??$count;
            if(!in_array($display,self::DISPLAYS,true)||!is_int($columns)||$columns<1||$columns>6)throw new InvalidArgumentException('รูปแบบหน้าจอหรือจำนวนคอลัมน์ไม่ถูกต้อง');
            $out[$device]=['display'=>$display,'columns'=>$columns];}return $out;
    }
    public static function section(array $input,?array $old=null): array {
        $input=[...($old??[]),...$input];$type=$input['type']??'rich_text';if(!in_array($type,self::TYPES,true))throw new InvalidArgumentException('ประเภท section ไม่รองรับ');
        $config=$input['config']??[];if(!is_array($config))throw new InvalidArgumentException('การตั้งค่าไม่ถูกต้อง');$clean=[];
        if($type==='feature'){$clean['feature']=$config['feature']??'';if(!in_array($clean['feature'],self::FEATURES,true))throw new InvalidArgumentException('ไม่พบฟีเจอร์เดิม');}
        if($type==='rich_text')$clean['html']=RichTextService::clean(self::text($config['html']??'',50000));
        foreach(['description','image','href','category_id'] as $key)if(array_key_exists($key,$config))$clean[$key]=match($key){'image','href'=>self::url($config[$key]),'category_id'=>$config[$key]===null?null:self::id($config[$key]),default=>self::text($config[$key],2000)};
        if(isset($config['items'])){if(!is_array($config['items'])||!array_is_list($config['items'])||count($config['items'])>50)throw new InvalidArgumentException('รายการย่อยต้องไม่เกิน 50 รายการ');
            $clean['items']=array_map(function($item){if(!is_array($item))throw new InvalidArgumentException('รายการย่อยไม่ถูกต้อง');return ['title'=>self::text($item['title']??'',255,true),'description'=>self::text($item['description']??'',2000),'image'=>self::url($item['image']??''),'href'=>self::url($item['href']??'')];},$config['items']);}
        if(isset($input['responsive'])&&!is_array($input['responsive']))throw new InvalidArgumentException('การตั้งค่าหน้าจอไม่ถูกต้อง');
        $result=['id'=>$old['id']??bin2hex(random_bytes(16)),'parent_id'=>empty($input['parent_id'])?null:self::id($input['parent_id']),'title'=>self::text($input['title']??'',150,true),'slug'=>self::slug($input['slug']??''),'type'=>$type,'config'=>$clean,'responsive'=>self::responsive($input['responsive']??[])];
        foreach(['visible'=>true,'archived'=>false,'home'=>true,'navigation'=>false] as $key=>$default){$value=$input[$key]??$default;if(!is_bool($value))throw new InvalidArgumentException('ค่าการแสดงผลต้องเป็น true/false');$result[$key]=$value;}return $result;
    }
    public static function tree(array $nodes,bool $sections=true): void {
        if(count($nodes)>500||!array_is_list($nodes))throw new InvalidArgumentException('โครงสร้างต้องไม่เกิน 500 รายการ');$map=[];$slugs=[];$features=[];
        foreach($nodes as $n){$id=self::id($n['id']??null);if(isset($map[$id]))throw new InvalidArgumentException('รหัสรายการซ้ำ');$map[$id]=$n;
            if($sections){$slug=self::slug($n['slug']??null);if(isset($slugs[$slug]))throw new InvalidArgumentException('Slug ซ้ำ');$slugs[$slug]=true;
                if(($n['type']??'')==='feature'&&!($n['archived']??false)){$f=$n['config']['feature'];if(isset($features[$f]))throw new InvalidArgumentException('อ้างอิงฟีเจอร์เดิมซ้ำ');$features[$f]=true;}}}
        foreach($nodes as $n){$seen=[];$id=$n['id'];while($id!==null){if(isset($seen[$id])||count($seen)>=8||!isset($map[$id]))throw new InvalidArgumentException('โครงสร้างวนซ้ำ ลึกเกิน 8 ชั้น หรือไม่พบหมวดแม่');$seen[$id]=true;$id=$map[$id]['parent_id']??null;}}
    }
    public static function state(array $state): array {
        self::tree($state['sections']);self::tree($state['categories'],false);$categories=array_column($state['categories'],null,'id');
        foreach($state['sections'] as $section)if(!empty($section['config']['category_id'])&&!isset($categories[$section['config']['category_id']]))throw new InvalidArgumentException('ไม่พบหมวดกิจกรรมของ section');return $state;
    }
    public static function album(array $input,?array $old=null): array {
        $v=[...($old??[]),...$input];$date=$v['activity_date']??date('Y-m-d');$d=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$date);if(!$d||$d->format('Y-m-d')!==$date)throw new InvalidArgumentException('วันที่กิจกรรมไม่ถูกต้อง');
        $display=$v['display']??'grid';if(!in_array($display,self::DISPLAYS,true))throw new InvalidArgumentException('รูปแบบอัลบั้มไม่ถูกต้อง');
        return ['title'=>self::text($v['title']??'',255,true),'slug'=>self::slug($v['slug']??''),'description'=>self::text($v['description']??'',5000),'activity_date'=>$date,'category_id'=>empty($v['category_id'])?null:self::id($v['category_id']),'cover_id'=>empty($v['cover_id'])?null:self::id($v['cover_id']),'display'=>$display,'seo_title'=>self::text($v['seo_title']??'',150),'seo_description'=>self::text($v['seo_description']??'',300)];
    }
}
