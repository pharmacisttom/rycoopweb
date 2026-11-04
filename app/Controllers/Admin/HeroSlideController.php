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

class HeroSlideController extends Controller
{
    public function apiIndex(): void
    {
        $slides = Database::query("SELECT * FROM hero_slides WHERE deleted_at IS NULL ORDER BY priority DESC, sort_order ASC, id DESC");
        $slides = array_map(function (array $slide): array {
            foreach (['image_url', 'desktop_image', 'mobile_image'] as $field) {
                if (array_key_exists($field, $slide)) $slide[$field] = resolve_media_url($slide[$field]);
            }
            $slide['version'] = \App\Services\AccessService::version($slide);
            return $slide;
        }, $slides);

        $this->response->json(['success' => true, 'data' => $slides]);
    }

    public function apiStore(): void
    {
        $title = trim((string)$this->request->input('title'));
        if ($title === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกหัวข้อสไลด์'], 422);
            return;
        }

        $desktopImage = trim((string)$this->request->input('desktop_image', 'hero_bg_default.jpg')) ?: 'hero_bg_default.jpg';
        if ($this->request->hasFile('file') || $this->request->hasFile('desktop_image_file')) {
            $file = $this->request->file('file') ?? $this->request->file('desktop_image_file');
            if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload = MediaService::upload($file, 'hero_slides', $title);
                    $desktopImage = $upload['path'];
                } catch (\Throwable $e) {
                    $this->response->json(['success' => false, 'message' => 'อัปโหลดภาพไม่สำเร็จ: ' . $e->getMessage()], 422);
                    return;
                }
            }
        }

        $sql = "INSERT INTO hero_slides (title, subtitle, description, desktop_image, button_text, button_url, button_target, text_alignment, overlay_opacity, priority, status, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $title,
            $this->request->input('subtitle', ''),
            $this->request->input('description', ''),
            $desktopImage,
            $this->request->input('button_text', ''),
            $this->request->input('button_url', ''),
            $this->request->input('button_target', '_self'),
            $this->request->input('text_alignment', 'left'),
            (float)$this->request->input('overlay_opacity', 0.80),
            (int)$this->request->input('priority', 10),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('hero_slides', 'create', (string)$id, null, ['title' => $title, 'image' => $desktopImage]);
        $row = Database::first("SELECT * FROM hero_slides WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มสไลด์สำเร็จ', 'data' => $row], 201);
    }

    public function apiUpdate(string $id): void
    {
        $slideId = (int)$id;
        $slide = Database::first("SELECT * FROM hero_slides WHERE id = ? AND deleted_at IS NULL", [$slideId]);
        if (!$slide) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสไลด์'], 404);
            return;
        }

        $title = trim((string)$this->request->input('title', $slide['title']));
        if ($title === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกหัวข้อสไลด์'], 422);
            return;
        }

        $desktopImage = $slide['desktop_image'];
        if ($this->request->hasFile('file') || $this->request->hasFile('desktop_image_file')) {
            $file = $this->request->file('file') ?? $this->request->file('desktop_image_file');
            if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload = MediaService::upload($file, 'hero_slides', $title);
                    $desktopImage = $upload['path'];
                } catch (\Throwable $e) {
                    $this->response->json(['success' => false, 'message' => 'อัปโหลดภาพไม่สำเร็จ: ' . $e->getMessage()], 422);
                    return;
                }
            }
        } elseif ($this->request->filled('desktop_image')) {
            $desktopImage = trim((string)$this->request->input('desktop_image'));
        }

        $sql = "UPDATE hero_slides SET 
                title = ?, subtitle = ?, description = ?, desktop_image = ?, 
                button_text = ?, button_url = ?, button_target = ?, text_alignment = ?, 
                overlay_opacity = ?, priority = ?, status = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?";

        Database::execute($sql, [
            $title,
            $this->request->input('subtitle', $slide['subtitle']),
            $this->request->input('description', $slide['description']),
            $desktopImage,
            $this->request->input('button_text', $slide['button_text']),
            $this->request->input('button_url', $slide['button_url']),
            $this->request->input('button_target', $slide['button_target'] ?? '_self'),
            $this->request->input('text_alignment', $slide['text_alignment'] ?? 'left'),
            (float)$this->request->input('overlay_opacity', $slide['overlay_opacity'] ?? 0.80),
            (int)$this->request->input('priority', $slide['priority'] ?? 10),
            $this->request->input('status', $slide['status'] ?? 'active'),
            Auth::id(),
            $slideId
        ]);

        AuditService::log('hero_slides', 'update', (string)$slideId, $slide, ['title' => $title, 'image' => $desktopImage]);
        $updated = Database::first("SELECT * FROM hero_slides WHERE id = ?", [$slideId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขสไลด์เรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroy(string $id): void
    {
        $slideId = (int)$id;
        $slide = Database::first("SELECT * FROM hero_slides WHERE id = ? AND deleted_at IS NULL", [$slideId]);
        if (!$slide) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลสไลด์'], 404);
            return;
        }

        Database::execute("UPDATE hero_slides SET deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ?", [Auth::id(), $slideId]);
        AuditService::log('hero_slides', 'delete', (string)$slideId, $slide);
        $this->response->json(['success' => true, 'message' => 'ลบสไลด์เรียบร้อยแล้ว']);
    }

    public function index(): void
    {
        $slides = Database::query("SELECT * FROM hero_slides WHERE deleted_at IS NULL ORDER BY priority DESC, sort_order ASC, id ASC");
        $slides = array_map(static function (array $slide): array {
            foreach (['image_url', 'desktop_image', 'mobile_image'] as $field) {
                if (array_key_exists($field, $slide)) $slide[$field] = resolve_media_url($slide[$field]);
            }
            return $slide;
        }, $slides);

        $this->render('admin.hero_slides.index', [
            'title' => 'จัดการ Hero Slideshow แบนเนอร์หน้าแรก',
            'slides' => $slides,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'title' => 'required',
        ]);

        $desktopImage = 'hero_bg_default.jpg';

        if ($this->request->hasFile('desktop_image_file')) {
            $file = $this->request->file('desktop_image_file');
            if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload = MediaService::upload($file, 'hero_slides', $data['title']);
                    $desktopImage = $upload['path'];
                } catch (Throwable $e) {
                    Session::flash('error', 'อัปโหลดภาพไม่สำเร็จ: ' . $e->getMessage());
                    $this->redirect(url('admin/hero-slides'));
                    return;
                }
            }
        } elseif ($this->request->filled('preset_image')) {
            $desktopImage = $this->request->input('preset_image');
        } elseif ($this->request->filled('desktop_image')) {
            $desktopImage = $this->request->input('desktop_image');
        }

        $sql = "INSERT INTO hero_slides (title, subtitle, description, desktop_image, button_text, button_url, button_target, text_alignment, overlay_opacity, priority, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['title'],
            $this->request->input('subtitle'),
            $this->request->input('description'),
            $desktopImage,
            $this->request->input('button_text'),
            $this->request->input('button_url'),
            $this->request->input('button_target', '_self'),
            $this->request->input('text_alignment', 'left'),
            (float) $this->request->input('overlay_opacity', 0.80),
            (int) $this->request->input('priority', 10),
            $this->request->input('status', 'active'),
            Auth::id()
        ]);

        AuditService::log('hero_slides', 'create', (string)$id, null, ['title' => $data['title'], 'image' => $desktopImage]);
        Session::flash('success', 'เพิ่มสไลด์เรียบร้อยแล้ว');
        $this->redirect(url('admin/hero-slides'));
    }

    public function update(string $id): void
    {
        $slide = Database::first("SELECT * FROM hero_slides WHERE id = ? AND deleted_at IS NULL LIMIT 1", [(int) $id]);
        if (!$slide) {
            Session::flash('error', 'ไม่พบข้อมูลสไลด์ที่ต้องการแก้ไข');
            $this->redirect(url('admin/hero-slides'));
            return;
        }

        $data = $this->validate([
            'title' => 'required',
        ]);

        $desktopImage = $slide['desktop_image'];

        if ($this->request->hasFile('desktop_image_file')) {
            $file = $this->request->file('desktop_image_file');
            if (!empty($file['name']) && $file['error'] === UPLOAD_ERR_OK) {
                try {
                    $upload = MediaService::upload($file, 'hero_slides', $data['title']);
                    $desktopImage = $upload['path'];
                } catch (Throwable $e) {
                    Session::flash('error', 'อัปโหลดภาพไม่สำเร็จ: ' . $e->getMessage());
                    $this->redirect(url('admin/hero-slides'));
                    return;
                }
            }
        } elseif ($this->request->filled('preset_image') && $this->request->input('preset_image') !== '') {
            $desktopImage = $this->request->input('preset_image');
        } elseif ($this->request->filled('desktop_image')) {
            $desktopImage = $this->request->input('desktop_image');
        }

        $sql = "UPDATE hero_slides SET 
                title = ?, 
                subtitle = ?, 
                description = ?, 
                desktop_image = ?, 
                button_text = ?, 
                button_url = ?, 
                button_target = ?, 
                text_alignment = ?, 
                overlay_opacity = ?, 
                priority = ?, 
                status = ?, 
                updated_by = ?, 
                updated_at = NOW() 
                WHERE id = ?";

        Database::execute($sql, [
            $data['title'],
            $this->request->input('subtitle'),
            $this->request->input('description'),
            $desktopImage,
            $this->request->input('button_text'),
            $this->request->input('button_url'),
            $this->request->input('button_target', '_self'),
            $this->request->input('text_alignment', 'left'),
            (float) $this->request->input('overlay_opacity', 0.80),
            (int) $this->request->input('priority', 10),
            $this->request->input('status', 'active'),
            Auth::id(),
            (int) $id
        ]);

        AuditService::log('hero_slides', 'update', (string)$id, $slide, ['title' => $data['title'], 'image' => $desktopImage]);
        Session::flash('success', 'บันทึกการแก้ไขสไลด์เรียบร้อยแล้ว');
        $this->redirect(url('admin/hero-slides'));
    }

    public function destroy(string $id): void
    {
        $slide = Database::first("SELECT * FROM hero_slides WHERE id = ? LIMIT 1", [(int) $id]);
        if ($slide) {
            Database::execute("UPDATE hero_slides SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('hero_slides', 'delete', (string)$id, $slide);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบสไลด์เรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบสไลด์เรียบร้อยแล้ว');
            $this->redirect(url('admin/hero-slides'));
        }
    }
}
