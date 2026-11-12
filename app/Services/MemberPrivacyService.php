<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class MemberPrivacyService
{
    // Same publication date shown on PrivacyPolicyPage; changing the notice
    // requires a reviewed new version and re-acknowledgement.
    public const NOTICE_VERSION = '2026-09-30';
    public const NOTICE_URL = '/privacy/policy';
    public const OPTIONAL_PURPOSE = 'optional_cooperative_updates';

    public static function acknowledged(int $userId): bool
    {
        $latest = Database::first(
            "SELECT choice, notice_version FROM member_privacy_events
             WHERE user_id=? AND event_type='notice_acknowledgement' AND purpose='member_online_service'
             ORDER BY id DESC LIMIT 1", [$userId]
        );
        return $latest !== null && $latest['choice'] === 'accepted'
            && $latest['notice_version'] === self::NOTICE_VERSION;
    }

    public static function optionalChoice(int $userId): string
    {
        $latest = Database::first(
            "SELECT choice FROM member_privacy_events
             WHERE user_id=? AND event_type='optional_consent' AND purpose=?
             ORDER BY id DESC LIMIT 1", [$userId, self::OPTIONAL_PURPOSE]
        );
        return $latest['choice'] ?? 'not_selected';
    }

    public static function record(int $userId, string $type, string $purpose, string $choice, string $version, string $ip, string $agent): void
    {
        if ($version !== self::NOTICE_VERSION) {
            throw new RuntimeException('NOTICE_VERSION_MISMATCH');
        }
        if (!in_array([$type, $purpose, $choice], [
            ['notice_acknowledgement', 'member_online_service', 'accepted'],
            ['optional_consent', self::OPTIONAL_PURPOSE, 'accepted'],
            ['optional_consent', self::OPTIONAL_PURPOSE, 'declined'],
            ['optional_consent', self::OPTIONAL_PURPOSE, 'withdrawn'],
        ], true)) {
            throw new RuntimeException('INVALID_PRIVACY_EVENT');
        }
        // Use keyed hashes, not reversible raw IP/User-Agent or unsalted
        // hashes (which can be brute-forced for IPv4 addresses).
        $auditKey = (string) env('APP_KEY', '');
        $ipHash = $auditKey !== '' && $ip !== '' ? hash_hmac('sha256', 'ip:'.$ip, $auditKey) : null;
        $agentHash = $auditKey !== '' && $agent !== '' ? hash_hmac('sha256', 'ua:'.$agent, $auditKey) : null;
        Database::execute(
            'INSERT INTO member_privacy_events (user_id,event_type,purpose,notice_version,choice,ip_hash,user_agent_hash,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())',
            [$userId,$type,$purpose,$version,$choice,
             $ipHash, $agentHash]
        );
    }
}
