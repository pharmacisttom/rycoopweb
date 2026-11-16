<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AuditService;
use App\Services\AdminCrudService;
use App\Exceptions\SecurityGateException;

final class ActivityGalleryController extends Controller
{
    private static function authorize(string $action): void
    {
        if (!Auth::check()) throw new SecurityGateException('MFA session required',401,'UNAUTHORIZED');
        if ((Auth::user()['role_slug'] ?? '') === 'member'
            || !Auth::hasPermission('news','view')
            || !Auth::hasPermission('news',$action)) {
            throw new SecurityGateException('Permission denied',403,'FORBIDDEN');
        }
    }

    private static function id(string $id): int
    {
        if (!preg_match('/^[1-9][0-9]*$/D',$id)) throw new \InvalidArgumentException('Invalid ID');
        return (int)$id;
    }

    private static function album(int $id, bool $lock = false): array
    {
        $row=Database::first('SELECT * FROM activity_albums WHERE id=? AND deleted_at IS NULL'
            .($lock?AdminCrudService::lock():''),[$id]);
        if (!$row) throw new SecurityGateException('Album not found',404,'NOT_FOUND');
        return $row;
    }

    private static function photos(int $id): array
    {
        return array_map(static function(array $photo) use ($id):array {
            $photo['url']="/api/public/activities/$id/photos/{$photo['id']}";
            return $photo;
        },Database::query('SELECT id,album_id,caption,sort_order FROM activity_photos WHERE album_id=? ORDER BY sort_order,id',[$id]));
    }

    public function publicAlbums(): void
    {
        AdminCrudService::respond($this->response,static function():array {
            $rows=Database::query("SELECT a.id,a.title,a.description,a.event_date,a.location,
                (SELECT COUNT(*) FROM activity_photos p WHERE p.album_id=a.id) AS photo_count,
                (SELECT p.id FROM activity_photos p WHERE p.album_id=a.id ORDER BY (p.id=a.cover_photo_id) DESC,p.sort_order,p.id LIMIT 1) AS cover_id
                FROM activity_albums a WHERE a.status='published' AND a.deleted_at IS NULL
                ORDER BY a.event_date DESC,a.id DESC LIMIT 100");
            foreach($rows as &$row) $row['cover_url']=empty($row['cover_id'])?null:"/api/public/activities/{$row['id']}/photos/{$row['cover_id']}";
            return ['data'=>$rows];
        });
    }

    public function publicAlbum(string $id): void
    {
        AdminCrudService::respond($this->response,static function() use ($id):array {
            $album=self::album(self::id($id));
            if ($album['status']!=='published') throw new SecurityGateException('Album not found',404,'NOT_FOUND');
            return ['data'=>['album'=>[
                'id'=>$album['id'],'title'=>$album['title'],'description'=>$album['description'],
                'event_date'=>$album['event_date'],'location'=>$album['location']],
                'photos'=>self::photos((int)$album['id'])]];
        });
    }

    public function adminAlbums(): void
    {
        AdminCrudService::respond($this->response,static function():array {
            self::authorize('view');
            return ['data'=>Database::query("SELECT a.*, (SELECT COUNT(*) FROM activity_photos p WHERE p.album_id=a.id) AS photo_count
             FROM activity_albums a WHERE deleted_at IS NULL ORDER BY a.id DESC LIMIT 200")];
        });
    }

    public function adminAlbum(string $id): void
    {
        AdminCrudService::respond($this->response,static function() use ($id):array {
            self::authorize('view');$album=self::album(self::id($id));
            return ['data'=>['album'=>$album,'photos'=>self::photos((int)$album['id'])]];
        });
    }

    public function create(): void
    {
        AdminCrudService::respond($this->response,function():array {
            self::authorize('update');
            $title=trim((string)$this->request->input('title',''));
            if ($title===''||mb_strlen($title)>255)throw new \InvalidArgumentException('Title is required (maximum 255)');
            $description=trim((string)$this->request->input('description',''));
            if(mb_strlen($description)>10000)throw new \InvalidArgumentException('Description too long');
            $date=(string)$this->request->input('event_date','');
            if($date!=='') {
                $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Invalid event date');
            }
            $location=trim((string)$this->request->input('location',''));
            if(mb_strlen($location)>255)throw new \InvalidArgumentException('Location too long');
            return Database::transaction(static function()use($title,$description,$date,$location):array {
                $id=(int)Database::insert("INSERT INTO activity_albums (title,description,event_date,location,created_by,status,created_at,updated_at) VALUES (?,?,?,?,?,'draft',NOW(),NOW())",
                  [$title,$description,$date?:null,$location,Auth::id()]);
                AuditService::log('activity_albums','create',(string)$id,null,['title'=>$title,'status'=>'draft'],required:true);
                return ['id'=>$id,'status'=>'draft'];
            });
        },201);
    }

    public function update(string $id): void
    {
        AdminCrudService::respond($this->response,function() use($id):array {
            self::authorize('update');$albumId=self::id($id);
            return Database::transaction(function() use($albumId):array {
                $old=self::album($albumId,true);
                if($old['status']!=='draft')throw new SecurityGateException('Only drafts can be edited',409,'NOT_DRAFT');
                $title=trim((string)$this->request->input('title',$old['title']));
                $description=trim((string)$this->request->input('description',$old['description']??''));
                $location=trim((string)$this->request->input('location',$old['location']??''));
                $date=(string)$this->request->input('event_date',$old['event_date']??'');
                if($title===''||mb_strlen($title)>255||mb_strlen($description)>10000||mb_strlen($location)>255)throw new \InvalidArgumentException('Invalid album information');
                if($date!==''){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Invalid date');}
                Database::execute('UPDATE activity_albums SET title=?,description=?,location=?,event_date=?,updated_at=NOW() WHERE id=?',[$title,$description,$location,$date?:null,$albumId]);
                AuditService::log('activity_albums','update',(string)$albumId,$old,['title'=>$title,'status'=>'draft'],required:true);
                return ['id'=>$albumId,'status'=>'draft'];
            });
        });
    }

    private static function photoRoot(): string
    {
        // Stored outside storage/uploads and public URL aliases. Only this controller streams files.
        return dirname(storage_upload_path()) . '/private-activity-photos';
    }

    public function uploadPhoto(string $id): void
    {
        AdminCrudService::respond($this->response,function()use($id):array {
            self::authorize('update');$albumId=self::id($id);
            // Single image per request supports queue/retry and bounded client concurrency.
            $file=$this->request->file('file');
            if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Select one valid image');
            if(!is_uploaded_file((string)($file['tmp_name']??'')))throw new \InvalidArgumentException('Invalid upload');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true))throw new \InvalidArgumentException('Images only');
            // No publication until explicit publish action.
            self::album($albumId);
            $uploaded=null;
            try {
                return Database::transaction(static function()use($albumId,$file,&$uploaded):array {
                    $album=self::album($albumId,true);
                    if($album['status']!=='draft')throw new SecurityGateException('Only draft albums accept uploads',409,'NOT_DRAFT');
                    $ext=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION));
                    $extensions=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif'];
                    $mime=(new \\finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                    if(!isset($extensions[$ext])||$extensions[$ext]!==$mime||($file['size']??0)>20*1024*1024||@getimagesize($file['tmp_name'])===false) {
                        throw new \\InvalidArgumentException('Unsupported image or image exceeds 20MB');
                    }
                    $root=self::photoRoot();
                    if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root))throw new \\RuntimeException('Photo storage unavailable');
                    $filename=bin2hex(random_bytes(16)).'.'.$ext;
                    $target=$root.'/'.$filename;
                    if(!move_uploaded_file($file['tmp_name'],$target))throw new \\RuntimeException('Unable to save image');
                    @chmod($target,0600);
                    $uploaded=['path'=>$target];
                    $position=(int)Database::value('SELECT COALESCE(MAX(sort_order),0)+1 FROM activity_photos WHERE album_id=?',[$albumId]);
                    $photoId=(int)Database::insert('INSERT INTO activity_photos (album_id,media_id,path,sort_order,created_at) VALUES (?,NULL,?,?,NOW())',
                      [$albumId,$filename,$position]);
                    AuditService::log('activity_albums','upload_photo',(string)$albumId,null,['photo_id'=>$photoId],required:true);
                    return ['photo'=>['id'=>$photoId,'url'=>"/api/admin/activities/$albumId/photos/$photoId",'sort_order'=>$position]];
                });
            } catch(\Throwable $e) {
                if($uploaded) @unlink($uploaded['path']);
                throw $e;
            }
        },201);
    }

    public function publish(string $id): void
    {
        AdminCrudService::respond($this->response,static function()use($id):array {
            self::authorize('publish');$albumId=self::id($id);
            return Database::transaction(static function()use($albumId):array {
                $old=self::album($albumId,true);
                if($old['status']!=='draft')throw new SecurityGateException('Album is not a draft',409,'NOT_DRAFT');
                if((int)Database::value('SELECT COUNT(*) FROM activity_photos WHERE album_id=?',[$albumId])<1)
                    throw new \InvalidArgumentException('Upload at least one image before publication');
                Database::execute("UPDATE activity_albums SET status='published',published_by=?,published_at=NOW(),updated_at=NOW() WHERE id=?",[Auth::id(),$albumId]);
                AuditService::log('activity_albums','publish',(string)$albumId,$old,['status'=>'published'],required:true);
                return ['id'=>$albumId,'status'=>'published'];
            });
        });
    }

    public function cover(string $id,string $photo):void
    {
        AdminCrudService::respond($this->response,static function()use($id,$photo):array {
            self::authorize('update');$albumId=self::id($id);$photoId=self::id($photo);
            return Database::transaction(static function()use($albumId,$photoId):array {
                $album=self::album($albumId,true);
                if($album['status']!=='draft')throw new SecurityGateException('Draft required',409,'NOT_DRAFT');
                if(!Database::first('SELECT id FROM activity_photos WHERE id=? AND album_id=?',[$photoId,$albumId]))
                    throw new SecurityGateException('Photo not found',404,'NOT_FOUND');
                Database::execute('UPDATE activity_albums SET cover_photo_id=?,updated_at=NOW() WHERE id=?',[$photoId,$albumId]);
                AuditService::log('activity_albums','cover',(string)$albumId,null,['photo_id'=>$photoId],required:true);
                return ['cover_photo_id'=>$photoId];
            });
        });
    }

    public function caption(string $id,string $photo):void
    {
        AdminCrudService::respond($this->response,function()use($id,$photo):array {
            self::authorize('update');$albumId=self::id($id);$photoId=self::id($photo);
            $caption=trim((string)$this->request->input('caption',''));
            if(mb_strlen($caption)>500)throw new \\InvalidArgumentException('Caption too long');
            return Database::transaction(static function()use($albumId,$photoId,$caption):array {
                if(self::album($albumId,true)['status']!=='draft')throw new SecurityGateException('Draft required',409,'NOT_DRAFT');
                if(!Database::first('SELECT id FROM activity_photos WHERE id=? AND album_id=?',[$photoId,$albumId]))
                    throw new SecurityGateException('Photo not found',404,'NOT_FOUND');
                Database::execute('UPDATE activity_photos SET caption=? WHERE id=?',[$caption,$photoId]);
                AuditService::log('activity_albums','caption',(string)$albumId,null,['photo_id'=>$photoId],required:true);
                return ['photo_id'=>$photoId];
            });
        });
    }

    public function removePhoto(string $id,string $photo):void
    {
        AdminCrudService::respond($this->response,static function()use($id,$photo):array {
            self::authorize('update');$albumId=self::id($id);$photoId=self::id($photo);
            $path=Database::transaction(static function()use($albumId,$photoId):string {
                if(self::album($albumId,true)['status']!=='draft')throw new SecurityGateException('Draft required',409,'NOT_DRAFT');
                $row=Database::first('SELECT path FROM activity_photos WHERE id=? AND album_id=?',[$photoId,$albumId]);
                if(!$row)throw new SecurityGateException('Photo not found',404,'NOT_FOUND');
                Database::execute('DELETE FROM activity_photos WHERE id=? AND album_id=?',[$photoId,$albumId]);
                Database::execute('UPDATE activity_albums SET cover_photo_id=NULL WHERE id=? AND cover_photo_id=?',[$albumId,$photoId]);
                AuditService::log('activity_albums','remove_photo',(string)$albumId,null,['photo_id'=>$photoId],required:true);
                return (string)$row['path'];
            });
            // Delete private file only after committed DB deletion.
            $filename=basename($path);
            if($filename===$path) @unlink(self::photoRoot().'/'.$filename);
            return ['deleted'=>true];
        });
    }

    public function reorder(string $id):void
    {
        AdminCrudService::respond($this->response,function()use($id):array {
            self::authorize('update');$albumId=self::id($id);
            $ids=$this->request->input('ids',[]);
            if(!is_array($ids)||count($ids)>1000)throw new \\InvalidArgumentException('Invalid photo order');
            return Database::transaction(static function()use($albumId,$ids):array {
                if(self::album($albumId,true)['status']!=='draft')throw new SecurityGateException('Draft required',409,'NOT_DRAFT');
                $original=array_map('intval',array_column(Database::query('SELECT id FROM activity_photos WHERE album_id=? ORDER BY id',[$albumId]),'id'));
                $incoming=[];
                foreach($ids as $v){if(!is_scalar($v)||!preg_match('/^[1-9][0-9]*$/D',(string)$v))throw new \\InvalidArgumentException('Invalid photo ID');$incoming[]=(int)$v;}
                sort($incoming);
                if($incoming!==$original)throw new SecurityGateException('Photo list has changed; reload',409,'STALE_PHOTOS');
                foreach($ids as $i=>$photoId)Database::execute('UPDATE activity_photos SET sort_order=? WHERE id=? AND album_id=?',[$i+1,(int)$photoId,$albumId]);
                AuditService::log('activity_albums','reorder',(string)$albumId,null,['count'=>count($ids)],required:true);
                return ['reordered'=>count($ids)];
            });
        });
    }

    public function archive(string $id):void
    {
        AdminCrudService::respond($this->response,static function()use($id):array {
            self::authorize('publish');$albumId=self::id($id);
            return Database::transaction(static function()use($albumId):array {
                $old=self::album($albumId,true);
                Database::execute('UPDATE activity_albums SET deleted_at=NOW(),updated_at=NOW() WHERE id=?',[$albumId]);
                AuditService::log('activity_albums','archive',(string)$albumId,$old,['deleted'=>true],required:true);
                return ['archived'=>true];
            });
        });
    }

    public function photo(string $album, string $photo): void { $this->streamPhoto($album,$photo,false); }
    public function adminPhoto(string $album,string $photo): void { $this->streamPhoto($album,$photo,true); }

    private function streamPhoto(string $album,string $photo,bool $admin):void
    {
        if(!preg_match('/^[1-9][0-9]*$/D',$album)||!preg_match('/^[1-9][0-9]*$/D',$photo)) {http_response_code(404);return;}
        if($admin && (!Auth::check()||!Auth::hasPermission('news','view')||(Auth::user()['role_slug']??'')==='member')) {http_response_code(403);return;}
        $sql='SELECT p.path,a.status FROM activity_photos p JOIN activity_albums a ON a.id=p.album_id WHERE p.id=? AND p.album_id=? AND a.deleted_at IS NULL';
        $row=Database::first($sql,[(int)$photo,(int)$album]);
        if(!$row||(!$admin&&$row['status']!=='published')) {http_response_code(404);return;}
        $base=realpath(self::photoRoot());
        $path=realpath(self::photoRoot().'/'.$row['path']);
        if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)||!is_readable($path)){http_response_code(404);return;}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)||@getimagesize($path)===false){http_response_code(415);return;}
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: '.($admin?'no-store, private':'public, max-age=3600'));
        header('Content-Length: '.filesize($path));
        if(strtoupper($_SERVER['REQUEST_METHOD']??'GET')!=='HEAD')readfile($path);
    }
}
