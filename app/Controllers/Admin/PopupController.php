<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class PopupController extends Controller
{
    public function index(): void
    {
        $popups = Database::query("SELECT * FROM popups WHERE deleted_at IS NULL ORDER BY FIELD(priority, 'critical', 'high', 'normal', 'low'), created_at DESC");
        $popups = array_map(static function (array $popup): array {
            foreach (['desktop_image', 'mobile_image'] as $field) {
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
