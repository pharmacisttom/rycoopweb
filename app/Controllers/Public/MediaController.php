<?php
declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Controller;

final class MediaController extends Controller
{
    public function show(string $folder, string $filename): void
    {
        $folders = ['popups', 'hero_slides'];

        if (!in_array($folder, $folders, true) ||
            !preg_match(
                '/^[a-f0-9]{32}\.(jpg|jpeg|png|webp|gif)$/',
                $filename
            )) {
            http_response_code(404);
            exit;
        }

        $mimes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        try {
            $base = realpath(storage_upload_path());
            $path = realpath(
                storage_upload_path($folder . '/' . $filename)
            );

            if (!$base || !$path ||
                !str_starts_with(
                    $path,
                    $base . DIRECTORY_SEPARATOR
                ) ||
                !is_file($path) ||
                !is_readable($path)) {
                http_response_code(404);
                exit;
            }

            $size = filesize($path);
            if ($size === false || $size > 20 * 1024 * 1024) {
                http_response_code(404);
                exit;
            }

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

            if ($mime !== ($mimes[$ext] ?? null) ||
                @getimagesize($path) === false) {
                http_response_code(415);
                exit;
            }

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $size);
            header('X-Content-Type-Options: nosniff');
            header('Content-Disposition: inline');
            header('Cache-Control: public, max-age=3600');

            readfile($path);
            exit;
        } catch (\Throwable $e) {
            error_log('Public media read failed');
            http_response_code(404);
            exit;
        }
    }
}
