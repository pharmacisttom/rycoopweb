<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Streams a gallery image only if it belongs to the requested article.
 * The public endpoint also requires the article to be published.
 * Never publish /storage or private shared storage through nginx.
 */
final class NewsGalleryImageController extends Controller
{
    public function published(string $id, string $filename): void
    {
        $this->stream($id, $filename, false);
    }

    public function admin(string $id, string $filename): void
    {
        $this->stream($id, $filename, true);
    }

    private function stream(string $id, string $filename, bool $admin): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . ($admin ? 'private, no-store' : 'public, max-age=300'));
        if (!preg_match('/^[1-9][0-9]*$/D', $id)
            || !preg_match('/^[a-f0-9]{32}-(?:src|thumbnail)\.webp$/D', $filename)) {
            http_response_code(404); return;
        }
        if ($admin && (!Auth::check() || !Auth::hasPermission('news', 'view'))) {
            http_response_code(403); return;
        }
        try {
            $statusCondition = $admin ? '' : " AND workflow_status='published' AND (publish_at IS NULL OR publish_at<=NOW())";
            $row = Database::first(
                'SELECT gallery_images FROM news WHERE id=? AND deleted_at IS NULL' . $statusCondition,
                [(int)$id]
            );
            if (!$row) { http_response_code(404); return; }
            $entries = json_decode((string)($row['gallery_images'] ?? '[]'), true);
            if (!is_array($entries)) { http_response_code(404); return; }
            $authorized = false;
            foreach ($entries as $entry) {
                if (is_string($entry)) $entry = ['src' => $entry];
                if (!is_array($entry)) continue;
                foreach (['src', 'thumbnail'] as $variant) {
                    $src = (string)($entry[$variant] ?? '');
                    $path = parse_url($src, PHP_URL_PATH);
                    if (is_string($path) && basename($path) === $filename
                        && str_contains($path, '/news/gallery/')) {
                        $authorized = true; break 2;
                    }
                }
            }
            if (!$authorized) { http_response_code(404); return; }

            $root = realpath(storage_upload_path('news/gallery'));
            $file = $root ? realpath($root . DIRECTORY_SEPARATOR . $filename) : false;
            if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)
                || !is_file($file) || !is_readable($file)
                || (new \finfo(FILEINFO_MIME_TYPE))->file($file) !== 'image/webp'
                || @getimagesize($file) === false) {
                http_response_code(404); return;
            }
            header('Content-Type: image/webp');
            header('Content-Length: ' . filesize($file));
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($file);
        } catch (\Throwable $e) {
            error_log('News gallery read failed: ' . get_class($e));
            http_response_code(404);
        }
    }
}
