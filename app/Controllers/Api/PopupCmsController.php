<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Core\Logger;
use App\Services\AuditService;
use Throwable;

/**
 * Central popup campaign API. All writes are routed through existing
 * AuthMiddleware + super_admin role + CsrfMiddleware.
 */
final class PopupCmsController extends Controller
{
    private function fail(string $action, Throwable $e, string $publicMessage): void
    {
        // Server-side diagnostic only: never reveal SQL, stack traces or secrets to clients.
        $ref = bin2hex(random_bytes(6));
        Logger::error('[POPUP_CMS]['.$action.']['.$ref.'] '.get_class($e).': '.$e->getMessage());
        $this->json(['success'=>false,'message'=>$publicMessage,'reference'=>$ref],503);
    }

    private const SELECT_FIELDS = 'id,title,content,button_text,button_url,desktop_image,priority,status,start_at,created_at';

    public function published(): void
    {
        try {
            $rows = Database::query(
                "SELECT id,title,content,button_text,button_url,desktop_image
                 FROM popups WHERE deleted_at IS NULL AND status='active'
                 AND (start_at IS NULL OR start_at <= NOW())
                 ORDER BY FIELD(priority,'critical','high','normal','low'), created_at DESC LIMIT 1"
            );
            $row = $rows[0] ?? null;
            if ($row) {
                $imagePath = resolve_media_url($row['desktop_image'] ?? null);
                $row['image_url'] = $imagePath && preg_match('#^/storage/uploads/[a-z0-9_-]+/[a-zA-Z0-9_-]+\\.webp$#D', $imagePath)
                    ? '/api/public/popup-campaign/image' : null;
                unset($row['desktop_image']);
                $row['button_url'] = self::safePath($row['button_url'] ?? '');
            }
            $this->json(['success' => true, 'data' => $row]);
        } catch (Throwable $e) {
            $this->fail('published',$e,'ไม่สามารถโหลดประกาศได้');
        }
    }

    public function image(): void
    {
        // Resolve only the currently published campaign. Never accept a client
        // supplied filesystem path, media ID, or arbitrary storage key.
        try {
            $rows = Database::query(
                "SELECT desktop_image FROM popups
                 WHERE deleted_at IS NULL AND status='active'
                 AND (start_at IS NULL OR start_at <= NOW())
                 ORDER BY FIELD(priority,'critical','high','normal','low'), created_at DESC LIMIT 1"
            );
            $url = resolve_media_url($rows[0]['desktop_image'] ?? null);
            if (!$url || !preg_match('#^/storage/uploads/([a-z0-9_-]+/[a-zA-Z0-9_-]+\\.webp)$#D', $url, $matches)) {
                $this->json(['success'=>false,'message'=>'ไม่พบภาพที่เผยแพร่'],404);
                return;
            }
            $path = storage_upload_path($matches[1]);
            if (!is_file($path) || filesize($path) < 1 || filesize($path) > 10*1024*1024) {
                $this->json(['success'=>false,'message'=>'ไม่พบไฟล์ภาพ'],404);
                return;
            }
            header('Content-Type: image/webp');
            header('Content-Length: '.filesize($path));
            header('Cache-Control: public, max-age=60');
            header('X-Content-Type-Options: nosniff');
            readfile($path);
            exit;
        } catch (Throwable $e) {
            $this->fail('image',$e,'ไม่สามารถโหลดภาพได้');
        }
    }

    public function index(): void
    {
        try {
            $rows = Database::query(
                'SELECT '.self::SELECT_FIELDS.' FROM popups WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 100'
            );
            foreach ($rows as &$row) {
                $row['desktop_image'] = resolve_media_url($row['desktop_image'] ?? null);
                $row['preview_url'] = null;
                if ($row['desktop_image'] && preg_match(
                    '#^/storage/uploads/([a-z0-9_-]+/[a-zA-Z0-9_-]+\\.webp)$#D',
                    $row['desktop_image'],
                    $match
                )) {
                    // Private CMS preview: reuse the existing authenticated Media API.
                    $media = Database::first(
                        'SELECT id FROM media WHERE path=? AND mime_type=? AND deleted_at IS NULL LIMIT 1',
                        [$match[1], 'image/webp']
                    );
                    if ($media) $row['preview_url'] = '/api/admin/media/'.(int)$media['id'].'/image';
                }
            }
            unset($row);
            $this->json(['success' => true, 'data' => $rows]);
        } catch (Throwable $e) {
            $this->fail('index',$e,'โหลดรายการ Popup ไม่สำเร็จ');
        }
    }

    private static function safePath(mixed $value): ?string
    {
        $v = trim((string)$value);
        return $v !== '' && strlen($v) <= 300
            && preg_match('#^/(?!/)[a-zA-Z0-9/_?=&%\#.-]*$#D', $v) ? $v : null;
    }

    private function input(): array
    {
        $title = trim((string)$this->request->input('title', ''));
        $content = trim((string)$this->request->input('content', ''));
        $buttonText = trim((string)$this->request->input('button_text', ''));
        $urlRaw = trim((string)$this->request->input('button_url', ''));
        $image = trim((string)$this->request->input('desktop_image', ''));
        $priority = (string)$this->request->input('priority', 'normal');
        $status = (string)$this->request->input('status', 'inactive');

        if ($title === '' || mb_strlen($title) > 200 ||
            mb_strlen($content) > 4000 || mb_strlen($buttonText) > 100) {
            throw new \InvalidArgumentException('ชื่อประกาศหรือเนื้อหาไม่ถูกต้อง');
        }
        if (!in_array($priority, ['critical','high','normal','low'], true) ||
            !in_array($status, ['active','inactive'], true)) {
            throw new \InvalidArgumentException('สถานะแคมเปญไม่ถูกต้อง');
        }
        if ($urlRaw !== '' && self::safePath($urlRaw) === null) {
            throw new \InvalidArgumentException('ลิงก์ปุ่มต้องเป็น Path ภายในเว็บไซต์เท่านั้น');
        }
        if ($image !== '' && (
            strlen($image) > 400 ||
            !preg_match('#^/storage/uploads/[a-zA-Z0-9/_-]+\.(?:webp|png|jpe?g)$#iD', $image)
        )) {
            throw new \InvalidArgumentException('ภาพต้องมาจาก Media Library ที่อนุญาต');
        }
        return [$title,$content,$buttonText,$urlRaw,$image,$priority,$status];
    }

    public function store(): void
    {
        try {
            [$title,$content,$buttonText,$url,$image,$priority,$status] = $this->input();
            $id = Database::transaction(static function () use ($title,$content,$buttonText,$url,$image,$priority,$status) {
                $id = Database::insert(
                    "INSERT INTO popups
                     (title,type,content,button_text,button_url,desktop_image,display_mode,delay_seconds,frequency,priority,status,start_at,created_by,created_at)
                     VALUES (?,'image_text',?,?,?,?,'load',0,'session',?,?,NOW(),?,NOW())",
                    [$title,$content,$buttonText,$url,$image ?: null,$priority,$status,Auth::id()]
                );
                AuditService::log('popups','create',(string)$id,null,['title'=>$title,'status'=>$status],true);
                return $id;
            });
            $this->json(['success'=>true,'message'=>'บันทึก Popup แล้ว','id'=>$id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch (Throwable $e) {
            $this->fail('store',$e,'ไม่สามารถบันทึก Popup ได้');
        }
    }

    public function update(string $id): void
    {
        try {
            if (!ctype_digit($id) || (int)$id < 1) throw new \InvalidArgumentException('รหัสรายการไม่ถูกต้อง');
            $old = Database::first('SELECT '.self::SELECT_FIELDS.' FROM popups WHERE id=? AND deleted_at IS NULL',[(int)$id]);
            if (!$old) { $this->json(['success'=>false,'message'=>'ไม่พบรายการ'],404); return; }
            [$title,$content,$buttonText,$url,$image,$priority,$status] = $this->input();
            Database::transaction(static function () use ($title,$content,$buttonText,$url,$image,$priority,$status,$id,$old) {
                Database::execute(
                    'UPDATE popups SET title=?,content=?,button_text=?,button_url=?,desktop_image=?,priority=?,status=?,updated_by=? WHERE id=? AND deleted_at IS NULL',
                    [$title,$content,$buttonText,$url,$image ?: null,$priority,$status,Auth::id(),(int)$id]
                );
                AuditService::log('popups','update',$id,['title'=>$old['title'],'status'=>$old['status']],['title'=>$title,'status'=>$status],true);
            });
            $this->json(['success'=>true,'message'=>'แก้ไข Popup แล้ว']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch (Throwable $e) {
            $this->fail('update',$e,'ไม่สามารถแก้ไข Popup ได้');
        }
    }

    public function disable(string $id): void
    {
        try {
            if (!ctype_digit($id) || (int)$id < 1) throw new \InvalidArgumentException('รหัสรายการไม่ถูกต้อง');
            $old = Database::first('SELECT id,title,status FROM popups WHERE id=? AND deleted_at IS NULL',[(int)$id]);
            if (!$old) { $this->json(['success'=>false,'message'=>'ไม่พบรายการ'],404); return; }
            Database::transaction(static function () use ($id,$old) {
                Database::execute("UPDATE popups SET status='inactive', updated_by=? WHERE id=? AND deleted_at IS NULL",[Auth::id(),(int)$id]);
                AuditService::log('popups','disable',$id,$old,['status'=>'inactive'],true);
            });
            $this->json(['success'=>true,'message'=>'ปิดการแสดง Popup แล้ว']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch (Throwable $e) {
            $this->fail('disable',$e,'ไม่สามารถปิด Popup ได้');
        }
    }
    public function destroy(string $id): void
    {
        try {
            if (!ctype_digit($id) || (int)$id < 1) throw new \InvalidArgumentException('รหัสรายการไม่ถูกต้อง');
            $old = Database::first('SELECT id,title,status FROM popups WHERE id=? AND deleted_at IS NULL',[(int)$id]);
            if (!$old) { $this->json(['success'=>false,'message'=>'ไม่พบรายการ'],404); return; }
            Database::transaction(static function () use ($id,$old) {
                Database::execute('UPDATE popups SET deleted_at=NOW(), updated_by=? WHERE id=? AND deleted_at IS NULL',[Auth::id(),(int)$id]);
                AuditService::log('popups','delete',$id,$old,['deleted'=>true],true);
            });
            $this->json(['success'=>true,'message'=>'ลบ Popup แล้ว']);
        } catch (\InvalidArgumentException $e) {
            $this->json(['success'=>false,'message'=>$e->getMessage()],422);
        } catch (Throwable $e) {
            $this->fail('delete',$e,'ไม่สามารถลบ Popup ได้');
        }
    }

}
