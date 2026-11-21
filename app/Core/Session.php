<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        // session_write_close() may have released the native session in this request.
        self::$started = false;

        $sessionConfig = config('security.session') ?? [];
        $lifetime = (int) ($sessionConfig['lifetime'] ?? 7200);

        ini_set('session.use_strict_mode', '1');
        if (PHP_SAPI !== 'cli') {
            ini_set('session.use_only_cookies', '1');
        }
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_lifetime', (string) $lifetime);
        $savePath = (string) ($sessionConfig['save_path'] ?? '');
        if ($savePath !== '') {
            ini_set('session.save_path', $savePath);
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => (string) ($sessionConfig['domain'] ?? ''),
            'secure' => (bool) ($sessionConfig['secure'] ?? false),
            'httponly' => true,
            'samesite' => $sessionConfig['samesite'] ?? 'Lax',
        ]);

        $sessionName = $sessionConfig['name'] ?? 'rayongcoop_session';
        if (session_name() !== $sessionName) {
            $existingId = session_id();
            session_name($sessionName);
            if ($existingId !== '') {
                session_id($existingId);
            }
        }
        session_start();
        self::$started = true;
        $now = time();

        // A concurrent logout must not mistake a rotation handoff for an anonymous session.
        $handoffAttempts = 0;
        while (!empty($_SESSION['__rotation_in_progress'])) {
            if (++$handoffAttempts > 20) {
                // Cancellation removes the predecessor; the rotator must discard its target.
                self::destroy(); session_id(''); self::start(); return;
            }
            $waitingId = session_id();
            session_write_close(); usleep(25000); session_id($waitingId); session_start();
        }

        // Follow metadata-only links with strict-mode target validation and a hop cap.
        $hops = 0;
        while (!empty($_SESSION['__is_rotated'])) {
            $at = (int) ($_SESSION['__rotated_at'] ?? 0);
            $grace = min(60, max(0, (int) ($_SESSION['__rotated_grace'] ?? 60)));
            $target = (string) ($_SESSION['__rotated_to'] ?? '');
            // Pre-hotfix links have no bounded-grace marker and may cross a login/MFA boundary.
            if (!array_key_exists('__rotated_grace', $_SESSION) || ++$hops > 4 || $at <= 0 || $at > $now || $now - $at > $grace ||
                !preg_match('/^[a-zA-Z0-9,-]{16,256}$/D', $target) || $target === session_id()) {
                self::destroy(); session_id(''); self::start(); return;
            }
            session_write_close(); session_id($target); session_start();
            if (session_id() !== $target) { $_SESSION = []; break; }
        }
        if ($hops > 0) self::sendCookie(session_id(), $sessionConfig);

        // Idle Timeout Check
        $idleTimeout = (int) ($sessionConfig['idle_timeout'] ?? 1200); // 20 mins

        if (isset($_SESSION['__last_activity']) && ($now - $_SESSION['__last_activity'] > $idleTimeout)) {
            if (isset($_SESSION['user_id'])) {
                self::destroy();
                self::start();
                self::flash('error', 'เซสชันหมดอายุเนื่องจากไม่มีการใช้งานเป็นเวลานาน กรุณาเข้าสู่ระบบใหม่');
                return;
            }
        }
        $probe = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/api/auth/me';
        if (!$probe || !isset($_SESSION['__last_activity'])) $_SESSION['__last_activity'] = $now;

        // Age Rotation with bounded transition window (safe for concurrent requests)
        if (!isset($_SESSION['__created_at'])) {
            $_SESSION['__created_at'] = $now;
        } elseif ($now - $_SESSION['__created_at'] > 1800) {
            // Rotate session ID every 30 mins safely
            self::rotate(60);
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

    /** Privilege changes must delete the old ID, never forward it. */
    public static function regenerateForAuthentication(bool $preserveIdentity = true): void
    {
        self::start();
        if (!$preserveIdentity) $_SESSION = [];
        if (!session_regenerate_id(true)) throw new \RuntimeException('Secure session regeneration failed');
        unset($_SESSION['__is_rotated'], $_SESSION['__rotated_to'], $_SESSION['__rotated_at'], $_SESSION['__rotated_grace'], $_SESSION['_csrf_token'], $_SESSION['mfa_step_up_verified_at'], $_SESSION['mfa_step_up_user_id'], $_SESSION['mfa_step_up_scope'], $_SESSION['mfa_step_up_consumed']);
        foreach (array_keys($_SESSION) as $key) {
            if (str_starts_with((string)$key, 'mfa_step_up_')) unset($_SESSION[$key]);
        }
        $_SESSION['__created_at'] = time();
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        self::start();
        if ($deleteOld) {
            session_regenerate_id(true);
            $_SESSION['__created_at'] = time();
            unset($_SESSION['__is_rotated'], $_SESSION['__rotated_to'], $_SESSION['__rotated_at']);
        } else {
            self::rotate(60);
        }
    }

    /**
     * Rotate session ID safely with bounded transition grace period for concurrent requests.
     */
    public static function rotate(int $gracePeriod = 60): void
    {
        self::start();
        $sessionConfig = config('security.session') ?? [];
        $oldId = session_id();
        $oldData = $_SESSION;
        // Retired IDs must not retain an independently authenticated snapshot.
        $_SESSION = ['__rotation_in_progress' => true];

        // Use PHP native session_regenerate_id(false) to create and initialize new session ID
        if (!session_regenerate_id(false)) {
            $_SESSION = $oldData;
            throw new \RuntimeException('Session rotation failed');
        }
        $newId = session_id();
        $_SESSION = $oldData;

        // In new session: clean metadata
        unset($_SESSION['__is_rotated'], $_SESSION['__rotated_to'], $_SESSION['__rotated_at']);
        $_SESSION['__created_at'] = time();
        session_write_close();

        // Open old session to write transition pointer
        session_id($oldId);
        session_start();
        if (session_id() === $oldId) {
            $_SESSION = ['__is_rotated' => true, '__rotated_to' => $newId,
                '__rotated_at' => time(), '__rotated_grace' => min(60, max(0, $gracePeriod))];
        } else {
            // A timed-out concurrent request cancelled the handoff. Never publish its target.
            session_write_close(); session_id($newId); session_start();
            self::destroy(); session_id(''); self::start(); return;
        }
        session_write_close();

        // Reopen new session as active
        session_id($newId);
        session_start();
        self::sendCookie($newId, $sessionConfig);
    }

    /**
     * Send session cookie with strict security flags.
     */
    public static function sendCookie(string $id, array $config): void
    {
        if (headers_sent()) {
            return;
        }
        $lifetime = (int) ($config['lifetime'] ?? 7200);
        $cookieLifetime = $lifetime > 0 ? time() + $lifetime : 0;
        setcookie(
            $config['name'] ?? 'rayongcoop_session',
            $id,
            [
                'expires' => $cookieLifetime,
                'path' => '/',
                'domain' => (string) ($config['domain'] ?? ''),
                'secure' => (bool) ($config['secure'] ?? false),
                'httponly' => true,
                'samesite' => $config['samesite'] ?? 'Lax',
            ]
        );
    }

    public static function destroy(): void
    {
        // Logout can follow an early session_write_close(); reopen to invalidate persisted state.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
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
