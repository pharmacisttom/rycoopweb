<?php
declare(strict_types=1);
namespace App\Services;

/** Gallery metadata lives in the existing news.gallery_images JSON column. */
final class NewsGalleryService
{
    public const MAX_IMAGES = 20;
    public const MAX_TOTAL_BYTES = 12 * 1024 * 1024;

    public static function decode(mixed $value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        if (!is_array($value)) return [];
        $images = [];
        foreach ($value as $entry) {
            if (is_string($entry)) $entry = ['src' => $entry];
            if (!is_array($entry) || !is_string($entry['src'] ?? null)) continue;
            $images[] = ['src' => resolve_media_url($entry['src']), 'thumbnail' => resolve_media_url($entry['thumbnail'] ?? $entry['src']),
                'alt' => (string)($entry['alt'] ?? ''), 'caption' => (string)($entry['caption'] ?? '')];
        }
        return $images;
    }

    /** Only existing references from THIS article or freshly decoded uploads are accepted. */
    public static function prepare(mixed $input, array $existing, array &$created): array
    {
        if (!is_array($input) || !array_is_list($input) || count($input) > self::MAX_IMAGES) {
            throw new \InvalidArgumentException('อัลบั้มต้องเป็นรายการภาพไม่เกิน 20 ภาพ');
        }
        $known = array_column($existing, null, 'src');
        $result = []; $total = 0;
        foreach ($input as $entry) {
            if (!is_array($entry) || !is_string($entry['src'] ?? null)) throw new \InvalidArgumentException('ข้อมูลภาพไม่ถูกต้อง');
            if (!is_string($entry['alt'] ?? '') || !is_string($entry['caption'] ?? '')) throw new \InvalidArgumentException('คำบรรยายภาพต้องเป็นข้อความ');
            $alt = trim((string)($entry['alt'] ?? '')); $caption = trim((string)($entry['caption'] ?? ''));
            if (mb_strlen($alt) > 255 || mb_strlen($caption) > 1000) throw new \InvalidArgumentException('คำบรรยายภาพยาวเกินกำหนด');
            $src = $entry['src'];
            if (isset($known[$src])) $image = $known[$src];
            else {
                if (!preg_match('#^data:image/(?:jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#D', $src, $matches) || strlen($src) > 7 * 1024 * 1024) {
                    throw new \InvalidArgumentException('กรุณาอัปโหลดภาพ JPEG, PNG หรือ WebP ใหม่');
                }
                $binary = base64_decode($matches[1], true);
                if ($binary === false || strlen($binary) > 5 * 1024 * 1024) throw new \InvalidArgumentException('แต่ละภาพต้องไม่เกิน 5 MB');
                $total += strlen($binary);
                if ($total > self::MAX_TOTAL_BYTES) throw new \InvalidArgumentException('ภาพใหม่รวมกันต้องไม่เกิน 12 MB ต่อการบันทึก');
                $image = self::store($binary, $created);
            }
            $result[] = [...$image, 'alt' => $alt, 'caption' => $caption];
        }
        return $result;
    }

    private static function store(string $binary, array &$created): array
    {
        $info = @getimagesizefromstring($binary);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ($info['mime'] ?? '') !== $mime
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000 || $info[0] * $info[1] > 16000000) {
            throw new \InvalidArgumentException('ไฟล์ภาพเสียหรือเกิน 8000 พิกเซล / 16 ล้านพิกเซล');
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) throw new \RuntimeException('Image processing unavailable');
        $source = @imagecreatefromstring($binary);
        if (!$source) throw new \InvalidArgumentException('อ่านไฟล์ภาพไม่ได้');
        $directory = storage_upload_path('news/gallery');
        try {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new \RuntimeException('Image storage unavailable');
            $name = bin2hex(random_bytes(16)); $paths = [];
            foreach (['src' => 1920, 'thumbnail' => 480] as $key => $limit) {
                $ratio = min(1, $limit / max($info[0], $info[1]));
                $width = max(1, (int)round($info[0] * $ratio)); $height = max(1, (int)round($info[1] * $ratio));
                $canvas = imagecreatetruecolor($width, $height);
                try {
                    imagealphablending($canvas, false); imagesavealpha($canvas, true);
                    imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
                    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
                    $file = $directory . '/' . $name . '-' . $key . '.webp';
                    $created[] = $file;
                    if (!imagewebp($canvas, $file, 85)) throw new \RuntimeException('Image write failed');
                    $paths[$key] = '/storage/uploads/news/gallery/' . basename($file);
                } finally { imagedestroy($canvas); }
            }
            // Re-encoding pixels removes EXIF/GPS and any appended executable payload.
            return $paths;
        } finally { imagedestroy($source); }
    }

    public static function cleanup(array $created): void
    {
        foreach ($created as $file) if (is_file($file)) @unlink($file);
    }
}
