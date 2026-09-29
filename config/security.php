<?php

declare(strict_types=1);

return [
    'session' => [
        'name' => 'rayongcoop_session',
        'lifetime' => (int) env('SESSION_LIFETIME', 7200), // 2 hours
        'idle_timeout' => 1800, // 30 minutes idle timeout
        'secure' => filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL),
        'httponly' => true,
        'samesite' => env('SESSION_SAMESITE', 'Lax'),
        'save_path' => env('SESSION_SAVE_PATH', ''),
    ],
    'rate_limiting' => [
        'login' => [
            'max_attempts' => (int) env('RATE_LIMIT_LOGIN_MAX', 5),
            'decay_seconds' => (int) env('RATE_LIMIT_LOGIN_DECAY', 900), // 15 minutes
        ],
        'public_form' => [
            'max_attempts' => 10,
            'decay_seconds' => 300,
        ],
    ],
    'csrf' => [
        'token_name' => '_csrf_token',
        'header_name' => 'X-CSRF-TOKEN',
    ],
    'password' => [
        'algo' => PASSWORD_ARGON2ID,
        'options' => [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 2,
        ],
    ],
    'two_factor' => [
        'issuer' => env('TWO_FACTOR_ISSUER', 'RayongCoop'),
        'window' => (int) env('TWO_FACTOR_WINDOW', 1),
    ],
    'upload' => [
        'max_size' => 20 * 1024 * 1024, // 20 MB
        'allowed_images' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'allowed_documents' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'blocked_extensions' => ['php', 'phtml', 'phar', 'cgi', 'pl', 'exe', 'sh', 'bat', 'cmd', 'vbs', 'ps1', 'jsp', 'asp', 'aspx', 'py', 'rb'],
    ],
];
