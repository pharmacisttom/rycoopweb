<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;

class DocumentController extends Controller
{
    public function index(): void
    {
        $categorySlug = $this->request->query('cat');
        $keyword = $this->request->query('q');
        $year = $this->request->query('year');

        $categories = Database::query("SELECT * FROM document_categories WHERE status = 'active' ORDER BY sort_order ASC");

        $sql = "SELECT d.*, c.name as category_name, c.slug as category_slug 
                FROM documents d 
                JOIN document_categories c ON d.category_id = c.id 
                WHERE d.status = 'active' AND d.deleted_at IS NULL";
        $params = [];

        if (!empty($categorySlug)) {
            $sql .= " AND c.slug = ?";
            $params[] = $categorySlug;
        }

        if (!empty($keyword)) {
            $sql .= " AND (d.title LIKE ? OR d.document_number LIKE ? OR d.tag LIKE ?)";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
            $params[] = "%{$keyword}%";
        }

        if (!empty($year)) {
            $sql .= " AND d.year = ?";
            $params[] = (int) $year;
        }

        $sql .= " ORDER BY d.sort_order ASC, d.created_at DESC";
        $documents = Database::query($sql, $params);

        $this->render('public.documents.index', [
            'title' => 'ศูนย์ดาวน์โหลดเอกสารและระเบียบข้อบังคับ',
            'categories' => $categories,
            'documents' => $documents,
            'selectedCategory' => $categorySlug,
            'keyword' => $keyword,
            'year' => $year,
        ]);
    }

    public function download(string $id): void
    {
        $doc = Database::first("SELECT * FROM documents WHERE id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1", [(int) $id]);
        if (!$doc) {
            Session::flash('error', 'ไม่พบเอกสารที่ระบุหรือเอกสารถูกยกเลิกการเผยแพร่แล้ว');
            $this->redirect(url('documents'));
            return;
        }

        if (empty($doc['file_path'])) {
            Logger::error("Document ID #{$doc['id']} has no file_path associated.");
            Session::flash('error', 'เอกสารนี้ยังไม่มีไฟล์แนบในระบบ กรุณาติดต่อเจ้าหน้าที่สหกรณ์');
            $this->redirect(url('documents'));
            return;
        }

        // File download / preview
        try {
            $filePath = storage_upload_path((string) $doc['file_path']);
        } catch (\InvalidArgumentException $e) {
            Logger::error("Unsafe document path: Document ID #{$doc['id']}");
            $filePath = '';
        }

        if (file_exists($filePath) && is_file($filePath)) {
            // Increment download counter
            Database::execute("UPDATE documents SET download_count = download_count + 1 WHERE id = ?", [$doc['id']]);

            $mimeType = $doc['file_type'] ?: 'application/pdf';
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: inline; filename="' . basename($doc['file_path']) . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($filePath);
            exit;
        } else {
            Logger::error("Missing document file on disk: Document ID #{$doc['id']}, Path: {$filePath}");
            Session::flash('error', 'ขออภัย ไม่พบไฟล์เอกสารในระบบจัดเก็บ กรุณาติดต่อเจ้าหน้าที่สหกรณ์เพื่อขอรับเอกสาร (รหัสเอกสาร #' . $doc['id'] . ')');
            $this->redirect(url('documents'));
        }
    }
}
