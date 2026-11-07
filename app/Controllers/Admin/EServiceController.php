<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class EServiceController extends Controller
{
    public function apiIndex(): void
    {
        $links = Database::query("SELECT * FROM eservice_links WHERE deleted_at IS NULL ORDER BY sort_order ASC, id DESC");
        $links = array_map(function (array $link): array {
            $link['version'] = \App\Services\AccessService::version($link);
            return $link;
        }, $links);

        $this->response->json(['success' => true, 'data' => $links]);
    }

    public function apiStore(): void
    {
        $name = trim((string)($this->request->input('name') ?? $this->request->input('title') ?? ''));
        $url = trim((string)$this->request->input('url', ''));

        if ($name === '' || $url === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อบริการและ URL'], 422);
            return;
        }

        $sql = "INSERT INTO eservice_links (name, description, url, icon, category, is_internal, open_new_tab, confirm_before_redirect, is_maintenance, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            $this->request->input('description', ''),
            $url,
            $this->request->input('icon', 'globe'),
            $this->request->input('category', 'general'),
            (int)($this->request->input('is_internal') ? 1 : 0),
            (int)($this->request->input('open_new_tab', 1) ? 1 : 0),
            (int)($this->request->input('confirm_before_redirect', 0) ? 1 : 0),
            (int)($this->request->input('is_maintenance', 0) ? 1 : 0),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
        ]);

        AuditService::log('eservices', 'create', (string)$id, null, ['name' => $name, 'url' => $url]);
        $row = Database::first("SELECT * FROM eservice_links WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มลิงก์บริการเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdate(string $id): void
    {
        $linkId = (int)$id;
        $old = Database::first("SELECT * FROM eservice_links WHERE id = ? AND deleted_at IS NULL", [$linkId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลลิงก์บริการ'], 404);
            return;
        }

        $name = trim((string)($this->request->input('name') ?? $this->request->input('title') ?? $old['name']));
        $url = trim((string)$this->request->input('url', $old['url']));

        if ($name === '' || $url === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อบริการและ URL'], 422);
            return;
        }

        $sql = "UPDATE eservice_links SET 
                name = ?, description = ?, url = ?, icon = ?, category = ?, 
                is_internal = ?, open_new_tab = ?, confirm_before_redirect = ?, 
                is_maintenance = ?, sort_order = ?, status = ?, updated_at = NOW() 
                WHERE id = ?";

        Database::execute($sql, [
            $name,
            $this->request->input('description', $old['description']),
            $url,
            $this->request->input('icon', $old['icon'] ?? 'globe'),
            $this->request->input('category', $old['category'] ?? 'general'),
            (int)($this->request->input('is_internal', $old['is_internal']) ? 1 : 0),
            (int)($this->request->input('open_new_tab', $old['open_new_tab']) ? 1 : 0),
            (int)($this->request->input('confirm_before_redirect', $old['confirm_before_redirect']) ? 1 : 0),
            (int)($this->request->input('is_maintenance', $old['is_maintenance']) ? 1 : 0),
            (int)$this->request->input('sort_order', $old['sort_order'] ?? 0),
            $this->request->input('status', $old['status'] ?? 'active'),
            $linkId
        ]);

        AuditService::log('eservices', 'update', (string)$linkId, $old, ['name' => $name, 'url' => $url]);
        $updated = Database::first("SELECT * FROM eservice_links WHERE id = ?", [$linkId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขบริการเรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroy(string $id): void
    {
        $linkId = (int)$id;
        $old = Database::first("SELECT * FROM eservice_links WHERE id = ? LIMIT 1", [$linkId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลลิงก์บริการ'], 404);
            return;
        }

        Database::execute("UPDATE eservice_links SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?", [$linkId]);
        AuditService::log('eservices', 'delete', (string)$linkId, $old);
        $this->response->json(['success' => true, 'message' => 'ลบลิงก์บริการเรียบร้อยแล้ว']);
    }

    public function index(): void
    {
        $eservices = Database::query("SELECT * FROM eservice_links WHERE deleted_at IS NULL ORDER BY sort_order ASC");

        $this->render('admin.eservices.index', [
            'title' => 'จัดการ E-Service Gateway และลิงก์บริการเดิม',
            'eservices' => $eservices,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'name' => 'required',
            'url' => 'required',
        ]);

        $sql = "INSERT INTO eservice_links (name, description, url, icon, category, is_internal, open_new_tab, confirm_before_redirect, is_maintenance, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['name'],
            $this->request->input('description'),
            $data['url'],
            $this->request->input('icon', 'bi-globe'),
            $this->request->input('category', 'general'),
            (int) ($this->request->input('is_internal') ? 1 : 0),
            (int) ($this->request->input('open_new_tab') ? 1 : 0),
            (int) ($this->request->input('confirm_before_redirect') ? 1 : 0),
            (int) ($this->request->input('is_maintenance') ? 1 : 0),
            $this->request->input('status', 'active')
        ]);

        AuditService::log('eservices', 'create', (string)$id, null, ['name' => $data['name']]);
        Session::flash('success', 'เพิ่มลิงก์ E-Service เรียบร้อยแล้ว');
        $this->redirect(url('admin/eservices'));
    }

    public function destroy(string $id): void
    {
        $es = Database::first("SELECT * FROM eservice_links WHERE id = ? LIMIT 1", [(int) $id]);
        if ($es) {
            Database::execute("UPDATE eservice_links SET deleted_at = NOW() WHERE id = ?", [(int) $id]);
            AuditService::log('eservices', 'delete', (string)$id, $es);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบลิงก์บริการเรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบลิงก์บริการเรียบร้อยแล้ว');
            $this->redirect(url('admin/eservices'));
        }
    }
}
