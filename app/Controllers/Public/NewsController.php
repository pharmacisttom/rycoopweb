<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;

class NewsController extends Controller
{
    public function index(): void
    {
        $catSlug = $this->request->query('cat');
        $categories = Database::query("SELECT * FROM news_categories WHERE status = 'active' ORDER BY sort_order ASC");

        $sql = "SELECT n.*, c.name as category_name, c.slug as category_slug
                FROM news n
                JOIN news_categories c ON n.category_id = c.id
                WHERE n.deleted_at IS NULL AND n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW())";
        $params = [];

        if (!empty($catSlug)) {
            $sql .= " AND c.slug = ?";
            $params[] = $catSlug;
        }

        $sql .= " ORDER BY n.is_pinned DESC, n.publish_at DESC";
        $newsList = Database::query($sql, $params);

        $this->render('public.news.index', [
            'title' => 'ข่าวสารและกิจกรรมประชาสัมพันธ์',
            'categories' => $categories,
            'newsList' => $newsList,
            'selectedCategory' => $catSlug,
        ]);
    }

    public function show(string $slug): void
    {
        $news = Database::first("SELECT n.*, c.name as category_name, c.slug as category_slug, u.name as author_name
                                 FROM news n
                                 JOIN news_categories c ON n.category_id = c.id
                                 LEFT JOIN users u ON n.author_id = u.id
                                 WHERE n.slug = ? AND n.deleted_at IS NULL AND n.workflow_status = 'published' AND (n.publish_at IS NULL OR n.publish_at <= NOW())
                                 LIMIT 1", [$slug]);

        if (!$news) {
            $this->redirect(url('news'));
            return;
        }

        // Increment views count
        Database::execute("UPDATE news SET views_count = views_count + 1 WHERE id = ?", [$news['id']]);

        // Related News
        $related = Database::query("SELECT * FROM news WHERE category_id = ? AND id != ? AND deleted_at IS NULL AND workflow_status = 'published' AND (publish_at IS NULL OR publish_at <= NOW()) ORDER BY publish_at DESC LIMIT 3", [$news['category_id'], $news['id']]);

        $this->render('public.news.show', [
            'title' => $news['title'],
            'news' => $news,
            'related' => $related,
        ]);
    }
}
