<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\{Auth,Database,Session};
use App\Exceptions\SecurityGateException;
use InvalidArgumentException;

/** One aggregate lock protects every draft edit and the atomic publication pointer. */
final class DynamicLayoutService
{
    public static function permission(string $action): void {
        if(!Auth::check())throw new SecurityGateException('กรุณาเข้าสู่ระบบและยืนยัน MFA',401,'UNAUTHORIZED');
        if((Auth::user()['role_slug']??'')==='member'||!Auth::hasPermission('layout','view')||!Auth::hasPermission('layout',$action))throw new SecurityGateException('ไม่มีสิทธิ์จัดการโครงสร้างเว็บไซต์',403,'FORBIDDEN');
    }
    public static function json(mixed $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private static function row(bool $lock=false): array {
        $row=Database::first('SELECT * FROM dynamic_layout_state WHERE id=1'.($lock?AdminCrudService::lock():''));
        if(!$row)throw new SecurityGateException('กรุณาติดตั้ง migration 032 ก่อนใช้งาน',503,'LAYOUT_NOT_READY');return $row;
    }
    private static function state(array $row): array {
        $state=json_decode($row['draft_json'],true,512,JSON_THROW_ON_ERROR);
        return ($state['initialized']??true)===false?LayoutSchema::defaults():$state;
    }
    private static function version(array $row,array $input): void {
        if(!isset($input['version'])||!is_scalar($input['version'])||(string)$input['version']!==(string)$row['version'])throw new SecurityGateException('ร่างเปลี่ยนแปลงแล้ว กรุณาโหลดใหม่ก่อนบันทึก',409,'STALE_VERSION');
    }
    public static function draft(): array {
        self::permission('view');$row=self::row();return ['state'=>self::state($row),'version'=>(int)$row['version'],'published_revision_id'=>$row['published_revision_id']?(int)$row['published_revision_id']:null,
            'history'=>Database::query('SELECT id,source_version,rollback_from,created_at FROM dynamic_layout_revisions ORDER BY id DESC LIMIT 30')];
    }
    private static function edit(array $input,string $action,callable $fn): array {
        self::permission('update');return Database::transaction(function()use($input,$action,$fn):array{
            $row=self::row(true);self::version($row,$input);$state=self::state($row);$before=$state;$result=$fn($state);$state['initialized']=true;LayoutSchema::state($state);
            Database::execute('UPDATE dynamic_layout_state SET draft_json=?,version=version+1,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=1',[self::json($state),Auth::id()]);
            AuditService::log('dynamic_layout',$action,'1',['version'=>(int)$row['version'],'state'=>$before],['version'=>(int)$row['version']+1,'state'=>$state,'result'=>$result],required:true);
            return ['version'=>(int)$row['version']+1,'item'=>$result];
        });
    }
    public static function saveDraft(array $input): array {
        return self::edit($input,'save_draft',function(array &$state)use($input):array{
            $nodes=$input['sections']??null;if(!is_array($nodes)||!array_is_list($nodes))throw new InvalidArgumentException('กรุณาส่งรายการ sections');
            $old=array_column($state['sections'],null,'id');$out=[];foreach($nodes as $n){if(!is_array($n)||!isset($old[$n['id']??'']))throw new InvalidArgumentException('เพิ่ม section ผ่านคำสั่ง Add Section ก่อน');$out[]=LayoutSchema::section($n,$old[$n['id']]);}
            if(count($out)!==count($old))throw new InvalidArgumentException('ลบ section ผ่านคำสั่ง Delete โดยตรง');$state['sections']=$out;return ['saved'=>true];
        });
    }
    public static function section(array $input,?string $id=null,string $operation='save'): array {
        return self::edit($input,'section_'.$operation,function(array &$state)use($input,$id,$operation):array{
            $index=$id===null?false:array_search(LayoutSchema::id($id),array_column($state['sections'],'id'),true);
            if($id!==null&&$index===false)throw new SecurityGateException('ไม่พบ section',404,'NOT_FOUND');
            if($operation==='delete'){
                foreach($state['sections'] as $n)if($n['parent_id']===$id)throw new InvalidArgumentException('กรุณาย้ายหรือลบ subsection ก่อน');
                array_splice($state['sections'],$index,1);return ['id'=>$id,'deleted'=>true];
            }
            $node=LayoutSchema::section($input,$index===false?null:$state['sections'][$index]);if($index===false)$state['sections'][]=$node;else $state['sections'][$index]=$node;return $node;
        });
    }
    public static function reorder(array $input): array {
        return self::edit($input,'reorder',function(array &$state)use($input):array{
            $order=$input['ids']??[];if(!is_array($order))throw new InvalidArgumentException('ลำดับไม่ถูกต้อง');foreach($order as $id)LayoutSchema::id($id);$map=array_column($state['sections'],null,'id');
            if(!is_array($order)||!array_is_list($order)||count($order)!==count($map)||count(array_unique($order))!==count($map)||array_diff($order,array_keys($map)))throw new InvalidArgumentException('ต้องส่งลำดับ section ทุก ID เพียงครั้งเดียว');
            $state['sections']=array_map(fn($id)=>$map[$id],$order);return ['ids'=>$order];
        });
    }
    public static function category(array $input,?string $id=null,bool $delete=false): array {
        return self::edit($input,$delete?'category_delete':'category_save',function(array &$state)use($input,$id,$delete):array{
            $index=$id===null?false:array_search(LayoutSchema::id($id),array_column($state['categories'],'id'),true);if($id!==null&&$index===false)throw new SecurityGateException('ไม่พบหมวด',404,'NOT_FOUND');
            if($delete){foreach($state['categories'] as $c)if($c['parent_id']===$id)throw new InvalidArgumentException('หมวดมีหมวดย่อย');
                foreach($state['sections'] as $n)if(($n['config']['category_id']??null)===$id)throw new InvalidArgumentException('หมวดยังถูกใช้ใน section');
                foreach(Database::query('SELECT payload FROM dynamic_gallery_albums') as $a)if(json_decode($a['payload'],true)['category_id']===$id)throw new InvalidArgumentException('หมวดยังมีอัลบั้ม');
                array_splice($state['categories'],$index,1);return ['deleted'=>true];}
            $old=$index===false?[]:$state['categories'][$index];$v=[...$old,...$input];$node=['id'=>$id??bin2hex(random_bytes(16)),'parent_id'=>empty($v['parent_id'])?null:LayoutSchema::id($v['parent_id']),'title'=>LayoutSchema::text($v['title']??'',150,true)];
            if($index===false)$state['categories'][]=$node;else $state['categories'][$index]=$node;return $node;
        });
    }
    private static function albumRow(int $id): array {
        $row=$id>0?Database::first('SELECT * FROM dynamic_gallery_albums WHERE id=?',[$id]):null;if(!$row)throw new SecurityGateException('ไม่พบอัลบั้ม',404,'NOT_FOUND');return $row;
    }
    private static function images(int $id): array {
        return Database::query('SELECT id,alt,caption,sort_order FROM dynamic_gallery_images WHERE album_id=? AND archived=0 ORDER BY sort_order,id',[$id]);
    }
    private static function draftAlbum(array $row): array {return [...json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR),'id'=>(int)$row['id'],'archived'=>(bool)$row['archived'],'images'=>self::images((int)$row['id'])];}
    public static function albums(int $page=1,?string $category=null,bool $preview=false): array {
        self::permission('view');$page=max(1,min(1000000,$page));$offset=($page-1)*20;$where=$preview?'archived=0':'1=1';$params=[];
        if($category!==null){LayoutSchema::id($category);$children=[$category];$categories=self::state(self::row())['categories'];do{$before=count($children);foreach($categories as $c)if(in_array($c['parent_id'],$children,true)&&!in_array($c['id'],$children,true))$children[]=$c['id'];}while(count($children)>$before);
            $column=Database::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql'?"JSON_UNQUOTE(JSON_EXTRACT(payload,'$.category_id'))":"json_extract(payload,'$.category_id')";
            $where.=' AND '.$column.' IN ('.implode(',',array_fill(0,count($children),'?')).')';$params=$children;}
        return ['items'=>array_map([self::class,'draftAlbum'],Database::query("SELECT * FROM dynamic_gallery_albums WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset",$params)),'page'=>$page,'total'=>(int)Database::value("SELECT COUNT(*) FROM dynamic_gallery_albums WHERE $where",$params),'per_page'=>20];
    }
    public static function album(int $id): array {self::permission('view');return self::draftAlbum(self::albumRow($id));}
    public static function saveAlbum(array $input,?int $id=null,bool $archive=false): array {
        return self::edit($input,$archive?'album_archive':'album_save',function(array &$state)use($input,$id,$archive):array{
            if(isset($input['archived'])&&!is_bool($input['archived']))throw new InvalidArgumentException('ค่าการเก็บถาวรต้องเป็น true/false');
            $old=$id===null?null:self::draftAlbum(self::albumRow($id));$payload=LayoutSchema::album($input,$old);
            if($payload['category_id']&&!in_array($payload['category_id'],array_column($state['categories'],'id'),true))throw new InvalidArgumentException('ไม่พบหมวดกิจกรรม');
            if($payload['cover_id']&&!in_array($payload['cover_id'],array_column($old['images']??[],'id'),true))throw new InvalidArgumentException('ภาพปกต้องอยู่ในอัลบั้มนี้');
            $existing=Database::first('SELECT id FROM dynamic_gallery_albums WHERE slug=?',[$payload['slug']]);if($existing&&(int)$existing['id']!==$id)throw new InvalidArgumentException('Slug อัลบั้มซ้ำ');
            if($id===null)$id=(int)Database::insert('INSERT INTO dynamic_gallery_albums(slug,payload,updated_by) VALUES(?,?,?)',[$payload['slug'],self::json($payload),Auth::id()]);
            else Database::execute('UPDATE dynamic_gallery_albums SET slug=?,payload=?,archived=?,version=version+1,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$payload['slug'],self::json($payload),(int)($archive||($input['archived']??$old['archived'])),Auth::id(),$id]);
            return ['id'=>$id,'before'=>$old,'after'=>$payload];
        });
    }
    public static function upload(int $albumId,array $files,array $input): array {
        self::permission('update');$created=[];try{
            return self::edit($input,'images_upload',function(array &$state)use($albumId,$files,&$created):array{
                $album=self::albumRow($albumId);if($album['archived'])throw new InvalidArgumentException('กรุณาคืนค่าอัลบั้มก่อนอัปโหลด');
                if(!array_is_list($files)||!$files||count($files)>12)throw new InvalidArgumentException('อัปโหลดครั้งละ 1–12 ภาพ');$total=0;$ids=[];
                $order=(int)Database::value('SELECT COALESCE(MAX(sort_order),0) FROM dynamic_gallery_images WHERE album_id=?',[$albumId]);
                foreach($files as $file){if(($file['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))throw new InvalidArgumentException('ไฟล์อัปโหลดไม่ถูกต้อง');
                    $size=filesize($file['tmp_name']);if($size===false||$size<1||$size>5*1024*1024)throw new InvalidArgumentException('ภาพต้องไม่เกิน 5 MB');
                    $binary=file_get_contents($file['tmp_name']);$total+=strlen($binary);if($total>20*1024*1024)throw new InvalidArgumentException('ภาพรวมต่อคำขอต้องไม่เกิน 20 MB');
                    $id=bin2hex(random_bytes(16));LayoutImageService::store($id,$binary,$created);$alt=LayoutSchema::text(pathinfo(basename($file['name']),PATHINFO_FILENAME),255,true);
                    Database::insert('INSERT INTO dynamic_gallery_images(id,album_id,alt,sort_order,created_by) VALUES(?,?,?,?,?)',[$id,$albumId,$alt,++$order,Auth::id()]);$ids[]=$id;}
                return ['album_id'=>$albumId,'ids'=>$ids];
            });
        }catch(\Throwable $e){LayoutImageService::cleanup($created);throw $e;}
    }
    public static function image(int $albumId,string $imageId,array $input,bool $remove=false): array {
        return self::edit($input,$remove?'image_archive':'image_update',function(array &$state)use($albumId,$imageId,$input,$remove):array{
            LayoutSchema::id($imageId);$old=Database::first('SELECT * FROM dynamic_gallery_images WHERE id=? AND album_id=? AND archived=0',[$imageId,$albumId]);if(!$old)throw new SecurityGateException('ไม่พบภาพในอัลบั้ม',404,'NOT_FOUND');
            $alt=LayoutSchema::text($input['alt']??$old['alt'],255,true);$caption=LayoutSchema::text($input['caption']??$old['caption'],1000);
            Database::execute('UPDATE dynamic_gallery_images SET alt=?,caption=?,archived=? WHERE id=?',[$alt,$caption,(int)$remove,$imageId]);
            if($remove){$album=self::albumRow($albumId);$p=json_decode($album['payload'],true);if(($p['cover_id']??null)===$imageId){$p['cover_id']=null;Database::execute('UPDATE dynamic_gallery_albums SET payload=? WHERE id=?',[self::json($p),$albumId]);}}
            return ['id'=>$imageId,'before'=>$old,'alt'=>$alt,'caption'=>$caption,'removed'=>$remove];
        });
    }
    public static function orderImages(int $albumId,array $input): array {
        return self::edit($input,'images_reorder',function(array &$state)use($albumId,$input):array{
            self::albumRow($albumId);$ids=$input['ids']??[];if(!is_array($ids))throw new InvalidArgumentException('ลำดับภาพไม่ถูกต้อง');foreach($ids as $imageId)LayoutSchema::id($imageId);$valid=array_column(self::images($albumId),'id');
            if(!is_array($ids)||!array_is_list($ids)||count($ids)!==count($valid)||count(array_unique($ids))!==count($valid)||array_diff($ids,$valid))throw new InvalidArgumentException('ลำดับภาพต้องมีทุกภาพในอัลบั้มเพียงครั้งเดียว');
            foreach($ids as $i=>$id)Database::execute('UPDATE dynamic_gallery_images SET sort_order=? WHERE id=?',[$i,$id]);return ['album_id'=>$albumId,'ids'=>$ids];
        });
    }
    public static function publish(array $input,?int $rollback=null): array {
        self::permission('publish');$scope=$rollback===null?'layout.publish':'layout.rollback';
        if(Session::get('mfa_step_up_scope')!==$scope)throw new SecurityGateException('กรุณายืนยัน TOTP สำหรับคำสั่งนี้',403,'STEP_UP_MFA_REQUIRED');
        DualControlSecurityService::requireFreshStepUpMfa((int)Auth::id(),null,$scope,true);
        return Database::transaction(function()use($input,$rollback):array{
            $row=self::row(true);self::version($row,$input);$state=LayoutSchema::state(self::state($row));
            if($rollback!==null){$revision=Database::first('SELECT state_json FROM dynamic_layout_revisions WHERE id=?',[$rollback]);if(!$revision)throw new SecurityGateException('ไม่พบเวอร์ชันเผยแพร่',404,'NOT_FOUND');$state=json_decode($revision['state_json'],true,512,JSON_THROW_ON_ERROR);}
            $id=(int)Database::insert('INSERT INTO dynamic_layout_revisions(state_json,source_version,rollback_from,created_by) VALUES(?,?,?,?)',[self::json($state),$row['version'],$rollback,Auth::id()]);
            if($rollback!==null)Database::execute('INSERT INTO dynamic_gallery_publications(revision_id,album_id,slug,category_id,payload) SELECT ?,album_id,slug,category_id,payload FROM dynamic_gallery_publications WHERE revision_id=?',[$id,$rollback]);
            else foreach(Database::query('SELECT * FROM dynamic_gallery_albums WHERE archived=0 ORDER BY id') as $a){$album=self::draftAlbum($a);Database::insert('INSERT INTO dynamic_gallery_publications(revision_id,album_id,slug,category_id,payload) VALUES(?,?,?,?,?)',[$id,$a['id'],$a['slug'],$album['category_id'],self::json($album)]);}
            if($rollback!==null)Database::execute('INSERT INTO dynamic_gallery_published_images(revision_id,image_id) SELECT ?,image_id FROM dynamic_gallery_published_images WHERE revision_id=?',[$id,$rollback]);
            else Database::execute('INSERT INTO dynamic_gallery_published_images(revision_id,image_id) SELECT ?,i.id FROM dynamic_gallery_images i JOIN dynamic_gallery_albums a ON a.id=i.album_id WHERE i.archived=0 AND a.archived=0',[$id]);
            Database::execute('UPDATE dynamic_layout_state SET published_revision_id=?,version=version+1,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=1',[$id,Auth::id()]);
            AuditService::log('dynamic_layout',$rollback===null?'publish':'rollback','1',['published_revision_id'=>$row['published_revision_id']],['revision_id'=>$id,'rollback_from'=>$rollback,'source_version'=>$row['version']],required:true);
            return ['revision_id'=>$id,'version'=>(int)$row['version']+1];
        });
    }
    public static function published(): ?array {
        // No initializer, scheduler or fallback write is permitted on public reads.
        if(!Database::hasTable('dynamic_layout_state'))return null;
        $row=Database::first('SELECT r.id,r.state_json FROM dynamic_layout_state s JOIN dynamic_layout_revisions r ON r.id=s.published_revision_id WHERE s.id=1');
        return $row?['revision_id'=>(int)$row['id'],'state'=>self::publicState(json_decode($row['state_json'],true,512,JSON_THROW_ON_ERROR))]:null;
    }
    private static function publicState(array $state): array {
        $nodes=array_column($state['sections'],null,'id');$allowed=[];
        foreach($nodes as $node){$n=$node;$visible=true;while($n){if(!$n['visible']||$n['archived']){$visible=false;break;}$n=$n['parent_id']?($nodes[$n['parent_id']]??null):null;}if($visible)$allowed[]=$node;}
        return ['sections'=>$allowed,'categories'=>$state['categories']];
    }
    public static function publicAlbums(int $page=1,?string $category=null,?string $slug=null): array {
        $pub=self::published();if(!$pub)return ['items'=>[],'total'=>0,'page'=>1,'per_page'=>20,'revision_id'=>null];
        $where='revision_id=?';$params=[$pub['revision_id']];if($category!==null){LayoutSchema::id($category);$children=[$category];do{$before=count($children);foreach($pub['state']['categories'] as $c)if(in_array($c['parent_id'],$children,true)&&!in_array($c['id'],$children,true))$children[]=$c['id'];}while(count($children)>$before);
            $where.=' AND category_id IN ('.implode(',',array_fill(0,count($children),'?')).')';$params=[...$params,...$children];}
        if($slug!==null){$where.=' AND slug=?';$params[]=LayoutSchema::slug($slug);}$page=max(1,min(1000000,$page));$offset=($page-1)*20;
        $items=array_map(function($a)use($slug,$pub){$album=json_decode($a['payload'],true,512,JSON_THROW_ON_ERROR);$album['image_count']=count($album['images']);if($slug===null){$cover=null;foreach($album['images'] as $image)if($image['id']===$album['cover_id']){$cover=$image;break;}$album['images']=$cover?[$cover]:array_slice($album['images'],0,1);}return self::imageUrls($album,$pub['revision_id']);},Database::query("SELECT payload FROM dynamic_gallery_publications WHERE $where ORDER BY album_id DESC LIMIT 20 OFFSET $offset",$params));
        return ['items'=>$items,'total'=>(int)Database::value("SELECT COUNT(*) FROM dynamic_gallery_publications WHERE $where",$params),'page'=>$page,'per_page'=>20,'revision_id'=>$pub['revision_id']];
    }
    public static function imageUrls(array $album,?int $revision=null): array {
        foreach($album['images'] as &$image){$prefix=$revision===null?'/api/admin/v1/layout/images/':'/api/public/v1/layout/images/'.$revision.'/';$image['src']=$prefix.$image['id'].'/full';$image['thumbnail']=$prefix.$image['id'].'/thumb';}return $album;
    }
    public static function imageAllowed(string $id,?int $revision=null): void {
        LayoutSchema::id($id);if($revision===null){self::permission('view');if(!Database::first('SELECT id FROM dynamic_gallery_images WHERE id=?',[$id]))throw new SecurityGateException('ไม่พบภาพ',404,'NOT_FOUND');return;}
        // An immutable published album, not a mutable draft row, authorizes a file read.
        if(Database::first('SELECT image_id FROM dynamic_gallery_published_images WHERE revision_id=? AND image_id=?',[$revision,$id]))return;
        throw new SecurityGateException('ไม่พบภาพเผยแพร่',404,'NOT_FOUND');
    }
}
