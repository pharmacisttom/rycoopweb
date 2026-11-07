<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AdminCrudService as Crud;
use App\Services\AuditService;
use App\Core\Database;
use App\Core\Auth;

final class DocumentController extends \App\Core\Controller
{
    public function index(): void
    {
        if (!$this->request->isAjax()) {
            $this->redirect(url('admin/workspace'));
            return;
        }
        Crud::respond($this->response, fn() => [
            'data' => Crud::listing('documents'),
            'categories' => Database::query("SELECT * FROM document_categories WHERE status = 'active' ORDER BY sort_order, id")
        ]);
    }

    public function store(): void
    {
        $this->save();
    }

    public function update(string $id): void
    {
        $this->save((int)$id);
    }

    private function save(?int $id = null): void
    {
        Crud::respond($this->response, fn() => [
            'data' => Crud::save('documents', $this->request->all(), $id, $this->request->file('file'))
        ], $id === null ? 201 : 200);
    }

    public function destroy(string $id): void
    {
        Crud::respond($this->response, function() use ($id) {
            Crud::disable('documents', (int)$id, $this->request->all());
            return [];
        });
    }

    // ──────────────────────────────────────────────
    // DOCUMENT CATEGORIES MANAGEMENT API
    // ──────────────────────────────────────────────

    public function apiCategories(): void
    {
        $categories = Database::query("SELECT * FROM document_categories ORDER BY sort_order ASC, id ASC");
        $categories = array_map(function (array $c): array {
            $c['version'] = \App\Services\AccessService::version($c);
            return $c;
        }, $categories);

        $this->response->json(['success' => true, 'data' => $categories]);
    }

    public function apiStoreCategory(): void
    {
        $name = trim((string)$this->request->input('name'));
        if ($name === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อหมวดหมู่เอกสาร'], 422);
            return;
        }

        $sql = "INSERT INTO document_categories (name, slug, description, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())";

        $id = (int)Database::insert($sql, [
            $name,
            str_slug($name) . '-' . time(),
            $this->request->input('description', ''),
            (int)$this->request->input('sort_order', 0),
            $this->request->input('status', 'active'),
        ]);

        AuditService::log('document_categories', 'create', (string)$id, null, ['name' => $name]);
        $row = Database::first("SELECT * FROM document_categories WHERE id = ?", [$id]);
        $row['version'] = \App\Services\AccessService::version($row);
        $this->response->json(['success' => true, 'message' => 'เพิ่มหมวดหมู่เอกสารเรียบร้อยแล้ว', 'data' => $row], 201);
    }

    public function apiUpdateCategory(string $id): void
    {
        $catId = (int)$id;
        $old = Database::first("SELECT * FROM document_categories WHERE id = ?", [$catId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลหมวดหมู่เอกสาร'], 404);
            return;
        }

        $name = trim((string)$this->request->input('name', $old['name']));
        if ($name === '') {
            $this->response->json(['success' => false, 'message' => 'กรุณากรอกชื่อหมวดหมู่เอกสาร'], 422);
            return;
        }

        $sql = "UPDATE document_categories SET name = ?, description = ?, sort_order = ?, status = ?, updated_at = NOW() WHERE id = ?";
        Database::execute($sql, [
            $name,
            $this->request->input('description', $old['description']),
            (int)$this->request->input('sort_order', $old['sort_order']),
            $this->request->input('status', $old['status'] ?? 'active'),
            $catId
        ]);

        AuditService::log('document_categories', 'update', (string)$catId, $old, ['name' => $name]);
        $updated = Database::first("SELECT * FROM document_categories WHERE id = ?", [$catId]);
        $updated['version'] = \App\Services\AccessService::version($updated);
        $this->response->json(['success' => true, 'message' => 'บันทึกการแก้ไขหมวดหมู่เรียบร้อยแล้ว', 'data' => $updated]);
    }

    public function apiDestroyCategory(string $id): void
    {
        $catId = (int)$id;
        $old = Database::first("SELECT * FROM document_categories WHERE id = ?", [$catId]);
        if (!$old) {
            $this->response->json(['success' => false, 'message' => 'ไม่พบข้อมูลหมวดหมู่เอกสาร'], 404);
            return;
        }

        $docsCount = (int)Database::value("SELECT COUNT(*) FROM documents WHERE category_id = ? AND deleted_at IS NULL", [$catId]);
        if ($docsCount > 0) {
            $this->response->json(['success' => false, 'message' => "ไม่สามารถลบหมวดหมู่นี้ได้เนื่องจากมีเอกสารอยู่ {$docsCount} รายการ"], 409);
            return;
        }

        Database::execute("UPDATE document_categories SET status = 'inactive', updated_at = NOW() WHERE id = ?", [$catId]);
        AuditService::log('document_categories', 'delete', (string)$catId, $old);
        $this->response->json(['success' => true, 'message' => 'ปิดใช้งานหมวดหมู่เอกสารเรียบร้อยแล้ว']);
    }
}
