<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\MediaService;
use Throwable;

class PopupController extends Controller
{
    public const MAX_ALLOWED_POPUPS = 3;

    /**
     * REST API: GET /api/admin/popups
     */
    public function apiIndex(): void
    {
        $popups = Database::query(
            "SELECT id, title, content AS subtitle, desktop_image, mascot_image, layout_mode, 
                    button_text, button_url, button_target, priority, priority_order, 
                    start_at, end_at, status, impressions_count, clicks_count, created_at, updated_at 
             FROM popups 
             WHERE deleted_at IS NULL 
             ORDER BY priority_order ASC, id ASC"
        );

        $popups = array_map(function (array $item): array {
            $item['banner_image'] = resolve_media_url($item['desktop_image'] ?? null);
            $item['mascot_image'] = resolve_media_url($item['mascot_image'] ?? null);
            if (empty($item['mascot_image']) && in_array($item['layout_mode'], ['mascot_only', 'banner_and_mascot'], true)) {
                $item['mascot_image'] = '/assets/img/mascot-aomsuk.svg';
            }
            $item['version'] = \App\Services\AccessService::version($item);
            return $item;
        }, $popups);

        $this->response->json([
            'success' => true,
            'data' => [
                'items' => $popups,
                'count' => count($popups),
                'max_limit' => self::MAX_ALLOWED_POPUPS,
                'can_create' => count($popups) < self::MAX_ALLOWED_POPUPS,
            ]
        ]);
    }

    /**
     * REST API: POST /api/admin/popups
     */
    public function apiStore(): void
    {
        $title = trim((string)$this->request->input('title'));
        if ($title === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกหัวข้อป็อปอัป'], 422);
            return;
        }

        Database::beginTransaction();
        try {
            // Strict enforcement of max 3 popups with pessimistic lock
            $count = (int)Database::value("SELECT COUNT(*) FROM popups WHERE deleted_at IS NULL FOR UPDATE");
            if ($count >= self::MAX_ALLOWED_POPUPS) {
                Database::rollBack();
                $this->response->json([
                    'success' => false,
                    'code' => 'MAX_POPUPS_REACHED',
                    'message' => 'ระบบอนุญาตให้สร้างป็อปอัปได้สูงสุดไม่เกิน 3 รายการ'
                ], 422);
                return;
            }

            // Duplicate title check
            $dup = Database::first("SELECT id FROM popups WHERE title = ? AND deleted_at IS NULL", [$title]);
            if ($dup) {
                Database::rollBack();
                $this->response->json(['success' => false, 'message' => 'มีป็อปอัปชื่อนี้อยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น'], 422);
                return;
            }

            // Handle Banner image upload or string
            $desktopImage = trim((string)$this->request->input('banner_image', $this->request->input('desktop_image', 'popup_dividend.jpg')));
            if ($this->request->hasFile('banner_file') || $this->request->hasFile('file')) {
                $file = $this->request->file('banner_file') ?? $this->request->file('file');
                if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
                    $upload = MediaService::upload($file, 'popups', $title . ' - banner');
                    $desktopImage = $upload['path'];
                }
            }

            // Handle Mascot image upload or default
            $mascotImage = trim((string)$this->request->input('mascot_image', ''));
            if ($this->request->hasFile('mascot_file')) {
                $mfile = $this->request->file('mascot_file');
                if (!empty($mfile['name']) && $mfile['error'] === UPLOAD_ERR_OK) {
                    $mupload = MediaService::upload($mfile, 'popups', $title . ' - mascot');
                    $mascotImage = $mupload['path'];
                }
            }

            $layoutMode = (string)$this->request->input('layout_mode', 'banner_only');
            if (!in_array($layoutMode, ['banner_only', 'mascot_only', 'banner_and_mascot'], true)) {
                $layoutMode = 'banner_only';
            }

            $priorityOrder = (int)$this->request->input('priority_order', 1);
            if ($priorityOrder < 1 || $priorityOrder > 3) {
                $priorityOrder = 1;
            }

            $sql = "INSERT INTO popups (
                        title, type, content, desktop_image, mascot_image, layout_mode, 
                        button_text, button_url, button_target, priority, priority_order, 
                        start_at, end_at, status, created_by, created_at, updated_at
                    ) VALUES (
                        ?, 'image_text', ?, ?, ?, ?, 
                        ?, ?, ?, 'normal', ?, 
                        ?, ?, ?, ?, NOW(), NOW()
                    )";

            $startAt = $this->request->input('start_at') ?: null;
            $endAt = $this->request->input('end_at') ?: null;
            $status = $this->request->input('status', 'active');
            if (!in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }

            $id = (int)Database::insert($sql, [
                $title,
                $this->request->input('subtitle', $this->request->input('content', '')),
                $desktopImage,
                $mascotImage ?: null,
                $layoutMode,
                $this->request->input('button_text', 'ดูรายละเอียด'),
                $this->request->input('button_url', '/'),
                $this->request->input('button_target', '_self'),
                $priorityOrder,
                $startAt,
                $endAt,
                $status,
                Auth::id()
            ]);

            AuditService::log('popups', 'create', (string)$id, null, ['title' => $title, 'priority_order' => $priorityOrder]);
            Database::commit();

            $row = Database::first("SELECT * FROM popups WHERE id = ?", [$id]);
            $row['banner_image'] = resolve_media_url($row['desktop_image'] ?? null);
            $row['mascot_image'] = resolve_media_url($row['mascot_image'] ?? null);
            $row['version'] = \App\Services\AccessService::version($row);

            $this->response->json(['success' => true, 'message' => 'สร้างแคมเปญป็อปอัปสำเร็จ', 'data' => $row], 201);
        } catch (Throwable $e) {
            Database::rollBack();
            $this->response->json(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()], 500);
        }
    }

    /**
     * REST API: POST /api/admin/popups/{id}/update (or PUT /api/admin/popups/{id})
     */
    public function apiUpdate(string $id): void
    {
        $popupId = (int)$id;
        $popup = Database::first("SELECT * FROM popups WHERE id = ? AND deleted_at IS NULL", [$popupId]);
        if (!$popup) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลป็อปอัป'], 404);
            return;
        }

        $title = trim((string)$this->request->input('title', $popup['title']));
        if ($title === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกหัวข้อป็อปอัป'], 422);
            return;
        }

        // Duplicate title check (excluding current record)
        $dup = Database::first("SELECT id FROM popups WHERE title = ? AND id != ? AND deleted_at IS NULL", [$title, $popupId]);
        if ($dup) {
            $this->response->json(['success' => false, 'message' => 'มีป็อปอัปชื่อนี้อยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น'], 422);
            return;
        }

        $desktopImage = trim((string)$this->request->input('banner_image', $this->request->input('desktop_image', $popup['desktop_image'])));
        if ($this->request->hasFile('banner_file') || $this->request->hasFile('file')) {
            $file = $this->request->file('banner_file') ?? $this->request->file('file');
            if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload = MediaService::upload($file, 'popups', $title . ' - banner');
                    $desktopImage = $upload['path'];
                } catch (Throwable $e) {
                    $this->response->json(['success' => false, 'message' => 'อัปโหลดภาพไม่สำเร็จ: ' . $e->getMessage()], 422);
                    return;
                }
            }
        }

        $mascotImage = trim((string)$this->request->input('mascot_image', $popup['mascot_image'] ?? ''));
        if ($this->request->hasFile('mascot_file')) {
            $mfile = $this->request->file('mascot_file');
            if (!empty($mfile['name']) && $mfile['error'] === UPLOAD_ERR_OK) {
                try {
                    $mupload = MediaService::upload($mfile, 'popups', $title . ' - mascot');
                    $mascotImage = $mupload['path'];
                } catch (Throwable $e) {
                    $this->response->json(['success' => false, 'message' => 'อัปโหลดมาสคอตไม่สำเร็จ: ' . $e->getMessage()], 422);
                    return;
                }
            }
        }

        $layoutMode = (string)$this->request->input('layout_mode', $popup['layout_mode'] ?? 'banner_only');
        if (!in_array($layoutMode, ['banner_only', 'mascot_only', 'banner_and_mascot'], true)) {
            $layoutMode = 'banner_only';
        }

        $priorityOrder = (int)$this->request->input('priority_order', $popup['priority_order'] ?? 1);
        if ($priorityOrder < 1 || $priorityOrder > 3) {
            $priorityOrder = 1;
        }

        $sql = "UPDATE popups SET 
                    title = ?, content = ?, desktop_image = ?, mascot_image = ?, layout_mode = ?, 
                    button_text = ?, button_url = ?, button_target = ?, priority_order = ?, 
                    start_at = ?, end_at = ?, status = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?";

        $startAt = $this->request->input('start_at', $popup['start_at']) ?: null;
        $endAt = $this->request->input('end_at', $popup['end_at']) ?: null;
        $status = $this->request->input('status', $popup['status']);
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        Database::execute($sql, [
            $title,
            $this->request->input('subtitle', $this->request->input('content', $popup['content'])),
            $desktopImage,
            $mascotImage ?: null,
            $layoutMode,
            $this->request->input('button_text', $popup['button_text']),
            $this->request->input('button_url', $popup['button_url']),
            $this->request->input('button_target', $popup['button_target'] ?? '_self'),
            $priorityOrder,
            $startAt,
            $endAt,
            $status,
            Auth::id(),
            $popupId
        ]);

        AuditService::log('popups', 'update', (string)$popupId, null, ['title' => $title, 'priority_order' => $priorityOrder]);

        $row = Database::first("SELECT * FROM popups WHERE id = ?", [$popupId]);
        $row['banner_image'] = resolve_media_url($row['desktop_image'] ?? null);
        $row['mascot_image'] = resolve_media_url($row['mascot_image'] ?? null);
        $row['version'] = \App\Services\AccessService::version($row);

        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขป็อปอัปสำเร็จ', 'data' => $row]);
    }

    /**
     * REST API: POST /api/admin/popups/{id}/delete (or DELETE /api/admin/popups/{id})
     */
    public function apiDestroy(string $id): void
    {
        $popupId = (int)$id;
        $popup = Database::first("SELECT * FROM popups WHERE id = ? AND deleted_at IS NULL", [$popupId]);
        if (!$popup) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลป็อปอัป'], 404);
            return;
        }

        Database::execute("UPDATE popups SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), $popupId]);
        AuditService::log('popups', 'delete', (string)$popupId, null, ['title' => $popup['title']]);

        $this->response->json(['success' => true, 'message' => 'ลบป็อปอัปเรียบร้อยแล้ว']);
    }

    /**
     * REST API: POST /api/admin/popups/upload
     */
    public function apiUpload(): void
    {
        if (!$this->request->hasFile('file')) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบไฟล์ที่อัปโหลด'], 422);
            return;
        }

        try {
            $upload = MediaService::upload($this->request->file('file'), 'popups', 'Popup media');
            $this->response->json([
                'success' => true,
                'data' => [
                    'url' => $upload['url'],
                    'path' => $upload['path'],
                    'filename' => $upload['filename']
                ]
            ]);
        } catch (Throwable $e) {
            $this->response->json(['success' => false, 'message' => 'อัปโหลดล้มเหลว: ' . $e->getMessage()], 422);
        }
    }

    // ──────────────────────────────────────────────
    // LEGACY BLADE WEB METHODS (Backward-compatibility)
    // ──────────────────────────────────────────────

    public function index(): void
    {
        $popups = Database::query("SELECT * FROM popups WHERE deleted_at IS NULL ORDER BY priority_order ASC, created_at DESC");
        $popups = array_map(static function (array $popup): array {
            foreach (['desktop_image', 'mobile_image', 'mascot_image'] as $field) {
                if (array_key_exists($field, $popup)) $popup[$field] = resolve_media_url($popup[$field]);
            }
            return $popup;
        }, $popups);

        $this->render('admin.popups.index', [
            'title' => 'จัดการ Popup Campaign ประชาสัมพันธ์',
            'popups' => $popups,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'title' => 'required',
            'content' => 'required',
        ]);

        $sql = "INSERT INTO popups (title, type, content, button_text, button_url, display_mode, delay_seconds, frequency, priority, status, start_at, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";

        $id = Database::insert($sql, [
            $data['title'],
            $this->request->input('type', 'image_text'),
            $data['content'],
            $this->request->input('button_text'),
            $this->request->input('button_url'),
            $this->request->input('display_mode', 'load'),
            (int) $this->request->input('delay_seconds', 0),
            $this->request->input('frequency', 'session'),
            $this->request->input('priority', 'normal'),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('popups', 'create', (string)$id, null, ['title' => $data['title']]);
        Session::flash('success', 'สร้างแคมเปญ Popup เรียบร้อยแล้ว');
        $this->redirect(url('admin/popups'));
    }

    public function destroy(string $id): void
    {
        $popup = Database::first("SELECT * FROM popups WHERE id = ? LIMIT 1", [(int) $id]);
        if ($popup) {
            Database::execute("UPDATE popups SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('popups', 'delete', (string)$id, $popup);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบ Popup เรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบ Popup เรียบร้อยแล้ว');
            $this->redirect(url('admin/popups'));
        }
    }
}
