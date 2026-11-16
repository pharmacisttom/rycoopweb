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

    /** Admin-only file review. Never expose private storage as a static URL. */
    public function preview(string $id): void
    {
        $this->serveAdminDocument($id, false);
    }

    /** Admin-only file download, including unpublished documents. */
    public function download(string $id): void
    {
        $this->serveAdminDocument($id, true);
    }

    private function serveAdminDocument(string $id, bool $download): void
    {
        // Defense in depth: the /api/admin route group and PermissionMiddleware
        // must also enforce the authenticated, fully MFA-verified admin session.
        if (!Auth::check() || !Auth::hasPermission('documents', 'view')) {
            http_response_code(403);
            header('Cache-Control: no-store, private');
            exit;
        }

        $docId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($docId === false) {
            http_response_code(404);
            exit;
        }

        $doc = Database::first(
            'SELECT id, file_path FROM documents WHERE id = ? AND deleted_at IS NULL LIMIT 1',
            [(int)$docId]
        );
        if (!$doc || empty($doc['file_path'])) {
            http_response_code(404);
            exit;
        }

        try {
            $base = realpath(storage_upload_path());
            $path = realpath(storage_upload_path((string)$doc['file_path']));
        } catch (\Throwable $e) {
            http_response_code(404);
            exit;
        }
        if (!$base || !$path || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)
            || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            exit;
        }

        // Never trust MIME metadata saved in the DB for HTTP response headers.
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        if (!isset($allowed[$extension])) {
            http_response_code(415);
            exit;
        }
        // The eye button previews PDFs only; other formats are downloaded.
        if (!$download && $extension !== 'pdf') {
            header('Cache-Control: no-store, private');
            http_response_code(415);
            exit;
        }
        if ($extension === 'pdf') {
            $fp = fopen($path, 'rb');
            $magic = $fp ? fread($fp, 5) : false;
            if ($fp) fclose($fp);
            if ($magic !== '%PDF-') {
                http_response_code(415);
                exit;
            }
        }

        $size = filesize($path);
        if ($size === false) {
            http_response_code(404);
            exit;
        }
        $filename = 'rycoop-document-' . (int)$docId . '.' . $extension;
        header('Content-Type: ' . $allowed[$extension]);
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
        header('Content-Length: ' . $size);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }
        readfile($path);
        exit;
    }


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
