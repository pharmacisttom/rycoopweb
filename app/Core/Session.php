<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $sessionConfig = config('security.session');
        $lifetime = $sessionConfig['lifetime'] ?? 7200;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        $savePath = (string) ($sessionConfig['save_path'] ?? '');
        if ($savePath !== '') {
            ini_set('session.save_path', $savePath);
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) ($sessionConfig['secure'] ?? false),
            'httponly' => true,
            'samesite' => $sessionConfig['samesite'] ?? 'Lax',
        ]);

        session_name($sessionConfig['name'] ?? 'rayongcoop_session');
        session_start();
        self::$started = true;

        // Idle Timeout Check
        $idleTimeout = $sessionConfig['idle_timeout'] ?? 1800; // 30 mins
        $now = time();

        if (isset($_SESSION['__last_activity']) && ($now - $_SESSION['__last_activity'] > $idleTimeout)) {
            if (isset($_SESSION['user_id'])) {
                self::destroy();
                self::start();
                self::flash('error', 'เซสชันหมดอายุเนื่องจากไม่มีการใช้งานเป็นเวลานาน กรุณาเข้าสู่ระบบใหม่');
                return;
            }
        }
        $_SESSION['__last_activity'] = $now;

        // Age Rotation
        if (!isset($_SESSION['__created_at'])) {
            $_SESSION['__created_at'] = $now;
        } elseif ($now - $_SESSION['__created_at'] > 1800) {
            // Rotate session ID every 30 mins
            session_regenerate_id(true);
            $_SESSION['__created_at'] = $now;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['__flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();
        if (isset($_SESSION['__flash'][$key])) {
            $value = $_SESSION['__flash'][$key];
            unset($_SESSION['__flash'][$key]);
            return $value;
        }
        return $default;
    }

    public static function setOldInput(array $input): void
    {
        self::set('__old_input', $input);
    }

    public static function getOldInput(string $key, mixed $default = ''): mixed
    {
        $old = self::get('__old_input', []);
        return $old[$key] ?? $default;
    }

    public static function clearOldInput(): void
    {
        self::remove('__old_input');
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
            self::$started = false;
        }
    }
}
