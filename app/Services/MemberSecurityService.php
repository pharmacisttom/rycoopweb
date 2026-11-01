<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;

class MemberSecurityService
{
    private static ?bool $cachedHasMustChangeCol = null;

    /**
     * Check whether users table contains must_change_password column.
     */
    public static function hasMustChangePasswordColumn(): bool
    {
        if (self::$cachedHasMustChangeCol !== null) {
            return self::$cachedHasMustChangeCol;
        }

        try {
            $pdo = Database::connect();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') {
                $col = Database::first(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'must_change_password' LIMIT 1"
                );
                self::$cachedHasMustChangeCol = !empty($col);
            } else {
                $cols = Database::query("PRAGMA table_info(users)");
                $found = false;
                foreach ($cols as $c) {
                    if (($c['name'] ?? '') === 'must_change_password') {
                        $found = true;
                        break;
                    }
                }
                self::$cachedHasMustChangeCol = $found;
            }
        } catch (\Throwable $e) {
            self::$cachedHasMustChangeCol = false;
        }

        return self::$cachedHasMustChangeCol;
    }

    /**
     * Determine whether a user must change their initial password before accessing financial data.
     * Enforces server-side database state and timestamp ordering.
     */
    public static function isPasswordChangeRequired(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (self::hasMustChangePasswordColumn()) {
            $user = Database::first("SELECT must_change_password, status FROM users WHERE id = ? LIMIT 1", [$userId]);
            if (!$user) {
                return false;
            }
            return (int)($user['must_change_password'] ?? 0) === 1;
        }

        // Fallback when column does not yet exist: check activation timestamp vs password change timestamp
        try {
            $member = Database::first("SELECT id FROM members WHERE user_id = ? LIMIT 1", [$userId]);
            if (!$member) {
                return false;
            }

            // Find latest activation
            $activatedAt = Database::value(
                "SELECT MAX(created_at) FROM member_data_changes WHERE member_id = ? AND action = 'account_activation'",
                [$member['id']]
            );
            if (!$activatedAt) {
                $activatedAt = Database::value(
                    "SELECT MAX(created_at) FROM audit_logs WHERE module = 'member_management' AND action = 'activate_account' AND record_id = ?",
                    [(string)$member['id']]
                );
            }

            if (!$activatedAt) {
                return false;
            }

            // Find latest password change
            $lastPwChangeAt = Database::value(
                "SELECT MAX(created_at) FROM audit_logs WHERE user_id = ? AND action = 'password_change'",
                [$userId]
            );

            if (!$lastPwChangeAt) {
                return true;
            }

            // An older password-change event does not count as password setup after activation
            return strtotime($lastPwChangeAt) <= strtotime($activatedAt);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Mark that password change is required upon account activation.
     */
    public static function markPasswordChangeRequired(int $userId): void
    {
        if (self::hasMustChangePasswordColumn()) {
            Database::execute("UPDATE users SET must_change_password = 1, updated_at = NOW() WHERE id = ?", [$userId]);
        }
    }

    /**
     * Clear password change requirement only after password hash persistence.
     */
    public static function clearPasswordChangeRequired(int $userId): void
    {
        if (self::hasMustChangePasswordColumn()) {
            Database::execute("UPDATE users SET must_change_password = 0, updated_at = NOW() WHERE id = ?", [$userId]);
        }
        Session::set('requires_password_change', false);
    }

    /**
     * Validate new password rules for member accounts:
     * - Minimum 8 characters
     * - Never accept citizen ID (raw or formatted)
     * - Never accept member number (raw, padded, or trimmed)
     * - Never accept username or default passwords
     * Returns null on success, or an error message string on failure.
     */
    public static function validatePasswordSecurity(string $password, int $userId): ?string
    {
        if (strlen($password) < 8) {
            return 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        }

        $cleanPass = preg_replace('/\D/', '', $password);

        $memberRecord = Database::first("SELECT member_no, id_card FROM members WHERE user_id = ? LIMIT 1", [$userId]);
        if ($memberRecord) {
            $rawMemberNo = (string)($memberRecord['member_no'] ?? '');
            $cleanMemberNo = ltrim($rawMemberNo, '0');
            $paddedMemberNo = str_pad($cleanMemberNo, 5, '0', STR_PAD_LEFT);

            $rawIdCard = (string)($memberRecord['id_card'] ?? '');
            $cleanIdCard = preg_replace('/\D/', '', $rawIdCard);

            if (
                $password === $rawMemberNo ||
                $password === $cleanMemberNo ||
                $password === $paddedMemberNo ||
                ($cleanPass !== '' && ($cleanPass === $cleanMemberNo || $cleanPass === $paddedMemberNo))
            ) {
                return 'รหัสผ่านใหม่ต้องไม่ตรงกับเลขสมาชิก';
            }

            if (
                $password === $rawIdCard ||
                ($cleanIdCard !== '' && ($password === $cleanIdCard || $cleanPass === $cleanIdCard))
            ) {
                return 'รหัสผ่านใหม่ต้องไม่ตรงกับเลขบัตรประชาชน';
            }
        }

        $user = Database::first("SELECT username FROM users WHERE id = ? LIMIT 1", [$userId]);
        if ($user) {
            $username = (string)($user['username'] ?? '');
            $cleanUsername = preg_replace('/\D/', '', $username);
            if ($password === $username || ($cleanUsername !== '' && $cleanPass === $cleanUsername)) {
                return 'รหัสผ่านใหม่ต้องไม่ตรงกับชื่อผู้ใช้งานหรือเลขประจำตัว';
            }
        }

        if (in_array(strtolower($password), ['coop1234', 'password', '12345678', 'admin1234', 'password123'], true)) {
            return 'รหัสผ่านนี้ง่ายเกินไป กรุณาตั้งรหัสผ่านที่มีความปลอดภัยสูงขึ้น';
        }

        return null;
    }

    /**
     * Reset cached schema state (for testing).
     */
    public static function resetSchemaCache(): void
    {
        self::$cachedHasMustChangeCol = null;
    }
}
