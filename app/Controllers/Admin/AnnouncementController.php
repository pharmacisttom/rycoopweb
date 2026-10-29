<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\MediaService;

class AnnouncementController extends Controller
{
    public function index(): void
    {
        if (!headers_sent()) { header('Cache-Control: no-store, private'); }
        $status = $this->request->query('status');
        $priority = $this->request->query('priority');

        $where = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($status)) {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if (!empty($priority)) {
            $where[] = "priority = ?";
            $params[] = $priority;
        }

        $whereSql = implode(" AND ", $where);
        $announcements = Database::query(
            "SELECT * FROM important_announcements 
             WHERE {$whereSql} 
             ORDER BY is_pinned DESC, publication_date DESC, created_at DESC",
            $params
        );

        if ($this->request->isAjax()) { $this->json(['success'=>true,'data'=>$announcements]); return; }
        $this->render('admin.announcements.index', [
            'title' => 'จัดการประกาศสำคัญ (Important Announcements)',
            'announcements' => $announcements,
            'status' => $status,
            'priority' => $priority,
        ], 'layouts.admin');
    }

    public function create(): void
    {
        $this->render('admin.announcements.create', [
            'title' => 'สร้างประกาศสำคัญใหม่',
        ], 'layouts.admin');
    }

    public function store(): void
    {
        if (!$this->validateContentPolicy()) { return; }
        $data = $this->validate([
            'title' => 'required',
            'content' => 'required',
            'publication_date' => 'required',
        ]);

        $slug = str_slug($data['title']) . '-' . time();
        $attachmentPath = null;
        $attachmentName = null;

        if ($this->request->hasFile('attachment') && !empty($this->request->file('attachment')['tmp_name'])) {
            try {
                $uploaded = MediaService::upload($this->request->file('attachment'), 'announcements');
                $attachmentPath = $uploaded['path'];
                $attachmentName = $this->request->file('attachment')['name'] ?? 'attachment.pdf';
            } catch (\Exception $e) {
                Session::flash('error', 'อัปโหลดไฟล์แนบไม่สำเร็จ: ' . $e->getMessage());
                $this->redirect(url('admin/announcements/create'));
                return;
            }
        }

        $sql = "INSERT INTO important_announcements 
                (title, slug, content, summary, attachment_path, attachment_name, publication_date, effective_start_date, expiry_date, priority, resolution_no, related_link, status, is_pinned, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $id = Database::insert($sql, [
            $data['title'],
            $slug,
            \App\Services\RichTextService::clean($data['content']),
            $this->request->input('summary'),
            $attachmentPath,
            $attachmentName,
            $data['publication_date'],
            $this->request->input('effective_start_date') ?: null,
            $this->request->input('expiry_date') ?: null,
            $this->request->input('priority', 'general'),
            $this->request->input('resolution_no'),
            $this->request->input('related_link'),
            $this->request->input('status', 'draft'),
            (int)($this->request->input('is_pinned') ? 1 : 0),
            Auth::id()
        ]);

        AuditService::log('important_announcements', 'create', (string)$id, null, ['title' => $data['title']]);
        if ($this->request->isAjax()) { $this->json(['success'=>true,'id'=>(int)$id],201); return; }
        Session::flash('success', 'สร้างประกาศสำคัญเรียบร้อยแล้ว');
        $this->redirect(url('admin/announcements'));
    }

    public function edit(string $id): void
    {
        $announcement = Database::first("SELECT * FROM important_announcements WHERE id = ? AND deleted_at IS NULL LIMIT 1", [(int)$id]);
        if (!$announcement && $this->request->isAjax()) { $this->json(['success'=>false,'message'=>'ไม่พบประกาศ'],404); return; }
        if (!$announcement) {
            Session::flash('error', 'ไม่พบประกาศที่ระบุ');
            $this->redirect(url('admin/announcements'));
            return;
        }

        $this->render('admin.announcements.edit', [
            'title' => 'แก้ไขประกาศสำคัญ: ' . $announcement['title'],
            'announcement' => $announcement,
        ], 'layouts.admin');
    }

    public function update(string $id): void
    {
        $announcement = Database::first("SELECT * FROM important_announcements WHERE id = ? AND deleted_at IS NULL LIMIT 1", [(int)$id]);
        if (!$announcement && $this->request->isAjax()) { $this->json(['success'=>false,'message'=>'ไม่พบประกาศ'],404); return; }
        if (!$announcement) {
            Session::flash('error', 'ไม่พบประกาศที่ระบุ');
            $this->redirect(url('admin/announcements'));
            return;
        }

        if (!$this->validateContentPolicy()) { return; }
        $data = $this->validate([
            'title' => 'required',
            'content' => 'required',
            'publication_date' => 'required',
        ]);

        $attachmentPath = $announcement['attachment_path'];
        $attachmentName = $announcement['attachment_name'];

        if ($this->request->hasFile('attachment') && !empty($this->request->file('attachment')['tmp_name'])) {
            try {
                $uploaded = MediaService::upload($this->request->file('attachment'), 'announcements');
                $attachmentPath = $uploaded['path'];
                $attachmentName = $this->request->file('attachment')['name'] ?? 'attachment.pdf';
            } catch (\Exception $e) {
                Session::flash('error', 'อัปโหลดไฟล์แนบไม่สำเร็จ: ' . $e->getMessage());
                $this->redirect(url("admin/announcements/{$id}/edit"));
                return;
            }
        }

        $sql = "UPDATE important_announcements 
                SET title = ?, content = ?, summary = ?, attachment_path = ?, attachment_name = ?, publication_date = ?, effective_start_date = ?, expiry_date = ?, priority = ?, resolution_no = ?, related_link = ?, status = ?, is_pinned = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?";

        Database::execute($sql, [
            $data['title'],
            \App\Services\RichTextService::clean($data['content']),
            $this->request->input('summary'),
            $attachmentPath,
            $attachmentName,
            $data['publication_date'],
            $this->request->input('effective_start_date') ?: null,
            $this->request->input('expiry_date') ?: null,
            $this->request->input('priority', 'general'),
            $this->request->input('resolution_no'),
            $this->request->input('related_link'),
            $this->request->input('status', 'draft'),
            (int)($this->request->input('is_pinned') ? 1 : 0),
            Auth::id(),
            (int)$id
        ]);

        AuditService::log('important_announcements', 'update', (string)$id, $announcement, $data);
        if ($this->request->isAjax()) { $this->json(['success'=>true]); return; }
        Session::flash('success', 'บันทึกการแก้ไขประกาศสำคัญเรียบร้อยแล้ว');
        $this->redirect(url('admin/announcements'));
    }

    public function destroy(string $id): void
    {
        $a = Database::first("SELECT * FROM important_announcements WHERE id = ? LIMIT 1", [(int) $id]);
        if ($a) {
            Database::execute("UPDATE important_announcements SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('important_announcements', 'delete', (string)$id, $a);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบประกาศเรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบประกาศเรียบร้อยแล้ว');
            $this->redirect(url('admin/announcements'));
        }
    }
    private function validateContentPolicy(): bool
    {
        $status = (string) $this->request->input('status', 'draft');
        $date = (string) $this->request->input('publication_date', '');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $content = (string) $this->request->input('content', '');
        $link = trim((string) $this->request->input('related_link', ''));
        if ($link !== '' && (!filter_var($link,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($link,PHP_URL_SCHEME)),['http','https'],true))) { $this->json(['success'=>false,'message'=>'ลิงก์ต้องเป็น HTTP หรือ HTTPS'],422); return false; }
        if (!in_array($status, ['draft','published','archived'], true) || !$parsed || $parsed->format('Y-m-d') !== $date || mb_strlen((string)$this->request->input('title','')) > 255 || strlen($content) > 60000 || \App\Services\RichTextService::text(\App\Services\RichTextService::clean($content)) === '') {
            $this->json(['success'=>false,'message'=>'กรุณาตรวจชื่อ เนื้อหา วันที่ และสถานะประกาศ'],422); return false;
        }
        if ($status === 'published' && !Auth::hasPermission('announcements','publish')) {
            $this->json(['success'=>false,'message'=>'ไม่มีสิทธิ์เผยแพร่ประกาศ'],403); return false;
        }
        return true;
    }

}
