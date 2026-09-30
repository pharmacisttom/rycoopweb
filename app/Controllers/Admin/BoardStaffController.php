<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class BoardStaffController extends Controller
{
    public function index(): void
    {
        $boards = Database::query("SELECT * FROM boards WHERE deleted_at IS NULL ORDER BY sort_order ASC");
        $staff = Database::query("SELECT * FROM staff WHERE deleted_at IS NULL ORDER BY sort_order ASC");
        $boards = array_map(static function (array $item): array { $item['photo'] = resolve_media_url($item['photo'] ?? null); return $item; }, $boards);
        $staff = array_map(static function (array $item): array { $item['photo'] = resolve_media_url($item['photo'] ?? null); return $item; }, $staff);

        $this->render('admin.board_staff.index', [
            'title' => 'จัดการคณะกรรมการและเจ้าหน้าที่',
            'boards' => $boards,
            'staff' => $staff,
        ], 'layouts.admin');
    }

    public function storeBoard(): void
    {
        $data = $this->validate([
            'name' => 'required',
            'position' => 'required',
        ]);

        Database::execute("INSERT INTO boards (name, position, role_type, term_years, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())", [
            $data['name'],
            $data['position'],
            $this->request->input('role_type', 'director'),
            $this->request->input('term_years', '2568-2569')
        ]);

        AuditService::log('boards', 'create', null, null, ['name' => $data['name']]);
        Session::flash('success', 'เพิ่มกรรมการเรียบร้อยแล้ว');
        $this->redirect(url('admin/board-staff'));
    }

    public function destroyBoard(string $id): void
    {
        Database::execute("UPDATE boards SET deleted_at = NOW() WHERE id = ?", [(int) $id]);
        if ($this->request->isAjax()) {
            $this->json(['success' => true]);
        } else {
            Session::flash('success', 'ลบกรรมการเรียบร้อยแล้ว');
            $this->redirect(url('admin/board-staff'));
        }
    }
}
