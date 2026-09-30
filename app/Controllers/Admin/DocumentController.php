<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\MediaService;

class DocumentController extends Controller
{
    public function index(): void
    {
        $categories = Database::query("SELECT * FROM document_categories WHERE status = 'active' ORDER BY sort_order ASC");
        $documents = Database::query("SELECT d.*, c.name as category_name 
                                     FROM documents d 
                                     JOIN document_categories c ON d.category_id = c.id 
                                     WHERE d.deleted_at IS NULL 
                                     ORDER BY d.created_at DESC");
        $documents = array_map(static function (array $document): array {
            foreach (['thumbnail', 'thumbnail_url', 'image_url'] as $field) {
                if (array_key_exists($field, $document)) $document[$field] = resolve_media_url($document[$field]);
            }
            return $document;
        }, $documents);

        $this->render('admin.documents.index', [
            'title' => 'จัดการศูนย์เอกสารและระเบียบ',
            'categories' => $categories,
            'documents' => $documents,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'category_id' => 'required|numeric',
            'title' => 'required',
        ]);

        $status = $this->request->input('status', 'active');
        $filePath = null;
        $fileSize = 0;
        $fileType = 'application/pdf';

        if ($this->request->hasFile('file') && !empty($this->request->file('file')['tmp_name'])) {
            try {
                $uploaded = MediaService::upload($this->request->file('file'), 'documents');
                $filePath = $uploaded['path'];
                $fileSize = $uploaded['size'];
                $fileType = $uploaded['mime'] ?? 'application/pdf';
            } catch (\Exception $e) {
                Session::flash('error', 'อัปโหลดไฟล์ไม่สำเร็จ: ' . $e->getMessage());
                $this->redirect(url('admin/documents'));
                return;
            }
        }

        // Do not allow published/active documents without an uploaded file
        if (empty($filePath)) {
            if ($status === 'active') {
                Session::flash('error', 'ไม่สามารถเผยแพร่เอกสารได้เนื่องจากยังไม่มีการอัปโหลดไฟล์จริง กรุณาแนบไฟล์เอกสาร (PDF, DOCX) หรือบันทึกเป็นแบบร่าง (Inactive)');
                $this->redirect(url('admin/documents'));
                return;
            }
            $filePath = ''; // Draft document without file
        }

        $sql = "INSERT INTO documents (category_id, title, slug, document_number, file_path, file_size, file_type, year, tag, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['category_id'],
            $data['title'],
            str_slug($data['title']) . '-' . time(),
            $this->request->input('document_number'),
            $filePath,
            $fileSize,
            $fileType,
            (int) ($this->request->input('year') ?: date('Y') + 543),
            $this->request->input('tag'),
            $status,
            Auth::id()
        ]);

        AuditService::log('documents', 'create', (string)$id, null, ['title' => $data['title'], 'status' => $status, 'file_path' => $filePath]);
        Session::flash('success', 'เพิ่มเอกสารเรียบร้อยแล้ว');
        $this->redirect(url('admin/documents'));
    }

    public function destroy(string $id): void
    {
        $doc = Database::first("SELECT * FROM documents WHERE id = ? LIMIT 1", [(int) $id]);
        if ($doc) {
            Database::execute("UPDATE documents SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('documents', 'delete', (string)$id, $doc);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบเอกสารเรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบเอกสารเรียบร้อยแล้ว');
            $this->redirect(url('admin/documents'));
        }
    }
}
