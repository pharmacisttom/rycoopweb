<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Auth, Database};
use InvalidArgumentException;
use RuntimeException;

/** Public CMS only: never reads or changes member/financial account records. */
class PublicContentService
{
    private const KEY = 'public_content_catalog';
    public const SECTIONS = [
        'profile' => ['label'=>'ข้อมูลสหกรณ์','module'=>'coop_info','path'=>['coopInfo']],
        'boards' => ['label'=>'กรรมการ','module'=>'people','path'=>['boardMembers']],
        'staff' => ['label'=>'เจ้าหน้าที่','module'=>'people','path'=>['staffMembers']],
        'advisors' => ['label'=>'ที่ปรึกษา','module'=>'people','path'=>['advisors']],
        'auditors' => ['label'=>'ผู้ตรวจสอบกิจการ','module'=>'people','path'=>['auditors']],
        'groups' => ['label'=>'คณะกรรมการย่อย','module'=>'people','path'=>['boardGroups']],
        'deposits' => ['label'=>'อัตราเงินฝาก','module'=>'rates','path'=>['interestRates','deposits']],
        'loan_rates' => ['label'=>'อัตราเงินกู้','module'=>'rates','path'=>['interestRates','loans']],
        'products' => ['label'=>'ผลิตภัณฑ์เงินกู้','module'=>'products','path'=>['loanProducts']],
    ];

    public static function reference(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__,2).'/src/data/legacyReference.json'), true, 512, JSON_THROW_ON_ERROR);
    }
    private static function json(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
    private static function setting(bool $lock = false): ?array
    {
        $suffix = $lock && Database::connect()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        return Database::first('SELECT id,`value` FROM site_settings WHERE `key`=?'.$suffix, [self::KEY]);
    }
    private static function payload(array $catalog, string $section): array
    {
        $result = $catalog;
        foreach (self::SECTIONS[$section]['path'] as $key) $result = $result[$key];
        return $result;
    }
    private static function assign(array &$catalog, string $section, array $value): void
    {
        $target =& $catalog;
        foreach (self::SECTIONS[$section]['path'] as $key) $target =& $target[$key];
        $target = $value;
    }
    public static function requirePermission(string $section, string $action): void
    {
        if (!isset(self::SECTIONS[$section])) throw new InvalidArgumentException('ไม่พบหมวดข้อมูล');
        $module = self::SECTIONS[$section]['module'];
        if (!Auth::check() || !Auth::user()) throw new RuntimeException('UNAUTHORIZED',401);
        if (!Auth::hasPermission($module,'view') || !Auth::hasPermission($module,$action)) throw new RuntimeException('FORBIDDEN',403);
    }
    public static function catalog(): array
    {
        $base = self::setting();
        $catalog = $base ? json_decode($base['value'],true,512,JSON_THROW_ON_ERROR) : self::reference();
        $today = date('Y-m-d');
        $rows = Database::query("SELECT section,payload,source_url,effective_on,expires_on,id FROM public_content_revisions WHERE state='published' AND effective_on<=? AND (expires_on IS NULL OR expires_on>=?) ORDER BY effective_on,id",[$today,$today]);
        $metadata = [];
        foreach ($rows as $row) {
            if (!isset(self::SECTIONS[$row['section']])) continue;
            self::assign($catalog,$row['section'],json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR));
            $metadata[$row['section']] = ['sourceUrl'=>$row['source_url'],'effectiveOn'=>$row['effective_on'],'expiresOn'=>$row['expires_on'],'revision'=>(int)$row['id']];
        }
        foreach ($catalog['loanProducts'] as &$product) {
            foreach ($catalog['interestRates']['loans'] as $rate) if ((!empty($rate['productId']) && $rate['productId']===$product['id']) || (empty($rate['productId']) && $rate['type']===$product['title'])) {
                $product['rateValue']=(float)rtrim($rate['rate'],'%');
                $product['interestRate']=$rate['rate'].' ต่อปี';
                break;
            }
        }
        $catalog['publication'] = $metadata;
        return $catalog;
    }
    public static function apiRates(array $catalog): array {
        $result=['deposit'=>[],'loan'=>[]];
        foreach(['deposit'=>'deposits','loan'=>'loans'] as $type=>$key)foreach($catalog['interestRates'][$key] as $index=>$rate){
            $result[$type][]=['id'=>$index+1,'product_name'=>$rate['type'],'product_type'=>$type,'rate'=>(float)rtrim($rate['rate'],'%'),'min_amount'=>$type==='deposit'?(float)preg_replace('/[^0-9.]/','',$rate['minDeposit']):null,'max_amount'=>null,'condition_note'=>$rate['payFreq']??$rate['condition']??'','description'=>$rate['payFreq']??$rate['condition']??'','icon'=>null,'sort_order'=>$index,'effective_date'=>$catalog['publication'][$type==='deposit'?'deposits':'loan_rates']['effectiveOn']??null];
        }return $result;
    }
    public static function apiProducts(array $catalog):array {
        return array_map(fn($product)=>['id'=>$product['id'],'name'=>$product['title'],'slug'=>$product['id'],'short_description'=>$product['subtitle']??'','description'=>implode("\n",$product['features']),'interest_rate'=>$product['rateValue'],'max_amount'=>$product['maxLimit'],'max_term_months'=>$product['maxTerm'],'guarantor_requirement'=>$product['guarantee'],'is_calculator_enabled'=>true,'icon'=>null,'badge'=>$product['badge']??null],$catalog['loanProducts']);
    }
    public static function section(string $section): array
    {
        self::requirePermission($section,'view');
        $value = self::payload(self::catalog(),$section);
        $latest = Database::value("SELECT MAX(id) FROM public_content_revisions WHERE section=? AND state='published'",[$section]);
        return ['value'=>$value,'version'=>hash('sha256',self::json([$value,(int)$latest])),
            'history'=>Database::query('SELECT id,section,payload,previous_payload,source_url,effective_on,expires_on,note,state,created_by,published_by,created_at,published_at FROM public_content_revisions WHERE section=? ORDER BY id DESC LIMIT 50',[$section])];
    }
    private static function text(mixed $value, int $limit = 255, bool $required = false): string
    {
        if (!is_string($value) || mb_strlen($value)>$limit || ($required && trim($value)==='') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$value)) throw new InvalidArgumentException('ข้อความไม่ถูกต้องหรือยาวเกินกำหนด');
        return trim($value);
    }
    private static function url(mixed $value, bool $image = false): ?string
    {
        if ($value === null || $value === '') return null;
        $value = self::text($value,500);
        if ($image && preg_match('#^(?:\./|/)(?:assets|storage/uploads|uploads)/[A-Za-z0-9_./%-]+$#D',$value) && !str_contains($value,'..')) return $value;
        if (filter_var($value,FILTER_VALIDATE_URL) && parse_url($value,PHP_URL_SCHEME)==='https' && !parse_url($value,PHP_URL_USER) && !parse_url($value,PHP_URL_PASS)) return $value;
        throw new InvalidArgumentException('URL ต้องเป็น HTTPS หรือไฟล์รูปในระบบที่อนุญาต');
    }
    private static function integer(mixed $value, int $max): int
    {
        if (filter_var($value,FILTER_VALIDATE_INT)===false || (int)$value<1 || (int)$value>$max) throw new InvalidArgumentException('จำนวนหรือวงเงินไม่ถูกต้อง');
        return (int)$value;
    }
    private static function date(string $value): string
    {
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$parsed || $parsed->format('Y-m-d')!==$value) throw new InvalidArgumentException('วันที่มีผลไม่ถูกต้อง');
        return $value;
    }
    public static function validate(string $section, mixed $value): array
    {
        if (!isset(self::SECTIONS[$section]) || !is_array($value)) throw new InvalidArgumentException('ข้อมูลหมวดไม่ถูกต้อง');
        if ($section==='profile') {
            $allowed=['nameTh','nameEn','shortName','slogan','establishedYear','address','phone','fax','email','workHours','lineId','facebook','mapUrl','mapEmbedUrl','lat','lng'];
            if (array_diff(array_keys($value),$allowed)) throw new InvalidArgumentException('มีฟิลด์ที่ไม่ได้รับอนุญาต');
            foreach (['nameTh','address','phone','email'] as $key) self::text($value[$key]??null,500,true);
            foreach ($value as $key=>$item) {
                if (in_array($key,['mapUrl','mapEmbedUrl'],true)) $value[$key]=self::url($item);
                elseif (in_array($key,['lat','lng'],true)) { if ($item!==null && (!is_numeric($item) || abs((float)$item)>($key==='lat'?90:180))) throw new InvalidArgumentException('พิกัดไม่ถูกต้อง'); }
                else $value[$key]=$item===null?null:self::text($item,500);
            }
            if (!filter_var($value['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('อีเมลไม่ถูกต้อง');
            if (!empty($value['facebook'])) self::url('https://'.$value['facebook']);
            return $value;
        }
        if (!array_is_list($value) || count($value)>200) throw new InvalidArgumentException('รายการข้อมูลไม่ถูกต้องหรือเกิน 200 รายการ');
        $keys=[];
        foreach ($value as &$item) {
            if (!is_array($item)) throw new InvalidArgumentException('รูปแบบรายการไม่ถูกต้อง');
            if (in_array($section,['advisors','auditors'],true)) {
                if (count($item)!==2 || !array_is_list($item)) throw new InvalidArgumentException('รูปแบบชื่อและตำแหน่งไม่ถูกต้อง');
                $item=[self::text($item[0],150,true),self::text($item[1],150,true)]; continue;
            }
            if ($section==='groups') {
                $title=self::text($item['title']??null,150,true);$members=$item['members']??null;
                if (!is_array($members) || !array_is_list($members) || count($members)>50) throw new InvalidArgumentException('รายชื่อคณะย่อยไม่ถูกต้อง');
                $boardIds=array_column(self::catalog()['boardMembers'],'id');$seen=[];
                foreach ($members as $member) { if (!is_array($member) || count($member)!==2 || !in_array((int)$member[0],array_map('intval',$boardIds),true) || isset($seen[(int)$member[0]])) throw new InvalidArgumentException('กรรมการในคณะย่อยไม่ถูกต้องหรือซ้ำ');self::text($member[1],150,true);$seen[(int)$member[0]]=true; }
                $item=['title'=>$title,'members'=>$members];continue;
            }
            $allowed=match($section){
                'boards'=>['id','name','position','image','term','workplace'],
                'staff'=>['id','name','position','image','department'],
                'deposits'=>['type','rate','minDeposit','payFreq','highlight'],
                'loan_rates'=>['type','rate','maxTerm','condition','productId'],
                'products'=>['id','title','subtitle','badge','badgeColor','maxAmount','interestRate','rateValue','maxLimit','period','maxTerm','guarantee','features'],
            };
            if (array_diff(array_keys($item),$allowed)) throw new InvalidArgumentException('มีฟิลด์ที่ไม่ได้รับอนุญาต');
            foreach ($item as $key=>$field) {
                if ($key==='image') $item[$key]=self::url($field,true);
                elseif ($key==='features') { if (!is_array($field) || !array_is_list($field) || count($field)>20) throw new InvalidArgumentException('รายละเอียดไม่ถูกต้อง');foreach($field as $line)self::text($line,1000,true); }
                elseif ($section==='products' && in_array($key,['maxLimit','maxTerm'],true)) $item[$key]=self::integer($field,$key==='maxTerm'?600:999999999);
                elseif ($key==='id' && $section!=='products') $item[$key]=self::integer($field,999999);
                elseif ($key==='highlight') $item[$key]=(bool)$field;
                elseif (in_array($key,['rate','rateValue'],true)) { $number=rtrim((string)$field,'%');if (!preg_match('/^\d{1,3}(?:\.\d{1,3})?$/D',$number) || (float)$number>100) throw new InvalidArgumentException('ดอกเบี้ยต้องอยู่ระหว่าง 0–100% และทศนิยมไม่เกิน 3 ตำแหน่ง'); }
                else $item[$key]=self::text($field,1000);
            }
            $required=match($section){'boards','staff'=>['id','name','position'],'deposits'=>['type','rate','minDeposit','payFreq'],'loan_rates'=>['type','rate','maxTerm','condition'],'products'=>['id','title','rateValue','maxLimit','maxTerm','guarantee','features']};
            foreach($required as $key)if(!array_key_exists($key,$item) || $item[$key]==='')throw new InvalidArgumentException('กรุณากรอกข้อมูลที่จำเป็นให้ครบ');
            if ($section==='loan_rates' && !empty($item['productId']) && !in_array($item['productId'],array_column(self::catalog()['loanProducts'],'id'),true)) throw new InvalidArgumentException('รหัสผลิตภัณฑ์ที่เชื่อมไม่ถูกต้อง');
            $identity=(string)($item['id']??$item['type']);if(isset($keys[$identity]))throw new InvalidArgumentException('รหัสหรือชื่อประเภทซ้ำ');$keys[$identity]=true;
            if ($section==='products') {
                if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/D',(string)$item['id'])) throw new InvalidArgumentException('รหัสผลิตภัณฑ์ไม่ถูกต้อง');
                $linked=null;foreach(self::catalog()['interestRates']['loans'] as $rate)if((!empty($rate['productId'])&&$rate['productId']===$item['id'])||(empty($rate['productId'])&&$rate['type']===$item['title'])){$linked=$rate;break;}
                if(!$linked)throw new InvalidArgumentException('กรุณาสร้างอัตราเงินกู้ที่เชื่อมกับผลิตภัณฑ์นี้ก่อน');
                $item['rateValue']=(float)rtrim($linked['rate'],'%');
                $item['interestRate']=rtrim((string)$item['rateValue'],'%').'% ต่อปี';$item['maxAmount']='สูงสุด '.number_format($item['maxLimit']).' บาท';$item['period']='ผ่อนชำระสูงสุด '.$item['maxTerm'].' งวด';
                $item += ['subtitle'=>$item['title'],'badge'=>'ข้อมูลสหกรณ์','badgeColor'=>'primary'];
            }
        }
        if ($section==='loan_rates') foreach(self::catalog()['loanProducts'] as $product){
            $found=false;foreach($value as $rate)if((!empty($rate['productId'])&&$rate['productId']===$product['id'])||(empty($rate['productId'])&&$rate['type']===$product['title']))$found=true;
            if(!$found)throw new InvalidArgumentException('กรุณานำผลิตภัณฑ์ที่เชื่อมออกก่อนลบอัตราเงินกู้');
        }
        if ($section==='boards') {
            $ids=array_column($value,'id');
            foreach(self::catalog()['boardGroups'] as $group)foreach($group['members'] as [$id])if(!in_array((int)$id,array_map('intval',$ids),true))throw new InvalidArgumentException('กรุณานำกรรมการออกจากคณะย่อยและเผยแพร่ก่อนนำออกจากทำเนียบ');
        }
        return $value;
    }
    private static function lockBase(): void
    {
        if (!self::setting()) {
            try { Database::execute("INSERT INTO site_settings (`key`,`value`,`group`,is_public) VALUES(?,?,'public_content',1)",[self::KEY,self::json(self::reference())]); }
            catch (\PDOException $e) { if (!self::setting()) throw $e; }
        }
        self::setting(true);
    }
    private static function audit(string $action, int $revision, array $before, array $after): void
    {
        // Strict audit: fail the surrounding transaction if this INSERT fails.
        Database::execute('INSERT INTO audit_logs(user_id,module,action,record_id,old_values,new_values,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',[Auth::id(),'public_content',$action,(string)$revision,self::json($before),self::json($after),$_SERVER['REMOTE_ADDR']??'127.0.0.1',mb_substr($_SERVER['HTTP_USER_AGENT']??'CLI',0,255)]);
    }
    public static function saveDraft(string $section, array $input): int
    {
        self::requirePermission($section,'update');
        $value=self::validate($section,$input['value']??null);
        $note=self::text($input['note']??null,1000,true);$source=self::url($input['source_url']??null);
        if (!$source) throw new InvalidArgumentException('กรุณาระบุแหล่งอ้างอิง HTTPS');
        $date=self::date((string)($input['effective_on']??''));
        $expires=empty($input['expires_on'])?null:self::date((string)$input['expires_on']);
        if($expires!==null && $expires<$date)throw new InvalidArgumentException('วันสิ้นสุดต้องไม่ก่อนวันที่มีผล');
        Database::beginTransaction();
        try {
            self::lockBase();$current=self::section($section);
            if (!hash_equals($current['version'],(string)($input['version']??''))) throw new RuntimeException('ข้อมูลเปลี่ยนแล้ว กรุณาโหลดล่าสุดก่อนแก้ไข',409);
            $id=(int)Database::insert("INSERT INTO public_content_revisions(section,payload,previous_payload,base_version,source_url,effective_on,expires_on,note,state,created_by) VALUES(?,?,?,?,?,?,?,?,'draft',?)",[$section,self::json($value),self::json($current['value']),$current['version'],$source,$date,$expires,$note,Auth::id()]);
            self::audit('draft',$id,$current['value'],['section'=>$section,'value'=>$value,'note'=>$note]);Database::commit();return $id;
        } catch (\Throwable $e) { Database::rollBack();throw $e; }
    }
    public static function publish(int $id): void
    {
        $draft=Database::first('SELECT * FROM public_content_revisions WHERE id=?',[$id]);
        if (!$draft) throw new InvalidArgumentException('ไม่พบร่าง');
        self::requirePermission($draft['section'],'publish');
        Database::beginTransaction();
        try {
            self::lockBase();$draft=Database::first('SELECT * FROM public_content_revisions WHERE id=?',[$id]);
            if ($draft['state']!=='draft') throw new RuntimeException('ร่างนี้ดำเนินการแล้ว',409);
            $current=self::section($draft['section']);
            if (!hash_equals($draft['base_version'],$current['version'])) throw new RuntimeException('ข้อมูลเผยแพร่เปลี่ยนแล้ว กรุณาสร้างร่างใหม่จากเวอร์ชันล่าสุด',409);
            $value=self::validate($draft['section'],json_decode($draft['payload'],true,512,JSON_THROW_ON_ERROR));
            Database::execute("UPDATE public_content_revisions SET state='published',published_by=?,published_at=CURRENT_TIMESTAMP WHERE id=? AND state='draft'",[Auth::id(),$id]);
            self::audit('publish',$id,$current['value'],['section'=>$draft['section'],'value'=>$value,'effective_on'=>$draft['effective_on'],'source_url'=>$draft['source_url'],'note'=>$draft['note']]);Database::commit();
        } catch (\Throwable $e) { Database::rollBack();throw $e; }
    }
}
