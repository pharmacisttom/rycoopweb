<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;
use App\Core\Database;

class SitemapController extends Controller
{
    public function index(): void
    {
        $db = Database::getInstance();
        $baseUrl = rtrim(config('app.url', ''), '/');

        // Static public pages
        $staticUrls = [
            ['loc' => $baseUrl . '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => $baseUrl . '/announcements', 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => $baseUrl . '/calendar', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => $baseUrl . '/loan-readiness', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/news', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => $baseUrl . '/deposits', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => $baseUrl . '/loans', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => $baseUrl . '/calculator', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/welfare', 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/documents', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => $baseUrl . '/about', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/board', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/statistics', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/contact', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/faqs', 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => $baseUrl . '/privacy/policy', 'priority' => '0.4', 'changefreq' => 'yearly'],
            ['loc' => $baseUrl . '/terms', 'priority' => '0.4', 'changefreq' => 'yearly'],
        ];

        // Dynamic news
        $newsArticles = [];
        try {
            $newsArticles = $db->query(
                "SELECT slug, updated_at FROM news WHERE status = 'published' ORDER BY updated_at DESC LIMIT 100"
            )->fetchAll();
        } catch (\Throwable $e) {
            // ignore if table error
        }

        // Dynamic announcements
        $announcements = [];
        try {
            $announcements = $db->query(
                "SELECT slug, updated_at FROM important_announcements WHERE status = 'published' ORDER BY updated_at DESC LIMIT 100"
            )->fetchAll();
        } catch (\Throwable $e) {
            // ignore if table error
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($staticUrls as $url) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($url['loc']) . "</loc>\n";
            echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
            echo "    <changefreq>" . $url['changefreq'] . "</changefreq>\n";
            echo "    <priority>" . $url['priority'] . "</priority>\n";
            echo "  </url>\n";
        }

        foreach ($announcements as $ann) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($baseUrl . '/announcements/' . $ann['slug']) . "</loc>\n";
            echo "    <lastmod>" . date('Y-m-d', strtotime($ann['updated_at'] ?? 'now')) . "</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.8</priority>\n";
            echo "  </url>\n";
        }

        foreach ($newsArticles as $news) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($baseUrl . '/news/' . $news['slug']) . "</loc>\n";
            echo "    <lastmod>" . date('Y-m-d', strtotime($news['updated_at'] ?? 'now')) . "</lastmod>\n";
            echo "    <changefreq>weekly</changefreq>\n";
            echo "    <priority>0.7</priority>\n";
            echo "  </url>\n";
        }

        echo '</urlset>';
        exit;
    }
}
