<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Exceptions\SecurityGateException;
use App\Services\AdminCrudService;
use App\Services\AuditService;
use App\Services\MediaService;

/**
 * Media Library API for CMS image pickers.
 *
 * All endpoints are mounted behind /api/admin AuthMiddleware.
 * No public endpoint enumerates unreviewed media records.
 * Uploads are limited to raster images and re-encoded to WebP without EXIF.
 */
final class MediaLibraryController extends Controller
{
    private static function authorize(string $action): void
    {
        if (!Auth::check()) {
            throw new SecurityGateException('Administrator authentication required', 401, 'UNAUTHORIZED');
        }
        $role = (string)(Auth::user()['role_slug'] ?? Auth::user()['role'] ?? '');
        if ($role === 'member' || !Auth::hasPermission('news', 'view')
            || ($action !== 'view' && !Auth::hasPermission('news', $action))) {
            throw new SecurityGateException('Not authorized to manage CMS media', 403, 'FORBIDDEN');
        }
    }

    private static function id(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            throw new \InvalidArgumentException('Invalid media ID');
        }
        return (int)$value;
    }

    private static function record(int $id, bool $lock = false): array
    {
        $row = Database::first(
            'SELECT * FROM media WHERE id=? AND deleted_at IS NULL'
            . ($lock ? AdminCrudService::lock() : ''),
            [$id]
        );
        if (!$row || !str_starts_with((string)$row['mime_type'], 'image/')) {
            throw new SecurityGateException('Image not found', 404, 'NOT_FOUND');
        }
        return $row;
    }

    private static function visible(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'url' => resolve_media_url($row['path']),
            'preview_url' => '/api/admin/media/' . (int)$row['id'] . '/image',
            'original_name' => $row['original_name'],
            'mime_type' => $row['mime_type'],
            'file_size' => (int)$row['file_size'],
            'folder' => $row['folder'],
            'alt_text' => $row['alt_text'],
            'caption' => $row['caption'],
            'created_at' => $row['created_at'],
            'version' => hash('sha256', implode('|', [
                $row['id'], $row['path'], $row['alt_text'] ?? '', $row['caption'] ?? '',
                $row['deleted_at'] ?? ''
            ])),
        ];
    }

    public function index(): void
    {
        AdminCrudService::respond($this->response, function (): array {
            self::authorize('view');
            $page = max(1, min(100000, (int)$this->request->query('page', 1)));
            $limit = max(1, min(50, (int)$this->request->query('per_page', 24)));
            $folder = trim((string)$this->request->query('folder', ''));
            $search = trim((string)$this->request->query('search', ''));
            if ($folder !== '' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/D', $folder)) {
                throw new \InvalidArgumentException('Invalid folder');
            }
            if (mb_strlen($search) > 100) {
                throw new \InvalidArgumentException('Search term too long');
            }
            $where = "deleted_at IS NULL AND mime_type IN ('image/jpeg','image/png','image/webp','image/gif')";
            $params = [];
            if ($folder !== '') {
                $where .= ' AND folder=?';
                $params[] = $folder;
            }
            if ($search !== '') {
                $where .= " AND (original_name LIKE ? OR alt_text LIKE ? OR caption LIKE ?)";
                $term = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
                array_push($params, $term, $term, $term);
            }
            $total = (int)Database::value("SELECT COUNT(*) FROM media WHERE $where", $params);
            $offset = ($page - 1) * $limit;
            $rows = Database::query("SELECT * FROM media WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset", $params);
            return ['data' => array_map([self::class, 'visible'], $rows), 'pagination' =>
                ['page' => $page, 'per_page' => $limit, 'total' => $total,
                    'pages' => (int)ceil($total / $limit)]];
        });
    }

    public function show(string $id): void
    {
        AdminCrudService::respond($this->response, static function () use ($id): array {
            self::authorize('view');
            return ['data' => self::visible(self::record(self::id($id)))];
        });
    }

    /**
     * Serve a private CMS preview after verified staff authorization.
     * This never exposes the filesystem path or permits public folder browsing.
     */
    public function image(string $id): void
    {
        self::authorize('view');
        $row = self::record(self::id($id));
        $allowed = ['image/webp', 'image/jpeg', 'image/png', 'image/gif'];
        if (!in_array((string)$row['mime_type'], $allowed, true)) {
            throw new SecurityGateException('Unsupported image', 404, 'NOT_FOUND');
        }

        $base = realpath(storage_upload_path());
        $candidate = storage_upload_path((string)$row['path']);
        $path = realpath($candidate);
        if ($base === false || $path === false
            || !str_starts_with($path, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            || !is_file($path) || !is_readable($path)) {
            throw new SecurityGateException('Image unavailable', 404, 'NOT_FOUND');
        }
        $actual = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($actual !== $row['mime_type'] || !in_array($actual, $allowed, true)) {
            throw new SecurityGateException('Invalid image', 404, 'NOT_FOUND');
        }
        $size = filesize($path);
        if ($size === false) {
            throw new SecurityGateException('Image unavailable', 404, 'NOT_FOUND');
        }
        $this->response
            ->header('Content-Type', (string)$actual)
            ->header('Content-Length', (string)$size)
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Disposition', 'inline')
            ->sendHeaders();
        readfile($path);
        exit;
    }

    public function upload(): void
    {
        AdminCrudService::respond($this->response, function (): array {
            self::authorize('create');
            $file = $this->request->file('file');
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
                throw new \InvalidArgumentException('Select a valid image');
            }
            $size = (int)($file['size'] ?? 0);
            if ($size < 1 || $size > 10 * 1024 * 1024) {
                throw new \InvalidArgumentException('Maximum image size is 10 MB');
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png',
                'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                throw new \InvalidArgumentException('Only JPEG, PNG and WebP are supported');
            }
            $dimensions = @getimagesize($file['tmp_name']);
            if (!$dimensions || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1
                || (int)$dimensions[0] * (int)$dimensions[1] > 16000000) {
                throw new \InvalidArgumentException('Invalid image or too many pixels');
            }
            if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
                throw new \RuntimeException('GD WebP support required');
            }
            $folder = (string)$this->request->input('folder', 'general');
            if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/D', $folder)) {
                throw new \InvalidArgumentException('Invalid folder');
            }
            $alt = trim((string)$this->request->input('alt_text', ''));
            $caption = trim((string)$this->request->input('caption', ''));
            if (mb_strlen($alt) > 255 || mb_strlen($caption) > 255) {
                throw new \InvalidArgumentException('Alt text or caption too long');
            }
            $raw = file_get_contents($file['tmp_name']);
            $image = $raw === false ? false : @imagecreatefromstring($raw);
            unset($raw);
            if (!$image) {
                throw new \InvalidArgumentException('Unreadable image');
            }
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, 2200 / max($width, $height));
            $w = max(1, (int)round($width * $scale));
            $h = max(1, (int)round($height * $scale));
            $processed = imagecreatetruecolor($w, $h);
            imagealphablending($processed, false);
            imagesavealpha($processed, true);
            imagecopyresampled($processed, $image, 0, 0, 0, 0, $w, $h, $width, $height);
            imagedestroy($image);
            $filename = bin2hex(random_bytes(16)) . '.webp';
            $directory = storage_upload_path($folder);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                imagedestroy($processed);
                throw new \RuntimeException('Storage unavailable');
            }
            $destination = rtrim($directory, '/') . '/' . $filename;
            $saved = imagewebp($processed, $destination, 82);
            imagedestroy($processed);
            if (!$saved) {
                throw new \RuntimeException('Image processing failed');
            }
            @chmod($destination, 0640);
            $path = $folder . '/' . $filename;
            try {
                $id = Database::transaction(static function () use ($filename, $file, $destination, $path, $folder, $alt, $caption): int {
                    $id = (int)Database::insert(
                        'INSERT INTO media (filename, original_name, mime_type, file_size, path, folder, alt_text, caption, uploaded_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())',
                        [$filename, mb_substr(basename((string)$file['name']), 0, 255),
                            'image/webp', filesize($destination), $path, $folder,
                            $alt, $caption, Auth::id()]
                    );
                    AuditService::log('media', 'create', (string)$id, null,
                        ['path' => $path, 'folder' => $folder], required: true);
                    return $id;
                });
            } catch (\Throwable $e) {
                @unlink($destination);
                throw $e;
            }
            return ['data' => self::visible(self::record($id))];
        }, 201);
    }

    public function update(string $id): void
    {
        AdminCrudService::respond($this->response, function () use ($id): array {
            self::authorize('update');
            $mediaId = self::id($id);
            $alt = trim((string)$this->request->input('alt_text', ''));
            $caption = trim((string)$this->request->input('caption', ''));
            $version = (string)$this->request->input('version', '');
            if (mb_strlen($alt) > 255 || mb_strlen($caption) > 255) {
                throw new \InvalidArgumentException('Alt text or caption too long');
            }
            return Database::transaction(static function () use ($mediaId, $alt, $caption, $version): array {
                $old = self::record($mediaId, true);
                if ($version === '' || !hash_equals(self::visible($old)['version'], $version)) {
                    throw new SecurityGateException('Image was modified; reload and retry', 409, 'STALE_VERSION');
                }
                Database::execute('UPDATE media SET alt_text=?, caption=? WHERE id=?', [$alt, $caption, $mediaId]);
                AuditService::log('media', 'update', (string)$mediaId,
                    ['alt_text' => $old['alt_text'], 'caption' => $old['caption']],
                    ['alt_text' => $alt, 'caption' => $caption], required: true);
                return ['data' => self::visible(self::record($mediaId))];
            });
        });
    }

    public function destroy(string $id): void
    {
        AdminCrudService::respond($this->response, function () use ($id): array {
            self::authorize('delete');
            $mediaId = self::id($id);
            $version = (string)$this->request->input('version', '');
            return Database::transaction(static function () use ($mediaId, $version): array {
                $old = self::record($mediaId, true);
                if ($version === '' || !hash_equals(self::visible($old)['version'], $version)) {
                    throw new SecurityGateException('Image was modified; reload and retry', 409, 'STALE_VERSION');
                }
                Database::execute('UPDATE media SET deleted_at=NOW() WHERE id=?', [$mediaId]);
                AuditService::log('media', 'delete', (string)$mediaId,
                    ['path' => $old['path']], ['deleted' => true], required: true);
                return ['id' => $mediaId, 'deleted' => true];
            });
        });
    }
}
