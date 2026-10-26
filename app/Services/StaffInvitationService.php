<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;

class StaffInvitationService
{
    private const TOKEN_EXPIRY_DAYS = 7;

    /**
     * Create a new secure, expiring staff invitation.
     */
    public static function createInvitation(
        string $name,
        string $email,
        int $roleId,
        ?string $department,
        int $invitedBy
    ): array {
        $cleanEmail = strtolower(trim($email));
        $cleanName = trim($name);
        $cleanDept = trim((string) $department);

        if ($cleanName === '' || mb_strlen($cleanName) > 100) {
            throw new \InvalidArgumentException('กรุณากรอกชื่อ-นามสกุลของเจ้าหน้าที่ให้ถูกต้อง');
        }

        if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) || strlen($cleanEmail) > 100) {
            throw new \InvalidArgumentException('กรุณากรอกอีเมล Google Account ที่ถูกต้อง');
        }

        // Verify role exists and is an administrative/staff role
        $role = Database::first("SELECT id, slug, name FROM roles WHERE id = ? LIMIT 1", [$roleId]);
        if (!$role) {
            throw new \InvalidArgumentException('ไม่พบบทบาท (Role) ที่เลือก');
        }
        if ($role['slug'] === 'member') {
            throw new \InvalidArgumentException('ไม่สามารถเชิญบัญชีสมาชิกผ่านระบบเจ้าหน้าที่ได้');
        }

        // Check if an active staff account already exists with this email
        $existingUser = Database::first(
            "SELECT u.id FROM users u 
             JOIN user_roles ur ON u.id = ur.user_id 
             JOIN roles r ON ur.role_id = r.id 
             WHERE u.email = ? AND u.deleted_at IS NULL AND r.slug != 'member'
             LIMIT 1",
            [$cleanEmail]
        );
        if ($existingUser) {
            throw new \InvalidArgumentException('มีบัญชีเจ้าหน้าที่ที่ใช้อีเมลนี้อยู่ในระบบแล้ว');
        }

        // Check if there is already a pending invitation for this email; if so, revoke it first
        Database::execute(
            "UPDATE staff_invitations SET status = 'revoked', revoked_at = NOW(), updated_at = NOW() 
             WHERE email = ? AND status = 'pending'",
            [$cleanEmail]
        );

        // Generate cryptographically secure 64-hex-char token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TOKEN_EXPIRY_DAYS * 86400));

        $invitationId = (int) Database::insert(
            "INSERT INTO staff_invitations (email, name, department, intended_role_id, invited_by, token_hash, expires_at, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())",
            [$cleanEmail, $cleanName, $cleanDept !== '' ? $cleanDept : null, $roleId, $invitedBy, $tokenHash, $expiresAt]
        );

        AuditService::log('staff_invitation', 'create', (string) $invitationId, null, [
            'email' => $cleanEmail,
            'name' => $cleanName,
            'role' => $role['name'],
            'department' => $cleanDept,
            'invited_by' => $invitedBy,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => $invitationId,
            'email' => $cleanEmail,
            'name' => $cleanName,
            'department' => $cleanDept,
            'role_id' => $roleId,
            'role_name' => $role['name'],
            'token' => $rawToken,
            'invitation_url' => url("admin/login?invitation={$rawToken}"),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Find a valid pending invitation by raw token string.
     */
    public static function findValidByToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', trim($rawToken));
        $sql = "SELECT i.*, r.slug as role_slug, r.name as role_name, u.name as inviter_name 
                FROM staff_invitations i
                JOIN roles r ON i.intended_role_id = r.id
                JOIN users u ON i.invited_by = u.id
                WHERE i.token_hash = ? AND i.status = 'pending' AND i.expires_at > NOW()
                LIMIT 1";

        return Database::first($sql, [$tokenHash]);
    }

    /**
     * Find a valid pending invitation by email.
     */
    public static function findValidByEmail(string $email): ?array
    {
        $cleanEmail = strtolower(trim($email));
        $sql = "SELECT i.*, r.slug as role_slug, r.name as role_name, u.name as inviter_name 
                FROM staff_invitations i
                JOIN roles r ON i.intended_role_id = r.id
                JOIN users u ON i.invited_by = u.id
                WHERE i.email = ? AND i.status = 'pending' AND i.expires_at > NOW()
                ORDER BY i.id DESC
                LIMIT 1";

        return Database::first($sql, [$cleanEmail]);
    }

    /**
     * Accept a pending invitation, provision or bind the staff user, and link their Google identity.
     */
    public static function acceptInvitation(int $invitationId, string $googleSub, string $googleEmail, string $displayName): array
    {
        $cleanEmail = strtolower(trim($googleEmail));

        return Database::transaction(function () use ($invitationId, $googleSub, $cleanEmail, $displayName) {
            $invitation = Database::first(
                "SELECT * FROM staff_invitations WHERE id = ? AND status = 'pending' AND expires_at > NOW() FOR UPDATE",
                [$invitationId]
            );

            if (!$invitation) {
                throw new \InvalidArgumentException('คำเชิญไม่ถูกต้องหรือหมดอายุแล้ว');
            }

            if (strtolower($invitation['email']) !== $cleanEmail) {
                throw new \InvalidArgumentException('อีเมล Google ที่ใช้เข้าสู่ระบบไม่ตรงกับอีเมลที่ได้รับคำเชิญ');
            }

            // Check if user already exists with this email
            $user = Database::first("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1", [$cleanEmail]);

            if ($user) {
                $userId = (int) $user['id'];
                // Update name / department if needed
                Database::execute(
                    "UPDATE users SET name = ?, department = COALESCE(?, department), status = 'active', updated_at = NOW() WHERE id = ?",
                    [$displayName ?: $invitation['name'], $invitation['department'], $userId]
                );

                // Ensure role is assigned
                Database::execute("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
                Database::execute("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $invitation['intended_role_id']]);
            } else {
                // Generate a unique username derived from email or name
                $baseUsername = strtolower(explode('@', $cleanEmail)[0]);
                $baseUsername = preg_replace('/[^a-z0-9_.]/', '', $baseUsername) ?? 'staff';
                if (strlen($baseUsername) < 3) {
                    $baseUsername .= bin2hex(random_bytes(2));
                }

                $username = $baseUsername;
                $counter = 1;
                while (Database::value("SELECT COUNT(*) FROM users WHERE username = ?", [$username]) > 0) {
                    $username = "{$baseUsername}{$counter}";
                    $counter++;
                }

                // Provision new staff user (random secure password hash; authentication is via Google)
                $randomPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
                $uuid = bin2hex(random_bytes(16));

                $userId = (int) Database::insert(
                    "INSERT INTO users (uuid, name, username, email, password, status, department, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())",
                    [$uuid, $invitation['name'], $username, $cleanEmail, $randomPasswordHash, $invitation['department']]
                );

                Database::execute(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$userId, $invitation['intended_role_id']]
                );
            }

            // Bind Google identity
            Database::execute(
                "INSERT INTO user_google_identities (user_id, google_sub, google_email, email_verified, linked_at, last_login_at, created_at, updated_at)
                 VALUES (?, ?, ?, 1, NOW(), NOW(), NOW(), NOW())
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), google_email = VALUES(google_email), last_login_at = NOW(), updated_at = NOW()",
                [$userId, $googleSub, $cleanEmail]
            );

            // Mark invitation accepted
            Database::execute(
                "UPDATE staff_invitations SET status = 'accepted', accepted_at = NOW(), updated_at = NOW() WHERE id = ?",
                [$invitationId]
            );

            AuditService::log('staff_invitation', 'accept', (string) $invitationId, null, [
                'user_id' => $userId,
                'email' => $cleanEmail,
                'google_sub' => $googleSub,
                'role_id' => $invitation['intended_role_id'],
            ]);

            return [
                'user_id' => $userId,
                'email' => $cleanEmail,
                'role_id' => (int) $invitation['intended_role_id'],
            ];
        });
    }

    /**
     * Revoke a staff invitation.
     */
    public static function revokeInvitation(int $invitationId, int $revokedBy): void
    {
        $invitation = Database::first("SELECT * FROM staff_invitations WHERE id = ? LIMIT 1", [$invitationId]);
        if (!$invitation) {
            throw new \InvalidArgumentException('ไม่พบคำเชิญ');
        }

        if ($invitation['status'] !== 'pending') {
            throw new \InvalidArgumentException('ไม่สามารถยกเลิกคำเชิญที่ไม่ได้อยู่ในสถานะรอยืนยัน');
        }

        Database::execute(
            "UPDATE staff_invitations SET status = 'revoked', revoked_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$invitationId]
        );

        AuditService::log('staff_invitation', 'revoke', (string) $invitationId, null, [
            'email' => $invitation['email'],
            'revoked_by' => $revokedBy,
        ]);
    }

    /**
     * Renew/resend a staff invitation (extends expiration and generates new token).
     */
    public static function resendInvitation(int $invitationId, int $renewedBy): array
    {
        $invitation = Database::first("SELECT * FROM staff_invitations WHERE id = ? LIMIT 1", [$invitationId]);
        if (!$invitation) {
            throw new \InvalidArgumentException('ไม่พบคำเชิญ');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TOKEN_EXPIRY_DAYS * 86400));

        Database::execute(
            "UPDATE staff_invitations SET token_hash = ?, expires_at = ?, status = 'pending', revoked_at = NULL, updated_at = NOW() WHERE id = ?",
            [$tokenHash, $expiresAt, $invitationId]
        );

        AuditService::log('staff_invitation', 'resend', (string) $invitationId, null, [
            'email' => $invitation['email'],
            'renewed_by' => $renewedBy,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => $invitationId,
            'email' => $invitation['email'],
            'token' => $rawToken,
            'invitation_url' => url("admin/login?invitation={$rawToken}"),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * List all staff invitations.
     */
    public static function listInvitations(): array
    {
        $sql = "SELECT i.*, r.name as role_name, r.slug as role_slug, u.name as inviter_name
                FROM staff_invitations i
                LEFT JOIN roles r ON i.intended_role_id = r.id
                LEFT JOIN users u ON i.invited_by = u.id
                ORDER BY i.created_at DESC";

        $rows = Database::query($sql);
        return array_map(function ($r) {
            $isExpired = ($r['status'] === 'pending' && strtotime($r['expires_at']) < time());
            return [
                'id' => (int) $r['id'],
                'email' => $r['email'],
                'name' => $r['name'],
                'department' => $r['department'] ?? '',
                'intended_role_id' => (int) $r['intended_role_id'],
                'role_name' => $r['role_name'] ?? 'เจ้าหน้าที่',
                'role_slug' => $r['role_slug'] ?? 'staff',
                'inviter_name' => $r['inviter_name'] ?? 'ผู้ดูแลระบบ',
                'status' => $isExpired ? 'expired' : $r['status'],
                'expires_at' => $r['expires_at'],
                'created_at' => $r['created_at'],
                'accepted_at' => $r['accepted_at'],
            ];
        }, $rows);
    }
}
