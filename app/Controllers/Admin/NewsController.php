<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;

class NewsController extends Controller
{
    public function index(): void
    {
        $newsList = Database::query("SELECT n.*, c.name as category_name, u.name as author_name 
                                    FROM news n 
                                    JOIN news_categories c ON n.category_id = c.id 
                                    LEFT JOIN users u ON n.author_id = u.id 
                                    WHERE n.deleted_at IS NULL 
                                    ORDER BY n.created_at DESC");
        $newsList = array_map(static function (array $news): array {
            $news['cover_image'] = resolve_media_url($news['cover_image'] ?? null);
            return $news;
        }, $newsList);

        $this->render('admin.news.index', [
            'title' => 'จัดการข่าวสารและกิจกรรม',
            'newsList' => $newsList,
        ], 'layouts.admin');
    }

    public function create(): void
    {
        $categories = Database::query("SELECT * FROM news_categories WHERE status = 'active' ORDER BY sort_order ASC");

        $this->render('admin.news.form', [
            'title' => 'สร้างข่าวสารใหม่',
            'news' => null,
            'categories' => $categories,
        ], 'layouts.admin');
    }

    public function store(): void
    {
        $data = $this->validate([
            'category_id' => 'required|numeric',
            'title' => 'required|min:5|max:255',
            'content' => 'required',
        ]);

        $slug = str_slug($data['title']) . '-' . time();
        $authorId = Auth::id();
        $isPinned = (int) ($this->request->input('is_pinned') ? 1 : 0);
        $isFeatured = (int) ($this->request->input('is_featured') ? 1 : 0);
        $workflowStatus = $this->request->input('workflow_status', 'published');

        $sql = "INSERT INTO news (category_id, title, slug, summary, content, tags, is_pinned, is_featured, workflow_status, author_id, publish_at, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";

        $id = Database::insert($sql, [
            $data['category_id'],
            $data['title'],
            $slug,
            $this->request->input('summary'),
            $data['content'],
            $this->request->input('tags'),
            $isPinned,
            $isFeatured,
            $workflowStatus,
            $authorId,
            $authorId
        ]);

        AuditService::log('news', 'create', (string)$id, null, ['title' => $data['title'], 'status' => $workflowStatus]);
        Session::flash('success', 'สร้างข่าวสารเรียบร้อยแล้ว');
        $this->redirect(url('admin/news'));
    }

    public function edit(string $id): void
    {
        $news = Database::first("SELECT * FROM news WHERE id = ? AND deleted_at IS NULL LIMIT 1", [(int) $id]);
        if (!$news) {
            $this->redirect(url('admin/news'));
            return;
        }

        $categories = Database::query("SELECT * FROM news_categories WHERE status = 'active' ORDER BY sort_order ASC");

        $this->render('admin.news.form', [
            'title' => 'แก้ไขข่าวสาร: ' . $news['title'],
            'news' => $news,
            'categories' => $categories,
        ], 'layouts.admin');
    }

    public function update(string $id): void
    {
        $news = Database::first("SELECT * FROM news WHERE id = ? AND deleted_at IS NULL LIMIT 1", [(int) $id]);
        if (!$news) {
            $this->redirect(url('admin/news'));
            return;
        }

        $data = $this->validate([
            'category_id' => 'required|numeric',
            'title' => 'required|min:5|max:255',
            'content' => 'required',
        ]);

        $isPinned = (int) ($this->request->input('is_pinned') ? 1 : 0);
        $isFeatured = (int) ($this->request->input('is_featured') ? 1 : 0);
        $workflowStatus = $this->request->input('workflow_status', 'published');

        $sql = "UPDATE news SET 
                category_id = ?, title = ?, summary = ?, content = ?, tags = ?, 
                is_pinned = ?, is_featured = ?, workflow_status = ?, updated_by = ?, updated_at = NOW() 
                WHERE id = ?";

        Database::execute($sql, [
            $data['category_id'],
            $data['title'],
            $this->request->input('summary'),
            $data['content'],
            $this->request->input('tags'),
            $isPinned,
            $isFeatured,
            $workflowStatus,
            Auth::id(),
            (int) $id
        ]);

        AuditService::log('news', 'update', (string)$id, $news, ['title' => $data['title'], 'status' => $workflowStatus]);
        Session::flash('success', 'บันทึกการแก้ไขข่าวสารเรียบร้อยแล้ว');
        $this->redirect(url('admin/news'));
    }

    public function destroy(string $id): void
    {
        $news = Database::first("SELECT * FROM news WHERE id = ? AND deleted_at IS NULL LIMIT 1", [(int) $id]);
        if ($news) {
            Database::execute("UPDATE news SET deleted_at = NOW(), updated_by = ? WHERE id = ?", [Auth::id(), (int) $id]);
            AuditService::log('news', 'delete', (string)$id, $news, ['deleted_at' => date('Y-m-d H:i:s')]);
        }

        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'message' => 'ลบข่าวสารเรียบร้อยแล้ว']);
        } else {
            Session::flash('success', 'ลบข่าวสารเรียบร้อยแล้ว');
            $this->redirect(url('admin/news'));
        }
    }
}
