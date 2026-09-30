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

        // Check file upload
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

        // Check file upload
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

