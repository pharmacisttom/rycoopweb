<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\MediaService;

class MediaController extends Controller
{
    public function index(): void
    {
        $mediaList = Database::query("SELECT m.*, u.name as uploader_name FROM media m LEFT JOIN users u ON m.uploaded_by = u.id WHERE m.deleted_at IS NULL ORDER BY m.created_at DESC");
        $mediaList = array_map(static function (array $media): array {
            $media['url'] = resolve_media_url($media['path'] ?? null);
            return $media;
        }, $mediaList);

        $this->render('admin.media.index', [
            'title' => 'คลังสื่อและรูปภาพ (Media Library)',
            'mediaList' => $mediaList,
        ], 'layouts.admin');
    }

    public function upload(): void
    {
        if ($this->request->hasFile('file')) {
            try {
                $alt = $this->request->input('alt_text');
                $folder = $this->request->input('folder', 'general');
                $result = MediaService::upload($this->request->file('file'), $folder, $alt);

                AuditService::log('media', 'upload', (string)$result['id'], null, ['filename' => $result['filename']]);
                Session::flash('success', 'อัปโหลดไฟล์เรียบร้อยแล้ว');
            } catch (\Exception $e) {
                Session::flash('error', $e->getMessage());
            }
        }
        $this->redirect(url('admin/media'));
    }

    public function destroy(string $id): void
    {
        $media = Database::first("SELECT * FROM media WHERE id = ? LIMIT 1", [(int) $id]);
        if ($media) {
            Database::execute("UPDATE media SET deleted_at = NOW() WHERE id = ?", [(int) $id]);
            AuditService::log('media', 'delete', (string)$id, $media);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true]);
        } else {
            Session::flash('success', 'ลบไฟล์สื่อเรียบร้อยแล้ว');
            $this->redirect(url('admin/media'));
        }
    }
}
