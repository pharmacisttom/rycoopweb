<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/**
 * Get current Request instance
 */
if (!function_exists('request')) {
    function request(): Request
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Request();
        }
        return $instance;
    }
}

/**
 * Get environment variable with fallback
 */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $processValue = getenv($key);
        if ($processValue !== false) {
            return $processValue;
        }

        static $envVars = null;
        if ($envVars === null) {
            $envVars = [];
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (str_contains($line, '=')) {
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim($k);
                        $v = trim($v);
                        if (str_starts_with($v, '"') && str_ends_with($v, '"')) {
                            $v = substr($v, 1, -1);
                        } elseif (str_starts_with($v, "'") && str_ends_with($v, "'")) {
                            $v = substr($v, 1, -1);
                        }
                        if (strtolower($v) === 'true') $v = true;
                        elseif (strtolower($v) === 'false') $v = false;
                        elseif (strtolower($v) === 'null') $v = null;
                        $envVars[$k] = $v;
                    }
                }
            }
        }

        return $envVars[$key] ?? $default;
    }
}

/**
 * Get configuration value using dot notation (e.g. config('app.name'))
 */
if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset($configs[$file])) {
            $path = dirname(__DIR__, 2) . "/config/{$file}.php";
            if (file_exists($path)) {
                $configs[$file] = require $path;
            } else {
                $configs[$file] = [];
            }
        }

        $val = $configs[$file];
        foreach ($parts as $part) {
            if (is_array($val) && array_key_exists($part, $val)) {
                $val = $val[$part];
            } else {
                return $default;
            }
        }
        return $val;
    }
}

/**
 * Generate full URL
 */
if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        static $baseUrl = null;
        if ($baseUrl === null) {
            $configured = config('app.url');
            if (!empty($configured) && !str_contains($configured, 'localhost')) {
                $baseUrl = rtrim($configured, '/');
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
                $scriptDir = rtrim($scriptDir, '/');
                $baseUrl = rtrim("{$protocol}{$host}{$scriptDir}", '/');
            }
        }
        $path = ltrim($path, '/');
        return $path === '' ? $baseUrl : "{$baseUrl}/{$path}";
    }
}

/**
 * Asset URL helper
 */
if (!function_exists('asset')) {
    function asset(string $path = ''): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

/**
 * Upload URL helper
 */
if (!function_exists('storage_url')) {
    function storage_url(string $path = ''): string
    {
        if ($path === '') {
            return url('storage/uploads');
        }
        $resolved = resolve_media_url($path);
        if ($resolved === null || preg_match('#^https?://#i', $resolved) === 1) {
            return $resolved ?? url('storage/uploads');
        }
        return url(ltrim($resolved, '/'));
    }
}

/**
 * Resolve a database media value to a public URL without prefix duplication.
 * Absolute HTTP(S) URLs and root-relative public paths are already resolved.
 */
if (!function_exists('resolve_media_url')) {
    function resolve_media_url(mixed $value, ?string $fallback = null): ?string
    {
        if ($value === null) {
            return $fallback;
        }

        $path = trim((string) $value);
        if ($path === '') {
            return $fallback;
        }

        $path = str_replace('\\', '/', $path);
        if (preg_match('#^https?://#i', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, 'assets/')) {
            return '/' . $path;
        }
        if (str_starts_with($path, 'storage/uploads/')) {
            return '/' . $path;
        }

        return '/storage/uploads/' . ltrim($path, '/');
    }
}

if (!function_exists('media_url')) {
    function media_url(?string $path, string $fallback = ''): string
    {
        return resolve_media_url($path, $fallback) ?? $fallback;
    }
}

/**
 * Resolve an upload-relative value to the canonical, non-public filesystem.
 * Throws for traversal, URLs, drive paths, and other values outside uploads.
 */
if (!function_exists('storage_upload_path')) {
    function storage_upload_path(?string $value = ''): string
    {
        $path = str_replace('\\', '/', trim((string) $value));
        if (str_contains($path, "\0") || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1) {
            throw new InvalidArgumentException('Unsafe upload path.');
        }

        $path = ltrim($path, '/');
        foreach (['storage/uploads/', 'public/storage/uploads/', 'public/uploads/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));
        if (in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new InvalidArgumentException('Unsafe upload path.');
        }

        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }
}

/**
 * Escape HTML
 */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if (is_array($value)) {
            return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (is_object($value) && !method_exists($value, '__toString')) {
            return htmlspecialchars(get_class($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * CSRF Token helper
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

/**
 * CSRF Hidden Input Field helper
 */
if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
    }
}

/**
 * Flash session data / Old input
 */
if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::getOldInput($key, $default);
    }
}

/**
 * Current authenticated user
 */
if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

/**
 * Check permission
 */
if (!function_exists('has_permission')) {
    function has_permission(string $module, string $action): bool
    {
        return Auth::hasPermission($module, $action);
    }
}

/**
 * Check role
 */
if (!function_exists('has_role')) {
    function has_role(string|array $roles): bool
    {
        return Auth::hasRole($roles);
    }
}

/**
 * Format currency / financial numbers
 */
if (!function_exists('format_money')) {
    function format_money(float|int|string|null $number, int $decimals = 2): string
    {
        if ($number === null || $number === '') {
            return '0.00';
        }
        return number_format((float) $number, $decimals);
    }
}

/**
 * Thai Buddhist Date Formatter (พ.ศ.)
 */
if (!function_exists('thai_date')) {
    function thai_date(?string $datetime, bool $includeTime = false, bool $shortMonth = false): string
    {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
            return '-';
        }
        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return '-';
        }

        $thaiMonthsShort = [
            1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
        ];

        $thaiMonthsFull = [
            1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
        ];

        $day = date('j', $timestamp);
        $monthNum = (int) date('n', $timestamp);
        $year = (int) date('Y', $timestamp) + 543;
        $month = $shortMonth ? $thaiMonthsShort[$monthNum] : $thaiMonthsFull[$monthNum];

        $result = "{$day} {$month} {$year}";
        if ($includeTime) {
            $time = date('H:i น.', $timestamp);
            $result .= " {$time}";
        }
        return $result;
    }
}

/**
 * คืนชื่อเดือนภาษาไทยแบบย่อจากลำดับเดือน 1-12
 */
if (!function_exists('thai_month')) {
    function thai_month(int|string $month): string
    {
        $months = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
        ];

        return $months[(int) $month] ?? '';
    }
}

/**
 * Alias for thai_month
 */
if (!function_exists('thai_month_short')) {
    function thai_month_short(int|string $month): string
    {
        return thai_month($month);
    }
}

/**
 * อ่านข้อความแจ้งเตือนครั้งเดียวจาก session
 */
if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}

/**
 * JSON Response helper
 */
if (!function_exists('json_response')) {
    function json_response(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

/**
 * Slug generator for Thai & English
 */
if (!function_exists('str_slug')) {
    function str_slug(string $title, string $separator = '-'): string
    {
        $title = trim($title);
        $title = preg_replace('/[^\p{L}\p{M}\p{N}\s_-]+/u', '', $title);
        $title = preg_replace('/[\s_-]+/u', $separator, $title);
        return trim($title, $separator);
    }
}
