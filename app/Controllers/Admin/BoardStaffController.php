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
    public function apiIndex(): void
    {
        $boards = Database::query("SELECT * FROM boards WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");
        $staff = Database::query("SELECT * FROM staff WHERE deleted_at IS NULL ORDER BY sort_order ASC, id ASC");

        $boards = array_map(function (array $item): array {
            $item['photo'] = resolve_media_url($item['photo'] ?? null);
            $item['version'] = \App\Services\AccessService::version($item);
            return $item;
        }, $boards);

        $staff = array_map(function (array $item): array {
            $item['photo'] = resolve_media_url($item['photo'] ?? null);
            $item['version'] = \App\Services\AccessService::version($item);
            return $item;
        }, $staff);

        $this->response->json([
            'success' => true,
            'data' => [
                'boards' => $boards,
                'staff' => $staff,
            ]
        ]);
    }

    public function apiStoreBoard(): void
    {
        $name = trim((string)$this->request->input('name'));
        $position = trim((string)$this->request->input('position'));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "INSERT INTO boards (name, position, role_type, term_years, photo, bio, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            $position,
            $this->request->input('role_type', 'director'),
            $this->request->input('term_years', '2568-2569'),
            $this->request->input('photo', ''),
            $this->request->input('bio', ''),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
        ]);

        AuditService::log('boards', 'create', (string)$id, null, ['name' => $name, 'position' => $position]);
        $row = Database::first("SELECT * FROM boards WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มกรรมการเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdateBoard(string $id): void
    {
        $boardId = (int)$id;
        $old = Database::first("SELECT * FROM boards WHERE id = ? AND deleted_at IS NULL", [$boardId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลกรรมการ'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        $position = trim((string)$this->request->input('position', $old['position']));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "UPDATE boards SET name = ?, position = ?, role_type = ?, term_years = ?, photo = ?, bio = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?";
        Database::execute($sql, [
            $name,
            $position,
            $this->request->input('role_type', $old['role_type']),
            $this->request->input('term_years', $old['term_years']),
            $this->request->input('photo', $old['photo']),
            $this->request->input('bio', $old['bio']),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            $boardId
        ]);

        AuditService::log('boards', 'update', (string)$boardId, $old, ['name' => $name, 'position' => $position]);
        $updated = Database::first("SELECT * FROM boards WHERE id = ?", [$boardId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขกรรมการเรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroyBoard(string $id): void
    {
        $boardId = (int)$id;
        $old = Database::first("SELECT * FROM boards WHERE id = ? AND deleted_at IS NULL", [$boardId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลกรรมการ'], 404);
            return;
        }

        Database::execute("UPDATE boards SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?", [$boardId]);
        AuditService::log('boards', 'delete', (string)$boardId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบกรรมการเรียบร้อยแล้ว']);
    }

    public function apiStoreStaff(): void
    {
        $name = trim((string)$this->request->input('name'));
        $position = trim((string)$this->request->input('position'));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "INSERT INTO staff (name, position, department, email, phone, photo, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            $position,
            $this->request->input('department', ''),
            $this->request->input('email', ''),
            $this->request->input('phone', ''),
            $this->request->input('photo', ''),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
        ]);

        AuditService::log('staff', 'create', (string)$id, null, ['name' => $name, 'position' => $position]);
        $row = Database::first("SELECT * FROM staff WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มเจ้าหน้าที่เรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdateStaff(string $id): void
    {
        $staffId = (int)$id;
        $old = Database::first("SELECT * FROM staff WHERE id = ? AND deleted_at IS NULL", [$staffId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลเจ้าหน้าที่'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        $position = trim((string)$this->request->input('position', $old['position']));

        if ($name === '' || $position === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อและตำแหน่ง'], 422);
            return;
        }

        $sql = "UPDATE staff SET name = ?, position = ?, department = ?, email = ?, phone = ?, photo = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?";
        Database::execute($sql, [
            $name,
            $position,
            $this->request->input('department', $old['department']),
            $this->request->input('email', $old['email']),
            $this->request->input('phone', $old['phone']),
            $this->request->input('photo', $old['photo']),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            $staffId
        ]);

        AuditService::log('staff', 'update', (string)$staffId, $old, ['name' => $name, 'position' => $position]);
        $updated = Database::first("SELECT * FROM staff WHERE id = ?", [$staffId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขเจ้าหน้าที่เรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroyStaff(string $id): void
    {
        $staffId = (int)$id;
        $old = Database::first("SELECT * FROM staff WHERE id = ? AND deleted_at IS NULL", [$staffId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลเจ้าหน้าที่'], 404);
            return;
        }

        Database::execute("UPDATE staff SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?", [$staffId]);
        AuditService::log('staff', 'delete', (string)$staffId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบเจ้าหน้าที่เรียบร้อยแล้ว']);
    }

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
