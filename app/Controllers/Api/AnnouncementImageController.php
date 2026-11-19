<?php
declare(strict_types=1);
namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

final class AnnouncementImageController extends Controller
{
    public function published(string $id): void { $this->stream($id, false); }
    public function admin(string $id): void { $this->stream($id, true); }

    private function stream(string $id, bool $admin): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . ($admin ? 'private, no-store' : 'public, max-age=300'));
        if (!preg_match('/^[1-9][0-9]*$/D', $id)) { http_response_code(404); return; }
        if ($admin && (!Auth::check() || !Auth::hasPermission('announcements','view'))) {
            http_response_code(403); return;
        }
        try {
            $condition = $admin ? '' : " AND status='published' AND publication_date<=CURDATE()
                AND (expiry_date IS NULL OR expiry_date>=CURDATE())";
            $row = Database::first(
                "SELECT attachment_path FROM important_announcements WHERE id=? AND deleted_at IS NULL" . $condition,
                [(int)$id]
            );
            $stored = (string)($row['attachment_path'] ?? '');
            if (!$row || !$stored) { http_response_code(404); return; }
            $base = realpath(storage_upload_path());
            $file = realpath(storage_upload_path($stored));
            if (!$base || !$file || !str_starts_with($file, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
                || !is_file($file) || !is_readable($file)) {
                http_response_code(404); return;
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                || @getimagesize($file) === false) { http_response_code(415); return; }
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($file));
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($file);
        } catch (\Throwable $e) {
            error_log('Announcement image read failed: ' . get_class($e));
            http_response_code(404);
        }
    }
}
