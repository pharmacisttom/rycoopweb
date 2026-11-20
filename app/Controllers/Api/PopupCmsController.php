<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Services\AuditService;
use Throwable;

/**
 * Central popup campaign API. All writes are routed through existing
 * AuthMiddleware + super_admin role + CsrfMiddleware.
 */
final class PopupCmsController extends Controller
{
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
                $row['desktop_image'] = resolve_media_url($row['desktop_image'] ?? null);
                $row['button_url'] = self::safePath($row['button_url'] ?? '');
            }
            $this->json(['success' => true, 'data' => $row]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'ไม่สามารถโหลดประกาศได้'], 503);
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
            }
            unset($row);
            $this->json(['success' => true, 'data' => $rows]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'message' => 'โหลดรายการ Popup ไม่สำเร็จ'], 503);
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
            $this->json(['success'=>false,'message'=>'ไม่สามารถบันทึก Popup ได้'],503);
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
            $this->json(['success'=>false,'message'=>'ไม่สามารถแก้ไข Popup ได้'],503);
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
            $this->json(['success'=>false,'message'=>'ไม่สามารถปิด Popup ได้'],503);
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
            $this->json(['success'=>false,'message'=>'ไม่สามารถลบ Popup ได้'],503);
        }
    }

}
