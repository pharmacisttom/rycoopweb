<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?array $user = null;

    public static function check(): bool
    {
        Session::start();
        return Session::has('user_id') && Session::get('2fa_verified', false) === true;
    }

    public static function checkPending2FA(): bool
    {
        Session::start();
        return Session::has('user_id') && Session::get('2fa_verified', false) === false;
    }

    public static function id(): ?int
    {
        Session::start();
        return Session::get('user_id');
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$user === null) {
            $userId = self::id();
            if ($userId) {
                $sql = "SELECT u.*, r.slug as role_slug, r.name as role_name 
                        FROM users u
                        LEFT JOIN user_roles ur ON u.id = ur.user_id
                        LEFT JOIN roles r ON ur.role_id = r.id
                        WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'
                        LIMIT 1";
                self::$user = Database::first($sql, [$userId]);
            }
        }

        return self::$user;
    }

    public static function login(array $user, bool $verified2FA = false): void
    {
        Session::start();
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user_email', $user['email']);
        Session::set('user_name', $user['name']);
        // Missing authorization data must never grant a privileged role.
        Session::set('role_slug', (string) ($user['role_slug'] ?? ''));
        Session::set('2fa_verified', $verified2FA);
        self::$user = null;

        Logger::auth("User logged in: {$user['email']} (ID: {$user['id']})");
    }

    public static function verify2FA(): void
    {
        Session::start();
        Session::set('2fa_verified', true);
        Logger::auth("2FA verified for user ID: " . Session::get('user_id'));
    }

    public static function logout(): void
    {
        $userId = self::id();
        if ($userId) {
            Logger::auth("User logged out (ID: {$userId})");
        }
        self::$user = null;
        Session::destroy();
    }

    public static function hasRole(string|array $roles): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        $currentRole = $user['role_slug'] ?? '';
        if ($currentRole === 'super_admin') {
            return true; // Super admin has all roles
        }

        if (is_array($roles)) {
            return in_array($currentRole, $roles, true);
        }

        return $currentRole === $roles;
    }

    public static function hasPermission(string $module, string $action): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        if (($user['role_slug'] ?? '') === 'super_admin') {
            return true; // Super admin has all permissions
        }

        // Check in role_permissions table
        $sql = "SELECT COUNT(*) FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                JOIN user_roles ur ON ur.role_id = rp.role_id
                WHERE ur.user_id = ? AND p.module = ? AND p.action = ?";

        $count = (int) Database::value($sql, [$user['id'], $module, $action]);
        return $count > 0;
    }
}
