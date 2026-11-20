<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;

class AuditService
{
    /** @var (\Closure(string, string, ?string, mixed, mixed, bool): void)|null */
    private static ?\Closure $testFaultInjector = null;

    public static function setTestFaultInjector(?\Closure $injector): void
    {
        self::$testFaultInjector = $injector;
    }

    /**
     * Record a Google identity that was cryptographically verified and authorized for staff.
     * This runs before a privileged session exists, so Auth::id() is not authoritative.
     * The caller must invoke this only after the OIDC claims and account authorization pass.
     * RC13A remains best-effort: audit failure does not change the existing login flow.
     */
    public static function logVerifiedGoogleIdentity(int $verifiedUserId): void
    {
        if ($verifiedUserId <= 0) {
            throw new \InvalidArgumentException('Verified user ID is required');
        }

        try {
            Database::execute(
                "INSERT INTO audit_logs (user_id, module, action, record_id, old_values, new_values, ip_address, user_agent, created_at)
                 VALUES (?, 'google_auth', 'identity_verified', ?, NULL, ?, ?, ?, NOW())",
                [
                    $verifiedUserId,
                    (string) $verifiedUserId,
                    json_encode(['provider' => 'google', 'stage' => 'pre_mfa'], JSON_THROW_ON_ERROR),
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                ]
            );
        } catch (\Throwable $e) {
            Logger::error('Google identity audit write failure: ' . $e->getMessage());
        }
    }

    public static function log(string $module, string $action, ?string $recordId = null, mixed $oldValues = null, mixed $newValues = null, bool $required = false): void
    {
        $userId = Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

        $oldJson = $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO audit_logs (user_id, module, action, record_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        try {
            if (self::$testFaultInjector !== null) {
                (self::$testFaultInjector)($module, $action, $recordId, $oldValues, $newValues, $required);
            }

            Database::execute($sql, [
                $userId,
                $module,
                $action,
                $recordId,
                $oldJson,
                $newJson,
                $ip,
                $userAgent
            ]);

            Logger::log('audit', "Audit Action: [{$module}] [{$action}] Record: {$recordId} by User: {$userId}");
        } catch (\Throwable $e) {
            Logger::error("Audit Log Failure: " . $e->getMessage());
            if ($required) { throw $e; }
        }
    }
}
