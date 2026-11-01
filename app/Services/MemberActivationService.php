<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class MemberActivationService
{
    public const INVITATION_TTL_MINUTES = 20;

    public static function enabled(): bool
    {
        return (bool) config('features.member_activation', false);
    }

    // Only authorized staff, after an in-person identity check, may issue
    // a single-member invitation. No bulk activation or predictable passwords.
    public static function issue(int $memberId, int $staffId): string
    {
        if (!self::enabled()) throw new RuntimeException('ACTIVATION_DISABLED');
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $member = Database::first(
                "SELECT m.id, m.user_id, m.status AS member_status, u.status AS account_status, u.deleted_at
                 FROM members m JOIN users u ON u.id=m.user_id
                 WHERE m.id=? FOR UPDATE", [$memberId]
            );
            if (!$member || $member['member_status'] !== 'active'
                || $member['account_status'] !== 'inactive' || $member['deleted_at'] !== null) {
                throw new RuntimeException('MEMBER_NOT_ELIGIBLE');
            }
            $userId = (int)$member['user_id'];
            $roles = Database::query(
                'SELECT r.slug FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?', [$userId]
            );
            if (count($roles) !== 1 || $roles[0]['slug'] !== 'member') {
                throw new RuntimeException('INVALID_MEMBER_ROLE');
            }
            $issuedToday = (int)Database::value(
                "SELECT COUNT(*) FROM member_activation_invitations
                 WHERE user_id=? AND created_at >= DATE_SUB(NOW(),INTERVAL 24 HOUR)", [$userId]
            );
            if ($issuedToday >= 3) throw new RuntimeException('INVITATION_LIMIT_REACHED');
            Database::execute(
                'UPDATE member_activation_invitations SET used_at=NOW()
                 WHERE user_id=? AND used_at IS NULL', [$userId]
            );
            $code = bin2hex(random_bytes(32));
            Database::execute(
                'INSERT INTO member_activation_invitations
                 (member_id,user_id,token_hash,issued_by,verification_method,expires_at)
                 VALUES (?,?,?,?,\'in_person_verified\',DATE_ADD(NOW(),INTERVAL 20 MINUTE))',
                [$memberId,$userId,hash('sha256',$code),$staffId]
            );
            $pdo->commit();
            return $code;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function redeem(string $code, string $password, string $confirmation): bool
    {
        if (!self::enabled()) throw new RuntimeException('ACTIVATION_DISABLED');
        if (!preg_match('/^[a-f0-9]{64}$/D', $code)) throw new RuntimeException('INVALID_INVITATION');
        if ($password !== $confirmation || strlen($password) < 12 || strlen($password) > 128
            || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new RuntimeException('INVALID_PASSWORD_POLICY');
        }
        $pdo = Database::connect();
        $pdo->beginTransaction();
        try {
            $invitation = Database::first(
                "SELECT i.id,i.user_id,i.member_id,u.status AS account_status,m.status AS member_status
                 FROM member_activation_invitations i
                 JOIN users u ON u.id=i.user_id AND u.deleted_at IS NULL
                 JOIN members m ON m.id=i.member_id AND m.user_id=i.user_id
                 WHERE i.token_hash=? AND i.used_at IS NULL AND i.expires_at > NOW()
                 FOR UPDATE", [hash('sha256', $code)]
            );
            if (!$invitation || $invitation['account_status'] !== 'inactive'
                || $invitation['member_status'] !== 'active') {
                throw new RuntimeException('INVALID_INVITATION');
            }
            $roles = Database::query('SELECT r.slug FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?',
                [(int)$invitation['user_id']]);
            if (count($roles) !== 1 || $roles[0]['slug'] !== 'member') throw new RuntimeException('INVALID_INVITATION');
            $passwordConfig = config('security.password');
            $hash = password_hash($password, $passwordConfig['algo'] ?? PASSWORD_ARGON2ID, $passwordConfig['options'] ?? []);
            if (!is_string($hash)) throw new RuntimeException('PASSWORD_HASH_FAILED');
            Database::execute("UPDATE users SET password=?,status='active',
                session_version=session_version+1,updated_at=NOW() WHERE id=? AND status='inactive'",
                [$hash,(int)$invitation['user_id']]);
            Database::execute('UPDATE member_activation_invitations SET used_at=NOW() WHERE id=? AND used_at IS NULL',
                [(int)$invitation['id']]);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
