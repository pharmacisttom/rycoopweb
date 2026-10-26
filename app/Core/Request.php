<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $bodyParams;
    private array $files;
    private array $server;
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->parseUri();
        $this->queryParams = $this->sanitizeArray($_GET);
        $this->bodyParams = $this->parseBody();
        $this->files = $_FILES ?? [];
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
    }

    private function parseUri(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($baseDir !== '' && $baseDir !== '/' && str_starts_with($requestUri, $baseDir)) {
            $requestUri = substr($requestUri, strlen($baseDir));
        }

        $parentDir = rtrim(str_replace('\\', '/', dirname($baseDir)), '/');
        if ($parentDir !== '' && $parentDir !== '/' && str_starts_with($requestUri, $parentDir)) {
            $requestUri = substr($requestUri, strlen($parentDir));
        }

        if (str_starts_with($requestUri, '/public')) {
            $requestUri = substr($requestUri, 7);
        }

        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtoupper($name)] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'X_CSRF_TOKEN'])) {
                $headers[str_replace('_', '-', $key)] = $value;
            }
        }
        return $headers;
    }

    private function parseBody(): array
    {
        if ($this->method === 'POST') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $raw = file_get_contents('php://input');
                $json = json_decode($raw, true);
                return is_array($json) ? $this->sanitizeArray($json) : [];
            }
            return $this->sanitizeArray($_POST);
        }
        return [];
    }

    private function sanitizeArray(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $val) {
            $cleanKey = htmlspecialchars(trim((string)$key), ENT_QUOTES, 'UTF-8');
            if (is_array($val)) {
                $clean[$cleanKey] = $this->sanitizeArray($val);
            } elseif (is_string($val)) {
                // Trim whitespace, leave HTML for markdown/editor sanitize separately
                $clean[$cleanKey] = in_array($cleanKey, ['password','current_password','new_password','confirm_password','password_confirmation'], true) ? $val : trim($val);
            } else {
                $clean[$cleanKey] = $val;
            }
        }
        return $clean;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isAjax(): bool
    {
        return ($this->header('X-REQUESTED-WITH') === 'XMLHttpRequest')
            || str_contains($this->header('ACCEPT') ?? '', 'application/json');
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function queryAll(): array
    {
        return $this->queryParams;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }

    public function header(string $key): ?string
    {
        return $this->headers[strtoupper($key)] ?? null;
    }

    public function ip(): string
    {
        $remote=(string)($this->server['REMOTE_ADDR']??'127.0.0.1');
        $trusted=array_filter(array_map('trim',explode(',',(string)config('security.trusted_proxy_ips',''))));
        if (!in_array($remote,$trusted,true)) return $remote;
        // Walk from the trusted proxy towards the client; the leftmost value can be attacker supplied.
        foreach(array_reverse(explode(',',(string)($this->server['HTTP_X_FORWARDED_FOR']??''))) as $ip){
            $ip=trim($ip);
            if(!filter_var($ip,FILTER_VALIDATE_IP)) return $remote;
            if(!in_array($ip,$trusted,true)) return $ip;
        }
        return $remote;
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public function csrfToken(): ?string
    {
        return $this->input('_csrf_token') ?? $this->input('csrf_token') ?? $this->header('X-CSRF-TOKEN');
    }
}
