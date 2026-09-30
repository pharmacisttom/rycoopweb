<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use RuntimeException;

class MediaService
{
    public static function upload(array $file, string $folder = 'general', ?string $altText = null): array
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/', $folder) !== 1) {
            throw new RuntimeException('Invalid media folder.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('การอัปโหลดไฟล์ล้มเหลว (Error Code: ' . $file['error'] . ')');
        }

        $config = config('security.upload');
        $maxSize = $config['max_size'] ?? (20 * 1024 * 1024);

        if ($file['size'] > $maxSize) {
            throw new RuntimeException('ขนาดไฟล์เกินกำหนด (สูงสุด ' . number_format($maxSize / (1024 * 1024)) . ' MB)');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $blocked = $config['blocked_extensions'] ?? [];

        if (in_array($ext, $blocked, true)) {
            throw new RuntimeException('ไม่อนุญาตให้อัปโหลดไฟล์นามสกุลนี้เพื่อความปลอดภัย');
        }

        // Verify MIME type using finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedImages = $config['allowed_images'] ?? [];
        $allowedDocs = $config['allowed_documents'] ?? [];
        $allowed = array_merge($allowedImages, $allowedDocs);

        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('ประเภทไฟล์ไม่ได้รับอนุญาต (MIME: ' . $mime . ')');
        }


        $extensionMimes = [
            'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
            'webp' => ['image/webp'], 'gif' => ['image/gif'], 'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
        if (!isset($extensionMimes[$ext]) || !in_array($mime, $extensionMimes[$ext], true)) {
            throw new RuntimeException('File extension does not match its content type.');
        }
        if (in_array($mime, $allowedImages, true) && @getimagesize($file['tmp_name']) === false) {
            throw new RuntimeException('Invalid image content.');
        }

        // Generate safe randomized filename
        $uniqueName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDir = storage_upload_path($folder);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to create media directory.');
        }

        $targetPath = $targetDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('ไม่สามารถบันทึกไฟล์ลงในโฟลเดอร์ปลายทางได้');
        }

        // Insert into media table
        $sql = "INSERT INTO media (filename, original_name, mime_type, file_size, path, folder, alt_text, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $userId = Auth::id();
        try {
            $mediaId = Database::insert($sql, [
                $uniqueName,
                basename((string) $file['name']),
                $mime,
                $file['size'],
                "{$folder}/{$uniqueName}",
                $folder,
                $altText ?: pathinfo((string) $file['name'], PATHINFO_FILENAME),
                $userId
            ]);
        } catch (\Throwable $e) {
            @unlink($targetPath);
            throw $e;
        }

        return [
            'id' => $mediaId,
            'filename' => $uniqueName,
            'original_name' => $file['name'],
            'path' => "{$folder}/{$uniqueName}",
            'url' => resolve_media_url("{$folder}/{$uniqueName}"),
            'mime_type' => $mime,
            'mime' => $mime,
            'size' => $file['size'],
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
