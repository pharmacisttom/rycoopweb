<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use Throwable;

class SecurityAuditService
{
    // Member Audit Events
    public const MEMBER_LOGIN_SUCCESS = 'MEMBER_LOGIN_SUCCESS';
    public const MEMBER_LOGIN_FAILED = 'MEMBER_LOGIN_FAILED';
    public const MEMBER_LOGOUT = 'MEMBER_LOGOUT';
    public const MEMBER_PDPA_VIEWED = 'MEMBER_PDPA_VIEWED';
    public const MEMBER_PDPA_ACKNOWLEDGED = 'MEMBER_PDPA_ACKNOWLEDGED';
    public const MEMBER_FINANCIAL_VIEW = 'MEMBER_FINANCIAL_VIEW';
    public const MEMBER_SESSION_EXPIRED = 'MEMBER_SESSION_EXPIRED';
    public const MEMBER_ACCESS_DENIED = 'MEMBER_ACCESS_DENIED';

    // Backend / Staff Audit Events
    public const ADMIN_LOGIN_SUCCESS = 'ADMIN_LOGIN_SUCCESS';
    public const ADMIN_LOGIN_FAILED = 'ADMIN_LOGIN_FAILED';
    public const ADMIN_2FA_SUCCESS = 'ADMIN_2FA_SUCCESS';
    public const ADMIN_2FA_FAILED = 'ADMIN_2FA_FAILED';
    public const ADMIN_LOGOUT = 'ADMIN_LOGOUT';
    public const ADMIN_MEMBER_VIEW = 'ADMIN_MEMBER_VIEW';
    public const ADMIN_MEMBER_UPDATE = 'ADMIN_MEMBER_UPDATE';
    public const ADMIN_FINANCIAL_IMPORT = 'ADMIN_FINANCIAL_IMPORT';
    public const ADMIN_FINANCIAL_EXPORT = 'ADMIN_FINANCIAL_EXPORT';
    public const ADMIN_ACCOUNT_ACTIVATION = 'ADMIN_ACCOUNT_ACTIVATION';
    public const ADMIN_PERMISSION_CHANGED = 'ADMIN_PERMISSION_CHANGED';

    // Actor Types
    public const ACTOR_MEMBER = 'member';
    public const ACTOR_STAFF = 'staff';

    // Results
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILURE = 'failure';
    public const RESULT_DENIED = 'denied';

    private static ?string $currentCorrelationId = null;
    private static ?\Closure $testFaultInjector = null;

    public static function setCorrelationId(?string $id): void
    {
        self::$currentCorrelationId = $id;
    }

    public static function getCorrelationId(): string
    {
        if (self::$currentCorrelationId !== null && self::$currentCorrelationId !== '') {
            return self::$currentCorrelationId;
        }

        $headerCorrelation = $_SERVER['HTTP_X_CORRELATION_ID']
            ?? $_SERVER['HTTP_X_REQUEST_ID']
            ?? null;

        if ($headerCorrelation !== null && preg_match('/^[a-zA-Z0-9\-_]{16,64}$/', $headerCorrelation)) {
            self::$currentCorrelationId = $headerCorrelation;
            return self::$currentCorrelationId;
        }

        self::$currentCorrelationId = self::generateUuid();
        return self::$currentCorrelationId;
    }

    public static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // Version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // RFC 4122 variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function getSessionFingerprint(): string
    {
        Session::start();
        $sessionId = session_id();
        if (empty($sessionId)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
            return hash('sha256', "stateless-{$ip}-{$ua}");
        }
        return hash('sha256', 'rycoop-session-' . $sessionId);
    }

    public static function setTestFaultInjector(?\Closure $injector): void
    {
        self::$testFaultInjector = $injector;
    }

    /**
     * Scrub metadata to strictly eliminate raw credentials, OTPs, TOTP secrets,
     * and raw citizen IDs.
     */
    public static function scrubMetadata(array $data): array
    {
        $scrubbed = [];
        $redactedKeys = [
            'password', 'new_password', 'current_password', 'confirm_password',
            'initial_password', 'secret', 'two_factor_secret', 'totp', 'otp',
            'code', 'recovery_code', 'pin', 'token', 'authorization'
        ];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);

            if (in_array($lowerKey, $redactedKeys, true)) {
                $scrubbed[$key] = '[REDACTED]';
                continue;
            }

            if ($lowerKey === 'id_card' || $lowerKey === 'citizen_id') {
                $scrubbed[$key] = MemberPiiMaskingService::maskCitizenId((string)$value);
                continue;
            }

            if ($lowerKey === 'member_no') {
                $scrubbed[$key] = MemberPiiMaskingService::maskMemberNo((string)$value);
                continue;
            }

            if ($lowerKey === 'phone' || $lowerKey === 'phone_number') {
                $scrubbed[$key] = MemberPiiMaskingService::maskPhoneNumber((string)$value);
                continue;
            }

            if (is_array($value)) {
                $scrubbed[$key] = self::scrubMetadata($value);
            } elseif (is_string($value)) {
                // If the value contains a 13-digit Thai Citizen ID pattern, mask it
                if (preg_match('/^\d{13}$/', $value)) {
                    $scrubbed[$key] = MemberPiiMaskingService::maskCitizenId($value);
                } else {
                    $scrubbed[$key] = $value;
                }
            } else {
                $scrubbed[$key] = $value;
            }
        }

        return $scrubbed;
    }

    /**
     * Core logging method writing to `security_audit_logs`.
     */
    public static function log(
        string $eventType,
        string $actorType,
        ?int $actorUserId,
        string $role,
        ?string $targetResource,
        string $result,
        array $metadata = []
    ): string {
        $eventId = self::generateUuid();
        $timestampUtc = gmdate('Y-m-d H:i:s');
        $correlationId = self::getCorrelationId();
        $sessionFingerprint = self::getSessionFingerprint();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 500);

        $safeMetadata = self::scrubMetadata($metadata);
        $metadataJson = json_encode($safeMetadata, JSON_UNESCAPED_UNICODE);

        if (self::$testFaultInjector !== null) {
            (self::$testFaultInjector)($eventType, $actorType, $actorUserId, $role, $targetResource, $result, $safeMetadata);
        }

        try {
            $sql = "INSERT INTO security_audit_logs
                    (event_id, timestamp_utc, actor_user_id, actor_type, role, event_type, target_resource, result, correlation_id, session_fingerprint, ip_address, user_agent, metadata, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            Database::execute($sql, [
                $eventId,
                $timestampUtc,
                $actorUserId,
                $actorType,
                $role,
                $eventType,
                $targetResource,
                $result,
                $correlationId,
                $sessionFingerprint,
                $ip,
                $ua,
                $metadataJson,
            ]);

            Logger::log('security_audit', "[{$actorType}:{$role}] {$eventType} ({$result}) - User: " . ($actorUserId ?? 'null') . " - Target: {$targetResource} [Event: {$eventId}]");
        } catch (Throwable $e) {
            // Graceful fallback: log to Logger and general AuditService
            Logger::error("SecurityAuditService Error: " . $e->getMessage());
            try {
                AuditService::log('security', $eventType, $targetResource, null, [
                    'actor_type' => $actorType,
                    'actor_user_id' => $actorUserId,
                    'role' => $role,
                    'result' => $result,
                    'correlation_id' => $correlationId,
                    'metadata' => $safeMetadata,
                ]);
            } catch (Throwable $ignored) {}
        }

        return $eventId;
    }

    // ==========================================
    // MEMBER AUDIT HELPERS
    // ==========================================

    public static function recordMemberLoginSuccess(int $userId, string $role = 'member', ?string $maskedUsername = null): string
    {
        return self::log(
            self::MEMBER_LOGIN_SUCCESS,
            self::ACTOR_MEMBER,
            $userId,
            $role,
            '/login',
            self::RESULT_SUCCESS,
            ['masked_username' => $maskedUsername]
        );
    }

    public static function recordMemberLoginFailed(?int $userId, ?string $maskedUsername, string $reason): string
    {
        return self::log(
            self::MEMBER_LOGIN_FAILED,
            self::ACTOR_MEMBER,
            $userId,
            'member',
            '/login',
            self::RESULT_FAILURE,
            ['masked_username' => $maskedUsername, 'reason' => $reason]
        );
    }

    public static function recordMemberLogout(int $userId, string $role = 'member'): string
    {
        return self::log(
            self::MEMBER_LOGOUT,
            self::ACTOR_MEMBER,
            $userId,
            $role,
            '/logout',
            self::RESULT_SUCCESS
        );
    }

    public static function recordMemberPdpaViewed(int $userId, string $policyVersion): string
    {
        return self::log(
            self::MEMBER_PDPA_VIEWED,
            self::ACTOR_MEMBER,
            $userId,
            'member',
            "/api/member/pdpa/status",
            self::RESULT_SUCCESS,
            ['policy_version' => $policyVersion]
        );
    }

    public static function recordMemberPdpaAcknowledged(int $userId, string $policyVersion, int $ackId): string
    {
        return self::log(
            self::MEMBER_PDPA_ACKNOWLEDGED,
            self::ACTOR_MEMBER,
            $userId,
            'member',
            "/api/member/pdpa/acknowledge",
            self::RESULT_SUCCESS,
            ['policy_version' => $policyVersion, 'acknowledgement_id' => $ackId]
        );
    }

    public static function recordMemberFinancialView(int $userId, string $resourceType, ?string $maskedMemberNo = null): string
    {
        return self::log(
            self::MEMBER_FINANCIAL_VIEW,
            self::ACTOR_MEMBER,
            $userId,
            'member',
            "/api/member/{$resourceType}",
            self::RESULT_SUCCESS,
            ['resource_type' => $resourceType, 'masked_member_no' => $maskedMemberNo]
        );
    }

    public static function recordMemberSessionExpired(int $userId, string $role = 'member'): string
    {
        return self::log(
            self::MEMBER_SESSION_EXPIRED,
            self::ACTOR_MEMBER,
            $userId,
            $role,
            'session',
            self::RESULT_FAILURE,
            ['reason' => 'Session lifetime expired']
        );
    }

    public static function recordMemberAccessDenied(?int $userId, string $role, string $targetResource, string $reason): string
    {
        return self::log(
            self::MEMBER_ACCESS_DENIED,
            self::ACTOR_MEMBER,
            $userId,
            $role,
            $targetResource,
            self::RESULT_DENIED,
            ['reason' => $reason]
        );
    }

    // ==========================================
    // BACKEND / STAFF AUDIT HELPERS
    // ==========================================

    public static function recordAdminLoginSuccess(int $userId, string $role, array $extra = []): string
    {
        return self::log(
            self::ADMIN_LOGIN_SUCCESS,
            self::ACTOR_STAFF,
            $userId,
            $role,
            '/admin/login',
            self::RESULT_SUCCESS,
            $extra
        );
    }

    public static function recordAdminLoginFailed(?int $userId, ?string $maskedUsername, string $reason): string
    {
        return self::log(
            self::ADMIN_LOGIN_FAILED,
            self::ACTOR_STAFF,
            $userId,
            'staff',
            '/admin/login',
            self::RESULT_FAILURE,
            ['masked_username' => $maskedUsername, 'reason' => $reason]
        );
    }

    public static function recordAdmin2faSuccess(int $userId, string $role, string $method = 'totp'): string
    {
        return self::log(
            self::ADMIN_2FA_SUCCESS,
            self::ACTOR_STAFF,
            $userId,
            $role,
            '/admin/mfa/verify',
            self::RESULT_SUCCESS,
            ['method' => $method]
        );
    }

    public static function recordAdmin2faFailed(int $userId, string $role, string $reason): string
    {
        return self::log(
            self::ADMIN_2FA_FAILED,
            self::ACTOR_STAFF,
            $userId,
            $role,
            '/admin/mfa/verify',
            self::RESULT_FAILURE,
            ['reason' => $reason]
        );
    }

    public static function recordAdminLogout(int $userId, string $role): string
    {
        return self::log(
            self::ADMIN_LOGOUT,
            self::ACTOR_STAFF,
            $userId,
            $role,
            '/admin/logout',
            self::RESULT_SUCCESS
        );
    }

    public static function recordAdminMemberView(int $adminId, string $role, int $targetMemberId): string
    {
        return self::log(
            self::ADMIN_MEMBER_VIEW,
            self::ACTOR_STAFF,
            $adminId,
            $role,
            "member:{$targetMemberId}",
            self::RESULT_SUCCESS,
            ['target_member_id' => $targetMemberId]
        );
    }

    public static function recordAdminMemberUpdate(int $adminId, string $role, int $targetMemberId, array $changedFields = []): string
    {
        return self::log(
            self::ADMIN_MEMBER_UPDATE,
            self::ACTOR_STAFF,
            $adminId,
            $role,
            "member:{$targetMemberId}",
            self::RESULT_SUCCESS,
            ['target_member_id' => $targetMemberId, 'updated_fields' => array_keys($changedFields)]
        );
    }

    public static function recordAdminFinancialImport(int $adminId, string $role, string $importType, int $recordCount): string
    {
        return self::log(
            self::ADMIN_FINANCIAL_IMPORT,
            self::ACTOR_STAFF,
            $adminId,
            $role,
            "import:{$importType}",
            self::RESULT_SUCCESS,
            ['import_type' => $importType, 'record_count' => $recordCount]
        );
    }

    public static function recordAdminFinancialExport(int $adminId, string $role, string $exportType, int $recordCount): string
    {
        return self::log(
            self::ADMIN_FINANCIAL_EXPORT,
            self::ACTOR_STAFF,
            $adminId,
            $role,
            "export:{$exportType}",
            self::RESULT_SUCCESS,
            ['export_type' => $exportType, 'record_count' => $recordCount]
        );
    }

    public static function recordAdminAccountActivation(int $adminId, string $role, int $targetUserId, bool $dryRun = false): string
    {
        return self::log(
            self::ADMIN_ACCOUNT_ACTIVATION,
            self::ACTOR_STAFF,
            $adminId,
            $role,
            "user:{$targetUserId}",
            self::RESULT_SUCCESS,
            ['target_user_id' => $targetUserId, 'dry_run' => $dryRun]
        );
    }

    public static function recordAdminPermissionChanged(int $adminId, string $role, int $targetUserId, string $changeSummary): string
    {
        return self::log(
            self::ADMIN_PERMISSION_CHANGED,
            self::ACTOR_STAFF,
            $adminId,
            $role,
            "user:{$targetUserId}",
            self::RESULT_SUCCESS,
            ['target_user_id' => $targetUserId, 'change_summary' => $changeSummary]
        );
    }
}
